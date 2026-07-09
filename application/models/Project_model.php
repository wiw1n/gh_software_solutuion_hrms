<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Construction projects (projects table).
 * Table + users.project_id column are auto-created on first use,
 * same pattern as Setting_model / Fingerprint_model.
 */
class Project_model extends CI_Model {

    private $table = 'projects';

    public function __construct() {
        parent::__construct();
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name`        VARCHAR(150) NOT NULL,
            `location`    VARCHAR(255) NULL,
            `description` VARCHAR(255) NULL,
            `status`      ENUM('active','on_hold','completed') NOT NULL DEFAULT 'active',
            `start_date`  DATE NULL,
            `end_date`    DATE NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Upgrade the legacy projects table (pre-removal schema) in place:
        // add missing columns and convert the old status enum ('inactive' → 'on_hold').
        if (!$this->db->field_exists('location', $this->table)) {
            $this->db->query("ALTER TABLE `{$this->table}`
                ADD COLUMN `location`   VARCHAR(255) NULL AFTER `name`,
                ADD COLUMN `start_date` DATE NULL AFTER `status`,
                ADD COLUMN `end_date`   DATE NULL AFTER `start_date`");
            $this->db->query("ALTER TABLE `{$this->table}`
                MODIFY COLUMN `status` ENUM('active','inactive','on_hold','completed') NOT NULL DEFAULT 'active'");
            $this->db->query("UPDATE `{$this->table}` SET `status` = 'on_hold' WHERE `status` = 'inactive'");
            $this->db->query("ALTER TABLE `{$this->table}`
                MODIFY COLUMN `status` ENUM('active','on_hold','completed') NOT NULL DEFAULT 'active'");
        }

        if (!$this->db->field_exists('project_id', 'users')) {
            $this->db->query("ALTER TABLE `users` ADD COLUMN `project_id` INT UNSIGNED NULL AFTER `job_role_id`");
        }

        // Assignment history log: one row per assignment period, open row = current
        $had_history = $this->db->table_exists('user_project_assignments');
        $this->db->query("CREATE TABLE IF NOT EXISTS `user_project_assignments` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id`     INT UNSIGNED NOT NULL,
            `project_id`  INT UNSIGNED NOT NULL,
            `assigned_by` INT UNSIGNED NULL,
            `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ended_at`    DATETIME NULL,
            KEY `idx_upa_user` (`user_id`),
            KEY `idx_upa_project` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (!$had_history) {
            // Seed with the assignments that exist right now (real start dates unknown)
            $this->db->query("INSERT INTO `user_project_assignments` (`user_id`, `project_id`)
                SELECT `id`, `project_id` FROM `users` WHERE `project_id` IS NOT NULL");
        }

        // Project heads: users with the project_head role assigned to manage
        // a project's employees. A project can have multiple heads.
        $this->db->query("CREATE TABLE IF NOT EXISTS `project_heads` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `project_id`  INT UNSIGNED NOT NULL,
            `user_id`     INT UNSIGNED NOT NULL,
            `assigned_by` INT UNSIGNED NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_ph_project_user` (`project_id`, `user_id`),
            KEY `idx_ph_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if ($this->db->where('slug', 'project_head')->count_all_results('roles') === 0) {
            $this->db->insert('roles', [
                'name'        => 'Project Head',
                'slug'        => 'project_head',
                'description' => 'Manages the employees assigned to their projects (e.g. HR head, secretary, manager)',
            ]);
        }
    }

    public function get_all($active_only = false) {
        if ($active_only) {
            $this->db->where('status', 'active');
        }
        return $this->db->order_by('name', 'ASC')->get($this->table)->result_array();
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    public function create($data) {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    public function delete($id) {
        $this->db->where('project_id', $id)->delete('project_heads');
        return $this->db->where('id', $id)->delete($this->table);
    }

    public function name_exists($name, $exclude_id = null) {
        $this->db->where('name', $name);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function is_in_use($id) {
        return $this->db->where('project_id', $id)->count_all_results('users') > 0;
    }

    public function assigned_count($id) {
        return $this->db->where('project_id', $id)->count_all_results('users');
    }

    public function set_user_project($user_id, $project_id, $assigned_by = null) {
        $current = $this->db->select('project_id')->where('id', $user_id)->get('users')->row_array();
        if (!$current) return false;

        if ((int)($current['project_id'] ?? 0) === (int)$project_id) {
            return true; // no change, keep history as-is
        }

        $now = date('Y-m-d H:i:s');

        // Close the currently open assignment period (if any)
        $this->db
            ->where('user_id', $user_id)
            ->where('ended_at IS NULL', null, false)
            ->update('user_project_assignments', ['ended_at' => $now]);

        // Open a new period when assigning to a project (not when unassigning)
        if ($project_id) {
            $this->db->insert('user_project_assignments', [
                'user_id'     => $user_id,
                'project_id'  => $project_id,
                'assigned_by' => $assigned_by,
                'assigned_at' => $now,
            ]);
        }

        return $this->db->where('id', $user_id)->update('users', ['project_id' => $project_id]);
    }

    public function get_user_project_history($user_id) {
        return $this->db
            ->select('upa.*, p.name AS project_name, p.location AS project_location, p.status AS project_status,
                      a.first_name AS assigner_first, a.last_name AS assigner_last')
            ->from('user_project_assignments upa')
            ->join($this->table . ' p', 'p.id = upa.project_id', 'left')
            ->join('users a', 'a.id = upa.assigned_by', 'left')
            ->where('upa.user_id', $user_id)
            ->order_by('upa.assigned_at', 'DESC')
            ->order_by('upa.id', 'DESC')
            ->get()->result_array();
    }

    // ================================================================
    // Project Heads
    // ================================================================

    public function get_heads($project_id) {
        return $this->db
            ->select('ph.user_id, ph.created_at, u.first_name, u.last_name, u.email, u.employee_id, u.status,
                      a.first_name AS assigner_first, a.last_name AS assigner_last')
            ->from('project_heads ph')
            ->join('users u', 'u.id = ph.user_id')
            ->join('users a', 'a.id = ph.assigned_by', 'left')
            ->where('ph.project_id', $project_id)
            ->order_by('u.first_name', 'ASC')
            ->get()->result_array();
    }

    // Active users with the project_head role not yet heading this project
    public function get_head_candidates($project_id) {
        return $this->db
            ->select('u.id, u.first_name, u.last_name, u.employee_id')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('r.slug', 'project_head')
            ->where('u.status', 'active')
            ->where("u.id NOT IN (SELECT user_id FROM project_heads WHERE project_id = " . (int)$project_id . ")", null, false)
            ->order_by('u.first_name', 'ASC')
            ->get()->result_array();
    }

    public function add_head($project_id, $user_id, $assigned_by = null) {
        $exists = $this->db
            ->where('project_id', $project_id)
            ->where('user_id', $user_id)
            ->count_all_results('project_heads') > 0;
        if ($exists) return true;

        return $this->db->insert('project_heads', [
            'project_id'  => $project_id,
            'user_id'     => $user_id,
            'assigned_by' => $assigned_by,
        ]);
    }

    public function remove_head($project_id, $user_id) {
        return $this->db
            ->where('project_id', $project_id)
            ->where('user_id', $user_id)
            ->delete('project_heads');
    }

    // Project IDs the given user manages as head
    public function get_head_project_ids($user_id) {
        $rows = $this->db
            ->select('project_id')
            ->where('user_id', $user_id)
            ->get('project_heads')->result_array();
        return array_map('intval', array_column($rows, 'project_id'));
    }

    // ================================================================
    // DataTable
    // ================================================================

    public function get_datatable_data($limit, $start, $search, $order_col, $order_dir) {
        $columns = [0 => 'p.id', 1 => 'p.name', 2 => 'p.location', 3 => 'assigned_count', 4 => 'heads_count', 5 => 'p.status', 6 => 'p.start_date'];

        $this->db
            ->select('p.*, COUNT(u.id) AS assigned_count,
                      (SELECT COUNT(*) FROM project_heads ph WHERE ph.project_id = p.id) AS heads_count', FALSE)
            ->from($this->table . ' p')
            ->join('users u', 'u.project_id = p.id', 'left')
            ->group_by('p.id');

        if (!empty($search)) {
            $this->db->group_start()
                ->like('p.name', $search)
                ->or_like('p.location', $search)
                ->or_like('p.description', $search)
                ->group_end();
        }

        $col = $columns[$order_col] ?? 'p.id';
        $dir = strtolower($order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($col, $dir);

        if ($limit != -1) {
            $this->db->limit($limit, $start);
        }

        return $this->db->get()->result_array();
    }

    public function get_datatable_total() {
        return $this->db->count_all($this->table);
    }

    public function get_datatable_filtered($search) {
        if (!empty($search)) {
            $this->db->group_start()
                ->like('name', $search)
                ->or_like('location', $search)
                ->or_like('description', $search)
                ->group_end();
        }
        return $this->db->count_all_results($this->table);
    }
}
