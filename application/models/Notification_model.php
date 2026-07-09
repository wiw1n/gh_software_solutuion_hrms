<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notification_model extends CI_Model {

    private $table = 'notifications';

    public function create($user_id, $type, $title, $message = null, $link = null) {
        $this->db->insert($this->table, [
            'user_id' => $user_id,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
        ]);
        return $this->db->insert_id();
    }

    // Notify every active admin and super_admin (or a custom set of roles).
    public function notify_admins($type, $title, $message = null, $link = null, $roles = ['super_admin', 'admin']) {
        $admins = $this->db
            ->select('u.id')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where_in('r.slug', $roles)
            ->where('u.status', 'active')
            ->get()->result_array();

        foreach ($admins as $a) {
            $this->create($a['id'], $type, $title, $message, $link);
        }
    }

    public function get_unread_count($user_id) {
        return (int)$this->db
            ->where('user_id', $user_id)
            ->where('is_read', 0)
            ->count_all_results($this->table);
    }

    public function get_recent($user_id, $limit = 15) {
        return $this->db
            ->where('user_id', $user_id)
            ->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get($this->table)->result_array();
    }

    public function mark_read($id, $user_id) {
        return $this->db
            ->where('id',      $id)
            ->where('user_id', $user_id)
            ->update($this->table, ['is_read' => 1]);
    }

    public function mark_all_read($user_id) {
        return $this->db
            ->where('user_id', $user_id)
            ->where('is_read', 0)
            ->update($this->table, ['is_read' => 1]);
    }
}
