'use strict';

/* ================================================================
   users.js – Server-Side DataTable + CRUD for User Management
   Requires: jQuery, DataTables, Bootstrap 5, SweetAlert2
   Globals from users/index.php view: APP_URL, USER_ROLE, ROLES
================================================================ */

let usersTable;
const modal     = new bootstrap.Modal(document.getElementById('user-modal'));
const $modalEl  = $('#user-modal');

// ── Helpers ──────────────────────────────────────────────────────

function setLoading(isLoading) {
    const $btn     = $('#btn-save-user');
    const $spinner = $('#save-spinner');
    const $icon    = $('#save-icon');

    if (isLoading) {
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');
    } else {
        $btn.prop('disabled', false);
        $spinner.addClass('d-none');
        $icon.removeClass('d-none');
    }
}

function resetForm() {
    $('#user-form')[0].reset();
    $('#edit-user-id').val('');
    $('#f-first-name, #f-last-name, #f-username, #f-email, #f-password').val('');
    $('#f-role-id').val('');
    $('#f-job-role-id').val('');
    $('#f-status').val('active');

    // Restore password field to required mode (Add)
    $('#pass-asterisk').show();
    $('#pass-hint').hide();

    // Clear any Bootstrap validation states
    $('#user-form .form-control, #user-form .form-select').removeClass('is-invalid is-valid');
    $('#user-form .invalid-feedback').remove();
}

function showFieldError(fieldId, message) {
    const $field = $('#' + fieldId);
    $field.addClass('is-invalid').removeClass('is-valid');
    $field.siblings('.invalid-feedback').remove();
    $field.after('<div class="invalid-feedback">' + message + '</div>');
}

function clearFieldErrors() {
    $('#user-form .form-control, #user-form .form-select').removeClass('is-invalid is-valid');
    $('#user-form .invalid-feedback').remove();
}

// ── DataTable Init ───────────────────────────────────────────────

$(document).ready(function () {

    usersTable = $('#users-table').DataTable({
        processing   : true,
        serverSide   : true,
        ajax         : {
            url  : APP_URL + 'users/datatable',
            type : 'POST',
        },
        columns: [
            { data: 0, orderable: true,  searchable: false, width: '52px' },
            { data: 1, orderable: true,  searchable: true  },
            { data: 2, orderable: true,  searchable: true  },
            { data: 3, orderable: true,  searchable: false },
            { data: 4, orderable: true,  searchable: false },
            { data: 5, orderable: true,  searchable: false },
            { data: 6, orderable: true,  searchable: false },
            { data: 7, orderable: false, searchable: false, className: 'text-center' },
        ],
        order       : [[0, 'asc']],
        pageLength  : 10,
        lengthMenu  : [10, 25, 50, 100],
        language    : {
            processing: '<div class="d-flex align-items-center gap-2 text-primary">'
                      + '<span class="spinner-border spinner-border-sm"></span>'
                      + '<span>Loading...</span></div>',
            emptyTable: '<div class="text-center text-muted py-3">'
                      + '<i class="fas fa-inbox fa-2x mb-2 d-block"></i>No users found.</div>',
            zeroRecords: '<div class="text-center text-muted py-3">'
                       + '<i class="fas fa-search fa-2x mb-2 d-block"></i>No matching records.</div>',
        },
        drawCallback: function () {
            $('[data-bs-toggle="tooltip"]').tooltip();
        },
    });

    // ── Add User Button ──────────────────────────────────────────

    $('#btn-add-user').on('click', function () {
        resetForm();
        $('#modal-title').html('<i class="fas fa-user-plus me-2 text-primary"></i>Add New User');
        $('#save-label').text('Save User');
        modal.show();
    });

    // ── Toggle password visibility (modal) ──────────────────────

    $('#toggle-modal-pass').on('click', function () {
        const $p = $('#f-password');
        const $i = $(this).find('i');
        if ($p.attr('type') === 'password') {
            $p.attr('type', 'text');
            $i.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $p.attr('type', 'password');
            $i.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // ── Edit Button (delegated) ──────────────────────────────────

    $('#users-table').on('click', '.btn-edit', function () {
        const id = $(this).data('id');

        $.get(APP_URL + 'users/get/' + id)
            .done(function (res) {
                if (!res.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                    return;
                }

                const u = res.data;
                resetForm();

                $('#edit-user-id').val(u.id);
                $('#f-first-name').val(u.first_name);
                $('#f-last-name').val(u.last_name);
                $('#f-username').val(u.username);
                $('#f-email').val(u.email);
                $('#f-role-id').val(u.role_id);
                $('#f-job-role-id').val(u.job_role_id || '');
                $('#f-status').val(u.status);

                // Password optional on edit
                $('#pass-asterisk').hide();
                $('#pass-hint').show();

                $('#modal-title').html('<i class="fas fa-user-edit me-2 text-primary"></i>Edit User');
                $('#save-label').text('Update User');
                modal.show();
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to fetch user data.', confirmButtonColor: '#4361ee' });
            });
    });

    // ── Save User (Create / Update) ──────────────────────────────

    $('#btn-save-user').on('click', function () {
        clearFieldErrors();

        const userId    = $('#edit-user-id').val();
        const isEditing = userId !== '';

        const payload = {
            first_name  : $.trim($('#f-first-name').val()),
            last_name   : $.trim($('#f-last-name').val()),
            username    : $.trim($('#f-username').val()),
            email       : $.trim($('#f-email').val()),
            password    : $('#f-password').val(),
            role_id     : $('#f-role-id').val(),
            job_role_id : $('#f-job-role-id').val(),
            status      : $('#f-status').val(),
        };

        // Client-side quick validation
        let hasError = false;

        if (!payload.first_name) { showFieldError('f-first-name', 'First name is required.'); hasError = true; }
        if (!payload.last_name)  { showFieldError('f-last-name',  'Last name is required.');  hasError = true; }
        if (!payload.username)   { showFieldError('f-username',   'Username is required.');   hasError = true; }
        if (!payload.email)      { showFieldError('f-email',      'Email is required.');      hasError = true; }
        if (!payload.role_id)    { showFieldError('f-role-id',    'Please select a role.');   hasError = true; }

        if (!isEditing && !payload.password) {
            showFieldError('f-password', 'Password is required.');
            hasError = true;
        } else if (payload.password && payload.password.length < 6) {
            showFieldError('f-password', 'Password must be at least 6 characters.');
            hasError = true;
        }

        if (hasError) return;

        const url = isEditing
            ? APP_URL + 'users/update/' + userId
            : APP_URL + 'users/create';

        setLoading(true);

        $.post(url, payload)
            .done(function (res) {
                setLoading(false);
                if (res.success) {
                    modal.hide();
                    usersTable.ajax.reload(null, false);
                    Swal.fire({
                        icon             : 'success',
                        title            : 'Success!',
                        text             : res.message,
                        confirmButtonColor: '#4361ee',
                        timer            : 2200,
                        timerProgressBar : true,
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
                }
            })
            .fail(function (xhr) {
                setLoading(false);
                const res = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
            });
    });

    // ── Delete Button (delegated) ────────────────────────────────

    $('#users-table').on('click', '.btn-delete', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');

        Swal.fire({
            title             : 'Delete User?',
            html              : 'You are about to delete <strong>' + name + '</strong>.<br>This action cannot be undone.',
            icon              : 'warning',
            showCancelButton  : true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor : '#6c757d',
            confirmButtonText : 'Yes, Delete',
            cancelButtonText  : 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(APP_URL + 'users/delete/' + id)
                .done(function (res) {
                    if (res.success) {
                        usersTable.ajax.reload(null, false);
                        Swal.fire({
                            icon             : 'success',
                            title            : 'Deleted!',
                            text             : res.message,
                            confirmButtonColor: '#4361ee',
                            timer            : 2000,
                            timerProgressBar : true,
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                    }
                })
                .fail(function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete user.', confirmButtonColor: '#4361ee' });
                });
        });
    });

    // ── Toggle Status Button (delegated) ────────────────────────

    $('#users-table').on('click', '.btn-toggle', function () {
        const id = $(this).data('id');

        $.post(APP_URL + 'users/toggle/' + id)
            .done(function (res) {
                if (res.success) {
                    usersTable.ajax.reload(null, false);
                    const icon = res.status === 'active' ? '✅' : '⛔';
                    Swal.fire({
                        icon             : 'success',
                        title            : icon + ' Status Updated',
                        text             : res.message,
                        confirmButtonColor: '#4361ee',
                        timer            : 1800,
                        timerProgressBar : true,
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                }
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update status.', confirmButtonColor: '#4361ee' });
            });
    });

    // ── Clear form when modal is fully hidden ────────────────────
    $modalEl.on('hidden.bs.modal', function () {
        resetForm();
        setLoading(false);
    });

});
