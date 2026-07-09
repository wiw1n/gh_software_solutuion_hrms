<script>
    var APP_URL = '<?= base_url() ?>';
</script>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-cogs me-2 text-primary"></i>System Configuration</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">Manage projects, job roles and system-wide settings</p>
    </div>
</div>

<?php if (!empty($is_super)): ?>
<!-- Attendance Clock Settings Card (super admin only) -->
<div class="card mb-4" id="att-settings-card">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3 px-4">
        <div>
            <h6 class="mb-0 fw-bold"><i class="fas fa-user-clock me-2 text-primary"></i>Attendance Clock Settings</h6>
            <small class="text-muted">Choose how employees clock in/out and the schedule used for tardiness and overtime</small>
        </div>
        <button class="btn btn-primary btn-sm px-3" id="btn-save-att">
            <span class="spinner-border spinner-border-sm me-1 d-none" id="att-spinner"></span>
            <i class="fas fa-save me-1" id="att-save-icon"></i> Save Settings
        </button>
    </div>
    <div class="card-body p-4">

        <label class="form-label fw-semibold mb-2">Attendance Mode</label>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="border rounded p-3 d-flex gap-3 h-100" style="cursor:pointer;">
                    <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="att-mode" id="att-mode-am-pm" value="am_pm">
                    <span>
                        <span class="fw-semibold d-block">AM / PM Sessions <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">Default</span></span>
                        <span class="text-muted small">Employees punch four times a day: AM time in/out and PM time in/out. Hours are the sum of both sessions.</span>
                    </span>
                </label>
            </div>
            <div class="col-md-6">
                <label class="border rounded p-3 d-flex gap-3 h-100" style="cursor:pointer;">
                    <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="att-mode" id="att-mode-single" value="single">
                    <span>
                        <span class="fw-semibold d-block">Single Time In / Out</span>
                        <span class="text-muted small">Employees punch twice a day: one time in and one time out covering the whole shift.</span>
                    </span>
                </label>
            </div>
        </div>

        <!-- AM/PM schedule -->
        <div id="att-sched-am-pm">
            <label class="form-label fw-semibold mb-2">AM / PM Schedule</label>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">AM Time In</label>
                    <input type="time" class="form-control" id="att-am-in">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">AM Time Out</label>
                    <input type="time" class="form-control" id="att-am-out">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">PM Time In</label>
                    <input type="time" class="form-control" id="att-pm-in">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">PM Time Out</label>
                    <input type="time" class="form-control" id="att-pm-out">
                </div>
            </div>
            <div class="form-text text-muted mt-2">
                Arriving after AM/PM Time In counts as tardiness; leaving after PM Time Out counts as overtime.
            </div>
        </div>

        <!-- Single-shift schedule -->
        <div id="att-sched-single" style="display:none;">
            <label class="form-label fw-semibold mb-2">Shift Schedule</label>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Time In</label>
                    <input type="time" class="form-control" id="att-day-in">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Time Out</label>
                    <input type="time" class="form-control" id="att-day-out">
                </div>
            </div>
            <div class="form-text text-muted mt-2">
                Arriving after Time In counts as tardiness; leaving after Time Out counts as overtime.
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<!-- Projects Card -->
<div class="card dt-card mb-4">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3 px-4">
        <div>
            <h6 class="mb-0 fw-bold"><i class="fas fa-building me-2 text-info"></i>Projects</h6>
            <small class="text-muted">Construction projects employees can be assigned to</small>
        </div>
        <button class="btn btn-primary btn-sm px-3" id="btn-add-pj">
            <i class="fas fa-plus me-1"></i> Add Project
        </button>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table id="pj-table" class="table table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th style="width:52px">#</th>
                        <th>Project Name</th>
                        <th>Location</th>
                        <th style="width:100px">Assigned</th>
                        <th style="width:100px">Heads</th>
                        <th style="width:110px">Status</th>
                        <th style="width:220px">Duration</th>
                        <th style="width:150px" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Job Roles Card -->
<div class="card dt-card">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3 px-4">
        <div>
            <h6 class="mb-0 fw-bold"><i class="fas fa-hard-hat me-2 text-warning"></i>Job Roles</h6>
            <small class="text-muted">Construction trade roles for employees</small>
        </div>
        <button class="btn btn-primary btn-sm px-3" id="btn-add-jr">
            <i class="fas fa-plus me-1"></i> Add Role
        </button>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table id="jr-table" class="table table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th style="width:52px">#</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th style="width:100px">Status</th>
                        <th style="width:120px">Created</th>
                        <th style="width:110px" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================================================
     Add / Edit Project Modal
================================================================ -->
<div class="modal fade" id="pj-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="pj-modal-title">
                    <i class="fas fa-building me-2 text-info"></i>Add Project
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form id="pj-form" autocomplete="off">
                    <input type="hidden" id="pj-edit-id" value="">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pj-name" placeholder="e.g. Riverside Bridge Construction">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" class="form-control" id="pj-location" placeholder="e.g. Palo, Leyte">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" class="form-control" id="pj-description" placeholder="Optional description">
                    </div>
                    <div class="row g-3 mb-1">
                        <div class="col-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="pj-status">
                                <option value="active">Active</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" class="form-control" id="pj-start-date">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" class="form-control" id="pj-end-date">
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-pj">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="pj-spinner"></span>
                    <i class="fas fa-save me-1" id="pj-save-icon"></i>
                    <span id="pj-save-label">Save Project</span>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ================================================================
     Manage Project Heads Modal
================================================================ -->
<div class="modal fade" id="ph-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-user-shield me-2 text-primary"></i>Project Heads
                    <span class="text-muted fw-normal" id="ph-project-name" style="font-size:.85rem;"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Heads can view and manage the employees assigned to this project.
                    A project can have multiple heads.
                </p>

                <!-- Add head -->
                <label class="form-label fw-semibold" style="font-size:.85rem;">Assign a Head</label>
                <div class="input-group input-group-sm mb-1">
                    <select class="form-select" id="ph-candidate-id">
                        <option value="">— Select user —</option>
                    </select>
                    <button class="btn btn-primary px-3" id="btn-add-ph">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="ph-spinner"></span>
                        <i class="fas fa-plus me-1" id="ph-add-icon"></i>Assign
                    </button>
                </div>
                <div class="form-text mb-3" style="font-size:.72rem;">
                    Only active users with the <strong>Project Head</strong> role are listed.
                    Assign that role in <a href="<?= base_url('users') ?>">User Management</a> first.
                </div>

                <hr class="opacity-25">

                <!-- Current heads -->
                <label class="form-label fw-semibold" style="font-size:.85rem;">Current Heads</label>
                <div id="ph-list">
                    <div class="text-center text-muted py-3 small">Loading…</div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<!-- ================================================================
     Add / Edit Job Role Modal
================================================================ -->
<div class="modal fade" id="jr-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="jr-modal-title">
                    <i class="fas fa-hard-hat me-2 text-warning"></i>Add Job Role
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form id="jr-form" autocomplete="off">
                    <input type="hidden" id="jr-edit-id" value="">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="jr-name" placeholder="e.g. Mason">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" class="form-control" id="jr-description" placeholder="Optional description">
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="jr-is-active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-jr">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="jr-spinner"></span>
                    <i class="fas fa-save me-1" id="jr-save-icon"></i>
                    <span id="jr-save-label">Save Role</span>
                </button>
            </div>

        </div>
    </div>
</div>
