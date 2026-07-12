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
    <title>Portal | <?= htmlspecialchars($site['system_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- App Stylesheet (?v= busts browser/proxy caches whenever the file changes) -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/style.css') ?>">
    <style>
        .portal-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, #f0f4ff 0%, #f8f9fc 50%, #eef7f1 100%);
        }
        .portal-logo-box {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0d6efd, #3b8bfd);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 20px rgba(13, 110, 253, .25);
        }
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.25rem;
            width: 100%;
            max-width: 1050px;
            margin-top: 2.25rem;
        }
        .portal-card {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .06);
            border-radius: 1rem;
            padding: 2rem 1.5rem;
            text-align: center;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .35rem;
            box-shadow: 0 2px 8px rgba(16, 24, 40, .05);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .portal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 30px rgba(16, 24, 40, .12);
            border-color: rgba(13, 110, 253, .35);
            color: inherit;
        }
        .portal-card .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: .75rem;
        }
        .portal-card .icon-website    { background: rgba(13, 110, 253, .12);  color: #0d6efd; }
        .portal-card .icon-hrms       { background: rgba(25, 135, 84, .12);   color: #198754; }
        .portal-card .icon-accounting { background: rgba(255, 193, 7, .18);   color: #b58900; }
        .portal-card .icon-inventory  { background: rgba(111, 66, 193, .12);  color: #6f42c1; }
        .portal-card h6 { font-weight: 700; margin-bottom: 0; }
        .portal-card p  { font-size: .82rem; color: #6c757d; margin-bottom: 0; }
        .portal-card .open-hint {
            font-size: .78rem;
            font-weight: 600;
            color: #0d6efd;
            margin-top: .75rem;
            opacity: 0;
            transition: opacity .18s ease;
        }
        .portal-card:hover .open-hint { opacity: 1; }
        .portal-copyright {
            margin-top: 2.5rem;
            font-size: .8rem;
            color: #8a94a6;
        }
    </style>
</head>
<body>
<div class="portal-page">

    <!-- Header -->
    <div class="text-center">
        <div class="portal-logo-box" <?= !empty($site['company_logo']) ? 'style="background:#fff;overflow:hidden;"' : '' ?>>
            <?php if (!empty($site['company_logo'])): ?>
            <img src="<?= base_url($site['company_logo']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;">
            <?php else: ?>
            <i class="fas fa-cubes text-white" style="font-size:1.5rem;"></i>
            <?php endif; ?>
        </div>
        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($site['system_name']) ?></h4>
        <p class="text-muted mb-0" style="font-size:.9rem;">Select a system to continue</p>
    </div>

    <!-- System Options -->
    <div class="portal-grid">

        <a href="<?= base_url('website') ?>" class="portal-card">
            <div class="icon-circle icon-website">
                <i class="fas fa-globe"></i>
            </div>
            <h6>Website</h6>
            <p>Company website and public information</p>
            <span class="open-hint">Open <i class="fas fa-arrow-right ms-1"></i></span>
        </a>

        <a href="<?= base_url('auth/login') ?>" class="portal-card">
            <div class="icon-circle icon-hrms">
                <i class="fas fa-users"></i>
            </div>
            <h6>HRMS</h6>
            <p>Employees, attendance, and payroll management</p>
            <span class="open-hint">Open <i class="fas fa-arrow-right ms-1"></i></span>
        </a>

        <a href="<?= base_url('accounting') ?>" class="portal-card">
            <div class="icon-circle icon-accounting">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <h6>Accounting</h6>
            <p>Financial records, billing, and reports</p>
            <span class="open-hint">Open <i class="fas fa-arrow-right ms-1"></i></span>
        </a>

        <a href="<?= base_url('inventory') ?>" class="portal-card">
            <div class="icon-circle icon-inventory">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <h6>Inventory</h6>
            <p>Stock, materials, and equipment tracking</p>
            <span class="open-hint">Open <i class="fas fa-arrow-right ms-1"></i></span>
        </a>

    </div>

    <p class="portal-copyright">&copy; <?= date('Y') ?> <?= htmlspecialchars($site['system_name']) ?>. All rights reserved.</p>
</div>
</body>
</html>
