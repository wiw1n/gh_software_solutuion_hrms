<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payroll_model extends CI_Model {

    // ================================================================
    // Available Periods (week_start + timesheet type of the employees)
    // ================================================================

    public function get_available_weeks() {
        return $this->db
            ->distinct()
            ->select('wc.week_start, u.timesheet_type')
            ->from('week_confirmations wc')
            ->join('users u', 'u.id = wc.user_id')
            ->order_by('wc.week_start', 'DESC')
            ->order_by('u.timesheet_type', 'ASC')
            ->get()
            ->result_array();
    }

    // ================================================================
    // Payroll Data  (joins attendance + benefit settings + borrow deductions)
    // ================================================================

    /**
     * @param string $week_start         Period start date (Monday, or the 1st/16th for semi-monthly)
     * @param string $period_end_excl    Day AFTER the period's last day (exclusive upper bound)
     * @param string $timesheet_type     'weekly' | 'semi_monthly' — only employees of this type are included
     */
    public function get_payroll_data($week_start, $period_end_excl, $timesheet_type) {
        $sql = "
            SELECT
                u.id          AS user_id,
                u.employee_id,
                u.first_name,
                u.last_name,
                u.email,
                r.name        AS role_name,
                wc.week_start,
                wc.confirmed_at,
                CONCAT(adm.first_name, ' ', adm.last_name) AS confirmed_by_name,

                COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS days_present,
                COALESCE(SUM(a.total_hours),  0) AS total_hours,
                COALESCE(SUM(a.tardiness),    0) AS total_tardiness,
                COALESCE(SUM(a.overtime),     0) AS total_overtime,

                epi.daily_rate,
                epi.sss_enabled,
                epi.sss_amount,
                epi.philhealth_enabled,
                epi.philhealth_amount,
                epi.pagibig_enabled,
                epi.pagibig_amount,

                sr_adv.amount AS advance_amount,
                sr_adv.status AS advance_status,
                sr_bor.amount AS borrow_amount,
                sr_bor.status AS borrow_status,
                
                CASE WHEN pbd.id IS NOT NULL THEN pbd.deduct_amount ELSE NULL END AS borrow_period_deduct

            FROM week_confirmations wc
            JOIN  users u   ON u.id   = wc.user_id
            JOIN  roles r   ON r.id   = u.role_id
            JOIN  users adm ON adm.id = wc.confirmed_by
            LEFT JOIN attendance a
                ON  a.user_id = wc.user_id
                AND a.date   >= wc.week_start
                AND a.date    < ?
            LEFT JOIN employee_payroll_info epi
                ON  epi.user_id = wc.user_id
            LEFT JOIN salary_requests sr_adv
                ON  sr_adv.user_id    = wc.user_id
                AND sr_adv.week_start = wc.week_start
                AND sr_adv.type       = 'advance'
            LEFT JOIN salary_requests sr_bor
                ON  sr_bor.user_id    = wc.user_id
                AND sr_bor.week_start = wc.week_start
                AND sr_bor.type       = 'borrow'
            LEFT JOIN payroll_borrow_deductions pbd
                ON  pbd.user_id    = wc.user_id
                AND pbd.week_start = wc.week_start
            WHERE wc.week_start = ?
              AND u.timesheet_type = ?
            GROUP BY
                wc.user_id, u.employee_id, wc.week_start, wc.confirmed_at,
                adm.first_name, adm.last_name,
                epi.daily_rate, epi.sss_enabled, epi.sss_amount,
                epi.philhealth_enabled, epi.philhealth_amount,
                epi.pagibig_enabled, epi.pagibig_amount,
                sr_adv.amount, sr_adv.status,
                sr_bor.amount, sr_bor.status,
                pbd.id, pbd.deduct_amount
            ORDER BY u.last_name, u.first_name
        ";

        return $this->db->query($sql, [$period_end_excl, $week_start, $timesheet_type])->result_array();
    }

    // ================================================================
    // Employee Payroll Settings  (daily rate + govt contributions)
    // ================================================================

    public function get_payroll_info($user_id) {
        return $this->db
            ->where('user_id', $user_id)
            ->get('employee_payroll_info')
            ->row_array();
    }

    public function save_payroll_info($user_id, $data) {
        $data['user_id']    = $user_id;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $existing = $this->db
            ->where('user_id', $user_id)
            ->get('employee_payroll_info')
            ->row_array();

        if ($existing) {
            return $this->db
                ->where('user_id', $user_id)
                ->update('employee_payroll_info', $data);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('employee_payroll_info', $data);
        return $this->db->insert_id();
    }

    // ================================================================
    // Borrow Deduction  (admin-decided per employee per payroll period)
    // ================================================================

    public function save_borrow_deduction($user_id, $week_start, $amount, $admin_id) {
        $existing = $this->db
            ->where('user_id',    $user_id)
            ->where('week_start', $week_start)
            ->get('payroll_borrow_deductions')
            ->row_array();

        $payload = [
            'deduct_amount' => $amount,
            'set_by'        => $admin_id,
            'set_at'        => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return $this->db
                ->where('id', $existing['id'])
                ->update('payroll_borrow_deductions', $payload);
        }

        $payload['user_id']    = $user_id;
        $payload['week_start'] = $week_start;
        $this->db->insert('payroll_borrow_deductions', $payload);
        return $this->db->insert_id();
    }

    // ================================================================
    // Payroll Line Items  (custom per-employee per-week adjustments)
    // ================================================================

    /**
     * Returns all line items for a week, keyed by user_id.
     * e.g. [3 => [{id, type, label, amount, ...}, ...], 7 => [...]]
     */
    public function get_week_line_items_keyed($week_start) {
        $rows = $this->db
            ->select('pli.*, CONCAT(ua.first_name, " ", ua.last_name) AS added_by_name')
            ->from('payroll_line_items pli')
            ->join('users ua', 'ua.id = pli.added_by')
            ->where('pli.week_start', $week_start)
            ->order_by('pli.user_id')
            ->order_by('pli.type')
            ->order_by('pli.id')
            ->get()->result_array();

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['user_id']][] = $row;
        }
        return $keyed;
    }

    /**
     * Returns line items for a specific employee/week (for AJAX refresh).
     */
    public function get_line_items($user_id, $week_start) {
        return $this->db
            ->select('pli.*, CONCAT(ua.first_name, " ", ua.last_name) AS added_by_name')
            ->from('payroll_line_items pli')
            ->join('users ua', 'ua.id = pli.added_by')
            ->where('pli.user_id',    $user_id)
            ->where('pli.week_start', $week_start)
            ->order_by('pli.type')
            ->order_by('pli.id')
            ->get()->result_array();
    }

    public function add_line_item($user_id, $week_start, $type, $label, $amount, $notes, $admin_id) {
        $this->db->insert('payroll_line_items', [
            'user_id'    => $user_id,
            'week_start' => $week_start,
            'type'       => $type,
            'label'      => $label,
            'amount'     => $amount,
            'notes'      => $notes ?: null,
            'added_by'   => $admin_id,
        ]);
        return $this->db->insert_id();
    }

    public function delete_line_item($id) {
        return $this->db->where('id', $id)->delete('payroll_line_items');
    }

    // ================================================================
    // Borrow Remaining Balance  (total approved borrows - total deducted)
    // ================================================================

    /**
     * Returns remaining borrow balance per user, keyed by user_id.
     * remaining = total approved borrow requests - all borrow deductions set by admin
     */
    public function get_borrow_balances($user_ids) {
        if (empty($user_ids)) return [];

        $ids_safe = implode(',', array_map('intval', $user_ids));

        $sql = "
            SELECT
                u.id AS user_id,
                COALESCE(SUM(CASE WHEN sr.type = 'borrow' AND sr.status = 'approved'
                               THEN sr.amount ELSE 0 END), 0)      AS total_borrowed,
                COALESCE(pbd_sum.total_deducted, 0)                AS total_deducted
            FROM users u
            LEFT JOIN salary_requests sr ON sr.user_id = u.id
            LEFT JOIN (
                SELECT user_id, SUM(deduct_amount) AS total_deducted
                FROM payroll_borrow_deductions
                GROUP BY user_id
            ) pbd_sum ON pbd_sum.user_id = u.id
            WHERE u.id IN ({$ids_safe})
            GROUP BY u.id, pbd_sum.total_deducted
        ";

        $rows   = $this->db->query($sql)->result_array();
        $keyed  = [];
        foreach ($rows as $row) {
            $remaining = (float)$row['total_borrowed'] - (float)$row['total_deducted'];
            $keyed[$row['user_id']] = [
                'total_borrowed' => (float)$row['total_borrowed'],
                'total_deducted' => (float)$row['total_deducted'],
                'remaining'      => max(0.0, $remaining),
            ];
        }
        return $keyed;
    }
}
