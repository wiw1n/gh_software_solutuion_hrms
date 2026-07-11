<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payroll extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_login();
        $this->require_role('super_admin', 'admin');
        $this->load->model('Payroll_model');
        // Ensures the projects table / users.project_id column exist
        // (payroll rows join projects for the project filter)
        $this->load->model('Project_model');
        // Ensures the attendance OT approval columns exist
        // (payroll sums approved overtime via attendance.ot_status)
        $this->load->model('Attendance_model');
    }

    // ----------------------------------------------------------------
    // Main payroll report page
    // ----------------------------------------------------------------

    public function index() {
        $week_start = $this->input->get('week') ?: null;
        $type       = $this->input->get('type');
        $project_id = (int)($this->input->get('project') ?: 0);

        if ($week_start && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            $week_start = null;
        }
        if (!in_array($type, ['weekly', 'semi_monthly'])) {
            $type = null;
        }

        $weeks = $this->Payroll_model->get_available_weeks();

        if (!$week_start && !empty($weeks)) {
            $week_start = $weeks[0]['week_start'];
            $type       = $weeks[0]['timesheet_type'];
        }

        if ($week_start && !$type) {
            foreach ($weeks as $w) {
                if ($w['week_start'] === $week_start) {
                    $type = $w['timesheet_type'];
                    break;
                }
            }
            if (!$type) $type = 'weekly';
        }

        $rows = $week_start ? $this->_fetch_payroll_rows($week_start, $type) : [];
        $rows = $this->_filter_by_project($rows, $project_id);

        $data = [
            'title'          => 'Payroll Report',
            'page_js'        => 'payroll.js',
            'weeks'          => $weeks,
            'week_start'     => $week_start,
            'period_type'    => $type,
            'week_end'       => $week_start ? $this->_period_end($week_start, $type) : null,
            'rows'           => $rows,
            'projects'       => $this->Project_model->get_all(),
            'project_filter' => $project_id,
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('payroll/index', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // AJAX: Set borrow deduction for an employee/week (admin)
    // ----------------------------------------------------------------

    public function set_borrow_deduction() {
        if ($this->input->method() !== 'post') show_404();

        $user_id    = (int)$this->input->post('user_id');
        $week_start = $this->input->post('week_start');
        $amount     = (float)$this->input->post('deduct_amount');

        if (!$user_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            $this->json(['success' => false, 'message' => 'Invalid parameters.'], 422);
            return;
        }

        if ($amount < 0) {
            $this->json(['success' => false, 'message' => 'Deduction amount cannot be negative.'], 422);
            return;
        }

        $this->Payroll_model->save_borrow_deduction(
            $user_id, $week_start, $amount, (int)$this->auth_user['id']
        );

        $msg = $amount > 0
            ? 'Borrow deduction of ₱' . number_format($amount, 2) . ' set for this period.'
            : 'Borrow deduction cleared for this period.';

        $this->json(['success' => true, 'message' => $msg]);
    }

    // ----------------------------------------------------------------
    // AJAX: Save employee payroll settings (rate + benefit toggles)
    // ----------------------------------------------------------------

    public function save_settings() {
        if ($this->input->method() !== 'post') show_404();

        $user_id = (int)$this->input->post('user_id');
        if (!$user_id) {
            $this->json(['success' => false, 'message' => 'Invalid employee.'], 422);
            return;
        }

        $daily_rate          = (float)$this->input->post('daily_rate');
        $sss_enabled         = $this->input->post('sss_enabled')        ? 1 : 0;
        $sss_amount          = (float)$this->input->post('sss_amount');
        $philhealth_enabled  = $this->input->post('philhealth_enabled') ? 1 : 0;
        $philhealth_amount   = (float)$this->input->post('philhealth_amount');
        $pagibig_enabled     = $this->input->post('pagibig_enabled')    ? 1 : 0;
        $pagibig_amount      = (float)$this->input->post('pagibig_amount');

        if ($daily_rate < 0) {
            $this->json(['success' => false, 'message' => 'Daily rate cannot be negative.'], 422);
            return;
        }

        $this->Payroll_model->save_payroll_info($user_id, [
            'daily_rate'         => $daily_rate,
            'sss_enabled'        => $sss_enabled,
            'sss_amount'         => $sss_enabled  ? $sss_amount        : 0,
            'philhealth_enabled' => $philhealth_enabled,
            'philhealth_amount'  => $philhealth_enabled ? $philhealth_amount : 0,
            'pagibig_enabled'    => $pagibig_enabled,
            'pagibig_amount'     => $pagibig_enabled ? $pagibig_amount  : 0,
        ]);

        $this->json(['success' => true, 'message' => 'Payroll settings saved.']);
    }

    // ----------------------------------------------------------------
    // AJAX: Get line items for an employee/week (for modal refresh)
    // ----------------------------------------------------------------

    public function get_line_items() {
        $user_id    = (int)$this->input->get('user_id');
        $week_start = $this->input->get('week_start');

        if (!$user_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            $this->json(['success' => false, 'message' => 'Invalid parameters.'], 422);
            return;
        }

        $items = $this->Payroll_model->get_line_items($user_id, $week_start);
        $this->json(['success' => true, 'items' => $items]);
    }

    // ----------------------------------------------------------------
    // AJAX: Add a custom line item (deduction or addition)
    // ----------------------------------------------------------------

    public function add_line_item() {
        if ($this->input->method() !== 'post') show_404();

        $user_id    = (int)$this->input->post('user_id');
        $week_start = $this->input->post('week_start');
        $type       = $this->input->post('type');
        $label      = trim($this->input->post('label'));
        $amount     = (float)$this->input->post('amount');
        $notes      = trim($this->input->post('notes') ?: '');

        if (!$user_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            $this->json(['success' => false, 'message' => 'Invalid parameters.'], 422);
            return;
        }

        if (!in_array($type, ['deduction', 'addition'])) {
            $this->json(['success' => false, 'message' => 'Type must be deduction or addition.'], 422);
            return;
        }

        if ($label === '') {
            $this->json(['success' => false, 'message' => 'Label is required.'], 422);
            return;
        }

        if ($amount <= 0) {
            $this->json(['success' => false, 'message' => 'Amount must be greater than zero.'], 422);
            return;
        }

        $id = $this->Payroll_model->add_line_item(
            $user_id, $week_start, $type, $label, $amount, $notes,
            (int)$this->auth_user['id']
        );

        $items = $this->Payroll_model->get_line_items($user_id, $week_start);
        $this->json(['success' => true, 'message' => 'Item added.', 'item_id' => $id, 'items' => $items]);
    }

    // ----------------------------------------------------------------
    // AJAX: Delete a line item
    // ----------------------------------------------------------------

    public function delete_line_item($id = 0) {
        if ($this->input->method() !== 'post') show_404();

        $id = (int)$id;
        if (!$id) {
            $this->json(['success' => false, 'message' => 'Invalid item ID.'], 422);
            return;
        }

        $this->Payroll_model->delete_line_item($id);
        $this->json(['success' => true, 'message' => 'Item removed.']);
    }

    // ----------------------------------------------------------------
    // Download as Excel-compatible CSV
    // ----------------------------------------------------------------

    public function export($week_start = '', $type = 'weekly') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) show_404();
        if (!in_array($type, ['weekly', 'semi_monthly'])) $type = 'weekly';

        $rows     = $this->_filter_by_project(
            $this->_fetch_payroll_rows($week_start, $type),
            (int)($this->input->get('project') ?: 0)
        );
        $week_end = $this->_period_end($week_start, $type);
        $filename = 'payroll_' . $week_start . ($type === 'semi_monthly' ? '_15-30' : '') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM so Excel opens correctly
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        fputcsv($out, ['PAYROLL REPORT — GH Software Solution']);
        fputcsv($out, ['Payroll Type:', $type === 'semi_monthly' ? '15/30 (Semi-Monthly)' : 'Weekly']);
        fputcsv($out, ['Period:', date('M d, Y', strtotime($week_start)) . ' - ' . date('M d, Y', strtotime($week_end))]);
        fputcsv($out, ['Generated:', date('M d, Y h:i A')]);
        fputcsv($out, ['Total Employees:', count($rows)]);
        fputcsv($out, []);
        fputcsv($out, [
            '#',
            'Employee Name',
            'Role',
            'Days Present',
            'Total Hours',
            'Approved OT (hrs)',
            'OT Pay (PHP)',
            'Daily Rate (PHP)',
            'Gross Pay (PHP)',
            'SSS Deduction (PHP)',
            'PhilHealth Deduction (PHP)',
            'Pag-IBIG Deduction (PHP)',
            'Govt Deductions (PHP)',
            'Advance Deduction (PHP)',
            'Advance Status',
            'Borrow Deduction (PHP)',
            'Borrow Request (PHP)',
            'Borrow Status',
            'Custom Additions (PHP)',
            'Custom Deductions (PHP)',
            'Total Deductions (PHP)',
            'Net Pay (PHP)',
            'Confirmed By',
            'Confirmed At',
        ]);

        foreach ($rows as $i => $r) {
            // Build readable line items string
            $additions_str  = '';
            $deductions_str = '';
            foreach ($r['line_items'] as $item) {
                if ($item['type'] === 'addition') {
                    $additions_str  .= $item['label'] . ':' . number_format($item['amount'], 2) . ' ';
                } else {
                    $deductions_str .= $item['label'] . ':' . number_format($item['amount'], 2) . ' ';
                }
            }

            fputcsv($out, [
                $i + 1,
                $r['first_name'] . ' ' . $r['last_name'],
                $r['role_name'],
                $r['days_present'],
                number_format((float)$r['total_hours'], 2),
                number_format($r['ot_hours'], 2),
                number_format($r['ot_pay'],   2),
                $r['daily_rate'] !== null ? number_format((float)$r['daily_rate'], 2) : 'Not Set',
                number_format($r['gross_pay'],         2),
                number_format($r['sss_deduct'],        2),
                number_format($r['philhealth_deduct'], 2),
                number_format($r['pagibig_deduct'],    2),
                number_format($r['govt_deductions'],   2),
                number_format($r['advance_deduct'],    2),
                $r['advance_status'] ?: '—',
                number_format($r['borrow_deduct'],     2),
                $r['borrow_amount'] !== null ? number_format((float)$r['borrow_amount'], 2) : '',
                $r['borrow_status'] ?: '—',
                number_format($r['line_additions'],    2) . ($additions_str  ? ' | ' . trim($additions_str)  : ''),
                number_format($r['line_deductions'],   2) . ($deductions_str ? ' | ' . trim($deductions_str) : ''),
                number_format($r['total_deductions'],  2),
                number_format($r['net_pay'],           2),
                $r['confirmed_by_name'],
                date('M d, Y h:i A', strtotime($r['confirmed_at'])),
            ]);
        }

        // Totals row
        fputcsv($out, []);
        fputcsv($out, [
            '', 'TOTALS', '',
            array_sum(array_column($rows, 'days_present')),
            number_format(array_sum(array_column($rows, 'total_hours')), 2),
            number_format(array_sum(array_column($rows, 'ot_hours')),    2),
            number_format(array_sum(array_column($rows, 'ot_pay')),      2),
            '',
            number_format(array_sum(array_column($rows, 'gross_pay')),         2),
            number_format(array_sum(array_column($rows, 'sss_deduct')),        2),
            number_format(array_sum(array_column($rows, 'philhealth_deduct')), 2),
            number_format(array_sum(array_column($rows, 'pagibig_deduct')),    2),
            number_format(array_sum(array_column($rows, 'govt_deductions')),   2),
            number_format(array_sum(array_column($rows, 'advance_deduct')),    2),
            '',
            number_format(array_sum(array_column($rows, 'borrow_deduct')),     2),
            '', '',
            number_format(array_sum(array_column($rows, 'line_additions')),    2),
            number_format(array_sum(array_column($rows, 'line_deductions')),   2),
            number_format(array_sum(array_column($rows, 'total_deductions')),  2),
            number_format(array_sum(array_column($rows, 'net_pay')),           2),
        ]);

        fclose($out);
        exit;
    }

    // ----------------------------------------------------------------
    // Print-ready standalone page
    // ----------------------------------------------------------------

    public function print_view($week_start = '', $type = 'weekly') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) show_404();
        if (!in_array($type, ['weekly', 'semi_monthly'])) $type = 'weekly';

        $rows = $this->_filter_by_project(
            $this->_fetch_payroll_rows($week_start, $type),
            (int)($this->input->get('project') ?: 0)
        );

        $this->load->model('Setting_model');

        $data = [
            'week_start'  => $week_start,
            'week_end'    => $this->_period_end($week_start, $type),
            'period_type' => $type,
            'rows'        => $rows,
            'company'     => $this->Setting_model->get_all(),
        ];

        $this->load->view('payroll/print', $data);
    }

    // ----------------------------------------------------------------
    // Print-ready signature sheet (payroll form employees sign)
    // One table per project, with per-day columns like the paper form
    // ----------------------------------------------------------------

    public function sign_sheet($week_start = '', $type = 'weekly') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) show_404();
        if (!in_array($type, ['weekly', 'semi_monthly'])) $type = 'weekly';

        $rows     = $this->_filter_by_project(
            $this->_fetch_payroll_rows($week_start, $type),
            (int)($this->input->get('project') ?: 0)
        );
        $week_end = $this->_period_end($week_start, $type);
        $user_ids = array_column($rows, 'user_id');

        $period_end_excl = date('Y-m-d', strtotime($week_end . ' +1 day'));
        $daily_attendance = $this->Payroll_model->get_daily_attendance($week_start, $period_end_excl, $user_ids);

        // Group payroll rows by project (employees without one go last)
        $groups = [];
        foreach ($rows as $r) {
            $pname = $r['project_name'] ?? null;
            $key   = ($pname !== null && $pname !== '') ? $pname : 'Unassigned';
            $groups[$key][] = $r;
        }
        uksort($groups, function ($a, $b) {
            if ($a === 'Unassigned') return 1;
            if ($b === 'Unassigned') return -1;
            return strcasecmp($a, $b);
        });

        $this->load->model('Setting_model');

        $data = [
            'week_start'       => $week_start,
            'week_end'         => $week_end,
            'period_type'      => $type,
            'groups'           => $groups,
            'daily_attendance' => $daily_attendance,
            'company'          => $this->Setting_model->get_all(),
        ];

        $this->load->view('payroll/sign_sheet', $data);
    }

    // ----------------------------------------------------------------
    // Print-ready payslips (all employees, or one via $user_id)
    // Compact vouchers laid out 12 per bond paper (3 cols x 4 rows)
    // ----------------------------------------------------------------

    public function payslips($week_start = '', $type = 'weekly', $user_id = 0) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) show_404();
        if (!in_array($type, ['weekly', 'semi_monthly'])) $type = 'weekly';

        $rows = $this->_filter_by_project(
            $this->_fetch_payroll_rows($week_start, $type),
            (int)($this->input->get('project') ?: 0)
        );

        $user_id = (int)$user_id;
        if ($user_id) {
            $rows = array_values(array_filter(
                $rows,
                function ($r) use ($user_id) { return (int)$r['user_id'] === $user_id; }
            ));
            if (empty($rows)) show_404();
        }

        $data = [
            'week_start'  => $week_start,
            'week_end'    => $this->_period_end($week_start, $type),
            'period_type' => $type,
            'rows'        => $rows,
            'single'      => (bool)$user_id,
        ];

        $this->load->view('payroll/payslips', $data);
    }

    // ----------------------------------------------------------------
    // Private: keep only rows assigned to a project (0 = all projects)
    // ----------------------------------------------------------------

    private function _filter_by_project($rows, $project_id) {
        if ($project_id <= 0) return $rows;
        return array_values(array_filter(
            $rows,
            function ($r) use ($project_id) { return (int)($r['project_id'] ?? 0) === $project_id; }
        ));
    }

    // ----------------------------------------------------------------
    // Private: period end (inclusive) for a period start + type
    // ----------------------------------------------------------------

    private function _period_end($week_start, $type) {
        if ($type === 'semi_monthly') {
            if ((int)date('j', strtotime($week_start)) === 1) {
                return date('Y-m-d', strtotime($week_start . ' +14 days')); // the 15th
            }
            return date('Y-m-t', strtotime($week_start)); // last day of month
        }
        return date('Y-m-d', strtotime($week_start . ' +6 days')); // Sunday
    }

    // ----------------------------------------------------------------
    // Private: fetch + enrich all payroll rows for a period
    // ----------------------------------------------------------------

    private function _fetch_payroll_rows($week_start, $type) {
        $period_end_excl = date('Y-m-d', strtotime($this->_period_end($week_start, $type) . ' +1 day'));
        $raw_rows        = $this->Payroll_model->get_payroll_data($week_start, $period_end_excl, $type);

        if (empty($raw_rows)) return [];

        $user_ids           = array_column($raw_rows, 'user_id');
        $line_items_by_user = $this->Payroll_model->get_week_line_items_keyed($week_start);
        $borrow_balances    = $this->Payroll_model->get_borrow_balances($user_ids);

        return $this->_compute_payroll($raw_rows, $line_items_by_user, $borrow_balances);
    }

    // ----------------------------------------------------------------
    // Private: enrich each row with computed payroll values
    // ----------------------------------------------------------------

    private function _compute_payroll($rows, $line_items_by_user = [], $borrow_balances = []) {
        foreach ($rows as &$r) {
            $uid  = $r['user_id'];
            $rate = $r['daily_rate'] !== null ? (float)$r['daily_rate'] : null;
            $days = (int)$r['days_present'];

            // Approved OT only (filtered in the SQL): daily rate ÷ 8 × OT hours
            $ot_hours = (float)$r['total_overtime'];
            $ot_pay   = ($rate !== null && $ot_hours > 0) ? round(($rate / 8) * $ot_hours, 2) : 0;
            $gross    = $rate !== null ? round($rate * $days + $ot_pay, 2) : 0;

            // Government contributions
            $sss        = ($r['sss_enabled']       && $r['sss_amount'])        ? (float)$r['sss_amount']        : 0;
            $philhealth = ($r['philhealth_enabled'] && $r['philhealth_amount']) ? (float)$r['philhealth_amount'] : 0;
            $pagibig    = ($r['pagibig_enabled']    && $r['pagibig_amount'])    ? (float)$r['pagibig_amount']    : 0;
            $govt       = round($sss + $philhealth + $pagibig, 2);

            // Advance: auto-deduct only if the request is approved
            $advance_deduct = ($r['advance_status'] === 'approved' && $r['advance_amount'] !== null)
                ? (float)$r['advance_amount'] : 0;

            // Borrow: admin-set deduction for this period
            $borrow_deduct = $r['borrow_period_deduct'] !== null
                ? (float)$r['borrow_period_deduct'] : 0;

            // Custom line items
            $items      = $line_items_by_user[$uid] ?? [];
            $line_add   = 0.0;
            $line_deduct = 0.0;
            foreach ($items as $item) {
                if ($item['type'] === 'addition') {
                    $line_add   += (float)$item['amount'];
                } else {
                    $line_deduct += (float)$item['amount'];
                }
            }
            $line_add    = round($line_add, 2);
            $line_deduct = round($line_deduct, 2);

            // Borrow remaining balance (total borrowed - total ever deducted)
            $bb = $borrow_balances[$uid] ?? null;
            $r['borrow_total_borrowed'] = $bb ? $bb['total_borrowed'] : 0.0;
            $r['borrow_remaining']      = $bb ? $bb['remaining']      : 0.0;

            $total_deductions = round($govt + $advance_deduct + $borrow_deduct + $line_deduct, 2);
            $net_pay          = round($gross + $line_add - $total_deductions, 2);

            $r['ot_hours']          = $ot_hours;
            $r['ot_pay']            = $ot_pay;
            $r['gross_pay']         = $gross;
            $r['sss_deduct']        = $sss;
            $r['philhealth_deduct'] = $philhealth;
            $r['pagibig_deduct']    = $pagibig;
            $r['govt_deductions']   = $govt;
            $r['advance_deduct']    = $advance_deduct;
            $r['borrow_deduct']     = $borrow_deduct;
            $r['line_items']        = $items;
            $r['line_additions']    = $line_add;
            $r['line_deductions']   = $line_deduct;
            $r['total_deductions']  = $total_deductions;
            $r['net_pay']           = $net_pay;
        }
        return $rows;
    }
}
