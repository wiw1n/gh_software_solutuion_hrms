<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// $week_end and $period_type come from the controller (period-aware)
$week_end          = $week_end ?? date('Y-m-d', strtotime($week_start . ' +6 days'));
$period_type       = $period_type ?? 'weekly';
$period_type_label = $period_type === 'semi_monthly' ? '15/30 (Semi-Monthly)' : 'Weekly';
$period_label      = date('M j', strtotime($week_start)) . '-' . date('j, Y', strtotime($week_end));
if (date('Y-m', strtotime($week_start)) !== date('Y-m', strtotime($week_end))) {
    $period_label = date('M j', strtotime($week_start)) . ' - ' . date('M j, Y', strtotime($week_end));
}

// Build the list of dates in the period (per-day columns)
$dates = [];
$d = strtotime($week_start);
$end = strtotime($week_end);
while ($d <= $end) {
    $dates[] = date('Y-m-d', $d);
    $d = strtotime('+1 day', $d);
}

$day_initials = ['Sun' => 'S', 'Mon' => 'M', 'Tue' => 'T', 'Wed' => 'W', 'Thu' => 'TH', 'Fri' => 'F', 'Sat' => 'S'];
$group_count  = count($groups);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Sign Sheet — <?= date('M d', strtotime($week_start)) ?> to <?= date('M d, Y', strtotime($week_end)) ?></title>
    <style>
        @page { size: landscape; margin: 10mm 12mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            background: #fff;
            padding: 14px 18px;
        }

        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }

        /* ---------- Company letterhead ---------- */
        .letterhead {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            text-align: center;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .letterhead .logo-img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            flex: 0 0 auto;
        }
        .letterhead .logo {
            width: 52px;
            height: 52px;
            background: #1a56db;
            color: #fff;
            font-size: 26pt;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            flex: 0 0 auto;
        }
        .letterhead .co-name {
            font-size: 15pt;
            font-weight: 800;
            letter-spacing: .5px;
        }
        .letterhead .co-sub {
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .letterhead .co-line {
            font-size: 8pt;
            color: #333;
        }
        .letterhead a { color: #1a56db; }

        /* ---------- Project / period bar ---------- */
        .project-bar {
            background: #ffff00;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 10px;
            font-size: 9.5pt;
            margin-bottom: 2px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .project-bar .label { font-weight: 400; }
        .project-bar .value { font-weight: 700; margin-left: 8px; }
        .project-bar .period { font-weight: 700; }

        /* ---------- Payroll table ---------- */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        th, td {
            border: 1px solid #444;
            padding: 3px 4px;
            vertical-align: middle;
        }
        thead th {
            font-weight: 700;
            text-align: center;
            background: #f4f6fa;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        thead .day-date { font-weight: 400; font-size: 7pt; color: #444; }

        td.name  { text-align: left; white-space: nowrap; font-weight: 600; }
        td.num   { text-align: right; white-space: nowrap; }
        td.c     { text-align: center; }
        td.rowno { text-align: center; color: #666; width: 22px; }
        td.sign  { min-width: 110px; }
        td.deduct { color: #b91c1c; }

        tbody tr { height: 26px; }

        tfoot td {
            background: #cfe2f3;
            font-weight: 700;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .dash { color: #999; }

        .sheet-footer {
            margin-top: 8px;
            font-size: 7.5pt;
            color: #777;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { padding: 0; }
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

<?php if (empty($groups)): ?>
<div style="text-align:center;padding:40px;color:#888;">No employees confirmed for this period.</div>
<?php endif; ?>

<?php $sheet_no = 0; foreach ($groups as $project_name => $rows): $sheet_no++; ?>
<?php
    $g_days   = array_sum(array_column($rows, 'days_present'));
    $g_wage   = array_sum(array_column($rows, 'gross_pay'));
    $g_adv    = array_sum(array_column($rows, 'advance_deduct'));
    $g_bor    = array_sum(array_column($rows, 'borrow_deduct'));
    $g_other  = array_sum(array_column($rows, 'govt_deductions')) + array_sum(array_column($rows, 'line_deductions'));
    $g_deduct = array_sum(array_column($rows, 'total_deductions'));
    $g_net    = array_sum(array_column($rows, 'net_pay'));
?>
<div class="sheet">

    <!-- Company Header -->
    <div class="letterhead">
        <?php if (!empty($company['company_logo'])): ?>
        <img class="logo-img" src="<?= base_url($company['company_logo']) ?>" alt="Logo">
        <?php else: ?>
        <div class="logo"><?= htmlspecialchars(strtoupper(mb_substr($company['company_name'] ?? 'C', 0, 1))) ?></div>
        <?php endif; ?>
        <div>
            <div class="co-name"><?= htmlspecialchars($company['company_name'] ?? '') ?></div>
            <?php if (!empty($company['company_tagline'])): ?>
            <div class="co-sub"><?= htmlspecialchars($company['company_tagline']) ?></div>
            <?php endif; ?>
            <?php if (!empty($company['company_address'])): ?>
            <div class="co-line"><?= htmlspecialchars($company['company_address']) ?></div>
            <?php endif; ?>
            <?php if (!empty($company['company_email']) || !empty($company['company_phone'])): ?>
            <div class="co-line">
                <?php if (!empty($company['company_email'])): ?>E-Mail Add: <a><?= htmlspecialchars($company['company_email']) ?></a><?php endif; ?>
                <?php if (!empty($company['company_phone'])): ?>&nbsp;Cell No. <?= htmlspecialchars($company['company_phone']) ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Project + Period bar -->
    <div class="project-bar">
        <div>
            <span class="label">Name of Project :</span>
            <span class="value"><?= htmlspecialchars($project_name) ?></span>
        </div>
        <div class="period">Period: <?= $period_label ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:22px;">#</th>
                <th rowspan="2" style="min-width:120px;">NAME</th>
                <?php foreach ($dates as $date): ?>
                <th style="width:30px;"><?= $day_initials[date('D', strtotime($date))] ?></th>
                <?php endforeach; ?>
                <th rowspan="2" style="width:38px;">DAYS</th>
                <th rowspan="2" style="width:62px;">WAGE</th>
                <th rowspan="2" style="width:58px;">ADVANCE<br>(Bale)</th>
                <th rowspan="2" style="width:58px;">BORROW<br>(Utang)</th>
                <th rowspan="2" style="width:58px;">OTHERS</th>
                <th rowspan="2" style="width:64px;">TOTAL<br>DEDUCTION</th>
                <th rowspan="2" style="width:60px;">REMAINING</th>
                <th rowspan="2" style="width:62px;">GROSS</th>
                <th rowspan="2" style="width:40px;">OT</th>
                <th rowspan="2" style="width:62px;">NET</th>
                <th rowspan="2" style="width:22px;"></th>
                <th rowspan="2" style="min-width:110px;">SIGNATURE<br>(Received)</th>
            </tr>
            <tr>
                <?php foreach ($dates as $date): ?>
                <th class="day-date"><?= (int)date('j', strtotime($date)) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <?php
                $no_rate = ($r['daily_rate'] === null);
                $att     = $daily_attendance[$r['user_id']] ?? [];
                $others  = $r['govt_deductions'] + $r['line_deductions'];
                $ot_hrs  = (float)$r['total_overtime'];
            ?>
            <tr>
                <td class="rowno"><?= $i + 1 ?></td>
                <td class="name"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>

                <?php foreach ($dates as $date): ?>
                <td class="c">
                    <?php if (isset($att[$date]) && $att[$date]['status'] === 'present'): ?>
                    1.00
                    <?php elseif (isset($att[$date]) && $att[$date]['status'] === 'leave'): ?>
                    L
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>

                <td class="c" style="font-weight:700;"><?= number_format((int)$r['days_present'], 2) ?></td>
                <td class="num"><?= $no_rate ? '<span class="dash">—</span>' : number_format($r['gross_pay'], 2) ?></td>
                <td class="num deduct"><?= $r['advance_deduct'] > 0 ? number_format($r['advance_deduct'], 2) : '' ?></td>
                <td class="num deduct"><?= $r['borrow_deduct']  > 0 ? number_format($r['borrow_deduct'],  2) : '' ?></td>
                <td class="num deduct"><?= $others > 0 ? number_format($others, 2) : '' ?></td>
                <td class="num deduct" style="font-weight:700;">
                    <?= $r['total_deductions'] > 0 ? number_format($r['total_deductions'], 2) : '' ?>
                </td>
                <td class="num"><?= $r['borrow_remaining'] > 0 ? number_format($r['borrow_remaining'], 2) : '<span class="dash">-</span>' ?></td>
                <td class="num"><?= $no_rate ? '<span class="dash">—</span>' : number_format($r['net_pay'], 2) ?></td>
                <td class="num"><?= $ot_hrs > 0 ? number_format($ot_hrs, 2) : '<span class="dash">-</span>' ?></td>
                <td class="num" style="font-weight:700;"><?= $no_rate ? '<span class="dash">—</span>' : number_format($r['net_pay'], 2) ?></td>
                <td class="rowno"><?= $i + 1 ?></td>
                <td class="sign"></td>
            </tr>
            <?php endforeach; ?>

            <!-- spare blank row for manual additions, like the paper form -->
            <tr>
                <td class="rowno"></td>
                <td class="name"></td>
                <?php foreach ($dates as $date): ?><td></td><?php endforeach; ?>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td class="sign"></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="c">TOTALS</td>
                <?php foreach ($dates as $date): ?><td></td><?php endforeach; ?>
                <td class="c"><?= number_format($g_days, 2) ?></td>
                <td class="num"><?= number_format($g_wage, 2) ?></td>
                <td class="num"><?= $g_adv   > 0 ? number_format($g_adv,   2) : '-' ?></td>
                <td class="num"><?= $g_bor   > 0 ? number_format($g_bor,   2) : '-' ?></td>
                <td class="num"><?= $g_other > 0 ? number_format($g_other, 2) : '-' ?></td>
                <td class="num"><?= $g_deduct > 0 ? number_format($g_deduct, 2) : '-' ?></td>
                <td></td>
                <td class="num"><?= number_format($g_net, 2) ?></td>
                <td></td>
                <td class="num"><?= number_format($g_net, 2) ?></td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="sheet-footer">
        <span>Payroll Sign Sheet &mdash; <?= $period_type_label ?> &mdash; Sheet <?= $sheet_no ?> of <?= $group_count ?></span>
        <span>Generated <?= date('M d, Y h:i A') ?></span>
    </div>

</div>
<?php endforeach; ?>

</body>
</html>
