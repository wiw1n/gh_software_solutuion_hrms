'use strict';

/* ================================================================
   system_config.js – Projects CRUD + Job Roles CRUD + DataTables
                    + Attendance Clock Settings (super admin)
   Globals from system_config/index.php view: APP_URL
================================================================ */

let jrTable;
const modal    = new bootstrap.Modal(document.getElementById('jr-modal'));
const $modalEl = $('#jr-modal');

// ── Helpers ──────────────────────────────────────────────────────

function setLoading(isLoading) {
    const $btn = $('#btn-save-jr');
    if (isLoading) {
        $btn.prop('disabled', true);
        $('#jr-spinner').removeClass('d-none');
        $('#jr-save-icon').addClass('d-none');
    } else {
        $btn.prop('disabled', false);
        $('#jr-spinner').addClass('d-none');
        $('#jr-save-icon').removeClass('d-none');
    }
}

function resetForm() {
    $('#jr-form')[0].reset();
    $('#jr-edit-id').val('');
    $('#jr-name, #jr-description').val('');
    $('#jr-is-active').val('1');
    $('#jr-form .form-control, #jr-form .form-select').removeClass('is-invalid');
    $('#jr-form .invalid-feedback').remove();
}

function showFieldError(fieldId, message) {
    const $field = $('#' + fieldId);
    $field.addClass('is-invalid');
    $field.siblings('.invalid-feedback').remove();
    $field.after('<div class="invalid-feedback">' + message + '</div>');
}

// ── DataTable Init ───────────────────────────────────────────────

$(document).ready(function () {

    jrTable = $('#jr-table').DataTable({
        processing : true,
        serverSide : true,
        ajax       : {
            url  : APP_URL + 'system_config/job_roles_datatable',
            type : 'POST',
        },
        columns: [
            { data: 0, orderable: true,  searchable: false, width: '52px' },
            { data: 1, orderable: true,  searchable: true  },
            { data: 2, orderable: false, searchable: true  },
            { data: 3, orderable: true,  searchable: false, width: '100px' },
            { data: 4, orderable: true,  searchable: false, width: '120px' },
            { data: 5, orderable: false, searchable: false, className: 'text-center', width: '110px' },
        ],
        order      : [[1, 'asc']],
        pageLength : 25,
        lengthMenu : [10, 25, 50, 100],
        language   : {
            processing : '<div class="d-flex align-items-center gap-2 text-primary">'
                       + '<span class="spinner-border spinner-border-sm"></span>'
                       + '<span>Loading...</span></div>',
            emptyTable : '<div class="text-center text-muted py-4">'
                       + '<i class="fas fa-hard-hat fa-2x mb-2 d-block opacity-25"></i>No job roles found.</div>',
            zeroRecords: '<div class="text-center text-muted py-4">'
                       + '<i class="fas fa-search fa-2x mb-2 d-block opacity-25"></i>No matching job roles.</div>',
        },
    });

    // ── Add Button ───────────────────────────────────────────────

    $('#btn-add-jr').on('click', function () {
        resetForm();
        $('#jr-modal-title').html('<i class="fas fa-hard-hat me-2 text-warning"></i>Add Job Role');
        $('#jr-save-label').text('Save Role');
        modal.show();
    });

    // ── Edit Button (delegated) ──────────────────────────────────

    $('#jr-table').on('click', '.btn-edit-jr', function () {
        const id = $(this).data('id');

        $.get(APP_URL + 'system_config/get_job_role/' + id)
            .done(function (res) {
                if (!res.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                    return;
                }

                const jr = res.data;
                resetForm();
                $('#jr-edit-id').val(jr.id);
                $('#jr-name').val(jr.name);
                $('#jr-description').val(jr.description || '');
                $('#jr-is-active').val(jr.is_active ? '1' : '0');

                $('#jr-modal-title').html('<i class="fas fa-hard-hat me-2 text-warning"></i>Edit Job Role');
                $('#jr-save-label').text('Update Role');
                modal.show();
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to fetch job role data.', confirmButtonColor: '#4361ee' });
            });
    });

    // ── Save (Create / Update) ───────────────────────────────────

    $('#btn-save-jr').on('click', function () {
        $('#jr-form .form-control, #jr-form .form-select').removeClass('is-invalid');
        $('#jr-form .invalid-feedback').remove();

        const editId    = $('#jr-edit-id').val();
        const isEditing = editId !== '';
        const name      = $.trim($('#jr-name').val());

        if (!name) {
            showFieldError('jr-name', 'Role name is required.');
            return;
        }

        const payload = {
            name        : name,
            description : $.trim($('#jr-description').val()),
            is_active   : $('#jr-is-active').val(),
        };

        const url = isEditing
            ? APP_URL + 'system_config/update_job_role/' + editId
            : APP_URL + 'system_config/create_job_role';

        setLoading(true);

        $.post(url, payload)
            .done(function (res) {
                setLoading(false);
                if (res.success) {
                    modal.hide();
                    jrTable.ajax.reload(null, false);
                    Swal.fire({
                        icon             : 'success',
                        title            : 'Success!',
                        text             : res.message,
                        confirmButtonColor: '#4361ee',
                        timer            : 2000,
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

    $('#jr-table').on('click', '.btn-delete-jr', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');

        Swal.fire({
            title             : 'Delete Job Role?',
            html              : 'You are about to delete <strong>' + name + '</strong>.<br>This cannot be undone.',
            icon              : 'warning',
            showCancelButton  : true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor : '#6c757d',
            confirmButtonText : 'Yes, Delete',
            cancelButtonText  : 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(APP_URL + 'system_config/delete_job_role/' + id)
                .done(function (res) {
                    if (res.success) {
                        jrTable.ajax.reload(null, false);
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
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete job role.', confirmButtonColor: '#4361ee' });
                });
        });
    });

    // ── Reset form on modal close ────────────────────────────────
    $modalEl.on('hidden.bs.modal', function () {
        resetForm();
        setLoading(false);
    });

    // ── System Settings (super admin card) ───────────────────────
    initSystemSettings();

    // ── Attendance Clock Settings (super admin card) ─────────────
    initAttendanceSettings();

    // ── Projects CRUD ────────────────────────────────────────────
    initProjects();
});

/* ================================================================
   Projects CRUD + DataTable
================================================================ */

function initProjects() {
    const pjModal    = new bootstrap.Modal(document.getElementById('pj-modal'));
    const $pjModalEl = $('#pj-modal');

    function setPjLoading(isLoading) {
        $('#btn-save-pj').prop('disabled', isLoading);
        $('#pj-spinner').toggleClass('d-none', !isLoading);
        $('#pj-save-icon').toggleClass('d-none', isLoading);
    }

    function resetPjForm() {
        $('#pj-form')[0].reset();
        $('#pj-edit-id').val('');
        $('#pj-name, #pj-location, #pj-description, #pj-start-date, #pj-end-date').val('');
        $('#pj-status').val('active');
        $('#pj-form .form-control, #pj-form .form-select').removeClass('is-invalid');
        $('#pj-form .invalid-feedback').remove();
    }

    const pjTable = $('#pj-table').DataTable({
        processing : true,
        serverSide : true,
        ajax       : {
            url  : APP_URL + 'system_config/projects_datatable',
            type : 'POST',
        },
        columns: [
            { data: 0, orderable: true,  searchable: false, width: '52px' },
            { data: 1, orderable: true,  searchable: true  },
            { data: 2, orderable: true,  searchable: true  },
            { data: 3, orderable: true,  searchable: false, width: '100px' },
            { data: 4, orderable: true,  searchable: false, width: '100px' },
            { data: 5, orderable: true,  searchable: false, width: '110px' },
            { data: 6, orderable: true,  searchable: false, width: '220px' },
            { data: 7, orderable: false, searchable: false, className: 'text-center', width: '150px' },
        ],
        order      : [[1, 'asc']],
        pageLength : 25,
        lengthMenu : [10, 25, 50, 100],
        language   : {
            processing : '<div class="d-flex align-items-center gap-2 text-primary">'
                       + '<span class="spinner-border spinner-border-sm"></span>'
                       + '<span>Loading...</span></div>',
            emptyTable : '<div class="text-center text-muted py-4">'
                       + '<i class="fas fa-building fa-2x mb-2 d-block opacity-25"></i>No projects found.</div>',
            zeroRecords: '<div class="text-center text-muted py-4">'
                       + '<i class="fas fa-search fa-2x mb-2 d-block opacity-25"></i>No matching projects.</div>',
        },
    });

    // ── Add Button ───────────────────────────────────────────────

    $('#btn-add-pj').on('click', function () {
        resetPjForm();
        $('#pj-modal-title').html('<i class="fas fa-building me-2 text-info"></i>Add Project');
        $('#pj-save-label').text('Save Project');
        pjModal.show();
    });

    // ── Edit Button (delegated) ──────────────────────────────────

    $('#pj-table').on('click', '.btn-edit-pj', function () {
        const id = $(this).data('id');

        $.get(APP_URL + 'system_config/get_project/' + id)
            .done(function (res) {
                if (!res.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                    return;
                }

                const p = res.data;
                resetPjForm();
                $('#pj-edit-id').val(p.id);
                $('#pj-name').val(p.name);
                $('#pj-location').val(p.location || '');
                $('#pj-description').val(p.description || '');
                $('#pj-status').val(p.status);
                $('#pj-start-date').val(p.start_date || '');
                $('#pj-end-date').val(p.end_date || '');

                $('#pj-modal-title').html('<i class="fas fa-building me-2 text-info"></i>Edit Project');
                $('#pj-save-label').text('Update Project');
                pjModal.show();
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to fetch project data.', confirmButtonColor: '#4361ee' });
            });
    });

    // ── Save (Create / Update) ───────────────────────────────────

    $('#btn-save-pj').on('click', function () {
        $('#pj-form .form-control, #pj-form .form-select').removeClass('is-invalid');
        $('#pj-form .invalid-feedback').remove();

        const editId    = $('#pj-edit-id').val();
        const isEditing = editId !== '';
        const name      = $.trim($('#pj-name').val());

        if (!name) {
            $('#pj-name').addClass('is-invalid')
                .after('<div class="invalid-feedback">Project name is required.</div>');
            return;
        }

        const payload = {
            name        : name,
            location    : $.trim($('#pj-location').val()),
            description : $.trim($('#pj-description').val()),
            status      : $('#pj-status').val(),
            start_date  : $('#pj-start-date').val(),
            end_date    : $('#pj-end-date').val(),
        };

        const url = isEditing
            ? APP_URL + 'system_config/update_project/' + editId
            : APP_URL + 'system_config/create_project';

        setPjLoading(true);

        $.post(url, payload)
            .done(function (res) {
                setPjLoading(false);
                if (res.success) {
                    pjModal.hide();
                    pjTable.ajax.reload(null, false);
                    Swal.fire({
                        icon             : 'success',
                        title            : 'Success!',
                        text             : res.message,
                        confirmButtonColor: '#4361ee',
                        timer            : 2000,
                        timerProgressBar : true,
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
                }
            })
            .fail(function (xhr) {
                setPjLoading(false);
                const res = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
            });
    });

    // ── Delete Button (delegated) ────────────────────────────────

    $('#pj-table').on('click', '.btn-delete-pj', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');

        Swal.fire({
            title             : 'Delete Project?',
            html              : 'You are about to delete <strong>' + name + '</strong>.<br>This cannot be undone.',
            icon              : 'warning',
            showCancelButton  : true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor : '#6c757d',
            confirmButtonText : 'Yes, Delete',
            cancelButtonText  : 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(APP_URL + 'system_config/delete_project/' + id)
                .done(function (res) {
                    if (res.success) {
                        pjTable.ajax.reload(null, false);
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
                .fail(function (xhr) {
                    const res = xhr.responseJSON;
                    Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'Failed to delete project.', confirmButtonColor: '#4361ee' });
                });
        });
    });

    // ── Reset form on modal close ────────────────────────────────
    $pjModalEl.on('hidden.bs.modal', function () {
        resetPjForm();
        setPjLoading(false);
    });

    // ═════════════════════════════════════════════════════════════
    // Project Heads modal
    // ═════════════════════════════════════════════════════════════

    const phModal = new bootstrap.Modal(document.getElementById('ph-modal'));
    let phProjectId = null;

    function setPhLoading(isLoading) {
        $('#btn-add-ph').prop('disabled', isLoading);
        $('#ph-spinner').toggleClass('d-none', !isLoading);
        $('#ph-add-icon').toggleClass('d-none', isLoading);
    }

    function renderHeads(data) {
        // Candidate dropdown
        const $sel = $('#ph-candidate-id').empty()
            .append('<option value="">— Select user —</option>');
        data.candidates.forEach(function (c) {
            const label = c.first_name + ' ' + c.last_name + (c.employee_id ? ' (' + c.employee_id + ')' : '');
            $sel.append($('<option>').val(c.id).text(label));
        });
        $('#btn-add-ph').prop('disabled', data.candidates.length === 0);

        // Current heads list
        const $list = $('#ph-list').empty();
        if (data.heads.length === 0) {
            $list.html('<div class="text-center text-muted py-3 small">'
                + '<i class="fas fa-user-slash me-1 opacity-50"></i>No heads assigned yet.</div>');
            return;
        }
        data.heads.forEach(function (h) {
            const inactive = h.status !== 'active'
                ? ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:.65em;">Inactive</span>'
                : '';
            const assigner = h.assigner_first
                ? 'Assigned by ' + h.assigner_first + ' ' + h.assigner_last
                : '';
            const $row = $('<div class="d-flex justify-content-between align-items-center border rounded px-3 py-2 mb-2">');
            $row.append(
                $('<div>')
                    .append($('<div class="fw-semibold" style="font-size:.85rem;">').text(h.first_name + ' ' + h.last_name).append(inactive))
                    .append($('<div class="text-muted" style="font-size:.72rem;">').text(
                        (h.employee_id ? h.employee_id + ' · ' : '') + h.email + (assigner ? ' · ' + assigner : '')
                    ))
            );
            $row.append(
                $('<button class="btn btn-sm btn-outline-danger btn-remove-ph" title="Remove head">')
                    .attr('data-user-id', h.user_id)
                    .attr('data-name', h.first_name + ' ' + h.last_name)
                    .html('<i class="fas fa-times"></i>')
            );
            $list.append($row);
        });
    }

    function loadHeads() {
        $('#ph-list').html('<div class="text-center text-muted py-3 small">Loading…</div>');
        $.get(APP_URL + 'system_config/project_heads/' + phProjectId)
            .done(function (res) {
                if (!res.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                    return;
                }
                renderHeads(res);
            })
            .fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load project heads.', confirmButtonColor: '#4361ee' });
            });
    }

    // Open modal
    $('#pj-table').on('click', '.btn-heads-pj', function () {
        phProjectId = $(this).data('id');
        $('#ph-project-name').text('— ' + $(this).data('name'));
        phModal.show();
        loadHeads();
    });

    // Assign head
    $('#btn-add-ph').on('click', function () {
        const userId = $('#ph-candidate-id').val();
        if (!userId) {
            Swal.fire({ icon: 'info', title: 'Select a user', text: 'Choose a user to assign as project head.', confirmButtonColor: '#4361ee' });
            return;
        }

        setPhLoading(true);
        $.post(APP_URL + 'system_config/add_project_head/' + phProjectId, { user_id: userId })
            .done(function (res) {
                setPhLoading(false);
                if (res.success) {
                    loadHeads();
                    pjTable.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
                }
            })
            .fail(function (xhr) {
                setPhLoading(false);
                const res = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'Failed to assign project head.', confirmButtonColor: '#4361ee' });
            });
    });

    // Remove head (delegated)
    $('#ph-list').on('click', '.btn-remove-ph', function () {
        const userId = $(this).data('user-id');
        const name   = $(this).data('name');

        Swal.fire({
            title             : 'Remove Head?',
            html              : '<strong>' + name + '</strong> will no longer manage this project\'s employees.',
            icon              : 'warning',
            showCancelButton  : true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor : '#6c757d',
            confirmButtonText : 'Yes, Remove',
            cancelButtonText  : 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(APP_URL + 'system_config/remove_project_head/' + phProjectId, { user_id: userId })
                .done(function (res) {
                    if (res.success) {
                        loadHeads();
                        pjTable.ajax.reload(null, false);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                    }
                })
                .fail(function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to remove project head.', confirmButtonColor: '#4361ee' });
                });
        });
    });
}

function initSystemSettings() {
    if ($('#sys-settings-card').length === 0) return; // not super admin

    function setSysLoading(isLoading) {
        $('#btn-save-sys').prop('disabled', isLoading);
        $('#sys-spinner').toggleClass('d-none', !isLoading);
        $('#sys-save-icon').toggleClass('d-none', isLoading);
    }

    function showLogoPreview(src) {
        if (src) {
            $('#sys-logo-preview').attr('src', src).removeClass('d-none');
            $('#sys-logo-placeholder').addClass('d-none');
        } else {
            $('#sys-logo-preview').attr('src', '').addClass('d-none');
            $('#sys-logo-placeholder').removeClass('d-none');
        }
    }

    function selectTheme(slug) {
        $('.theme-swatch').removeClass('active');
        const $sw = $('.theme-swatch[data-theme="' + slug + '"]');
        $sw.addClass('active').find('input').prop('checked', true);
    }

    // Swatch selection
    $('#sys-theme-list').on('change', 'input[name="sys-theme"]', function () {
        selectTheme($(this).val());
    });

    // Live preview of a newly chosen logo file
    $('#sys-logo').on('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        $('#sys-logo-remove').prop('checked', false);
        const reader = new FileReader();
        reader.onload = function (e) { showLogoPreview(e.target.result); };
        reader.readAsDataURL(file);
    });

    $('#sys-logo-remove').on('change', function () {
        if ($(this).is(':checked')) {
            $('#sys-logo').val('');
            showLogoPreview('');
        }
    });

    // Load current settings
    $.get(APP_URL + 'system_config/system_settings')
        .done(function (res) {
            if (!res.success) return;
            const s = res.settings;
            $('#sys-name').val(s.system_name);
            $('#sys-co-name').val(s.company_name);
            $('#sys-co-tagline').val(s.company_tagline);
            $('#sys-co-address').val(s.company_address);
            $('#sys-co-email').val(s.company_email);
            $('#sys-co-phone').val(s.company_phone);
            selectTheme(s.theme || 'blue');
            showLogoPreview(s.company_logo_url || '');
        });

    // Save
    $('#btn-save-sys').on('click', function () {
        const systemName  = $.trim($('#sys-name').val());
        const companyName = $.trim($('#sys-co-name').val());

        if (!systemName) {
            Swal.fire({ icon: 'warning', title: 'Missing field', text: 'System name is required.', confirmButtonColor: '#4361ee' });
            return;
        }
        if (!companyName) {
            Swal.fire({ icon: 'warning', title: 'Missing field', text: 'Company name is required.', confirmButtonColor: '#4361ee' });
            return;
        }

        const fd = new FormData();
        fd.append('system_name',     systemName);
        fd.append('theme',           $('input[name="sys-theme"]:checked').val() || 'blue');
        fd.append('company_name',    companyName);
        fd.append('company_tagline', $.trim($('#sys-co-tagline').val()));
        fd.append('company_address', $.trim($('#sys-co-address').val()));
        fd.append('company_email',   $.trim($('#sys-co-email').val()));
        fd.append('company_phone',   $.trim($('#sys-co-phone').val()));

        const logoFile = $('#sys-logo')[0].files[0];
        if (logoFile) {
            fd.append('logo', logoFile);
        } else if ($('#sys-logo-remove').is(':checked')) {
            fd.append('remove_logo', '1');
        }

        setSysLoading(true);

        $.ajax({
            url         : APP_URL + 'system_config/save_system_settings',
            method      : 'POST',
            data        : fd,
            processData : false,
            contentType : false,
        })
            .done(function (res) {
                setSysLoading(false);
                if (res.success) {
                    Swal.fire({
                        icon             : 'success',
                        title            : 'Saved!',
                        text             : res.message + ' Refreshing to apply…',
                        confirmButtonColor: '#4361ee',
                        timer            : 1500,
                        timerProgressBar : true,
                        showConfirmButton: false,
                    }).then(function () {
                        location.reload(); // apply new theme / name / logo everywhere
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
                }
            })
            .fail(function (xhr) {
                setSysLoading(false);
                const res = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
            });
    });
}

function initAttendanceSettings() {
    if ($('#att-settings-card').length === 0) return; // not super admin

    function toggleSchedSections() {
        const amPm = $('#att-mode-am-pm').is(':checked');
        $('#att-sched-am-pm').toggle(amPm);
        $('#att-sched-single').toggle(!amPm);
    }

    $('input[name="att-mode"]').on('change', toggleSchedSections);

    // Load current settings
    $.get(APP_URL + 'system_config/attendance_settings')
        .done(function (res) {
            if (!res.success) return;
            const s = res.settings;
            $(s.attendance_mode === 'single' ? '#att-mode-single' : '#att-mode-am-pm').prop('checked', true);
            $('#att-am-in').val(s.sched_am_in);
            $('#att-am-out').val(s.sched_am_out);
            $('#att-pm-in').val(s.sched_pm_in);
            $('#att-pm-out').val(s.sched_pm_out);
            $('#att-day-in').val(s.sched_day_in);
            $('#att-day-out').val(s.sched_day_out);
            toggleSchedSections();
        });

    // Save
    $('#btn-save-att').on('click', function () {
        const $btn = $(this);
        const payload = {
            attendance_mode : $('input[name="att-mode"]:checked').val() || 'am_pm',
            sched_am_in     : $('#att-am-in').val(),
            sched_am_out    : $('#att-am-out').val(),
            sched_pm_in     : $('#att-pm-in').val(),
            sched_pm_out    : $('#att-pm-out').val(),
            sched_day_in    : $('#att-day-in').val(),
            sched_day_out   : $('#att-day-out').val(),
        };

        $btn.prop('disabled', true);
        $('#att-spinner').removeClass('d-none');
        $('#att-save-icon').addClass('d-none');

        $.post(APP_URL + 'system_config/save_attendance_settings', payload)
            .done(function (res) {
                $btn.prop('disabled', false);
                $('#att-spinner').addClass('d-none');
                $('#att-save-icon').removeClass('d-none');

                if (res.success) {
                    Swal.fire({
                        icon             : 'success',
                        title            : 'Saved!',
                        text             : res.message,
                        confirmButtonColor: '#4361ee',
                        timer            : 2000,
                        timerProgressBar : true,
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
                }
            })
            .fail(function (xhr) {
                $btn.prop('disabled', false);
                $('#att-spinner').addClass('d-none');
                $('#att-save-icon').removeClass('d-none');
                const res = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
            });
    });
}
