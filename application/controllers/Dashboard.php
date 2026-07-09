<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_login();
        $this->load->model('User_model');
    }

    public function index() {
        $data = [
            'title'           => 'Dashboard',
            'page_js'         => null,
            'total_users'     => $this->User_model->count_total_users(),
            'active_users'    => $this->User_model->count_active_users(),
            'total_admins'    => $this->User_model->count_by_role('admin'),
            'total_employees' => $this->User_model->count_by_role('employee'),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('dashboard/index', $data);
        $this->load->view('layouts/footer', $data);
    }
}
