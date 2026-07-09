<?php
$user     = $this->session->userdata('user');
$view_uid = $view_user['id']; // admin's own ID for My Timesheet tab
?>

<script>
    var APP_URL      = '<?= base_url() ?>';
    var VIEW_USER_ID = <?= (int)$view_uid ?>;
    var CAN_EDIT     = <?= !empty($can_edit) ? 'true' : 'false' ?>;
    var IS_SELF      = true;
    var CAN_REQUEST_SALARY = false; // requests are filed from the employee's timesheet page, not one's own
    var TIMESHEET_TYPE = '<?= ($view_user['timesheet_type'] ?? 'weekly') === 'semi_monthly' ? 'semi_monthly' : 'weekly' ?>';
    var ATT_MODE     = '<?= ($att_mode ?? 'single') === 'am_pm' ? 'am_pm' : 'single' ?>';
</script>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-clock me-2 text-primary"></i>Attendance</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">Employee attendance tracking and management</p>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="attendanceTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="tab-employees-btn"
                data-bs-toggle="tab" data-bs-target="#tab-employees" type="button" role="tab">
            <i class="fas fa-users me-2"></i>Employees
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-my-ts-btn"
                data-bs-toggle="tab" data-bs-target="#tab-my-timesheet" type="button" role="tab">
            <i class="fas fa-calendar-check me-2"></i>My Timesheet
        </button>
    </li>
</ul>

<div class="tab-content" id="attendanceTabContent">

    <!-- ── Tab 1: Employees ── -->
    <div class="tab-pane fade show active" id="tab-employees" role="tabpanel">

        <div class="card dt-card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="employees-table" class="table table-hover align-middle" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th style="width:52px">#</th>
                                <th>Name / Email</th>
                                <th style="width:110px">Role</th>
                                <th style="width:125px">Today Status</th>
                                <th style="width:170px">Time In / Out</th>
                                <th style="width:120px" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Tab 2: My Timesheet ── -->
    <div class="tab-pane fade" id="tab-my-timesheet" role="tabpanel">

        <div class="mb-3">
            <h6 class="fw-bold text-muted">
                <?= htmlspecialchars($view_user['first_name'] . ' ' . $view_user['last_name']) ?>
            </h6>
        </div>

        <?php include(APPPATH . 'views/attendance/_timesheet_panel.php'); ?>

    </div>

</div>
