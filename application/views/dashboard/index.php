<?php $user = $this->session->userdata('user'); ?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-home me-2 text-primary"></i>Dashboard</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">
            Welcome back, <strong><?= htmlspecialchars($user['first_name']) ?></strong>!
            Here's what's happening today.
        </p>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" style="font-size:.78rem;">
        <i class="fas fa-calendar-alt me-1"></i><?= date('F d, Y') ?>
    </span>
</div>

<!-- ── Stat Cards ─────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Total Users</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($total_users) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Registered accounts</p>
                    </div>
                    <div class="icon-wrap" style="background:#eef2ff;">
                        <i class="fas fa-users" style="color:#4361ee;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Active Users</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($active_users) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Currently active</p>
                    </div>
                    <div class="icon-wrap" style="background:#e8f8f0;">
                        <i class="fas fa-user-check" style="color:#198754;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Admins</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($total_admins) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Admin role accounts</p>
                    </div>
                    <div class="icon-wrap" style="background:#fff3e0;">
                        <i class="fas fa-user-shield" style="color:#fd7e14;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Employees</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($total_employees) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Employee accounts</p>
                    </div>
                    <div class="icon-wrap" style="background:#fce4ec;">
                        <i class="fas fa-id-badge" style="color:#e91e63;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Upcoming Modules ───────────────────────────── -->
<div class="card dt-card">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
        <h6 class="fw-bold mb-0">
            <i class="fas fa-rocket me-2 text-primary"></i>Upcoming Modules
        </h6>
        <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">Features that will be available in future updates</p>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <?php
            $modules = [
                ['icon' => 'fa-user-tie',     'name' => 'Customers', 'color' => '#4361ee', 'bg' => '#eef2ff'],
                ['icon' => 'fa-shopping-cart', 'name' => 'Sales',     'color' => '#198754', 'bg' => '#e8f8f0'],
                ['icon' => 'fa-boxes',         'name' => 'Inventory', 'color' => '#fd7e14', 'bg' => '#fff3e0'],
                ['icon' => 'fa-chart-line',    'name' => 'Financial', 'color' => '#6f42c1', 'bg' => '#f3eeff'],
                ['icon' => 'fa-clock',         'name' => 'Attendance','color' => '#e91e63', 'bg' => '#fce4ec'],
            ];
            foreach ($modules as $m): ?>
            <div class="col-xl-2 col-md-3 col-6">
                <div class="d-flex flex-column align-items-center p-3 rounded-3 text-center h-100"
                     style="background:<?= $m['bg'] ?>;">
                    <div style="width:48px;height:48px;background:<?= $m['color'] ?>22;border-radius:13px;
                                display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                        <i class="fas <?= $m['icon'] ?>" style="color:<?= $m['color'] ?>;font-size:1.2rem;"></i>
                    </div>
                    <span style="font-size:.82rem;font-weight:600;color:#333;"><?= $m['name'] ?></span>
                    <span class="badge bg-secondary mt-2" style="font-size:.62rem;">Coming Soon</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
