<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Security service for JSON2Activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_json2activity\security;

defined('MOODLE_INTERNAL') || die();

/**
 * Class security_service - handles HMAC verification, IP allowlist, and anti-replay.
 */
class security_service {

    /**
     * Validate all security checks for an incoming request.
     *
     * @param string $clientid The client ID.
     * @param string $requestid The request ID.
     * @param int $timestamp The request timestamp.
     * @param string|null $nonce The nonce (optional).
     * @param string $signature The HMAC signature.
     * @param string $rawbody The raw request body.
     * @param string $remoteip The remote IP address.
     * @return \stdClass The client record if validation passes.
     * @throws \moodle_exception If any validation fails.
     */
    public function validate_request(string $clientid, string $requestid, int $timestamp,
            ?string $nonce, string $signature, string $rawbody, string $remoteip): \stdClass {

        // 1. Get and validate client.
        $client = $this->get_client($clientid);

        // 2. Check IP allowlist.
        $this->validate_ip($client, $remoteip);

        // 3. Check timestamp window.
        $this->validate_timestamp($timestamp);

        // 4. Check anti-replay (request_id).
        $this->validate_replay($clientid, $requestid);

        // 5. Check nonce if required.
        if (get_config('local_json2activity', 'require_nonce')) {
            $this->validate_nonce($clientid, $nonce);
        }

        // 6. Verify HMAC signature.
        $this->validate_signature($client, $timestamp, $requestid, $rawbody, $signature);

        return $client;
    }

    /**
     * Get and validate client.
     *
     * @param string $clientid The client ID.
     * @return \stdClass The client record.
     * @throws \moodle_exception If client not found or disabled.
     */
    public function get_client(string $clientid): \stdClass {
        global $DB;

        $client = $DB->get_record('local_json2activity_client', ['clientid' => $clientid]);
        if (!$client || !$client->enabled) {
            throw new \moodle_exception('error_client_not_found', 'local_json2activity');
        }

        return $client;
    }

    /**
     * Validate IP against allowlist.
     *
     * @param \stdClass $client The client record.
     * @param string $remoteip The remote IP address.
     * @throws \moodle_exception If IP not in allowlist.
     */
    public function validate_ip(\stdClass $client, string $remoteip): void {
        if (empty($client->allowedipranges)) {
            return; // No restrictions.
        }

        $ranges = array_filter(array_map('trim', explode("\n", $client->allowedipranges)));
        if (empty($ranges)) {
            return;
        }

        foreach ($ranges as $range) {
            if ($this->ip_in_cidr($remoteip, $range)) {
                return; // IP is allowed.
            }
        }

        throw new \moodle_exception('error_ip_not_allowed', 'local_json2activity');
    }

    /**
     * Check if IP is in CIDR range.
     *
     * @param string $ip The IP address.
     * @param string $cidr The CIDR notation (e.g., 192.168.1.0/24).
     * @return bool True if IP is in range.
     */
    protected function ip_in_cidr(string $ip, string $cidr): bool {
        $cidr = trim($cidr);

        // Handle simple IP without CIDR notation.
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        list($subnet, $mask) = explode('/', $cidr);

        // Handle IPv6.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6_in_cidr($ip, $subnet, (int)$mask);
        }

        // Handle IPv4.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $iplong = ip2long($ip);
        $subnetlong = ip2long($subnet);
        $masklong = -1 << (32 - (int)$mask);

        return ($iplong & $masklong) === ($subnetlong & $masklong);
    }

    /**
     * Check if IPv6 is in CIDR range.
     *
     * @param string $ip The IPv6 address.
     * @param string $subnet The subnet.
     * @param int $mask The mask bits.
     * @return bool True if IP is in range.
     */
    protected function ipv6_in_cidr(string $ip, string $subnet, int $mask): bool {
        $ipbin = inet_pton($ip);
        $subnetbin = inet_pton($subnet);

        if ($ipbin === false || $subnetbin === false) {
            return false;
        }

        $maskbin = str_repeat('1', $mask) . str_repeat('0', 128 - $mask);
        $maskbin = pack('H*', base_convert($maskbin, 2, 16));

        return ($ipbin & $maskbin) === ($subnetbin & $maskbin);
    }

    /**
     * Validate request timestamp.
     *
     * @param int $timestamp The request timestamp.
     * @throws \moodle_exception If timestamp outside acceptable window.
     */
    public function validate_timestamp(int $timestamp): void {
        $maxskew = get_config('local_json2activity', 'max_timestamp_skew') ?: 300;
        $now = time();

        if (abs($now - $timestamp) > $maxskew) {
            throw new \moodle_exception('error_invalid_timestamp', 'local_json2activity');
        }
    }

    /**
     * Validate request_id for anti-replay.
     *
     * @param string $clientid The client ID.
     * @param string $requestid The request ID.
     * @throws \moodle_exception If request_id already used.
     */
    public function validate_replay(string $clientid, string $requestid): void {
        global $DB;

        // Check if this request_id has been used before.
        $exists = $DB->record_exists('local_json2activity_req', ['requestid' => $requestid]);
        if ($exists) {
            throw new \moodle_exception('error_replay_detected', 'local_json2activity');
        }
    }

    /**
     * Validate nonce for anti-replay.
     *
     * @param string $clientid The client ID.
     * @param string|null $nonce The nonce.
     * @throws \moodle_exception If nonce missing or already used.
     */
    public function validate_nonce(string $clientid, ?string $nonce): void {
        global $DB;

        if (empty($nonce)) {
            throw new \moodle_exception('error_invalid_nonce', 'local_json2activity');
        }

        // Check if nonce was already used.
        $exists = $DB->record_exists('local_json2activity_nonce', [
            'clientid' => $clientid,
            'nonce' => $nonce,
        ]);

        if ($exists) {
            throw new \moodle_exception('error_replay_detected', 'local_json2activity');
        }

        // Store the nonce.
        $ttl = get_config('local_json2activity', 'nonce_ttl') ?: 86400;
        $DB->insert_record('local_json2activity_nonce', (object)[
            'clientid' => $clientid,
            'nonce' => $nonce,
            'expiresat' => time() + $ttl,
        ]);
    }

    /**
     * Validate HMAC signature.
     *
     * @param \stdClass $client The client record.
     * @param int $timestamp The timestamp.
     * @param string $requestid The request ID.
     * @param string $rawbody The raw body.
     * @param string $signature The signature to verify.
     * @throws \moodle_exception If signature invalid.
     */
    public function validate_signature(\stdClass $client, int $timestamp, string $requestid,
            string $rawbody, string $signature): void {
        $expected = $this->generate_signature($client->sharedsecret, $timestamp, $requestid, $rawbody);

        if (!hash_equals($expected, $signature)) {
            throw new \moodle_exception('error_invalid_signature', 'local_json2activity');
        }
    }

    /**
     * Generate HMAC signature.
     *
     * @param string $secret The shared secret.
     * @param int $timestamp The timestamp.
     * @param string $requestid The request ID.
     * @param string $rawbody The raw body.
     * @return string The base64-encoded signature.
     */
    public function generate_signature(string $secret, int $timestamp, string $requestid, string $rawbody): string {
        $bodyhash = hash('sha256', $rawbody);
        $canonical = $timestamp . '.' . $requestid . '.' . $bodyhash;
        $hmac = hash_hmac('sha256', $canonical, $secret, true);
        return base64_encode($hmac);
    }

    /**
     * Clean up expired nonces.
     */
    public function cleanup_expired_nonces(): void {
        global $DB;

        $DB->delete_records_select('local_json2activity_nonce', 'expiresat < ?', [time()]);
    }

    /**
     * Validate payload size.
     *
     * @param string $rawbody The raw body.
     * @throws \moodle_exception If payload too large.
     */
    public function validate_payload_size(string $rawbody): void {
        $maxsize = get_config('local_json2activity', 'max_payload_bytes') ?: 5242880;
        $size = strlen($rawbody);

        if ($size > $maxsize) {
            throw new \moodle_exception('error_payload_too_large', 'local_json2activity', '', $maxsize);
        }
    }

    /**
     * Log a rejected request.
     *
     * @param string $requestid The request ID.
     * @param string|null $clientid The client ID.
     * @param string|null $remoteip The remote IP.
     * @param string $reason The rejection reason.
     * @param bool $sigvalid Whether signature was valid.
     * @param bool $replayrejected Whether rejected as replay.
     */
    public function log_rejected_request(string $requestid, ?string $clientid, ?string $remoteip,
            string $reason, bool $sigvalid = false, bool $replayrejected = false): void {
        global $DB, $USER;

        $record = new \stdClass();
        $record->requestid = $requestid;
        $record->clientid = $clientid;
        $record->courseid = 0;
        $record->userid = $USER->id ?? 0;
        $record->remoteip = $remoteip;
        $record->receivedat = time();
        $record->status = 'rejected';
        $record->mode = 'partial';
        $record->dryrun = 0;
        $record->itemcount = 0;
        $record->createdcount = 0;
        $record->failedcount = 0;
        $record->sigvalid = $sigvalid ? 1 : 0;
        $record->replayrejected = $replayrejected ? 1 : 0;
        $record->errsummary = substr($reason, 0, 1000);

        $DB->insert_record('local_json2activity_req', $record);
    }
}
