<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Biometric Device page: terminals seen on the ADMS push endpoints,
 * manual attendance-file import for offline sites, and the imported
 * punch log. Admin and super admin only.
 */
class Devices extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->require_role('super_admin', 'admin');
        $this->load->model('Device_model');
    }

    public function index() {
        $data = [
            'title'    => 'Biometric Devices',
            'page_js'  => 'devices.js',
            'devices'  => $this->Device_model->get_devices(),
            'stats'    => $this->Device_model->stats(),
            'push_url' => base_url('iclock/cdata'),
        ];

        $this->load->view('layouts/header', $data);
        $this->load->view('layouts/sidebar', $data);
        $this->load->view('devices/index', $data);
        $this->load->view('layouts/footer', $data);
    }

    // AJAX: recent imported punches for the log table.
    public function punches() {
        $this->json([
            'success' => true,
            'punches' => $this->Device_model->recent_punches(200),
            'stats'   => $this->Device_model->stats(),
        ]);
    }

    // AJAX: import a ZKTeco attendance-data file (USB download from an
    // offline site). Accepts the device's *_attlog.dat format — one punch
    // per line: PIN <tab> YYYY-MM-DD HH:MM:SS <tab> status ... — and the
    // same data saved as .txt/.csv (comma-separated also accepted).
    public function upload() {
        if (empty($_FILES['file']['name'])) {
            $this->json(['success' => false, 'message' => 'Please choose a file to import.'], 422);
            return;
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'Upload failed. Please try again.'], 422);
            return;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $this->json(['success' => false, 'message' => 'File is too large (5 MB max).'], 422);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['dat', 'txt', 'csv', 'log'])) {
            $this->json(['success' => false, 'message' => 'Unsupported file type. Use the attendance data file from the device USB download (.dat, .txt, .csv).'], 422);
            return;
        }

        $rows    = [];
        $skipped = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string)file_get_contents($file['tmp_name'])) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $c = strpos($line, "\t") !== false ? preg_split('/\t+/', $line) : str_getcsv($line);
            $c = array_map('trim', $c);

            // header rows / malformed lines: need a numeric PIN + parseable time
            if (count($c) < 2 || !preg_match('/^\d+$/', $c[0]) || !strtotime($c[1])) {
                $skipped++;
                continue;
            }
            $rows[] = ['pin' => $c[0], 'time' => $c[1], 'status' => $c[2] ?? null];
        }

        if (!$rows) {
            $this->json(['success' => false, 'message' => 'No punches found in the file. Export the attendance data (attlog) file from the device, not the Excel report.'], 422);
            return;
        }

        $ingested = $this->Device_model->ingest($rows, 'upload');
        $result   = $this->Device_model->process_pending();

        $this->json([
            'success'  => true,
            'message'  => 'Import finished.',
            'ingested' => $ingested + ['header_lines' => $skipped],
            'result'   => $result,
        ]);
    }

    // AJAX: retry punches whose device User ID had no matching employee.
    public function reprocess() {
        $this->json(['success' => true, 'result' => $this->Device_model->reprocess_unmatched()]);
    }
}
