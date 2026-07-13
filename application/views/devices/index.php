<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-tablet-alt me-2 text-primary"></i>Biometric Devices</h4>
        <p class="text-muted mb-0" style="font-size:.87rem;">
            ZKTeco terminal punches — live push status and offline file import
        </p>
    </div>
    <button class="btn btn-outline-primary px-3" id="btn-refresh-punches">
        <i class="fas fa-sync-alt me-1"></i> Refresh
    </button>
</div>

<div class="row g-4 mb-4">
    <!-- Registered terminals -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3"><i class="fas fa-wifi me-2 text-primary"></i>Connected Terminals</h6>

                <?php if (empty($devices)): ?>
                <div class="alert alert-light border mb-3" style="font-size:.85rem;">
                    <i class="fas fa-info-circle me-1 text-primary"></i>
                    No terminal has reported yet. On the device, open
                    <strong>Menu &rsaquo; Comm. &rsaquo; Cloud Server Setting (ADMS)</strong> and set the
                    server address to this system's domain (port 80/443, no proxy). The terminal
                    appears here on its first contact.
                </div>
                <?php else: ?>
                <div class="table-responsive mb-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Serial No.</th>
                                <th>Last Seen</th>
                                <th class="text-end">Punches</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $d): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($d['serial_no']) ?></td>
                                <td><?= $d['last_seen'] ? date('M d, Y g:i A', strtotime($d['last_seen'])) : '—' ?></td>
                                <td class="text-end"><?= (int)$d['punch_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="text-muted" style="font-size:.8rem;">
                    <i class="fas fa-link me-1"></i>Push endpoint:
                    <code style="font-size:.78rem;"><?= htmlspecialchars($push_url) ?></code>
                </div>
            </div>
        </div>
    </div>

    <!-- Offline file import -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3"><i class="fas fa-file-import me-2 text-primary"></i>Import Attendance File</h6>
                <p class="text-muted" style="font-size:.83rem;">
                    For sites without internet: on the terminal, insert a USB drive and run
                    <strong>Menu &rsaquo; USB Manager &rsaquo; Download &rsaquo; Attendance Data</strong>,
                    then upload the downloaded file here (usually <code>*_attlog.dat</code>).
                </p>
                <form id="import-form">
                    <div class="mb-3">
                        <input type="file" class="form-control" id="import-file" name="file"
                               accept=".dat,.txt,.csv,.log" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="btn-import">
                        <i class="fas fa-upload me-1"></i> Import Punches
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Unmatched warning -->
<div class="alert alert-warning d-none align-items-center" id="unmatched-alert" style="font-size:.86rem;">
    <div class="flex-grow-1">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <span id="unmatched-text"></span>
        <div class="text-muted mt-1" style="font-size:.78rem;">
            Set the employee's <strong>Device User ID (barcode)</strong> to the terminal's User ID, then retry.
        </div>
    </div>
    <button class="btn btn-sm btn-warning ms-3 text-nowrap" id="btn-reprocess">
        <i class="fas fa-redo me-1"></i> Retry Unmatched
    </button>
</div>

<!-- Imported punches log -->
<div class="card dt-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0"><i class="fas fa-list me-2 text-primary"></i>Imported Punches</h6>
            <span class="text-muted" style="font-size:.8rem;" id="punch-stats"></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="punches-table" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>Punch Time</th>
                        <th>Device User ID</th>
                        <th>Employee</th>
                        <th>Source</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
