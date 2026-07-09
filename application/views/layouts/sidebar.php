<?php
$user      = $this->session->userdata('user');
$seg1      = $this->uri->segment(1);
$seg2      = $this->uri->segment(2);
$role_slug = $user['role_slug'] ?? '';

$role_badge_class = match($role_slug) {
    'super_admin'  => 'bg-danger',
    'admin'        => 'bg-primary',
    'project_head' => 'bg-info',
    default        => 'bg-secondary'
};

$has_sidebar = in_array($role_slug, ['super_admin', 'admin', 'project_head']);

$initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
?>

<!-- ═══ SIDEBAR ═══ -->
<?php if ($has_sidebar): ?>
<nav id="sidebar">

    <!-- Brand -->
    <div class="sb-brand">
        <div class="logo-box">
            <i class="fas fa-cubes text-white" style="font-size:.95rem;"></i>
        </div>
        <div>
            <div class="brand-name">GH Software</div>
            <div class="brand-sub">SOLUTION</div>
        </div>
    </div>

    <!-- Logged-in User -->
    <div class="sb-user">
        <div class="u-avatar"><?= $initials ?></div>
        <div>
            <div class="u-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
            <span class="u-badge <?= $role_badge_class ?> text-white">
                <?= htmlspecialchars($user['role_name']) ?>
            </span>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sb-nav">
        <div class="nav-section">Main</div>

        <a href="<?= base_url('dashboard') ?>" class="nav-link <?= $seg1 === 'dashboard' || $seg1 === '' ? 'active' : '' ?>">
            <i class="fas fa-home ni"></i>
            <span>Dashboard</span>
        </a>

        

        <div class="nav-section">HR Management</div>

        <?php if (in_array($role_slug, ['super_admin', 'admin'])): ?>
        <?php $empOpen = in_array($seg1, ['employees', 'Employees', 'attendance', 'SalaryRequest', 'payroll', 'Payroll']); ?>
        

        <a href="<?= base_url('employees') ?>" class="nav-link <?= in_array($seg1, ['employees', 'Employees']) ? 'active' : '' ?>">
            <i class="fas fa-id-card ni"></i>
            <span>Employees</span>
        </a>
        <a href="<?= base_url('attendance') ?>" class="nav-link <?= $seg1 === 'attendance' && $seg2 !== 'scanner' ? 'active' : '' ?>">
            <i class="fas fa-clock ni"></i>
            <span>Attendance</span>
        </a>
        <a href="<?= base_url('attendance/scanner') ?>" class="nav-link" target="_blank" rel="noopener">
            <i class="fas fa-barcode ni"></i>
            <span>Entrance Kiosk</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size:.6rem;opacity:.6;"></i>
        </a>
        <?php if (false): // fingerprint kiosk — temporarily replaced by the badge scan station until the .NET scanner program is ready ?>
        <a href="<?= base_url('scanner') ?>" class="nav-link" target="_blank" rel="noopener">
            <i class="fas fa-fingerprint ni"></i>
            <span>Fingerprint Kiosk</span>
            <i class="fas fa-external-link-alt ms-auto" style="font-size:.6rem;opacity:.6;"></i>
        </a>
        <?php endif; ?>
        <?php if ($role_slug === 'super_admin'): ?>
        <a href="<?= base_url('SalaryRequest') ?>" class="nav-link <?= $seg1 === 'SalaryRequest' ? 'active' : '' ?>" id="sr-nav-link">
            <i class="fas fa-money-bill-wave ni"></i>
            <span>Salary Requests</span>
            <span class="badge bg-danger rounded-pill ms-auto d-none" id="sr-pending-badge" style="font-size:.6rem;"></span>
        </a>
        <?php endif; ?>
        <a href="<?= base_url('payroll') ?>" class="nav-link <?= in_array($seg1, ['payroll', 'Payroll']) ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar ni"></i>
            <span>Payroll Report</span>
        </a>
        <?php elseif ($role_slug === 'project_head'): ?>
        <a href="<?= base_url('employees') ?>" class="nav-link <?= in_array($seg1, ['employees', 'Employees']) ? 'active' : '' ?>">
            <i class="fas fa-id-card ni"></i>
            <span>My Employees</span>
        </a>
        <a href="<?= base_url('attendance') ?>" class="nav-link <?= $seg1 === 'attendance' ? 'active' : '' ?>">
            <i class="fas fa-clock ni"></i>
            <span>Attendance</span>
        </a>
        <?php else: ?>
        <a href="<?= base_url('attendance') ?>" class="nav-link <?= $seg1 === 'attendance' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check ni"></i>
            <span>Timesheet</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($role_slug, ['super_admin', 'admin'])): ?>
        <div class="nav-section">Management</div>

            <a href="<?= base_url('users') ?>" class="nav-link <?= $seg1 === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users ni"></i>
                <span>Users</span>
            </a>

            <a href="<?= base_url('system_config') ?>" class="nav-link <?= $seg1 === 'system_config' ? 'active' : '' ?>">
                <i class="fas fa-cogs ni"></i>
                <span>System Config</span>
            </a>
        <?php endif; ?>
    </div>

</nav>
<?php endif; ?>
<!-- ═══ /SIDEBAR ═══ -->

<!-- ═══ MAIN WRAPPER ═══ -->
<div id="main-wrap" <?= !$has_sidebar ? 'style="margin-left:0"' : '' ?>>

    <!-- Topbar -->
    <div id="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light d-lg-none" onclick="openSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0" style="font-size:.83rem;">
                    <li class="breadcrumb-item">
                        <a href="<?= base_url('dashboard') ?>" class="text-decoration-none text-primary">Home</a>
                    </li>
                    <?php if (!empty($title) && $title !== 'Dashboard'): ?>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($title) ?></li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>

        
        <!-- Notification Bell -->
        <div class="dropdown me-1" id="notif-wrap">
            <button class="btn btn-sm btn-light position-relative px-2 py-1"
                    type="button" id="notif-bell-btn"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                      id="notif-count-badge" style="font-size:.6rem;">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0"
                 style="min-width:320px;max-width:360px;" id="notif-dropdown">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                    <h6 class="mb-0 fw-bold small">Notifications</h6>
                    <button class="btn btn-xs btn-outline-secondary" id="notif-mark-all-btn">
                        Mark all read
                    </button>
                </div>
                <div id="notif-list" style="max-height:360px;overflow-y:auto;">
                    <div class="text-center text-muted py-4 small">Loading…</div>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2 py-2"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle text-primary"></i>
                <span class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($user['first_name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width:180px;">
                <li>
                    <div class="px-3 py-2 border-bottom">
                        <div class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= base_url('auth/logout') ?>">
                        <i class="fas fa-sign-out-alt me-2 text-danger"></i>
                        <span class="text-danger">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <!-- /Topbar -->

    <!-- Page Content -->
    <div id="page-content">
