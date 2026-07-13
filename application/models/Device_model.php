<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Biometric terminal integration (ZKTeco MB10-VL).
 *
 * Raw punches land in `device_punches` from two sources:
 *   push   — the terminal's ADMS/cloud push (Iclock controller)
 *   upload — attendance-data files carried from offline sites (Devices page)
 * Both sources dedupe on (device_user_id, punch_time), then process_pending()
 * maps the device User ID to users.barcode and replays each punch through
 * Attendance_model::apply_device_punch() in chronological order.
 */
class Device_model extends CI_Model {

    private $punches = 'device_punches';
    private $devices = 'devices';

    public function __construct() {
        parent::__construct();
        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->devices}` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `serial_no`  VARCHAR(64) NOT NULL UNIQUE,
            `name`       VARCHAR(100) NULL,
            `last_seen`  DATETIME NULL,
            `created_at` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->punches}` (
            `id`             INT AUTO_INCREMENT PRIMARY KEY,
            `device_sn`      VARCHAR(64) NULL,
            `device_user_id` VARCHAR(32) NOT NULL,
            `punch_time`     DATETIME NOT NULL,
            `status`         VARCHAR(8) NULL,
            `source`         ENUM('push','upload') NOT NULL DEFAULT 'push',
            `user_id`        INT NULL,
            `result`         VARCHAR(20) NULL,
            `processed_at`   DATETIME NULL,
            `created_at`     DATETIME NOT NULL,
            UNIQUE KEY `uniq_punch` (`device_user_id`, `punch_time`),
            KEY `idx_pending` (`processed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // ================================================================
    // Devices (terminals seen on the push endpoints)
    // ================================================================

    public function touch_device($sn) {
        $sn = trim($sn);
        if ($sn === '') return;
        $this->db->query(
            "INSERT INTO `{$this->devices}` (serial_no, last_seen, created_at)
             VALUES (?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE last_seen = NOW()",
            [$sn]
        );
    }

    public function get_devices() {
        return $this->db
            ->select("d.*, (SELECT COUNT(*) FROM {$this->punches} p
                            WHERE p.device_sn = d.serial_no) AS punch_count", FALSE)
            ->from($this->devices . ' d')
            ->order_by('d.last_seen', 'DESC')
            ->get()->result_array();
    }

    // ================================================================
    // Ingest (raw punch rows, deduped)
    // ================================================================

    /**
     * $rows: list of ['pin' => device user id, 'time' => 'Y-m-d H:i[:s]',
     * 'status' => raw device code or null].
     * Returns ['new' => n, 'duplicate' => n, 'invalid' => n].
     */
    public function ingest(array $rows, $source, $sn = null) {
        $counts = ['new' => 0, 'duplicate' => 0, 'invalid' => 0];

        foreach ($rows as $r) {
            $pin = trim($r['pin'] ?? '');
            $ts  = strtotime(trim($r['time'] ?? ''));
            if ($pin === '' || !$ts) {
                $counts['invalid']++;
                continue;
            }

            $this->db->query(
                "INSERT IGNORE INTO `{$this->punches}`
                 (device_sn, device_user_id, punch_time, status, source, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$sn, $pin, date('Y-m-d H:i:s', $ts),
                 isset($r['status']) ? substr(trim($r['status']), 0, 8) : null,
                 $source === 'upload' ? 'upload' : 'push']
            );
            $this->db->affected_rows() > 0 ? $counts['new']++ : $counts['duplicate']++;
        }
        return $counts;
    }

    // ================================================================
    // Processing (raw punches → attendance records)
    // ================================================================

    /**
     * Applies all unprocessed punches in chronological order.
     * Returns ['applied' => n, 'skipped' => n, 'unmatched' => n,
     *          'unmatched_pins' => [...]].
     */
    public function process_pending() {
        $this->load->model('Attendance_model');

        $pending = $this->db
            ->where('processed_at IS NULL', null, false)
            ->order_by('punch_time', 'ASC')
            ->order_by('id', 'ASC')
            ->get($this->punches)->result_array();

        $summary = ['applied' => 0, 'skipped' => 0, 'unmatched' => 0, 'unmatched_pins' => []];
        if (!$pending) return $summary;

        // Device User ID lives in users.barcode
        $map = [];
        foreach ($this->db->select('id, barcode')->from('users')
                     ->where('barcode IS NOT NULL', null, false)
                     ->get()->result_array() as $u) {
            $map[trim($u['barcode'])] = (int)$u['id'];
        }

        foreach ($pending as $p) {
            $user_id = $map[trim($p['device_user_id'])] ?? null;

            if (!$user_id) {
                $result = 'unmatched';
                $summary['unmatched']++;
                $summary['unmatched_pins'][$p['device_user_id']] = true;
            } else {
                $result = $this->Attendance_model->apply_device_punch($user_id, $p['punch_time']);
                in_array($result, ['skipped', 'done']) ? $summary['skipped']++ : $summary['applied']++;
            }

            $this->db->where('id', $p['id'])->update($this->punches, [
                'user_id'      => $user_id,
                'result'       => $result,
                'processed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $summary['unmatched_pins'] = array_keys($summary['unmatched_pins']);
        return $summary;
    }

    // Re-queue punches that had no matching employee (run after fixing the
    // barcode/device-ID mapping on the employee record).
    public function reprocess_unmatched() {
        $this->db->where('result', 'unmatched')->update($this->punches, [
            'user_id'      => null,
            'result'       => null,
            'processed_at' => null,
        ]);
        return $this->process_pending();
    }

    // ================================================================
    // Import page data
    // ================================================================

    public function recent_punches($limit = 200) {
        return $this->db
            ->select('p.device_sn, p.device_user_id, p.punch_time, p.source,
                      p.result, p.created_at, u.first_name, u.last_name, u.employee_id')
            ->from($this->punches . ' p')
            ->join('users u', 'u.id = p.user_id', 'left')
            ->order_by('p.punch_time', 'DESC')
            ->order_by('p.id', 'DESC')
            ->limit($limit)
            ->get()->result_array();
    }

    public function stats() {
        $row = $this->db
            ->select("COUNT(*) AS total,
                      SUM(result = 'unmatched') AS unmatched,
                      SUM(result IN ('am_in','am_out','pm_in','pm_out','time_in','time_out')) AS applied,
                      MAX(punch_time) AS last_punch", FALSE)
            ->get($this->punches)->row_array();
        return [
            'total'      => (int)($row['total'] ?? 0),
            'unmatched'  => (int)($row['unmatched'] ?? 0),
            'applied'    => (int)($row['applied'] ?? 0),
            'last_punch' => $row['last_punch'],
        ];
    }
}
