<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_role('super_admin', 'admin');
        $this->load->model('User_model');
        $this->load->model('Job_role_model');
        $this->load->model('Project_model'); // ensures the project_head role exists before the role dropdown renders
    }

    public function index() {
        $data = [
            'title'      => 'User Management',
            'page_js'    => 'users.js',
            'roles'      => $this->User_model->get_all_roles(),
            'job_roles'  => $this->Job_role_model->get_all(true),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('users/index', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // Server-Side DataTable
    // ----------------------------------------------------------------

    public function get_datatable() {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $draw      = intval($this->input->post('draw'));
        $start     = intval($this->input->post('start'));
        $length    = intval($this->input->post('length'));
        $search    = $this->input->post('search')['value'] ?? '';
        $order     = $this->input->post('order')[0] ?? [];
        $order_col = intval($order['column'] ?? 0);
        $order_dir = $order['dir'] ?? 'asc';

        $total    = $this->User_model->get_datatable_total();
        $filtered = $this->User_model->get_datatable_filtered($search);
        $rows     = $this->User_model->get_datatable_data($length, $start, $search, $order_col, $order_dir);

        $data    = [];
        $row_num = $start + 1;

        foreach ($rows as $u) {
            $status_badge = $u['status'] === 'active'
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Inactive</span>';

            $role_color = match($u['role_slug']) {
                'super_admin'  => 'danger',
                'admin'        => 'primary',
                'project_head' => 'info',
                default        => 'secondary'
            };
            $role_badge = '<span class="badge bg-' . $role_color . '-subtle text-' . $role_color . ' border border-' . $role_color . '-subtle">'
                        . htmlspecialchars($u['role_name']) . '</span>';

            $can_edit   = true;
            $can_delete = (int)$u['id'] !== (int)$this->auth_user['id'];

            if (!$this->is_super_admin() && $u['role_slug'] === 'super_admin') {
                $can_edit   = false;
                $can_delete = false;
            }

            $actions = '';
            if ($can_edit) {
                $actions .= '<button class="btn btn-sm btn-outline-info btn-edit me-1" data-id="' . $u['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>';
                $toggle_class = $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success';
                $toggle_title = $u['status'] === 'active' ? 'Deactivate' : 'Activate';
                $actions .= '<button class="btn btn-sm ' . $toggle_class . ' btn-toggle me-1" data-id="' . $u['id'] . '" title="' . $toggle_title . '"><i class="fas fa-toggle-' . ($u['status'] === 'active' ? 'on' : 'off') . '"></i></button>';
            }
            if ($can_delete) {
                $actions .= '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' . $u['id'] . '" data-name="' . htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) . '" title="Delete"><i class="fas fa-trash"></i></button>';
            }
            if (empty($actions)) {
                $actions = '<span class="text-muted small">—</span>';
            }

            $emp_id_html = !empty($u['employee_id'])
                ? '<span class="text-primary fw-semibold">' . htmlspecialchars($u['employee_id']) . '</span> · '
                : '';

            $name_cell = '<div class="d-flex align-items-center gap-2">'
                       . '<div class="avatar-initials" data-initials="' . strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)) . '"></div>'
                       . '<div><div class="fw-semibold">' . htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) . '</div>'
                       . '<div class="text-muted small">' . $emp_id_html . htmlspecialchars($u['email']) . '</div></div></div>';

            $job_role_badge = !empty($u['job_role_name'])
                ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">'
                  . htmlspecialchars($u['job_role_name']) . '</span>'
                : '<span class="text-muted small">—</span>';

            $data[] = [
                $row_num++,
                $name_cell,
                '<span class="text-muted">@</span>' . htmlspecialchars($u['username']),
                $role_badge,
                $job_role_badge,
                $status_badge,
                date('M d, Y', strtotime($u['created_at'])),
                $actions,
            ];
        }

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => (int)$total,
            'recordsFiltered' => (int)$filtered,
            'data'            => $data,
        ]);
    }

    // ----------------------------------------------------------------
    // Create
    // ----------------------------------------------------------------

    public function create() {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $first_name  = trim($this->input->post('first_name', TRUE));
        $last_name   = trim($this->input->post('last_name', TRUE));
        $username    = trim($this->input->post('username', TRUE));
        $email       = trim($this->input->post('email', TRUE));
        $password    = $this->input->post('password');
        $role_id     = intval($this->input->post('role_id'));
        $job_role_id = intval($this->input->post('job_role_id')) ?: null;
        $status      = $this->input->post('status') === 'active' ? 'active' : 'inactive';
        $date_hired  = $this->input->post('date_hired');
        $date_hired  = !empty($date_hired) ? $date_hired : null;
        $timesheet_type = $this->input->post('timesheet_type');
        if (!in_array($timesheet_type, ['weekly', 'semi_monthly'])) {
            $timesheet_type = 'weekly';
        }

        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || !$role_id) {
            $this->json(['success' => false, 'message' => 'All fields are required.'], 422);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
            return;
        }

        if (strlen($password) < 6) {
            $this->json(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
            return;
        }

        if ($this->User_model->username_exists($username)) {
            $this->json(['success' => false, 'message' => 'Username is already taken.'], 422);
            return;
        }

        if ($this->User_model->email_exists($email)) {
            $this->json(['success' => false, 'message' => 'Email address is already in use.'], 422);
            return;
        }

        if (!$this->is_super_admin()) {
            $role = $this->User_model->get_role_by_id($role_id);
            if ($role && $role['slug'] === 'super_admin') {
                $this->json(['success' => false, 'message' => 'You are not allowed to assign the Super Admin role.'], 403);
                return;
            }
        }

        $employee_id = $this->User_model->generate_employee_id();

        $id = $this->User_model->create([
            'employee_id' => $employee_id,
            'role_id'     => $role_id,
            'job_role_id' => $job_role_id,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'username'    => $username,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'status'      => $status,
            'date_hired'  => $date_hired,
            'timesheet_type' => $timesheet_type,
        ]);

        if ($id) {
            $this->json([
                'success'     => true,
                'message'     => 'User created successfully. Employee ID: ' . $employee_id,
                'employee_id' => $employee_id,
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to create user. Please try again.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Get single user (for edit modal)
    // ----------------------------------------------------------------

    public function get_user($id) {
        $user = $this->User_model->get_by_id($id);

        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        if (!$this->is_super_admin() && $user['role_slug'] === 'super_admin') {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        unset($user['password']);
        $this->json(['success' => true, 'data' => $user]);
    }

    // ----------------------------------------------------------------
    // Update
    // ----------------------------------------------------------------

    public function update($id) {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $user = $this->User_model->get_by_id($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        if (!$this->is_super_admin() && $user['role_slug'] === 'super_admin') {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $first_name  = trim($this->input->post('first_name', TRUE));
        $last_name   = trim($this->input->post('last_name', TRUE));
        $username    = trim($this->input->post('username', TRUE));
        $email       = trim($this->input->post('email', TRUE));
        $password    = $this->input->post('password');
        $role_id     = intval($this->input->post('role_id'));
        $job_role_id = intval($this->input->post('job_role_id')) ?: null;
        $status      = $this->input->post('status') === 'active' ? 'active' : 'inactive';

        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || !$role_id) {
            $this->json(['success' => false, 'message' => 'All required fields must be filled.'], 422);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
            return;
        }

        if ($this->User_model->username_exists($username, $id)) {
            $this->json(['success' => false, 'message' => 'Username is already taken.'], 422);
            return;
        }

        if ($this->User_model->email_exists($email, $id)) {
            $this->json(['success' => false, 'message' => 'Email address is already in use.'], 422);
            return;
        }

        if (!$this->is_super_admin()) {
            $role = $this->User_model->get_role_by_id($role_id);
            if ($role && $role['slug'] === 'super_admin') {
                $this->json(['success' => false, 'message' => 'You are not allowed to assign the Super Admin role.'], 403);
                return;
            }
        }

        $update_data = [
            'role_id'     => $role_id,
            'job_role_id' => $job_role_id,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'username'    => $username,
            'email'       => $email,
            'status'      => $status,
        ];

        if (!empty($password)) {
            if (strlen($password) < 6) {
                $this->json(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
                return;
            }
            $update_data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $result = $this->User_model->update($id, $update_data);
        if ($result !== false) {
            $this->json(['success' => true, 'message' => 'User updated successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update user. Please try again.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Delete
    // ----------------------------------------------------------------

    public function delete($id) {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        if ((int)$id === (int)$this->auth_user['id']) {
            $this->json(['success' => false, 'message' => 'You cannot delete your own account.'], 403);
            return;
        }

        $user = $this->User_model->get_by_id($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        if (!$this->is_super_admin() && $user['role_slug'] === 'super_admin') {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        if ($this->User_model->delete($id)) {
            $this->json(['success' => true, 'message' => 'User deleted successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to delete user. Please try again.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Toggle Status
    // ----------------------------------------------------------------

    public function toggle_status($id) {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        if ((int)$id === (int)$this->auth_user['id']) {
            $this->json(['success' => false, 'message' => 'You cannot change your own status.'], 403);
            return;
        }

        $user = $this->User_model->get_by_id($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        if (!$this->is_super_admin() && $user['role_slug'] === 'super_admin') {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        if ($this->User_model->toggle_status($id)) {
            $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
            $this->json(['success' => true, 'message' => 'User status updated to ' . $new_status . '.', 'status' => $new_status]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update status.'], 500);
        }
    }
}
