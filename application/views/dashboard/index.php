<?php
$user = $this->session->userdata('user');

if (!function_exists('dash_time')) {
    function dash_time($t) {
        return $t ? date('g:i A', strtotime($t)) : '<span class="text-muted">—</span>';
    }
}

$status_badges = [
    'present'  => ['bg-success-subtle text-success',   'Present'],
    'absent'   => ['bg-danger-subtle text-danger',     'Absent'],
    'half_day' => ['bg-warning-subtle text-warning',   'Half Day'],
    'holiday'  => ['bg-info-subtle text-info',         'Holiday'],
    'leave'    => ['bg-primary-subtle text-primary',   'Leave'],
    'off'      => ['bg-secondary-subtle text-secondary', 'Off'],
];
$is_am_pm = ($att_mode === 'am_pm');
?>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-home me-2 text-primary"></i>HR Dashboard</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">
            Welcome back, <strong><?= htmlspecialchars($user['first_name']) ?></strong>!
            Here's your workforce overview for today.
        </p>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" style="font-size:.78rem;">
        <i class="fas fa-calendar-alt me-1"></i><?= date('F d, Y') ?>
    </span>
</div>

<!-- ── Workforce Stats ────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100 position-relative">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Total Employees</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['total_employees']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;"><?= number_format($stats['active_employees']) ?> active</p>
                    </div>
                    <div class="icon-wrap" style="background:#eef2ff;">
                        <i class="fas fa-users" style="color:#4361ee;"></i>
                    </div>
                </div>
            </div>
            <a href="<?= base_url('employees') ?>" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100 position-relative">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Present Today</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['present_today']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">of <?= number_format($stats['active_employees']) ?> active employees</p>
                    </div>
                    <div class="icon-wrap" style="background:#e8f8f0;">
                        <i class="fas fa-user-check" style="color:#198754;"></i>
                    </div>
                </div>
            </div>
            <a href="<?= base_url('attendance') ?>" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100 position-relative">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Late Today</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['late_today']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Clocked in past schedule</p>
                    </div>
                    <div class="icon-wrap" style="background:#fff3e0;">
                        <i class="fas fa-user-clock" style="color:#fd7e14;"></i>
                    </div>
                </div>
            </div>
            <a href="<?= base_url('attendance') ?>" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Not Clocked In</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['not_clocked_in']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;"><?= number_format($stats['on_leave_today']) ?> on leave today</p>
                    </div>
                    <div class="icon-wrap" style="background:#fce4ec;">
                        <i class="fas fa-user-slash" style="color:#e91e63;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── HR Action Items ────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100 position-relative">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Pending OT Approvals</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['pending_ot']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Overtime awaiting review</p>
                    </div>
                    <div class="icon-wrap" style="background:#f3eeff;">
                        <i class="fas fa-business-time" style="color:#6f42c1;"></i>
                    </div>
                </div>
            </div>
            <a href="<?= base_url('attendance') ?>" class="stretched-link"></a>
        </div>
    </div>

    <?php if ($is_super): ?>
    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100 position-relative">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Salary Requests</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['pending_salary']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Pending approval</p>
                    </div>
                    <div class="icon-wrap" style="background:#e8f8f0;">
                        <i class="fas fa-money-bill-wave" style="color:#198754;"></i>
                    </div>
                </div>
            </div>
            <a href="<?= base_url('SalaryRequest') ?>" class="stretched-link"></a>
        </div>
    </div>
    <?php else: ?>
    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">On Leave Today</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['on_leave_today']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Approved leave</p>
                    </div>
                    <div class="icon-wrap" style="background:#e8f8f0;">
                        <i class="fas fa-umbrella-beach" style="color:#198754;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100 position-relative">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">Active Projects</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['active_projects']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Ongoing sites</p>
                    </div>
                    <div class="icon-wrap" style="background:#e0f2fe;">
                        <i class="fas fa-hard-hat" style="color:#0284c7;"></i>
                    </div>
                </div>
            </div>
            <a href="<?= base_url('system_config') ?>" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card stat-card h-100 position-relative">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1" style="font-size:.73rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:600;">New Hires</p>
                        <h2 class="fw-bold mb-0" style="color:#1a2442;"><?= number_format($stats['new_hires_month']) ?></h2>
                        <p class="text-muted mb-0 mt-1" style="font-size:.78rem;">Hired in <?= date('F') ?></p>
                    </div>
                    <div class="icon-wrap" style="background:#eef2ff;">
                        <i class="fas fa-user-plus" style="color:#4361ee;"></i>
                    </div>
                </div>
            </div>
            <a href="<?= base_url('employees') ?>" class="stretched-link"></a>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ── Today's Attendance ─────────────────────── -->
    <div class="col-xl-8">
        <div class="card dt-card h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-clipboard-check me-2 text-primary"></i>Today's Attendance
                    </h6>
                    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">Latest clock-ins for <?= date('F d, Y') ?></p>
                </div>
                <a href="<?= base_url('attendance') ?>" class="btn btn-sm btn-outline-primary" style="font-size:.75rem;">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-4">
                <?php if (empty($today_logs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-mug-hot mb-3" style="font-size:2rem;opacity:.4;"></i>
                    <p class="mb-0" style="font-size:.87rem;">No attendance records yet today.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.84rem;">
                        <thead>
                            <tr class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.8px;">
                                <th>Employee</th>
                                <th>Job Role</th>
                                <?php if ($is_am_pm): ?>
                                <th>AM In</th><th>AM Out</th><th>PM In</th><th>PM Out</th>
                                <?php else: ?>
                                <th>Time In</th><th>Time Out</th>
                                <?php endif; ?>
                                <th>Hours</th>
                                <th>Late</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($today_logs, 0, 10) as $log): ?>
                            <tr>
                                <td class="fw-semibold" style="color:#1a2442;">
                                    <a href="<?= base_url('attendance/view/' . $log['user_id']) ?>" class="text-decoration-none" style="color:inherit;">
                                        <?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?>
                                    </a>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($log['job_role_name'] ?: '—') ?></td>
                                <?php if ($is_am_pm): ?>
                                <td><?= dash_time($log['am_time_in']) ?></td>
                                <td><?= dash_time($log['am_time_out']) ?></td>
                                <td><?= dash_time($log['pm_time_in']) ?></td>
                                <td><?= dash_time($log['pm_time_out']) ?></td>
                                <?php else: ?>
                                <td><?= dash_time($log['time_in']) ?></td>
                                <td><?= dash_time($log['time_out']) ?></td>
                                <?php endif; ?>
                                <td><?= $log['total_hours'] > 0 ? number_format($log['total_hours'], 2) : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <?php if ($log['tardiness'] > 0): ?>
                                    <span class="badge bg-warning-subtle text-warning" style="font-size:.7rem;"><?= round($log['tardiness'] * 60) ?> min</span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php [$cls, $lbl] = $status_badges[$log['status']] ?? ['bg-secondary-subtle text-secondary', ucfirst($log['status'])]; ?>
                                    <span class="badge <?= $cls ?>" style="font-size:.7rem;"><?= $lbl ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($today_logs) > 10): ?>
                <p class="text-muted text-center mt-3 mb-0" style="font-size:.78rem;">
                    Showing 10 of <?= count($today_logs) ?> records —
                    <a href="<?= base_url('attendance') ?>" class="text-decoration-none">see all in Attendance</a>
                </p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Right Column ───────────────────────────── -->
    <div class="col-xl-4 d-flex flex-column gap-3">

        <!-- Workforce by Job Role -->
        <div class="card dt-card">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h6 class="fw-bold mb-0">
                    <i class="fas fa-hard-hat me-2 text-primary"></i>Workforce by Job Role
                </h6>
                <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">Active employees per trade</p>
            </div>
            <div class="card-body p-4">
                <?php if (empty($job_roles)): ?>
                <p class="text-muted text-center mb-0" style="font-size:.85rem;">No active employees yet.</p>
                <?php else: ?>
                <?php $max = max(1, $stats['active_employees']); ?>
                <?php foreach ($job_roles as $jr): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem;">
                        <span class="fw-semibold" style="color:#1a2442;"><?= htmlspecialchars($jr['job_role']) ?></span>
                        <span class="text-muted"><?= $jr['headcount'] ?></span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-primary" style="width:<?= round($jr['headcount'] / $max * 100) ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Hires -->
        <div class="card dt-card flex-grow-1">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h6 class="fw-bold mb-0">
                    <i class="fas fa-user-plus me-2 text-primary"></i>Recent Hires
                </h6>
                <p class="text-muted mt-1 mb-0" style="font-size:.82rem;">Newest members of the team</p>
            </div>
            <div class="card-body p-4">
                <?php if (empty($recent_hires)): ?>
                <p class="text-muted text-center mb-0" style="font-size:.85rem;">No employees yet.</p>
                <?php else: ?>
                <?php foreach ($recent_hires as $i => $h): ?>
                <div class="d-flex align-items-center gap-3 <?= $i < count($recent_hires) - 1 ? 'mb-3 pb-3 border-bottom' : '' ?>">
                    <div class="avatar-initials" data-initials="<?= htmlspecialchars(strtoupper(substr($h['first_name'], 0, 1) . substr($h['last_name'], 0, 1))) ?>"></div>
                    <div class="flex-grow-1 min-width-0">
                        <a href="<?= base_url('employees/view/' . $h['id']) ?>" class="text-decoration-none fw-semibold d-block text-truncate" style="color:#1a2442;font-size:.85rem;">
                            <?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?>
                        </a>
                        <span class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($h['job_role_name'] ?: 'No job role') ?></span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary" style="font-size:.68rem;">
                        <?= date('M d, Y', strtotime($h['hired_on'])) ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
