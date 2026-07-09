<?php
$user      = $this->session->userdata('user');
$filter    = $filter ?? 'all';
$pending   = array_filter($requests, fn($r) => $r['status'] === 'pending');
$approved  = array_filter($requests, fn($r) => $r['status'] === 'approved');
$rejected  = array_filter($requests, fn($r) => $r['status'] === 'rejected');

$type_labels  = ['advance' => 'Advance Payment', 'borrow' => 'Borrow Money'];
$status_cfg   = [
    'pending'  => ['bg' => 'bg-warning-subtle text-warning border-warning-subtle',   'icon' => 'fa-clock',       'label' => 'Pending'],
    'approved' => ['bg' => 'bg-success-subtle text-success border-success-subtle',   'icon' => 'fa-check-circle','label' => 'Approved'],
    'rejected' => ['bg' => 'bg-danger-subtle text-danger border-danger-subtle',      'icon' => 'fa-times-circle','label' => 'Declined'],
];
?>
<script>
    var APP_URL = '<?= base_url() ?>';
</script>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-money-bill-wave me-2 text-success"></i>Salary Requests</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">Review and respond to employee advance & borrow requests</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
            <i class="fas fa-clock me-1"></i><?= count($pending) ?> Pending
        </span>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
            <i class="fas fa-check me-1"></i><?= count($approved) ?> Approved
        </span>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
            <i class="fas fa-times me-1"></i><?= count($rejected) ?> Declined
        </span>
    </div>
</div>

<!-- Status Filter Tabs -->
<ul class="nav nav-tabs mb-4" id="srTabs" role="tablist">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Declined'] as $key => $lbl): ?>
    <li class="nav-item" role="presentation">
        <a class="nav-link fw-semibold <?= $filter === $key ? 'active' : '' ?>"
           href="<?= base_url('salaryRequest?status=' . $key) ?>">
            <?= $lbl ?>
            <?php if ($key === 'pending' && count($pending) > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= count($pending) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Requests Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-2x mb-3 d-block"></i>No requests found.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:48px">#</th>
                        <th>Employee</th>
                        <th style="width:150px">Type</th>
                        <th style="width:110px" class="text-end">Amount</th>
                        <th style="width:130px">Week</th>
                        <th style="width:115px">Submitted</th>
                        <th>Notes</th>
                        <th style="width:120px" class="text-center">Status</th>
                        <th style="width:170px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $n = 1; foreach ($requests as $r): ?>
                <?php $cfg = $status_cfg[$r['status']] ?? $status_cfg['pending']; ?>
                <tr data-id="<?= $r['id'] ?>">
                    <td class="text-muted small"><?= $n++ ?></td>
                    <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($r['email']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $r['type'] === 'advance'
                            ? 'bg-warning-subtle text-warning border border-warning-subtle'
                            : 'bg-info-subtle text-info border border-info-subtle' ?> px-2">
                            <i class="fas <?= $r['type'] === 'advance' ? 'fa-hand-holding-usd' : 'fa-hand-holding' ?> me-1"></i>
                            <?= $type_labels[$r['type']] ?>
                        </span>
                    </td>
                    <td class="text-end fw-bold">₱<?= number_format($r['amount'], 2) ?></td>
                    <td class="small text-muted">
                        <i class="fas fa-calendar-week me-1"></i>
                        <?= date('M d', strtotime($r['week_start'])) ?>
                    </td>
                    <td class="small text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                    <td class="small text-muted">
                        <?php if (!empty($r['notes'])): ?>
                        <span><?= htmlspecialchars($r['notes']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($r['admin_notes'])): ?>
                        <div class="text-secondary mt-1">
                            <i class="fas fa-reply me-1"></i><?= htmlspecialchars($r['admin_notes']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $cfg['bg'] ?> border px-2 py-1">
                            <i class="fas <?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
                        </span>
                    </td>
                    <td class="text-center" style="white-space:nowrap;">
                        <?php if ($r['status'] === 'pending'): ?>
                        <button class="btn btn-sm btn-success btn-approve-sr me-1"
                                data-id="<?= $r['id'] ?>"
                                data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                data-type="<?= $type_labels[$r['type']] ?>"
                                data-amount="<?= number_format($r['amount'], 2) ?>">
                            <i class="fas fa-check me-1"></i>Approve
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-reject-sr"
                                data-id="<?= $r['id'] ?>"
                                data-name="<?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>"
                                data-type="<?= $type_labels[$r['type']] ?>"
                                data-amount="<?= number_format($r['amount'], 2) ?>">
                            <i class="fas fa-times me-1"></i>Decline
                        </button>
                        <?php else: ?>
                        <?php if (!empty($r['reviewer_first'])): ?>
                        <span class="text-muted small">
                            by <?= htmlspecialchars($r['reviewer_first'] . ' ' . $r['reviewer_last']) ?>
                            <?php if (!empty($r['reviewed_at'])): ?>
                            <br><?= date('M d', strtotime($r['reviewed_at'])) ?>
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Admin Notes Modal -->
<div class="modal fade" id="admin-notes-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="anm-title">Approve Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border small mb-3" id="anm-info"></div>
                <input type="hidden" id="anm-id">
                <input type="hidden" id="anm-action">
                <div>
                    <label class="form-label fw-semibold">
                        Admin Notes
                        <span class="text-muted small fw-normal">(optional)</span>
                    </label>
                    <textarea class="form-control" id="anm-notes" rows="2"
                              placeholder="Optional remarks to the employee…"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4" id="anm-submit-btn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="anm-spinner"></span>
                    <i class="fas fa-check me-1" id="anm-icon"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>
