<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Scan Station | GH Software Solution</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        html, body { height: 100%; }
        body {
            background: #eef2f7;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            overflow-x: hidden;
        }
        .kiosk-topbar {
            background: #fff;
            border-bottom: 1px solid #dfe5ee;
        }
        #scan-clock { font-variant-numeric: tabular-nums; letter-spacing: .03em; }

        /* Big, touch-friendly scan input */
        #scan-input {
            letter-spacing: .15em;
            font-size: 1.15rem;
            min-height: 3.2rem;
        }
        #btn-scan-enter { min-width: 6rem; font-size: 1.05rem; }

        /* Touch-friendly punch buttons */
        #pp-buttons button { min-height: 3.4rem; }
    </style>
</head>
<body>

<!-- ══════════ Top bar ══════════ -->
<nav class="kiosk-topbar sticky-top px-3 px-lg-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <i class="fas fa-barcode fa-lg text-primary"></i>
        <div>
            <div class="fw-bold">Attendance Scan Station</div>
            <div class="text-muted" style="font-size:.72rem;">GH Software Solution — Entrance Kiosk</div>
        </div>
    </div>

    <div class="text-center">
        <div class="fw-bold fs-4 text-primary" id="scan-clock">--:--:--</div>
        <div class="text-muted" style="font-size:.75rem;"><?= date('l, F j, Y') ?></div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small d-none d-md-inline">
            <i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($kiosk_admin['first_name'] . ' ' . $kiosk_admin['last_name']) ?>
        </span>
        <button class="btn btn-outline-secondary btn-sm" id="btn-fullscreen" title="Toggle Fullscreen">
            <i class="fas fa-expand"></i>
        </button>
        <a href="<?= base_url('dashboard') ?>" class="btn btn-outline-secondary btn-sm" title="Back to Dashboard">
            <i class="fas fa-home"></i>
        </a>
    </div>
</nav>

<!-- ══════════ Main ══════════ -->
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="row g-4">

        <!-- ── Scanner Panel ── -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <i class="fas fa-barcode fa-3x text-primary" id="scan-icon"></i>
                    </div>
                    <h6 class="fw-bold mb-1">Ready to Scan</h6>
                    <p class="text-muted small mb-3">
                        Scan a badge, or type the badge code, username, or employee ID
                        and tap <strong>Enter</strong>.
                    </p>

                    <form id="scan-form" autocomplete="off">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control text-center fw-bold"
                                   id="scan-input" placeholder="| waiting for scan…"
                                   autocomplete="off" autofocus>
                            <button type="submit" class="btn btn-primary fw-semibold" id="btn-scan-enter">
                                <i class="fas fa-arrow-right me-1"></i>Enter
                            </button>
                        </div>
                    </form>

                    <!-- Punch selection panel: shown after a badge scan so the
                         employee picks AM/PM Time In or Time Out. Punches that
                         are already recorded render disabled with their time. -->
                    <div id="punch-panel" class="mt-4 d-none text-start rounded border p-3 bg-white">
                        <div class="fw-bold" id="pp-name" style="font-size:1.05rem;"></div>
                        <div class="text-muted small mb-3" id="pp-hint"></div>
                        <div id="pp-buttons" class="d-grid gap-2" style="grid-template-columns:1fr 1fr;"></div>
                        <div class="text-center mt-2">
                            <button type="button" class="btn btn-sm btn-link text-muted p-0" id="pp-cancel">Cancel</button>
                        </div>
                    </div>

                    <!-- Feedback panel -->
                    <div id="scan-feedback" class="mt-4 d-none rounded p-3 text-start">
                        <div class="d-flex align-items-center gap-3">
                            <i id="sf-icon" class="fas fa-check-circle fa-2x"></i>
                            <div>
                                <div class="fw-bold" id="sf-name" style="font-size:1.05rem;"></div>
                                <div id="sf-detail" class="small"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Today's Log Table ── -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="fw-bold text-muted text-uppercase mb-0" style="font-size:.75rem;letter-spacing:.5px;">
                            Today's Time In / Time Out Logs
                        </h6>
                        <div class="d-flex gap-2">
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="fas fa-sign-in-alt me-1"></i><span id="cnt-in">0</span> Clocked In
                            </span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="fas fa-check-circle me-1"></i><span id="cnt-done">0</span> Completed
                            </span>
                            <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-logs" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" class="align-middle" style="width:44px">#</th>
                                    <th rowspan="2" class="align-middle">Employee</th>
                                    <th colspan="2" class="text-center border-start">AM</th>
                                    <th colspan="2" class="text-center border-start">PM</th>
                                    <th rowspan="2" class="text-center align-middle border-start" style="width:80px">Hours</th>
                                    <th rowspan="2" class="text-center align-middle" style="width:90px">Late (min)</th>
                                    <th rowspan="2" class="text-center align-middle" style="width:105px">Status</th>
                                </tr>
                                <tr>
                                    <th class="text-center border-start small" style="width:95px">Time In</th>
                                    <th class="text-center small" style="width:95px">Time Out</th>
                                    <th class="text-center border-start small" style="width:95px">Time In</th>
                                    <th class="text-center small" style="width:95px">Time Out</th>
                                </tr>
                            </thead>
                            <tbody id="scan-logs-tbody">
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const SCANNER_APP_URL = '<?= base_url() ?>';
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/attendance_scanner.js') ?>"></script>
<script>
document.getElementById('btn-fullscreen').addEventListener('click', function () {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        document.documentElement.requestFullscreen().catch(function () {});
    }
});
</script>
</body>
</html>
