<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Employees extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_role('super_admin', 'admin', 'project_head');
        $this->load->model('Employee_model');
        $this->load->model('User_model');
        $this->load->model('Job_role_model');
        $this->load->model('Project_model');
    }

    // Employee fetch honoring project-head scoping: heads only get employees
    // assigned to a project they manage; admins get everyone.
    private function get_scoped_employee($id) {
        return $this->Employee_model->get_employee($id, $this->get_scoped_project_ids());
    }

    // ----------------------------------------------------------------
    // Employee List
    // ----------------------------------------------------------------

    public function index() {
        // Project filter options — heads only see the projects they manage
        $scope    = $this->get_scoped_project_ids();
        $projects = $this->Project_model->get_all(true);
        if (is_array($scope)) {
            $projects = array_values(array_filter($projects, function ($p) use ($scope) {
                return in_array($p['id'], $scope);
            }));
        }

        $data = [
            'title'     => 'Employees',
            'page_js'   => 'employees.js',
            'roles'     => $this->User_model->get_all_roles(),
            'job_roles' => $this->Job_role_model->get_all(true),
            'projects'  => $projects,
            'can_admin' => $this->is_admin_or_above(),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('employees/index', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // Server-Side DataTable
    // ----------------------------------------------------------------

    public function get_datatable() {
        if ($this->input->method() !== 'post') show_404();

        $draw      = intval($this->input->post('draw'));
        $start     = intval($this->input->post('start'));
        $length    = intval($this->input->post('length'));
        $search    = $this->input->post('search')['value'] ?? '';
        $order     = $this->input->post('order')[0] ?? [];
        $order_col = intval($order['column'] ?? 0);
        $order_dir = $order['dir'] ?? 'asc';

        $project_id = intval($this->input->post('project_id')) ?: null;

        $scope    = $this->get_scoped_project_ids();
        $total    = $this->Employee_model->get_datatable_total($scope);
        $filtered = $this->Employee_model->get_datatable_filtered($search, $scope, $project_id);
        $rows     = $this->Employee_model->get_datatable_data($length, $start, $search, $order_col, $order_dir, $scope, $project_id);

        $data    = [];
        $row_num = $start + 1;

        foreach ($rows as $emp) {
            $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));

            $status_badge = $emp['status'] === 'active'
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Inactive</span>';

            $daily_rate = !empty($emp['daily_rate'])
                ? '&#8369;' . number_format((float)$emp['daily_rate'], 2)
                : '<span class="text-muted small">Not set</span>';

            $role_badge = '';
            if (($emp['role_slug'] ?? 'employee') !== 'employee') {
                $role_badge = ' <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size:.65em;">'
                            . htmlspecialchars($emp['role_name']) . '</span>';
            }

            $name_cell = '<div class="d-flex align-items-center gap-2">'
                    //    . '<div class="avatar-initials" data-initials="' . $initials . '"></div>'
                       . '<div>'
                       . '<div class="fw-semibold">' . htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) . $role_badge . '</div>'
                       . '<div class="text-muted small">' . htmlspecialchars($emp['email']) . '</div>'
                       . '</div></div>';

            $project_cell = !empty($emp['project_name'])
                ? '<span class="badge bg-info-subtle text-info border border-info-subtle">'
                  . htmlspecialchars($emp['project_name']) . '</span>'
                : '<span class="text-muted small">—</span>';

            $job_role_cell = !empty($emp['job_role_name'])
                ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">'
                  . htmlspecialchars($emp['job_role_name']) . '</span>'
                : '<span class="text-muted small">—</span>';

            $actions = '<a href="' . base_url('employees/view/' . $emp['id']) . '" '
                     . 'class="btn btn-sm btn-outline-primary me-1" title="View Profile">'
                     . '<i class="fas fa-id-card me-1"></i>Profile</a>'
                     . '<a href="' . base_url('employees/print_id/' . $emp['id']) . '" target="_blank" '
                     . 'class="btn btn-sm btn-outline-secondary" title="Print ID Card">'
                     . '<i class="fas fa-print"></i></a>';

            $emp_id_cell = !empty($emp['employee_id'])
                ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">'
                  . htmlspecialchars($emp['employee_id']) . '</span>'
                : '<span class="text-muted small">—</span>';

            $data[] = [
                $row_num++,
                $emp_id_cell,
                $name_cell,
                '<span class="text-muted">@</span>' . htmlspecialchars($emp['username']),
                $project_cell,
                $job_role_cell,
                $status_badge,
                $daily_rate,
                date('M d, Y', strtotime($emp['created_at'])),
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
    // Employee Profile View
    // ----------------------------------------------------------------

    public function view($id) {
        $employee = $this->get_scoped_employee($id);
        if (!$employee) show_404();

        $stats             = $this->Employee_model->get_employee_stats($id);

        $data = [
            'title'              => $employee['first_name'] . ' ' . $employee['last_name'],
            'page_js'            => 'employee_view.js',
            'employee'           => $employee,
            'stats'              => $stats,
            'job_roles'          => $this->Job_role_model->get_all(true),
            'projects'           => $this->Project_model->get_all(true),
            'emergency_contact'  => $this->Employee_model->get_emergency_contact($id),
            'can_admin'          => $this->is_admin_or_above(),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('employees/view', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // Printable Employee ID Card
    // ----------------------------------------------------------------

    public function print_id($id) {
        $employee = $this->get_scoped_employee($id);
        if (!$employee) show_404();

        $this->load->view('employees/print_id', [
            'employee'          => $employee,
            'emergency_contact' => $this->Employee_model->get_emergency_contact($id),
        ]);
    }

    // ----------------------------------------------------------------
    // Update Employee Basic Info
    // ----------------------------------------------------------------

    public function update_info($id) {
        if ($this->input->method() !== 'post') show_404();

        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $first_name  = trim($this->input->post('first_name', TRUE));
        $last_name   = trim($this->input->post('last_name', TRUE));
        $username    = trim($this->input->post('username', TRUE));
        $email       = trim($this->input->post('email', TRUE));
        $password    = $this->input->post('password');
        $job_role_id = intval($this->input->post('job_role_id')) ?: null;
        $status      = $this->input->post('status') === 'active' ? 'active' : 'inactive';
        $date_hired  = $this->input->post('date_hired');
        $date_hired  = !empty($date_hired) ? $date_hired : null;
        $timesheet_type = $this->input->post('timesheet_type');
        if (!in_array($timesheet_type, ['weekly', 'semi_monthly'])) {
            $timesheet_type = 'weekly';
        }

        if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
            $this->json(['success' => false, 'message' => 'First name, last name, username, and email are required.'], 422);
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

        $update_data = [
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'username'    => $username,
            'email'       => $email,
            'job_role_id' => $job_role_id,
            'status'      => $status,
            'date_hired'  => $date_hired,
            'timesheet_type' => $timesheet_type,
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
            $this->json(['success' => true, 'message' => 'Employee information updated successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update employee information.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Set Project Assignment
    // ----------------------------------------------------------------

    public function set_project($id) {
        if ($this->input->method() !== 'post') show_404();

        if (!$this->is_admin_or_above()) {
            $this->json(['success' => false, 'message' => 'Only administrators can change project assignments.'], 403);
            return;
        }

        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $project_id = intval($this->input->post('project_id')) ?: null;

        if ($project_id !== null) {
            $project = $this->Project_model->get_by_id($project_id);
            if (!$project) {
                $this->json(['success' => false, 'message' => 'Project not found.'], 404);
                return;
            }
        }

        if ($this->Project_model->set_user_project($id, $project_id, $this->auth_user['id'] ?? null) !== false) {
            $this->json([
                'success' => true,
                'message' => $project_id
                    ? 'Employee assigned to ' . $project['name'] . '.'
                    : 'Project assignment removed.',
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update project assignment.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Save Payroll Info
    // ----------------------------------------------------------------

    public function save_payroll_info($id) {
        if ($this->input->method() !== 'post') show_404();

        if (!$this->is_admin_or_above()) {
            $this->json(['success' => false, 'message' => 'Only administrators can edit payroll information.'], 403);
            return;
        }

        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $daily_rate         = floatval($this->input->post('daily_rate'));
        $sss_enabled        = intval((bool)$this->input->post('sss_enabled'));
        $sss_amount         = floatval($this->input->post('sss_amount'));
        $philhealth_enabled = intval((bool)$this->input->post('philhealth_enabled'));
        $philhealth_amount  = floatval($this->input->post('philhealth_amount'));
        $pagibig_enabled    = intval((bool)$this->input->post('pagibig_enabled'));
        $pagibig_amount     = floatval($this->input->post('pagibig_amount'));

        if ($daily_rate < 0) {
            $this->json(['success' => false, 'message' => 'Daily rate cannot be negative.'], 422);
            return;
        }

        $result = $this->Employee_model->save_payroll_info($id, [
            'daily_rate'          => $daily_rate,
            'sss_enabled'         => $sss_enabled,
            'sss_amount'          => $sss_amount,
            'philhealth_enabled'  => $philhealth_enabled,
            'philhealth_amount'   => $philhealth_amount,
            'pagibig_enabled'     => $pagibig_enabled,
            'pagibig_amount'      => $pagibig_amount,
        ]);

        if ($result !== false) {
            $this->json(['success' => true, 'message' => 'Payroll information saved successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to save payroll information.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Save Emergency Contact
    // ----------------------------------------------------------------

    public function save_emergency_contact($id) {
        if ($this->input->method() !== 'post') show_404();

        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $name         = trim($this->input->post('name', TRUE));
        $relationship = trim($this->input->post('relationship', TRUE));
        $phone        = trim($this->input->post('phone', TRUE));
        $phone_alt    = trim($this->input->post('phone_alt', TRUE));
        $address      = trim($this->input->post('address', TRUE));

        if (empty($name)) {
            $this->json(['success' => false, 'message' => 'Contact name is required.'], 422);
            return;
        }

        if (empty($phone)) {
            $this->json(['success' => false, 'message' => 'Primary phone number is required.'], 422);
            return;
        }

        $result = $this->Employee_model->save_emergency_contact($id, [
            'name'         => $name,
            'relationship' => $relationship !== '' ? $relationship : null,
            'phone'        => $phone,
            'phone_alt'    => $phone_alt !== '' ? $phone_alt : null,
            'address'      => $address !== '' ? $address : null,
        ]);

        if ($result !== false) {
            $this->json(['success' => true, 'message' => 'Emergency contact saved successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to save emergency contact.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Get Attendance (AJAX)
    // ----------------------------------------------------------------

    public function get_attendance($id) {
        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $records = $this->Employee_model->get_attendance($id, 30);
        $this->json(['success' => true, 'data' => $records]);
    }

    // ----------------------------------------------------------------
    // Get Salary Requests (AJAX)
    // ----------------------------------------------------------------

    public function get_salary_requests($id) {
        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $records = $this->Employee_model->get_salary_requests($id, 20);
        $this->json(['success' => true, 'data' => $records]);
    }

    // ----------------------------------------------------------------
    // Get Project Assignment History (AJAX)
    // ----------------------------------------------------------------

    public function get_project_history($id) {
        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $records = $this->Project_model->get_user_project_history($id);
        $this->json(['success' => true, 'data' => $records]);
    }

    // ----------------------------------------------------------------
    // Employee Files
    // ----------------------------------------------------------------

    private const FILE_ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip'];
    private const FILE_MAX_BYTES    = 10485760; // 10 MB

    public function get_files($id) {
        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $files = $this->Employee_model->get_files($id);
        foreach ($files as &$f) {
            $f['url'] = base_url($f['file_path']);
        }
        $this->json(['success' => true, 'data' => $files]);
    }

    public function upload_file($id) {
        if ($this->input->method() !== 'post') show_404();

        $employee = $this->get_scoped_employee($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'Employee not found.'], 404);
            return;
        }

        $name = trim($this->input->post('name', TRUE));
        if ($name === '') {
            $this->json(['success' => false, 'message' => 'Please enter a name for the file.'], 422);
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->json(['success' => false, 'message' => 'Please choose a file to upload.'], 422);
            return;
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Upload failed (error code ' . $file['error'] . '). Please try again.'], 422);
            return;
        }

        if ($file['size'] > self::FILE_MAX_BYTES) {
            $this->json(['success' => false, 'message' => 'File is too large. Maximum size is 10 MB.'], 422);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::FILE_ALLOWED_EXTS)) {
            $this->json(['success' => false, 'message' => 'File type ".' . $ext . '" is not allowed. Allowed: ' . implode(', ', self::FILE_ALLOWED_EXTS) . '.'], 422);
            return;
        }

        // Folder per employee, same convention as attendance photos
        $safe_last  = preg_replace('/[^A-Za-z0-9_\-]/', '', $employee['last_name']);
        $rel_dir    = 'assets/uploads/employee_files/' . $id . '_' . ($safe_last ?: 'user') . '/';
        $dir        = FCPATH . $rel_dir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Filename based on the given display name so direct downloads stay readable
        $safe_name = preg_replace('/[^A-Za-z0-9_\- ]/', '', $name);
        $safe_name = str_replace(' ', '_', trim($safe_name)) ?: 'file';
        $filename  = date('Ymd_His') . '_' . $safe_name . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            $this->json(['success' => false, 'message' => 'Failed to save the uploaded file.'], 500);
            return;
        }

        $this->Employee_model->add_file([
            'user_id'       => $id,
            'name'          => $name,
            'original_name' => $file['name'],
            'file_path'     => $rel_dir . $filename,
            'file_ext'      => $ext,
            'file_size'     => (int)$file['size'],
            'uploaded_by'   => $this->auth_user['id'] ?? null,
        ]);

        $this->json(['success' => true, 'message' => '"' . $name . '" uploaded successfully.']);
    }

    public function archive_file($file_id) {
        $this->_set_file_status($file_id, 'archived', 'File archived.');
    }

    public function restore_file($file_id) {
        $this->_set_file_status($file_id, 'active', 'File restored.');
    }

    private function _set_file_status($file_id, $status, $success_message) {
        if ($this->input->method() !== 'post') show_404();

        $file = $this->Employee_model->get_file($file_id);
        if (!$file) {
            $this->json(['success' => false, 'message' => 'File not found.'], 404);
            return;
        }

        // Project heads may only touch files of employees within their scope
        if (!$this->get_scoped_employee($file['user_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        if ($this->Employee_model->set_file_status($file_id, $status) !== false) {
            $this->json(['success' => true, 'message' => $success_message]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update file.'], 500);
        }
    }
}
