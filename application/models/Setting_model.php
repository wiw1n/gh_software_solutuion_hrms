<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * System-wide key/value settings (system_settings table).
 * Table is auto-created on first use, same pattern as Fingerprint_model.
 *
 * Attendance clock settings (super admin configurable in System Config):
 *   attendance_mode — 'am_pm' (AM + PM sessions, the default) | 'single'
 *   sched_am_in / sched_am_out / sched_pm_in / sched_pm_out — AM/PM schedule
 *   sched_day_in / sched_day_out — schedule for single in/out mode
 */
class Setting_model extends CI_Model {

    private $table = 'system_settings';

    private $defaults = [
        'attendance_mode' => 'am_pm',
        'sched_am_in'     => '08:00',
        'sched_am_out'    => '12:00',
        'sched_pm_in'     => '13:00',
        'sched_pm_out'    => '17:00',
        'sched_day_in'    => '08:00',
        'sched_day_out'   => '17:00',
    ];

    public function __construct() {
        parent::__construct();
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `key`        VARCHAR(64)  NOT NULL PRIMARY KEY,
            `value`      VARCHAR(255) NOT NULL,
            `updated_at` DATETIME     NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // All settings merged over defaults.
    public function get_all() {
        $settings = $this->defaults;
        foreach ($this->db->get($this->table)->result_array() as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }

    public function get($key, $fallback = null) {
        $row = $this->db->where('key', $key)->get($this->table)->row_array();
        if ($row) return $row['value'];
        return $this->defaults[$key] ?? $fallback;
    }

    public function set($key, $value) {
        $this->db->replace($this->table, [
            'key'        => $key,
            'value'      => (string)$value,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function set_many(array $pairs) {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }
    }

    // Normalized attendance clock config ('HH:MM:SS' times) for Attendance_model.
    public function attendance_config() {
        $s = $this->get_all();
        $t = function ($v, $fallback) {
            return preg_match('/^\d{2}:\d{2}$/', $v) ? $v . ':00'
                 : (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v) ? $v : $fallback);
        };
        return [
            'mode'    => $s['attendance_mode'] === 'single' ? 'single' : 'am_pm',
            'am_in'   => $t($s['sched_am_in'],   '08:00:00'),
            'am_out'  => $t($s['sched_am_out'],  '12:00:00'),
            'pm_in'   => $t($s['sched_pm_in'],   '13:00:00'),
            'pm_out'  => $t($s['sched_pm_out'],  '17:00:00'),
            'day_in'  => $t($s['sched_day_in'],  '08:00:00'),
            'day_out' => $t($s['sched_day_out'], '17:00:00'),
        ];
    }
}
