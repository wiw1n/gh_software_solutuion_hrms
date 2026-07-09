<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Salary_request_model extends CI_Model {

    private $table = 'salary_requests';
    const MAX_AMOUNT = 1000;
    const MIN_DAYS   = 2;

    // Returns the Monday (week_start) for a given date (or today).
    public function get_week_start($date = null) {
        $ts  = $date ? strtotime($date) : time();
        $dow = (int)date('N', $ts); // 1=Mon … 7=Sun
        return date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
    }

    public function get_days_present_this_week($user_id) {
        $week_start = $this->get_week_start();
        $week_end   = date('Y-m-d', strtotime($week_start . ' +6 days'));
        return (int)$this->db
            ->where('user_id', $user_id)
            ->where('date >=', $week_start)
            ->where('date <=', $week_end)
            ->where('status', 'present')
            ->count_all_results('attendance');
    }

    public function get_this_week_requests($user_id) {
        $week_start = $this->get_week_start();
        $rows = $this->db
            ->where('user_id', $user_id)
            ->where('week_start', $week_start)
            ->get($this->table)->result_array();

        $by_type = [];
        foreach ($rows as $r) {
            $by_type[$r['type']] = $r;
        }
        return $by_type;
    }

    public function create_request($user_id, $type, $amount, $notes = null, $week_start = null) {
        if (!$week_start) {
            $week_start = $this->get_week_start();
        }

        $existing = $this->db
            ->where('user_id',    $user_id)
            ->where('type',       $type)
            ->where('week_start', $week_start)
            ->get($this->table)->row_array();

        if ($existing) return false;

        $this->db->insert($this->table, [
            'user_id'    => $user_id,
            'type'       => $type,
            'amount'     => $amount,
            'week_start' => $week_start,
            'notes'      => $notes,
        ]);
        return $this->db->insert_id();
    }

    // Returns requests for the month keyed by week_start → type.
    public function get_month_requests($user_id, $year, $month) {
        $first       = sprintf('%04d-%02d-01', $year, $month);
        $range_start = date('Y-m-d', strtotime($first . ' -6 days'));
        $range_end   = date('Y-m-t', strtotime($first));

        $rows = $this->db
            ->where('user_id',    $user_id)
            ->where('week_start >=', $range_start)
            ->where('week_start <=', $range_end)
            ->get($this->table)->result_array();

        $keyed = [];
        foreach ($rows as $r) {
            $keyed[$r['week_start']][$r['type']] = $r;
        }
        return $keyed;
    }

    public function update_request($id, $amount, $notes = null) {
        return $this->db->where('id', $id)->update($this->table, [
            'amount'     => $amount,
            'notes'      => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_user_requests($user_id, $limit = 20) {
        return $this->db
            ->where('user_id', $user_id)
            ->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get($this->table)->result_array();
    }

    public function get_all_requests($status = null) {
        $this->db
            ->select('sr.*, u.first_name, u.last_name, u.email,
                      rv.first_name AS reviewer_first, rv.last_name AS reviewer_last')
            ->from('salary_requests sr')
            ->join('users u',  'u.id  = sr.user_id',     'left')
            ->join('users rv', 'rv.id = sr.reviewed_by', 'left');

        if ($status) {
            $this->db->where('sr.status', $status);
        }

        return $this->db->order_by('sr.created_at', 'DESC')->get()->result_array();
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    public function update_status($id, $status, $admin_id, $admin_notes = null) {
        return $this->db->where('id', $id)->update($this->table, [
            'status'      => $status,
            'reviewed_by' => $admin_id,
            'admin_notes' => $admin_notes,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
