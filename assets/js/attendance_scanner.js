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
        scanPhoto = null;
        pendingScan = null;
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

    // ── Camera capture (opens right after a badge scan) ──────────
    // The photo taken here is attached to whichever punch the employee
    // selects next — same proof-of-presence idea as the timesheet
    // clock in/out camera.

    const camModalEl = document.getElementById('scan-cam-modal');
    let camStream    = null;
    let scanPhoto    = null;   // base64 jpeg for the punch about to be recorded
    let pendingScan  = null;   // {code, name, punches, hint} waiting on the camera
    let camConfirmed = false;

    function stopCam() {
        if (camStream) {
            camStream.getTracks().forEach(function (t) { t.stop(); });
            camStream = null;
        }
    }

    function proceedToPunch() {
        if (!pendingScan) return;
        showPunchPanel(pendingScan.code, pendingScan.name, pendingScan.punches, pendingScan.hint);
    }

    function openScanCamera(code, res) {
        pendingScan  = { code: code, name: res.name, punches: res.punches || [], hint: res.message };
        scanPhoto    = null;
        camConfirmed = false;

        $('#cam-name').text(res.name);
        $('#cam-video').removeClass('d-none');
        $('#cam-preview').addClass('d-none').attr('src', '');
        $('#btn-cam-capture').removeClass('d-none');
        $('#btn-cam-retake, #btn-cam-confirm').addClass('d-none');
        $('#cam-hint').text('Look at the camera, then tap Capture.');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            proceedToPunch();   // station has no camera — record without photo
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
        })
            .then(function (stream) {
                camStream = stream;
                document.getElementById('cam-video').srcObject = stream;
                bootstrap.Modal.getOrCreateInstance(camModalEl).show();
            })
            .catch(function () {
                showFeedback('ignored', res.name, 'Camera unavailable — continuing without a photo.');
                proceedToPunch();
            });
    }

    $('#btn-cam-capture').on('click', function () {
        const video  = document.getElementById('cam-video');
        const canvas = document.getElementById('cam-canvas');
        canvas.width  = video.videoWidth  || 640;
        canvas.height = video.videoHeight || 480;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        scanPhoto = canvas.toDataURL('image/jpeg', 0.85);

        $('#cam-preview').attr('src', scanPhoto).removeClass('d-none');
        $('#cam-video').addClass('d-none');
        $(this).addClass('d-none');
        $('#btn-cam-retake, #btn-cam-confirm').removeClass('d-none');
        $('#cam-hint').text('Photo captured. Tap Continue to pick Time In / Time Out.');
    });

    $('#btn-cam-retake').on('click', function () {
        scanPhoto = null;
        $('#cam-preview').addClass('d-none').attr('src', '');
        $('#cam-video').removeClass('d-none');
        $('#btn-cam-capture').removeClass('d-none');
        $('#btn-cam-retake, #btn-cam-confirm').addClass('d-none');
        $('#cam-hint').text('Look at the camera, then tap Capture.');
    });

    $('#btn-cam-confirm').on('click', function () {
        camConfirmed = true;
        bootstrap.Modal.getInstance(camModalEl).hide();
    });

    $(camModalEl).on('hidden.bs.modal', function () {
        stopCam();
        if (camConfirmed) {
            proceedToPunch();
        } else {
            // Cancelled — drop the scan entirely
            scanPhoto   = null;
            pendingScan = null;
            refocusInput();
        }
    });

    // Employee picked a punch — record it (with the captured photo, if any)
    $('#pp-buttons').on('click', 'button[data-punch]', function () {
        const punch = $(this).attr('data-punch');
        if (!panelCode || scanning) return;
        scanning = true;

        $.post(SCANNER_APP_URL + 'attendance/scan', { code: panelCode, punch: punch, photo: scanPhoto || '' })
            .done(function (res) {
                scanPhoto = null; // photo belongs to the punch just recorded
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

        const code = $input.val().trim().toUpperCase();
        $input.val('');
        if (!code || scanning) return;
        scanning = true;

        $.post(SCANNER_APP_URL + 'attendance/scan', { code: code })
            .done(function (res) {
                if (res.action === 'select') {
                    beep(true);
                    const pending = (res.punches || []).some(function (p) { return !p.done; });
                    if (pending) {
                        // Take the proof photo first, then pick the punch
                        openScanCamera(code, res);
                    } else {
                        // Everything already recorded — no photo needed
                        showPunchPanel(code, res.name, res.punches || [], res.message);
                    }
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

    // One row per punch event: employee id, name, action in/out, time, photo
    function renderLogs(logs) {
        const $tbody = $('#scan-logs-tbody');
        $tbody.empty();

        let cntIn = 0, cntOut = 0;

        if (!logs.length) {
            $tbody.append(
                '<tr><td colspan="6" class="text-center py-4 text-muted">' +
                '<i class="fas fa-inbox fa-lg d-block mb-2 opacity-50"></i>' +
                'No logs yet today — waiting for the first scan.</td></tr>'
            );
        }

        logs.forEach(function (r, i) {
            const isIn = r.action === 'in';
            if (isIn) cntIn++; else cntOut++;

            const badge = isIn
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">' +
                  '<i class="fas fa-sign-in-alt me-1"></i>' + esc(r.label || 'Time In') + '</span>'
                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">' +
                  '<i class="fas fa-sign-out-alt me-1"></i>' + esc(r.label || 'Time Out') + '</span>';

            const photo = r.photo
                ? '<a href="' + SCANNER_APP_URL + esc(r.photo) + '" target="_blank" title="View photo">' +
                  '<img src="' + SCANNER_APP_URL + esc(r.photo) + '" alt="photo" ' +
                  'style="width:36px;height:36px;object-fit:cover;border-radius:6px;"></a>'
                : '<span class="text-muted">—</span>';

            $tbody.append(
                '<tr>' +
                '<td class="text-muted small">' + (i + 1) + '</td>' +
                '<td class="fw-semibold">' + esc(r.employee_id || '—') + '</td>' +
                '<td><div class="fw-semibold">' + esc(r.first_name + ' ' + r.last_name) + '</div>' +
                    '<div class="text-muted" style="font-size:.72rem;">' + esc(r.job_role_name || '') + '</div></td>' +
                '<td class="text-center">' + badge + '</td>' +
                '<td class="text-center"><span class="' + (isIn ? 'text-success' : 'text-danger') +
                    ' fw-semibold">' + fmtTime(r.time) + '</span></td>' +
                '<td class="text-center">' + photo + '</td>' +
                '</tr>'
            );
        });

        $('#cnt-in').text(cntIn);
        $('#cnt-out').text(cntOut);
    }

    $('#btn-refresh-logs').on('click', loadLogs);

    loadLogs();
    setInterval(loadLogs, 30000); // keep the table fresh on an idle kiosk
});
