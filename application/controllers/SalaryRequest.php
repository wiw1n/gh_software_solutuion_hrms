<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class SalaryRequest extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_login();
        $this->load->model(['Salary_request_model', 'Notification_model', 'User_model']);
    }

    // ----------------------------------------------------------------
    // AJAX GET: count of pending requests (for sidebar badge — admin only)
    // ----------------------------------------------------------------

    // True when the logged-in user may file/edit a request on behalf of $user_id.
    // Super admin: any employee; project head: only employees on their projects.
    private function can_request_for($user_id) {
        $this->load->model('Employee_model');
        if ($this->is_super_admin()) {
            return (bool)$this->Employee_model->get_employee($user_id);
        }
        if (!$this->is_project_head()) return false;
        return (bool)$this->Employee_model->get_employee($user_id, $this->get_scoped_project_ids());
    }

    public function pending_count() {
        if (!$this->is_super_admin()) {
            $this->json(['success' => true, 'count' => 0]);
            return;
        }
        $count = $this->db
            ->where('status', 'pending')
            ->count_all_results('salary_requests');
        $this->json(['success' => true, 'count' => (int)$count]);
    }

    // ----------------------------------------------------------------
    // Admin: list page
    // ----------------------------------------------------------------

    public function index() {
        $this->require_role('super_admin');

        $filter   = $this->input->get('status') ?: 'all';
        $status   = in_array($filter, ['pending', 'approved', 'rejected']) ? $filter : null;
        $requests = $this->Salary_request_model->get_all_requests($status);

        $data = [
            'title'    => 'Salary Requests',
            'page_js'  => 'salary_requests.js',
            'requests' => $requests,
            'filter'   => $filter,
        ];

        $this->load->view('layouts/header',       $data);
        $this->load->view('layouts/sidebar',      $data);
        $this->load->view('salary_requests/index', $data);
        $this->load->view('layouts/footer',       $data);
    }

    // ----------------------------------------------------------------
    // AJAX POST: project head (or super admin) files a request on
    // behalf of an employee. Employees can no longer request directly.
    // ----------------------------------------------------------------

    public function request() {
        if ($this->input->method() !== 'post') show_404();

        $user_id    = (int)$this->input->post('user_id');
        $type       = $this->input->post('type');
        $amount     = (float)$this->input->post('amount');
        $notes      = trim((string)$this->input->post('notes', true));
        $week_start = $this->input->post('week_start') ?: null;

        if (!$user_id || !$this->can_request_for($user_id)) {
            $this->json(['success' => false, 'message' => 'Only the employee\'s project head can file advance/borrow requests.'], 403);
            return;
        }

        if (!in_array($type, ['advance', 'borrow'])) {
            $this->json(['success' => false, 'message' => 'Invalid request type.'], 422);
            return;
        }

        if ($amount <= 0 || $amount > Salary_request_model::MAX_AMOUNT) {
            $this->json(['success' => false, 'message' => 'Amount must be between ₱1 and ₱1,000.'], 422);
            return;
        }

        $result = $this->Salary_request_model->create_request($user_id, $type, $amount, $notes ?: null, $week_start);

        if ($result === false) {
            $label = $type === 'advance' ? 'advance payment' : 'borrow money';
            $this->json(['success' => false, 'message' => 'A ' . $label . ' request already exists for this week.'], 409);
            return;
        }

        // Notify super admins (they review the requests)
        $employee = $this->Employee_model->get_employee($user_id);
        $name     = $employee['first_name'] . ' ' . $employee['last_name'];
        $filer    = $this->auth_user['first_name'] . ' ' . $this->auth_user['last_name'];
        $label    = $type === 'advance' ? 'Advance Payment' : 'Borrow Money';
        $this->Notification_model->notify_admins(
            'salaryRequest',
            $name . ' — ' . $label . ' Request',
            '₱' . number_format($amount, 2)
                . ($week_start ? '  ·  Week of ' . date('M d', strtotime($week_start)) : '')
                . '  ·  Filed by ' . $filer,
            base_url('salaryRequest'),
            ['super_admin']
        );

        $this->json(['success' => true, 'message' => $label . ' request of ₱' . number_format($amount, 2) . ' submitted for ' . $name . '.']);
    }

    // ----------------------------------------------------------------
    // AJAX POST: project head (or super admin) edits a pending request
    // of an employee they manage
    // ----------------------------------------------------------------

    public function update($id) {
        if ($this->input->method() !== 'post') show_404();

        $req = $this->Salary_request_model->get_by_id((int)$id);
        if (!$req || !$this->can_request_for((int)$req['user_id'])) {
            $this->json(['success' => false, 'message' => 'Request not found.'], 404);
            return;
        }
        if ($req['status'] !== 'pending') {
            $this->json(['success' => false, 'message' => 'Only pending requests can be edited.'], 409);
            return;
        }

        $amount = (float)$this->input->post('amount');
        $notes  = trim((string)$this->input->post('notes', true));

        if ($amount <= 0 || $amount > Salary_request_model::MAX_AMOUNT) {
            $this->json(['success' => false, 'message' => 'Amount must be between ₱1 and ₱1,000.'], 422);
            return;
        }

        $this->Salary_request_model->update_request((int)$id, $amount, $notes ?: null);
        $this->json(['success' => true, 'message' => 'Request updated successfully.']);
    }

    // ----------------------------------------------------------------
    // Admin AJAX: approve
    // ----------------------------------------------------------------

    public function approve($id) {
        $this->require_role('super_admin');
        if ($this->input->method() !== 'post') show_404();

        $req = $this->Salary_request_model->get_by_id((int)$id);
        if (!$req) {
            $this->json(['success' => false, 'message' => 'Request not found.'], 404);
            return;
        }
        if ($req['status'] !== 'pending') {
            $this->json(['success' => false, 'message' => 'Only pending requests can be approved.'], 409);
            return;
        }

        $admin_notes = trim($this->input->post('admin_notes', true));
        $this->Salary_request_model->update_status((int)$id, 'approved', (int)$this->auth_user['id'], $admin_notes ?: null);

        // Notify the employee
        $label = $req['type'] === 'advance' ? 'Advance Payment' : 'Borrow Money';
        $this->Notification_model->create(
            (int)$req['user_id'],
            'salary_approved',
            'Your ' . $label . ' request was Approved ✓',
            '₱' . number_format($req['amount'], 2) . ($admin_notes ? '  ·  ' . $admin_notes : ''),
            base_url('attendance')
        );

        $this->json(['success' => true, 'message' => 'Request approved.']);
    }

    // ----------------------------------------------------------------
    // Admin AJAX: reject
    // ----------------------------------------------------------------

    public function reject($id) {
        $this->require_role('super_admin');
        if ($this->input->method() !== 'post') show_404();

        $req = $this->Salary_request_model->get_by_id((int)$id);
        if (!$req) {
            $this->json(['success' => false, 'message' => 'Request not found.'], 404);
            return;
        }
        if ($req['status'] !== 'pending') {
            $this->json(['success' => false, 'message' => 'Only pending requests can be rejected.'], 409);
            return;
        }

        $admin_notes = trim($this->input->post('admin_notes', true));
        $this->Salary_request_model->update_status((int)$id, 'rejected', (int)$this->auth_user['id'], $admin_notes ?: null);

        // Notify the employee
        $label = $req['type'] === 'advance' ? 'Advance Payment' : 'Borrow Money';
        $this->Notification_model->create(
            (int)$req['user_id'],
            'salary_rejected',
            'Your ' . $label . ' request was Declined ✗',
            '₱' . number_format($req['amount'], 2) . ($admin_notes ? '  ·  ' . $admin_notes : ''),
            base_url('attendance')
        );

        $this->json(['success' => true, 'message' => 'Request declined.']);
    }
}
