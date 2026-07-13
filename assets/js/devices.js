// Biometric Devices page: imported punch log, offline file import,
// unmatched-punch retry.
$(function () {

    const RESULT_BADGES = {
        am_in:     ['bg-success-subtle text-success border-success-subtle', 'AM In'],
        am_out:    ['bg-success-subtle text-success border-success-subtle', 'AM Out'],
        pm_in:     ['bg-success-subtle text-success border-success-subtle', 'PM In'],
        pm_out:    ['bg-success-subtle text-success border-success-subtle', 'PM Out'],
        time_in:   ['bg-success-subtle text-success border-success-subtle', 'Time In'],
        time_out:  ['bg-success-subtle text-success border-success-subtle', 'Time Out'],
        done:      ['bg-secondary-subtle text-secondary border-secondary-subtle', 'Day Complete'],
        skipped:   ['bg-secondary-subtle text-secondary border-secondary-subtle', 'Skipped'],
        unmatched: ['bg-danger-subtle text-danger border-danger-subtle', 'No Match'],
    };

    function fmtTime(dt) {
        const d = new Date(dt.replace(' ', 'T'));
        return isNaN(d) ? dt : d.toLocaleString('en-US', {
            month: 'short', day: '2-digit', year: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true,
        });
    }

    function resultBadge(result) {
        const [cls, label] = RESULT_BADGES[result] || ['bg-warning-subtle text-warning border-warning-subtle', 'Pending'];
        return `<span class="badge ${cls} border">${label}</span>`;
    }

    function loadPunches() {
        $.getJSON(APP_URL + 'devices/punches', function (res) {
            if (!res.success) return;

            const rows = res.punches.map(function (p) {
                const emp = p.first_name
                    ? `<div class="fw-semibold">${$('<i>').text(p.first_name + ' ' + p.last_name).html()}</div>
                       <div class="text-muted small">${p.employee_id || ''}</div>`
                    : '<span class="text-muted">—</span>';
                const source = p.source === 'push'
                    ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Live Push</span>'
                    : '<span class="badge bg-info-subtle text-info border border-info-subtle">File Import</span>';
                return `<tr>
                    <td class="fw-semibold">${fmtTime(p.punch_time)}</td>
                    <td>${$('<i>').text(p.device_user_id).html()}</td>
                    <td>${emp}</td>
                    <td>${source}</td>
                    <td>${resultBadge(p.result)}</td>
                </tr>`;
            });

            $('#punches-table tbody').html(
                rows.join('') || '<tr><td colspan="5" class="text-center text-muted py-4">No punches imported yet</td></tr>'
            );

            const s = res.stats;
            $('#punch-stats').text(
                s.total + ' total · ' + s.applied + ' applied' +
                (s.last_punch ? ' · latest ' + fmtTime(s.last_punch) : '')
            );

            if (s.unmatched > 0) {
                $('#unmatched-text').text(
                    s.unmatched + ' punch' + (s.unmatched > 1 ? 'es' : '') +
                    ' could not be matched to an employee.'
                );
                $('#unmatched-alert').removeClass('d-none').addClass('d-flex');
            } else {
                $('#unmatched-alert').addClass('d-none').removeClass('d-flex');
            }
        });
    }

    $('#btn-refresh-punches').on('click', loadPunches);

    // ── Offline file import ──────────────────────────────────────────
    $('#import-form').on('submit', function (e) {
        e.preventDefault();
        const file = $('#import-file')[0].files[0];
        if (!file) return;

        const fd = new FormData();
        fd.append('file', file);

        const $btn = $('#btn-import').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Importing…');

        $.ajax({
            url: APP_URL + 'devices/upload',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        }).done(function (res) {
            const r = res.result, g = res.ingested;
            let html = `<div class="text-start" style="font-size:.92rem;">
                <div><strong>${g.new}</strong> new punch(es) read, <strong>${g.duplicate}</strong> already imported</div>
                <div><strong>${r.applied}</strong> applied to timesheets, <strong>${r.skipped}</strong> skipped (duplicates / day complete)</div>`;
            if (r.unmatched > 0) {
                html += `<div class="text-danger mt-1"><strong>${r.unmatched}</strong> punch(es) had no matching employee (Device ID: ${r.unmatched_pins.join(', ')})</div>`;
            }
            html += '</div>';
            Swal.fire({ icon: r.unmatched > 0 ? 'warning' : 'success', title: 'Import Finished', html: html });
            $('#import-form')[0].reset();
            loadPunches();
        }).fail(function (xhr) {
            Swal.fire('Import Failed', (xhr.responseJSON && xhr.responseJSON.message) || 'Could not import the file.', 'error');
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Import Punches');
        });
    });

    // ── Retry unmatched ──────────────────────────────────────────────
    $('#btn-reprocess').on('click', function () {
        const $btn = $(this).prop('disabled', true);
        $.post(APP_URL + 'devices/reprocess').done(function (res) {
            const r = res.result;
            Swal.fire('Done', r.applied + ' punch(es) applied, ' + r.unmatched + ' still unmatched.',
                r.unmatched > 0 ? 'warning' : 'success');
            loadPunches();
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    loadPunches();
});
