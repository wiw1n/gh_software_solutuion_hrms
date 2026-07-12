<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_login();
        $this->load->model(['Attendance_model', 'User_model', 'Salary_request_model']);
    }

    // True when the logged-in user may manage $user_id's timesheet.
    // Admins manage everyone; project heads only employees on their projects.
    private function can_manage_attendance($user_id) {
        if ($this->is_admin_or_above()) return true;
        if (!$this->is_project_head()) return false;
        $this->load->model('Employee_model');
        return (bool)$this->Employee_model->get_employee($user_id, $this->get_scoped_project_ids());
    }

    // ----------------------------------------------------------------
    // Main entry — dispatches by role
    // ----------------------------------------------------------------

    public function index() {
        if ($this->is_admin_or_above() || $this->is_project_head()) {
            $this->admin_view();
        } else {
            $this->employee_view();
        }
    }

    // ----------------------------------------------------------------
    // Employee: own timesheet
    // ----------------------------------------------------------------

    private function employee_view() {
        // Fetch fresh from DB — session copy may lack newer columns (e.g. timesheet_type)
        $user    = $this->User_model->get_by_id($this->auth_user['id']);
        $today   = $this->Attendance_model->get_today($user['id']);

        $data = [
            'title'      => 'My Timesheet',
            'page_js'    => 'attendance.js',
            'today_rec'  => $today,
            'view_user'  => $user,
            'can_edit'   => false,
            'att_mode'   => $this->Attendance_model->get_mode(),
            'att_cfg'    => $this->Attendance_model->get_config(),
            'next_punch' => $this->Attendance_model->next_punch($today),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('attendance/index', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // Admin / Super-Admin: employee list + own timesheet tabs
    // ----------------------------------------------------------------

    private function admin_view() {
        // Fetch fresh from DB — session copy may lack newer columns (e.g. timesheet_type)
        $user  = $this->User_model->get_by_id($this->auth_user['id']);
        $today = $this->Attendance_model->get_today($user['id']);

        $data = [
            'title'      => 'Attendance',
            'page_js'    => 'attendance.js',
            'today_rec'  => $today,
            'view_user'  => $user,
            // Heads may edit their employees' timesheets but not their own
            'can_edit'   => $this->is_admin_or_above(),
            'att_mode'   => $this->Attendance_model->get_mode(),
            'att_cfg'    => $this->Attendance_model->get_config(),
            'next_punch' => $this->Attendance_model->next_punch($today),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('attendance/admin', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // Admin: view a specific employee's timesheet
    // ----------------------------------------------------------------

    public function view($user_id) {
        $this->require_role('super_admin', 'admin', 'project_head');
        if (!$this->can_manage_attendance($user_id)) show_404();

        $target = $this->User_model->get_by_id($user_id);
        if (!$target) show_404();

        $today = $this->Attendance_model->get_today($user_id);

        $data = [
            'title'      => htmlspecialchars($target['first_name'] . ' ' . $target['last_name']) . ' — Timesheet',
            'page_js'    => 'attendance.js',
            'today_rec'  => $today,
            'view_user'  => $target,
            'can_edit'   => true,
            // Advance/borrow requests are filed by the employee's project head
            // (super admin can too); plain admins only review timesheets
            'can_request_salary' => $this->is_super_admin() || $this->is_project_head(),
            'att_mode'   => $this->Attendance_model->get_mode(),
            'att_cfg'    => $this->Attendance_model->get_config(),
            'next_punch' => $this->Attendance_model->next_punch($today),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('attendance/view_employee', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // Scan Station: barcode-badge clock in/out kiosk (admin machine)
    // ----------------------------------------------------------------

    public function scanner() {
        $this->require_role('super_admin', 'admin');

        // Standalone single-page kiosk (no dashboard layout) — temporary
        // entrance kiosk while the .NET fingerprint program is built.
        $this->load->view('attendance/scanner', [
            'kiosk_admin' => $this->auth_user,
        ]);
    }

    // AJAX: process one badge scan. Step 1 (code only) returns the
    // employee's AM/PM punch buttons — completed punches come back
    // disabled. Step 2 (code + punch) records the selected punch.
    public function scan() {
        $this->require_role('super_admin', 'admin');
        if ($this->input->method() !== 'post') show_404();

        $code = trim((string)$this->input->post('code'));
        if ($code === '') {
            $this->json(['success' => false, 'message' => 'Empty scan.'], 422);
            return;
        }

        $user = $this->User_model->find_by_scan_code($code);
        if (!$user) {
            $this->json([
                'success' => false,
                'action'  => 'unknown',
                'message' => 'No active employee matches code "' . htmlspecialchars($code) . '".',
            ], 404);
            return;
        }

        $name   = $user['first_name'] . ' ' . $user['last_name'];
        $punch  = trim((string)$this->input->post('punch'));
        $today  = $this->Attendance_model->get_today($user['id']);
        $states = $this->Attendance_model->punch_states($today);

        // Step 1: no punch chosen yet — send back the selection buttons.
        if ($punch === '') {
            $pending = array_filter($states, function ($s) { return !$s['done']; });
            $this->json([
                'success' => true,
                'action'  => 'select',
                'name'    => $name,
                'punches' => $states,
                'message' => $pending
                    ? 'Select which time to record.'
                    : $name . ' already completed attendance today.',
            ]);
            return;
        }

        // Step 2: record the chosen punch.
        if (!$this->Attendance_model->can_punch($today, $punch)) {
            $done_at = $this->Attendance_model->punch_value($today, $punch);
            $this->json([
                'success' => false,
                'action'  => 'error',
                'name'    => $name,
                'punches' => $states,
                'message' => $done_at
                    ? $this->_punch_label($punch) . ' was already recorded at ' . date('h:i A', strtotime($done_at)) . '.'
                    : $this->_punch_label($punch) . ' is not available yet.',
            ], 422);
            return;
        }

        // Guard: a double scan within 60s should not record the next punch
        $last = $this->Attendance_model->last_punch_time($today);
        if ($today && $last && (time() - strtotime($today['date'] . ' ' . $last)) < 60) {
            $this->json([
                'success' => true,
                'action'  => 'ignored',
                'name'    => $name,
                'punches' => $states,
                'time'    => date('h:i A', strtotime($today['date'] . ' ' . $last)),
                'message' => $name . ' just scanned — duplicate scan ignored.',
            ]);
            return;
        }

        // Photo captured on the kiosk camera after the badge scan (optional —
        // the station may have no webcam).
        $photo_path = null;
        $photo_data = (string)$this->input->post('photo');
        if ($photo_data !== '') {
            $suffix     = $punch === 'time_in' ? 'in' : ($punch === 'time_out' ? 'out' : $punch);
            $saved      = $this->_save_photo($photo_data, $user['id'], $suffix, $user['last_name']);
            $photo_path = $saved !== false ? $saved : null;
        }

        $is_in = in_array($punch, ['time_in', 'am_in', 'pm_in']);
        $this->Attendance_model->record_punch($user['id'], $punch, $photo_path);

        $rec   = $this->Attendance_model->get_today($user['id']);
        $time  = $this->Attendance_model->punch_value($rec, $punch);
        $label = $this->_punch_label($punch);

        if ($is_in) {
            $this->json([
                'success'   => true,
                'action'    => 'in',
                'name'      => $name,
                'punch'     => $punch,
                'label'     => $label,
                'punches'   => $this->Attendance_model->punch_states($rec),
                'time'      => date('h:i A', strtotime($time)),
                'tardiness' => (float)$rec['tardiness'],
                'message'   => $name . ' — ' . $label . ' at ' . date('h:i A', strtotime($time)) . '.',
            ]);
        } else {
            $this->json([
                'success'  => true,
                'action'   => 'out',
                'name'     => $name,
                'punch'    => $punch,
                'label'    => $label,
                'punches'  => $this->Attendance_model->punch_states($rec),
                'time'     => date('h:i A', strtotime($time)),
                'hours'    => (float)$rec['total_hours'],
                'overtime' => (float)$rec['overtime'],
                'message'  => $name . ' — ' . $label . ' at ' . date('h:i A', strtotime($time))
                            . ' — ' . number_format($rec['total_hours'], 2) . ' hrs so far.',
            ]);
        }
    }

    // Human label for a punch key
    private function _punch_label($punch) {
        $labels = [
            'time_in' => 'Clock In',    'time_out' => 'Clock Out',
            'am_in'   => 'AM Time In',  'am_out'   => 'AM Time Out',
            'pm_in'   => 'PM Time In',  'pm_out'   => 'PM Time Out',
        ];
        return $labels[$punch] ?? 'Punch';
    }

    // AJAX: today's punches, one row per scan event (scan station table)
    public function today_logs() {
        $this->require_role('super_admin', 'admin');
        $this->json([
            'success' => true,
            'logs'    => $this->Attendance_model->get_today_punch_logs(),
        ]);
    }

    // ----------------------------------------------------------------
    // AJAX: Time In
    // ----------------------------------------------------------------

    public function time_in() {
        if ($this->input->method() !== 'post') show_404();

        $photo_data = $this->input->post('photo');
        if (empty($photo_data)) {
            $this->json(['success' => false, 'message' => 'Photo is required.'], 422);
            return;
        }

        $user_id = (int)$this->auth_user['id'];
        $punch   = $this->Attendance_model->next_punch_for($user_id);

        if (!in_array($punch, ['time_in', 'am_in', 'pm_in'])) {
            $this->json(['success' => false, 'message' => 'You have already clocked in today.'], 409);
            return;
        }

        $suffix     = $punch === 'time_in' ? 'in' : $punch;
        $photo_path = $this->_save_photo($photo_data, $user_id, $suffix, $this->auth_user['last_name']);

        if ($photo_path === false) {
            $this->json(['success' => false, 'message' => 'Failed to save photo.'], 500);
            return;
        }

        $result = $this->Attendance_model->time_in($user_id, $photo_path);

        if ($result === false) {
            $this->json(['success' => false, 'message' => 'You have already clocked in today.'], 409);
            return;
        }

        $this->json(['success' => true, 'message' => $this->_punch_label($punch) . ' recorded at ' . date('h:i A') . '.']);
    }

    // ----------------------------------------------------------------
    // AJAX: Time Out
    // ----------------------------------------------------------------

    public function time_out() {
        if ($this->input->method() !== 'post') show_404();

        $photo_data = $this->input->post('photo');
        if (empty($photo_data)) {
            $this->json(['success' => false, 'message' => 'Photo is required.'], 422);
            return;
        }

        $user_id = (int)$this->auth_user['id'];
        $punch   = $this->Attendance_model->next_punch_for($user_id);

        if (!in_array($punch, ['time_out', 'am_out', 'pm_out'])) {
            $this->json(['success' => false, 'message' => 'No active clock-in record found for today.'], 409);
            return;
        }

        $suffix     = $punch === 'time_out' ? 'out' : $punch;
        $photo_path = $this->_save_photo($photo_data, $user_id, $suffix, $this->auth_user['last_name']);

        if ($photo_path === false) {
            $this->json(['success' => false, 'message' => 'Failed to save photo.'], 500);
            return;
        }

        $result = $this->Attendance_model->time_out($user_id, $photo_path);

        if ($result === false) {
            $this->json(['success' => false, 'message' => 'No active clock-in record found for today.'], 409);
            return;
        }

        $this->json(['success' => true, 'message' => $this->_punch_label($punch) . ' recorded at ' . date('h:i A') . '.']);
    }

    // ----------------------------------------------------------------
    // AJAX: Monthly data
    // ----------------------------------------------------------------

    public function monthly() {
        $user_id = (int)$this->input->get_post('user_id');
        $year    = (int)$this->input->get_post('year');
        $month   = (int)$this->input->get_post('month');

        // Only allow self, or someone whose timesheet the viewer manages
        if ($user_id !== (int)$this->auth_user['id'] && !$this->can_manage_attendance($user_id)) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        if (!$user_id || !$year || !$month) {
            $this->json(['success' => false, 'message' => 'Invalid parameters.'], 422);
            return;
        }

        $records = $this->Attendance_model->get_monthly($user_id, $year, $month);
        $summary = $this->Attendance_model->get_monthly_summary($user_id, $year, $month);

        // Build a keyed map by date
        $keyed = [];
        foreach ($records as $r) {
            $keyed[$r['date']] = $r;
        }

        // Generate all days in the month
        $days_in_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $rows          = [];
        $today_str     = date('Y-m-d');

        for ($d = 1; $d <= $days_in_month; $d++) {
            $date_str  = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $day_name  = date('l', strtotime($date_str));
            $is_today  = ($date_str === $today_str);
            $is_future = ($date_str > $today_str);

            if (isset($keyed[$date_str])) {
                $rec = $keyed[$date_str];
                $rows[] = [
                    'id'                 => $rec['id'],
                    'date'               => $date_str,
                    'day'                => $day_name,
                    'status'             => $rec['status'],
                    'time_in'            => $rec['time_in'],
                    'time_out'           => $rec['time_out'],
                    'time_in_photo'      => $rec['time_in_photo'],
                    'time_out_photo'     => $rec['time_out_photo'],
                    'am_time_in'         => $rec['am_time_in'],
                    'am_time_out'        => $rec['am_time_out'],
                    'pm_time_in'         => $rec['pm_time_in'],
                    'pm_time_out'        => $rec['pm_time_out'],
                    'am_time_in_photo'   => $rec['am_time_in_photo'],
                    'am_time_out_photo'  => $rec['am_time_out_photo'],
                    'pm_time_in_photo'   => $rec['pm_time_in_photo'],
                    'pm_time_out_photo'  => $rec['pm_time_out_photo'],
                    'total_hours'        => $rec['total_hours'],
                    'tardiness'          => $rec['tardiness'],
                    'overtime'           => $rec['overtime'],
                    'ot_status'          => $rec['ot_status'],
                    'notes'              => $rec['notes'],
                    'is_today'           => $is_today,
                    'is_future'          => false,
                ];
            } else {
                $rows[] = [
                    'id'                 => null,
                    'date'               => $date_str,
                    'day'                => $day_name,
                    'status'             => $is_future ? 'future' : 'no_record',
                    'time_in'            => null,
                    'time_out'           => null,
                    'time_in_photo'      => null,
                    'time_out_photo'     => null,
                    'am_time_in'         => null,
                    'am_time_out'        => null,
                    'pm_time_in'         => null,
                    'pm_time_out'        => null,
                    'am_time_in_photo'   => null,
                    'am_time_out_photo'  => null,
                    'pm_time_in_photo'   => null,
                    'pm_time_out_photo'  => null,
                    'total_hours'        => null,
                    'tardiness'          => null,
                    'overtime'           => null,
                    'ot_status'          => null,
                    'notes'              => null,
                    'is_today'           => $is_today,
                    'is_future'          => $is_future,
                ];
            }
        }

        $confirmed_weeks = $this->Attendance_model->get_confirmed_weeks($user_id, $year, $month);
        $salary_requests = $this->Salary_request_model->get_month_requests($user_id, $year, $month);

        $this->json([
            'success'         => true,
            'rows'            => $rows,
            'summary'         => $summary,
            'confirmed_weeks' => $confirmed_weeks,
            'salary_requests' => $salary_requests,
        ]);
    }

    // ----------------------------------------------------------------
    // AJAX: Confirm / Unconfirm a week (admin)
    // ----------------------------------------------------------------

    public function confirm_week() {
        $this->require_role('super_admin', 'admin', 'project_head');
        if ($this->input->method() !== 'post') show_404();

        $user_id    = (int)$this->input->post('user_id');
        $week_start = $this->input->post('week_start');
        $action     = $this->input->post('action'); // 'confirm' | 'unconfirm'

        if (!$user_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            $this->json(['success' => false, 'message' => 'Invalid parameters.'], 422);
            return;
        }

        if (!$this->can_manage_attendance($user_id)) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        if ($action === 'unconfirm') {
            $this->Attendance_model->unconfirm_week($user_id, $week_start);
            $this->json(['success' => true, 'message' => 'Week confirmation removed.']);
            return;
        }

        // A period can only be confirmed once every day in it has a record —
        // absences must be marked absent/leave/holiday; no "No Record" days.
        $target      = $this->User_model->get_by_id($user_id);
        $period_end  = $this->_period_end($week_start, $target['timesheet_type'] ?? 'weekly');
        $period_word = ($target['timesheet_type'] ?? 'weekly') === 'semi_monthly' ? 'period' : 'week';
        $missing     = $this->Attendance_model->get_missing_dates($user_id, $week_start, $period_end);

        if (!empty($missing)) {
            $today  = date('Y-m-d');
            $future = array_filter($missing, function ($d) use ($today) { return $d > $today; });

            if (!empty($future)) {
                $this->json([
                    'success' => false,
                    'message' => 'This ' . $period_word . ' is not finished yet (ends '
                               . date('M d, Y', strtotime($period_end)) . '). It can only be confirmed once every day has a record.',
                ], 422);
                return;
            }

            $labels = array_map(function ($d) { return date('M d', strtotime($d)); }, array_slice($missing, 0, 10));
            $this->json([
                'success' => false,
                'message' => count($missing) . ' day(s) in this ' . $period_word . ' have no record: '
                           . implode(', ', $labels) . (count($missing) > 10 ? '…' : '')
                           . '. Mark each as Absent, Leave, or Holiday before confirming.',
            ], 422);
            return;
        }

        $this->Attendance_model->confirm_week($user_id, $week_start, (int)$this->auth_user['id']);
        $this->json(['success' => true, 'message' => ucfirst($period_word) . ' confirmed for salary preparation.']);
    }

    // Period end (inclusive) for a period start + timesheet type
    // (weekly: Mon–Sun; semi-monthly: 1st–15th or 16th–month end)
    private function _period_end($week_start, $type) {
        if ($type === 'semi_monthly') {
            if ((int)date('j', strtotime($week_start)) === 1) {
                return date('Y-m-d', strtotime($week_start . ' +14 days'));
            }
            return date('Y-m-t', strtotime($week_start));
        }
        return date('Y-m-d', strtotime($week_start . ' +6 days'));
    }

    // ----------------------------------------------------------------
    // AJAX: Approve / Decline overtime (head or admin)
    // ----------------------------------------------------------------

    public function ot_action() {
        $this->require_role('super_admin', 'admin', 'project_head');
        if ($this->input->method() !== 'post') show_404();

        $id     = (int)$this->input->post('record_id');
        $action = $this->input->post('action'); // 'approve' | 'decline'

        if (!$id || !in_array($action, ['approve', 'decline'])) {
            $this->json(['success' => false, 'message' => 'Invalid parameters.'], 422);
            return;
        }

        $rec = $this->Attendance_model->get_record($id);
        if (!$rec) {
            $this->json(['success' => false, 'message' => 'Record not found.'], 404);
            return;
        }
        if (!$this->can_manage_attendance((int)$rec['user_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }
        if ((float)$rec['overtime'] <= 0 || empty($rec['ot_status'])) {
            $this->json(['success' => false, 'message' => 'This record has no overtime to review.'], 422);
            return;
        }

        $status = $action === 'approve' ? 'approved' : 'declined';
        $this->Attendance_model->set_ot_status($id, $status, (int)$this->auth_user['id']);

        $this->load->model('Notification_model');
        $this->Notification_model->create(
            (int)$rec['user_id'],
            'ot_' . $status,
            'Overtime ' . ucfirst($status),
            number_format((float)$rec['overtime'], 2) . ' hr(s) of overtime on '
                . date('M d, Y', strtotime($rec['date'])) . ' was ' . $status . ' by '
                . $this->auth_user['first_name'] . ' ' . $this->auth_user['last_name'] . '.',
            base_url('attendance')
        );

        $this->json([
            'success' => true,
            'message' => 'Overtime of ' . number_format((float)$rec['overtime'], 2)
                       . ' hr(s) on ' . date('M d, Y', strtotime($rec['date'])) . ' ' . $status . '.',
        ]);
    }

    // ----------------------------------------------------------------
    // AJAX: Employee DataTable (admin)
    // ----------------------------------------------------------------

    public function datatable() {
        $this->require_role('super_admin', 'admin', 'project_head');
        if ($this->input->method() !== 'post') show_404();

        $draw       = intval($this->input->post('draw'));
        $start      = intval($this->input->post('start'));
        $length     = intval($this->input->post('length'));
        $search     = $this->input->post('search')['value'] ?? '';
        $order      = $this->input->post('order')[0] ?? [];
        $order_col  = intval($order['column'] ?? 0);
        $order_dir  = $order['dir'] ?? 'asc';

        $scope    = $this->get_scoped_project_ids();
        $total    = $this->Attendance_model->get_employees_datatable_total($scope);
        $filtered = $this->Attendance_model->get_employees_datatable_filtered($search, $scope);
        $rows     = $this->Attendance_model->get_employees_datatable($length, $start, $search, $order_col, $order_dir, $scope);

        $data    = [];
        $row_num = $start + 1;
        $am_pm   = $this->Attendance_model->get_mode() === 'am_pm';

        foreach ($rows as $u) {
            $role_color = $u['role_slug'] === 'admin' ? 'primary' : 'secondary';
            $role_badge = '<span class="badge bg-' . $role_color . '-subtle text-' . $role_color . ' border border-' . $role_color . '-subtle">'
                        . htmlspecialchars($u['role_name']) . '</span>';

            // In AM/PM mode the day is only complete once the PM session is closed
            $complete = $am_pm ? !empty($u['pm_time_out']) : !empty($u['time_out']);

            if ($u['att_status'] === 'present' && $u['time_in'] && !$complete) {
                $att_badge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">In Progress</span>';
            } elseif ($u['att_status'] === 'present' && $complete) {
                $att_badge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Completed</span>';
            } else {
                $att_badge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">No Record</span>';
            }

            $time_display = '—';
            if ($u['time_in']) {
                $time_display = date('h:i A', strtotime($u['time_in']));
                if ($u['time_out']) {
                    $time_display .= ' – ' . date('h:i A', strtotime($u['time_out']));
                }
            }

            $name_cell = '<div class="d-flex align-items-center gap-2">'
                       . '<div class="avatar-initials" data-initials="' . strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)) . '"></div>'
                       . '<div><div class="fw-semibold">' . htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) . '</div>'
                       . '<div class="text-muted small">' . htmlspecialchars($u['email']) . '</div></div></div>';

            $actions = '<a href="' . base_url('attendance/view/' . $u['id']) . '" class="btn btn-sm btn-outline-primary" title="View Timesheet">'
                     . '<i class="fas fa-calendar-alt me-1"></i>Timesheet</a>';

            $data[] = [
                $row_num++,
                $name_cell,
                $role_badge,
                $att_badge,
                $time_display,
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
    // AJAX: Edit / Upsert record (admin)
    // ----------------------------------------------------------------

    public function edit($id) {
        $this->require_role('super_admin', 'admin', 'project_head');
        if ($this->input->method() !== 'post') show_404();

        $user_id = (int)$this->input->post('user_id');
        $date    = $this->input->post('date');
        $status  = $this->input->post('status');
        $notes   = trim($this->input->post('notes', TRUE));

        $allowed = ['present', 'absent', 'half_day', 'holiday', 'leave', 'off'];
        if (!in_array($status, $allowed)) $status = 'present';

        if (!$user_id || !$date) {
            $this->json(['success' => false, 'message' => 'User and date are required.'], 422);
            return;
        }

        // Scope check against the record owner (existing record) or the target user (new record)
        $owner_id = $user_id;
        if ($id > 0) {
            $rec = $this->Attendance_model->get_record($id);
            if (!$rec) {
                $this->json(['success' => false, 'message' => 'Record not found.'], 404);
                return;
            }
            $owner_id = (int)$rec['user_id'];
        }
        if (!$this->can_manage_attendance($owner_id)) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        if ($this->Attendance_model->get_mode() === 'am_pm') {
            // AM/PM mode — legacy time_in/time_out are derived in the model
            $record_data = [
                'am_time_in'  => $this->input->post('am_time_in') ?: null,
                'am_time_out' => $this->input->post('am_time_out') ?: null,
                'pm_time_in'  => $this->input->post('pm_time_in') ?: null,
                'pm_time_out' => $this->input->post('pm_time_out') ?: null,
                'status'      => $status,
                'notes'       => $notes ?: null,
            ];
        } else {
            $record_data = [
                'time_in'  => $this->input->post('time_in') ?: null,
                'time_out' => $this->input->post('time_out') ?: null,
                'status'   => $status,
                'notes'    => $notes ?: null,
            ];
        }

        if ($id > 0) {
            $result = $this->Attendance_model->update_record($id, $record_data);
        } else {
            $result = $this->Attendance_model->upsert_record($user_id, $date, $record_data);
        }

        $result !== false
            ? $this->json(['success' => true, 'message' => 'Attendance record saved.'])
            : $this->json(['success' => false, 'message' => 'Failed to save record.'], 500);
    }

    // ----------------------------------------------------------------
    // AJAX: Delete record (admin)
    // ----------------------------------------------------------------

    public function delete_record($id) {
        $this->require_role('super_admin', 'admin', 'project_head');
        if ($this->input->method() !== 'post') show_404();

        $rec = $this->Attendance_model->get_record($id);
        if (!$rec) {
            $this->json(['success' => false, 'message' => 'Record not found.'], 404);
            return;
        }
        if (!$this->can_manage_attendance((int)$rec['user_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
            return;
        }

        $result = $this->Attendance_model->delete_record($id);
        $result
            ? $this->json(['success' => true, 'message' => 'Record deleted.'])
            : $this->json(['success' => false, 'message' => 'Failed to delete record.'], 500);
    }

    // ----------------------------------------------------------------
    // Internal: save base64 photo to disk
    // ----------------------------------------------------------------

    private function _save_photo($base64_data, $user_id, $type, $last_name = '') {
        $base64_data = preg_replace('#^data:image/\w+;base64,#', '', $base64_data);
        $image_data  = base64_decode($base64_data);
        if ($image_data === false) return false;

        $month      = date('Y-m');
        $safe_name  = preg_replace('/[^A-Za-z0-9_\-]/', '', $last_name);
        $emp_folder = $user_id . '_' . ($safe_name ?: 'user');
        $rel_path   = 'assets/uploads/attendance/' . $month . '/' . $emp_folder . '/';
        $dir        = FCPATH . $rel_path;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = date('Y-m-d') . '_' . $type . '.jpg';
        if (file_put_contents($dir . $filename, $image_data) === false) return false;

        return $rel_path . $filename;
    }
}
