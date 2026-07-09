<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

    private $table       = 'users';
    private $roles_table = 'roles';

    // ================================================================
    // Server-Side DataTable
    // ================================================================

    public function get_datatable_data($limit, $start, $search, $order_col, $order_dir) {
        $columns = [
            0 => 'u.id',
            1 => 'u.first_name',
            2 => 'u.username',
            3 => 'r.name',
            4 => 'jr.name',
            5 => 'u.status',
            6 => 'u.created_at',
        ];

        $this->db
            ->select('u.id, u.employee_id, u.first_name, u.last_name, u.username, u.email, u.status, u.created_at,
                      u.job_role_id,
                      r.name AS role_name, r.slug AS role_slug,
                      jr.name AS job_role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left');

        if (!empty($search)) {
            $this->db->group_start()
                ->like('u.employee_id', $search)
                ->or_like('u.first_name', $search)
                ->or_like('u.last_name', $search)
                ->or_like('u.username', $search)
                ->or_like('u.email', $search)
                ->or_like('r.name', $search)
                ->or_like('jr.name', $search)
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

    public function get_datatable_total() {
        return $this->db->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->count_all_results();
    }

    public function get_datatable_filtered($search) {
        $this->db->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left');

        if (!empty($search)) {
            $this->db->group_start()
                ->like('u.employee_id', $search)
                ->or_like('u.first_name', $search)
                ->or_like('u.last_name', $search)
                ->or_like('u.username', $search)
                ->or_like('u.email', $search)
                ->or_like('r.name', $search)
                ->or_like('jr.name', $search)
                ->group_end();
        }

        return $this->db->count_all_results();
    }

    // ================================================================
    // CRUD Operations
    // ================================================================

    public function get_by_id($id) {
        return $this->db
            ->select('u.*, r.name AS role_name, r.slug AS role_slug, jr.name AS job_role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->where('u.id', $id)
            ->get()->row_array();
    }

    // Find an active user by scanned ID badge code.
    // The Employee ID (KMR#####) is the badge basis; the legacy
    // barcode column and username still work as fallbacks.
    public function find_by_scan_code($code) {
        $this->db
            ->select('u.*, r.name AS role_name, r.slug AS role_slug, jr.name AS job_role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->where('u.status', 'active')
            ->group_start()
            ->where('u.employee_id', $code);

        if ($this->db->field_exists('barcode', 'users')) {
            $this->db->or_where('u.barcode', $code);
        }
        $this->db->or_where('u.username', $code);

        return $this->db->group_end()->get()->row_array();
    }

    public function get_by_username($username) {
        return $this->db
            ->select('u.*, r.name AS role_name, r.slug AS role_slug, jr.name AS job_role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->where('u.username', $username)
            ->get()->row_array();
    }

    public function create($data) {
        if (empty($data['employee_id'])) {
            $data['employee_id'] = $this->generate_employee_id();
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    // Generate the next Employee ID in the KMR##### sequence.
    public function generate_employee_id() {
        $row = $this->db
            ->select("MAX(CAST(SUBSTRING(employee_id, 4) AS UNSIGNED)) AS max_num", FALSE)
            ->like('employee_id', 'KMR', 'after')
            ->get($this->table)
            ->row_array();

        $next = intval($row['max_num'] ?? 0) + 1;
        return 'KMR' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    public function delete($id) {
        return $this->db->where('id', $id)->delete($this->table);
    }

    public function toggle_status($id) {
        $user = $this->get_by_id($id);
        if (!$user) return false;
        $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
        return $this->update($id, ['status' => $new_status]);
    }

    public function update_last_login($id) {
        return $this->update($id, ['last_login' => date('Y-m-d H:i:s')]);
    }

    // ================================================================
    // Validation Helpers
    // ================================================================

    public function username_exists($username, $exclude_id = null) {
        $this->db->where('username', $username);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function email_exists($email, $exclude_id = null) {
        $this->db->where('email', $email);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    // ================================================================
    // Roles
    // ================================================================

    public function get_all_roles() {
        return $this->db->get($this->roles_table)->result_array();
    }

    public function get_role_by_id($id) {
        return $this->db->where('id', $id)->get($this->roles_table)->row_array();
    }

    // ================================================================
    // Dashboard Stats
    // ================================================================

    public function count_total_users() {
        return $this->db->count_all($this->table);
    }

    public function count_active_users() {
        return $this->db->where('status', 'active')->count_all_results($this->table);
    }

    public function count_by_role($role_slug) {
        return $this->db
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('r.slug', $role_slug)
            ->count_all_results();
    }
}
