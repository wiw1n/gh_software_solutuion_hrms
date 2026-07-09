<?php
$emp       = $employee;
$can_admin = !empty($can_admin);
$initials  = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
$status_class = $emp['status'] === 'active' ? 'success' : 'danger';
$status_label = ucfirst($emp['status']);
$daily_rate   = !empty($emp['daily_rate']) ? '₱' . number_format((float)$emp['daily_rate'], 2) : '—';
?>

<script>
    var APP_URL        = '<?= base_url() ?>';
    var EMP_ID         = <?= (int)$emp['id'] ?>;
    var EMP_JOB_ROLE_ID = <?= json_encode($emp['job_role_id'] ?? null) ?>;
    var EMP_PROJECT_ID = <?= json_encode($emp['project_id'] ?? null) ?>;
    var JOB_ROLES      = <?= json_encode($job_roles) ?>;
    var PROJECTS       = <?= json_encode($projects) ?>;
</script>

<!-- Back + Breadcrumb -->
<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= base_url('employees') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Employees
    </a>
    <span class="text-muted small ms-1">/ <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></span>
</div>

<!-- ── Profile Header ── -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">

            <!-- Avatar + Name -->
            <div class="col-auto">
                <div class="emp-profile-avatar"><?= $initials ?></div>
            </div>
            <div class="col">
                <h4 class="mb-1 fw-bold"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h4>
                <div class="text-muted small mb-2">
                    <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($emp['email']) ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-at me-1"></i><?= htmlspecialchars($emp['username']) ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if (!empty($emp['employee_id'])): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1">
                        <i class="fas fa-id-badge me-1"></i><?= htmlspecialchars($emp['employee_id']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="badge bg-<?= $status_class ?>-subtle text-<?= $status_class ?> border border-<?= $status_class ?>-subtle px-3 py-1">
                        <i class="fas fa-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i><?= $status_label ?>
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1">
                        <i class="fas fa-user-tie me-1"></i>Employee
                    </span>
                    <?php if (!empty($emp['job_role_name'])): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1">
                        <i class="fas fa-hard-hat me-1"></i><?= htmlspecialchars($emp['job_role_name']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($emp['project_name'])): ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1">
                        <i class="fas fa-building me-1"></i><?= htmlspecialchars($emp['project_name']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($emp['last_login']): ?>
                    <span class="badge bg-light text-muted border px-3 py-1" style="font-weight:400;">
                        <i class="fas fa-clock me-1"></i>Last login: <?= date('M d, Y g:i A', strtotime($emp['last_login'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Button -->
            <div class="col-lg-auto d-flex flex-column align-items-end gap-2">
                <button class="btn btn-outline-primary btn-sm px-3" id="btn-edit-emp-info">
                    <i class="fas fa-edit me-1"></i> Edit Info
                </button>
                <?php if ($can_admin): ?>
                <button class="btn btn-outline-info btn-sm px-3" id="btn-set-project">
                    <i class="fas fa-building me-1"></i> Set Project
                </button>
                <?php endif; ?>
                <a href="<?= base_url('employees/print_id/' . $employee['id']) ?>" target="_blank"
                   class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fas fa-print me-1"></i> Print ID
                </a>
            </div>

            <!-- Stats -->
            <div class="col-lg-auto">
                <div class="d-flex gap-4 text-center">
                    <div>
                        <div class="fw-bold fs-5 text-success"><?= $stats['present_count'] ?></div>
                        <div class="text-muted" style="font-size:.75rem;">Days Present</div>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-warning"><?= $stats['pending_requests'] ?></div>
                        <div class="text-muted" style="font-size:.75rem;">Pending Req.</div>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-info"><?= !empty($emp['daily_rate']) ? '₱' . number_format((float)$emp['daily_rate'], 2) : '—' ?></div>
                        <div class="text-muted" style="font-size:.75rem;">Daily Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Tabs ── -->
<ul class="nav nav-tabs nav-tabs-card mb-0" id="empTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link active" id="tab-overview" data-bs-toggle="tab" href="#pane-overview" role="tab">
            <i class="fas fa-user me-1"></i> Overview
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="tab-payroll" data-bs-toggle="tab" href="#pane-payroll" role="tab">
            <i class="fas fa-money-check-alt me-1"></i> Payroll Info
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="tab-attendance" data-bs-toggle="tab" href="#pane-attendance" role="tab">
            <i class="fas fa-calendar-check me-1"></i> Attendance
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="tab-salary" data-bs-toggle="tab" href="#pane-salary" role="tab">
            <i class="fas fa-hand-holding-usd me-1"></i> Salary Requests
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="tab-projects" data-bs-toggle="tab" href="#pane-projects" role="tab">
            <i class="fas fa-building me-1"></i> Projects
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="tab-files" data-bs-toggle="tab" href="#pane-files" role="tab">
            <i class="fas fa-file-alt me-1"></i> Files
        </a>
    </li>
</ul>

<div class="tab-content tab-content-card" id="empTabContent">

    <!-- ════ OVERVIEW TAB ════ -->
    <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
            <div class="card-body p-4">
                <div class="row g-4">

                    <!-- ── Left: Basic Information ── -->
                    <div class="col-lg-6">
                        <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.5px;">
                            <i class="fas fa-id-card me-1 text-primary"></i> Basic Information
                        </h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="info-field">
                                    <div class="info-label">Employee ID</div>
                                    <div class="info-value fw-semibold text-primary"><?= !empty($emp['employee_id']) ? htmlspecialchars($emp['employee_id']) : '—' ?></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-field">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-field">
                                    <div class="info-label">Username</div>
                                    <div class="info-value">@<?= htmlspecialchars($emp['username']) ?></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-field">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?= htmlspecialchars($emp['email']) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-field">
                                    <div class="info-label">Job Role</div>
                                    <div class="info-value" id="ov-job-role">
                                        <?php if (!empty($emp['job_role_name'])): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                            <i class="fas fa-hard-hat me-1"></i><?= htmlspecialchars($emp['job_role_name']) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-field">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="badge bg-<?= $status_class ?>-subtle text-<?= $status_class ?> border border-<?= $status_class ?>-subtle" id="ov-status">
                                            <?= $status_label ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-field">
                                    <div class="info-label">Assigned Project</div>
                                    <div class="info-value" id="ov-project">
                                        <?php if (!empty($emp['project_name'])): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <i class="fas fa-building me-1"></i><?= htmlspecialchars($emp['project_name']) ?>
                                        </span>
                                        <?php if (!empty($emp['project_location'])): ?>
                                        <span class="text-muted small ms-1">
                                            <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($emp['project_location']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-field">
                                    <div class="info-label">Date Joined</div>
                                    <div class="info-value"><?= date('M d, Y', strtotime($emp['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-field">
                                    <div class="info-label">Date Hired</div>
                                    <div class="info-value"><?= !empty($emp['date_hired']) ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-field">
                                    <div class="info-label">Timesheet Type</div>
                                    <div class="info-value"><?= ($emp['timesheet_type'] ?? 'weekly') === 'semi_monthly' ? '15 / 30 (Semi-Monthly)' : 'Weekly' ?></div>
                                </div>
                            </div>
                            <?php if ($emp['last_login']): ?>
                            <div class="col-6">
                                <div class="info-field">
                                    <div class="info-label">Last Login</div>
                                    <div class="info-value"><?= date('M d, Y g:i A', strtotime($emp['last_login'])) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Divider ── -->
                    <div class="col-lg-auto d-none d-lg-flex">
                        <div class="vr opacity-25"></div>
                    </div>
                    <div class="col-12 d-lg-none">
                        <hr class="my-0 opacity-25">
                    </div>

                    <!-- ── Right: Emergency Contact ── -->
                    <div class="col-lg">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-muted text-uppercase mb-0" style="font-size:.75rem;letter-spacing:.5px;">
                                <i class="fas fa-phone-alt me-1 text-danger"></i> Emergency Contact
                            </h6>
                            <?php if (!empty($emergency_contact)): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem;">
                                <i class="fas fa-check me-1"></i>On file
                            </span>
                            <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.7rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i>Not set
                            </span>
                            <?php endif; ?>
                        </div>

                        <?php $ec = $emergency_contact ?? []; ?>
                        <form id="ec-form" autocomplete="off">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">
                                        Contact Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm" id="ec-name"
                                           value="<?= htmlspecialchars($ec['name'] ?? '') ?>"
                                           placeholder="Full name">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Relationship</label>
                                    <select class="form-select form-select-sm" id="ec-relationship">
                                        <?php
                                        $relationships = ['', 'Spouse', 'Parent', 'Sibling', 'Child', 'Relative', 'Friend', 'Other'];
                                        $current_rel   = $ec['relationship'] ?? '';
                                        foreach ($relationships as $rel):
                                        ?>
                                        <option value="<?= $rel ?>" <?= $current_rel === $rel ? 'selected' : '' ?>>
                                            <?= $rel === '' ? '— Select —' : $rel ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">
                                        Phone <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light"><i class="fas fa-phone" style="font-size:.75rem;"></i></span>
                                        <input type="text" class="form-control" id="ec-phone"
                                               value="<?= htmlspecialchars($ec['phone'] ?? '') ?>"
                                               placeholder="09XX XXX XXXX">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Alt. Phone</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light"><i class="fas fa-phone-volume" style="font-size:.75rem;"></i></span>
                                        <input type="text" class="form-control" id="ec-phone-alt"
                                               value="<?= htmlspecialchars($ec['phone_alt'] ?? '') ?>"
                                               placeholder="Optional">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Address</label>
                                    <input type="text" class="form-control form-control-sm" id="ec-address"
                                           value="<?= htmlspecialchars($ec['address'] ?? '') ?>"
                                           placeholder="Optional">
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-danger btn-sm px-4" id="btn-save-ec">
                                        <span class="spinner-border spinner-border-sm me-1 d-none" id="ec-spinner"></span>
                                        <i class="fas fa-save me-1" id="ec-save-icon"></i>
                                        Save Emergency Contact
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div><!-- /row -->
            </div>
        </div>
    </div>

    <!-- ════ PAYROLL INFO TAB ════ -->
    <div class="tab-pane fade" id="pane-payroll" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
            <div class="card-body p-4">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.5px;">Payroll Configuration</h6>
                <?php if (!$can_admin): ?>
                <div class="alert alert-light border small py-2 mb-3">
                    <i class="fas fa-lock me-1 text-muted"></i> Payroll information is read-only. Only administrators can edit it.
                </div>
                <?php endif; ?>
                <form id="payroll-form">
                    <fieldset <?= $can_admin ? '' : 'disabled' ?>>
                    <div class="row g-3">

                        <!-- Daily Rate -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Daily Rate (PHP) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">&#8369;</span>
                                <input type="number" class="form-control" id="pf-daily-rate"
                                       value="<?= htmlspecialchars($emp['daily_rate'] ?? '0.00') ?>"
                                       min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                            <h6 class="fw-bold text-muted text-uppercase mt-3 mb-3" style="font-size:.75rem;letter-spacing:.5px;">Government Contributions</h6>
                        </div>

                        <!-- SSS -->
                        <div class="col-md-4">
                            <div class="contribution-card" id="sss-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="contribution-icon bg-primary-subtle text-primary">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <span class="fw-semibold">SSS</span>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input contribution-toggle" type="checkbox" id="pf-sss-enabled"
                                               <?= !empty($emp['sss_enabled']) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <label class="form-label small text-muted mb-1">Employee Share / Period</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">&#8369;</span>
                                    <input type="number" class="form-control contribution-amount" id="pf-sss-amount"
                                           value="<?= htmlspecialchars($emp['sss_amount'] ?? '0.00') ?>"
                                           min="0" step="0.01" placeholder="0.00"
                                           <?= empty($emp['sss_enabled']) ? 'disabled' : '' ?>>
                                </div>
                                <div class="form-text" id="pf-sss-hint" style="font-size:.72rem;"></div>
                            </div>
                        </div>

                        <!-- PhilHealth -->
                        <div class="col-md-4">
                            <div class="contribution-card" id="philhealth-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="contribution-icon bg-success-subtle text-success">
                                            <i class="fas fa-heartbeat"></i>
                                        </div>
                                        <span class="fw-semibold">PhilHealth</span>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input contribution-toggle" type="checkbox" id="pf-philhealth-enabled"
                                               <?= !empty($emp['philhealth_enabled']) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <label class="form-label small text-muted mb-1">Employee Share / Period</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">&#8369;</span>
                                    <input type="number" class="form-control contribution-amount" id="pf-philhealth-amount"
                                           value="<?= htmlspecialchars($emp['philhealth_amount'] ?? '0.00') ?>"
                                           min="0" step="0.01" placeholder="0.00"
                                           <?= empty($emp['philhealth_enabled']) ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <!-- Pag-IBIG -->
                        <div class="col-md-4">
                            <div class="contribution-card" id="pagibig-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="contribution-icon bg-warning-subtle text-warning">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <span class="fw-semibold">Pag-IBIG</span>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input contribution-toggle" type="checkbox" id="pf-pagibig-enabled"
                                               <?= !empty($emp['pagibig_enabled']) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <label class="form-label small text-muted mb-1">Employee Share / Period</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">&#8369;</span>
                                    <input type="number" class="form-control contribution-amount" id="pf-pagibig-amount"
                                           value="<?= htmlspecialchars($emp['pagibig_amount'] ?? '0.00') ?>"
                                           min="0" step="0.01" placeholder="0.00"
                                           <?= empty($emp['pagibig_enabled']) ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <?php if ($can_admin): ?>
                        <div class="col-12 mt-2">
                            <button type="button" class="btn btn-primary px-4" id="btn-save-payroll">
                                <span class="spinner-border spinner-border-sm me-1 d-none" id="payroll-spinner"></span>
                                <i class="fas fa-save me-1" id="payroll-save-icon"></i>
                                Save Payroll Info
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>

    <!-- ════ ATTENDANCE TAB ════ -->
    <div class="tab-pane fade" id="pane-attendance" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
            <div class="card-body p-4">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.5px;">
                    Recent Attendance <span class="text-primary">(Last 30 Records)</span>
                </h6>
                <div id="attendance-content">
                    <div class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm me-2"></span> Loading attendance...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════ SALARY REQUESTS TAB ════ -->
    <div class="tab-pane fade" id="pane-salary" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
            <div class="card-body p-4">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.5px;">
                    Salary Requests <span class="text-primary">(Last 20 Records)</span>
                </h6>
                <div id="salary-requests-content">
                    <div class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm me-2"></span> Loading salary requests...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════ PROJECTS TAB ════ -->
    <div class="tab-pane fade" id="pane-projects" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-muted text-uppercase mb-0" style="font-size:.75rem;letter-spacing:.5px;">
                        <i class="fas fa-building me-1 text-info"></i> Assigned Projects
                    </h6>
                    <?php if ($can_admin): ?>
                    <button class="btn btn-outline-info btn-sm" id="btn-set-project-tab">
                        <i class="fas fa-plus me-1"></i> Add Project
                    </button>
                    <?php endif; ?>
                </div>
                <div id="project-history-content">
                    <div class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm me-2"></span> Loading project history...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════ FILES TAB ════ -->
    <div class="tab-pane fade" id="pane-files" role="tabpanel">
        <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
            <div class="card-body p-4">

                <!-- Upload form -->
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.5px;">
                    <i class="fas fa-cloud-upload-alt me-1 text-primary"></i> Upload File
                </h6>
                <form id="file-upload-form" class="mb-4" autocomplete="off">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:.82rem;">
                                File Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="ef-name"
                                   placeholder="e.g. Birth Certificate, Resume, Contract" maxlength="150">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold" style="font-size:.82rem;">
                                File <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control form-control-sm" id="ef-file"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.gif,.webp,.zip">
                            <div class="form-text" style="font-size:.72rem;">
                                PDF, Word, Excel, images, TXT, CSV or ZIP — max 10 MB.
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary btn-sm px-4 mb-4" id="btn-upload-file">
                                <span class="spinner-border spinner-border-sm me-1 d-none" id="ef-spinner"></span>
                                <i class="fas fa-upload me-1" id="ef-upload-icon"></i>
                                Upload
                            </button>
                        </div>
                    </div>
                </form>

                <hr class="opacity-25">

                <!-- Files list -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-muted text-uppercase mb-0" style="font-size:.75rem;letter-spacing:.5px;">
                        <i class="fas fa-folder-open me-1 text-warning"></i> Uploaded Files
                    </h6>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="ef-show-archived">
                        <label class="form-check-label small text-muted" for="ef-show-archived">Show archived</label>
                    </div>
                </div>
                <div id="files-content">
                    <div class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm me-2"></span> Loading files...
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- ================================================================
     Edit Employee Info Modal
================================================================ -->
<div class="modal fade" id="edit-emp-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-edit me-2 text-primary"></i>Edit Employee Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form id="edit-emp-form" autocomplete="off">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ei-first-name"
                                   value="<?= htmlspecialchars($emp['first_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ei-last-name"
                                   value="<?= htmlspecialchars($emp['last_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">@</span>
                                <input type="text" class="form-control" id="ei-username"
                                       value="<?= htmlspecialchars($emp['username']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="ei-email"
                                   value="<?= htmlspecialchars($emp['email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="ei-password"
                                       placeholder="Leave blank to keep current">
                                <button class="btn btn-outline-secondary" type="button" id="toggle-ei-pass">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Min. 6 characters. Leave blank to keep unchanged.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Job Role</label>
                            <select class="form-select" id="ei-job-role-id">
                                <option value="">— None —</option>
                                <?php foreach ($job_roles as $jr): ?>
                                <option value="<?= $jr['id'] ?>"
                                    <?= ($emp['job_role_id'] ?? null) == $jr['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($jr['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="ei-status">
                                <option value="active"   <?= $emp['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $emp['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Date Hired</label>
                            <input type="date" class="form-control" id="ei-date-hired"
                                   value="<?= htmlspecialchars($emp['date_hired'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Timesheet Type</label>
                            <select class="form-select" id="ei-timesheet-type">
                                <option value="weekly"       <?= ($emp['timesheet_type'] ?? 'weekly') === 'weekly'       ? 'selected' : '' ?>>Weekly</option>
                                <option value="semi_monthly" <?= ($emp['timesheet_type'] ?? 'weekly') === 'semi_monthly' ? 'selected' : '' ?>>15 / 30 (Semi-Monthly)</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-emp-info">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="ei-spinner"></span>
                    <i class="fas fa-save me-1" id="ei-save-icon"></i>
                    Save Changes
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ================================================================
     Set Project Modal
================================================================ -->
<div class="modal fade" id="set-project-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-building me-2 text-info"></i>Set Project
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <label class="form-label fw-semibold">Project</label>
                <select class="form-select" id="sp-project-id">
                    <option value="">— No Project —</option>
                    <?php
                    // Include the currently assigned project even if it is no longer active
                    if (!empty($emp['project_id']) && !in_array($emp['project_id'], array_column($projects, 'id'))): ?>
                    <option value="<?= $emp['project_id'] ?>" selected>
                        <?= htmlspecialchars($emp['project_name']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $emp['project_status'])) ?>)
                    </option>
                    <?php endif; ?>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"
                        <?= ($emp['project_id'] ?? null) == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name']) ?><?= !empty($p['location']) ? ' — ' . htmlspecialchars($p['location']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Only active projects are listed. Manage projects in System Configuration.</div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-project">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="sp-spinner"></span>
                    <i class="fas fa-save me-1" id="sp-save-icon"></i>
                    Save Assignment
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ── Inline styles for this page ── -->
<style>
.emp-profile-avatar {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    font-weight: 700;
    flex-shrink: 0;
}

.nav-tabs-card .nav-link {
    border-radius: .5rem .5rem 0 0;
    font-size: .85rem;
    color: #6c757d;
    padding: .6rem 1.1rem;
}
.nav-tabs-card .nav-link.active {
    color: var(--accent);
    font-weight: 600;
}

.tab-content-card .tab-pane > .card {
    border-radius: 0 0 var(--card-radius) var(--card-radius);
}

.info-field {
    background: #f8f9fa;
    border-radius: 8px;
    padding: .75rem 1rem;
}
.info-label {
    font-size: .73rem;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: #8a94a6;
    font-weight: 600;
    margin-bottom: .25rem;
}
.info-value {
    font-size: .9rem;
    font-weight: 500;
    color: #1a2442;
}

.contribution-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1rem;
    border: 1px solid #e9ecef;
    transition: border-color .2s;
}
.contribution-card:hover {
    border-color: #c5cae0;
}
.contribution-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
}

.att-table th {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: #8a94a6;
    font-weight: 600;
}
.att-table td {
    font-size: .85rem;
}
</style>
