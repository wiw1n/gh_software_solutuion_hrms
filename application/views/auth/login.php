<?php
$CI =& get_instance();
$CI->load->model('Setting_model');
$site = $CI->Setting_model->get_all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars($site['system_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- App Stylesheet -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="login-body">
<div class="login-wrapper">
    <div class="login-card card">

        <!-- Header -->
        <div class="login-header">
            <div class="logo-box" <?= !empty($site['company_logo']) ? 'style="background:#fff;overflow:hidden;"' : '' ?>>
                <?php if (!empty($site['company_logo'])): ?>
                <img src="<?= base_url($site['company_logo']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;">
                <?php else: ?>
                <i class="fas fa-cubes text-white" style="font-size:1.3rem;"></i>
                <?php endif; ?>
            </div>
            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($site['system_name']) ?></h5>
            <p class="text-muted mb-0" style="font-size:.83rem;">Admin Management System</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <p class="text-muted mb-4" style="font-size:.85rem;">
                <strong class="text-dark">Welcome back!</strong> Sign in to your account to continue.
            </p>

            <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">
                <i class="fas fa-exclamation-circle flex-shrink-0"></i>
                <span><?= $this->session->flashdata('error') ?></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
                <i class="fas fa-check-circle flex-shrink-0"></i>
                <span><?= $this->session->flashdata('success') ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= base_url('auth/login') ?>" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-user text-muted" style="font-size:.85rem;"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-1" id="username"
                               name="username" placeholder="Enter your username"
                               value="<?= set_value('username') ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-lock text-muted" style="font-size:.85rem;"></i>
                        </span>
                        <input type="password" class="form-control border-start-0 ps-1 border-end-0"
                               id="password" name="password" placeholder="Enter your password" required>
                        <button class="btn btn-light border" type="button" id="toggle-pass">
                            <i class="fas fa-eye text-muted" style="font-size:.85rem;"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-login w-100 rounded-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
        </div>
    </div>

    <p class="copyright">&copy; <?= date('Y') ?> <?= htmlspecialchars($site['system_name']) ?>. All rights reserved.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('toggle-pass').addEventListener('click', function () {
        const p = document.getElementById('password');
        const i = this.querySelector('i');
        if (p.type === 'password') {
            p.type = 'text';
            i.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            p.type = 'password';
            i.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
</script>
</body>
</html>
