<?php $user = $this->session->userdata('user'); ?>

<!-- Pass PHP values to JS as globals -->
<script>
    var APP_URL   = '<?= base_url() ?>';
    var USER_ROLE = '<?= $user['role_slug'] ?>';
    var ROLES     = <?= json_encode($roles) ?>;
    var JOB_ROLES = <?= json_encode($job_roles) ?>;
</script>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-users me-2 text-primary"></i>User Management</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">Manage system accounts and role assignments</p>
    </div>
    <button class="btn btn-primary px-3" id="btn-add-user">
        <i class="fas fa-plus me-1"></i> Add User
    </button>
</div>

<!-- DataTable Card -->
<div class="card dt-card">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table id="users-table" class="table table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th style="width:52px">#</th>
                        <th>Name / Email</th>
                        <th>Username</th>
                        <th>System Role</th>
                        <th>Job Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="width:120px" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================================================
     Add / Edit User Modal
================================================================ -->
<div class="modal fade" id="user-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modal-title">
                    <i class="fas fa-user-plus me-2 text-primary"></i>Add New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <form id="user-form" autocomplete="off">
                    <input type="hidden" id="edit-user-id" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="f-first-name" placeholder="Juan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="f-last-name" placeholder="Dela Cruz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">@</span>
                                <input type="text" class="form-control" id="f-username" placeholder="juandelacruz">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="f-email" placeholder="juan@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Password <span class="text-danger" id="pass-asterisk">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="f-password" placeholder="Min. 6 characters">
                                <button class="btn btn-outline-secondary" type="button" id="toggle-modal-pass">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text" id="pass-hint" style="display:none;">
                                Leave blank to keep current password.
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">System Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="f-role-id">
                                <option value="">-- Select --</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" data-slug="<?= $role['slug'] ?>">
                                    <?= htmlspecialchars($role['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Job Role</label>
                            <select class="form-select" id="f-job-role-id">
                                <option value="">— None —</option>
                                <?php foreach ($job_roles as $jr): ?>
                                <option value="<?= $jr['id'] ?>">
                                    <?= htmlspecialchars($jr['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="f-status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="btn-save-user">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="save-spinner"></span>
                    <i class="fas fa-save me-1" id="save-icon"></i>
                    <span id="save-label">Save User</span>
                </button>
            </div>

        </div>
    </div>
</div>
