/**
 * Edit Profile modal (topbar user dropdown) — available on every page.
 */
$(function () {
    var $form = $('#edit-profile-form');
    if (!$form.length) return;

    $form.on('submit', function (e) {
        e.preventDefault();

        var newPass     = $form.find('[name="new_password"]').val();
        var confirmPass = $form.find('[name="confirm_password"]').val();
        var currentPass = $form.find('[name="current_password"]').val();

        if (newPass || confirmPass || currentPass) {
            if (!currentPass) {
                Swal.fire('Missing Field', 'Please enter your current password to change it.', 'warning');
                return;
            }
            if (newPass !== confirmPass) {
                Swal.fire('Mismatch', 'New password and confirmation do not match.', 'warning');
                return;
            }
            if (newPass.length < 6) {
                Swal.fire('Too Short', 'New password must be at least 6 characters.', 'warning');
                return;
            }
        }

        var $btn = $('#edit-profile-save-btn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving…');

        $.ajax({
            url: APP_URL + 'profile/update',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        })
        .done(function (res) {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved',
                    text: res.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(function () {
                    location.reload();
                });
            } else {
                Swal.fire('Error', res.message || 'Failed to update profile.', 'error');
            }
        })
        .fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to update profile.';
            Swal.fire('Error', msg, 'error');
        })
        .always(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Changes');
        });
    });

    // Clear password fields whenever the modal is reopened
    $('#editProfileModal').on('hidden.bs.modal', function () {
        $form.find('[name="current_password"], [name="new_password"], [name="confirm_password"]').val('');
    });
});
