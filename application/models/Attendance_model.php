<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance_model extends CI_Model {

    private $table = 'attendance';

    // OT below 30 minutes does not count
    const OT_MIN_HOURS = 0.5;

    // Shift schedule + mode come from system_settings (see Setting_model)
    private $cfg;

    public function __construct() {
        parent::__construct();
        $this->load->model('Setting_model');
        $this->cfg = $this->Setting_model->attendance_config();
        $this->_ensure_session_columns();
        $this->_ensure_ot_columns();
        $this->_ensure_off_status();
    }

    // Adds the AM/PM punch columns on first run after upgrade.
    private function _ensure_session_columns() {
        if ($this->db->field_exists('am_time_in', $this->table)) return;
        $this->db->query("ALTER TABLE `{$this->table}`
            ADD COLUMN `am_time_in`        TIME NULL,
            ADD COLUMN `am_time_out`       TIME NULL,
            ADD COLUMN `pm_time_in`        TIME NULL,
            ADD COLUMN `pm_time_out`       TIME NULL,
            ADD COLUMN `am_time_in_photo`  VARCHAR(255) NULL,
            ADD COLUMN `am_time_out_photo` VARCHAR(255) NULL,
            ADD COLUMN `pm_time_in_photo`  VARCHAR(255) NULL,
            ADD COLUMN `pm_time_out_photo` VARCHAR(255) NULL");
    }

    // Adds the OT approval columns on first run after upgrade.
    private function _ensure_ot_columns() {
        if ($this->db->field_exists('ot_status', $this->table)) return;
        $this->db->query("ALTER TABLE `{$this->table}`
            ADD COLUMN `ot_status`      VARCHAR(10) NULL DEFAULT NULL,
            ADD COLUMN `ot_approved_by` INT NULL DEFAULT NULL,
            ADD COLUMN `ot_approved_at` DATETIME NULL DEFAULT NULL");
        // OT recorded before this feature existed still needs a decision
        $this->db->query("UPDATE `{$this->table}` SET ot_status = 'pending'
            WHERE overtime >= " . self::OT_MIN_HOURS);
    }

    // Adds the 'off' (rest day) status to the enum on first run after upgrade.
    private function _ensure_off_status() {
        $col = $this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'status'")->row_array();
        if (!$col || strpos($col['Type'], "'off'") !== false) return;
        $this->db->query("ALTER TABLE `{$this->table}`
            MODIFY `status` ENUM('present','absent','half_day','holiday','leave','off')
            NOT NULL DEFAULT 'present'");
    }

    // Overtime + approval state derived from a raw past-schedule duration
    // (in hours). OT counts only from 30 minutes up, and any recompute
    // resets the head/admin decision back to pending.
    private function _ot_fields($raw_hours) {
        $ot = round(max(0, $raw_hours), 2);
        if ($ot < self::OT_MIN_HOURS) $ot = 0;
        return [
            'overtime'       => $ot,
            'ot_status'      => $ot > 0 ? 'pending' : null,
            'ot_approved_by' => null,
            'ot_approved_at' => null,
        ];
    }

    // ================================================================
    // Mode / schedule
    // ================================================================

    // 'am_pm' (AM + PM sessions) or 'single' (one in/out pair)
    public function get_mode() {
        return $this->cfg['mode'];
    }

    public function get_config() {
        return $this->cfg;
    }

    // ================================================================
    // Punch state machine
    // ================================================================

    // Which punch comes next for a today-record ('done' when complete).
    // single: time_in → time_out → done
    // am_pm : am_in → am_out → pm_in → pm_out → done
    //         (AM is skipped entirely when the first punch happens after
    //          the scheduled PM start)
    public function next_punch($rec) {
        if ($this->cfg['mode'] === 'single') {
            if (!$rec || empty($rec['time_in'])) return 'time_in';
            return empty($rec['time_out']) ? 'time_out' : 'done';
        }

        if (!$rec || (empty($rec['am_time_in']) && empty($rec['pm_time_in']))) {
            return date('H:i:s') >= $this->cfg['pm_in'] ? 'pm_in' : 'am_in';
        }
        if (!empty($rec['am_time_in']) && empty($rec['am_time_out'])) return 'am_out';
        if (empty($rec['pm_time_in']))  return 'pm_in';
        if (empty($rec['pm_time_out'])) return 'pm_out';
        return 'done';
    }

    public function next_punch_for($user_id) {
        return $this->next_punch($this->get_today($user_id));
    }

    // Value of a given punch on a record (e.g. 'am_in' → am_time_in).
    public function punch_value($rec, $punch) {
        $map = [
            'time_in' => 'time_in',    'time_out' => 'time_out',
            'am_in'   => 'am_time_in', 'am_out'   => 'am_time_out',
            'pm_in'   => 'pm_time_in', 'pm_out'   => 'pm_time_out',
        ];
        return $rec[$map[$punch] ?? ''] ?? null;
    }

    // Punches offered on the scan station with their done/allowed state,
    // so the UI can render selectable buttons and disable finished ones.
    public function punch_states($rec) {
        $punches = $this->cfg['mode'] === 'single'
            ? ['time_in', 'time_out']
            : ['am_in', 'am_out', 'pm_in', 'pm_out'];

        $states = [];
        foreach ($punches as $p) {
            $time = $this->punch_value($rec, $p);
            $states[] = [
                'punch'   => $p,
                'done'    => !empty($time),
                'time'    => $time,
                'allowed' => $this->can_punch($rec, $p),
            ];
        }
        return $states;
    }

    // Whether a specific punch may be recorded now on this record.
    public function can_punch($rec, $punch) {
        $single = $this->cfg['mode'] === 'single';
        if ($single !== in_array($punch, ['time_in', 'time_out'])) return false;
        if ($this->punch_value($rec, $punch)) return false; // already recorded

        switch ($punch) {
            case 'time_in':  return true;
            case 'time_out': return !empty($rec['time_in']);
            case 'am_in':    return true;
            case 'am_out':   return !empty($rec['am_time_in']);
            // PM can start only when AM is untouched or closed
            case 'pm_in':    return empty($rec['am_time_in']) || !empty($rec['am_time_out']);
            case 'pm_out':   return !empty($rec['pm_time_in']);
        }
        return false;
    }

    // Record a specific punch chosen on the scan station.
    public function record_punch($user_id, $punch, $photo_path = null) {
        $rec = $this->get_today($user_id);
        if (!$this->can_punch($rec, $punch)) return false;

        $now = date('H:i:s');

        if (in_array($punch, ['time_in', 'am_in', 'pm_in'])) {
            if ($punch === 'time_in') {
                $data = [
                    'time_in'       => $now,
                    'time_in_photo' => $photo_path,
                    'tardiness'     => $this->_minutes_late($now, $this->cfg['day_in']),
                    'status'        => 'present',
                ];
            } else {
                $session = $punch === 'am_in' ? 'am' : 'pm';
                $data = [
                    $session . '_time_in'       => $now,
                    $session . '_time_in_photo' => $photo_path,
                    'tardiness' => round((float)($rec['tardiness'] ?? 0)
                                   + $this->_minutes_late($now, $this->cfg[$session . '_in']), 2),
                    'status'    => 'present',
                ];
                if (empty($rec['time_in'])) {
                    $data['time_in']       = $now;
                    $data['time_in_photo'] = $photo_path;
                }
            }

            if ($rec) {
                $data['updated_at'] = date('Y-m-d H:i:s');
                return $this->db->where('id', $rec['id'])->update($this->table, $data);
            }
            $data['user_id'] = $user_id;
            $data['date']    = date('Y-m-d');
            $this->db->insert($this->table, $data);
            return $this->db->insert_id();
        }

        // OUT punches require today's record (guaranteed by can_punch)
        if ($punch === 'time_out') {
            $data = [
                'time_out'       => $now,
                'time_out_photo' => $photo_path,
                'total_hours'    => $this->_sched_hours($rec['time_in'], $now,
                                        $this->cfg['day_in'], $this->cfg['day_out']),
                'updated_at'     => date('Y-m-d H:i:s'),
            ] + $this->_ot_fields(($now > $this->cfg['day_out'])
                    ? (strtotime($now) - strtotime($this->cfg['day_out'])) / 3600 : 0);
            return $this->db->where('id', $rec['id'])->update($this->table, $data);
        }

        $session       = $punch === 'am_out' ? 'am' : 'pm';
        $session_hours = $this->_sched_hours(
            $rec[$session . '_time_in'], $now,
            $this->cfg[$session . '_in'], $this->cfg[$session . '_out']);

        $data = [
            $session . '_time_out'       => $now,
            $session . '_time_out_photo' => $photo_path,
            'time_out'                   => $now,
            'time_out_photo'             => $photo_path,
            'total_hours'                => round((float)$rec['total_hours'] + $session_hours, 2),
            'updated_at'                 => date('Y-m-d H:i:s'),
        ];
        if ($punch === 'pm_out') {
            $data += $this->_ot_fields(($now > $this->cfg['pm_out'])
                ? (strtotime($now) - strtotime($this->cfg['pm_out'])) / 3600 : 0);
        }
        return $this->db->where('id', $rec['id'])->update($this->table, $data);
    }

    // Most recent punch time on a record (for duplicate-scan guards).
    public function last_punch_time($rec) {
        if (!$rec) return null;
        $times = array_filter([
            $rec['time_in']    ?? null, $rec['time_out']    ?? null,
            $rec['am_time_in'] ?? null, $rec['am_time_out'] ?? null,
            $rec['pm_time_in'] ?? null, $rec['pm_time_out'] ?? null,
        ]);
        return $times ? max($times) : null;
    }

    private function _minutes_late($time, $sched_start) {
        return ($time && $time > $sched_start)
            ? round((strtotime($time) - strtotime($sched_start)) / 60, 2)
            : 0;
    }

    // Hours worked inside the scheduled window only. Early clock-ins are
    // clamped to the scheduled start and late clock-outs to the scheduled
    // end, so a full day is fixed at the schedule's length (8 hrs) — time
    // past the scheduled out is handled separately as overtime.
    private function _sched_hours($in, $out, $sched_in, $sched_out) {
        if (!$in || !$out) return 0;
        $start = max(strtotime($in), strtotime($sched_in));
        $end   = min(strtotime($out), strtotime($sched_out));
        return round(max(0, $end - $start) / 3600, 2);
    }

    // ================================================================
    // Clock In / Out
    // ================================================================

    public function get_today($user_id) {
        return $this->db
            ->where('user_id', $user_id)
            ->where('date', date('Y-m-d'))
            ->get($this->table)->row_array();
    }

    public function time_in($user_id, $photo_path) {
        $rec   = $this->get_today($user_id);
        $punch = $this->next_punch($rec);
        $now   = date('H:i:s');

        if ($this->cfg['mode'] === 'single') {
            if ($punch !== 'time_in') return false;

            $data = [
                'time_in'       => $now,
                'time_in_photo' => $photo_path,
                'tardiness'     => $this->_minutes_late($now, $this->cfg['day_in']),
                'status'        => 'present',
            ];
            if ($rec) {
                $data['updated_at'] = date('Y-m-d H:i:s');
                return $this->db->where('id', $rec['id'])->update($this->table, $data);
            }
            $data['user_id'] = $user_id;
            $data['date']    = date('Y-m-d');
            $this->db->insert($this->table, $data);
            return $this->db->insert_id();
        }

        if ($punch !== 'am_in' && $punch !== 'pm_in') return false;

        $session   = $punch === 'am_in' ? 'am' : 'pm';
        $tardiness = $this->_minutes_late($now, $this->cfg[$session . '_in']);

        $data = [
            $session . '_time_in'       => $now,
            $session . '_time_in_photo' => $photo_path,
            'status'                    => 'present',
        ];

        if (!$rec) {
            $data['user_id']       = $user_id;
            $data['date']          = date('Y-m-d');
            $data['time_in']       = $now;
            $data['time_in_photo'] = $photo_path;
            $data['tardiness']     = $tardiness;
            $this->db->insert($this->table, $data);
            return $this->db->insert_id();
        }

        if (empty($rec['time_in'])) {
            $data['time_in']       = $now;
            $data['time_in_photo'] = $photo_path;
        }
        $data['tardiness']  = round((float)$rec['tardiness'] + $tardiness, 2);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $rec['id'])->update($this->table, $data);
    }

    public function time_out($user_id, $photo_path) {
        $rec = $this->get_today($user_id);
        if (!$rec) return false;

        $punch = $this->next_punch($rec);
        $now   = date('H:i:s');

        if ($this->cfg['mode'] === 'single') {
            if ($punch !== 'time_out') return false;

            $total_hours = $this->_sched_hours($rec['time_in'], $now,
                $this->cfg['day_in'], $this->cfg['day_out']);

            $data = [
                'time_out'       => $now,
                'time_out_photo' => $photo_path,
                'total_hours'    => $total_hours,
                'updated_at'     => date('Y-m-d H:i:s'),
            ] + $this->_ot_fields(($now > $this->cfg['day_out'])
                    ? (strtotime($now) - strtotime($this->cfg['day_out'])) / 3600 : 0);
            return $this->db->where('id', $rec['id'])->update($this->table, $data);
        }

        if ($punch !== 'am_out' && $punch !== 'pm_out') return false;

        $session       = $punch === 'am_out' ? 'am' : 'pm';
        $session_hours = $this->_sched_hours(
            $rec[$session . '_time_in'], $now,
            $this->cfg[$session . '_in'], $this->cfg[$session . '_out']);

        $data = [
            $session . '_time_out'       => $now,
            $session . '_time_out_photo' => $photo_path,
            'time_out'                   => $now,
            'time_out_photo'             => $photo_path,
            'total_hours'                => round((float)$rec['total_hours'] + $session_hours, 2),
            'updated_at'                 => date('Y-m-d H:i:s'),
        ];
        if ($punch === 'pm_out') {
            $data += $this->_ot_fields(($now > $this->cfg['pm_out'])
                ? (strtotime($now) - strtotime($this->cfg['pm_out'])) / 3600 : 0);
        }
        return $this->db->where('id', $rec['id'])->update($this->table, $data);
    }

    // Today's clock in/out logs across all employees (scan station table)
    public function get_today_logs() {
        return $this->db
            ->select('a.id, a.user_id, a.time_in, a.time_out, a.total_hours,
                      a.am_time_in, a.am_time_out, a.pm_time_in, a.pm_time_out,
                      a.tardiness, a.overtime, a.status,
                      u.first_name, u.last_name, jr.name AS job_role_name')
            ->from($this->table . ' a')
            ->join('users u', 'u.id = a.user_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->where('a.date', date('Y-m-d'))
            ->order_by('a.time_in', 'DESC')
            ->get()->result_array();
    }

    // Today's punches flattened to one row per scan event, newest first
    // (scan station line table): employee id, name, action in/out, time, photo.
    public function get_today_punch_logs() {
        $rows = $this->db
            ->select('a.time_in, a.time_out, a.time_in_photo, a.time_out_photo,
                      a.am_time_in, a.am_time_out, a.pm_time_in, a.pm_time_out,
                      a.am_time_in_photo, a.am_time_out_photo,
                      a.pm_time_in_photo, a.pm_time_out_photo,
                      u.employee_id, u.first_name, u.last_name, jr.name AS job_role_name')
            ->from($this->table . ' a')
            ->join('users u', 'u.id = a.user_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->where('a.date', date('Y-m-d'))
            ->get()->result_array();

        $logs = [];
        foreach ($rows as $r) {
            // Legacy/single-mode records have no AM/PM punches — fall back
            // to the plain time_in/time_out pair.
            $has_sessions = $r['am_time_in'] || $r['am_time_out'] || $r['pm_time_in'] || $r['pm_time_out'];
            $map = $has_sessions
                ? [['am_time_in', 'in',  'AM Time In',  'am_time_in_photo'],
                   ['am_time_out','out', 'AM Time Out', 'am_time_out_photo'],
                   ['pm_time_in', 'in',  'PM Time In',  'pm_time_in_photo'],
                   ['pm_time_out','out', 'PM Time Out', 'pm_time_out_photo']]
                : [['time_in',  'in',  'Time In',  'time_in_photo'],
                   ['time_out', 'out', 'Time Out', 'time_out_photo']];

            foreach ($map as $m) {
                if (empty($r[$m[0]])) continue;
                $logs[] = [
                    'employee_id'   => $r['employee_id'],
                    'first_name'    => $r['first_name'],
                    'last_name'     => $r['last_name'],
                    'job_role_name' => $r['job_role_name'],
                    'action'        => $m[1],
                    'label'         => $m[2],
                    'time'          => $r[$m[0]],
                    'photo'         => $r[$m[3]],
                ];
            }
        }

        usort($logs, function ($a, $b) { return strcmp($b['time'], $a['time']); });
        return $logs;
    }

    // ================================================================
    // Monthly Data
    // ================================================================

    public function get_monthly($user_id, $year, $month) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        return $this->db
            ->where('user_id', $user_id)
            ->where('date >=', $start)
            ->where('date <=', $end)
            ->order_by('date', 'ASC')
            ->get($this->table)->result_array();
    }

    public function get_monthly_summary($user_id, $year, $month) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        return $this->db
            ->select('SUM(total_hours) AS total_hours, SUM(tardiness) AS total_tardiness,
                      SUM(CASE WHEN ot_status = "approved" THEN overtime ELSE 0 END) AS total_overtime,
                      SUM(CASE WHEN ot_status = "pending"  THEN overtime ELSE 0 END) AS total_ot_pending,
                      SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) AS days_present,
                      SUM(CASE WHEN status="absent"  THEN 1 ELSE 0 END) AS days_absent,
                      SUM(CASE WHEN status="leave"   THEN 1 ELSE 0 END) AS days_leave')
            ->where('user_id', $user_id)
            ->where('date >=', $start)
            ->where('date <=', $end)
            ->get($this->table)->row_array();
    }

    // ================================================================
    // Single record
    // ================================================================

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    // Recalculates total_hours / tardiness / overtime (and, in AM/PM mode,
    // syncs the legacy time_in/time_out pair) from the submitted times.
    private function _derive_fields($data) {
        if (array_key_exists('am_time_in', $data) || array_key_exists('pm_time_in', $data)) {
            $am_in  = $data['am_time_in']  ?? null;
            $am_out = $data['am_time_out'] ?? null;
            $pm_in  = $data['pm_time_in']  ?? null;
            $pm_out = $data['pm_time_out'] ?? null;

            $total = $this->_sched_hours($am_in, $am_out, $this->cfg['am_in'], $this->cfg['am_out'])
                   + $this->_sched_hours($pm_in, $pm_out, $this->cfg['pm_in'], $this->cfg['pm_out']);

            $data['total_hours'] = round($total, 2);
            $data['tardiness']   = round(
                $this->_minutes_late($am_in, $this->cfg['am_in'])
                + $this->_minutes_late($pm_in, $this->cfg['pm_in']), 2);
            $data = array_merge($data, $this->_ot_fields(($pm_out && $pm_out > $this->cfg['pm_out'])
                ? (strtotime($pm_out) - strtotime($this->cfg['pm_out'])) / 3600 : 0));
            $data['time_in']  = $am_in  ?: $pm_in;
            $data['time_out'] = $pm_out ?: $am_out;

        } elseif (isset($data['time_in']) && isset($data['time_out']) && $data['time_in'] && $data['time_out']) {
            $data['total_hours'] = $this->_sched_hours($data['time_in'], $data['time_out'],
                $this->cfg['day_in'], $this->cfg['day_out']);
            $data['tardiness']   = $this->_minutes_late($data['time_in'], $this->cfg['day_in']);
            $data = array_merge($data, $this->_ot_fields(($data['time_out'] > $this->cfg['day_out'])
                ? (strtotime($data['time_out']) - strtotime($this->cfg['day_out'])) / 3600 : 0));
        }
        return $data;
    }

    // ================================================================
    // OT Approval (head / admin)
    // ================================================================

    public function set_ot_status($id, $status, $admin_id) {
        return $this->db->where('id', $id)->update($this->table, [
            'ot_status'      => $status,
            'ot_approved_by' => $admin_id,
            'ot_approved_at' => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    public function update_record($id, $data) {
        $data = $this->_derive_fields($data);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    public function upsert_record($user_id, $date, $data) {
        $existing = $this->db
            ->where('user_id', $user_id)
            ->where('date', $date)
            ->get($this->table)->row_array();

        if ($existing) {
            return $this->update_record($existing['id'], $data);
        }

        $data            = $this->_derive_fields($data);
        $data['user_id'] = $user_id;
        $data['date']    = $date;
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function delete_record($id) {
        return $this->db->where('id', $id)->delete($this->table);
    }

    public function get_record($id) {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    // ================================================================
    // Week Confirmations
    // ================================================================

    // Returns array of week_start strings confirmed for user within ±1 week of the month.
    public function get_confirmed_weeks($user_id, $year, $month) {
        $first      = sprintf('%04d-%02d-01', $year, $month);
        $range_start = date('Y-m-d', strtotime($first . ' -6 days'));
        $range_end   = date('Y-m-t', strtotime($first));

        $rows = $this->db
            ->select('week_start')
            ->where('user_id', $user_id)
            ->where('week_start >=', $range_start)
            ->where('week_start <=', $range_end)
            ->get('week_confirmations')->result_array();

        return array_column($rows, 'week_start');
    }

    // Dates in [start, end] with no attendance row at all ("No Record" days).
    // Every day of a period must carry a status (present/absent/leave/…)
    // before the period can be confirmed for payroll.
    public function get_missing_dates($user_id, $start, $end) {
        $rows = $this->db
            ->select('date')
            ->where('user_id', $user_id)
            ->where('date >=', $start)
            ->where('date <=', $end)
            ->get($this->table)->result_array();
        $have = array_column($rows, 'date');

        $missing = [];
        for ($d = strtotime($start); $d <= strtotime($end); $d += 86400) {
            $ds = date('Y-m-d', $d);
            if (!in_array($ds, $have)) $missing[] = $ds;
        }
        return $missing;
    }

    public function confirm_week($user_id, $week_start, $admin_id) {
        $this->db->replace('week_confirmations', [
            'user_id'      => $user_id,
            'week_start'   => $week_start,
            'confirmed_by' => $admin_id,
            'confirmed_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affected_rows() > 0;
    }

    public function unconfirm_week($user_id, $week_start) {
        return $this->db
            ->where('user_id',    $user_id)
            ->where('week_start', $week_start)
            ->delete('week_confirmations');
    }

    // ================================================================
    // Employee List (for admin DataTable)
    // ================================================================

    /**
     * Project-head scoping: when $project_ids is an array, only employees
     * (role slug = employee) assigned to one of those projects are listed.
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

    public function get_employees_datatable($limit, $start, $search, $order_col, $order_dir, $project_ids = null) {
        $today   = date('Y-m-d');
        $columns = [
            0 => 'u.id',
            1 => 'u.first_name',
            2 => 'r.name',
            3 => 'u.status',
        ];

        $this->db
            ->select("u.id, u.first_name, u.last_name, u.email,
                      r.name AS role_name, r.slug AS role_slug, u.status AS user_status,
                      a.time_in, a.time_out, a.status AS att_status,
                      a.am_time_in, a.am_time_out, a.pm_time_in, a.pm_time_out")
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('attendance a', "a.user_id = u.id AND a.date = '$today'", 'left')
            ->where_in('r.slug', ['admin', 'employee'])
            ->group_by('u.id');

        $this->apply_project_scope($project_ids);

        if (!empty($search)) {
            $this->db->group_start()
                ->like('u.first_name', $search)
                ->or_like('u.last_name', $search)
                ->or_like('u.email', $search)
                ->or_like('r.name', $search)
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

    public function get_employees_datatable_total($project_ids = null) {
        $this->db
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where_in('r.slug', ['admin', 'employee']);

        $this->apply_project_scope($project_ids);

        return $this->db->count_all_results();
    }

    public function get_employees_datatable_filtered($search, $project_ids = null) {
        $this->db->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where_in('r.slug', ['admin', 'employee']);

        $this->apply_project_scope($project_ids);

        if (!empty($search)) {
            $this->db->group_start()
                ->like('u.first_name', $search)
                ->or_like('u.last_name', $search)
                ->or_like('u.email', $search)
                ->or_like('r.name', $search)
                ->group_end();
        }

        return $this->db->count_all_results();
    }
}
