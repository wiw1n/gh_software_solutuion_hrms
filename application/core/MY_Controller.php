<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {

    protected $auth_user;

    public function __construct() {
        parent::__construct();
        $this->auth_user = $this->session->userdata('user');
    }

    protected function require_login() {
        if (!$this->auth_user) {
            redirect('auth/login');
        }
    }

    protected function require_role(...$roles) {
        $this->require_login();
        if (!in_array($this->auth_user['role_slug'] ?? '', $roles)) {
            show_error('Access Denied: You do not have permission to view this page.', 403);
        }
    }

    protected function is_super_admin() {
        return ($this->auth_user['role_slug'] ?? '') === 'super_admin';
    }

    protected function is_admin_or_above() {
        return in_array($this->auth_user['role_slug'] ?? '', ['super_admin', 'admin']);
    }

    protected function is_project_head() {
        return ($this->auth_user['role_slug'] ?? '') === 'project_head';
    }

    /**
     * Project IDs the logged-in project head manages.
     * Returns NULL for admin/super_admin (no scoping), an array for heads.
     */
    protected function get_scoped_project_ids() {
        if ($this->is_admin_or_above()) {
            return null;
        }
        $this->load->model('Project_model');
        return $this->Project_model->get_head_project_ids($this->auth_user['id'] ?? 0);
    }

    protected function json($data, $status_code = 200) {
        $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
