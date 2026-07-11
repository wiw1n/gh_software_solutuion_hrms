'use strict';

/* ================================================================
   attendance.js
   Handles: monthly timesheet (employee + admin), camera clock-in/out,
            employee DataTable (admin), edit record modal (admin).
   Globals set by view: APP_URL, VIEW_USER_ID, CAN_EDIT, IS_SELF
================================================================ */

// ── State ────────────────────────────────────────────────────────

let currentYear  = new Date().getFullYear();
let currentMonth = new Date().getMonth() + 1; // 1–12

let currentStream        = null;
let capturedImageData    = null;
let currentClockAction   = null; // 'time_in' | 'time_out'

const MONTH_NAMES = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

// Status display config
const STATUS_CFG = {
    present   : { cls: '',                           label: '<span class="badge bg-success-subtle text-success border border-success-subtle">Present</span>' },
    absent    : { cls: 'table-danger',               label: '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Absent</span>' },
    half_day  : { cls: 'table-warning',              label: '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Half Day</span>' },
    holiday   : { cls: 'table-info',                 label: '<span class="badge bg-info-subtle text-info border border-info-subtle">Holiday</span>' },
    leave     : { cls: 'table-secondary',            label: '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Leave</span>' },
    off       : { cls: 'table-light',                label: '<span class="badge bg-dark-subtle text-dark border border-dark-subtle">Off</span>' },
    no_record : { cls: 'text-muted',                 label: '<span class="badge bg-light text-muted border">No Record</span>' },
    future    : { cls: 'text-muted',                 label: '<span class="text-muted small">—</span>' },
};

// ── Helpers ──────────────────────────────────────────────────────

function fmt12(timeStr) {
    if (!timeStr) return '—';
    const [h, m] = timeStr.split(':');
    const hour   = parseInt(h, 10);
    const ampm   = hour >= 12 ? 'PM' : 'AM';
    const h12    = hour % 12 || 12;
    return h12 + ':' + m + ' ' + ampm;
}

function fmtNum(val, dec, unit) {
    if (val === null || val === undefined || val === '') return '—';
    const n = parseFloat(val);
    return n === 0 ? '—' : n.toFixed(dec) + (unit ? ' ' + unit : '');
}

function isWeekend(dayName) {
    return dayName === 'Saturday' || dayName === 'Sunday';
}

// ── Attendance mode helpers ──────────────────────────────────────
// ATT_MODE global ('am_pm' | 'single') set by the attendance views.
// AM/PM mode shows four punch columns instead of one in/out pair.

function isAmPmMode() {
    return typeof ATT_MODE !== 'undefined' && ATT_MODE === 'am_pm';
}

function tsColspan() {
    return isAmPmMode() ? 12 : 10;
}

// ── Week / Period helpers ────────────────────────────────────────
// TIMESHEET_TYPE global ('weekly' | 'semi_monthly') decides how days
// are grouped: Mon–Sun weeks, or 1st–15th / 16th–end-of-month halves.

function isSemiMonthly() {
    return typeof TIMESHEET_TYPE !== 'undefined' && TIMESHEET_TYPE === 'semi_monthly';
}

// Returns 'YYYY-MM-DD' for the start of the period containing dateStr.
function getPeriodStart(dateStr) {
    if (isSemiMonthly()) {
        const p = dateStr.split('-');
        return p[0] + '-' + p[1] + '-' + (parseInt(p[2], 10) <= 15 ? '01' : '16');
    }
    return getWeekStart(dateStr);
}

// Returns 'YYYY-MM-DD' for the last day of the period starting at periodStart.
function getPeriodEnd(periodStart) {
    if (isSemiMonthly()) {
        const p = periodStart.split('-');
        if (parseInt(p[2], 10) === 1) {
            return p[0] + '-' + p[1] + '-15';
        }
        const lastDay = new Date(parseInt(p[0], 10), parseInt(p[1], 10), 0).getDate();
        return p[0] + '-' + p[1] + '-' + String(lastDay).padStart(2, '0');
    }
    return addDays(periodStart, 6);
}

// Returns 'YYYY-MM-DD' for the Monday of the week containing dateStr.
function getWeekStart(dateStr) {
    const p   = dateStr.split('-');
    const d   = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    const dow = d.getDay(); // 0=Sun
    d.setDate(d.getDate() - (dow === 0 ? 6 : dow - 1));
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
}

function addDays(dateStr, n) {
    const p = dateStr.split('-');
    const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    d.setDate(d.getDate() + n);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
}

function fmtShort(dateStr) {
    const p = dateStr.split('-');
    const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

// ── Timesheet Load ───────────────────────────────────────────────

function loadTimesheet(year, month) {
    $('#month-label').text(MONTH_NAMES[month - 1] + ' ' + year);
    $('#timesheet-tbody').html(
        '<tr><td colspan="' + tsColspan() + '" class="text-center py-4">'
        + '<span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading...</td></tr>'
    );

    $.get(APP_URL + 'attendance/monthly', { user_id: VIEW_USER_ID, year: year, month: month })
        .done(function (res) {
            if (!res.success) {
                $('#timesheet-tbody').html('<tr><td colspan="' + tsColspan() + '" class="text-center text-danger">Failed to load data.</td></tr>');
                return;
            }
            renderTimesheet(res.rows, res.confirmed_weeks || [], res.salary_requests || {});
            renderSummary(res.summary);
        })
        .fail(function () {
            $('#timesheet-tbody').html('<tr><td colspan="' + tsColspan() + '" class="text-center text-danger">Request failed.</td></tr>');
        });
}

function renderTimesheet(rows, confirmedWeeks, salaryRequests) {
    if (!rows || rows.length === 0) {
        $('#timesheet-tbody').html('<tr><td colspan="' + tsColspan() + '" class="text-center text-muted py-4">No data for this month.</td></tr>');
        return;
    }

    // Group rows by period start (Monday, or 1st/16th for semi-monthly)
    const groups = {};
    rows.forEach(function (r) {
        const ws = getPeriodStart(r.date);
        if (!groups[ws]) groups[ws] = [];
        groups[ws].push(r);
    });

    const todayStr    = new Date().toISOString().slice(0, 10);
    const sortedWeeks = Object.keys(groups).sort();
    let html = '';

    sortedWeeks.forEach(function (ws) {
        const weekRows  = groups[ws];
        const weekEnd   = getPeriodEnd(ws);
        const confirmed = confirmedWeeks.indexOf(ws) !== -1;

        // Week summary
        const daysPresent = weekRows.filter(function (r) { return r.status === 'present'; }).length;
        const totalHours  = weekRows.reduce(function (s, r) { return s + (parseFloat(r.total_hours) || 0); }, 0);
        const totalOT     = weekRows.reduce(function (s, r) { return s + (r.ot_status === 'approved' ? (parseFloat(r.overtime) || 0) : 0); }, 0);
        const pendingOT   = weekRows.reduce(function (s, r) { return s + (r.ot_status === 'pending'  ? (parseFloat(r.overtime) || 0) : 0); }, 0);
        const totalTard   = weekRows.reduce(function (s, r) { return s + (parseFloat(r.tardiness)   || 0); }, 0);

        // Confirm / Unconfirm button (admin only). A period with days that
        // have no record at all (incl. future days) cannot be confirmed —
        // absences must be marked Absent/Leave/Holiday first. The server
        // re-checks this across month boundaries.
        const periodWord  = isSemiMonthly() ? 'Period' : 'Week';
        const missingDays = weekRows.filter(function (r) { return !r.id; }).length;
        let confirmHtml = '';
        if (CAN_EDIT) {
            if (confirmed) {
                confirmHtml = '<span class="badge bg-light text-success border border-success-subtle px-2 py-1 me-1">'
                            + '<i class="fas fa-lock me-1"></i>Confirmed</span>'
                            + '<button class="btn btn-xs btn-outline-light btn-unconfirm-week"'
                            + ' data-user="' + VIEW_USER_ID + '" data-week="' + ws + '"'
                            + ' title="Remove confirmation"><i class="fas fa-unlock-alt"></i></button>';
            } else if (missingDays > 0) {
                confirmHtml = '<span class="badge bg-warning text-dark px-2 py-1"'
                            + ' title="Every day needs a record before this ' + periodWord.toLowerCase()
                            + ' can be confirmed — mark missing days as Absent, Leave, or Holiday.">'
                            + '<i class="fas fa-exclamation-triangle me-1"></i>'
                            + missingDays + ' day' + (missingDays > 1 ? 's' : '') + ' with no record</span>';
            } else {
                confirmHtml = '<button class="btn btn-sm btn-outline-light btn-confirm-week"'
                            + ' data-user="' + VIEW_USER_ID + '" data-week="' + ws + '">'
                            + '<i class="fas fa-check-circle me-1"></i>Confirm ' + periodWord + '</button>';
            }
        }

        // Advance / Borrow — status badges are visible to everyone, but the
        // request/edit buttons only render for the employee's project head
        // (or super admin) viewing this timesheet (CAN_REQUEST_SALARY).
        let salaryHtml = '';
        var canReqSalary = (typeof CAN_REQUEST_SALARY !== 'undefined') && CAN_REQUEST_SALARY;
        var weekReqs     = (salaryRequests && salaryRequests[ws]) || {};

        ['advance', 'borrow'].forEach(function (type) {
            var req   = weekReqs[type];
            var icon  = type === 'advance' ? 'fa-hand-holding-usd' : 'fa-hand-holding';
            var lbl   = type === 'advance' ? 'Advance' : 'Borrow';
            var btnCls = type === 'advance' ? 'btn-warning' : 'btn-info';

            if (!req) {
                if (canReqSalary) {
                    salaryHtml += '<button class="btn btn-xs ' + btnCls + ' btn-week-salary me-1"'
                                + ' data-type="' + type + '" data-week="' + ws + '">'
                                + '<i class="fas ' + icon + ' me-1"></i>' + lbl + '</button>';
                }
            } else if (req.status === 'pending') {
                salaryHtml += '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 me-1">'
                            + '<i class="fas fa-clock me-1"></i>' + lbl + ' ₱' + parseFloat(req.amount).toFixed(2) + ' · Pending</span>';
                if (canReqSalary) {
                    salaryHtml += '<button class="btn btn-xs btn-outline-warning btn-edit-salary-req me-1"'
                                + ' data-id="' + req.id + '" data-type="' + type + '" data-week="' + ws + '"'
                                + ' data-amount="' + req.amount + '" data-notes="' + escHtml(req.notes || '') + '"'
                                + ' title="Edit Request"><i class="fas fa-pen"></i></button>';
                }
            } else if (req.status === 'approved') {
                salaryHtml += '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 me-1">'
                            + '<i class="fas fa-check me-1"></i>' + lbl + ' ₱' + parseFloat(req.amount).toFixed(2) + ' · Approved</span>';
            } else {
                salaryHtml += '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 me-1">'
                            + '<i class="fas fa-times me-1"></i>' + lbl + ' ₱' + parseFloat(req.amount).toFixed(2) + ' · Declined</span>';
            }
        });

        // Week header row
        const headerCls = confirmed ? 'bg-success' : 'table-dark';

        html += '<tr class="' + headerCls + '">'
              + '<td colspan="' + tsColspan() + '" class="py-2 px-3">'
              + '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">'

              + '<div class="fw-bold small text-white">'
              + '<i class="fas fa-calendar-week me-2"></i>'
              + fmtShort(ws) + ' – ' + fmtShort(weekEnd)
              + (isSemiMonthly()
                    ? (ws.slice(-2) === '01' ? ' (1st Half)' : ' (2nd Half)')
                    : ' (Mon–Sun)')
              + '</div>'

              + '<div class="d-flex align-items-center gap-3 small text-white-50">'
              + '<span><i class="fas fa-user-check me-1"></i>' + daysPresent + ' present</span>'
              + '<span><i class="fas fa-clock me-1"></i>' + totalHours.toFixed(2) + ' hrs</span>'
              + (totalTard > 0 ? '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>' + totalTard.toFixed(0) + ' min late</span>' : '')
              + (totalOT   > 0 ? '<span><i class="fas fa-star me-1"></i>' + totalOT.toFixed(2) + ' OT</span>' : '')
              + (pendingOT > 0 ? '<span class="text-warning"><i class="fas fa-hourglass-half me-1"></i>' + pendingOT.toFixed(2) + ' OT pending</span>' : '')
              + '</div>'

              + '<div class="d-flex align-items-center gap-1">'
              + salaryHtml
              + (salaryHtml && CAN_EDIT ? '<span class="text-white-50 mx-1">|</span>' : '')
              + (CAN_EDIT ? confirmHtml : '')
              + '</div>'

              + '</div>'
              + '</td>'
              + '</tr>';

        // Day rows for this week
        weekRows.forEach(function (r) {
            const cfg     = STATUS_CFG[r.status] || STATUS_CFG.no_record;
            const weekend = isWeekend(r.day);
            let rowClass  = '';

            if (r.is_today) {
                rowClass = 'table-primary';
            } else if (weekend) {
                rowClass = 'table-light';
            } else if (cfg.cls) {
                rowClass = cfg.cls;
            }

            const dateStr  = fmtShort(r.date);
            const tardCell = r.tardiness > 0
                ? '<span class="text-danger fw-semibold">' + parseFloat(r.tardiness).toFixed(0) + ' min</span>'
                : '<span class="text-muted">—</span>';
            const otCell   = buildOtCell(r);
            const hrsCell  = r.total_hours
                ? '<span class="fw-semibold">' + parseFloat(r.total_hours).toFixed(2) + '</span>'
                : '<span class="text-muted">—</span>';

            const hasPhotos = r.time_in_photo || r.time_out_photo
                           || r.am_time_in_photo || r.am_time_out_photo
                           || r.pm_time_in_photo || r.pm_time_out_photo;

            let actions = '';
            if (!r.is_future) {
                if (hasPhotos) {
                    actions += '<button class="btn btn-xs btn-outline-secondary btn-view-photos me-1 mb-1"'
                             + ' data-in="' + (r.time_in_photo || '') + '"'
                             + ' data-out="' + (r.time_out_photo || '') + '"'
                             + ' data-am-in="' + (r.am_time_in_photo || '') + '"'
                             + ' data-am-out="' + (r.am_time_out_photo || '') + '"'
                             + ' data-pm-in="' + (r.pm_time_in_photo || '') + '"'
                             + ' data-pm-out="' + (r.pm_time_out_photo || '') + '"'
                             + ' data-date="' + r.date + '"'
                             + ' title="View Photos"><i class="fas fa-images"></i></button>';
                }
                if (CAN_EDIT) {
                    actions += '<button class="btn btn-xs btn-outline-info btn-edit-record mb-1"'
                             + ' data-id="' + (r.id || 0) + '"'
                             + ' data-user="' + VIEW_USER_ID + '"'
                             + ' data-date="' + r.date + '"'
                             + ' data-time-in="' + (r.time_in || '') + '"'
                             + ' data-time-out="' + (r.time_out || '') + '"'
                             + ' data-am-time-in="' + (r.am_time_in || '') + '"'
                             + ' data-am-time-out="' + (r.am_time_out || '') + '"'
                             + ' data-pm-time-in="' + (r.pm_time_in || '') + '"'
                             + ' data-pm-time-out="' + (r.pm_time_out || '') + '"'
                             + ' data-status="' + (r.status === 'future' ? 'present' : r.status) + '"'
                             + ' data-notes="' + (r.notes || '') + '"'
                             + ' title="Edit"><i class="fas fa-edit"></i></button>';
                    if (r.id) {
                        actions += '<button class="btn btn-xs btn-outline-danger btn-delete-record mb-1"'
                                 + ' data-id="' + r.id + '"'
                                 + ' data-date="' + r.date + '"'
                                 + ' title="Delete"><i class="fas fa-trash"></i></button>';
                    }
                }
            }
            if (!actions) actions = '<span class="text-muted">—</span>';

            let timeCells;
            if (isAmPmMode()) {
                timeCells = '<td class="small">' + fmt12(r.am_time_in) + '</td>'
                          + '<td class="small">' + fmt12(r.am_time_out) + '</td>'
                          + '<td class="small">' + fmt12(r.pm_time_in) + '</td>'
                          + '<td class="small">' + fmt12(r.pm_time_out) + '</td>';
            } else {
                timeCells = '<td class="small">' + fmt12(r.time_in) + '</td>'
                          + '<td class="small">' + fmt12(r.time_out) + '</td>';
            }

            html += '<tr class="' + rowClass + (r.is_today ? ' fw-semibold' : '') + '">'
                  + '<td class="small ps-4">'
                  +   (r.is_today ? '<span class="badge bg-primary me-1" style="font-size:.6rem;">Today</span>' : '')
                  +   dateStr
                  + '</td>'
                  + '<td class="small ' + (weekend ? 'text-muted' : '') + '">' + r.day.substring(0, 3) + '</td>'
                  + '<td>' + cfg.label + '</td>'
                  + timeCells
                  + '<td class="text-center small">' + hrsCell + '</td>'
                  + '<td class="text-center small">' + tardCell + '</td>'
                  + '<td class="text-center small">' + otCell + '</td>'
                  + '<td class="small text-muted">' + (r.notes ? escHtml(r.notes) : '—') + '</td>'
                  + '<td class="text-center" style="white-space:nowrap;">' + actions + '</td>'
                  + '</tr>';
        });
    });

    $('#timesheet-tbody').html(html);
}

// ── OT Approval (head / admin) ───────────────────────────────────
// OT records carry ot_status: 'pending' | 'approved' | 'declined'.
// Viewers with CAN_EDIT get approve/decline buttons; a decided OT
// keeps a small button to reverse the decision.

const OT_BADGES = {
    pending : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle d-block mt-1" style="font-size:.6rem;">Pending</span>',
    approved: '<span class="badge bg-success-subtle text-success border border-success-subtle d-block mt-1" style="font-size:.6rem;">Approved</span>',
    declined: '<span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block mt-1" style="font-size:.6rem;">Declined</span>',
};

function otActionBtn(r, action, cls, icon, title) {
    return '<button class="btn btn-xs ' + cls + ' btn-ot-action"'
         + ' data-id="' + r.id + '" data-action="' + action + '"'
         + ' data-hours="' + parseFloat(r.overtime).toFixed(2) + '" data-date="' + r.date + '"'
         + ' title="' + title + '"><i class="fas ' + icon + '"></i></button>';
}

function buildOtCell(r) {
    const hrs = parseFloat(r.overtime) || 0;
    if (hrs <= 0) return '<span class="text-muted">—</span>';

    let cell = '<span class="text-info fw-semibold">' + hrs.toFixed(2) + '</span>'
             + (OT_BADGES[r.ot_status] || '');

    if (CAN_EDIT && r.id && r.ot_status) {
        if (r.ot_status === 'pending') {
            cell += '<div class="mt-1 d-flex gap-1 justify-content-center">'
                  + otActionBtn(r, 'approve', 'btn-success', 'fa-check', 'Approve OT')
                  + otActionBtn(r, 'decline', 'btn-outline-danger', 'fa-times', 'Decline OT')
                  + '</div>';
        } else if (r.ot_status === 'approved') {
            cell += '<div class="mt-1">' + otActionBtn(r, 'decline', 'btn-outline-danger', 'fa-times', 'Decline this OT instead') + '</div>';
        } else {
            cell += '<div class="mt-1">' + otActionBtn(r, 'approve', 'btn-outline-success', 'fa-check', 'Approve this OT instead') + '</div>';
        }
    }
    return cell;
}

$(document).on('click', '.btn-ot-action', function () {
    const $btn   = $(this);
    const action = $btn.data('action');
    const hours  = $btn.data('hours');
    const date   = $btn.data('date');
    const verb   = action === 'approve' ? 'Approve' : 'Decline';

    Swal.fire({
        title             : verb + ' Overtime?',
        html              : verb + ' <strong>' + hours + ' hr(s)</strong> of overtime on <strong>' + fmtShort(date) + '</strong>?'
                          + (action === 'approve'
                                ? '<br><small class="text-muted">Approved OT is paid at Daily Rate ÷ 8 × OT hours.</small>'
                                : '<br><small class="text-muted">Declined OT is not paid in payroll.</small>'),
        icon              : 'question',
        showCancelButton  : true,
        confirmButtonColor: action === 'approve' ? '#198754' : '#dc3545',
        cancelButtonColor : '#6c757d',
        confirmButtonText : '<i class="fas fa-' + (action === 'approve' ? 'check' : 'times') + ' me-1"></i>Yes, ' + verb,
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post(APP_URL + 'attendance/ot_action', { record_id: $btn.data('id'), action: action })
            .done(function (res) {
                if (res.success) {
                    loadTimesheet(currentYear, currentMonth);
                    Swal.fire({ icon: 'success', title: 'Done!', text: res.message, confirmButtonColor: '#4361ee', timer: 1800, timerProgressBar: true });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                }
            }).fail(function (xhr) {
                const r = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Error', text: r ? r.message : 'Request failed.', confirmButtonColor: '#4361ee' });
            });
    });
});

function renderSummary(s) {
    if (!s) return;
    $('#sum-present').text(s.days_present || 0);
    $('#sum-hours').text(s.total_hours ? parseFloat(s.total_hours).toFixed(2) : '0.00');
    $('#sum-tardiness').text(s.total_tardiness ? parseFloat(s.total_tardiness).toFixed(0) : '0');
    $('#sum-ot').text(s.total_overtime ? parseFloat(s.total_overtime).toFixed(2) : '0.00');

    const pend = parseFloat(s.total_ot_pending) || 0;
    $('#sum-ot-pending').text(pend > 0 ? '+ ' + pend.toFixed(2) + ' pending approval' : '');
}

// ── Week Confirm / Unconfirm (Admin) ─────────────────────────────

$(document).on('click', '.btn-confirm-week', function () {
    const $btn  = $(this);
    const user  = $btn.data('user');
    const week  = $btn.data('week');

    const periodWord = isSemiMonthly() ? 'period' : 'week';
    Swal.fire({
        title             : 'Confirm this ' + periodWord + '?',
        html              : 'Mark the ' + periodWord + ' of <strong>' + fmtShort(week) + ' – ' + fmtShort(getPeriodEnd(week)) + '</strong> as confirmed and ready for salary preparation.<br><br>You can undo this later.',
        icon              : 'question',
        showCancelButton  : true,
        confirmButtonColor: '#198754',
        cancelButtonColor : '#6c757d',
        confirmButtonText : '<i class="fas fa-check me-1"></i>Yes, Confirm',
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post(APP_URL + 'attendance/confirm_week', { user_id: user, week_start: week, action: 'confirm' })
            .done(function (res) {
                if (res.success) {
                    loadTimesheet(currentYear, currentMonth);
                    Swal.fire({ icon: 'success', title: 'Confirmed!', text: res.message, confirmButtonColor: '#4361ee', timer: 1800, timerProgressBar: true });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                }
            }).fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.', confirmButtonColor: '#4361ee' });
            });
    });
});

$(document).on('click', '.btn-unconfirm-week', function () {
    const $btn = $(this);
    const user = $btn.data('user');
    const week = $btn.data('week');

    Swal.fire({
        title             : 'Remove confirmation?',
        html              : 'Unconfirm the ' + (isSemiMonthly() ? 'period' : 'week') + ' of <strong>' + fmtShort(week) + ' – ' + fmtShort(getPeriodEnd(week)) + '</strong>?',
        icon              : 'warning',
        showCancelButton  : true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor : '#6c757d',
        confirmButtonText : 'Yes, Unconfirm',
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post(APP_URL + 'attendance/confirm_week', { user_id: user, week_start: week, action: 'unconfirm' })
            .done(function (res) {
                if (res.success) {
                    loadTimesheet(currentYear, currentMonth);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                }
            }).fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.', confirmButtonColor: '#4361ee' });
            });
    });
});

function escHtml(str) {
    return $('<div>').text(str).html();
}

// ── Month Navigation ─────────────────────────────────────────────

$(document).on('click', '#btn-prev-month', function () {
    currentMonth--;
    if (currentMonth < 1) { currentMonth = 12; currentYear--; }
    loadTimesheet(currentYear, currentMonth);
});

$(document).on('click', '#btn-next-month', function () {
    currentMonth++;
    if (currentMonth > 12) { currentMonth = 1; currentYear++; }
    loadTimesheet(currentYear, currentMonth);
});

// ── Camera Clock In / Out ────────────────────────────────────────

function openCameraModal(action, label) {
    currentClockAction = action;
    capturedImageData  = null;

    const title = label || (action === 'time_in' ? 'Clock In' : 'Clock Out');
    $('#camera-modal-title').html('<i class="fas fa-camera me-2 text-primary"></i>' + title);

    // Reset modal state
    $('#camera-video').show();
    $('#camera-preview').hide().attr('src', '');
    $('#btn-capture').show();
    $('#btn-retake').hide();
    $('#btn-submit-clock').addClass('d-none');
    $('#camera-hint').text('Position yourself clearly in the frame, then click Capture.');

    // Request camera
    navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' } })
        .then(function (stream) {
            currentStream = stream;
            document.getElementById('camera-video').srcObject = stream;
            new bootstrap.Modal(document.getElementById('camera-modal')).show();
        })
        .catch(function (err) {
            Swal.fire({
                icon : 'error',
                title: 'Camera Error',
                text : 'Could not access camera. Please allow camera permissions and try again. (' + err.message + ')',
                confirmButtonColor: '#4361ee',
            });
        });
}

// Stop camera when modal is closed
$('#camera-modal').on('hide.bs.modal', function () {
    stopCamera();
});

function stopCamera() {
    if (currentStream) {
        currentStream.getTracks().forEach(function (t) { t.stop(); });
        currentStream = null;
    }
}

// Capture photo
$(document).on('click', '#btn-capture', function () {
    const video  = document.getElementById('camera-video');
    const canvas = document.getElementById('camera-canvas');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    capturedImageData = canvas.toDataURL('image/jpeg', 0.85);

    $('#camera-preview').attr('src', capturedImageData).show();
    $('#camera-video').hide();
    $(this).hide();
    $('#btn-retake').show();
    $('#btn-submit-clock').removeClass('d-none');
    $('#camera-hint').text('Photo captured. Click Confirm to submit.');
});

// Retake
$(document).on('click', '#btn-retake', function () {
    capturedImageData = null;
    $('#camera-preview').hide().attr('src', '');
    $('#camera-video').show();
    $('#btn-capture').show();
    $(this).hide();
    $('#btn-submit-clock').addClass('d-none');
    $('#camera-hint').text('Position yourself clearly in the frame, then click Capture.');
});

// Submit clock action
$(document).on('click', '#btn-submit-clock', function () {
    if (!capturedImageData) return;

    const $btn = $(this);
    $btn.prop('disabled', true);
    $('#clock-spinner').removeClass('d-none');
    $('#clock-icon').addClass('d-none');

    $.post(APP_URL + 'attendance/' + currentClockAction, { photo: capturedImageData })
        .done(function (res) {
            $('#clock-spinner').addClass('d-none');
            $('#clock-icon').removeClass('d-none');
            $btn.prop('disabled', false);

            stopCamera();
            const modal = bootstrap.Modal.getInstance(document.getElementById('camera-modal'));
            if (modal) modal.hide();

            if (res.success) {
                Swal.fire({
                    icon             : 'success',
                    title            : 'Done!',
                    text             : res.message,
                    confirmButtonColor: '#4361ee',
                    timer            : 2000,
                    timerProgressBar : true,
                }).then(function () { location.reload(); });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function (xhr) {
            $('#clock-spinner').addClass('d-none');
            $('#clock-icon').removeClass('d-none');
            $btn.prop('disabled', false);
            const r = xhr.responseJSON;
            Swal.fire({ icon: 'error', title: 'Error', text: r ? r.message : 'Request failed.', confirmButtonColor: '#4361ee' });
        });
});

// ── Photo Viewer ─────────────────────────────────────────────────

$(document).on('click', '.btn-view-photos', function () {
    const d = $(this).data();

    $('#photo-date-label').text(d.date ? '(' + d.date + ')' : '');

    let slots;
    if (isAmPmMode()) {
        slots = [
            { label: 'AM Time In',  icon: 'fa-sign-in-alt text-success',  src: d.amIn },
            { label: 'AM Time Out', icon: 'fa-sign-out-alt text-danger',  src: d.amOut },
            { label: 'PM Time In',  icon: 'fa-sign-in-alt text-success',  src: d.pmIn },
            { label: 'PM Time Out', icon: 'fa-sign-out-alt text-danger',  src: d.pmOut },
        ];
    } else {
        slots = [
            { label: 'Time In Photo',  icon: 'fa-sign-in-alt text-success',  src: d.in },
            { label: 'Time Out Photo', icon: 'fa-sign-out-alt text-danger',  src: d.out },
        ];
    }

    let html = '';
    slots.forEach(function (s) {
        html += '<div class="col-md-6 text-center">'
              + '<h6 class="fw-semibold mb-2"><i class="fas ' + s.icon + ' me-1"></i>' + s.label + '</h6>'
              + (s.src
                    ? '<img src="' + APP_URL + s.src + '" alt="' + s.label + '" class="img-fluid rounded border" style="max-height:250px;">'
                    : '<p class="text-muted small mt-1 mb-0">No photo taken</p>')
              + '</div>';
    });
    $('#photo-slots').html(html);

    new bootstrap.Modal(document.getElementById('photo-modal')).show();
});

// ── Edit Record (Admin) ──────────────────────────────────────────

$(document).on('click', '.btn-edit-record', function () {
    const $btn   = $(this);
    const id     = $btn.data('id') || 0;
    const userId = $btn.data('user');
    const date   = $btn.data('date');

    $('#er-record-id').val(id);
    $('#er-user-id').val(userId);
    $('#er-date').val(date);
    if (isAmPmMode()) {
        $('#er-am-time-in').val($btn.data('am-time-in') || '');
        $('#er-am-time-out').val($btn.data('am-time-out') || '');
        $('#er-pm-time-in').val($btn.data('pm-time-in') || '');
        $('#er-pm-time-out').val($btn.data('pm-time-out') || '');
    } else {
        $('#er-time-in').val($btn.data('time-in') || '');
        $('#er-time-out').val($btn.data('time-out') || '');
    }
    $('#er-status').val($btn.data('status') || 'present');
    $('#er-notes').val($btn.data('notes') || '');

    new bootstrap.Modal(document.getElementById('edit-record-modal')).show();
});

$(document).on('click', '#btn-save-record', function () {
    const id     = $('#er-record-id').val();
    const userId = $('#er-user-id').val();
    const date   = $('#er-date').val();

    if (!date || !userId) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Date is required.', confirmButtonColor: '#4361ee' });
        return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true);
    $('#er-spinner').removeClass('d-none');

    const payload = {
        user_id : userId,
        date    : date,
        status  : $('#er-status').val(),
        notes   : $('#er-notes').val(),
    };
    if (isAmPmMode()) {
        payload.am_time_in  = $('#er-am-time-in').val();
        payload.am_time_out = $('#er-am-time-out').val();
        payload.pm_time_in  = $('#er-pm-time-in').val();
        payload.pm_time_out = $('#er-pm-time-out').val();
    } else {
        payload.time_in  = $('#er-time-in').val();
        payload.time_out = $('#er-time-out').val();
    }

    $.post(APP_URL + 'attendance/edit/' + id, payload).done(function (res) {
        $btn.prop('disabled', false);
        $('#er-spinner').addClass('d-none');

        if (res.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('edit-record-modal'));
            if (modal) modal.hide();
            Swal.fire({
                icon             : 'success',
                title            : 'Saved!',
                text             : res.message,
                confirmButtonColor: '#4361ee',
                timer            : 1800,
                timerProgressBar : true,
            }).then(function () { loadTimesheet(currentYear, currentMonth); });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
        }
    }).fail(function () {
        $btn.prop('disabled', false);
        $('#er-spinner').addClass('d-none');
        Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.', confirmButtonColor: '#4361ee' });
    });
});

// ── Delete Record (Admin) ─────────────────────────────────────────

$(document).on('click', '.btn-delete-record', function () {
    const id   = $(this).data('id');
    const date = $(this).data('date');

    Swal.fire({
        title             : 'Delete Record?',
        html              : 'Delete attendance record for <strong>' + date + '</strong>?<br>This cannot be undone.',
        icon              : 'warning',
        showCancelButton  : true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor : '#6c757d',
        confirmButtonText : 'Yes, Delete',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.post(APP_URL + 'attendance/delete_record/' + id)
            .done(function (res) {
                if (res.success) {
                    loadTimesheet(currentYear, currentMonth);
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: res.message, confirmButtonColor: '#4361ee', timer: 1800, timerProgressBar: true });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
                }
            }).fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete record.', confirmButtonColor: '#4361ee' });
            });
    });
});

// ── Employee DataTable (Admin) ────────────────────────────────────

let empTable;

function initEmployeesTable() {
    if ($('#employees-table').length === 0) return;

    empTable = $('#employees-table').DataTable({
        processing : true,
        serverSide : true,
        ajax       : {
            url : APP_URL + 'attendance/datatable',
            type: 'POST',
        },
        columns    : [
            { data: 0, orderable: true,  searchable: false, width: '52px' },
            { data: 1, orderable: true,  searchable: true  },
            { data: 2, orderable: true,  searchable: false },
            { data: 3, orderable: false, searchable: false },
            { data: 4, orderable: false, searchable: false },
            { data: 5, orderable: false, searchable: false, className: 'text-center' },
        ],
        order      : [[0, 'asc']],
        pageLength : 10,
        lengthMenu : [10, 25, 50],
        language   : {
            processing : '<div class="d-flex align-items-center gap-2 text-primary"><span class="spinner-border spinner-border-sm"></span><span>Loading...</span></div>',
            emptyTable : '<div class="text-center text-muted py-3"><i class="fas fa-users fa-2x mb-2 d-block"></i>No employees found.</div>',
            zeroRecords: '<div class="text-center text-muted py-3"><i class="fas fa-search fa-2x mb-2 d-block"></i>No matching records.</div>',
        },
    });
}

// ── Init ─────────────────────────────────────────────────────────

$(document).ready(function () {

    // Load timesheet on page load
    loadTimesheet(currentYear, currentMonth);

    // Initialize employees DataTable if present (admin view)
    initEmployeesTable();

    // When admin switches to My Timesheet tab, init the month nav
    $('#tab-my-ts-btn').on('shown.bs.tab', function () {
        loadTimesheet(currentYear, currentMonth);
    });

});

// ── Salary Requests (per-week buttons in timesheet header) ───────

function openSalaryModal(type, weekStart, id, amount, notes) {
    var editing = !!(id && id > 0);
    var label   = type === 'advance' ? 'Advance Payment' : 'Borrow Money';
    var title   = (editing ? 'Edit ' : 'Request ') + label;

    $('#salary-modal-title').html('<i class="fas fa-money-bill-wave me-2 text-success"></i>' + title);
    $('#sr-id').val(id || 0);
    $('#sr-type').val(type);
    $('#sr-week-start').val(weekStart);
    $('#sr-week-info').html(
        '<i class="fas fa-calendar-week me-1 text-primary"></i>'
        + (isSemiMonthly() ? 'Period' : 'Week') + ' of <strong>' + fmtShort(weekStart) + ' – ' + fmtShort(getPeriodEnd(weekStart)) + '</strong>'
        + '&nbsp;&nbsp;|&nbsp;&nbsp;Max: <strong>₱1,000.00</strong>'
    );
    $('#sr-amount').val(amount || '');
    $('#sr-notes').val(notes || '');
    $('#sr-icon').attr('class', 'fas ' + (editing ? 'fa-save' : 'fa-paper-plane') + ' me-1');
    new bootstrap.Modal(document.getElementById('salary-request-modal')).show();
}

// New request button in week header
$(document).on('click', '.btn-week-salary', function () {
    openSalaryModal($(this).data('type'), $(this).data('week'));
});

// Edit pending request button
$(document).on('click', '.btn-edit-salary-req', function () {
    var $b = $(this);
    openSalaryModal($b.data('type'), $b.data('week'), $b.data('id'), $b.data('amount'), $b.data('notes'));
});

// Submit (create OR update)
$(document).on('click', '#btn-submit-salary-request', function () {
    var id     = parseInt($('#sr-id').val(), 10);
    var amount = parseFloat($('#sr-amount').val());
    var type   = $('#sr-type').val();

    if (!amount || amount <= 0 || amount > 1000) {
        Swal.fire({ icon: 'error', title: 'Invalid Amount', text: 'Enter an amount between ₱1 and ₱1,000.', confirmButtonColor: '#4361ee' });
        return;
    }

    var $btn    = $(this);
    var editing = (id > 0);
    var url     = editing
        ? APP_URL + 'SalaryRequest/update/' + id
        : APP_URL + 'SalaryRequest/request';
    var payload = editing
        ? { amount: amount, notes: $('#sr-notes').val() }
        : { user_id: VIEW_USER_ID, type: type, amount: amount, notes: $('#sr-notes').val(), week_start: $('#sr-week-start').val() };

    $btn.prop('disabled', true);
    $('#sr-spinner').removeClass('d-none');
    $('#sr-icon').addClass('d-none');

    $.post(url, payload)
        .done(function (res) {
            $btn.prop('disabled', false);
            $('#sr-spinner').addClass('d-none');
            $('#sr-icon').removeClass('d-none');

            var modal = bootstrap.Modal.getInstance(document.getElementById('salary-request-modal'));
            if (modal) modal.hide();

            if (res.success) {
                Swal.fire({
                    icon             : 'success',
                    title            : editing ? 'Updated!' : 'Request Submitted!',
                    text             : res.message,
                    confirmButtonColor: '#4361ee',
                    timer            : 2000,
                    timerProgressBar : true,
                }).then(function () { loadTimesheet(currentYear, currentMonth); });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function (xhr) {
            $btn.prop('disabled', false);
            $('#sr-spinner').addClass('d-none');
            $('#sr-icon').removeClass('d-none');
            var r = xhr.responseJSON;
            Swal.fire({ icon: 'error', title: 'Error', text: r ? r.message : 'Request failed.', confirmButtonColor: '#4361ee' });
        });
});

// ── Expose to inline onclick handlers (for time in/out buttons in PHP)
window.openCameraModal = openCameraModal;
