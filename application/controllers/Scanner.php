<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Entrance Fingerprint Scan Station (kiosk).
 *
 * A standalone full-screen page meant to run on a PC at the entrance.
 * Flow:
 *   1. An admin unlocks the station with their username + password.
 *   2. Employees clock in/out by fingerprint (WebAuthn / Windows Hello)
 *      or — if enabled in settings — by typing/scanning a badge code.
 *   3. Registering fingers, managing them, and changing settings are
 *      password-gated: the admin must re-enter their password.
 *
 * Kiosk state lives in its own session keys (kiosk_admin, kiosk_mgmt_until)
 * so the entrance PC never holds a normal dashboard login.
 */
class Scanner extends MY_Controller {

    const MGMT_WINDOW_SECS = 600; // password re-entry valid for 10 minutes

    public function __construct() {
        parent::__construct();
        $this->load->model(['User_model', 'Attendance_model', 'Fingerprint_model']);
        $this->load->library('Webauthn_lib');
    }

    // ----------------------------------------------------------------
    // Kiosk page
    // ----------------------------------------------------------------

    public function index() {
        $settings = $this->Fingerprint_model->get_settings();

        $this->load->view('scanner/index', [
            'settings' => $settings,
            'unlocked' => (bool)$this->session->userdata('kiosk_admin'),
            'kiosk_admin' => $this->session->userdata('kiosk_admin'),
            'rp_id'    => $this->webauthn_lib->rp_id(),
        ]);
    }

    // ----------------------------------------------------------------
    // Station unlock / lock (admin credentials required)
    // ----------------------------------------------------------------

    public function unlock() {
        if ($this->input->method() !== 'post') show_404();

        $username = trim((string)$this->input->post('username', TRUE));
        $password = (string)$this->input->post('password');

        if ($username === '' || $password === '') {
            $this->json(['success' => false, 'message' => 'Username and password are required.'], 422);
            return;
        }

        $user = $this->User_model->get_by_username($username);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->json(['success' => false, 'message' => 'Invalid username or password.'], 401);
            return;
        }
        if ($user['status'] !== 'active') {
            $this->json(['success' => false, 'message' => 'This account is deactivated.'], 403);
            return;
        }
        if (!in_array($user['role_slug'], ['super_admin', 'admin'])) {
            $this->json(['success' => false, 'message' => 'Only administrators can unlock the scan station.'], 403);
            return;
        }

        $this->session->set_userdata('kiosk_admin', [
            'id'       => (int)$user['id'],
            'name'     => $user['first_name'] . ' ' . $user['last_name'],
            'username' => $user['username'],
        ]);
        $this->session->unset_userdata('kiosk_mgmt_until');

        $this->json(['success' => true, 'message' => 'Station unlocked.', 'admin' => $user['first_name'] . ' ' . $user['last_name']]);
    }

    public function lock() {
        if ($this->input->method() !== 'post') show_404();
        $this->session->unset_userdata(['kiosk_admin', 'kiosk_mgmt_until', 'kiosk_scan_challenge', 'kiosk_reg_challenge', 'kiosk_reg_user_id']);
        $this->json(['success' => true, 'message' => 'Station locked.']);
    }

    // ----------------------------------------------------------------
    // Guards
    // ----------------------------------------------------------------

    private function _kiosk_admin() {
        $admin = $this->session->userdata('kiosk_admin');
        if (!$admin) {
            $this->json(['success' => false, 'locked' => true, 'message' => 'Station is locked — an administrator must unlock it.'], 401);
            return false;
        }
        return $admin;
    }

    // Management actions (register/delete fingers, settings) additionally
    // require the admin password re-entered within the last 10 minutes.
    private function _mgmt_admin() {
        $admin = $this->_kiosk_admin();
        if ($admin === false) return false;

        $until = (int)$this->session->userdata('kiosk_mgmt_until');
        if ($until < time()) {
            $this->json(['success' => false, 'need_password' => true, 'message' => 'Admin password required.'], 403);
            return false;
        }
        return $admin;
    }

    // Re-enter admin password to open the management window.
    public function verify_password() {
        if ($this->input->method() !== 'post') show_404();
        $admin = $this->_kiosk_admin();
        if ($admin === false) return;

        $password = (string)$this->input->post('password');
        $user     = $this->User_model->get_by_id($admin['id']);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->json(['success' => false, 'message' => 'Incorrect admin password.'], 401);
            return;
        }

        $this->session->set_userdata('kiosk_mgmt_until', time() + self::MGMT_WINDOW_SECS);
        $this->json(['success' => true, 'message' => 'Password verified.']);
    }

    // ----------------------------------------------------------------
    // Fingerprint scan (WebAuthn assertion) → clock in / out
    // ----------------------------------------------------------------

    // Issue a one-time challenge for the next fingerprint scan.
    public function challenge() {
        if ($this->input->method() !== 'post') show_404();
        if ($this->_kiosk_admin() === false) return;

        $challenge = $this->webauthn_lib->generate_challenge();
        $this->session->set_userdata('kiosk_scan_challenge', $challenge);

        $this->json([
            'success'   => true,
            'challenge' => $challenge,
            'rp_id'     => $this->webauthn_lib->rp_id(),
        ]);
    }

    public function scan_verify() {
        if ($this->input->method() !== 'post') show_404();
        if ($this->_kiosk_admin() === false) return;

        $expected = (string)$this->session->userdata('kiosk_scan_challenge');
        $this->session->unset_userdata('kiosk_scan_challenge'); // single use

        $credential_id = (string)$this->input->post('credential_id');
        $client_data   = (string)$this->input->post('client_data_json');
        $auth_data     = (string)$this->input->post('authenticator_data');
        $signature     = (string)$this->input->post('signature');

        if ($credential_id === '' || $client_data === '' || $auth_data === '' || $signature === '') {
            $this->json(['success' => false, 'message' => 'Incomplete scan data.'], 422);
            return;
        }

        $finger = $this->Fingerprint_model->get_by_credential_id($credential_id);
        if (!$finger) {
            $this->json([
                'success' => false,
                'action'  => 'unknown',
                'message' => 'This fingerprint is not registered. Ask an admin to register it.',
            ], 404);
            return;
        }

        $result = $this->webauthn_lib->verify_assertion(
            $client_data, $auth_data, $signature, $finger['public_key'], $expected
        );
        if ($result !== true) {
            $this->json(['success' => false, 'action' => 'error', 'message' => 'Fingerprint check failed: ' . $result], 401);
            return;
        }

        $user = $this->User_model->get_by_id($finger['user_id']);
        if (!$user || $user['status'] !== 'active') {
            $this->json(['success' => false, 'action' => 'error', 'message' => 'This employee account is inactive.'], 403);
            return;
        }

        $this->Fingerprint_model->mark_used(
            $finger['id'],
            $this->webauthn_lib->extract_sign_count($auth_data)
        );

        $this->_record_scan($user);
    }

    // ----------------------------------------------------------------
    // Manual / badge-code fallback (setting-controlled)
    // ----------------------------------------------------------------

    public function scan_code() {
        if ($this->input->method() !== 'post') show_404();
        if ($this->_kiosk_admin() === false) return;

        $settings = $this->Fingerprint_model->get_settings();
        if ((string)$settings['allow_manual_input'] !== '1') {
            $this->json(['success' => false, 'message' => 'Manual input is disabled on this station.'], 403);
            return;
        }

        $code = trim((string)$this->input->post('code'));
        if ($code === '') {
            $this->json(['success' => false, 'message' => 'Empty scan.'], 422);
            return;
        }

        $user = $this->User_model->find_by_scan_code($code);
        if (!$user) {
            $this->json([
                'success' => false,
                'action'  => 'unknown',
                'message' => 'No active employee matches code "' . htmlspecialchars($code) . '".',
            ], 404);
            return;
        }

        $this->_record_scan($user);
    }

    // Shared clock in/out logic — punches advance through the configured
    // attendance mode: single (in → out) or AM/PM sessions
    // (am_in → am_out → pm_in → pm_out).
    private function _record_scan($user) {
        $settings = $this->Fingerprint_model->get_settings();
        $guard    = max(0, (int)$settings['duplicate_guard_secs']);

        $name  = $user['first_name'] . ' ' . $user['last_name'];
        $today = $this->Attendance_model->get_today($user['id']);
        $punch = $this->Attendance_model->next_punch($today);

        if ($punch === 'done') {
            $this->json([
                'success' => true,
                'action'  => 'done',
                'name'    => $name,
                'time'    => date('h:i A', strtotime($today['time_out'])),
                'message' => $name . ' already completed attendance today ('
                           . date('h:i A', strtotime($today['time_in'])) . ' – '
                           . date('h:i A', strtotime($today['time_out'])) . ').',
            ]);
            return;
        }

        $last = $this->Attendance_model->last_punch_time($today);
        if ($guard > 0 && $today && $last
            && (time() - strtotime($today['date'] . ' ' . $last)) < $guard) {
            $this->json([
                'success' => true,
                'action'  => 'ignored',
                'name'    => $name,
                'time'    => date('h:i A', strtotime($today['date'] . ' ' . $last)),
                'message' => $name . ' just scanned — duplicate scan ignored.',
            ]);
            return;
        }

        $is_in = in_array($punch, ['time_in', 'am_in', 'pm_in']);
        $is_in
            ? $this->Attendance_model->time_in($user['id'], null)
            : $this->Attendance_model->time_out($user['id'], null);

        $rec   = $this->Attendance_model->get_today($user['id']);
        $time  = $this->Attendance_model->punch_value($rec, $punch);
        $label = $this->_punch_label($punch);

        if ($is_in) {
            $this->json([
                'success'   => true,
                'action'    => 'in',
                'name'      => $name,
                'time'      => date('h:i A', strtotime($time)),
                'tardiness' => (float)$rec['tardiness'],
                'message'   => $name . ' — ' . $label . ' at ' . date('h:i A', strtotime($time)) . '.',
            ]);
        } else {
            $this->json([
                'success'  => true,
                'action'   => 'out',
                'name'     => $name,
                'time'     => date('h:i A', strtotime($time)),
                'hours'    => (float)$rec['total_hours'],
                'overtime' => (float)$rec['overtime'],
                'message'  => $name . ' — ' . $label . ' at ' . date('h:i A', strtotime($time))
                            . ' — ' . number_format($rec['total_hours'], 2) . ' hrs so far.',
            ]);
        }
    }

    private function _punch_label($punch) {
        $labels = [
            'time_in' => 'Clock In',    'time_out' => 'Clock Out',
            'am_in'   => 'AM Time In',  'am_out'   => 'AM Time Out',
            'pm_in'   => 'PM Time In',  'pm_out'   => 'PM Time Out',
        ];
        return $labels[$punch] ?? 'Punch';
    }

    // ----------------------------------------------------------------
    // Today's log table
    // ----------------------------------------------------------------

    public function today_logs() {
        if ($this->_kiosk_admin() === false) return;
        $this->json([
            'success' => true,
            'logs'    => $this->Attendance_model->get_today_logs(),
        ]);
    }

    // ----------------------------------------------------------------
    // Finger registration (password-gated, admin only)
    // ----------------------------------------------------------------

    // Employees selectable for registration, with current finger counts.
    public function employees() {
        if ($this->_mgmt_admin() === false) return;
        $this->json([
            'success'   => true,
            'employees' => $this->Fingerprint_model->get_registrable_users(),
        ]);
    }

    // WebAuthn creation options for a chosen employee.
    public function register_options() {
        if ($this->input->method() !== 'post') show_404();
        if ($this->_mgmt_admin() === false) return;

        $user_id = (int)$this->input->post('user_id');
        $user    = $this->User_model->get_by_id($user_id);
        if (!$user || $user['status'] !== 'active') {
            $this->json(['success' => false, 'message' => 'Employee not found or inactive.'], 404);
            return;
        }

        $challenge = $this->webauthn_lib->generate_challenge();
        $this->session->set_userdata([
            'kiosk_reg_challenge' => $challenge,
            'kiosk_reg_user_id'   => $user_id,
        ]);

        // Prevent registering the same employee twice on this device.
        $exclude = $this->Fingerprint_model->credential_ids_for_user($user_id);

        $this->json([
            'success'   => true,
            'challenge' => $challenge,
            'rp'        => ['id' => $this->webauthn_lib->rp_id(), 'name' => 'GH Software Solution'],
            'user'      => [
                'id_b64'      => $this->webauthn_lib->b64url_encode('user-' . $user_id),
                'name'        => $user['username'],
                'displayName' => $user['first_name'] . ' ' . $user['last_name'],
            ],
            'exclude_credentials' => $exclude,
        ]);
    }

    // Store the new credential after the browser ceremony.
    public function register_verify() {
        if ($this->input->method() !== 'post') show_404();
        $admin = $this->_mgmt_admin();
        if ($admin === false) return;

        $expected = (string)$this->session->userdata('kiosk_reg_challenge');
        $user_id  = (int)$this->session->userdata('kiosk_reg_user_id');
        $this->session->unset_userdata(['kiosk_reg_challenge', 'kiosk_reg_user_id']);

        if (!$user_id || $user_id !== (int)$this->input->post('user_id')) {
            $this->json(['success' => false, 'message' => 'Registration session expired — try again.'], 422);
            return;
        }

        $result = $this->webauthn_lib->verify_registration(
            (string)$this->input->post('client_data_json'),
            (string)$this->input->post('attestation_object'),
            $expected
        );

        if (!$result['success']) {
            $this->json(['success' => false, 'message' => $result['message']], 422);
            return;
        }

        if ($this->Fingerprint_model->get_by_credential_id($result['credential_id'])) {
            $this->json(['success' => false, 'message' => 'This fingerprint credential is already registered.'], 409);
            return;
        }

        $label = trim((string)$this->input->post('label', TRUE));

        $this->Fingerprint_model->create([
            'user_id'       => $user_id,
            'credential_id' => $result['credential_id'],
            'public_key'    => $result['public_key'],
            'sign_count'    => $result['sign_count'],
            'label'         => $label !== '' ? $label : null,
            'created_by'    => (int)$admin['id'],
        ]);

        $user = $this->User_model->get_by_id($user_id);
        $this->json([
            'success' => true,
            'message' => 'Fingerprint registered for ' . $user['first_name'] . ' ' . $user['last_name'] . '.',
        ]);
    }

    // ----------------------------------------------------------------
    // Manage registered fingers (password-gated)
    // ----------------------------------------------------------------

    public function fingerprints() {
        if ($this->_mgmt_admin() === false) return;
        $this->json([
            'success'      => true,
            'fingerprints' => $this->Fingerprint_model->get_all_with_users(),
        ]);
    }

    public function delete_fingerprint($id) {
        if ($this->input->method() !== 'post') show_404();
        if ($this->_mgmt_admin() === false) return;

        $this->Fingerprint_model->delete((int)$id)
            ? $this->json(['success' => true, 'message' => 'Fingerprint removed.'])
            : $this->json(['success' => false, 'message' => 'Failed to remove fingerprint.'], 500);
    }

    // ----------------------------------------------------------------
    // Station settings (password-gated)
    // ----------------------------------------------------------------

    public function get_settings() {
        if ($this->_mgmt_admin() === false) return;
        $this->json([
            'success'  => true,
            'settings' => $this->Fingerprint_model->get_settings(),
        ]);
    }

    public function save_settings() {
        if ($this->input->method() !== 'post') show_404();
        if ($this->_mgmt_admin() === false) return;

        $station_name = trim((string)$this->input->post('station_name', TRUE));
        $guard        = (int)$this->input->post('duplicate_guard_secs');
        $refresh      = (int)$this->input->post('log_refresh_secs');

        $this->Fingerprint_model->save_settings([
            'station_name'         => $station_name !== '' ? $station_name : 'Main Entrance',
            'duplicate_guard_secs' => (string)min(600, max(0, $guard)),
            'allow_manual_input'   => $this->input->post('allow_manual_input') ? '1' : '0',
            'play_sounds'          => $this->input->post('play_sounds') ? '1' : '0',
            'auto_start_scanner'   => $this->input->post('auto_start_scanner') ? '1' : '0',
            'log_refresh_secs'     => (string)min(300, max(10, $refresh ?: 30)),
        ]);

        $this->json(['success' => true, 'message' => 'Scanner settings saved. Reloading station…']);
    }
}
