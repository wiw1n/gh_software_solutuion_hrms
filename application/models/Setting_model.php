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
 *
 * System / branding settings (super admin configurable in System Config):
 *   system_name  — app name shown in the sidebar brand and page titles
 *   theme        — color preset slug (see self::THEMES)
 *   company_*    — company profile printed on form letterheads
 *   company_logo — relative path of the uploaded logo (empty = none)
 */
class Setting_model extends CI_Model {

    private $table = 'system_settings';

    // Color presets applied through the :root CSS variables in the layout header.
    const THEMES = [
        'blue'   => ['label' => 'Royal Blue',  'accent' => '#4361ee', 'accent_dark' => '#3451d1', 'sidebar' => '#1a2442'],
        'teal'   => ['label' => 'Teal',        'accent' => '#0d9488', 'accent_dark' => '#0f766e', 'sidebar' => '#12302d'],
        'green'  => ['label' => 'Green',       'accent' => '#16a34a', 'accent_dark' => '#15803d', 'sidebar' => '#14291b'],
        'purple' => ['label' => 'Purple',      'accent' => '#7c3aed', 'accent_dark' => '#6d28d9', 'sidebar' => '#231a3d'],
        'maroon' => ['label' => 'Maroon',      'accent' => '#b91c1c', 'accent_dark' => '#991b1b', 'sidebar' => '#2c1515'],
        'orange' => ['label' => 'Orange',      'accent' => '#ea580c', 'accent_dark' => '#c2410c', 'sidebar' => '#2f1d10'],
        'slate'  => ['label' => 'Slate',       'accent' => '#475569', 'accent_dark' => '#334155', 'sidebar' => '#0f172a'],
    ];

    private $defaults = [
        'attendance_mode' => 'am_pm',
        'sched_am_in'     => '08:00',
        'sched_am_out'    => '12:00',
        'sched_pm_in'     => '13:00',
        'sched_pm_out'    => '17:00',
        'sched_day_in'    => '08:00',
        'sched_day_out'   => '17:00',
        'system_name'     => 'GH Software Solution',
        'theme'           => 'blue',
        'company_name'    => 'KAMARI CONSTRUCTION AND SUPPLY',
        'company_tagline' => 'GENERAL ENGINEERING GENERAL BUILDING',
        'company_address' => 'Msgr. Lino Gonzaga St. Brgy. II Poblacion, Jaro, Leyte',
        'company_email'   => 'kamari.construction@gmail.com',
        'company_phone'   => '+63910-976-0000',
        'company_logo'    => '',
    ];

    // Settings are read by the layout header + sidebar on every page; cache per request.
    private static $cache = null;

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
        if (self::$cache !== null) {
            return self::$cache;
        }
        $settings = $this->defaults;
        foreach ($this->db->get($this->table)->result_array() as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return self::$cache = $settings;
    }

    public function get($key, $fallback = null) {
        $row = $this->db->where('key', $key)->get($this->table)->row_array();
        if ($row) return $row['value'];
        return $this->defaults[$key] ?? $fallback;
    }

    public function set($key, $value) {
        self::$cache = null;
        $this->db->replace($this->table, [
            'key'        => $key,
            'value'      => (string)$value,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Colors for the active (or given) theme preset.
    public static function theme_colors($slug) {
        return self::THEMES[$slug] ?? self::THEMES['blue'];
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
