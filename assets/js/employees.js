'use strict';

/* ================================================================
   employees.js – Server-Side DataTable + Add Employee
   Globals from employees/index.php: APP_URL, USER_ROLE, ROLES
================================================================ */

let empTable;
const modal    = new bootstrap.Modal(document.getElementById('employee-modal'));
const $modalEl = $('#employee-modal');

// ── Find Employee role ID from ROLES array ───────────────────────
function getEmployeeRoleId() {
    const role = ROLES.find(r => r.slug === 'employee');
    return role ? role.id : '';
}

// ── Helpers ──────────────────────────────────────────────────────

function setLoading(isLoading) {
    const $btn     = $('#btn-save-employee');
    const $spinner = $('#emp-save-spinner');
    const $icon    = $('#emp-save-icon');
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
    $('#employee-form')[0].reset();
    $('#ef-first-name, #ef-last-name, #ef-username, #ef-email, #ef-password, #ef-date-hired').val('');
    $('#ef-job-role-id').val('');
    $('#ef-status').val('active');
    $('#ef-timesheet-type').val('weekly');
    $('#employee-form .form-control, #employee-form .form-select').removeClass('is-invalid is-valid');
    $('#employee-form .invalid-feedback').remove();
}

function showFieldError(fieldId, message) {
    const $field = $('#' + fieldId);
    $field.addClass('is-invalid').removeClass('is-valid');
    $field.siblings('.invalid-feedback').remove();
    $field.after('<div class="invalid-feedback">' + message + '</div>');
}

function clearFieldErrors() {
    $('#employee-form .form-control, #employee-form .form-select').removeClass('is-invalid is-valid');
    $('#employee-form .invalid-feedback').remove();
}

// ── DataTable Init ───────────────────────────────────────────────

$(document).ready(function () {

    empTable = $('#employees-table').DataTable({
        processing : true,
        serverSide : true,
        ajax       : {
            url  : APP_URL + 'employees/datatable',
            type : 'POST',
        },
        columns: [
            { data: 0, orderable: true,  searchable: false, width: '52px' },
            { data: 1, orderable: true,  searchable: true  },
            { data: 2, orderable: true,  searchable: true  },
            { data: 3, orderable: true,  searchable: true  },
            { data: 4, orderable: true,  searchable: false },
            { data: 5, orderable: true,  searchable: false },
            { data: 6, orderable: true,  searchable: false },
            { data: 7, orderable: true,  searchable: false },
            { data: 8, orderable: false, searchable: false, className: 'text-center' },
        ],
        order      : [[0, 'asc']],
        pageLength : 10,
        lengthMenu : [10, 25, 50, 100],
        language   : {
            processing : '<div class="d-flex align-items-center gap-2 text-primary">'
                       + '<span class="spinner-border spinner-border-sm"></span>'
                       + '<span>Loading...</span></div>',
            emptyTable : '<div class="text-center text-muted py-4">'
                       + '<i class="fas fa-users-slash fa-2x mb-2 d-block opacity-25"></i>No employees found.</div>',
            zeroRecords: '<div class="text-center text-muted py-4">'
                       + '<i class="fas fa-search fa-2x mb-2 d-block opacity-25"></i>No matching employees.</div>',
        },
        drawCallback: function () {
            // Initialize avatar initials
            document.querySelectorAll('.avatar-initials').forEach(function (el) {
                el.textContent = el.dataset.initials || '';
            });
        },
    });

    // ── Add Employee Button ──────────────────────────────────────

    $('#btn-add-employee').on('click', function () {
        resetForm();
        $('#ef-role-id').val(getEmployeeRoleId());
        modal.show();
    });

    // ── Toggle password visibility ───────────────────────────────

    $('#toggle-emp-pass').on('click', function () {
        const $p = $('#ef-password');
        const $i = $(this).find('i');
        if ($p.attr('type') === 'password') {
            $p.attr('type', 'text');
            $i.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $p.attr('type', 'password');
            $i.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // ── Save Employee ────────────────────────────────────────────

    $('#btn-save-employee').on('click', function () {
        clearFieldErrors();

        const payload = {
            first_name  : $.trim($('#ef-first-name').val()),
            last_name   : $.trim($('#ef-last-name').val()),
            username    : $.trim($('#ef-username').val()),
            email       : $.trim($('#ef-email').val()),
            password    : $('#ef-password').val(),
            role_id     : $('#ef-role-id').val(),
            job_role_id : $('#ef-job-role-id').val(),
            status      : $('#ef-status').val(),
            date_hired  : $('#ef-date-hired').val(),
            timesheet_type: $('#ef-timesheet-type').val(),
        };

        let hasError = false;
        if (!payload.first_name) { showFieldError('ef-first-name', 'First name is required.'); hasError = true; }
        if (!payload.last_name)  { showFieldError('ef-last-name',  'Last name is required.');  hasError = true; }
        if (!payload.username)   { showFieldError('ef-username',   'Username is required.');   hasError = true; }
        if (!payload.email)      { showFieldError('ef-email',      'Email is required.');      hasError = true; }
        if (!payload.password)   { showFieldError('ef-password',   'Password is required.');   hasError = true; }
        else if (payload.password.length < 6) {
            showFieldError('ef-password', 'Password must be at least 6 characters.');
            hasError = true;
        }
        if (hasError) return;

        setLoading(true);

        $.post(APP_URL + 'users/create', payload)
            .done(function (res) {
                setLoading(false);
                if (res.success) {
                    modal.hide();
                    empTable.ajax.reload(null, false);
                    Swal.fire({
                        icon             : 'success',
                        title            : 'Employee Added!',
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

    // ── Clear form on modal close ────────────────────────────────
    $modalEl.on('hidden.bs.modal', function () {
        resetForm();
        setLoading(false);
    });
});
