<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Minimal WebAuthn (FIDO2) server-side helper.
 *
 * Supports fingerprint clock-in via platform authenticators
 * (Windows Hello, built-in fingerprint readers) with:
 *   - "none" attestation registration  → extracts credential ID + public key
 *   - assertion verification           → ES256 (P-256) and RS256 signatures
 *
 * No composer dependencies — includes a small CBOR decoder covering the
 * subset of CBOR that WebAuthn attestation objects and COSE keys use.
 */
class Webauthn_lib {

    // ================================================================
    // Base64URL helpers
    // ================================================================

    public function b64url_encode($bin) {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public function b64url_decode($str) {
        $pad = strlen($str) % 4;
        if ($pad) $str .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($str, '-_', '+/'));
    }

    public function generate_challenge($bytes = 32) {
        return $this->b64url_encode(random_bytes($bytes));
    }

    // The Relying Party ID is the host name (no scheme, no port).
    public function rp_id() {
        $host = parse_url(base_url(), PHP_URL_HOST);
        return $host ?: 'localhost';
    }

    // Expected origin: scheme://host[:port]
    public function expected_origin() {
        $parts  = parse_url(base_url());
        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost');
        if (!empty($parts['port'])) $origin .= ':' . $parts['port'];
        return $origin;
    }

    // ================================================================
    // Registration: parse attestation, extract credential + public key
    // ================================================================

    /**
     * Verifies a navigator.credentials.create() response.
     *
     * @param string $client_data_json_b64 base64url clientDataJSON
     * @param string $attestation_b64      base64url attestationObject (CBOR)
     * @param string $expected_challenge   the base64url challenge we issued
     * @return array ['success'=>bool, 'message'=>..] on failure, or
     *               ['success'=>true, 'credential_id'=>b64url, 'public_key'=>PEM, 'sign_count'=>int]
     */
    public function verify_registration($client_data_json_b64, $attestation_b64, $expected_challenge) {
        $check = $this->_check_client_data($client_data_json_b64, 'webauthn.create', $expected_challenge);
        if ($check !== true) return ['success' => false, 'message' => $check];

        $attestation = $this->b64url_decode($attestation_b64);
        if ($attestation === false) return ['success' => false, 'message' => 'Invalid attestation encoding.'];

        $offset = 0;
        try {
            $att_obj = $this->cbor_decode($attestation, $offset);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Malformed attestation object (' . $e->getMessage() . ').'];
        }

        if (!is_array($att_obj) || !isset($att_obj['authData'])) {
            return ['success' => false, 'message' => 'Attestation object missing authData.'];
        }

        $auth = $this->_parse_auth_data($att_obj['authData']);
        if (isset($auth['error'])) return ['success' => false, 'message' => $auth['error']];

        if ($auth['rp_id_hash'] !== hash('sha256', $this->rp_id(), true)) {
            return ['success' => false, 'message' => 'Relying Party ID mismatch.'];
        }
        if (!($auth['flags'] & 0x01)) {
            return ['success' => false, 'message' => 'User presence flag not set.'];
        }
        if (empty($auth['credential_id']) || empty($auth['cose_key'])) {
            return ['success' => false, 'message' => 'No attested credential data found.'];
        }

        $pem = $this->_cose_to_pem($auth['cose_key']);
        if ($pem === false) {
            return ['success' => false, 'message' => 'Unsupported public key type (only ES256 / RS256 are supported).'];
        }

        return [
            'success'       => true,
            'credential_id' => $this->b64url_encode($auth['credential_id']),
            'public_key'    => $pem,
            'sign_count'    => $auth['sign_count'],
        ];
    }

    // ================================================================
    // Assertion: verify a navigator.credentials.get() response
    // ================================================================

    /**
     * @param string $client_data_json_b64 base64url clientDataJSON
     * @param string $auth_data_b64        base64url authenticatorData
     * @param string $signature_b64        base64url signature
     * @param string $public_key_pem       stored PEM public key
     * @param string $expected_challenge   the base64url challenge we issued
     * @return true|string true on success, error message string on failure
     */
    public function verify_assertion($client_data_json_b64, $auth_data_b64, $signature_b64, $public_key_pem, $expected_challenge) {
        $check = $this->_check_client_data($client_data_json_b64, 'webauthn.get', $expected_challenge);
        if ($check !== true) return $check;

        $auth_data_raw = $this->b64url_decode($auth_data_b64);
        $signature     = $this->b64url_decode($signature_b64);
        if ($auth_data_raw === false || strlen($auth_data_raw) < 37 || $signature === false) {
            return 'Malformed assertion data.';
        }

        $auth = $this->_parse_auth_data($auth_data_raw);
        if (isset($auth['error'])) return $auth['error'];

        if ($auth['rp_id_hash'] !== hash('sha256', $this->rp_id(), true)) {
            return 'Relying Party ID mismatch.';
        }
        if (!($auth['flags'] & 0x01)) {
            return 'User presence flag not set.';
        }
        if (!($auth['flags'] & 0x04)) {
            return 'Fingerprint (user verification) was not performed.';
        }

        $client_data_raw = $this->b64url_decode($client_data_json_b64);
        $signed_data     = $auth_data_raw . hash('sha256', $client_data_raw, true);

        $key = openssl_pkey_get_public($public_key_pem);
        if ($key === false) return 'Stored public key is invalid.';

        $ok = openssl_verify($signed_data, $signature, $key, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) return 'Signature verification failed.';

        return true;
    }

    /** Sign counter from a raw base64url authenticatorData blob. */
    public function extract_sign_count($auth_data_b64) {
        $raw = $this->b64url_decode($auth_data_b64);
        if ($raw === false || strlen($raw) < 37) return 0;
        return unpack('N', substr($raw, 33, 4))[1];
    }

    // ================================================================
    // Internals
    // ================================================================

    private function _check_client_data($client_data_json_b64, $expected_type, $expected_challenge) {
        $raw = $this->b64url_decode($client_data_json_b64);
        if ($raw === false) return 'Invalid clientDataJSON encoding.';

        $cd = json_decode($raw, true);
        if (!is_array($cd)) return 'clientDataJSON is not valid JSON.';

        if (($cd['type'] ?? '') !== $expected_type) {
            return 'Unexpected ceremony type.';
        }
        if (empty($expected_challenge) || !hash_equals($expected_challenge, (string)($cd['challenge'] ?? ''))) {
            return 'Challenge mismatch — please retry.';
        }
        if (($cd['origin'] ?? '') !== $this->expected_origin()) {
            return 'Origin mismatch (' . htmlspecialchars($cd['origin'] ?? '') . ').';
        }
        return true;
    }

    /**
     * authenticatorData layout:
     * rpIdHash(32) | flags(1) | signCount(4) | [attestedCredentialData] | [extensions]
     */
    private function _parse_auth_data($raw) {
        if (strlen($raw) < 37) return ['error' => 'authData too short.'];

        $out = [
            'rp_id_hash'    => substr($raw, 0, 32),
            'flags'         => ord($raw[32]),
            'sign_count'    => unpack('N', substr($raw, 33, 4))[1],
            'credential_id' => null,
            'cose_key'      => null,
        ];

        if ($out['flags'] & 0x40) { // AT flag: attested credential data present
            if (strlen($raw) < 55) return ['error' => 'authData attested data truncated.'];
            $cred_id_len = unpack('n', substr($raw, 53, 2))[1];
            if (strlen($raw) < 55 + $cred_id_len) return ['error' => 'Credential ID truncated.'];

            $out['credential_id'] = substr($raw, 55, $cred_id_len);

            $offset = 55 + $cred_id_len;
            try {
                $out['cose_key'] = $this->cbor_decode($raw, $offset);
            } catch (Exception $e) {
                return ['error' => 'Malformed COSE key (' . $e->getMessage() . ').'];
            }
        }

        return $out;
    }

    /** Convert a decoded COSE key map to an SPKI PEM public key. */
    private function _cose_to_pem($cose) {
        if (!is_array($cose) || !isset($cose[1])) return false;

        if ((int)$cose[1] === 2) { // EC2 — expect P-256 (ES256)
            if (!isset($cose[-2], $cose[-3])) return false;
            if (isset($cose[-1]) && (int)$cose[-1] !== 1) return false; // crv must be P-256
            $x = str_pad($cose[-2], 32, "\0", STR_PAD_LEFT);
            $y = str_pad($cose[-3], 32, "\0", STR_PAD_LEFT);
            // Fixed SPKI header for id-ecPublicKey + prime256v1, uncompressed point
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200')
                 . "\x04" . $x . $y;
            return $this->_pem_wrap($der);
        }

        if ((int)$cose[1] === 3) { // RSA (RS256)
            if (!isset($cose[-1], $cose[-2])) return false;
            $n = $this->_der_int($cose[-1]);
            $e = $this->_der_int($cose[-2]);
            $rsa_seq  = $this->_der_seq($n . $e);
            $bit_str  = $this->_der('03', "\x00" . $rsa_seq);
            $alg_seq  = $this->_der_seq(hex2bin('06092a864886f70d0101010500')); // rsaEncryption + NULL
            $der      = $this->_der_seq($alg_seq . $bit_str);
            return $this->_pem_wrap($der);
        }

        return false;
    }

    private function _pem_wrap($der) {
        return "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
    }

    private function _der($tag_hex, $content) {
        $len = strlen($content);
        if ($len < 0x80) {
            $len_bytes = chr($len);
        } else {
            $len_enc   = ltrim(pack('N', $len), "\0");
            $len_bytes = chr(0x80 | strlen($len_enc)) . $len_enc;
        }
        return hex2bin($tag_hex) . $len_bytes . $content;
    }

    private function _der_seq($content) {
        return $this->_der('30', $content);
    }

    private function _der_int($bin) {
        if ($bin === '' || (ord($bin[0]) & 0x80)) $bin = "\0" . $bin;
        return $this->_der('02', $bin);
    }

    // ================================================================
    // CBOR decoder — subset used by WebAuthn (RFC 8949)
    // ================================================================

    /**
     * Decodes one CBOR item starting at $offset; advances $offset past it.
     * Supports: uint, negint, byte string, text string, array, map,
     * tags (skipped), and the simple values false/true/null.
     */
    public function cbor_decode($data, &$offset) {
        if ($offset >= strlen($data)) {
            throw new Exception('unexpected end of data');
        }

        $initial = ord($data[$offset++]);
        $major   = $initial >> 5;
        $info    = $initial & 0x1f;

        // Resolve the "argument" (length / value)
        if ($info < 24) {
            $val = $info;
        } elseif ($info === 24) {
            $val = ord($this->_take($data, $offset, 1));
        } elseif ($info === 25) {
            $val = unpack('n', $this->_take($data, $offset, 2))[1];
        } elseif ($info === 26) {
            $val = unpack('N', $this->_take($data, $offset, 4))[1];
        } elseif ($info === 27) {
            $b = $this->_take($data, $offset, 8);
            $val = 0;
            for ($i = 0; $i < 8; $i++) $val = ($val << 8) | ord($b[$i]);
        } else {
            throw new Exception('unsupported additional info ' . $info);
        }

        switch ($major) {
            case 0: return $val;            // unsigned int
            case 1: return -1 - $val;       // negative int
            case 2: return $this->_take($data, $offset, $val); // byte string
            case 3: return $this->_take($data, $offset, $val); // text string
            case 4: // array
                $arr = [];
                for ($i = 0; $i < $val; $i++) $arr[] = $this->cbor_decode($data, $offset);
                return $arr;
            case 5: // map
                $map = [];
                for ($i = 0; $i < $val; $i++) {
                    $k = $this->cbor_decode($data, $offset);
                    $v = $this->cbor_decode($data, $offset);
                    $map[$k] = $v;
                }
                return $map;
            case 6: // tag — ignore, decode tagged item
                return $this->cbor_decode($data, $offset);
            case 7:
                if ($val === 20) return false;
                if ($val === 21) return true;
                if ($val === 22) return null;
                throw new Exception('unsupported simple/float value');
        }

        throw new Exception('unsupported major type ' . $major);
    }

    private function _take($data, &$offset, $len) {
        if ($offset + $len > strlen($data)) {
            throw new Exception('truncated data');
        }
        $chunk = substr($data, $offset, $len);
        $offset += $len;
        return $chunk;
    }
}
