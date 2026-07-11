<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// $week_end and $period_type are provided by the controller (period-aware)
$week_end    = $week_end ?? ($week_start ? date('Y-m-d', strtotime($week_start . ' +6 days')) : null);
$period_type = $period_type ?? 'weekly';
$is_semi     = $period_type === 'semi_monthly';
$total_gross      = array_sum(array_column($rows, 'gross_pay'));
$total_sss        = array_sum(array_column($rows, 'sss_deduct'));
$total_philhealth = array_sum(array_column($rows, 'philhealth_deduct'));
$total_pagibig    = array_sum(array_column($rows, 'pagibig_deduct'));
$total_adv_deduct = array_sum(array_column($rows, 'advance_deduct'));
$total_bor_deduct = array_sum(array_column($rows, 'borrow_deduct'));
$total_line_add   = array_sum(array_column($rows, 'line_additions'));
$total_line_deduct= array_sum(array_column($rows, 'line_deductions'));
$total_net        = array_sum(array_column($rows, 'net_pay'));
$missing_rate     = count(array_filter($rows, fn($r) => $r['daily_rate'] === null));

// Project filter (0 = all projects); appended to export/print URLs
$projects       = $projects ?? [];
$project_filter = (int)($project_filter ?? 0);
$project_qs     = $project_filter > 0 ? '?project=' . $project_filter : '';
$project_fname  = '';
foreach ($projects as $p) {
    if ((int)$p['id'] === $project_filter) { $project_fname = $p['name']; break; }
}

// Borrow rows that are approved but admin hasn't set deduction yet
$pending_borrow_decision = count(array_filter($rows, fn($r) =>
    $r['borrow_status'] === 'approved' && $r['borrow_period_deduct'] === null
));
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fas fa-file-invoice-dollar text-primary me-2"></i>Payroll Report
        </h4>
        <p class="text-muted mb-0 small">Confirmed timesheets ready for salary processing</p>
        <?php if ($week_start): ?>
        <span class="badge bg-success-subtle text-success border border-success-subtle mt-1">
            <i class="fas fa-calendar-week me-1"></i>
            <?= $is_semi ? 'Period' : 'Week of' ?> <?= date('M d', strtotime($week_start)) ?> – <?= date('M d, Y', strtotime($week_end)) ?>
        </span>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1 ms-1">
            <?= $is_semi ? '15/30 Payroll' : 'Weekly Payroll' ?>
        </span>
        <?php if ($project_fname !== ''): ?>
        <span class="badge bg-info-subtle text-info border border-info-subtle mt-1 ms-1">
            <i class="fas fa-hard-hat me-1"></i><?= htmlspecialchars($project_fname) ?>
        </span>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($missing_rate > 0): ?>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle mt-1 ms-1">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <?= $missing_rate ?> missing rate
        </span>
        <?php endif; ?>
        <?php if ($pending_borrow_decision > 0): ?>
        <span class="badge bg-info-subtle text-info border border-info-subtle mt-1 ms-1">
            <i class="fas fa-hand-holding me-1"></i>
            <?= $pending_borrow_decision ?> borrow deduction<?= $pending_borrow_decision > 1 ? 's' : '' ?> pending
        </span>
        <?php endif; ?>
    </div>
    <?php if ($week_start && !empty($rows)): ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= base_url('payroll/export/' . $week_start . '/' . $period_type . $project_qs) ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>Export Excel
        </a>
        <button onclick="window.open('<?= base_url('payroll/print_view/' . $week_start . '/' . $period_type . $project_qs) ?>', '_blank')"
                class="btn btn-outline-secondary">
            <i class="fas fa-print me-2"></i>Print
        </button>
        <button onclick="window.open('<?= base_url('payroll/payslips/' . $week_start . '/' . $period_type . $project_qs) ?>', '_blank')"
                class="btn btn-outline-primary">
            <i class="fas fa-receipt me-2"></i>Print Payslips
        </button>
        <button onclick="window.open('<?= base_url('payroll/sign_sheet/' . $week_start . '/' . $period_type . $project_qs) ?>', '_blank')"
                class="btn btn-outline-dark">
            <i class="fas fa-signature me-2"></i>Sign Sheet
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Week Selector -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" action="<?= base_url('payroll') ?>" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Select Payroll Period</label>
                <?php if (empty($weeks)): ?>
                <select class="form-select" disabled><option>No confirmed periods yet</option></select>
                <?php else: ?>
                <input type="hidden" name="week" id="pw-week" value="<?= $week_start ?>">
                <input type="hidden" name="type" id="pw-type" value="<?= $period_type ?>">
                <select class="form-select"
                        onchange="var p = this.value.split('|'); document.getElementById('pw-week').value = p[0]; document.getElementById('pw-type').value = p[1]; this.form.submit();">
                    <?php foreach ($weeks as $w): ?>
                    <?php
                        $w_semi = $w['timesheet_type'] === 'semi_monthly';
                        if ($w_semi) {
                            $we = (int)date('j', strtotime($w['week_start'])) === 1
                                ? date('Y-m-d', strtotime($w['week_start'] . ' +14 days'))
                                : date('Y-m-t', strtotime($w['week_start']));
                        } else {
                            $we = date('Y-m-d', strtotime($w['week_start'] . ' +6 days'));
                        }
                    ?>
                    <option value="<?= $w['week_start'] . '|' . $w['timesheet_type'] ?>"
                        <?= ($w['week_start'] === $week_start && $w['timesheet_type'] === $period_type) ? 'selected' : '' ?>>
                        <?= date('M d', strtotime($w['week_start'])) ?> – <?= date('M d, Y', strtotime($we)) ?>
                        <?= $w_semi ? '· 15/30' : '· Weekly' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Project</label>
                <select class="form-select" name="project" onchange="this.form.submit();">
                    <option value="0">All Projects</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $project_filter ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($weeks)): ?>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync-alt me-1"></i>Load
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($rows)): ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100">
            <div class="fs-4 fw-bold text-primary"><?= count($rows) ?></div>
            <div class="text-muted small">Employees</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100">
            <div class="fs-5 fw-bold text-success">&#8369;<?= number_format($total_gross, 2) ?></div>
            <div class="text-muted small">Total Gross Pay</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100">
            <div class="fs-5 fw-bold text-danger">&#8369;<?= number_format($total_sss + $total_philhealth + $total_pagibig, 2) ?></div>
            <div class="text-muted small">Gov't Deductions</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100">
            <div class="fs-5 fw-bold text-warning">&#8369;<?= number_format($total_adv_deduct, 2) ?></div>
            <div class="text-muted small">Advance Deducted</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100">
            <div class="fs-5 fw-bold" style="color:#7c3aed;">&#8369;<?= number_format($total_bor_deduct, 2) ?></div>
            <div class="text-muted small">Borrow Deducted</div>
        </div>
    </div>
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100">
            <div class="fs-5 fw-bold text-info">&#8369;<?= number_format($total_net, 2) ?></div>
            <div class="text-muted small">Total Net Pay</div>
        </div>
    </div>
</div>

<!-- Payroll Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width:1320px;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:44px">#</th>
                        <th>Employee</th>
                        <th class="text-center" style="width:95px">Days</th>
                        <th class="text-end"    style="width:130px">Daily Rate</th>
                        <th class="text-end"    style="width:110px">Gross Pay</th>
                        <th class="text-end"    style="width:80px">SSS</th>
                        <th class="text-end"    style="width:95px">PhilHealth</th>
                        <th class="text-end"    style="width:80px">Pag-IBIG</th>
                        <th class="text-center" style="width:155px">
                            Advance
                            <span class="text-success" style="font-size:.65rem;display:block;font-weight:400;">auto-deducted if approved</span>
                        </th>
                        <th class="text-center" style="width:170px">
                            Borrow
                            <span class="text-primary" style="font-size:.65rem;display:block;font-weight:400;">admin sets deduction</span>
                        </th>
                        <th class="text-center" style="width:130px">
                            Adjustments
                            <span class="text-muted" style="font-size:.65rem;display:block;font-weight:400;">additions &amp; deductions</span>
                        </th>
                        <th class="text-end"    style="width:115px">Net Pay</th>
                        <th style="width:145px">Confirmed By</th>
                        <th class="text-center" style="width:80px">Payslip</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                    <?php
                        $no_rate        = ($r['daily_rate'] === null);
                        $adv_status     = $r['advance_status'] ?? '';
                        $bor_status     = $r['borrow_status']  ?? '';
                        $borrow_decided = ($r['borrow_period_deduct'] !== null);
                        $has_items      = !empty($r['line_items']);
                        $adv_cls        = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$adv_status] ?? 'secondary';
                        $bor_cls        = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$bor_status] ?? 'secondary';
                    ?>
                    <tr class="<?= $no_rate ? 'table-warning' : '' ?>">
                        <td class="text-muted small ps-4"><?= $i + 1 ?></td>

                        <!-- Employee -->
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
                            <div class="text-muted" style="font-size:.74rem;">
                                <?php if (!empty($r['employee_id'])): ?>
                                <span class="text-primary fw-semibold"><?= htmlspecialchars($r['employee_id']) ?></span> ·
                                <?php endif; ?>
                                <?= htmlspecialchars($r['email']) ?>
                            </div>
                        </td>

                        <!-- Days -->
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <?= (int)$r['days_present'] ?> day<?= $r['days_present'] != 1 ? 's' : '' ?>
                            </span>
                            <?php if ($r['total_hours'] > 0): ?>
                            <div class="text-muted" style="font-size:.7rem;"><?= number_format($r['total_hours'], 2) ?> hrs</div>
                            <?php endif; ?>
                            <?php if ($r['ot_hours'] > 0): ?>
                            <div class="text-info" style="font-size:.7rem;">
                                <i class="fas fa-star me-1"></i><?= number_format($r['ot_hours'], 2) ?> OT hrs
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Daily Rate -->
                        <td class="text-end">
                            <?php if ($no_rate): ?>
                            <button class="btn btn-xs btn-warning btn-edit-payroll-settings"
                                    data-user-id="<?= $r['user_id'] ?>"
                                    data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                    data-rate="0"
                                    data-sss-enabled="0" data-sss-amount="0"
                                    data-philhealth-enabled="0" data-philhealth-amount="0"
                                    data-pagibig-enabled="0" data-pagibig-amount="0">
                                <i class="fas fa-exclamation-triangle me-1"></i>Set Rate
                            </button>
                            <?php else: ?>
                            <div class="fw-semibold">&#8369;<?= number_format($r['daily_rate'], 2) ?></div>
                            <button class="btn btn-xs btn-link text-muted p-0 btn-edit-payroll-settings"
                                    data-user-id="<?= $r['user_id'] ?>"
                                    data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                    data-rate="<?= $r['daily_rate'] ?>"
                                    data-sss-enabled="<?= (int)$r['sss_enabled'] ?>"
                                    data-sss-amount="<?= $r['sss_amount'] ?>"
                                    data-philhealth-enabled="<?= (int)$r['philhealth_enabled'] ?>"
                                    data-philhealth-amount="<?= $r['philhealth_amount'] ?>"
                                    data-pagibig-enabled="<?= (int)$r['pagibig_enabled'] ?>"
                                    data-pagibig-amount="<?= $r['pagibig_amount'] ?>">
                                <i class="fas fa-pen" style="font-size:.7rem;"></i> Edit
                            </button>
                            <?php endif; ?>
                        </td>

                        <!-- Gross Pay -->
                        <td class="text-end fw-semibold">
                            <?= $no_rate ? '<span class="text-muted">—</span>' : '&#8369;' . number_format($r['gross_pay'], 2) ?>
                            <?php if (!$no_rate && $r['ot_pay'] > 0): ?>
                            <div class="text-info fw-normal" style="font-size:.68rem;">
                                incl. &#8369;<?= number_format($r['ot_pay'], 2) ?> OT
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- SSS -->
                        <td class="text-end small">
                            <?= $r['sss_deduct'] > 0
                                ? '<span class="text-danger">&#8369;' . number_format($r['sss_deduct'], 2) . '</span>'
                                : '<span class="text-muted">—</span>' ?>
                        </td>

                        <!-- PhilHealth -->
                        <td class="text-end small">
                            <?= $r['philhealth_deduct'] > 0
                                ? '<span class="text-danger">&#8369;' . number_format($r['philhealth_deduct'], 2) . '</span>'
                                : '<span class="text-muted">—</span>' ?>
                        </td>

                        <!-- Pag-IBIG -->
                        <td class="text-end small">
                            <?= $r['pagibig_deduct'] > 0
                                ? '<span class="text-danger">&#8369;' . number_format($r['pagibig_deduct'], 2) . '</span>'
                                : '<span class="text-muted">—</span>' ?>
                        </td>

                        <!-- Advance -->
                        <td class="text-center">
                            <?php if ($r['advance_amount'] !== null): ?>
                            <span class="badge bg-<?= $adv_cls ?>-subtle text-<?= $adv_cls ?> border border-<?= $adv_cls ?>-subtle py-1 px-2" style="line-height:1.6;">
                                <div>&#8369;<?= number_format($r['advance_amount'], 2) ?></div>
                                <div style="font-size:.65rem;"><?= ucfirst($adv_status) ?></div>
                            </span>
                            <?php if ($adv_status === 'approved'): ?>
                            <div class="text-success mt-1" style="font-size:.7rem;">
                                <i class="fas fa-check-circle me-1"></i>Auto-deducted
                            </div>
                            <?php elseif ($adv_status === 'pending'): ?>
                            <div class="text-muted mt-1" style="font-size:.7rem;">
                                <i class="fas fa-clock me-1"></i>Not deducted yet
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Borrow -->
                        <td class="text-center">
                            <?php if ($r['borrow_amount'] !== null): ?>
                            <span class="badge bg-<?= $bor_cls ?>-subtle text-<?= $bor_cls ?> border border-<?= $bor_cls ?>-subtle py-1 px-2" style="line-height:1.6;">
                                <div>&#8369;<?= number_format($r['borrow_amount'], 2) ?></div>
                                <div style="font-size:.65rem;"><?= ucfirst($bor_status) ?></div>
                            </span>

                            <?php if ($bor_status === 'approved'): ?>
                            <div class="mt-1">
                                <?php if ($borrow_decided): ?>
                                <div class="<?= $r['borrow_deduct'] > 0 ? 'text-danger' : 'text-muted' ?>" style="font-size:.75rem; font-weight:600;">
                                    <?= $r['borrow_deduct'] > 0
                                        ? '<i class="fas fa-minus-circle me-1"></i>&#8369;' . number_format($r['borrow_deduct'], 2) . ' deducted'
                                        : '<i class="fas fa-ban me-1"></i>No deduction set' ?>
                                </div>
                                <button class="btn btn-xs btn-link text-primary p-0 mt-1 btn-set-borrow-deduct"
                                        data-user-id="<?= $r['user_id'] ?>"
                                        data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                        data-week-start="<?= $r['week_start'] ?>"
                                        data-borrow-amount="<?= $r['borrow_amount'] ?>"
                                        data-borrow-remaining="<?= $r['borrow_remaining'] ?>"
                                        data-current-deduct="<?= $r['borrow_period_deduct'] ?>">
                                    <i class="fas fa-pen" style="font-size:.7rem;"></i> Change
                                </button>
                                <?php else: ?>
                                <button class="btn btn-xs btn-outline-primary mt-1 btn-set-borrow-deduct"
                                        data-user-id="<?= $r['user_id'] ?>"
                                        data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                        data-week-start="<?= $r['week_start'] ?>"
                                        data-borrow-amount="<?= $r['borrow_amount'] ?>"
                                        data-borrow-remaining="<?= $r['borrow_remaining'] ?>"
                                        data-current-deduct="">
                                    <i class="fas fa-gavel me-1"></i>Set Deduction
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($r['borrow_remaining'] > 0): ?>
                            <div class="mt-1" style="font-size:.68rem; color:#7c3aed;">
                                <i class="fas fa-exclamation-circle me-1"></i>&#8369;<?= number_format($r['borrow_remaining'], 2) ?> remaining balance
                            </div>
                            <?php elseif ($r['borrow_total_borrowed'] > 0): ?>
                            <div class="mt-1 text-success" style="font-size:.68rem;">
                                <i class="fas fa-check-circle me-1"></i>Fully paid
                            </div>
                            <?php endif; ?>

                            <?php elseif ($bor_status === 'pending'): ?>
                            <div class="text-muted mt-1" style="font-size:.7rem;">
                                <i class="fas fa-clock me-1"></i>Approve first
                            </div>
                            <?php endif; ?>

                            <?php else: ?>
                            <?php if ($r['borrow_remaining'] > 0): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-1 px-2" style="font-size:.72rem;">
                                &#8369;<?= number_format($r['borrow_remaining'], 2) ?> balance
                            </span>
                            <div class="text-muted mt-1" style="font-size:.68rem;">from previous period</div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <!-- Adjustments (custom line items) -->
                        <td class="text-center">
                            <?php if ($has_items): ?>
                            <?php if ($r['line_additions'] > 0): ?>
                            <div class="text-success" style="font-size:.78rem; font-weight:600;">
                                <i class="fas fa-plus-circle me-1"></i>&#8369;<?= number_format($r['line_additions'], 2) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($r['line_deductions'] > 0): ?>
                            <div class="text-danger" style="font-size:.78rem; font-weight:600;">
                                <i class="fas fa-minus-circle me-1"></i>&#8369;<?= number_format($r['line_deductions'], 2) ?>
                            </div>
                            <?php endif; ?>
                            <button class="btn btn-xs btn-link text-primary p-0 mt-1 btn-adjust-items"
                                    data-user-id="<?= $r['user_id'] ?>"
                                    data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                    data-week-start="<?= $r['week_start'] ?>">
                                <i class="fas fa-pen" style="font-size:.7rem;"></i> Edit (<?= count($r['line_items']) ?>)
                            </button>
                            <?php else: ?>
                            <button class="btn btn-xs btn-outline-secondary btn-adjust-items"
                                    data-user-id="<?= $r['user_id'] ?>"
                                    data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                    data-week-start="<?= $r['week_start'] ?>">
                                <i class="fas fa-plus me-1"></i>Add Item
                            </button>
                            <?php endif; ?>
                        </td>

                        <!-- Net Pay -->
                        <td class="text-end">
                            <?php if ($no_rate): ?>
                            <span class="text-muted">—</span>
                            <?php else: ?>
                            <span class="fw-bold fs-6 <?= $r['net_pay'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                &#8369;<?= number_format($r['net_pay'], 2) ?>
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Confirmed By -->
                        <td>
                            <div class="small fw-semibold"><?= htmlspecialchars($r['confirmed_by_name']) ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?= date('M d, Y', strtotime($r['confirmed_at'])) ?></div>
                        </td>

                        <!-- Payslip -->
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-primary"
                                    title="Print payslip for <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                    onclick="window.open('<?= base_url('payroll/payslips/' . $week_start . '/' . $period_type . '/' . $r['user_id']) ?>', '_blank')">
                                <i class="fas fa-receipt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold border-top-2">
                    <tr>
                        <td colspan="4" class="text-end ps-4 pe-3">Totals</td>
                        <td class="text-end">&#8369;<?= number_format($total_gross, 2) ?></td>
                        <td class="text-end small text-danger">&#8369;<?= number_format($total_sss, 2) ?></td>
                        <td class="text-end small text-danger">&#8369;<?= number_format($total_philhealth, 2) ?></td>
                        <td class="text-end small text-danger">&#8369;<?= number_format($total_pagibig, 2) ?></td>
                        <td class="text-center text-warning">&#8369;<?= number_format($total_adv_deduct, 2) ?></td>
                        <td class="text-center" style="color:#7c3aed;">&#8369;<?= number_format($total_bor_deduct, 2) ?></td>
                        <td class="text-center" style="font-size:.82rem;">
                            <?php if ($total_line_add > 0): ?>
                            <span class="text-success">+&#8369;<?= number_format($total_line_add, 2) ?></span>
                            <?php endif; ?>
                            <?php if ($total_line_deduct > 0): ?>
                            <span class="text-danger d-block">-&#8369;<?= number_format($total_line_deduct, 2) ?></span>
                            <?php endif; ?>
                            <?php if ($total_line_add == 0 && $total_line_deduct == 0): ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-success fs-6">&#8369;<?= number_format($total_net, 2) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php elseif ($week_start): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="fas fa-file-invoice fa-3x text-muted mb-3 d-block"></i>
        <h6 class="text-muted mb-1">No confirmed employees for this week</h6>
        <p class="text-muted small mb-3">Confirm employee timesheets in Attendance to add them to payroll.</p>
        <a href="<?= base_url('attendance') ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-clock me-1"></i>Go to Attendance
        </a>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="fas fa-calendar-check fa-3x text-muted mb-3 d-block"></i>
        <h6 class="text-muted mb-1">No confirmed weeks found</h6>
        <p class="text-muted small mb-3">
            Open an employee's timesheet in Attendance and click <strong>Confirm Week</strong>.
        </p>
        <a href="<?= base_url('attendance') ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-clock me-1"></i>Go to Attendance
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================
     Payroll Adjustments (Line Items) Modal
================================================================ -->
<div class="modal fade" id="line-items-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:520px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>Payroll Adjustments
                    </h5>
                    <div class="text-muted small mt-1" id="li-employee-label"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="li-user-id">
                <input type="hidden" id="li-week-start">

                <!-- Existing items list -->
                <div id="li-items-list" class="mb-4">
                    <div class="text-center text-muted py-3 small" id="li-empty-state">
                        <i class="fas fa-inbox fa-lg d-block mb-1 opacity-50"></i>No adjustments yet.
                    </div>
                </div>

                <hr class="my-3">

                <!-- Add new item form -->
                <div class="fw-semibold small text-muted text-uppercase mb-3" style="letter-spacing:.06em;">
                    <i class="fas fa-plus-circle me-1 text-primary"></i>Add New Item
                </div>

                <!-- Type toggle -->
                <div class="mb-3">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="li-type" id="li-type-addition" value="addition" checked>
                        <label class="btn btn-outline-success btn-sm" for="li-type-addition">
                            <i class="fas fa-plus me-1"></i>Addition (+ to Net Pay)
                        </label>
                        <input type="radio" class="btn-check" name="li-type" id="li-type-deduction" value="deduction">
                        <label class="btn btn-outline-danger btn-sm" for="li-type-deduction">
                            <i class="fas fa-minus me-1"></i>Deduction (− from Net Pay)
                        </label>
                    </div>
                </div>

                <!-- Label -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description / Label <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="li-label"
                           placeholder="e.g. Transport Allowance, Remaining Borrow, Penalty…" maxlength="150">
                </div>

                <!-- Amount -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">&#8369;</span>
                        <input type="number" class="form-control" id="li-amount"
                               min="0.01" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <!-- Notes (optional) -->
                <div class="mb-1">
                    <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control" id="li-notes" rows="2"
                              placeholder="e.g. Week 3 borrow carry-over…" maxlength="500"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary px-4" id="btn-add-line-item">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="li-spinner"></span>
                    <i class="fas fa-plus me-1" id="li-add-icon"></i>Add Item
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
     Set Borrow Deduction Modal
================================================================ -->
<div class="modal fade" id="borrow-deduct-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-gavel me-2 text-primary"></i>Set Borrow Deduction
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="bd-user-id">
                <input type="hidden" id="bd-week-start">

                <p class="text-muted small mb-3" id="bd-employee-name"></p>

                <div class="alert alert-light border py-2 px-3 small mb-3" id="bd-borrow-info"></div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Deduct this payroll period <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">&#8369;</span>
                        <input type="number" class="form-control" id="bd-amount"
                               min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-text" id="bd-max-hint"></div>
                </div>

                <div class="alert alert-info border-0 small py-2 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Set <strong>0</strong> if you don't want to deduct anything this period.
                    The remaining balance will carry over to next week's payroll.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-borrow-deduct">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="bd-spinner"></span>
                    <i class="fas fa-save me-1" id="bd-save-icon"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
     Edit Payroll Settings Modal
================================================================ -->
<div class="modal fade" id="payroll-settings-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-sliders-h me-2 text-primary"></i>Payroll Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="ps-user-id">
                <p class="text-muted small mb-3" id="ps-employee-name"></p>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Daily Rate <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">&#8369;</span>
                        <input type="number" class="form-control" id="ps-daily-rate"
                               min="0" step="0.01" placeholder="e.g. 500.00">
                    </div>
                    <div class="form-text">Gross Pay = Days Present × Daily Rate + OT Pay (Daily Rate ÷ 8 × approved OT hrs)</div>
                </div>

                <hr class="my-3">
                <div class="fw-semibold small text-muted mb-3 text-uppercase" style="letter-spacing:.06em;">
                    <i class="fas fa-landmark me-1"></i>Government Contributions (auto-deduct)
                </div>

                <!-- SSS -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-check-label fw-semibold" for="ps-sss-enabled">SSS</label>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ps-sss-enabled">
                        </div>
                    </div>
                    <div id="ps-sss-amount-wrap" class="input-group" style="display:none;">
                        <span class="input-group-text fw-bold">&#8369;</span>
                        <input type="number" class="form-control" id="ps-sss-amount"
                               min="0" step="0.01" placeholder="Employee share per period">
                    </div>
                </div>

                <!-- PhilHealth -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-check-label fw-semibold" for="ps-philhealth-enabled">PhilHealth</label>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ps-philhealth-enabled">
                        </div>
                    </div>
                    <div id="ps-philhealth-amount-wrap" class="input-group" style="display:none;">
                        <span class="input-group-text fw-bold">&#8369;</span>
                        <input type="number" class="form-control" id="ps-philhealth-amount"
                               min="0" step="0.01" placeholder="Employee share per period">
                    </div>
                </div>

                <!-- Pag-IBIG -->
                <div class="mb-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-check-label fw-semibold" for="ps-pagibig-enabled">Pag-IBIG (HDMF)</label>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ps-pagibig-enabled">
                        </div>
                    </div>
                    <div id="ps-pagibig-amount-wrap" class="input-group" style="display:none;">
                        <span class="input-group-text fw-bold">&#8369;</span>
                        <input type="number" class="form-control" id="ps-pagibig-amount"
                               min="0" step="0.01" placeholder="Employee share per period">
                    </div>
                </div>

                <div class="alert alert-light border small mt-3 mb-0 py-2">
                    <i class="fas fa-info-circle text-primary me-1"></i>
                    Amounts are fixed deductions per payroll period (set per your contribution schedule).
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-payroll-settings">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="ps-spinner"></span>
                    <i class="fas fa-save me-1" id="ps-save-icon"></i>Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const PAYROLL_APP_URL = '<?= base_url() ?>';
const PAYROLL_WEEK    = '<?= $week_start ?>';
const PAYROLL_TYPE    = '<?= $period_type ?>';
</script>
