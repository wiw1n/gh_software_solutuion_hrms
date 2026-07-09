'use strict';

/* ================================================================
   notifications.js  — global bell & unread badge
   Requires: APP_URL (set by each page layout)
================================================================ */

var _notifLoaded = false;

var NOTIF_ICON = {
    salary_request  : { icon: 'fa-money-bill-wave', cls: 'text-warning' },
    salary_approved : { icon: 'fa-check-circle',    cls: 'text-success' },
    salary_rejected : { icon: 'fa-times-circle',    cls: 'text-danger'  },
};

function refreshNotifCount() {
    $.get(APP_URL + 'notifications/unread_count')
        .done(function (res) {
            if (!res.success) return;
            var c = res.count;
            if (c > 0) {
                $('#notif-count-badge').text(c > 99 ? '99+' : c).removeClass('d-none');
            } else {
                $('#notif-count-badge').addClass('d-none');
            }
            // Update sidebar pending-requests badge (admins)
            updatePendingBadge();
        });
}

function updatePendingBadge() {
    var $badge = $('#sr-pending-badge');
    if ($badge.length === 0) return;
    $.get(APP_URL + 'salaryRequest/pending_count')
        .done(function (res) {
            if (!res || !res.success) return;
            if (res.count > 0) {
                $badge.text(res.count).removeClass('d-none');
            } else {
                $badge.addClass('d-none');
            }
        });
}

function loadNotifications() {
    $('#notif-list').html('<div class="text-center text-muted py-4 small"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</div>');
    $.get(APP_URL + 'notifications/recent')
        .done(function (res) {
            if (!res.success || res.notifications.length === 0) {
                $('#notif-list').html('<div class="text-center text-muted py-4 small"><i class="fas fa-bell-slash d-block mb-2"></i>No notifications yet.</div>');
                return;
            }
            var html = '';
            res.notifications.forEach(function (n) {
                var cfg  = NOTIF_ICON[n.type] || { icon: 'fa-bell', cls: 'text-primary' };
                var unread = n.is_read == 0;
                var ts   = new Date(n.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                html += '<a href="' + (n.link || '#') + '"'
                      + ' class="d-flex align-items-start gap-2 px-3 py-2 text-decoration-none border-bottom notif-item'
                      + (unread ? ' bg-primary-subtle' : '') + '"'
                      + ' data-id="' + n.id + '">'
                      + '<span class="mt-1"><i class="fas ' + cfg.icon + ' ' + cfg.cls + '"></i></span>'
                      + '<div class="flex-grow-1 overflow-hidden">'
                      + '<div class="small fw-semibold text-dark text-truncate">' + escHtmlNotif(n.title) + '</div>'
                      + (n.message ? '<div class="small text-muted text-truncate">' + escHtmlNotif(n.message) + '</div>' : '')
                      + '<div style="font-size:.7rem;" class="text-muted mt-1">' + ts + '</div>'
                      + '</div>'
                      + (unread ? '<span class="mt-1"><span class="badge bg-primary rounded-pill" style="font-size:.55rem;">New</span></span>' : '')
                      + '</a>';
            });
            $('#notif-list').html(html);
            _notifLoaded = true;
            // Mark all as read after viewing
            $.post(APP_URL + 'notifications/mark_all_read').done(function () {
                $('#notif-count-badge').addClass('d-none');
            });
        });
}

function escHtmlNotif(str) {
    return $('<div>').text(str).html();
}

$(document).ready(function () {
    if (typeof APP_URL === 'undefined') return;

    // Initial count
    refreshNotifCount();

    // Poll every 60 seconds
    setInterval(refreshNotifCount, 60000);

    // Load notifications on bell open
    $('#notif-bell-btn').closest('.dropdown').on('show.bs.dropdown', function () {
        loadNotifications();
    });

    // Mark all read button
    $(document).on('click', '#notif-mark-all-btn', function (e) {
        e.stopPropagation();
        $.post(APP_URL + 'notifications/mark_all_read').done(function () {
            $('#notif-count-badge').addClass('d-none');
            loadNotifications();
        });
    });
});
