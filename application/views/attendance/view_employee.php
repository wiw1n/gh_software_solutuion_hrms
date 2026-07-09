<?php
$auth_user = $this->session->userdata('user');
$view_uid  = $view_user['id'];
?>

<script>
    var APP_URL      = '<?= base_url() ?>';
    var VIEW_USER_ID = <?= (int)$view_uid ?>;
    var CAN_EDIT     = true;
    var IS_SELF      = false;
    var CAN_REQUEST_SALARY = <?= !empty($can_request_salary) ? 'true' : 'false' ?>;
    var TIMESHEET_TYPE = '<?= ($view_user['timesheet_type'] ?? 'weekly') === 'semi_monthly' ? 'semi_monthly' : 'weekly' ?>';
    var ATT_MODE     = '<?= ($att_mode ?? 'single') === 'am_pm' ? 'am_pm' : 'single' ?>';
</script>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= base_url('attendance') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <div>
            <h4 class="mb-1">
                <i class="fas fa-calendar-alt me-2 text-primary"></i>
                <?= htmlspecialchars($view_user['first_name'] . ' ' . $view_user['last_name']) ?> — Timesheet
            </h4>
            <p class="text-muted mb-0" style="font-size:.87rem;">
                <?php if (!empty($view_user['employee_id'])): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-2">
                    <i class="fas fa-id-badge me-1"></i><?= htmlspecialchars($view_user['employee_id']) ?>
                </span>
                <?php endif; ?>
                <span class="badge bg-<?= $view_user['role_slug'] === 'admin' ? 'primary' : 'secondary' ?>-subtle
                      text-<?= $view_user['role_slug'] === 'admin' ? 'primary' : 'secondary' ?>
                      border border-<?= $view_user['role_slug'] === 'admin' ? 'primary' : 'secondary' ?>-subtle me-2">
                    <?= htmlspecialchars($view_user['role_name']) ?>
                </span>
                <span class="badge bg-info-subtle text-info border border-info-subtle me-2">
                    <i class="fas fa-calendar-week me-1"></i>
                    <?= ($view_user['timesheet_type'] ?? 'weekly') === 'semi_monthly' ? '15/30 Timesheet' : 'Weekly Timesheet' ?>
                </span>
                <?= htmlspecialchars($view_user['email']) ?>
            </p>
        </div>
    </div>
</div>

<?php include(APPPATH . 'views/attendance/_timesheet_panel.php'); ?>
