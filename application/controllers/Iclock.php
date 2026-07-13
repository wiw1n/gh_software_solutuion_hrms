<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ZKTeco ADMS / PUSH-SDK endpoints ("iClock" protocol).
 *
 * The MB10-VL is pointed at this server via its Comm. → Cloud Server
 * (ADMS) setting; it then pushes attendance logs over plain HTTP:
 *
 *   GET  /iclock/cdata?SN=...&options=all   handshake — we reply with the
 *                                           transfer options below
 *   POST /iclock/cdata?SN=...&table=ATTLOG  punch lines, tab-separated:
 *                                           PIN  DATETIME  STATUS  VERIFY ...
 *   GET  /iclock/getrequest?SN=...          command poll (we issue none)
 *   POST /iclock/devicecmd                  command results (ignored)
 *
 * Responses are bare text/plain — no auth, no session, no HTML. The
 * device authenticates only by knowing the URL; punches are harmless
 * writes deduped on (device user, time) and matched via users.barcode.
 */
class Iclock extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Device_model');
        $this->output
            ->set_content_type('text/plain')
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('X-LiteSpeed-Cache-Control: no-cache');
    }

    public function cdata() {
        $sn = trim((string)$this->input->get('SN', TRUE));
        if ($sn !== '') $this->Device_model->touch_device($sn);

        if (strtolower($this->input->method()) !== 'post') {
            // Handshake: transfer options for the device. Realtime=1 makes it
            // push each punch immediately; stored logs flush on reconnect.
            $this->output->set_output(
                "GET OPTION FROM: {$sn}\r\n" .
                "ATTLOGStamp=None\r\n" .
                "OPERLOGStamp=None\r\n" .
                "ATTPHOTOStamp=None\r\n" .
                "ErrorDelay=30\r\n" .
                "Delay=10\r\n" .
                "TransTimes=00:00;12:00\r\n" .
                "TransInterval=1\r\n" .
                "TransFlag=TransData AttLog\r\n" .
                "TimeZone=8\r\n" .
                "Realtime=1\r\n" .
                "Encrypt=None"
            );
            return;
        }

        $body  = (string)$this->input->raw_input_stream;
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $body)));
        $table = strtoupper((string)$this->input->get('table', TRUE));

        if ($table === 'ATTLOG') {
            $rows = [];
            foreach ($lines as $line) {
                $c = preg_split('/\t/', $line);
                if (count($c) < 2) continue;
                $rows[] = ['pin' => $c[0], 'time' => $c[1], 'status' => $c[2] ?? null];
            }
            $this->Device_model->ingest($rows, 'push', $sn);
            $this->Device_model->process_pending();
        }
        // OPERLOG / ATTPHOTO / anything else: acknowledge so the device
        // clears its buffer, but we don't consume it.

        $this->output->set_output('OK: ' . count($lines));
    }

    public function getrequest() {
        $sn = trim((string)$this->input->get('SN', TRUE));
        if ($sn !== '') $this->Device_model->touch_device($sn);
        $this->output->set_output('OK');
    }

    public function devicecmd() {
        $this->output->set_output('OK');
    }

    // Fingerprint/face template + photo uploads — acknowledged, not stored.
    public function fdata() {
        $this->output->set_output('OK');
    }
}
