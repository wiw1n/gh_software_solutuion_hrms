/**
 * Attendance Scan Station
 * A barcode scanner behaves like a keyboard: it types the badge code and
 * presses Enter. The input stays focused so scans are always captured.
 */
$(document).ready(function () {

    const $input    = $('#scan-input');
    const $feedback = $('#scan-feedback');
    let   feedbackTimer = null;
    let   scanning      = false;

    // ── Live clock ───────────────────────────────────────────────

    function tickClock() {
        const now = new Date();
        let h = now.getHours();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        const pad = n => String(n).padStart(2, '0');
        $('#scan-clock').text(pad(h) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + ampm);
    }
    tickClock();
    setInterval(tickClock, 1000);

    // ── Keep the scanner input focused ───────────────────────────
    // On touch devices (tablet kiosk) skip the aggressive refocus so the
    // on-screen keyboard doesn't keep popping up — the Enter button is
    // used to submit instead.

    const isTouch = window.matchMedia('(pointer: coarse)').matches;

    if (!isTouch) {
        $input.trigger('focus');

        $input.on('blur', function () {
            setTimeout(() => $input.trigger('focus'), 150);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('button, a, input, select, textarea').length) {
                $input.trigger('focus');
            }
        });
    }

    // ── Feedback panel + beep ─────────────────────────────────────

    const STYLES = {
        in:      { cls: 'bg-success-subtle border border-success-subtle text-success',    icon: 'fa-sign-in-alt' },
        out:     { cls: 'bg-danger-subtle border border-danger-subtle text-danger',       icon: 'fa-sign-out-alt' },
        done:    { cls: 'bg-info-subtle border border-info-subtle text-info',             icon: 'fa-check-double' },
        ignored: { cls: 'bg-secondary-subtle border border-secondary-subtle text-secondary', icon: 'fa-hand-paper' },
        error:   { cls: 'bg-danger-subtle border border-danger-subtle text-danger',       icon: 'fa-times-circle' },
    };

    function beep(ok) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = ok ? 1200 : 300;
            gain.gain.value = 0.08;
            osc.start();
            osc.stop(ctx.currentTime + (ok ? 0.12 : 0.35));
            osc.onended = () => ctx.close();
        } catch (e) { /* audio not available — ignore */ }
    }

    function showFeedback(action, name, detail) {
        const style = STYLES[action] || STYLES.error;

        $feedback.removeClass().addClass('mt-4 rounded p-3 text-start ' + style.cls);
        $('#sf-icon').removeClass().addClass('fas fa-2x ' + style.icon);
        $('#sf-name').text(name);
        $('#sf-detail').text(detail);

        clearTimeout(feedbackTimer);
        feedbackTimer = setTimeout(() => $feedback.addClass('d-none'), 8000);
    }

    // ── Punch selection panel (AM/PM Time In / Time Out) ─────────

    const PUNCH_LABELS = {
        time_in: 'Time In',    time_out: 'Time Out',
        am_in:   'AM Time In', am_out:   'AM Time Out',
        pm_in:   'PM Time In', pm_out:   'PM Time Out',
    };

    const $panel     = $('#punch-panel');
    let   panelCode  = null;   // badge code of the employee shown in the panel
    let   panelTimer = null;

    function hidePunchPanel() {
        clearTimeout(panelTimer);
        panelCode = null;
        $panel.addClass('d-none');
    }

    function renderPunchButtons(punches) {
        const $btns = $('#pp-buttons').empty();

        punches.forEach(function (p) {
            const isIn  = p.punch.slice(-3) === '_in';
            const label = PUNCH_LABELS[p.punch] || p.punch;
            const $btn  = $('<button type="button" class="btn fw-semibold py-2"></button>')
                .attr('data-punch', p.punch);

            if (p.done) {
                // Already recorded — keep visible but disabled, showing the time
                $btn.addClass(isIn ? 'btn-success' : 'btn-danger')
                    .prop('disabled', true)
                    .html('<i class="fas fa-check me-1"></i>' + label +
                          '<div class="small fw-normal">' + fmtTime(p.time) + '</div>');
            } else {
                $btn.addClass(isIn ? 'btn-outline-success' : 'btn-outline-danger')
                    .prop('disabled', !p.allowed)
                    .html('<i class="fas ' + (isIn ? 'fa-sign-in-alt' : 'fa-sign-out-alt') + ' me-1"></i>' + label);
            }
            $btns.append($btn);
        });
    }

    function showPunchPanel(code, name, punches, hint) {
        panelCode = code;
        $('#pp-name').text(name);
        $('#pp-hint').text(hint || 'Select which time to record:');
        renderPunchButtons(punches);
        $panel.removeClass('d-none');

        // Auto-close so the kiosk is ready for the next employee
        clearTimeout(panelTimer);
        panelTimer = setTimeout(hidePunchPanel, 30000);
    }

    function refocusInput() {
        if (!isTouch) $input.trigger('focus');
    }

    $('#pp-cancel').on('click', function () {
        hidePunchPanel();
        refocusInput();
    });

    function scanDetail(res) {
        if (res.action === 'in') {
            return (res.label || 'TIME IN') + ' recorded at ' + res.time +
                   (res.tardiness > 0 ? ' — ' + Math.round(res.tardiness) + ' min late' : ' — on time');
        }
        if (res.action === 'out') {
            return (res.label || 'TIME OUT') + ' recorded at ' + res.time + ' — ' + res.hours.toFixed(2) + ' hrs' +
                   (res.overtime > 0 ? ' (' + res.overtime.toFixed(2) + ' OT)' : '');
        }
        return res.message;
    }

    // Employee picked a punch — record it
    $('#pp-buttons').on('click', 'button[data-punch]', function () {
        const punch = $(this).attr('data-punch');
        if (!panelCode || scanning) return;
        scanning = true;

        $.post(SCANNER_APP_URL + 'attendance/scan', { code: panelCode, punch: punch })
            .done(function (res) {
                showFeedback(res.action, res.name, scanDetail(res));
                beep(true);
                if (res.punches) {
                    // Re-render so the punch just recorded shows disabled
                    renderPunchButtons(res.punches);
                    clearTimeout(panelTimer);
                    panelTimer = setTimeout(hidePunchPanel, 8000);
                } else {
                    hidePunchPanel();
                }
                loadLogs();
            })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                showFeedback('error', res.name || 'Punch failed', res.message || 'Could not record — try again.');
                beep(false);
                if (res.punches) renderPunchButtons(res.punches);
            })
            .always(function () {
                scanning = false;
                refocusInput();
            });
    });

    // ── Process a scan ────────────────────────────────────────────

    $('#scan-form').on('submit', function (e) {
        e.preventDefault();

        const code = $input.val().trim();
        $input.val('');
        if (!code || scanning) return;
        scanning = true;

        $.post(SCANNER_APP_URL + 'attendance/scan', { code: code })
            .done(function (res) {
                if (res.action === 'select') {
                    beep(true);
                    showPunchPanel(code, res.name, res.punches || [], res.message);
                    return;
                }
                showFeedback(res.action, res.name, scanDetail(res));
                beep(true);
                loadLogs();
            })
            .fail(function (xhr) {
                const res = xhr.responseJSON || {};
                showFeedback('error', 'Scan failed', res.message || 'Unknown code — check the badge and try again.');
                beep(false);
            })
            .always(function () {
                scanning = false;
                refocusInput();
            });
    });

    // ── Today's logs table ────────────────────────────────────────

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function fmtTime(t) {
        if (!t) return null;
        const parts = t.split(':');
        let h = parseInt(parts[0], 10);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return String(h).padStart(2, '0') + ':' + parts[1] + ' ' + ampm;
    }

    function loadLogs() {
        $.getJSON(SCANNER_APP_URL + 'attendance/today_logs')
            .done(function (res) {
                if (!res.success) return;
                renderLogs(res.logs || []);
            });
    }

    function renderLogs(logs) {
        const $tbody = $('#scan-logs-tbody');
        $tbody.empty();

        let cntIn = 0, cntDone = 0;

        if (!logs.length) {
            $tbody.append(
                '<tr><td colspan="9" class="text-center py-4 text-muted">' +
                '<i class="fas fa-inbox fa-lg d-block mb-2 opacity-50"></i>' +
                'No logs yet today — waiting for the first scan.</td></tr>'
            );
        }

        function timeCell(t, inOut) {
            if (!t) return '<td class="text-center"><span class="text-muted">—</span></td>';
            const cls = inOut === 'in' ? 'text-success' : 'text-danger';
            return '<td class="text-center"><span class="' + cls + ' fw-semibold">' + fmtTime(t) + '</span></td>';
        }

        logs.forEach(function (r, i) {
            // Legacy/single-mode records have no AM/PM punches — show their
            // plain time_in/time_out in the AM columns instead.
            const hasSessions = r.am_time_in || r.am_time_out || r.pm_time_in || r.pm_time_out;
            const amIn  = hasSessions ? r.am_time_in  : r.time_in;
            const amOut = hasSessions ? r.am_time_out : r.time_out;
            const pmIn  = hasSessions ? r.pm_time_in  : null;
            const pmOut = hasSessions ? r.pm_time_out : null;

            const done = hasSessions ? !!r.pm_time_out : !!r.time_out;
            if (done) cntDone++; else if (r.time_in) cntIn++;

            const late = parseFloat(r.tardiness) || 0;
            const statusBadge = done
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Completed</span>'
                : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">In Progress</span>';

            $tbody.append(
                '<tr>' +
                '<td class="text-muted small">' + (i + 1) + '</td>' +
                '<td><div class="fw-semibold">' + esc(r.first_name + ' ' + r.last_name) + '</div>' +
                    '<div class="text-muted" style="font-size:.72rem;">' + esc(r.job_role_name || '') + '</div></td>' +
                timeCell(amIn, 'in') +
                timeCell(amOut, 'out') +
                timeCell(pmIn, 'in') +
                timeCell(pmOut, 'out') +
                '<td class="text-center">' +
                    (r.total_hours ? parseFloat(r.total_hours).toFixed(2) : '<span class="text-muted">—</span>') +
                '</td>' +
                '<td class="text-center">' +
                    (late > 0 ? '<span class="text-danger">' + Math.round(late) + '</span>' : '<span class="text-muted">—</span>') +
                '</td>' +
                '<td class="text-center">' + statusBadge + '</td>' +
                '</tr>'
            );
        });

        $('#cnt-in').text(cntIn);
        $('#cnt-done').text(cntDone);
    }

    $('#btn-refresh-logs').on('click', loadLogs);

    loadLogs();
    setInterval(loadLogs, 30000); // keep the table fresh on an idle kiosk
});
