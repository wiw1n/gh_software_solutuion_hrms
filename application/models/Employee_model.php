<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Employee_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        // Auto-create employee_files table (same pattern as Project_model / Setting_model)
        $this->db->query("CREATE TABLE IF NOT EXISTS `employee_files` (
            `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id`       INT UNSIGNED NOT NULL,
            `name`          VARCHAR(150) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `file_path`     VARCHAR(255) NOT NULL,
            `file_ext`      VARCHAR(10)  NOT NULL,
            `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
            `status`        ENUM('active','archived') NOT NULL DEFAULT 'active',
            `uploaded_by`   INT UNSIGNED NULL,
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `archived_at`   DATETIME NULL,
            KEY `idx_ef_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // ================================================================
    // Server-Side DataTable (employees only)
    // ================================================================

    /**
     * Project-head scoping: when $project_ids is an array, only employees
     * (role slug = employee) assigned to one of those projects are returned.
     * NULL means no scoping (admin / super_admin).
     */
    private function apply_project_scope($project_ids) {
        if (!is_array($project_ids)) return;

        $this->db->where('r.slug', 'employee');
        if (empty($project_ids)) {
            $this->db->where('1 = 0', null, false); // head with no projects sees nothing
        } else {
            $this->db->where_in('u.project_id', $project_ids);
        }
    }

    public function get_datatable_data($limit, $start, $search, $order_col, $order_dir, $project_ids = null, $filter_project_id = null) {
        $columns = [
            0 => 'u.id',
            1 => 'u.employee_id',
            2 => 'u.first_name',
            3 => 'u.username',
            4 => 'p.name',
            5 => 'jr.name',
            6 => 'u.status',
            7 => 'epi.daily_rate',
            8 => 'u.created_at',
        ];

        $this->db
            ->select('u.id, u.employee_id, u.first_name, u.last_name, u.username, u.email, u.status, u.created_at,
                      epi.daily_rate,
                      jr.name AS job_role_name,
                      p.name AS project_name,
                      r.name AS role_name, r.slug AS role_slug', FALSE)
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('employee_payroll_info epi', 'epi.user_id = u.id', 'left')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->join('projects p', 'p.id = u.project_id', 'left')
            ->where('r.slug !=', 'super_admin')
            ->group_by('u.id');

        $this->apply_project_scope($project_ids);

        if (!empty($filter_project_id)) {
            $this->db->where('u.project_id', $filter_project_id);
        }

        if (!empty($search)) {
            $this->db->group_start()
                ->like('u.employee_id', $search)
                ->or_like('u.first_name', $search)
                ->or_like('u.last_name', $search)
                ->or_like('u.username', $search)
                ->or_like('u.email', $search)
                ->group_end();
        }

        $col = $columns[$order_col] ?? 'u.id';
        $dir = strtolower($order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($col, $dir);

        if ($limit != -1) {
            $this->db->limit($limit, $start);
        }

        return $this->db->get()->result_array();
    }

    public function get_datatable_total($project_ids = null) {
        $this->db
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('r.slug !=', 'super_admin');

        $this->apply_project_scope($project_ids);

        return $this->db->count_all_results();
    }

    public function get_datatable_filtered($search, $project_ids = null, $filter_project_id = null) {
        $this->db
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('r.slug !=', 'super_admin');

        $this->apply_project_scope($project_ids);

        if (!empty($filter_project_id)) {
            $this->db->where('u.project_id', $filter_project_id);
        }

        if (!empty($search)) {
            $this->db->group_start()
                ->like('u.employee_id', $search)
                ->or_like('u.first_name', $search)
                ->or_like('u.last_name', $search)
                ->or_like('u.username', $search)
                ->or_like('u.email', $search)
                ->group_end();
        }

        return $this->db->count_all_results();
    }

    // ================================================================
    // Get Single Employee (with payroll info joined)
    // ================================================================

    public function get_employee($id, $project_ids = null) {
        $this->apply_project_scope($project_ids);
        return $this->db
            ->select('u.id, u.employee_id, u.first_name, u.last_name, u.username, u.email, u.status, u.created_at, u.last_login, u.date_hired, u.timesheet_type,
                      r.name AS role_name, r.slug AS role_slug,
                      jr.id AS job_role_id, jr.name AS job_role_name,
                      p.id AS project_id, p.name AS project_name, p.location AS project_location, p.status AS project_status,
                      epi.daily_rate, epi.sss_enabled, epi.sss_amount,
                      epi.philhealth_enabled, epi.philhealth_amount,
                      epi.pagibig_enabled, epi.pagibig_amount')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('employee_payroll_info epi', 'epi.user_id = u.id', 'left')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->join('projects p', 'p.id = u.project_id', 'left')
            ->where('u.id', $id)
            ->where('r.slug !=', 'super_admin')
            ->get()->row_array();
    }

    // ================================================================
    // Payroll Info
    // ================================================================

    public function get_payroll_info($user_id) {
        return $this->db->where('user_id', $user_id)->get('employee_payroll_info')->row_array();
    }

    public function save_payroll_info($user_id, $data) {
        $existing = $this->get_payroll_info($user_id);
        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->db->where('user_id', $user_id)->update('employee_payroll_info', $data);
        }
        $data['user_id'] = $user_id;
        return $this->db->insert('employee_payroll_info', $data);
    }

    // ================================================================
    // Attendance
    // ================================================================

    public function get_attendance($user_id, $limit = 30) {
        return $this->db
            ->where('user_id', $user_id)
            ->order_by('date', 'DESC')
            ->limit($limit)
            ->get('attendance')
            ->result_array();
    }

    // ================================================================
    // Salary Requests
    // ================================================================

    public function get_salary_requests($user_id, $limit = 20) {
        return $this->db
            ->select('sr.*, u.first_name AS reviewer_first, u.last_name AS reviewer_last')
            ->from('salary_requests sr')
            ->join('users u', 'u.id = sr.reviewed_by', 'left')
            ->where('sr.user_id', $user_id)
            ->order_by('sr.created_at', 'DESC')
            ->limit($limit)
            ->get()->result_array();
    }

    // ================================================================
    // Emergency Contact
    // ================================================================

    public function get_emergency_contact($user_id) {
        return $this->db
            ->where('user_id', $user_id)
            ->get('employee_emergency_contacts')
            ->row_array();
    }

    public function save_emergency_contact($user_id, $data) {
        $existing = $this->get_emergency_contact($user_id);
        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->db->where('user_id', $user_id)->update('employee_emergency_contacts', $data);
        }
        $data['user_id'] = $user_id;
        return $this->db->insert('employee_emergency_contacts', $data);
    }

    // ================================================================
    // Employee Files
    // ================================================================

    public function get_files($user_id) {
        return $this->db
            ->select('ef.*, u.first_name AS uploader_first, u.last_name AS uploader_last')
            ->from('employee_files ef')
            ->join('users u', 'u.id = ef.uploaded_by', 'left')
            ->where('ef.user_id', $user_id)
            ->order_by('ef.created_at', 'DESC')
            ->get()->result_array();
    }

    public function get_file($file_id) {
        return $this->db->where('id', $file_id)->get('employee_files')->row_array();
    }

    public function add_file($data) {
        $this->db->insert('employee_files', $data);
        return $this->db->insert_id();
    }

    public function set_file_status($file_id, $status) {
        return $this->db->where('id', $file_id)->update('employee_files', [
            'status'      => $status,
            'archived_at' => $status === 'archived' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    // ================================================================
    // Stats for profile header
    // ================================================================

    public function get_employee_stats($user_id) {
        $present_count = $this->db
            ->where('user_id', $user_id)
            ->where('status', 'present')
            ->count_all_results('attendance');

        $pending_requests = $this->db
            ->where('user_id', $user_id)
            ->where('status', 'pending')
            ->count_all_results('salary_requests');

        return [
            'present_count'    => $present_count,
            'pending_requests' => $pending_requests,
        ];
    }
}
