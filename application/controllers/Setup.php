<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * One-time setup controller.
 * Visit /setup to create the default Super Admin account.
 * DELETE THIS FILE after setup is complete.
 */
class Setup extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model('User_model');
    }

    public function index() {
        $count = $this->db->count_all('users');

        if ($count > 0) {
            show_error(
                'Setup is already complete. <strong>Delete <code>application/controllers/Setup.php</code></strong> for security.',
                403,
                'Setup Already Done'
            );
            return;
        }

        $hashed = password_hash('Admin@123', PASSWORD_BCRYPT);

        $this->User_model->create([
            'role_id'    => 1,
            'first_name' => 'Super',
            'last_name'  => 'Admin',
            'username'   => 'superadmin',
            'email'      => 'superadmin@ghsoftware.com',
            'password'   => $hashed,
            'status'     => 'active',
        ]);

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Setup Complete</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card shadow-sm" style="max-width:460px;width:100%">
<div class="card-body p-4 text-center">
<div class="mb-3"><i class="fas fa-check-circle text-success" style="font-size:3rem;"></i></div>
<h4 class="fw-bold text-success">Setup Complete!</h4>
<p class="text-muted">Default Super Admin account has been created.</p>
<table class="table table-bordered mb-4">
<tr><th class="text-start">Username</th><td class="text-start">superadmin</td></tr>
<tr><th class="text-start">Password</th><td class="text-start">Admin@123</td></tr>
</table>
<div class="alert alert-danger text-start">
<strong>⚠ Security Warning:</strong><br>
Delete <code>application/controllers/Setup.php</code> now to prevent unauthorized access.
</div>
<a href="' . base_url('auth/login') . '" class="btn btn-primary px-4">Go to Login</a>
</div></div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body></html>';
    }
}
