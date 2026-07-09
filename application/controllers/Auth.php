<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('User_model');
    }

    public function index() {
        if ($this->auth_user) {
            redirect('dashboard');
        }
        redirect('auth/login');
    }

    public function login() {
        if ($this->auth_user) {
            redirect('dashboard');
        }

        if ($this->input->method() === 'post') {
            $username = trim($this->input->post('username', TRUE));
            $password = $this->input->post('password');

            if (empty($username) || empty($password)) {
                $this->session->set_flashdata('error', 'Username and password are required.');
                redirect('auth/login');
                return;
            }

            $user = $this->User_model->get_by_username($username);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $this->session->set_flashdata('error', 'Your account has been deactivated. Contact the administrator.');
                    redirect('auth/login');
                    return;
                }

                $this->session->set_userdata('user', [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'username'   => $user['username'],
                    'email'      => $user['email'],
                    'role_id'    => $user['role_id'],
                    'role_name'  => $user['role_name'],
                    'role_slug'  => $user['role_slug'],
                ]);

                $this->User_model->update_last_login($user['id']);
                // if role is super_admin or admin, redirect to dashboard, else redirect to a different page
                if (in_array($user['role_slug'], ['super_admin', 'admin'])) {
                    redirect('dashboard');
                } elseif ($user['role_slug'] === 'project_head') {
                    redirect('employees'); // heads land on their scoped employee list
                } else {
                    redirect('attendance'); // Replace with the actual page for other roles
                }
            } else {
                $this->session->set_flashdata('error', 'Invalid username or password.');
                redirect('auth/login');
            }
            return;
        }

        $this->load->view('auth/login');
    }

    public function logout() {
        $this->session->unset_userdata('user');
        $this->session->sess_destroy();
        redirect('auth/login');
    }
}
