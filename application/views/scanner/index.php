<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['station_name']) ?> | Fingerprint Scan Station</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --kiosk-bg: #0e1420;
            --kiosk-card: #17202f;
            --kiosk-border: #26324a;
            --kiosk-accent: #4f8cff;
        }
        html, body { height: 100%; }
        body {
            background: var(--kiosk-bg);
            color: #e8edf5;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            overflow-x: hidden;
        }
        .kiosk-card {
            background: var(--kiosk-card);
            border: 1px solid var(--kiosk-border);
            border-radius: 1rem;
        }
        .kiosk-muted { color: #8fa0b8; }
        .kiosk-topbar {
            border-bottom: 1px solid var(--kiosk-border);
            background: rgba(23, 32, 47, .85);
            backdrop-filter: blur(6px);
        }
        #kiosk-clock { font-variant-numeric: tabular-nums; letter-spacing: .03em; }

        /* Fingerprint pulse */
        .fp-ring {
            width: 170px; height: 170px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto;
            border: 3px solid var(--kiosk-border);
            transition: border-color .3s, box-shadow .3s;
        }
        .fp-ring i { font-size: 4.5rem; color: #5b6b85; transition: color .3s; }
        .fp-ring.listening {
            border-color: var(--kiosk-accent);
            box-shadow: 0 0 0 0 rgba(79, 140, 255, .5);
            animation: fp-pulse 1.8s infinite;
        }
        .fp-ring.listening i { color: var(--kiosk-accent); }
        .fp-ring.ok    { border-color: #2fbf71; box-shadow: 0 0 24px rgba(47,191,113,.35); animation: none; }
        .fp-ring.ok i  { color: #2fbf71; }
        .fp-ring.bad   { border-color: #e35d6a; box-shadow: 0 0 24px rgba(227,93,106,.35); animation: none; }
        .fp-ring.bad i { color: #e35d6a; }
        @keyframes fp-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(79,140,255,.45); }
            70%  { box-shadow: 0 0 0 26px rgba(79,140,255,0); }
            100% { box-shadow: 0 0 0 0 rgba(79,140,255,0); }
        }

        /* Feedback banner */
        #scan-feedback { display: none; border-radius: .9rem; }
        #scan-feedback.fb-in      { background: rgba(47,191,113,.12); border: 1px solid rgba(47,191,113,.5); }
        #scan-feedback.fb-out     { background: rgba(255,171,64,.12);  border: 1px solid rgba(255,171,64,.5); }
        #scan-feedback.fb-done    { background: rgba(79,140,255,.12);  border: 1px solid rgba(79,140,255,.5); }
        #scan-feedback.fb-ignored { background: rgba(143,160,184,.12); border: 1px solid rgba(143,160,184,.5); }
        #scan-feedback.fb-error   { background: rgba(227,93,106,.12);  border: 1px solid rgba(227,93,106,.5); }

        /* Logs table */
        .kiosk-table { --bs-table-bg: transparent; --bs-table-color: #dbe4f0; }
        .kiosk-table thead th {
            color: #8fa0b8; font-size: .72rem; text-transform: uppercase; letter-spacing: .5px;
            border-color: var(--kiosk-border) !important;
        }
        .kiosk-table td { border-color: var(--kiosk-border) !important; }

        /* Lock overlay */
        #lock-overlay {
            position: fixed; inset: 0; z-index: 2000;
            background: radial-gradient(circle at 50% 30%, #16213a 0%, #0b101b 70%);
            display: flex; align-items: center; justify-content: center;
        }
        #lock-overlay .card { max-width: 420px; width: 92%; }

        /* Modals in dark */
        .modal-content { background: var(--kiosk-card); color: #e8edf5; border: 1px solid var(--kiosk-border); }
        .modal-header, .modal-footer { border-color: var(--kiosk-border); }
        .form-control, .form-select {
            background: #101828; border-color: var(--kiosk-border); color: #e8edf5;
        }
        .form-control:focus, .form-select:focus {
            background: #101828; color: #e8edf5;
            border-color: var(--kiosk-accent); box-shadow: 0 0 0 .2rem rgba(79,140,255,.2);
        }
        .form-control::placeholder { color: #5b6b85; }
        .btn-close { filter: invert(1) grayscale(100%); }
        .input-group-text { background: #101828; border-color: var(--kiosk-border); color: #8fa0b8; }
    </style>
</head>
<body>

<!-- ══════════ Lock overlay: admin credentials required ══════════ -->
<div id="lock-overlay" <?= $unlocked ? 'style="display:none;"' : '' ?>>
    <div class="card kiosk-card shadow-lg">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="fp-ring mb-3" style="width:90px;height:90px;">
                    <i class="fas fa-lock" style="font-size:2.2rem;"></i>
                </div>
                <h5 class="fw-bold mb-1">Scan Station Locked</h5>
                <p class="kiosk-muted small mb-0">
                    An <strong>administrator</strong> must sign in to activate this station.
                </p>
            </div>
            <form id="unlock-form" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Admin Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                        <input type="text" class="form-control" id="unlock-username" placeholder="Username" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Admin Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" class="form-control" id="unlock-password" placeholder="Password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold" id="btn-unlock">
                    <i class="fas fa-unlock me-2"></i>Unlock Station
                </button>
                <div class="text-danger small text-center mt-3 d-none" id="unlock-error"></div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════ Top bar ══════════ -->
<nav class="kiosk-topbar sticky-top px-3 px-lg-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <i class="fas fa-fingerprint fa-lg" style="color:var(--kiosk-accent);"></i>
        <div>
            <div class="fw-bold" id="station-name"><?= htmlspecialchars($settings['station_name']) ?></div>
            <div class="kiosk-muted" style="font-size:.72rem;">GH Software Solution — Attendance Kiosk</div>
        </div>
    </div>

    <div class="text-center">
        <div class="fw-bold fs-4" id="kiosk-clock">--:--:--</div>
        <div class="kiosk-muted" style="font-size:.75rem;"><?= date('l, F j, Y') ?></div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span class="kiosk-muted small d-none d-md-inline">
            <i class="fas fa-user-shield me-1"></i><?= $unlocked ? htmlspecialchars($kiosk_admin['name']) : '' ?>
        </span>
        <div class="dropdown">
            <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-1"></i>Admin
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                <li><button class="dropdown-item" id="btn-open-register">
                    <i class="fas fa-fingerprint me-2 text-primary"></i>Register Finger
                </button></li>
                <li><button class="dropdown-item" id="btn-open-manage">
                    <i class="fas fa-list me-2 text-info"></i>Manage Fingerprints
                </button></li>
                <li><button class="dropdown-item" id="btn-open-settings">
                    <i class="fas fa-sliders-h me-2 text-warning"></i>Station Settings
                </button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" id="btn-fullscreen">
                    <i class="fas fa-expand me-2 text-secondary"></i>Toggle Fullscreen
                </button></li>
                <li><button class="dropdown-item text-danger" id="btn-lock">
                    <i class="fas fa-lock me-2"></i>Lock Station
                </button></li>
            </ul>
        </div>
    </div>
</nav>

<!-- ══════════ Main ══════════ -->
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="row g-4">

        <!-- Scan panel -->
        <div class="col-lg-4">
            <div class="kiosk-card p-4 text-center h-100 d-flex flex-column">
                <div class="my-4">
                    <div class="fp-ring" id="fp-ring">
                        <i class="fas fa-fingerprint" id="fp-icon"></i>
                    </div>
                </div>

                <h5 class="fw-bold mb-1" id="scan-title">Scanner Idle</h5>
                <p class="kiosk-muted small mb-4" id="scan-subtitle">
                    Press start, then touch the fingerprint sensor to clock in or out.
                </p>

                <button class="btn btn-primary btn-lg fw-semibold mb-3" id="btn-start-scan">
                    <i class="fas fa-fingerprint me-2"></i>Start Fingerprint Scanner
                </button>

                <div class="alert alert-warning py-2 small d-none text-start" id="webauthn-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Fingerprint scanning needs a fingerprint-capable device (e.g. Windows Hello)
                    and must run on <strong>localhost or HTTPS</strong>. Use the badge input below instead.
                </div>

                <!-- Manual / badge fallback -->
                <?php if ((string)$settings['allow_manual_input'] === '1'): ?>
                <form id="manual-form" autocomplete="off" class="mt-auto">
                    <label class="kiosk-muted small mb-1 d-block">Badge / ID fallback</label>
                    <input type="text" class="form-control text-center fw-bold" id="manual-input"
                           placeholder="scan badge or type ID + Enter" autocomplete="off"
                           style="letter-spacing:.12em;">
                </form>
                <?php endif; ?>

                <!-- Feedback -->
                <div id="scan-feedback" class="mt-3 p-3 text-start">
                    <div class="d-flex align-items-center gap-3">
                        <i id="sf-icon" class="fas fa-check-circle fa-2x"></i>
                        <div>
                            <div class="fw-bold" id="sf-name" style="font-size:1.05rem;"></div>
                            <div id="sf-detail" class="small kiosk-muted"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's log -->
        <div class="col-lg-8">
            <div class="kiosk-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold kiosk-muted text-uppercase mb-0" style="font-size:.75rem;letter-spacing:.5px;">
                        Today's Time In / Time Out
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge text-warning border border-warning" style="background:rgba(255,193,7,.12);">
                            <i class="fas fa-sign-in-alt me-1"></i><span id="cnt-in">0</span> Clocked In
                        </span>
                        <span class="badge text-success border border-success" style="background:rgba(47,191,113,.12);">
                            <i class="fas fa-check-circle me-1"></i><span id="cnt-done">0</span> Completed
                        </span>
                        <button class="btn btn-sm btn-outline-light" id="btn-refresh-logs" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table kiosk-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th>Employee</th>
                                <th class="text-center" style="width:110px">Time In</th>
                                <th class="text-center" style="width:110px">Time Out</th>
                                <th class="text-center" style="width:90px">Hours</th>
                                <th class="text-center" style="width:100px">Late (min)</th>
                                <th class="text-center" style="width:110px">Status</th>
                            </tr>
                        </thead>
                        <tbody id="scan-logs-tbody">
                            <tr><td colspan="7" class="text-center py-4 kiosk-muted">
                                <span class="spinner-border spinner-border-sm me-2"></span>Loading…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ══════════ Register Finger modal ══════════ -->
<div class="modal fade" id="register-modal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">
                    <i class="fas fa-fingerprint me-2 text-primary"></i>Register Employee Fingerprint
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Employee</label>
                    <select class="form-select" id="reg-employee">
                        <option value="">Loading employees…</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Finger Label <span class="kiosk-muted">(optional)</span></label>
                    <input type="text" class="form-control" id="reg-label"
                           placeholder="e.g. Right index" maxlength="100">
                </div>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    After pressing <strong>Capture</strong>, ask the employee to place their finger on
                    this device's fingerprint sensor when prompted.
                </div>
                <div class="text-center mt-3 d-none" id="reg-status">
                    <span class="spinner-border spinner-border-sm text-primary me-2"></span>
                    <span class="small">Waiting for fingerprint…</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary px-4" id="btn-capture-finger">
                    <i class="fas fa-fingerprint me-1"></i>Capture Fingerprint
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════ Manage Fingerprints modal ══════════ -->
<div class="modal fade" id="manage-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">
                    <i class="fas fa-list me-2 text-info"></i>Registered Fingerprints
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table kiosk-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Label</th>
                                <th>Registered</th>
                                <th>Last Used</th>
                                <th class="text-end" style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody id="manage-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════ Settings modal ══════════ -->
<div class="modal fade" id="settings-modal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">
                    <i class="fas fa-sliders-h me-2 text-warning"></i>Station Settings
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Station Name</label>
                    <input type="text" class="form-control" id="set-station-name" maxlength="100">
                    <div class="form-text kiosk-muted">Shown in the top bar, e.g. “Main Entrance”.</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Duplicate Guard (sec)</label>
                        <input type="number" class="form-control" id="set-guard" min="0" max="600">
                        <div class="form-text kiosk-muted">Repeat scans within this window are ignored.</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Log Refresh (sec)</label>
                        <input type="number" class="form-control" id="set-refresh" min="10" max="300">
                        <div class="form-text kiosk-muted">Auto-refresh of the log table.</div>
                    </div>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="set-manual">
                    <label class="form-check-label small" for="set-manual">Allow badge / ID manual input fallback</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="set-sounds">
                    <label class="form-check-label small" for="set-sounds">Play sounds on scan</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="set-autostart">
                    <label class="form-check-label small" for="set-autostart">Auto-restart fingerprint scanner after each scan</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning px-4 fw-semibold" id="btn-save-settings">
                    <i class="fas fa-save me-1"></i>Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const KIOSK_URL      = '<?= base_url() ?>';
    const KIOSK_UNLOCKED = <?= $unlocked ? 'true' : 'false' ?>;
    const KIOSK_SETTINGS = <?= json_encode([
        'duplicate_guard_secs' => (int)$settings['duplicate_guard_secs'],
        'play_sounds'          => (string)$settings['play_sounds'] === '1',
        'auto_start_scanner'   => (string)$settings['auto_start_scanner'] === '1',
        'log_refresh_secs'     => (int)$settings['log_refresh_secs'],
        'allow_manual_input'   => (string)$settings['allow_manual_input'] === '1',
    ]) ?>;
</script>
<script src="<?= base_url('assets/js/scanner_kiosk.js') ?>"></script>
</body>
</html>
