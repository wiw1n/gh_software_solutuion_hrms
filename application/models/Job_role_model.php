<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Job_role_model extends CI_Model {

    private $table = 'job_roles';

    public function get_all($active_only = false) {
        if ($active_only) {
            $this->db->where('is_active', 1);
        }
        return $this->db->order_by('name', 'ASC')->get($this->table)->result_array();
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    public function create($data) {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    public function delete($id) {
        return $this->db->where('id', $id)->delete($this->table);
    }

    public function slug_exists($slug, $exclude_id = null) {
        $this->db->where('slug', $slug);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function is_in_use($id) {
        return $this->db->where('job_role_id', $id)->count_all_results('users') > 0;
    }

    // ================================================================
    // DataTable
    // ================================================================

    public function get_datatable_data($limit, $start, $search, $order_col, $order_dir) {
        $columns = [0 => 'id', 1 => 'name', 2 => 'description', 3 => 'is_active', 4 => 'created_at'];

        if (!empty($search)) {
            $this->db->group_start()
                ->like('name', $search)
                ->or_like('description', $search)
                ->group_end();
        }

        $col = $columns[$order_col] ?? 'id';
        $dir = strtolower($order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($col, $dir);

        if ($limit != -1) {
            $this->db->limit($limit, $start);
        }

        return $this->db->get($this->table)->result_array();
    }

    public function get_datatable_total() {
        return $this->db->count_all($this->table);
    }

    public function get_datatable_filtered($search) {
        if (!empty($search)) {
            $this->db->group_start()
                ->like('name', $search)
                ->or_like('description', $search)
                ->group_end();
        }
        return $this->db->count_all_results($this->table);
    }
}
