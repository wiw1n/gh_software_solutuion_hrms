<?php $user = $this->session->userdata('user'); ?>

<script>
    var APP_URL   = '<?= base_url() ?>';
    var USER_ROLE = '<?= $user['role_slug'] ?>';
    var ROLES     = <?= json_encode($roles) ?>;
    var JOB_ROLES = <?= json_encode($job_roles) ?>;
</script>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-users-tie me-2 text-primary"></i>Employees</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">
            <?= !empty($can_admin) ? 'Manage employee profiles and payroll info' : 'Employees assigned to the projects you manage' ?>
        </p>
    </div>
    <?php if (!empty($can_admin)): ?>
    <button class="btn btn-primary px-3" id="btn-add-employee">
        <i class="fas fa-user-plus me-1"></i> Add Employee
    </button>
    <?php endif; ?>
</div>

<!-- DataTable Card -->
<div class="card dt-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-end mb-3">
            <div class="d-flex align-items-center gap-2">
                <label for="filter-project" class="form-label fw-semibold mb-0 text-nowrap" style="font-size:.87rem;">
                    <i class="fas fa-filter me-1 text-muted"></i>Project
                </label>
                <select class="form-select form-select-sm" id="filter-project" style="min-width:220px;">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table id="employees-table" class="table table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th style="width:52px">#</th>
                        <th>Employee ID</th>
                        <th>Name / Email</th>
                        <th>Username</th>
                        <th>Project</th>
                        <th>Job Role</th>
                        <th>Status</th>
                        <th>Daily Rate</th>
                        <th>Joined</th>
                        <th style="width:150px" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================================================
     Add Employee Modal
================================================================ -->
<div class="modal fade" id="employee-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-tie me-2 text-primary"></i>Add New Employee
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form id="employee-form" autocomplete="off">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ef-first-name" placeholder="Juan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ef-last-name" placeholder="Dela Cruz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">@</span>
                                <input type="text" class="form-control" id="ef-username" placeholder="juandelacruz">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="ef-email" placeholder="juan@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="ef-password" placeholder="Min. 6 characters">
                                <button class="btn btn-outline-secondary" type="button" id="toggle-emp-pass">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Job Role</label>
                            <select class="form-select" id="ef-job-role-id">
                                <option value="">— Select Job Role —</option>
                                <?php foreach ($job_roles as $jr): ?>
                                <option value="<?= $jr['id'] ?>"><?= htmlspecialchars($jr['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="ef-status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Date Hired</label>
                            <input type="date" class="form-control" id="ef-date-hired">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Timesheet Type</label>
                            <select class="form-select" id="ef-timesheet-type">
                                <option value="weekly">Weekly</option>
                                <option value="semi_monthly">15 / 30 (Semi-Monthly)</option>
                            </select>
                        </div>
                        <!-- Role is fixed to Employee — hidden -->
                        <input type="hidden" id="ef-role-id" value="">
                    </div>
                </form>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-employee">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="emp-save-spinner"></span>
                    <i class="fas fa-save me-1" id="emp-save-icon"></i>
                    <span id="emp-save-label">Add Employee</span>
                </button>
            </div>

        </div>
    </div>
</div>
