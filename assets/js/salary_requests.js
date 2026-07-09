'use strict';

// ── Approve button ────────────────────────────────────────────────

$(document).on('click', '.btn-approve-sr', function () {
    var $btn   = $(this);
    var id     = $btn.data('id');
    var name   = $btn.data('name');
    var type   = $btn.data('type');
    var amount = $btn.data('amount');

    $('#anm-title').html('<i class="fas fa-check-circle me-2 text-success"></i>Approve Request');
    $('#anm-submit-btn').removeClass('btn-danger').addClass('btn-success');
    $('#anm-icon').attr('class', 'fas fa-check me-1');
    $('#anm-info').html(
        '<strong>' + name + '</strong> — ' + type
        + '&nbsp;&nbsp;|&nbsp;&nbsp;<strong>₱' + amount + '</strong>'
    );
    $('#anm-id').val(id);
    $('#anm-action').val('approve');
    $('#anm-notes').val('');

    new bootstrap.Modal(document.getElementById('admin-notes-modal')).show();
});

// ── Decline button ────────────────────────────────────────────────

$(document).on('click', '.btn-reject-sr', function () {
    var $btn   = $(this);
    var id     = $btn.data('id');
    var name   = $btn.data('name');
    var type   = $btn.data('type');
    var amount = $btn.data('amount');

    $('#anm-title').html('<i class="fas fa-times-circle me-2 text-danger"></i>Decline Request');
    $('#anm-submit-btn').removeClass('btn-success').addClass('btn-danger');
    $('#anm-icon').attr('class', 'fas fa-times me-1');
    $('#anm-info').html(
        '<strong>' + name + '</strong> — ' + type
        + '&nbsp;&nbsp;|&nbsp;&nbsp;<strong>₱' + amount + '</strong>'
    );
    $('#anm-id').val(id);
    $('#anm-action').val('reject');
    $('#anm-notes').val('');

    new bootstrap.Modal(document.getElementById('admin-notes-modal')).show();
});

// ── Submit approve / decline ──────────────────────────────────────

$(document).on('click', '#anm-submit-btn', function () {
    var id     = $('#anm-id').val();
    var action = $('#anm-action').val();
    var notes  = $('#anm-notes').val();

    var $btn = $(this);
    $btn.prop('disabled', true);
    $('#anm-spinner').removeClass('d-none');
    $('#anm-icon').addClass('d-none');

    $.post(APP_URL + 'salaryRequest/' + action + '/' + id, { admin_notes: notes })
        .done(function (res) {
            $btn.prop('disabled', false);
            $('#anm-spinner').addClass('d-none');
            $('#anm-icon').removeClass('d-none');

            var modal = bootstrap.Modal.getInstance(document.getElementById('admin-notes-modal'));
            if (modal) modal.hide();

            if (res.success) {
                Swal.fire({
                    icon             : 'success',
                    title            : action === 'approve' ? 'Approved!' : 'Declined',
                    text             : res.message,
                    confirmButtonColor: '#4361ee',
                    timer            : 1800,
                    timerProgressBar : true,
                }).then(function () { location.reload(); });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message, confirmButtonColor: '#4361ee' });
            }
        })
        .fail(function () {
            $btn.prop('disabled', false);
            $('#anm-spinner').addClass('d-none');
            $('#anm-icon').removeClass('d-none');
            Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.', confirmButtonColor: '#4361ee' });
        });
});
