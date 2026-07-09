'use strict';

/* ================================================================
   payroll.js
   Handles:
     - Edit Payroll Settings modal (daily rate + govt contributions)
     - Set Borrow Deduction modal (per-employee per-week decision)
     - Payroll Adjustments modal (custom line item additions/deductions)
   Globals set by view: PAYROLL_APP_URL, PAYROLL_WEEK, PAYROLL_TYPE
================================================================ */

// ── Helpers ───────────────────────────────────────────────────────

function reloadPayrollPage() {
    var url = PAYROLL_APP_URL + 'payroll' + (PAYROLL_WEEK ? '?week=' + PAYROLL_WEEK + '&type=' + PAYROLL_TYPE : '');
    window.location.href = url;
}

function toggleBenefitAmount(checkboxId, wrapId) {
    var enabled = document.getElementById(checkboxId).checked;
    var wrap    = document.getElementById(wrapId);
    if (enabled) {
        wrap.style.display = '';
        wrap.querySelector('input').focus();
    } else {
        wrap.style.display = 'none';
        wrap.querySelector('input').value = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('ps-sss-enabled').addEventListener('change', function () {
        toggleBenefitAmount('ps-sss-enabled', 'ps-sss-amount-wrap');
    });
    document.getElementById('ps-philhealth-enabled').addEventListener('change', function () {
        toggleBenefitAmount('ps-philhealth-enabled', 'ps-philhealth-amount-wrap');
    });
    document.getElementById('ps-pagibig-enabled').addEventListener('change', function () {
        toggleBenefitAmount('ps-pagibig-enabled', 'ps-pagibig-amount-wrap');
    });
});

// ================================================================
//  PAYROLL ADJUSTMENTS MODAL  (custom line items)
// ================================================================

var _lineItemsChanged = false;

function renderLineItems(items) {
    var $list  = $('#li-items-list');
    var $empty = $('#li-empty-state');

    if (!items || items.length === 0) {
        $empty.show();
        $list.find('.li-item-row').remove();
        return;
    }

    $empty.hide();

    // Remove old rows, keep empty-state element
    $list.find('.li-item-row').remove();

    $.each(items, function (_, item) {
        var isAdd   = item.type === 'addition';
        var color   = isAdd ? '#15803d' : '#b91c1c';
        var icon    = isAdd ? 'fa-plus-circle' : 'fa-minus-circle';
        var prefix  = isAdd ? '+' : '−';
        var amount  = parseFloat(item.amount).toFixed(2);
        var notes   = item.notes ? '<span class="text-muted" style="font-size:.75rem;"> — ' + $('<div>').text(item.notes).html() + '</span>' : '';
        var addedBy = item.added_by_name ? '<span class="text-muted" style="font-size:.7rem;"> by ' + $('<div>').text(item.added_by_name).html() + '</span>' : '';

        var $row = $('<div>', { 'class': 'li-item-row d-flex align-items-center justify-content-between mb-2 p-2 rounded border' })
            .css('background', isAdd ? '#f0fdf4' : '#fff1f2')
            .html(
                '<div style="flex:1;">'
                + '<i class="fas ' + icon + ' me-1" style="color:' + color + ';"></i>'
                + '<strong style="color:' + color + ';">&#8369;' + amount + '</strong>'
                + ' <span>' + $('<div>').text(item.label).html() + '</span>'
                + notes + addedBy
                + '</div>'
                + '<button type="button" class="btn btn-xs btn-outline-danger ms-2 btn-delete-line-item" data-item-id="' + item.id + '">'
                + '<i class="fas fa-trash-alt"></i></button>'
            );

        $list.append($row);
    });
}

// Open modal
$(document).on('click', '.btn-adjust-items', function () {
    var $btn = $(this);
    _lineItemsChanged = false;

    $('#li-user-id').val($btn.data('user-id'));
    $('#li-week-start').val($btn.data('week-start'));
    $('#li-employee-label').html(
        '<i class="fas fa-user me-1 text-primary"></i><strong>' + $('<div>').text($btn.data('name')).html() + '</strong>'
    );

    // Reset add form
    $('[name="li-type"][value="addition"]').prop('checked', true);
    $('#li-label').val('');
    $('#li-amount').val('');
    $('#li-notes').val('');

    // Fetch current items
    $('#li-items-list').find('.li-item-row').remove();
    $('#li-empty-state').show();

    $.get(PAYROLL_APP_URL + 'payroll/line_items', {
        user_id    : $btn.data('user-id'),
        week_start : $btn.data('week-start'),
    }).done(function (res) {
        if (res.success) renderLineItems(res.items);
    });

    new bootstrap.Modal(document.getElementById('line-items-modal')).show();
    setTimeout(function () { document.getElementById('li-label').focus(); }, 350);
});

// Reload page when modal closes (if items changed)
document.getElementById('line-items-modal').addEventListener('hidden.bs.modal', function () {
    if (_lineItemsChanged) reloadPayrollPage();
});

// Delete a line item
$(document).on('click', '.btn-delete-line-item', function () {
    var $btn   = $(this);
    var itemId = $btn.data('item-id');

    Swal.fire({
        icon              : 'question',
        title             : 'Remove this item?',
        text              : 'This adjustment will be removed from the payroll.',
        showCancelButton  : true,
        confirmButtonColor: '#dc2626',
        confirmButtonText : 'Yes, remove',
        cancelButtonText  : 'Cancel',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.post(PAYROLL_APP_URL + 'payroll/delete_line_item/' + itemId)
        .done(function (res) {
            if (res.success) {
                _lineItemsChanged = true;
                // Refresh the list
                var userId    = $('#li-user-id').val();
                var weekStart = $('#li-week-start').val();
                $.get(PAYROLL_APP_URL + 'payroll/line_items', {
                    user_id: userId, week_start: weekStart,
                }).done(function (r2) {
                    if (r2.success) renderLineItems(r2.items);
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.', confirmButtonColor: '#4361ee' });
        });
    });
});

// Add a line item
$(document).on('click', '#btn-add-line-item', function () {
    var userId    = $('#li-user-id').val();
    var weekStart = $('#li-week-start').val();
    var type      = $('[name="li-type"]:checked').val();
    var label     = $.trim($('#li-label').val());
    var amount    = parseFloat($('#li-amount').val());
    var notes     = $.trim($('#li-notes').val());

    if (!userId || !weekStart) return;

    if (label === '') {
        Swal.fire({ icon: 'error', title: 'Label required', text: 'Please enter a description for this item.', confirmButtonColor: '#4361ee' });
        $('#li-label').focus();
        return;
    }

    if (isNaN(amount) || amount <= 0) {
        Swal.fire({ icon: 'error', title: 'Invalid amount', text: 'Amount must be greater than zero.', confirmButtonColor: '#4361ee' });
        $('#li-amount').focus();
        return;
    }

    var $btn = $('#btn-add-line-item');
    $btn.prop('disabled', true);
    $('#li-spinner').removeClass('d-none');
    $('#li-add-icon').addClass('d-none');

    $.post(PAYROLL_APP_URL + 'payroll/add_line_item', {
        user_id    : userId,
        week_start : weekStart,
        type       : type,
        label      : label,
        amount     : amount,
        notes      : notes,
    })
    .done(function (res) {
        $btn.prop('disabled', false);
        $('#li-spinner').addClass('d-none');
        $('#li-add-icon').removeClass('d-none');

        if (res.success) {
            _lineItemsChanged = true;
            renderLineItems(res.items);
            // Clear the form
            $('#li-label').val('').focus();
            $('#li-amount').val('');
            $('#li-notes').val('');
            $('[name="li-type"][value="addition"]').prop('checked', true);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
        }
    })
    .fail(function (xhr) {
        $btn.prop('disabled', false);
        $('#li-spinner').addClass('d-none');
        $('#li-add-icon').removeClass('d-none');
        var r = xhr.responseJSON;
        Swal.fire({ icon: 'error', title: 'Error', text: r ? r.message : 'Request failed.', confirmButtonColor: '#4361ee' });
    });
});

// ================================================================
//  SET BORROW DEDUCTION MODAL
// ================================================================

$(document).on('click', '.btn-set-borrow-deduct', function () {
    var $btn          = $(this);
    var userId        = $btn.data('user-id');
    var name          = $btn.data('name');
    var weekStart     = $btn.data('week-start');
    var borrowAmount  = parseFloat($btn.data('borrow-amount')) || 0;
    var remaining     = parseFloat($btn.data('borrow-remaining')) || 0;
    var currentDeduct = $btn.data('current-deduct');

    $('#bd-user-id').val(userId);
    $('#bd-week-start').val(weekStart);
    $('#bd-employee-name').html(
        '<i class="fas fa-user me-1 text-primary"></i><strong>' + name + '</strong>'
    );

    var infoHtml = '<i class="fas fa-hand-holding me-1 text-primary"></i>'
        + 'Approved borrow request: <strong>&#8369;' + borrowAmount.toFixed(2) + '</strong>';
    if (remaining > 0 && remaining !== borrowAmount) {
        infoHtml += '<br><i class="fas fa-exclamation-circle me-1" style="color:#7c3aed;"></i>'
            + 'Remaining balance (including past periods): <strong style="color:#7c3aed;">&#8369;' + remaining.toFixed(2) + '</strong>';
    }
    $('#bd-borrow-info').html(infoHtml);

    var maxAmount = remaining > 0 ? remaining : borrowAmount;
    $('#bd-amount').val(currentDeduct !== '' ? parseFloat(currentDeduct).toFixed(2) : '');
    $('#bd-max-hint').text('Remaining balance: ₱' + maxAmount.toFixed(2) + ' — suggested maximum for this period.');
    $('#bd-amount').attr('max', maxAmount);

    new bootstrap.Modal(document.getElementById('borrow-deduct-modal')).show();
    setTimeout(function () { document.getElementById('bd-amount').focus(); }, 300);
});

$(document).on('click', '#btn-save-borrow-deduct', function () {
    var userId    = $('#bd-user-id').val();
    var weekStart = $('#bd-week-start').val();
    var amount    = parseFloat($('#bd-amount').val());

    if (!userId || !weekStart) return;

    if (isNaN(amount) || amount < 0) {
        Swal.fire({
            icon             : 'error',
            title            : 'Invalid Amount',
            text             : 'Enter 0 or a positive deduction amount.',
            confirmButtonColor: '#4361ee',
        });
        return;
    }

    var $btn = $('#btn-save-borrow-deduct');
    $btn.prop('disabled', true);
    $('#bd-spinner').removeClass('d-none');
    $('#bd-save-icon').addClass('d-none');

    $.post(PAYROLL_APP_URL + 'payroll/set_borrow_deduction', {
        user_id      : userId,
        week_start   : weekStart,
        deduct_amount: amount,
    })
    .done(function (res) {
        $btn.prop('disabled', false);
        $('#bd-spinner').addClass('d-none');
        $('#bd-save-icon').removeClass('d-none');

        var modal = bootstrap.Modal.getInstance(document.getElementById('borrow-deduct-modal'));
        if (modal) modal.hide();

        if (res.success) {
            Swal.fire({
                icon             : 'success',
                title            : 'Saved!',
                text             : res.message,
                confirmButtonColor: '#4361ee',
                timer            : 1600,
                timerProgressBar : true,
            }).then(reloadPayrollPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
        }
    })
    .fail(function (xhr) {
        $btn.prop('disabled', false);
        $('#bd-spinner').addClass('d-none');
        $('#bd-save-icon').removeClass('d-none');
        var r = xhr.responseJSON;
        Swal.fire({ icon: 'error', title: 'Error', text: r ? r.message : 'Request failed.', confirmButtonColor: '#4361ee' });
    });
});

// ================================================================
//  EDIT PAYROLL SETTINGS MODAL  (daily rate + govt contributions)
// ================================================================

$(document).on('click', '.btn-edit-payroll-settings', function () {
    var $btn = $(this);

    $('#ps-user-id').val($btn.data('user-id'));
    $('#ps-employee-name').html(
        '<i class="fas fa-user me-1 text-primary"></i><strong>' + $btn.data('name') + '</strong>'
    );
    $('#ps-daily-rate').val($btn.data('rate') || '');

    // SSS
    var sssEnabled = !!parseInt($btn.data('sss-enabled'), 10);
    $('#ps-sss-enabled').prop('checked', sssEnabled);
    $('#ps-sss-amount-wrap').toggle(sssEnabled);
    $('#ps-sss-amount').val(sssEnabled ? ($btn.data('sss-amount') || '') : '');

    // PhilHealth
    var phEnabled = !!parseInt($btn.data('philhealth-enabled'), 10);
    $('#ps-philhealth-enabled').prop('checked', phEnabled);
    $('#ps-philhealth-amount-wrap').toggle(phEnabled);
    $('#ps-philhealth-amount').val(phEnabled ? ($btn.data('philhealth-amount') || '') : '');

    // Pag-IBIG
    var piEnabled = !!parseInt($btn.data('pagibig-enabled'), 10);
    $('#ps-pagibig-enabled').prop('checked', piEnabled);
    $('#ps-pagibig-amount-wrap').toggle(piEnabled);
    $('#ps-pagibig-amount').val(piEnabled ? ($btn.data('pagibig-amount') || '') : '');

    new bootstrap.Modal(document.getElementById('payroll-settings-modal')).show();
});

$(document).on('click', '#btn-save-payroll-settings', function () {
    var userId    = $('#ps-user-id').val();
    var dailyRate = parseFloat($('#ps-daily-rate').val());

    if (!userId) return;

    if (isNaN(dailyRate) || dailyRate < 0) {
        Swal.fire({
            icon             : 'error',
            title            : 'Invalid Rate',
            text             : 'Please enter a valid daily rate (0 or greater).',
            confirmButtonColor: '#4361ee',
        });
        return;
    }

    var sssEnabled  = $('#ps-sss-enabled').is(':checked')        ? 1 : 0;
    var sssAmount   = parseFloat($('#ps-sss-amount').val())      || 0;
    var phEnabled   = $('#ps-philhealth-enabled').is(':checked') ? 1 : 0;
    var phAmount    = parseFloat($('#ps-philhealth-amount').val())|| 0;
    var piEnabled   = $('#ps-pagibig-enabled').is(':checked')    ? 1 : 0;
    var piAmount    = parseFloat($('#ps-pagibig-amount').val())  || 0;

    var $btn = $('#btn-save-payroll-settings');
    $btn.prop('disabled', true);
    $('#ps-spinner').removeClass('d-none');
    $('#ps-save-icon').addClass('d-none');

    $.post(PAYROLL_APP_URL + 'payroll/save_settings', {
        user_id            : userId,
        daily_rate         : dailyRate,
        sss_enabled        : sssEnabled,
        sss_amount         : sssAmount,
        philhealth_enabled : phEnabled,
        philhealth_amount  : phAmount,
        pagibig_enabled    : piEnabled,
        pagibig_amount     : piAmount,
    })
    .done(function (res) {
        $btn.prop('disabled', false);
        $('#ps-spinner').addClass('d-none');
        $('#ps-save-icon').removeClass('d-none');

        var modal = bootstrap.Modal.getInstance(document.getElementById('payroll-settings-modal'));
        if (modal) modal.hide();

        if (res.success) {
            Swal.fire({
                icon             : 'success',
                title            : 'Saved!',
                text             : res.message,
                confirmButtonColor: '#4361ee',
                timer            : 1600,
                timerProgressBar : true,
            }).then(reloadPayrollPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
        }
    })
    .fail(function (xhr) {
        $btn.prop('disabled', false);
        $('#ps-spinner').addClass('d-none');
        $('#ps-save-icon').removeClass('d-none');
        var r = xhr.responseJSON;
        Swal.fire({ icon: 'error', title: 'Error', text: r ? r.message : 'Request failed.', confirmButtonColor: '#4361ee' });
    });
});
