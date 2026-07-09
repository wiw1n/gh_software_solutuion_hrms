<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifications extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_login();
        $this->load->model('Notification_model');
    }

    public function unread_count() {
        $this->json([
            'success' => true,
            'count'   => $this->Notification_model->get_unread_count((int)$this->auth_user['id']),
        ]);
    }

    public function recent() {
        $items = $this->Notification_model->get_recent((int)$this->auth_user['id'], 15);
        $this->json(['success' => true, 'notifications' => $items]);
    }

    public function mark_read($id) {
        if ($this->input->method() !== 'post') show_404();
        $this->Notification_model->mark_read((int)$id, (int)$this->auth_user['id']);
        $this->json(['success' => true]);
    }

    public function mark_all_read() {
        if ($this->input->method() !== 'post') show_404();
        $this->Notification_model->mark_all_read((int)$this->auth_user['id']);
        $this->json(['success' => true]);
    }
}
