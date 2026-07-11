<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Profile extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_login();
        $this->load->model('User_model');
    }

    // ----------------------------------------------------------------
    // Update own personal information (Edit Profile modal)
    // ----------------------------------------------------------------

    public function update() {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $id   = (int)$this->auth_user['id'];
        $user = $this->User_model->get_by_id($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found.'], 404);
            return;
        }

        $first_name       = trim($this->input->post('first_name', TRUE));
        $last_name        = trim($this->input->post('last_name', TRUE));
        $email            = trim($this->input->post('email', TRUE));
        $current_password = $this->input->post('current_password');
        $new_password     = $this->input->post('new_password');
        $confirm_password = $this->input->post('confirm_password');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $this->json(['success' => false, 'message' => 'First name, last name and email are required.'], 422);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
            return;
        }

        if ($this->User_model->email_exists($email, $id)) {
            $this->json(['success' => false, 'message' => 'Email address is already in use.'], 422);
            return;
        }

        $update_data = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
        ];

        if (!empty($new_password)) {
            if (empty($current_password) || !password_verify($current_password, $user['password'])) {
                $this->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
                return;
            }
            if (strlen($new_password) < 6) {
                $this->json(['success' => false, 'message' => 'New password must be at least 6 characters.'], 422);
                return;
            }
            if ($new_password !== $confirm_password) {
                $this->json(['success' => false, 'message' => 'New password and confirmation do not match.'], 422);
                return;
            }
            $update_data['password'] = password_hash($new_password, PASSWORD_BCRYPT);
        }

        $result = $this->User_model->update($id, $update_data);
        if ($result === false) {
            $this->json(['success' => false, 'message' => 'Failed to update profile. Please try again.'], 500);
            return;
        }

        // Refresh session so the sidebar/topbar show the new details immediately
        $session_user               = $this->auth_user;
        $session_user['first_name'] = $first_name;
        $session_user['last_name']  = $last_name;
        $session_user['email']      = $email;
        $this->session->set_userdata('user', $session_user);

        $this->json(['success' => true, 'message' => 'Profile updated successfully.']);
    }
}
