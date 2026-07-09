<?php
$user      = $this->session->userdata('user');
$view_uid  = $view_user['id'];
$is_self   = ((int)$view_uid === (int)$user['id']);
$today_str = date('Y-m-d');
?>

<script>
    var APP_URL       = '<?= base_url() ?>';
    var VIEW_USER_ID  = <?= (int)$view_uid ?>;
    var CAN_EDIT      = <?= $can_edit ? 'true' : 'false' ?>;
    var IS_SELF       = <?= $is_self ? 'true' : 'false' ?>;
    var CAN_REQUEST_SALARY = false; // employees can no longer file advance/borrow — their project head does
    var TIMESHEET_TYPE = '<?= ($view_user['timesheet_type'] ?? 'weekly') === 'semi_monthly' ? 'semi_monthly' : 'weekly' ?>';
    var ATT_MODE      = '<?= ($att_mode ?? 'single') === 'am_pm' ? 'am_pm' : 'single' ?>';
</script>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="fas fa-calendar-check me-2 text-primary"></i>My Timesheet
        </h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">
            <?= htmlspecialchars($view_user['first_name'] . ' ' . $view_user['last_name']) ?>
        </p>
    </div>
</div>

<?php include(APPPATH . 'views/attendance/_timesheet_panel.php'); ?>
