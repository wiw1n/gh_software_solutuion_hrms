<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class SystemConfig extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_role('super_admin', 'admin');
        $this->load->model('Job_role_model');
        $this->load->model('Project_model');
    }

    public function index() {
        $data = [
            'title'    => 'System Configuration',
            'page_js'  => 'system_config.js',
            'is_super' => $this->is_super_admin(),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('system_config/index', $data);
        $this->load->view('layouts/footer', $data);
    }

    // ----------------------------------------------------------------
    // Attendance Clock Settings (super admin only)
    // ----------------------------------------------------------------

    public function attendance_settings() {
        $this->require_role('super_admin');
        $this->load->model('Setting_model');

        $s = $this->Setting_model->get_all();
        $this->json([
            'success'  => true,
            'settings' => [
                'attendance_mode' => $s['attendance_mode'],
                'sched_am_in'     => $s['sched_am_in'],
                'sched_am_out'    => $s['sched_am_out'],
                'sched_pm_in'     => $s['sched_pm_in'],
                'sched_pm_out'    => $s['sched_pm_out'],
                'sched_day_in'    => $s['sched_day_in'],
                'sched_day_out'   => $s['sched_day_out'],
            ],
        ]);
    }

    public function save_attendance_settings() {
        $this->require_role('super_admin');
        if ($this->input->method() !== 'post') show_404();
        $this->load->model('Setting_model');

        $mode = $this->input->post('attendance_mode') === 'single' ? 'single' : 'am_pm';

        $times = [];
        foreach (['sched_am_in', 'sched_am_out', 'sched_pm_in', 'sched_pm_out', 'sched_day_in', 'sched_day_out'] as $key) {
            $val = trim((string)$this->input->post($key));
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $val)) {
                $this->json(['success' => false, 'message' => 'All schedule times are required (HH:MM format).'], 422);
                return;
            }
            $times[$key] = $val;
        }

        if ($mode === 'am_pm') {
            if ($times['sched_am_in'] >= $times['sched_am_out']
                || $times['sched_am_out'] > $times['sched_pm_in']
                || $times['sched_pm_in'] >= $times['sched_pm_out']) {
                $this->json(['success' => false, 'message' => 'Schedule must be in order: AM In < AM Out ≤ PM In < PM Out.'], 422);
                return;
            }
        } elseif ($times['sched_day_in'] >= $times['sched_day_out']) {
            $this->json(['success' => false, 'message' => 'Time In must be earlier than Time Out.'], 422);
            return;
        }

        $this->Setting_model->set_many(['attendance_mode' => $mode] + $times);
        $this->json(['success' => true, 'message' => 'Attendance settings saved.']);
    }

    // ----------------------------------------------------------------
    // Job Roles DataTable
    // ----------------------------------------------------------------

    public function job_roles_datatable() {
        if ($this->input->method() !== 'post') show_404();

        $draw      = intval($this->input->post('draw'));
        $start     = intval($this->input->post('start'));
        $length    = intval($this->input->post('length'));
        $search    = $this->input->post('search')['value'] ?? '';
        $order     = $this->input->post('order')[0] ?? [];
        $order_col = intval($order['column'] ?? 0);
        $order_dir = $order['dir'] ?? 'asc';

        $total    = $this->Job_role_model->get_datatable_total();
        $filtered = $this->Job_role_model->get_datatable_filtered($search);
        $rows     = $this->Job_role_model->get_datatable_data($length, $start, $search, $order_col, $order_dir);

        $data    = [];
        $row_num = $start + 1;

        foreach ($rows as $jr) {
            $status_badge = $jr['is_active']
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>';

            $actions = '<button class="btn btn-sm btn-outline-info btn-edit-jr me-1" data-id="' . $jr['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>'
                     . '<button class="btn btn-sm btn-outline-danger btn-delete-jr" data-id="' . $jr['id'] . '" data-name="' . htmlspecialchars($jr['name']) . '" title="Delete"><i class="fas fa-trash"></i></button>';

            $data[] = [
                $row_num++,
                htmlspecialchars($jr['name']),
                htmlspecialchars($jr['description'] ?? '—'),
                $status_badge,
                date('M d, Y', strtotime($jr['created_at'])),
                $actions,
            ];
        }

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => (int)$total,
            'recordsFiltered' => (int)$filtered,
            'data'            => $data,
        ]);
    }

    // ----------------------------------------------------------------
    // Create Job Role
    // ----------------------------------------------------------------

    public function create_job_role() {
        if ($this->input->method() !== 'post') show_404();

        $name        = trim($this->input->post('name', TRUE));
        $description = trim($this->input->post('description', TRUE));
        $is_active   = $this->input->post('is_active') === '1' ? 1 : 0;

        if (empty($name)) {
            $this->json(['success' => false, 'message' => 'Role name is required.'], 422);
            return;
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name, ' _')));

        if ($this->Job_role_model->slug_exists($slug)) {
            $this->json(['success' => false, 'message' => 'A job role with this name already exists.'], 422);
            return;
        }

        $id = $this->Job_role_model->create([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description !== '' ? $description : null,
            'is_active'   => $is_active,
        ]);

        if ($id) {
            $this->json(['success' => true, 'message' => 'Job role created successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to create job role.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Get single Job Role (for edit modal)
    // ----------------------------------------------------------------

    public function get_job_role($id) {
        $jr = $this->Job_role_model->get_by_id($id);
        if (!$jr) {
            $this->json(['success' => false, 'message' => 'Job role not found.'], 404);
            return;
        }
        $this->json(['success' => true, 'data' => $jr]);
    }

    // ----------------------------------------------------------------
    // Update Job Role
    // ----------------------------------------------------------------

    public function update_job_role($id) {
        if ($this->input->method() !== 'post') show_404();

        $jr = $this->Job_role_model->get_by_id($id);
        if (!$jr) {
            $this->json(['success' => false, 'message' => 'Job role not found.'], 404);
            return;
        }

        $name        = trim($this->input->post('name', TRUE));
        $description = trim($this->input->post('description', TRUE));
        $is_active   = $this->input->post('is_active') === '1' ? 1 : 0;

        if (empty($name)) {
            $this->json(['success' => false, 'message' => 'Role name is required.'], 422);
            return;
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name, ' _')));

        if ($this->Job_role_model->slug_exists($slug, $id)) {
            $this->json(['success' => false, 'message' => 'A job role with this name already exists.'], 422);
            return;
        }

        $result = $this->Job_role_model->update($id, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description !== '' ? $description : null,
            'is_active'   => $is_active,
        ]);

        if ($result !== false) {
            $this->json(['success' => true, 'message' => 'Job role updated successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update job role.'], 500);
        }
    }

    // ----------------------------------------------------------------
    // Delete Job Role
    // ----------------------------------------------------------------

    public function delete_job_role($id) {
        if ($this->input->method() !== 'post') show_404();

        $jr = $this->Job_role_model->get_by_id($id);
        if (!$jr) {
            $this->json(['success' => false, 'message' => 'Job role not found.'], 404);
            return;
        }

        if ($this->Job_role_model->is_in_use($id)) {
            $this->json(['success' => false, 'message' => 'Cannot delete: this job role is currently assigned to one or more users.'], 422);
            return;
        }

        if ($this->Job_role_model->delete($id)) {
            $this->json(['success' => true, 'message' => 'Job role deleted successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to delete job role.'], 500);
        }
    }

    // ================================================================
    // Projects
    // ================================================================

    public function projects_datatable() {
        if ($this->input->method() !== 'post') show_404();

        $draw      = intval($this->input->post('draw'));
        $start     = intval($this->input->post('start'));
        $length    = intval($this->input->post('length'));
        $search    = $this->input->post('search')['value'] ?? '';
        $order     = $this->input->post('order')[0] ?? [];
        $order_col = intval($order['column'] ?? 0);
        $order_dir = $order['dir'] ?? 'asc';

        $total    = $this->Project_model->get_datatable_total();
        $filtered = $this->Project_model->get_datatable_filtered($search);
        $rows     = $this->Project_model->get_datatable_data($length, $start, $search, $order_col, $order_dir);

        $status_map = [
            'active'    => ['success',   'Active'],
            'on_hold'   => ['warning',   'On Hold'],
            'completed' => ['secondary', 'Completed'],
        ];

        $data    = [];
        $row_num = $start + 1;

        foreach ($rows as $p) {
            [$sc, $sl] = $status_map[$p['status']] ?? ['secondary', ucfirst($p['status'])];
            $status_badge = '<span class="badge bg-' . $sc . '-subtle text-' . $sc . ' border border-' . $sc . '-subtle">' . $sl . '</span>';

            $dates = '<span class="text-muted small">'
                   . ($p['start_date'] ? date('M d, Y', strtotime($p['start_date'])) : '—')
                   . ' → '
                   . ($p['end_date'] ? date('M d, Y', strtotime($p['end_date'])) : '—')
                   . '</span>';

            $assigned = (int)$p['assigned_count'] > 0
                ? '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fas fa-users me-1"></i>' . (int)$p['assigned_count'] . '</span>'
                : '<span class="text-muted small">—</span>';

            $heads = (int)$p['heads_count'] > 0
                ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fas fa-user-shield me-1"></i>' . (int)$p['heads_count'] . '</span>'
                : '<span class="text-muted small">—</span>';

            $actions = '<button class="btn btn-sm btn-outline-primary btn-heads-pj me-1" data-id="' . $p['id'] . '" data-name="' . htmlspecialchars($p['name']) . '" title="Manage Heads"><i class="fas fa-user-shield"></i></button>'
                     . '<button class="btn btn-sm btn-outline-info btn-edit-pj me-1" data-id="' . $p['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>'
                     . '<button class="btn btn-sm btn-outline-danger btn-delete-pj" data-id="' . $p['id'] . '" data-name="' . htmlspecialchars($p['name']) . '" title="Delete"><i class="fas fa-trash"></i></button>';

            $data[] = [
                $row_num++,
                '<span class="fw-semibold">' . htmlspecialchars($p['name']) . '</span>'
                    . (!empty($p['description']) ? '<div class="text-muted small">' . htmlspecialchars($p['description']) . '</div>' : ''),
                !empty($p['location'])
                    ? '<i class="fas fa-map-marker-alt me-1 text-muted"></i>' . htmlspecialchars($p['location'])
                    : '<span class="text-muted small">—</span>',
                $assigned,
                $heads,
                $status_badge,
                $dates,
                $actions,
            ];
        }

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => (int)$total,
            'recordsFiltered' => (int)$filtered,
            'data'            => $data,
        ]);
    }

    private function _project_input() {
        $name        = trim($this->input->post('name', TRUE));
        $location    = trim($this->input->post('location', TRUE));
        $description = trim($this->input->post('description', TRUE));
        $status      = $this->input->post('status');
        $start_date  = trim((string)$this->input->post('start_date'));
        $end_date    = trim((string)$this->input->post('end_date'));

        if (empty($name)) {
            $this->json(['success' => false, 'message' => 'Project name is required.'], 422);
            return null;
        }

        if (!in_array($status, ['active', 'on_hold', 'completed'])) {
            $status = 'active';
        }

        foreach (['start_date' => $start_date, 'end_date' => $end_date] as $label => $val) {
            if ($val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $this->json(['success' => false, 'message' => 'Invalid date format.'], 422);
                return null;
            }
        }

        if ($start_date !== '' && $end_date !== '' && $end_date < $start_date) {
            $this->json(['success' => false, 'message' => 'End date cannot be earlier than start date.'], 422);
            return null;
        }

        return [
            'name'        => $name,
            'location'    => $location !== '' ? $location : null,
            'description' => $description !== '' ? $description : null,
            'status'      => $status,
            'start_date'  => $start_date !== '' ? $start_date : null,
            'end_date'    => $end_date !== '' ? $end_date : null,
        ];
    }

    public function create_project() {
        if ($this->input->method() !== 'post') show_404();

        $data = $this->_project_input();
        if ($data === null) return;

        if ($this->Project_model->name_exists($data['name'])) {
            $this->json(['success' => false, 'message' => 'A project with this name already exists.'], 422);
            return;
        }

        if ($this->Project_model->create($data)) {
            $this->json(['success' => true, 'message' => 'Project created successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to create project.'], 500);
        }
    }

    public function get_project($id) {
        $p = $this->Project_model->get_by_id($id);
        if (!$p) {
            $this->json(['success' => false, 'message' => 'Project not found.'], 404);
            return;
        }
        $this->json(['success' => true, 'data' => $p]);
    }

    public function update_project($id) {
        if ($this->input->method() !== 'post') show_404();

        $p = $this->Project_model->get_by_id($id);
        if (!$p) {
            $this->json(['success' => false, 'message' => 'Project not found.'], 404);
            return;
        }

        $data = $this->_project_input();
        if ($data === null) return;

        if ($this->Project_model->name_exists($data['name'], $id)) {
            $this->json(['success' => false, 'message' => 'A project with this name already exists.'], 422);
            return;
        }

        if ($this->Project_model->update($id, $data) !== false) {
            $this->json(['success' => true, 'message' => 'Project updated successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update project.'], 500);
        }
    }

    public function delete_project($id) {
        if ($this->input->method() !== 'post') show_404();

        $p = $this->Project_model->get_by_id($id);
        if (!$p) {
            $this->json(['success' => false, 'message' => 'Project not found.'], 404);
            return;
        }

        if ($this->Project_model->is_in_use($id)) {
            $this->json(['success' => false, 'message' => 'Cannot delete: this project is currently assigned to one or more employees.'], 422);
            return;
        }

        if ($this->Project_model->delete($id)) {
            $this->json(['success' => true, 'message' => 'Project deleted successfully.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to delete project.'], 500);
        }
    }

    // ================================================================
    // Project Heads (assign users with the Project Head role)
    // ================================================================

    public function project_heads($id) {
        $p = $this->Project_model->get_by_id($id);
        if (!$p) {
            $this->json(['success' => false, 'message' => 'Project not found.'], 404);
            return;
        }

        $this->json([
            'success'    => true,
            'project'    => ['id' => (int)$p['id'], 'name' => $p['name']],
            'heads'      => $this->Project_model->get_heads($id),
            'candidates' => $this->Project_model->get_head_candidates($id),
        ]);
    }

    public function add_project_head($id) {
        if ($this->input->method() !== 'post') show_404();

        $p = $this->Project_model->get_by_id($id);
        if (!$p) {
            $this->json(['success' => false, 'message' => 'Project not found.'], 404);
            return;
        }

        $user_id = intval($this->input->post('user_id'));
        if (!$user_id) {
            $this->json(['success' => false, 'message' => 'Please select a user.'], 422);
            return;
        }

        $this->load->model('User_model');
        $user = $this->User_model->get_by_id($user_id);
        if (!$user || $user['role_slug'] !== 'project_head') {
            $this->json(['success' => false, 'message' => 'Only users with the Project Head role can be assigned as heads.'], 422);
            return;
        }

        if ($this->Project_model->add_head($id, $user_id, $this->auth_user['id'] ?? null)) {
            $this->json(['success' => true, 'message' => $user['first_name'] . ' ' . $user['last_name'] . ' is now a head of ' . $p['name'] . '.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to assign project head.'], 500);
        }
    }

    public function remove_project_head($id) {
        if ($this->input->method() !== 'post') show_404();

        $p = $this->Project_model->get_by_id($id);
        if (!$p) {
            $this->json(['success' => false, 'message' => 'Project not found.'], 404);
            return;
        }

        $user_id = intval($this->input->post('user_id'));
        if (!$user_id) {
            $this->json(['success' => false, 'message' => 'Invalid user.'], 422);
            return;
        }

        if ($this->Project_model->remove_head($id, $user_id) !== false) {
            $this->json(['success' => true, 'message' => 'Project head removed.']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to remove project head.'], 500);
        }
    }
}
