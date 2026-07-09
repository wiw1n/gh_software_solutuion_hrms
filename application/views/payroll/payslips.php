<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// $week_end and $period_type come from the controller (period-aware)
$week_end          = $week_end ?? date('Y-m-d', strtotime($week_start . ' +6 days'));
$period_type       = $period_type ?? 'weekly';
$single            = $single ?? false;
$period_type_label = $period_type === 'semi_monthly' ? '15/30' : 'Weekly';
$period_label      = date('M d', strtotime($week_start)) . ' – ' . date('M d, Y', strtotime($week_end));

// 12 slips per bond paper: 3 columns x 4 rows
$pages = array_chunk($rows, 12);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips — <?= $period_label ?></title>
    <style>
        @page { size: letter portrait; margin: 8mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 6.8pt;
            color: #1a1a1a;
            background: #f1f5f9;
        }

        .sheet {
            width: 200mm;                 /* letter 216mm - 2x8mm margin */
            margin: 10px auto;
            background: #fff;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5mm;
            padding: 1mm;
        }

        .slip {
            border: 1px dashed #94a3b8;   /* cut guide */
            height: 63mm;
            padding: 2.2mm 2.5mm;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            break-inside: avoid;
        }

        .slip-head {
            text-align: center;
            border-bottom: 1px solid #1a56db;
            padding-bottom: 1mm;
            margin-bottom: 1mm;
        }
        .slip-head .co  { font-size: 7pt; font-weight: 700; color: #1a56db; letter-spacing: .04em; }
        .slip-head .prd { font-size: 6pt; color: #555; }

        .emp { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .8mm; }
        .emp .nm   { font-weight: 700; font-size: 7.2pt; }
        .emp .role { font-size: 5.8pt; color: #777; white-space: nowrap; margin-left: 4px; }

        table { width: 100%; border-collapse: collapse; }
        td { padding: .35mm 0; font-size: 6.4pt; vertical-align: top; }
        td.r { text-align: right; white-space: nowrap; }
        td.lbl { color: #444; }
        tr.section td { padding-top: .7mm; font-weight: 700; color: #333; font-size: 6pt;
                        text-transform: uppercase; letter-spacing: .04em; }
        tr.total td { border-top: 1px solid #cbd5e1; font-weight: 700; }
        .deduct { color: #b91c1c; }
        .add-val { color: #15803d; }
        .muted { color: #999; }
        .warn { color: #b45309; font-weight: 600; }

        .net {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 2px;
            padding: .8mm 1.5mm;
            font-weight: 700;
        }
        .net .amt { font-size: 8.5pt; color: #15803d; }

        .sig {
            margin-top: 1.2mm;
            font-size: 5.8pt;
            color: #555;
            display: flex;
            justify-content: space-between;
            gap: 3mm;
        }
        .sig span { flex: 1; border-top: 1px solid #94a3b8; padding-top: .4mm; text-align: center; }

        .no-print-bar {
            text-align: center;
            padding: 12px;
        }
        .no-print-bar button {
            border: none; padding: 8px 22px; border-radius: 5px;
            cursor: pointer; font-size: 10pt; margin: 0 4px;
        }
        .btn-print { background: #1a56db; color: #fff; }
        .btn-close-w { background: #fff; color: #333; border: 1px solid #cbd5e1 !important; }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; width: auto; padding: 0; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
            .slip, .net { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print-bar { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn-print" onclick="window.print()">&#x1F5A8; Print
        <?= $single ? 'Payslip' : 'All Payslips (' . count($rows) . ')' ?>
    </button>
    <button class="btn-close-w" onclick="window.close()">Close</button>
</div>

<?php foreach ($pages as $page): ?>
<div class="sheet">
    <?php foreach ($page as $r): ?>
    <?php
        $no_rate = ($r['daily_rate'] === null);
        $others_deduct = $r['borrow_deduct'] + $r['line_deductions'];
    ?>
    <div class="slip">
        <div class="slip-head">
            <div class="co">GH SOFTWARE SOLUTION</div>
            <div class="prd">PAYSLIP &middot; <?= $period_type_label ?> &middot; <?= $period_label ?></div>
        </div>

        <div class="emp">
            <span class="nm"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></span>
            <span class="role"><?= !empty($r['employee_id']) ? htmlspecialchars($r['employee_id']) . ' · ' : '' ?><?= htmlspecialchars($r['role_name'] ?? '') ?></span>
        </div>

        <table>
            <tr>
                <td class="lbl">Days Worked</td>
                <td class="r"><?= (int)$r['days_present'] ?> day<?= $r['days_present'] != 1 ? 's' : '' ?></td>
            </tr>
            <tr>
                <td class="lbl">Daily Rate</td>
                <td class="r">
                    <?= $no_rate ? '<span class="warn">Not Set</span>' : '&#8369;' . number_format($r['daily_rate'], 2) ?>
                </td>
            </tr>
            <tr>
                <td class="lbl"><strong>Gross Pay</strong></td>
                <td class="r"><strong><?= $no_rate ? '—' : '&#8369;' . number_format($r['gross_pay'], 2) ?></strong></td>
            </tr>
            <?php if ($r['line_additions'] > 0): ?>
            <tr>
                <td class="lbl">Additions</td>
                <td class="r add-val">+&#8369;<?= number_format($r['line_additions'], 2) ?></td>
            </tr>
            <?php endif; ?>

            <tr class="section"><td colspan="2">Deductions</td></tr>
            <tr>
                <td class="lbl">SSS</td>
                <td class="r <?= $r['sss_deduct'] > 0 ? 'deduct' : 'muted' ?>">
                    <?= $r['sss_deduct'] > 0 ? '&#8369;' . number_format($r['sss_deduct'], 2) : '—' ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">PhilHealth</td>
                <td class="r <?= $r['philhealth_deduct'] > 0 ? 'deduct' : 'muted' ?>">
                    <?= $r['philhealth_deduct'] > 0 ? '&#8369;' . number_format($r['philhealth_deduct'], 2) : '—' ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Pag-IBIG</td>
                <td class="r <?= $r['pagibig_deduct'] > 0 ? 'deduct' : 'muted' ?>">
                    <?= $r['pagibig_deduct'] > 0 ? '&#8369;' . number_format($r['pagibig_deduct'], 2) : '—' ?>
                </td>
            </tr>
            <?php if ($r['advance_deduct'] > 0): ?>
            <tr>
                <td class="lbl">Cash Advance</td>
                <td class="r deduct">&#8369;<?= number_format($r['advance_deduct'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($r['borrow_deduct'] > 0): ?>
            <tr>
                <td class="lbl">Borrow</td>
                <td class="r deduct">&#8369;<?= number_format($r['borrow_deduct'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($r['line_deductions'] > 0): ?>
            <tr>
                <td class="lbl">Other Deductions</td>
                <td class="r deduct">&#8369;<?= number_format($r['line_deductions'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total">
                <td class="lbl">Total Deductions</td>
                <td class="r deduct">&#8369;<?= number_format($r['total_deductions'], 2) ?></td>
            </tr>
        </table>

        <div class="net">
            <span>NET PAY</span>
            <span class="amt"><?= $no_rate ? '—' : '&#8369;' . number_format($r['net_pay'], 2) ?></span>
        </div>

        <div class="sig">
            <span>Received by (Signature)</span>
            <span>Date</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (empty($rows)): ?>
<div style="text-align:center;padding:40px;color:#888;">No employees confirmed for this period.</div>
<?php endif; ?>

</body>
</html>
