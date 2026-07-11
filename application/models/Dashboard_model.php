<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read-only aggregate queries for the admin HRMS dashboard.
 * Workforce = every user except super_admin (same rule as the Employees list).
 */
class Dashboard_model extends CI_Model {

    private function workforce_base() {
        return $this->db
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('r.slug !=', 'super_admin');
    }

    // Today-scoped attendance counts, workforce only.
    private function count_today($extra_where) {
        $this->db
            ->from('attendance a')
            ->join('users u', 'u.id = a.user_id')
            ->join('roles r', 'r.id = u.role_id')
            ->where('r.slug !=', 'super_admin')
            ->where('a.date', date('Y-m-d'));
        foreach ($extra_where as $key => $val) {
            $this->db->where($key, $val, $val !== null);
        }
        return $this->db->count_all_results();
    }

    public function get_stats() {
        $total_employees  = $this->workforce_base()->count_all_results();
        $active_employees = $this->workforce_base()->where('u.status', 'active')->count_all_results();

        $present_today  = $this->count_today(['a.time_in IS NOT NULL' => null]);
        $late_today     = $this->count_today(['a.time_in IS NOT NULL' => null, 'a.tardiness >' => 0]);
        $on_leave_today = $this->count_today(['a.status' => 'leave']);
        $off_today      = $this->count_today(['a.status' => 'off']);

        $pending_ot = $this->db->where('ot_status', 'pending')->count_all_results('attendance');

        $pending_salary = $this->db->table_exists('salary_requests')
            ? $this->db->where('status', 'pending')->count_all_results('salary_requests')
            : 0;

        $active_projects = $this->db->table_exists('projects')
            ? $this->db->where('status', 'active')->count_all_results('projects')
            : 0;

        $new_hires_month = $this->workforce_base()
            ->where("COALESCE(u.date_hired, DATE(u.created_at)) >=", date('Y-m-01'))
            ->count_all_results();

        return [
            'total_employees'  => $total_employees,
            'active_employees' => $active_employees,
            'present_today'    => $present_today,
            'late_today'       => $late_today,
            'on_leave_today'   => $on_leave_today,
            'not_clocked_in'   => max(0, $active_employees - $present_today - $on_leave_today - $off_today),
            'pending_ot'       => $pending_ot,
            'pending_salary'   => $pending_salary,
            'active_projects'  => $active_projects,
            'new_hires_month'  => $new_hires_month,
        ];
    }

    // Active-employee headcount per job role, largest first.
    public function get_job_role_breakdown() {
        return $this->db
            ->select("COALESCE(jr.name, 'Unassigned') AS job_role, COUNT(*) AS headcount", FALSE)
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->where('r.slug !=', 'super_admin')
            ->where('u.status', 'active')
            ->group_by('jr.id')
            ->order_by('headcount', 'DESC')
            ->get()->result_array();
    }

    public function get_recent_hires($limit = 5) {
        return $this->db
            ->select("u.id, u.first_name, u.last_name, u.employee_id,
                      jr.name AS job_role_name,
                      COALESCE(u.date_hired, DATE(u.created_at)) AS hired_on", FALSE)
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->join('job_roles jr', 'jr.id = u.job_role_id', 'left')
            ->where('r.slug !=', 'super_admin')
            ->order_by('hired_on', 'DESC')
            ->order_by('u.id', 'DESC')
            ->limit($limit)
            ->get()->result_array();
    }
}
