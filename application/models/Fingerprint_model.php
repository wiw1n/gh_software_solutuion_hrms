<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Fingerprint_model extends CI_Model {

    private $table          = 'fingerprints';
    private $settings_table = 'scanner_settings';

    // Kiosk settings and their defaults — created on first run.
    private $default_settings = [
        'station_name'         => 'Main Entrance',
        'duplicate_guard_secs' => '60',  // ignore repeat scans within N seconds of clock-in
        'allow_manual_input'   => '1',   // show badge/username fallback input on kiosk
        'play_sounds'          => '1',   // beep on scan success/failure
        'auto_start_scanner'   => '1',   // resume fingerprint listening automatically
        'log_refresh_secs'     => '30',  // auto refresh interval of today's log table
    ];

    public function __construct() {
        parent::__construct();
        $this->_ensure_tables();
    }

    // Creates the module's tables on first use (project has no migration runner).
    private function _ensure_tables() {
        if (!$this->db->table_exists($this->table)) {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `{$this->table}` (
                    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id`       INT UNSIGNED NOT NULL,
                    `credential_id` VARCHAR(500) NOT NULL,
                    `public_key`    TEXT NOT NULL,
                    `sign_count`    INT UNSIGNED NOT NULL DEFAULT 0,
                    `label`         VARCHAR(100) DEFAULT NULL,
                    `created_by`    INT UNSIGNED DEFAULT NULL,
                    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `last_used_at`  DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_credential` (`credential_id`(191)),
                    KEY `idx_fp_user` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        if (!$this->db->table_exists($this->settings_table)) {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `{$this->settings_table}` (
                    `name`  VARCHAR(64) NOT NULL,
                    `value` VARCHAR(255) NOT NULL,
                    PRIMARY KEY (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }

    // ================================================================
    // Credentials
    // ================================================================

    public function create($data) {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function get_by_credential_id($credential_id) {
        return $this->db
            ->where('credential_id', $credential_id)
            ->get($this->table)->row_array();
    }

    public function get_by_user($user_id) {
        return $this->db
            ->where('user_id', $user_id)
            ->order_by('created_at', 'ASC')
            ->get($this->table)->result_array();
    }

    // All registered fingers with owner info (for the manage list).
    public function get_all_with_users() {
        return $this->db
            ->select('f.id, f.user_id, f.label, f.created_at, f.last_used_at,
                      u.first_name, u.last_name, u.username')
            ->from($this->table . ' f')
            ->join('users u', 'u.id = f.user_id')
            ->order_by('u.first_name', 'ASC')
            ->order_by('f.created_at', 'ASC')
            ->get()->result_array();
    }

    public function credential_ids_for_user($user_id) {
        $rows = $this->db
            ->select('credential_id')
            ->where('user_id', $user_id)
            ->get($this->table)->result_array();
        return array_column($rows, 'credential_id');
    }

    public function mark_used($id, $sign_count) {
        return $this->db->where('id', $id)->update($this->table, [
            'sign_count'   => $sign_count,
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete($id) {
        return $this->db->where('id', $id)->delete($this->table);
    }

    // Active employees/admins with how many fingers each has registered.
    public function get_registrable_users() {
        return $this->db
            ->select('u.id, u.first_name, u.last_name, u.username,
                      jr.name AS job_role_name,
                      COUNT(f.id) AS finger_count')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->join($this->table . ' f', 'f.user_id = u.id', 'left')
            ->where('u.status', 'active')
            ->where_in('r.slug', ['admin', 'employee'])
            ->group_by('u.id')
            ->order_by('u.first_name', 'ASC')
            ->get()->result_array();
    }

    // ================================================================
    // Settings
    // ================================================================

    public function get_settings() {
        $rows     = $this->db->get($this->settings_table)->result_array();
        $settings = $this->default_settings;
        foreach ($rows as $r) {
            if (array_key_exists($r['name'], $settings)) {
                $settings[$r['name']] = $r['value'];
            }
        }
        return $settings;
    }

    public function save_settings(array $settings) {
        foreach ($settings as $name => $value) {
            if (!array_key_exists($name, $this->default_settings)) continue;
            $this->db->replace($this->settings_table, [
                'name'  => $name,
                'value' => (string)$value,
            ]);
        }
        return true;
    }
}
