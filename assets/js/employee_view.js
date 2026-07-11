'use strict';

/* ================================================================
   employee_view.js – Employee Profile Page AJAX handlers
   Globals from employees/view.php: APP_URL, EMP_ID
================================================================ */

// ── Status helpers ───────────────────────────────────────────────

const STATUS_COLOR = {
    pending  : 'warning',
    approved : 'success',
    rejected : 'danger',
};

const ATT_STATUS_COLOR = {
    present  : 'success',
    absent   : 'danger',
    half_day : 'warning',
    holiday  : 'info',
    leave    : 'secondary',
    off      : 'dark',
};

function fmt(val) {
    return val !== null && val !== undefined && val !== '' ? val : '—';
}

function fmtTime(t) {
    if (!t) return '<span class="text-muted">—</span>';
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = ((hour % 12) || 12);
    return hour12 + ':' + m + ' ' + ampm;
}

function fmtDate(d) {
    if (!d) return '—';
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const dt = new Date(d + 'T00:00:00');
    return months[dt.getMonth()] + ' ' + String(dt.getDate()).padStart(2, '0') + ', ' + dt.getFullYear();
}

function fmtDateTime(d) {
    if (!d) return '—';
    const dt = new Date(d.replace(' ', 'T'));
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const h = dt.getHours(), m = dt.getMinutes();
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = ((h % 12) || 12);
    return months[dt.getMonth()] + ' ' + String(dt.getDate()).padStart(2, '0') + ', ' + dt.getFullYear()
         + ' ' + h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
}

function fmtPeso(v) {
    return v ? '₱' + parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—';
}

// ── Attendance Tab ───────────────────────────────────────────────

let attendanceLoaded = false;

function loadAttendance() {
    if (attendanceLoaded) return;

    $.get(APP_URL + 'employees/attendance/' + EMP_ID)
        .done(function (res) {
            attendanceLoaded = true;
            if (!res.success) {
                $('#attendance-content').html('<div class="alert alert-danger">' + res.message + '</div>');
                return;
            }
            if (!res.data.length) {
                $('#attendance-content').html(
                    '<div class="text-center text-muted py-5">'
                  + '<i class="fas fa-calendar-times fa-2x mb-2 d-block opacity-25"></i>'
                  + 'No attendance records found.</div>'
                );
                return;
            }

            let html = '<div class="table-responsive">'
                     + '<table class="table table-hover align-middle att-table">'
                     + '<thead class="table-light"><tr>'
                     + '<th>Date</th><th>Time In</th><th>Time Out</th>'
                     + '<th>Total Hrs</th><th>Overtime</th><th>Tardiness</th><th>Status</th><th>Notes</th>'
                     + '</tr></thead><tbody>';

            res.data.forEach(function (r) {
                const sc  = ATT_STATUS_COLOR[r.status] || 'secondary';
                const label = r.status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                html += '<tr>'
                      + '<td class="fw-semibold">' + fmtDate(r.date) + '</td>'
                      + '<td>' + fmtTime(r.time_in) + '</td>'
                      + '<td>' + fmtTime(r.time_out) + '</td>'
                      + '<td>' + (r.total_hours ? r.total_hours + ' hrs' : '<span class="text-muted">—</span>') + '</td>'
                      + '<td>' + (parseFloat(r.overtime) > 0
                            ? r.overtime + ' hrs' + (r.ot_status ? ' <span class="text-muted small">(' + r.ot_status + ')</span>' : '')
                            : '<span class="text-muted">—</span>') + '</td>'
                      + '<td>' + (r.tardiness ? r.tardiness + ' min' : '<span class="text-muted">—</span>') + '</td>'
                      + '<td><span class="badge bg-' + sc + '-subtle text-' + sc + ' border border-' + sc + '-subtle">' + label + '</span></td>'
                      + '<td class="text-muted small">' + (r.notes || '—') + '</td>'
                      + '</tr>';
            });

            html += '</tbody></table></div>';
            $('#attendance-content').html(html);
        })
        .fail(function () {
            $('#attendance-content').html('<div class="alert alert-danger">Failed to load attendance records.</div>');
        });
}

// ── Salary Requests Tab ──────────────────────────────────────────

let salaryLoaded = false;

function loadSalaryRequests() {
    if (salaryLoaded) return;

    $.get(APP_URL + 'employees/salary_requests/' + EMP_ID)
        .done(function (res) {
            salaryLoaded = true;
            if (!res.success) {
                $('#salary-requests-content').html('<div class="alert alert-danger">' + res.message + '</div>');
                return;
            }
            if (!res.data.length) {
                $('#salary-requests-content').html(
                    '<div class="text-center text-muted py-5">'
                  + '<i class="fas fa-hand-holding-usd fa-2x mb-2 d-block opacity-25"></i>'
                  + 'No salary requests found.</div>'
                );
                return;
            }

            let html = '<div class="table-responsive">'
                     + '<table class="table table-hover align-middle att-table">'
                     + '<thead class="table-light"><tr>'
                     + '<th>Type</th><th>Amount</th><th>Week</th><th>Status</th>'
                     + '<th>Reviewed By</th><th>Reviewed At</th><th>Notes</th>'
                     + '</tr></thead><tbody>';

            res.data.forEach(function (r) {
                const sc    = STATUS_COLOR[r.status] || 'secondary';
                const label = r.status.charAt(0).toUpperCase() + r.status.slice(1);
                const type  = r.type.charAt(0).toUpperCase() + r.type.slice(1);
                const reviewer = (r.reviewer_first && r.reviewer_last)
                    ? r.reviewer_first + ' ' + r.reviewer_last
                    : '<span class="text-muted">—</span>';

                html += '<tr>'
                      + '<td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">' + type + '</span></td>'
                      + '<td class="fw-semibold text-success">' + fmtPeso(r.amount) + '</td>'
                      + '<td>' + fmtDate(r.week_start) + '</td>'
                      + '<td><span class="badge bg-' + sc + '-subtle text-' + sc + ' border border-' + sc + '-subtle">' + label + '</span></td>'
                      + '<td>' + reviewer + '</td>'
                      + '<td class="text-muted small">' + (r.reviewed_at ? fmtDateTime(r.reviewed_at) : '—') + '</td>'
                      + '<td class="text-muted small">' + (r.notes || '—') + '</td>'
                      + '</tr>';
            });

            html += '</tbody></table></div>';
            $('#salary-requests-content').html(html);
        })
        .fail(function () {
            $('#salary-requests-content').html('<div class="alert alert-danger">Failed to load salary requests.</div>');
        });
}

// ── Projects Tab ─────────────────────────────────────────────────

let projectHistoryLoaded = false;

const PROJECT_STATUS_COLOR = {
    active    : 'success',
    on_hold   : 'warning',
    completed : 'secondary',
};

function loadProjectHistory(force) {
    if (projectHistoryLoaded && !force) return;

    $.get(APP_URL + 'employees/project_history/' + EMP_ID)
        .done(function (res) {
            projectHistoryLoaded = true;
            if (!res.success) {
                $('#project-history-content').html('<div class="alert alert-danger">' + res.message + '</div>');
                return;
            }
            if (!res.data.length) {
                $('#project-history-content').html(
                    '<div class="text-center text-muted py-5">'
                  + '<i class="fas fa-building fa-2x mb-2 d-block opacity-25"></i>'
                  + 'This employee has never been assigned to a project.</div>'
                );
                return;
            }

            // One row per project (data is newest-first, so the first
            // occurrence carries the latest assignment info)
            const seen  = {};
            const projects = res.data.filter(function (r) {
                const key = r.project_id || 'deleted-' + r.id;
                if (seen[key]) return false;
                seen[key] = true;
                return true;
            });

            let html = '<div class="table-responsive">'
                     + '<table class="table table-hover align-middle att-table">'
                     + '<thead class="table-light"><tr>'
                     + '<th>Project</th><th>Location</th><th>Project Status</th>'
                     + '<th>Assigned By</th>'
                     + '</tr></thead><tbody>';

            projects.forEach(function (r) {
                const current  = !r.ended_at;
                const deleted  = !r.project_name;
                const sc       = PROJECT_STATUS_COLOR[r.project_status] || 'secondary';
                const psLabel  = r.project_status
                    ? r.project_status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
                    : '—';
                const assigner = (r.assigner_first && r.assigner_last)
                    ? escHtml(r.assigner_first + ' ' + r.assigner_last)
                    : '<span class="text-muted">—</span>';

                html += '<tr>'
                      + '<td class="fw-semibold"><i class="fas fa-building text-info me-2"></i>'
                      + (deleted ? '<span class="text-muted fst-italic">Deleted project</span>' : escHtml(r.project_name))
                      + (current
                            ? ' <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size:.65em;">Current</span>'
                            : '')
                      + '</td>'
                      + '<td class="text-muted small">' + (r.project_location ? escHtml(r.project_location) : '—') + '</td>'
                      + '<td><span class="badge bg-' + sc + '-subtle text-' + sc + ' border border-' + sc + '-subtle">' + psLabel + '</span></td>'
                      + '<td class="small">' + assigner + '</td>'
                      + '</tr>';
            });

            html += '</tbody></table></div>';
            $('#project-history-content').html(html);
        })
        .fail(function () {
            $('#project-history-content').html('<div class="alert alert-danger">Failed to load project history.</div>');
        });
}

// ── Files Tab ────────────────────────────────────────────────────

let filesLoaded  = false;
let filesData    = [];

const FILE_ICONS = {
    pdf : 'fa-file-pdf text-danger',
    doc : 'fa-file-word text-primary',   docx: 'fa-file-word text-primary',
    xls : 'fa-file-excel text-success',  xlsx: 'fa-file-excel text-success', csv: 'fa-file-csv text-success',
    png : 'fa-file-image text-info',     jpg : 'fa-file-image text-info',
    jpeg: 'fa-file-image text-info',     gif : 'fa-file-image text-info',   webp: 'fa-file-image text-info',
    zip : 'fa-file-archive text-warning',
    txt : 'fa-file-lines text-secondary',
};

function fmtFileSize(bytes) {
    bytes = parseInt(bytes) || 0;
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}

function loadFiles(force) {
    if (filesLoaded && !force) return;

    $.get(APP_URL + 'employees/files/' + EMP_ID)
        .done(function (res) {
            filesLoaded = true;
            if (!res.success) {
                $('#files-content').html('<div class="alert alert-danger">' + res.message + '</div>');
                return;
            }
            filesData = res.data;
            renderFiles();
        })
        .fail(function () {
            $('#files-content').html('<div class="alert alert-danger">Failed to load files.</div>');
        });
}

function renderFiles() {
    const showArchived = $('#ef-show-archived').is(':checked');
    const files = filesData.filter(f => showArchived || f.status === 'active');

    if (!files.length) {
        $('#files-content').html(
            '<div class="text-center text-muted py-5">'
          + '<i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>'
          + (filesData.length
                ? 'No active files. Toggle "Show archived" to see archived files.'
                : 'No files uploaded yet.')
          + '</div>'
        );
        return;
    }

    let html = '<div class="table-responsive">'
             + '<table class="table table-hover align-middle att-table">'
             + '<thead class="table-light"><tr>'
             + '<th>Name</th><th>Original File</th><th>Size</th>'
             + '<th>Uploaded By</th><th>Uploaded At</th><th>Status</th>'
             + '<th class="text-end">Actions</th>'
             + '</tr></thead><tbody>';

    files.forEach(function (f) {
        const icon     = FILE_ICONS[f.file_ext] || 'fa-file text-secondary';
        const archived = f.status === 'archived';
        const uploader = (f.uploader_first && f.uploader_last)
            ? escHtml(f.uploader_first + ' ' + f.uploader_last)
            : '<span class="text-muted">—</span>';

        const statusBadge = archived
            ? '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Archived</span>'
            : '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>';

        const archiveBtn = archived
            ? '<button class="btn btn-sm btn-outline-success btn-restore-file" data-id="' + f.id + '" data-name="' + escHtml(f.name) + '" title="Restore">'
            + '<i class="fas fa-undo me-1"></i>Restore</button>'
            : '<button class="btn btn-sm btn-outline-secondary btn-archive-file" data-id="' + f.id + '" data-name="' + escHtml(f.name) + '" title="Archive">'
            + '<i class="fas fa-box-archive me-1"></i>Archive</button>';

        html += '<tr' + (archived ? ' class="opacity-50"' : '') + '>'
              + '<td class="fw-semibold"><i class="fas ' + icon + ' me-2"></i>' + escHtml(f.name) + '</td>'
              + '<td class="text-muted small">' + escHtml(f.original_name) + '</td>'
              + '<td class="text-muted small">' + fmtFileSize(f.file_size) + '</td>'
              + '<td class="small">' + uploader + '</td>'
              + '<td class="text-muted small">' + fmtDateTime(f.created_at) + '</td>'
              + '<td>' + statusBadge + '</td>'
              + '<td class="text-end text-nowrap">'
              + '<a href="' + f.url + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-1" title="View">'
              + '<i class="fas fa-eye me-1"></i>View</a>'
              + archiveBtn
              + '</td>'
              + '</tr>';
    });

    html += '</tbody></table></div>';
    $('#files-content').html(html);
}

// ── Document Ready ───────────────────────────────────────────────

$(document).ready(function () {

    // ── Tab: lazy-load attendance and salary requests ────────────

    $('#tab-attendance').on('shown.bs.tab', function () {
        loadAttendance();
    });

    $('#tab-salary').on('shown.bs.tab', function () {
        loadSalaryRequests();
    });

    $('#tab-projects').on('shown.bs.tab', function () {
        loadProjectHistory();
    });

    $('#tab-files').on('shown.bs.tab', function () {
        loadFiles();
    });

    // ── Files: show/hide archived ────────────────────────────────

    $('#ef-show-archived').on('change', function () {
        if (filesLoaded) renderFiles();
    });

    // ── Files: upload ────────────────────────────────────────────

    $('#btn-upload-file').on('click', function () {
        $('#file-upload-form .form-control').removeClass('is-invalid');
        $('#file-upload-form .invalid-feedback').remove();

        const name  = $.trim($('#ef-name').val());
        const input = document.getElementById('ef-file');
        const file  = input.files[0];

        let hasError = false;
        if (!name) {
            $('#ef-name').addClass('is-invalid').after('<div class="invalid-feedback">Please enter a name for this file.</div>');
            hasError = true;
        }
        if (!file) {
            $('#ef-file').addClass('is-invalid').after('<div class="invalid-feedback">Please choose a file.</div>');
            hasError = true;
        }
        if (file && file.size > 10485760) {
            $('#ef-file').addClass('is-invalid').after('<div class="invalid-feedback">File is too large (max 10 MB).</div>');
            hasError = true;
        }
        if (hasError) return;

        const formData = new FormData();
        formData.append('name', name);
        formData.append('file', file);

        const $btn     = $(this);
        const $spinner = $('#ef-spinner');
        const $icon    = $('#ef-upload-icon');
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');

        $.ajax({
            url         : APP_URL + 'employees/upload_file/' + EMP_ID,
            type        : 'POST',
            data        : formData,
            processData : false,
            contentType : false,
        })
        .done(function (res) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');

            if (res.success) {
                $('#ef-name').val('');
                $('#ef-file').val('');
                Swal.fire({
                    icon             : 'success',
                    title            : 'Uploaded!',
                    text             : res.message,
                    confirmButtonColor: '#4361ee',
                    timer            : 2000,
                    timerProgressBar : true,
                });
                loadFiles(true);
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function (xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');
            const res = xhr.responseJSON;
            Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'Failed to upload file.', confirmButtonColor: '#4361ee' });
        });
    });

    // ── Files: archive / restore ─────────────────────────────────

    $('#files-content').on('click', '.btn-archive-file, .btn-restore-file', function () {
        const $btn      = $(this);
        const fileId    = $btn.data('id');
        const fileName  = $btn.data('name');
        const isArchive = $btn.hasClass('btn-archive-file');

        Swal.fire({
            icon               : 'question',
            title              : isArchive ? 'Archive this file?' : 'Restore this file?',
            text               : isArchive
                ? '"' + fileName + '" will be hidden from the active list. You can restore it anytime.'
                : '"' + fileName + '" will be moved back to the active list.',
            showCancelButton   : true,
            confirmButtonText  : isArchive ? 'Yes, archive' : 'Yes, restore',
            confirmButtonColor : '#4361ee',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(APP_URL + 'employees/' + (isArchive ? 'archive_file/' : 'restore_file/') + fileId)
                .done(function (res) {
                    if (res.success) {
                        Swal.fire({
                            icon             : 'success',
                            title            : 'Done!',
                            text             : res.message,
                            confirmButtonColor: '#4361ee',
                            timer            : 1800,
                            timerProgressBar : true,
                        });
                        loadFiles(true);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
                    }
                })
                .fail(function (xhr) {
                    const res = xhr.responseJSON;
                    Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
                });
        });
    });

    // ── Payroll: toggle contribution amount field ────────────────

    $('.contribution-toggle').on('change', function () {
        const $amountInput = $(this).closest('.contribution-card').find('.contribution-amount');
        if ($(this).is(':checked')) {
            $amountInput.prop('disabled', false).focus();
        } else {
            $amountInput.prop('disabled', true).val('0.00');
        }
    });

    // ── Payroll: suggested SSS employee share (2025 SSS table) ───
    // Contribution rate is 15% of the Monthly Salary Credit (MSC):
    // 10% employer + 5% employee. MSC brackets run in P500 steps
    // from P5,000 to P35,000. Monthly salary is estimated from the
    // daily rate x 26 working days.
    function updateSssHint() {
        const $hint = $('#pf-sss-hint');
        if (!$hint.length) return;

        if (!$('#pf-sss-enabled').is(':checked')) {
            $hint.html('');
            return;
        }

        const dailyRate = parseFloat($('#pf-daily-rate').val()) || 0;
        if (dailyRate <= 0) {
            $hint.text('Enter a daily rate to see the suggested SSS deduction.');
            return;
        }

        const monthly = dailyRate * 26;
        let msc = Math.round(monthly / 500) * 500;
        msc = Math.min(35000, Math.max(5000, msc));

        const monthlyShare = msc * 0.05;   // employee share = 5% of MSC
        const weeklyShare  = monthlyShare / 4;

        $hint.html(
            'Per SSS table: <strong>&#8369;' + monthlyShare.toFixed(2) + '</strong>/month' +
            ' &asymp; <strong>&#8369;' + weeklyShare.toFixed(2) + '</strong>/week' +
            ' (5% of MSC &#8369;' + msc.toLocaleString() + ')'
        );
    }

    $('#pf-daily-rate').on('input', updateSssHint);
    $('#pf-sss-enabled').on('change', updateSssHint);
    updateSssHint();

    // ── Payroll: Save ────────────────────────────────────────────

    $('#btn-save-payroll').on('click', function () {
        const $btn     = $(this);
        const $spinner = $('#payroll-spinner');
        const $icon    = $('#payroll-save-icon');

        const daily_rate = parseFloat($('#pf-daily-rate').val());
        if (isNaN(daily_rate) || daily_rate < 0) {
            Swal.fire({ icon: 'warning', title: 'Validation', text: 'Please enter a valid daily rate.', confirmButtonColor: '#4361ee' });
            return;
        }

        const payload = {
            daily_rate         : daily_rate,
            sss_enabled        : $('#pf-sss-enabled').is(':checked') ? 1 : 0,
            sss_amount         : parseFloat($('#pf-sss-amount').val()) || 0,
            philhealth_enabled : $('#pf-philhealth-enabled').is(':checked') ? 1 : 0,
            philhealth_amount  : parseFloat($('#pf-philhealth-amount').val()) || 0,
            pagibig_enabled    : $('#pf-pagibig-enabled').is(':checked') ? 1 : 0,
            pagibig_amount     : parseFloat($('#pf-pagibig-amount').val()) || 0,
        };

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');

        $.post(APP_URL + 'employees/save_payroll_info/' + EMP_ID, payload)
            .done(function (res) {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $icon.removeClass('d-none');

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
            .fail(function () {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $icon.removeClass('d-none');
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save payroll information.', confirmButtonColor: '#4361ee' });
            });
    });

    // ── Emergency Contact: Save ──────────────────────────────────

    $('#btn-save-ec').on('click', function () {
        $('#ec-form .form-control, #ec-form .form-select').removeClass('is-invalid');
        $('#ec-form .invalid-feedback').remove();

        const name     = $.trim($('#ec-name').val());
        const phone    = $.trim($('#ec-phone').val());

        let hasError = false;
        if (!name) {
            $('#ec-name').addClass('is-invalid').after('<div class="invalid-feedback">Contact name is required.</div>');
            hasError = true;
        }
        if (!phone) {
            $('#ec-phone').addClass('is-invalid').after('<div class="invalid-feedback">Phone number is required.</div>');
            hasError = true;
        }
        if (hasError) return;

        const $btn     = $('#btn-save-ec');
        const $spinner = $('#ec-spinner');
        const $icon    = $('#ec-save-icon');
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');

        $.post(APP_URL + 'employees/save_emergency_contact/' + EMP_ID, {
            name         : name,
            relationship : $('#ec-relationship').val(),
            phone        : phone,
            phone_alt    : $.trim($('#ec-phone-alt').val()),
            address      : $.trim($('#ec-address').val()),
        })
        .done(function (res) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');

            if (res.success) {
                Swal.fire({
                    icon             : 'success',
                    title            : 'Saved!',
                    text             : res.message,
                    confirmButtonColor: '#4361ee',
                    timer            : 2000,
                    timerProgressBar : true,
                });
                // Update "on file" badge
                const $header = $('#ec-form').closest('.col-lg').find('h6').parent();
                $header.find('.badge').replaceWith(
                    '<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem;">'
                  + '<i class="fas fa-check me-1"></i>On file</span>'
                );
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function (xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');
            const res = xhr.responseJSON;
            Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
        });
    });

    // ── Edit Employee Info ───────────────────────────────────────

    const editEmpModal = new bootstrap.Modal(document.getElementById('edit-emp-modal'));

    $('#btn-edit-emp-info').on('click', function () {
        // Clear any previous validation states
        $('#edit-emp-form .form-control, #edit-emp-form .form-select').removeClass('is-invalid');
        $('#edit-emp-form .invalid-feedback').remove();
        $('#ei-password').val('');
        editEmpModal.show();
    });

    $('#toggle-ei-pass').on('click', function () {
        const $p = $('#ei-password');
        const $i = $(this).find('i');
        if ($p.attr('type') === 'password') {
            $p.attr('type', 'text');
            $i.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $p.attr('type', 'password');
            $i.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    function showEiError(fieldId, message) {
        const $field = $('#' + fieldId);
        $field.addClass('is-invalid');
        $field.closest('.input-group, .mb-3, .col-md-6, .col-md-3').find('.invalid-feedback').remove();
        $field.after('<div class="invalid-feedback">' + message + '</div>');
    }

    $('#btn-save-emp-info').on('click', function () {
        $('#edit-emp-form .form-control, #edit-emp-form .form-select').removeClass('is-invalid');
        $('#edit-emp-form .invalid-feedback').remove();

        const first_name  = $.trim($('#ei-first-name').val());
        const last_name   = $.trim($('#ei-last-name').val());
        const username    = $.trim($('#ei-username').val());
        const email       = $.trim($('#ei-email').val());
        const password    = $('#ei-password').val();
        const job_role_id = $('#ei-job-role-id').val();
        const status      = $('#ei-status').val();
        const date_hired  = $('#ei-date-hired').val();
        const timesheet_type = $('#ei-timesheet-type').val();

        let hasError = false;
        if (!first_name) { showEiError('ei-first-name', 'First name is required.'); hasError = true; }
        if (!last_name)  { showEiError('ei-last-name',  'Last name is required.');  hasError = true; }
        if (!username)   { showEiError('ei-username',   'Username is required.');   hasError = true; }
        if (!email)      { showEiError('ei-email',      'Email is required.');      hasError = true; }
        if (password && password.length < 6) {
            showEiError('ei-password', 'Password must be at least 6 characters.');
            hasError = true;
        }
        if (hasError) return;

        const $btn     = $('#btn-save-emp-info');
        const $spinner = $('#ei-spinner');
        const $icon    = $('#ei-save-icon');
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');

        $.post(APP_URL + 'employees/update_info/' + EMP_ID, {
            first_name  : first_name,
            last_name   : last_name,
            username    : username,
            email       : email,
            password    : password,
            job_role_id : job_role_id,
            status      : status,
            date_hired  : date_hired,
            timesheet_type : timesheet_type,
        })
        .done(function (res) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');

            if (res.success) {
                editEmpModal.hide();
                Swal.fire({
                    icon             : 'success',
                    title            : 'Updated!',
                    text             : res.message,
                    confirmButtonColor: '#4361ee',
                    timer            : 2000,
                    timerProgressBar : true,
                    willClose        : () => window.location.reload(),
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function (xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');
            const res = xhr.responseJSON;
            Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
        });
    });

    // Reset password toggle on modal close
    $('#edit-emp-modal').on('hidden.bs.modal', function () {
        $('#ei-password').attr('type', 'password');
        $('#toggle-ei-pass').find('i').removeClass('fa-eye-slash').addClass('fa-eye');
    });

    // ── Set Project ──────────────────────────────────────────────

    const setProjectModal = new bootstrap.Modal(document.getElementById('set-project-modal'));

    $('#btn-set-project, #btn-set-project-tab').on('click', function () {
        $('#sp-project-id').val(EMP_PROJECT_ID ? String(EMP_PROJECT_ID) : '');
        setProjectModal.show();
    });

    $('#btn-save-project').on('click', function () {
        const $btn     = $(this);
        const $spinner = $('#sp-spinner');
        const $icon    = $('#sp-save-icon');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');

        $.post(APP_URL + 'employees/set_project/' + EMP_ID, {
            project_id : $('#sp-project-id').val(),
        })
        .done(function (res) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');

            if (res.success) {
                setProjectModal.hide();
                Swal.fire({
                    icon             : 'success',
                    title            : 'Saved!',
                    text             : res.message,
                    confirmButtonColor: '#4361ee',
                    timer            : 2000,
                    timerProgressBar : true,
                    willClose        : () => window.location.reload(),
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function (xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');
            const res = xhr.responseJSON;
            Swal.fire({ icon: 'error', title: 'Error', text: res ? res.message : 'An unexpected error occurred.', confirmButtonColor: '#4361ee' });
        });
    });
});

// ── HTML escape helper ───────────────────────────────────────────
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
