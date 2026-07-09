/**
 * Entrance Fingerprint Scan Station (kiosk)
 *
 * - Admin unlocks the station with username + password (admin roles only).
 * - Employees clock in/out with a fingerprint via WebAuthn
 *   (Windows Hello / built-in FIDO2 fingerprint readers).
 * - Register Finger / Manage / Settings re-ask for the admin password.
 * - Optional badge/ID manual input as a fallback.
 */
$(document).ready(function () {

    'use strict';

    // ── Base64URL <-> ArrayBuffer helpers ─────────────────────────

    function bufToB64url(buf) {
        const bytes = new Uint8Array(buf);
        let str = '';
        bytes.forEach(b => str += String.fromCharCode(b));
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function b64urlToBuf(b64url) {
        const b64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
        const pad = b64.length % 4 ? '='.repeat(4 - (b64.length % 4)) : '';
        const bin = atob(b64 + pad);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes.buffer;
    }

    // ── Live clock ────────────────────────────────────────────────

    function tickClock() {
        const now = new Date();
        let h = now.getHours();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        const pad = n => String(n).padStart(2, '0');
        $('#kiosk-clock').text(pad(h) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + ampm);
    }
    tickClock();
    setInterval(tickClock, 1000);

    // ── WebAuthn availability ─────────────────────────────────────

    const webauthnOk = !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext);
    if (!webauthnOk) {
        $('#webauthn-warning').removeClass('d-none');
        $('#btn-start-scan').prop('disabled', true);
    }

    // ── Sounds ────────────────────────────────────────────────────

    function beep(ok) {
        if (!KIOSK_SETTINGS.play_sounds) return;
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
        } catch (e) { /* audio unavailable */ }
    }

    // ── Feedback panel ────────────────────────────────────────────

    const FB = {
        in:      { cls: 'fb-in',      icon: 'fa-sign-in-alt text-success' },
        out:     { cls: 'fb-out',     icon: 'fa-sign-out-alt text-warning' },
        done:    { cls: 'fb-done',    icon: 'fa-check-double text-primary' },
        ignored: { cls: 'fb-ignored', icon: 'fa-hand-paper text-secondary' },
        error:   { cls: 'fb-error',   icon: 'fa-times-circle text-danger' },
    };
    let feedbackTimer = null;

    function showFeedback(action, name, detail) {
        const fb = FB[action] || FB.error;
        $('#scan-feedback').removeClass('fb-in fb-out fb-done fb-ignored fb-error')
            .addClass(fb.cls).show();
        $('#sf-icon').removeClass().addClass('fas fa-2x ' + fb.icon);
        $('#sf-name').text(name);
        $('#sf-detail').text(detail);
        clearTimeout(feedbackTimer);
        feedbackTimer = setTimeout(() => $('#scan-feedback').fadeOut(300), 8000);
    }

    function flashRing(state) {
        const $ring = $('#fp-ring');
        $ring.removeClass('listening ok bad').addClass(state);
        setTimeout(() => {
            $ring.removeClass('ok bad');
            if (scanning) $ring.addClass('listening');
        }, 2200);
    }

    function describeResult(res) {
        if (res.action === 'in') {
            return 'Clocked IN at ' + res.time +
                (res.tardiness > 0 ? ' — ' + Math.round(res.tardiness) + ' min late' : ' — on time');
        }
        if (res.action === 'out') {
            return 'Clocked OUT at ' + res.time + ' — ' + res.hours.toFixed(2) + ' hrs' +
                (res.overtime > 0 ? ' (' + res.overtime.toFixed(2) + ' OT)' : '');
        }
        return res.message;
    }

    // ── Station unlock / lock ─────────────────────────────────────

    $('#unlock-form').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-unlock').prop('disabled', true);
        $('#unlock-error').addClass('d-none');

        $.post(KIOSK_URL + 'scanner/unlock', {
            username: $('#unlock-username').val().trim(),
            password: $('#unlock-password').val(),
        })
        .done(() => location.reload())
        .fail(xhr => {
            const res = xhr.responseJSON || {};
            $('#unlock-error').removeClass('d-none').text(res.message || 'Unlock failed.');
            $btn.prop('disabled', false);
        });
    });

    $('#btn-lock').on('click', function () {
        Swal.fire({
            title: 'Lock the station?',
            text: 'Scanning stops until an administrator unlocks it again.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Lock Station',
            confirmButtonColor: '#dc3545',
        }).then(r => {
            if (!r.isConfirmed) return;
            $.post(KIOSK_URL + 'scanner/lock').always(() => location.reload());
        });
    });

    function handleLocked(xhr) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.locked) {
            location.reload();
            return true;
        }
        return false;
    }

    // ── Management gate: re-ask admin password when the server says so ──

    function mgmtRequest(url, data, method) {
        method = method || 'GET';
        const doCall = () => $.ajax({ url: KIOSK_URL + url, method: method, data: data || {} });

        return doCall().catch(function (xhr) {
            if (handleLocked(xhr)) return $.Deferred().reject(xhr);
            if (!xhr.responseJSON || !xhr.responseJSON.need_password) {
                return $.Deferred().reject(xhr);
            }
            // Ask for the admin password, verify, then retry once.
            return Swal.fire({
                title: 'Admin Password Required',
                input: 'password',
                inputPlaceholder: 'Enter admin password',
                inputAttributes: { autocomplete: 'current-password' },
                showCancelButton: true,
                confirmButtonText: 'Verify',
                allowOutsideClick: false,
                preConfirm: pwd => {
                    if (!pwd) {
                        Swal.showValidationMessage('Password is required.');
                        return false;
                    }
                    return $.post(KIOSK_URL + 'scanner/verify_password', { password: pwd })
                        .catch(x => {
                            Swal.showValidationMessage((x.responseJSON || {}).message || 'Verification failed.');
                            return false; // keep the dialog open
                        });
                },
            }).then(r => {
                if (!r.isConfirmed) return $.Deferred().reject('cancelled');
                return doCall();
            });
        });
    }

    // ── Fingerprint scan loop ─────────────────────────────────────

    let scanning = false;       // loop active
    let scanAbort = null;       // AbortController of the pending get()
    let restartTimer = null;

    function setScanUi(active) {
        $('#fp-ring').toggleClass('listening', active);
        $('#scan-title').text(active ? 'Touch the Sensor' : 'Scanner Idle');
        $('#scan-subtitle').text(active
            ? 'Place your registered finger on the fingerprint sensor.'
            : 'Press start, then touch the fingerprint sensor to clock in or out.');
        $('#btn-start-scan').toggleClass('d-none', active);
    }

    function stopScanner() {
        scanning = false;
        clearTimeout(restartTimer);
        if (scanAbort) { scanAbort.abort(); scanAbort = null; }
        setScanUi(false);
    }

    function scheduleNextScan(delayMs) {
        clearTimeout(restartTimer);
        if (!scanning) return;
        restartTimer = setTimeout(scanOnce, delayMs);
    }

    function startScanner() {
        if (!webauthnOk || scanning) return;
        scanning = true;
        setScanUi(true);
        scanOnce();
    }

    function scanOnce() {
        if (!scanning) return;

        $.post(KIOSK_URL + 'scanner/challenge')
            .done(function (res) {
                if (!scanning) return;

                scanAbort = new AbortController();
                navigator.credentials.get({
                    publicKey: {
                        challenge: b64urlToBuf(res.challenge),
                        rpId: res.rp_id,
                        timeout: 120000,
                        userVerification: 'required',
                        allowCredentials: [], // discoverable: employee is identified by the credential
                    },
                    signal: scanAbort.signal,
                })
                .then(assertion => {
                    scanAbort = null;
                    return $.post(KIOSK_URL + 'scanner/scan_verify', {
                        credential_id:      bufToB64url(assertion.rawId),
                        client_data_json:   bufToB64url(assertion.response.clientDataJSON),
                        authenticator_data: bufToB64url(assertion.response.authenticatorData),
                        signature:          bufToB64url(assertion.response.signature),
                    });
                })
                .then(res2 => {
                    showFeedback(res2.action, res2.name, describeResult(res2));
                    flashRing('ok');
                    beep(true);
                    loadLogs();
                    if (!KIOSK_SETTINGS.auto_start_scanner) {
                        stopScanner();
                    } else {
                        scheduleNextScan(2500);
                    }
                })
                .catch(err => {
                    scanAbort = null;
                    if (!scanning) return;

                    if (err && err.name === 'NotAllowedError') {
                        // Prompt timed out or was dismissed — quietly listen again.
                        scheduleNextScan(800);
                        return;
                    }
                    if (err && err.responseJSON) { // server rejected the scan
                        if (handleLocked(err)) return;
                        showFeedback('error', 'Scan failed', err.responseJSON.message || 'Fingerprint not recognized.');
                    } else {
                        showFeedback('error', 'Scanner error', (err && err.message) || 'Fingerprint reading failed.');
                    }
                    flashRing('bad');
                    beep(false);
                    scheduleNextScan(3000);
                });
            })
            .fail(function (xhr) {
                if (handleLocked(xhr)) return;
                if (scanning) scheduleNextScan(4000);
            });
    }

    $('#btn-start-scan').on('click', startScanner);

    if (KIOSK_UNLOCKED && webauthnOk && KIOSK_SETTINGS.auto_start_scanner) {
        // Browsers allow WebAuthn without a click on most kiosk setups;
        // if the call is rejected, the visible Start button remains.
        startScanner();
    }

    // ── Manual / badge fallback ───────────────────────────────────

    $('#manual-form').on('submit', function (e) {
        e.preventDefault();
        const code = $('#manual-input').val().trim();
        $('#manual-input').val('');
        if (!code) return;

        $.post(KIOSK_URL + 'scanner/scan_code', { code: code })
            .done(res => {
                showFeedback(res.action, res.name, describeResult(res));
                flashRing('ok');
                beep(true);
                loadLogs();
            })
            .fail(xhr => {
                if (handleLocked(xhr)) return;
                const res = xhr.responseJSON || {};
                showFeedback('error', 'Scan failed', res.message || 'Unknown code.');
                flashRing('bad');
                beep(false);
            });
    });

    // ── Today's logs ──────────────────────────────────────────────

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
        if (!KIOSK_UNLOCKED) return;
        $.getJSON(KIOSK_URL + 'scanner/today_logs')
            .done(res => { if (res.success) renderLogs(res.logs || []); })
            .fail(xhr => handleLocked(xhr));
    }

    function renderLogs(logs) {
        const $tbody = $('#scan-logs-tbody').empty();
        let cntIn = 0, cntDone = 0;

        if (!logs.length) {
            $tbody.append(
                '<tr><td colspan="7" class="text-center py-4 kiosk-muted">' +
                '<i class="fas fa-inbox fa-lg d-block mb-2 opacity-50"></i>' +
                'No logs yet today — waiting for the first scan.</td></tr>'
            );
        }

        logs.forEach(function (r, i) {
            const done = !!r.time_out;
            if (done) cntDone++; else if (r.time_in) cntIn++;

            const late = parseFloat(r.tardiness) || 0;
            const statusBadge = done
                ? '<span class="badge text-success border border-success" style="background:rgba(47,191,113,.12);">Completed</span>'
                : '<span class="badge text-warning border border-warning" style="background:rgba(255,193,7,.12);">In Progress</span>';

            $tbody.append(
                '<tr>' +
                '<td class="kiosk-muted small">' + (i + 1) + '</td>' +
                '<td><div class="fw-semibold">' + esc(r.first_name + ' ' + r.last_name) + '</div>' +
                    '<div class="kiosk-muted" style="font-size:.72rem;">' + esc(r.job_role_name || '') + '</div></td>' +
                '<td class="text-center">' +
                    (r.time_in ? '<span class="text-success fw-semibold">' + fmtTime(r.time_in) + '</span>' : '<span class="kiosk-muted">—</span>') +
                '</td>' +
                '<td class="text-center">' +
                    (r.time_out ? '<span class="text-warning fw-semibold">' + fmtTime(r.time_out) + '</span>' : '<span class="kiosk-muted">—</span>') +
                '</td>' +
                '<td class="text-center">' +
                    (r.total_hours ? parseFloat(r.total_hours).toFixed(2) : '<span class="kiosk-muted">—</span>') +
                '</td>' +
                '<td class="text-center">' +
                    (late > 0 ? '<span class="text-danger">' + Math.round(late) + '</span>' : '<span class="kiosk-muted">—</span>') +
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
    setInterval(loadLogs, Math.max(10, KIOSK_SETTINGS.log_refresh_secs) * 1000);

    // ── Register Finger (password-gated) ──────────────────────────

    const registerModal = new bootstrap.Modal('#register-modal');
    const manageModal   = new bootstrap.Modal('#manage-modal');
    const settingsModal = new bootstrap.Modal('#settings-modal');

    $('#btn-open-register').on('click', function () {
        stopScanner(); // a pending fingerprint prompt would block registration
        mgmtRequest('scanner/employees').then(function (res) {
            const $sel = $('#reg-employee').empty()
                .append('<option value="">— Select employee —</option>');
            (res.employees || []).forEach(e => {
                const fingers = parseInt(e.finger_count, 10) || 0;
                $sel.append(
                    $('<option>').val(e.id).text(
                        e.first_name + ' ' + e.last_name +
                        (e.job_role_name ? ' — ' + e.job_role_name : '') +
                        (fingers ? ' (' + fingers + ' finger' + (fingers > 1 ? 's' : '') + ')' : '')
                    )
                );
            });
            $('#reg-label').val('');
            $('#reg-status').addClass('d-none');
            registerModal.show();
        }).catch(() => {});
    });

    $('#btn-capture-finger').on('click', function () {
        const userId = $('#reg-employee').val();
        if (!userId) {
            Swal.fire('Select an employee', 'Choose whose fingerprint to register.', 'info');
            return;
        }
        if (!webauthnOk) {
            Swal.fire('Not available', 'This device/browser does not support fingerprint capture.', 'error');
            return;
        }

        const $btn = $(this).prop('disabled', true);
        $('#reg-status').removeClass('d-none');

        Promise.resolve(mgmtRequest('scanner/register_options', { user_id: userId }, 'POST'))
            .then(function (opts) {
                return navigator.credentials.create({
                    publicKey: {
                        challenge: b64urlToBuf(opts.challenge),
                        rp: opts.rp,
                        user: {
                            id: b64urlToBuf(opts.user.id_b64),
                            name: opts.user.name,
                            displayName: opts.user.displayName,
                        },
                        pubKeyCredParams: [
                            { type: 'public-key', alg: -7 },   // ES256
                            { type: 'public-key', alg: -257 }, // RS256
                        ],
                        authenticatorSelection: {
                            authenticatorAttachment: 'platform',
                            residentKey: 'required',
                            requireResidentKey: true,
                            userVerification: 'required',
                        },
                        excludeCredentials: (opts.exclude_credentials || []).map(id => ({
                            type: 'public-key',
                            id: b64urlToBuf(id),
                        })),
                        timeout: 90000,
                        attestation: 'none',
                    },
                });
            })
            .then(function (cred) {
                return $.post(KIOSK_URL + 'scanner/register_verify', {
                    user_id:            userId,
                    label:              $('#reg-label').val().trim(),
                    client_data_json:   bufToB64url(cred.response.clientDataJSON),
                    attestation_object: bufToB64url(cred.response.attestationObject),
                });
            })
            .then(function (res) {
                registerModal.hide();
                Swal.fire({ icon: 'success', title: 'Registered!', text: res.message, timer: 2500, showConfirmButton: false });
            })
            .catch(function (err) {
                if (err === 'cancelled') return;
                let msg = 'Fingerprint capture failed.';
                if (err && err.name === 'InvalidStateError') {
                    msg = 'A fingerprint for this employee is already registered on this device.';
                } else if (err && err.name === 'NotAllowedError') {
                    msg = 'Capture was cancelled or timed out.';
                } else if (err && err.responseJSON) {
                    msg = err.responseJSON.message || msg;
                } else if (err && err.message) {
                    msg = err.message;
                }
                Swal.fire('Registration failed', msg, 'error');
            })
            .finally(function () {
                $btn.prop('disabled', false);
                $('#reg-status').addClass('d-none');
            });
    });

    // ── Manage fingerprints (password-gated) ──────────────────────

    function loadManageList() {
        return mgmtRequest('scanner/fingerprints').then(function (res) {
            const $tbody = $('#manage-tbody').empty();
            const rows = res.fingerprints || [];
            if (!rows.length) {
                $tbody.append('<tr><td colspan="5" class="text-center py-4 kiosk-muted">No fingerprints registered yet.</td></tr>');
            }
            rows.forEach(function (f) {
                $tbody.append(
                    '<tr>' +
                    '<td><div class="fw-semibold">' + esc(f.first_name + ' ' + f.last_name) + '</div>' +
                        '<div class="kiosk-muted" style="font-size:.72rem;">' + esc(f.username) + '</div></td>' +
                    '<td>' + (f.label ? esc(f.label) : '<span class="kiosk-muted">—</span>') + '</td>' +
                    '<td class="small">' + esc((f.created_at || '').substring(0, 10)) + '</td>' +
                    '<td class="small">' + (f.last_used_at ? esc(f.last_used_at.substring(0, 16)) : '<span class="kiosk-muted">never</span>') + '</td>' +
                    '<td class="text-end"><button class="btn btn-sm btn-outline-danger btn-del-finger" data-id="' + f.id + '" data-name="' + esc(f.first_name + ' ' + f.last_name) + '">' +
                        '<i class="fas fa-trash"></i></button></td>' +
                    '</tr>'
                );
            });
        });
    }

    $('#btn-open-manage').on('click', function () {
        stopScanner();
        loadManageList().then(() => manageModal.show()).catch(() => {});
    });

    $(document).on('click', '.btn-del-finger', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Remove fingerprint?',
            text: name + ' will no longer be able to clock in by fingerprint with this record.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Remove',
            confirmButtonColor: '#dc3545',
        }).then(r => {
            if (!r.isConfirmed) return;
            mgmtRequest('scanner/delete_fingerprint/' + id, {}, 'POST')
                .then(() => loadManageList())
                .catch(() => {});
        });
    });

    // ── Settings (password-gated) ─────────────────────────────────

    $('#btn-open-settings').on('click', function () {
        stopScanner();
        mgmtRequest('scanner/get_settings').then(function (res) {
            const s = res.settings || {};
            $('#set-station-name').val(s.station_name || '');
            $('#set-guard').val(s.duplicate_guard_secs || 60);
            $('#set-refresh').val(s.log_refresh_secs || 30);
            $('#set-manual').prop('checked', String(s.allow_manual_input) === '1');
            $('#set-sounds').prop('checked', String(s.play_sounds) === '1');
            $('#set-autostart').prop('checked', String(s.auto_start_scanner) === '1');
            settingsModal.show();
        }).catch(() => {});
    });

    $('#btn-save-settings').on('click', function () {
        mgmtRequest('scanner/save_settings', {
            station_name:         $('#set-station-name').val().trim(),
            duplicate_guard_secs: $('#set-guard').val(),
            log_refresh_secs:     $('#set-refresh').val(),
            allow_manual_input:   $('#set-manual').is(':checked') ? 1 : '',
            play_sounds:          $('#set-sounds').is(':checked') ? 1 : '',
            auto_start_scanner:   $('#set-autostart').is(':checked') ? 1 : '',
        }, 'POST')
        .then(function (res) {
            Swal.fire({ icon: 'success', title: 'Saved', text: res.message, timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        })
        .catch(() => {});
    });

    // ── Fullscreen ────────────────────────────────────────────────

    $('#btn-fullscreen').on('click', function () {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            document.documentElement.requestFullscreen().catch(() => {});
        }
    });

    // Resume scanning when a management modal closes
    $('#register-modal, #manage-modal, #settings-modal').on('hidden.bs.modal', function () {
        if (KIOSK_UNLOCKED && webauthnOk && KIOSK_SETTINGS.auto_start_scanner && !scanning) {
            startScanner();
        }
    });
});
