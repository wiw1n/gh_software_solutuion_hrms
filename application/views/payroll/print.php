<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// $week_end and $period_type come from the controller (period-aware)
$week_end         = $week_end ?? date('Y-m-d', strtotime($week_start . ' +6 days'));
$period_type      = $period_type ?? 'weekly';
$period_type_label = $period_type === 'semi_monthly' ? '15/30 (Semi-Monthly)' : 'Weekly';
$total_gross      = array_sum(array_column($rows, 'gross_pay'));
$total_sss        = array_sum(array_column($rows, 'sss_deduct'));
$total_philhealth = array_sum(array_column($rows, 'philhealth_deduct'));
$total_pagibig    = array_sum(array_column($rows, 'pagibig_deduct'));
$total_adv_deduct = array_sum(array_column($rows, 'advance_deduct'));
$total_bor_deduct = array_sum(array_column($rows, 'borrow_deduct'));
$total_line_add   = array_sum(array_column($rows, 'line_additions'));
$total_line_deduct= array_sum(array_column($rows, 'line_deductions'));
$total_deductions = array_sum(array_column($rows, 'total_deductions'));
$total_net        = array_sum(array_column($rows, 'net_pay'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Report — <?= date('M d', strtotime($week_start)) ?> to <?= date('M d, Y', strtotime($week_end)) ?></title>
    <style>
        @page { size: landscape; margin: 14mm 16mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 9.5pt;
            color: #1a1a1a;
            background: #fff;
            padding: 16px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2.5px solid #1a56db;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header h1 { font-size: 16pt; color: #1a56db; font-weight: 700; }
        .header h1 span { font-size: 10pt; color: #555; font-weight: 400; display: block; margin-top: 2px; }
        .header .meta { text-align: right; font-size: 8.5pt; color: #555; line-height: 1.7; }
        .header .period { margin-top: 4px; font-size: 9.5pt; color: #333; font-weight: 600; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .summary-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 6px 10px;
            text-align: center;
        }

        .summary-box .val { font-size: 12pt; font-weight: 700; color: #1a56db; }
        .summary-box .val.green  { color: #15803d; }
        .summary-box .val.red    { color: #b91c1c; }
        .summary-box .val.teal   { color: #0e7490; }
        .summary-box .lbl { font-size: 7.5pt; color: #777; margin-top: 1px; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        thead tr { background: #1a56db; color: #fff; }
        thead th { padding: 6px 7px; text-align: left; font-weight: 600; white-space: nowrap; }
        thead th.r { text-align: right; }
        thead th.c { text-align: center; }

        tbody tr:nth-child(even) { background: #f7f9fc; }
        tbody td { padding: 6px 7px; border-bottom: 1px solid #e8e8e8; vertical-align: middle; }
        tbody td.r { text-align: right; }
        tbody td.c { text-align: center; }
        tbody tr.no-rate { background: #fffbeb; }

        tfoot tr { background: #1e293b; color: #fff; }
        tfoot td { padding: 7px; font-weight: 700; font-size: 9pt; }
        tfoot td.r { text-align: right; }
        tfoot td.c { text-align: center; }

        .name-block .name  { font-weight: 600; }

        .pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 7.5pt;
            font-weight: 600;
            white-space: nowrap;
        }
        .pill-blue   { background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; }
        .pill-green  { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .pill-yellow { background: #fefce8; color: #854d0e; border: 1px solid #fde047; }
        .pill-red    { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .pill-gray   { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

        .no-rate-warn { font-size: 7.5pt; color: #b45309; font-weight: 600; }
        .deduct       { color: #b91c1c; }
        .add-val      { color: #15803d; }
        .net-pay      { color: #15803d; font-weight: 700; }
        .dash         { color: #aaa; }
        .balance-note { font-size: 7pt; color: #6d28d9; }

        .line-items-list { list-style: none; padding: 0; margin: 0; }
        .line-items-list li { font-size: 7pt; }

        .doc-footer {
            margin-top: 14px;
            font-size: 7.5pt;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 6px;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { padding: 0; }
            thead { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tfoot { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .pill, .summary-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Screen-only controls -->
<div class="no-print" style="text-align:right;margin-bottom:14px;">
    <button onclick="window.print()"
            style="background:#1a56db;color:#fff;border:none;padding:7px 18px;border-radius:5px;cursor:pointer;font-size:9.5pt;margin-right:6px;">
        &#x1F5A8; Print
    </button>
    <button onclick="window.close()"
            style="background:#f1f5f9;color:#333;border:1px solid #ddd;padding:7px 18px;border-radius:5px;cursor:pointer;font-size:9.5pt;">
        Close
    </button>
</div>

<!-- Header -->
<div class="header">
    <div>
        <h1>
            GH Software Solution
            <span>Payroll Report</span>
        </h1>
        <div class="period">
            <?= $period_type_label ?> Payroll: <?= date('M d, Y', strtotime($week_start)) ?> &ndash; <?= date('M d, Y', strtotime($week_end)) ?>
        </div>
    </div>
    <div class="meta">
        <div><strong>Generated:</strong> <?= date('M d, Y h:i A') ?></div>
        <div><strong>Total Employees:</strong> <?= count($rows) ?></div>
    </div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-box">
        <div class="val"><?= count($rows) ?></div>
        <div class="lbl">Employees</div>
    </div>
    <div class="summary-box">
        <div class="val green">&#8369;<?= number_format($total_gross, 2) ?></div>
        <div class="lbl">Total Gross Pay</div>
    </div>
    <div class="summary-box">
        <div class="val red">&#8369;<?= number_format($total_sss + $total_philhealth + $total_pagibig, 2) ?></div>
        <div class="lbl">Gov't Deductions</div>
    </div>
    <div class="summary-box">
        <div class="val red">&#8369;<?= number_format($total_adv_deduct, 2) ?></div>
        <div class="lbl">Advance Deducted</div>
    </div>
    <div class="summary-box">
        <div class="val red">&#8369;<?= number_format($total_bor_deduct, 2) ?></div>
        <div class="lbl">Borrow Deducted</div>
    </div>
    <div class="summary-box">
        <div class="val teal">&#8369;<?= number_format($total_net, 2) ?></div>
        <div class="lbl">Total Net Pay</div>
    </div>
</div>

<?php if (!empty($rows)): ?>
<table>
    <thead>
        <tr>
            <th style="width:26px">#</th>
            <th style="width:72px">Employee ID</th>
            <th>Employee</th>
            <th class="c" style="width:60px">Days</th>
            <th class="r" style="width:75px">Daily Rate</th>
            <th class="r" style="width:80px">Gross Pay</th>
            <th class="r" style="width:70px">SSS</th>
            <th class="r" style="width:78px">PhilHealth</th>
            <th class="r" style="width:65px">Pag-IBIG</th>
            <th class="c" style="width:95px">Advance</th>
            <th class="c" style="width:95px">Borrow</th>
            <th class="c" style="width:110px">Adjustments</th>
            <th class="r" style="width:80px">Total Deduct.</th>
            <th class="r" style="width:82px">Net Pay</th>
            <th style="width:100px">Confirmed By</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $i => $r): ?>
        <?php
            $no_rate    = ($r['daily_rate'] === null);
            $adv_status = $r['advance_status'] ?? '';
            $bor_status = $r['borrow_status']  ?? '';
        ?>
        <tr class="<?= $no_rate ? 'no-rate' : '' ?>">
            <td class="c" style="color:#999;"><?= $i + 1 ?></td>
            <td class="c"><?= !empty($r['employee_id']) ? htmlspecialchars($r['employee_id']) : '<span class="dash">—</span>' ?></td>
            <td class="name-block">
                <div class="name"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
            </td>
            <td class="c">
                <span class="pill pill-blue"><?= (int)$r['days_present'] ?>d</span>
            </td>
            <td class="r">
                <?= $no_rate
                    ? '<span class="no-rate-warn">Not Set</span>'
                    : '&#8369;' . number_format($r['daily_rate'], 2) ?>
            </td>
            <td class="r">
                <?= $no_rate ? '<span class="dash">—</span>' : '&#8369;' . number_format($r['gross_pay'], 2) ?>
            </td>
            <td class="r deduct">
                <?= $r['sss_deduct'] > 0 ? '&#8369;' . number_format($r['sss_deduct'], 2) : '<span class="dash">—</span>' ?>
            </td>
            <td class="r deduct">
                <?= $r['philhealth_deduct'] > 0 ? '&#8369;' . number_format($r['philhealth_deduct'], 2) : '<span class="dash">—</span>' ?>
            </td>
            <td class="r deduct">
                <?= $r['pagibig_deduct'] > 0 ? '&#8369;' . number_format($r['pagibig_deduct'], 2) : '<span class="dash">—</span>' ?>
            </td>
            <!-- Advance -->
            <td class="c">
                <?php if ($adv_status === 'approved' && $r['advance_amount'] !== null): ?>
                <span class="pill pill-green">&#8369;<?= number_format($r['advance_amount'], 2) ?></span>
                <div style="font-size:7pt;color:#15803d;">Auto-deducted</div>
                <?php elseif ($r['advance_amount'] !== null): ?>
                <span class="pill pill-gray">&#8369;<?= number_format($r['advance_amount'], 2) ?> · <?= ucfirst($adv_status) ?></span>
                <?php else: ?>
                <span class="dash">—</span>
                <?php endif; ?>
            </td>
            <!-- Borrow -->
            <td class="c">
                <?php if ($r['borrow_deduct'] > 0): ?>
                <span class="pill pill-red">&#8369;<?= number_format($r['borrow_deduct'], 2) ?></span>
                <?php if ($r['borrow_remaining'] > 0): ?>
                <div class="balance-note">&#8369;<?= number_format($r['borrow_remaining'], 2) ?> balance</div>
                <?php endif; ?>
                <?php elseif ($r['borrow_amount'] !== null && $bor_status === 'approved'): ?>
                <span class="pill pill-gray">&#8369;0 / No deduct</span>
                <?php elseif ($r['borrow_remaining'] > 0): ?>
                <span class="pill pill-yellow">&#8369;<?= number_format($r['borrow_remaining'], 2) ?> bal.</span>
                <?php else: ?>
                <span class="dash">—</span>
                <?php endif; ?>
            </td>
            <!-- Adjustments (line items) -->
            <td class="c">
                <?php if (!empty($r['line_items'])): ?>
                <ul class="line-items-list">
                    <?php foreach ($r['line_items'] as $item): ?>
                    <li class="<?= $item['type'] === 'addition' ? 'add-val' : 'deduct' ?>">
                        <?= $item['type'] === 'addition' ? '+' : '−' ?>&#8369;<?= number_format($item['amount'], 2) ?>
                        <span style="color:#555;"> <?= htmlspecialchars($item['label']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <span class="dash">—</span>
                <?php endif; ?>
            </td>
            <td class="r deduct">
                <?= $r['total_deductions'] > 0 ? '&#8369;' . number_format($r['total_deductions'], 2) : '<span class="dash">—</span>' ?>
            </td>
            <td class="r net-pay">
                <?= $no_rate ? '<span class="dash">—</span>' : '&#8369;' . number_format($r['net_pay'], 2) ?>
            </td>
            <td>
                <div style="font-weight:600;"><?= htmlspecialchars($r['confirmed_by_name']) ?></div>
                <div style="font-size:7.5pt;color:#777;"><?= date('M d, Y', strtotime($r['confirmed_at'])) ?></div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="r">TOTALS</td>
            <td class="r">&#8369;<?= number_format($total_gross, 2) ?></td>
            <td class="r">&#8369;<?= number_format($total_sss, 2) ?></td>
            <td class="r">&#8369;<?= number_format($total_philhealth, 2) ?></td>
            <td class="r">&#8369;<?= number_format($total_pagibig, 2) ?></td>
            <td class="c">&#8369;<?= number_format($total_adv_deduct, 2) ?></td>
            <td class="c">&#8369;<?= number_format($total_bor_deduct, 2) ?></td>
            <td class="c">
                <?php if ($total_line_add > 0): ?>+&#8369;<?= number_format($total_line_add, 2) ?><?php endif; ?>
                <?php if ($total_line_deduct > 0): ?>&#10;−&#8369;<?= number_format($total_line_deduct, 2) ?><?php endif; ?>
                <?php if ($total_line_add == 0 && $total_line_deduct == 0): ?>—<?php endif; ?>
            </td>
            <td class="r">&#8369;<?= number_format($total_deductions, 2) ?></td>
            <td class="r">&#8369;<?= number_format($total_net, 2) ?></td>
            <td></td>
        </tr>
    </tfoot>
</table>
<?php else: ?>
<div style="text-align:center;padding:40px;color:#888;">No employees confirmed for this week.</div>
<?php endif; ?>

<!-- Document Footer -->
<div class="doc-footer">
    <span>GH Software Solution &mdash; Payroll Report &mdash; Prepared by accounting</span>
    <span>Period: <?= date('M d', strtotime($week_start)) ?> &ndash; <?= date('M d, Y', strtotime($week_end)) ?> (<?= $period_type_label ?>) &mdash; Generated <?= date('M d, Y h:i A') ?></span>
</div>

</body>
</html>
