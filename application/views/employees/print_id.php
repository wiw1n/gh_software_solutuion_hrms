<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$full_name = $employee['first_name'] . ' ' . $employee['last_name'];
$initials  = strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1));
$emp_code  = $employee['employee_id'] ?? '';
$job_role  = $employee['job_role_name'] ?: 'Employee';
$hired     = !empty($employee['date_hired']) ? date('M d, Y', strtotime($employee['date_hired'])) : '—';
$ec        = $emergency_contact ?: null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ID Card — <?= htmlspecialchars($full_name) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    :root {
        --brand:      #0d6efd;
        --brand-dark: #0a3f8f;
        --ink:        #1c2434;
        --muted:      #6b7480;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: "Segoe UI", Arial, sans-serif;
        background: #e9edf2;
        color: var(--ink);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 30px 16px;
    }

    /* ---------- Toolbar (screen only) ---------- */
    .toolbar {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 26px;
    }
    .toolbar button {
        border: 0;
        border-radius: 6px;
        padding: 10px 22px;
        font-size: .95rem;
        font-weight: 600;
        cursor: pointer;
        background: var(--brand);
        color: #fff;
    }
    .toolbar button.secondary { background: #6c757d; }

    .cards { display: flex; gap: 26px; flex-wrap: wrap; justify-content: center; }

    /* ---------- Card (CR80: 3.375in x 2.125in) ---------- */
    .id-card {
        width: 3.375in;
        height: 2.125in;
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(0,0,0,.18);
        position: relative;
        display: flex;
        flex-direction: column;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ----- Front ----- */
    .card-head {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff;
        padding: 7px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .card-head .logo {
        width: 22px; height: 22px;
        border-radius: 5px;
        background: rgba(255,255,255,.2);
        display: flex; align-items: center; justify-content: center;
        font-size: .7rem;
        flex-shrink: 0;
    }
    .card-head .co-name { font-size: .62rem; font-weight: 700; letter-spacing: .5px; line-height: 1.15; }
    .card-head .co-sub  { font-size: .45rem; letter-spacing: 2.5px; opacity: .85; }
    .card-head .tag {
        margin-left: auto;
        font-size: .42rem;
        letter-spacing: 1.5px;
        border: 1px solid rgba(255,255,255,.6);
        border-radius: 3px;
        padding: 2px 5px;
    }

    .card-body-front {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
    }
    .avatar {
        width: .82in; height: .82in;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--brand), var(--brand-dark));
        color: #fff;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid #dbe4f0;
        flex-shrink: 0;
    }
    .who { flex: 1; min-width: 0; }
    .who .name {
        font-size: .8rem;
        font-weight: 700;
        line-height: 1.15;
        text-transform: uppercase;
    }
    .who .role { font-size: .58rem; color: var(--brand-dark); font-weight: 600; margin-top: 2px; }
    .who .empno { font-size: .52rem; color: var(--muted); margin-top: 5px; }
    .who .empno b { color: var(--ink); font-size: .62rem; letter-spacing: .5px; }

    .qr-box { text-align: center; flex-shrink: 0; }
    .qr-box .qr { width: .72in; height: .72in; }
    .qr-box .qr img, .qr-box .qr canvas { width: 100% !important; height: 100% !important; }
    .qr-box .hint { font-size: .38rem; color: var(--muted); letter-spacing: 1px; margin-top: 2px; }

    .card-foot {
        background: var(--brand-dark);
        color: #fff;
        font-size: .45rem;
        letter-spacing: .5px;
        text-align: center;
        padding: 3px 8px;
    }

    /* ----- Back ----- */
    .back-body { flex: 1; padding: 9px 14px; display: flex; flex-direction: column; }
    .back-title {
        font-size: .5rem;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: var(--brand-dark);
        border-bottom: 1.5px solid var(--brand);
        padding-bottom: 3px;
        margin-bottom: 6px;
    }
    .back-row { font-size: .55rem; margin-bottom: 3px; display: flex; gap: 6px; }
    .back-row .lbl { color: var(--muted); width: .95in; flex-shrink: 0; }
    .back-row .val { font-weight: 600; }

    .sign {
        margin-top: auto;
        text-align: center;
        padding-top: 4px;
    }
    .sign .line {
        border-top: 1px solid var(--ink);
        width: 1.6in;
        margin: 0 auto 2px;
    }
    .sign .cap { font-size: .45rem; color: var(--muted); letter-spacing: 1px; }

    .back-note {
        font-size: .42rem;
        color: var(--muted);
        text-align: center;
        padding: 4px 10px 7px;
        line-height: 1.35;
    }

    .side-label {
        margin-top: 8px;
        text-align: center;
        font-size: .75rem;
        color: var(--muted);
        font-weight: 600;
        letter-spacing: 1px;
    }

    /* ---------- Print ---------- */
    @media print {
        body { background: #fff; padding: .25in; }
        .toolbar, .side-label { display: none; }
        .cards { gap: .2in; }
        .id-card {
            box-shadow: none;
            border: 1px dashed #bbb;   /* cutting guide */
            border-radius: 10px;
            page-break-inside: avoid;
        }
    }
</style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()"><i class="fas fa-print"></i> Print ID Card</button>
    <button class="secondary" onclick="window.close()">Close</button>
</div>

<div class="cards">

    <!-- FRONT -->
    <div>
        <div class="id-card">
            <div class="card-head">
                <div class="logo"><i class="fas fa-cubes"></i></div>
                <div>
                    <div class="co-name">GH SOFTWARE</div>
                    <div class="co-sub">SOLUTION</div>
                </div>
                <div class="tag">EMPLOYEE ID</div>
            </div>

            <div class="card-body-front">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="who">
                    <div class="name"><?= htmlspecialchars($full_name) ?></div>
                    <div class="role"><?= htmlspecialchars($job_role) ?></div>
                    <div class="empno">ID No. <b><?= $emp_code !== '' ? htmlspecialchars($emp_code) : '—' ?></b></div>
                </div>
                <?php if ($emp_code !== ''): ?>
                <div class="qr-box">
                    <div class="qr" id="qrcode"></div>
                    <div class="hint">SCAN</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card-foot">This card is property of GH Software Solution</div>
        </div>
        <div class="side-label">FRONT</div>
    </div>

    <!-- BACK -->
    <div>
        <div class="id-card">
            <div class="back-body">
                <div class="back-title">EMPLOYEE INFORMATION</div>
                <div class="back-row"><span class="lbl">Employee ID</span><span class="val"><?= $emp_code !== '' ? htmlspecialchars($emp_code) : '—' ?></span></div>
                <div class="back-row"><span class="lbl">Date Hired</span><span class="val"><?= htmlspecialchars($hired) ?></span></div>

                <div class="back-title" style="margin-top:6px;">IN CASE OF EMERGENCY</div>
                <?php if ($ec): ?>
                <div class="back-row"><span class="lbl">Contact Person</span><span class="val"><?= htmlspecialchars($ec['name']) ?><?= !empty($ec['relationship']) ? ' (' . htmlspecialchars($ec['relationship']) . ')' : '' ?></span></div>
                <div class="back-row"><span class="lbl">Contact No.</span><span class="val"><?= htmlspecialchars($ec['phone']) ?><?= !empty($ec['phone_alt']) ? ' / ' . htmlspecialchars($ec['phone_alt']) : '' ?></span></div>
                <?php else: ?>
                <div class="back-row"><span class="val" style="color:var(--muted);font-weight:400;">Not provided</span></div>
                <?php endif; ?>

                <div class="sign">
                    <div class="line"></div>
                    <div class="cap">EMPLOYEE SIGNATURE</div>
                </div>
            </div>
            <div class="back-note">
                If found, please return to GH Software Solution.<br>
                This ID is non-transferable and must be surrendered upon separation.
            </div>
        </div>
        <div class="side-label">BACK</div>
    </div>

</div>

<?php if ($emp_code !== ''): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('qrcode'), {
        text: <?= json_encode($emp_code) ?>,
        width: 128,
        height: 128,
        correctLevel: QRCode.CorrectLevel.M
    });
</script>
<?php endif; ?>

</body>
</html>
