<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_login();

        // Admin HRMS dashboard — other roles land on their own pages
        if (!$this->is_admin_or_above()) {
            redirect($this->is_project_head() ? 'employees' : 'attendance');
        }

        $this->load->model(['Dashboard_model', 'Attendance_model', 'Project_model']);
    }

    public function index() {
        $data = [
            'title'        => 'Dashboard',
            'page_js'      => null,
            'stats'        => $this->Dashboard_model->get_stats(),
            'today_logs'   => $this->Attendance_model->get_today_logs(),
            'att_mode'     => $this->Attendance_model->get_mode(),
            'job_roles'    => $this->Dashboard_model->get_job_role_breakdown(),
            'recent_hires' => $this->Dashboard_model->get_recent_hires(5),
            'is_super'     => $this->is_super_admin(),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('dashboard/index', $data);
        $this->load->view('layouts/footer', $data);
    }
}
