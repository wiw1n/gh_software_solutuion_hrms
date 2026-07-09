<?php
// Reusable timesheet panel — included by index.php and admin.php
// Requires: $today_rec (array|null), $view_user (array), $can_edit (bool),
//           $att_mode ('am_pm'|'single'), $att_cfg (schedule), $next_punch,
//           IS_SELF / VIEW_USER_ID / ATT_MODE set in outer view
$today_str = date('Y-m-d');
$is_am_pm  = ($att_mode ?? 'single') === 'am_pm';
$np        = $next_punch ?? 'time_in';
$has_in    = !empty($today_rec['time_in']);
$has_out   = !empty($today_rec['time_out']);

$fmt_t = function ($t) { return $t ? date('h:i A', strtotime($t)) : '—'; };

// Next-punch button config: label, endpoint action, style
$punch_btns = [
    'time_in'  => ['label' => 'Clock In',     'action' => 'time_in',  'class' => 'btn-success', 'icon' => 'fa-sign-in-alt'],
    'time_out' => ['label' => 'Clock Out',    'action' => 'time_out', 'class' => 'btn-danger',  'icon' => 'fa-sign-out-alt'],
    'am_in'    => ['label' => 'AM Time In',   'action' => 'time_in',  'class' => 'btn-success', 'icon' => 'fa-sign-in-alt'],
    'am_out'   => ['label' => 'AM Time Out',  'action' => 'time_out', 'class' => 'btn-danger',  'icon' => 'fa-sign-out-alt'],
    'pm_in'    => ['label' => 'PM Time In',   'action' => 'time_in',  'class' => 'btn-success', 'icon' => 'fa-sign-in-alt'],
    'pm_out'   => ['label' => 'PM Time Out',  'action' => 'time_out', 'class' => 'btn-danger',  'icon' => 'fa-sign-out-alt'],
];
?>

<!-- ── Clock Status Card ── -->
<?php if ($can_edit || (bool)($view_user['id'] == $this->session->userdata('user')['id'])): ?>
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h6 class="fw-bold mb-1"><i class="fas fa-clock text-primary me-2"></i>Today — <?= date('l, F j, Y') ?></h6>

                <?php if ($is_am_pm): ?>
                    <?php if (!$today_rec): ?>
                        <p class="text-muted mb-0 small">You haven't clocked in yet.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2 mt-1 small">
                            <span class="badge bg-light text-dark border px-2 py-1">
                                <span class="fw-semibold text-primary">AM</span>&nbsp;
                                <i class="fas fa-sign-in-alt text-success me-1"></i><?= $fmt_t($today_rec['am_time_in'] ?? null) ?>
                                &rarr;
                                <i class="fas fa-sign-out-alt text-danger ms-1 me-1"></i><?= $fmt_t($today_rec['am_time_out'] ?? null) ?>
                            </span>
                            <span class="badge bg-light text-dark border px-2 py-1">
                                <span class="fw-semibold text-primary">PM</span>&nbsp;
                                <i class="fas fa-sign-in-alt text-success me-1"></i><?= $fmt_t($today_rec['pm_time_in'] ?? null) ?>
                                &rarr;
                                <i class="fas fa-sign-out-alt text-danger ms-1 me-1"></i><?= $fmt_t($today_rec['pm_time_out'] ?? null) ?>
                            </span>
                            <?php if (($today_rec['total_hours'] ?? 0) > 0): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                <?= number_format($today_rec['total_hours'], 2) ?> hrs
                            </span>
                            <?php endif; ?>
                            <?php if (($today_rec['tardiness'] ?? 0) > 0): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                <i class="fas fa-exclamation-triangle me-1"></i><?= number_format($today_rec['tardiness'], 0) ?> min late
                            </span>
                            <?php endif; ?>
                            <?php if (($today_rec['overtime'] ?? 0) > 0): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                <i class="fas fa-star me-1"></i><?= number_format($today_rec['overtime'], 2) ?> OT
                            </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <p class="text-muted mb-0 small mt-1">
                        Schedule: AM <?= $fmt_t($att_cfg['am_in']) ?>–<?= $fmt_t($att_cfg['am_out']) ?>
                        &nbsp;·&nbsp; PM <?= $fmt_t($att_cfg['pm_in']) ?>–<?= $fmt_t($att_cfg['pm_out']) ?>
                    </p>
                <?php else: ?>
                    <?php if (!$has_in): ?>
                        <p class="text-muted mb-0 small">You haven't clocked in yet.</p>
                    <?php elseif ($has_in && !$has_out): ?>
                        <p class="text-success mb-0 small">
                            <i class="fas fa-sign-in-alt me-1"></i>
                            Clocked in at <strong><?= date('h:i A', strtotime($today_rec['time_in'])) ?></strong>
                            <?php if ($today_rec['tardiness'] > 0): ?>
                            <span class="text-danger ms-2">(<i class="fas fa-exclamation-triangle me-1"></i><?= number_format($today_rec['tardiness'], 0) ?> min late)</span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted mb-0 small">
                            <i class="fas fa-sign-in-alt text-success me-1"></i><strong><?= date('h:i A', strtotime($today_rec['time_in'])) ?></strong>
                            &nbsp;&rarr;&nbsp;
                            <i class="fas fa-sign-out-alt text-danger me-1"></i><strong><?= date('h:i A', strtotime($today_rec['time_out'])) ?></strong>
                            &nbsp;|&nbsp;
                            <strong><?= number_format($today_rec['total_hours'], 2) ?> hrs</strong>
                            <?php if ($today_rec['overtime'] > 0): ?>
                            <span class="text-info ms-2"><i class="fas fa-star me-1"></i><?= number_format($today_rec['overtime'], 2) ?> OT</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <?php if ($np === 'done'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2" style="font-size:.85rem;">
                        <i class="fas fa-check-circle me-1"></i>Attendance Complete
                    </span>
                <?php elseif (isset($punch_btns[$np])): $btn = $punch_btns[$np]; ?>
                    <button class="btn <?= $btn['class'] ?> px-4" id="btn-next-punch"
                            onclick="openCameraModal('<?= $btn['action'] ?>', '<?= $btn['label'] ?>')">
                        <i class="fas <?= $btn['icon'] ?> me-2"></i><?= $btn['label'] ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Month Navigator ── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-outline-secondary btn-sm px-3" id="btn-prev-month">
                <i class="fas fa-chevron-left"></i>
            </button>
            <h5 class="mb-0 fw-bold" id="month-label">Loading...</h5>
            <button class="btn btn-outline-secondary btn-sm px-3" id="btn-next-month">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <!-- Summary Row -->
        <div class="row g-3 mb-4" id="summary-cards">
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-4 fw-bold text-success" id="sum-present">—</div>
                    <div class="text-muted small">Days Present</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-4 fw-bold text-primary" id="sum-hours">—</div>
                    <div class="text-muted small">Total Hours</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-4 fw-bold text-danger" id="sum-tardiness">—</div>
                    <div class="text-muted small">Tardiness (min)</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-4 fw-bold text-info" id="sum-ot">—</div>
                    <div class="text-muted small">Overtime (hrs)</div>
                </div>
            </div>
        </div>

        <!-- Timesheet Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0" id="timesheet-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:110px">Date</th>
                        <th style="width:100px">Day</th>
                        <th style="width:110px">Status</th>
                        <?php if ($is_am_pm): ?>
                        <th style="width:95px">AM In</th>
                        <th style="width:95px">AM Out</th>
                        <th style="width:95px">PM In</th>
                        <th style="width:95px">PM Out</th>
                        <?php else: ?>
                        <th style="width:105px">Time In</th>
                        <th style="width:105px">Time Out</th>
                        <?php endif; ?>
                        <th style="width:100px" class="text-center">Total Hrs</th>
                        <th style="width:105px" class="text-center">Tardiness</th>
                        <th style="width:90px" class="text-center">OT (hrs)</th>
                        <th>Notes</th>
                        <th style="width:100px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="timesheet-tbody">
                    <tr>
                        <td colspan="<?= $is_am_pm ? 12 : 10 ?>" class="text-center py-4">
                            <span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- ================================================================
     Camera Modal (Time In / Out)
================================================================ -->
<div class="modal fade" id="camera-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="camera-modal-title">
                    <i class="fas fa-camera me-2 text-primary"></i>Clock In
                </h5>
                <button type="button" class="btn-close" id="camera-close-btn" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4 text-center">
                <div class="position-relative d-inline-block w-100" style="background:#000;border-radius:8px;overflow:hidden;min-height:260px;">
                    <video id="camera-video" autoplay playsinline class="w-100" style="border-radius:8px;display:block;"></video>
                    <canvas id="camera-canvas" class="d-none"></canvas>
                    <img id="camera-preview" class="w-100 d-none" style="border-radius:8px;" alt="Captured photo">
                </div>
                <p class="text-muted small mt-2 mb-0" id="camera-hint">Position yourself clearly in the frame, then click Capture.</p>
            </div>

            <div class="modal-footer border-0 pt-0 justify-content-between">
                <div>
                    <button class="btn btn-outline-secondary" id="btn-retake" style="display:none;">
                        <i class="fas fa-redo me-1"></i>Retake
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" id="btn-capture">
                        <i class="fas fa-camera me-1"></i>Capture
                    </button>
                    <button class="btn btn-success d-none" id="btn-submit-clock">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="clock-spinner"></span>
                        <i class="fas fa-check me-1" id="clock-icon"></i>Confirm
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ================================================================
     Photo Viewer Modal
================================================================ -->
<div class="modal fade" id="photo-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-images me-2 text-primary"></i>Attendance Photos
                    <span class="text-muted fw-normal small ms-2" id="photo-date-label"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-3" id="photo-slots"></div>
            </div>

        </div>
    </div>
</div>

<!-- ================================================================
     Edit Record Modal (Admin)
================================================================ -->
<?php if ($can_edit): ?>
<div class="modal fade" id="edit-record-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit me-2 text-primary"></i>Edit Attendance Record
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form id="edit-record-form">
                    <input type="hidden" id="er-record-id" value="0">
                    <input type="hidden" id="er-user-id" value="">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" class="form-control" id="er-date">
                        </div>
                        <?php if ($is_am_pm): ?>
                        <div class="col-6">
                            <label class="form-label fw-semibold">AM Time In</label>
                            <input type="time" class="form-control" id="er-am-time-in">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">AM Time Out</label>
                            <input type="time" class="form-control" id="er-am-time-out">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">PM Time In</label>
                            <input type="time" class="form-control" id="er-pm-time-in">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">PM Time Out</label>
                            <input type="time" class="form-control" id="er-pm-time-out">
                        </div>
                        <?php else: ?>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Time In</label>
                            <input type="time" class="form-control" id="er-time-in">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Time Out</label>
                            <input type="time" class="form-control" id="er-time-out">
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="er-status">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="half_day">Half Day</option>
                                <option value="holiday">Holiday</option>
                                <option value="leave">Leave</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" id="er-notes" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-record">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="er-spinner"></span>
                    <i class="fas fa-save me-1"></i>Save
                </button>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================
     Salary Request Modal
================================================================ -->
<div class="modal fade" id="salary-request-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="salary-modal-title">
                    <i class="fas fa-money-bill-wave me-2 text-success"></i>Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <input type="hidden" id="sr-id" value="0">
                <input type="hidden" id="sr-type" value="">
                <input type="hidden" id="sr-week-start" value="">

                <div class="alert alert-light border small mb-3 py-2" id="sr-week-info"></div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">₱</span>
                        <input type="number" class="form-control" id="sr-amount"
                               min="1" max="1000" step="1"
                               placeholder="1 – 1,000">
                    </div>
                    <div class="form-text text-muted">Maximum per request: <strong>₱1,000.00</strong></div>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">
                        Reason / Notes
                        <span class="text-muted small fw-normal">(optional)</span>
                    </label>
                    <textarea class="form-control" id="sr-notes" rows="2"
                              placeholder="Optional reason or notes…"></textarea>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4" id="btn-submit-salary-request">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="sr-spinner"></span>
                    <i class="fas fa-paper-plane me-1" id="sr-icon"></i>Submit Request
                </button>
            </div>

        </div>
    </div>
</div>
