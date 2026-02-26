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
 * External function for processing JSON payload.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_json2activity\external;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * Class process - External function for processing JSON2Activity requests.
 */
class process extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'payload' => new external_value(PARAM_RAW, 'JSON payload string'),
            'clientid' => new external_value(PARAM_ALPHANUMEXT, 'Client ID', VALUE_DEFAULT, ''),
            'requestid' => new external_value(PARAM_ALPHANUMEXT, 'Request ID (UUID v4)', VALUE_DEFAULT, ''),
            'timestamp' => new external_value(PARAM_INT, 'Request timestamp (epoch seconds)', VALUE_DEFAULT, 0),
            'nonce' => new external_value(PARAM_ALPHANUMEXT, 'Nonce for anti-replay', VALUE_DEFAULT, ''),
            'signature' => new external_value(PARAM_RAW, 'HMAC signature (base64)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Process a JSON payload to create activities.
     *
     * @param string $payload The JSON payload.
     * @param string $clientid The client ID.
     * @param string $requestid The request ID.
     * @param int $timestamp The timestamp.
     * @param string $nonce The nonce.
     * @param string $signature The signature.
     * @return array The result.
     */
    public static function execute(string $payload, string $clientid = '', string $requestid = '',
            int $timestamp = 0, string $nonce = '', string $signature = ''): array {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'payload' => $payload,
            'clientid' => $clientid,
            'requestid' => $requestid,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
        ]);

        $payload = $params['payload'];
        $clientid = $params['clientid'];
        $requestid = $params['requestid'];
        $timestamp = $params['timestamp'];
        $nonce = $params['nonce'];
        $signature = $params['signature'];

        // Get remote IP.
        $remoteip = getremoteaddr();

        // Security service.
        $security = new \local_json2activity\security\security_service();

        try {
            // Validate payload size.
            $security->validate_payload_size($payload);

            // Decode payload.
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            // Get request_id from payload if not provided.
            if (empty($requestid) && !empty($decoded['request_id'])) {
                $requestid = $decoded['request_id'];
            }

            // Generate request_id if still empty.
            if (empty($requestid)) {
                $requestid = self::generate_uuid();
            }

            // If client ID is provided, perform security validation.
            if (!empty($clientid)) {
                $client = $security->validate_request(
                    $clientid,
                    $requestid,
                    $timestamp ?: time(),
                    $nonce ?: null,
                    $signature,
                    $payload,
                    $remoteip
                );
            }

            // Process the payload.
            $processor = new \local_json2activity\processor();
            $result = $processor->process_payload(
                $decoded,
                $requestid,
                $USER->id,
                $clientid ?: null,
                $remoteip
            );

            return self::format_result($result);

        } catch (\JsonException $e) {
            $security->log_rejected_request($requestid ?: 'unknown', $clientid ?: null, $remoteip,
                'Invalid JSON: ' . $e->getMessage());

            return self::format_error($requestid ?: 'unknown', 'JSON_PARSE_ERROR', $e->getMessage());

        } catch (\moodle_exception $e) {
            $isreplay = strpos($e->errorcode, 'replay') !== false;
            $security->log_rejected_request($requestid ?: 'unknown', $clientid ?: null, $remoteip,
                $e->getMessage(), false, $isreplay);

            return self::format_error($requestid ?: 'unknown', $e->errorcode, $e->getMessage());

        } catch (\Throwable $e) {
            $security->log_rejected_request($requestid ?: 'unknown', $clientid ?: null, $remoteip,
                $e->getMessage());

            return self::format_error($requestid ?: 'unknown', 'INTERNAL_ERROR', $e->getMessage());
        }
    }

    /**
     * Map internal processor status to canonical API status.
     *
     * @param string $status The internal status string.
     * @return string The canonical status string.
     */
    protected static function map_status(string $status): string {
        $map = [
            'partial'           => 'partial_success',
            'dry_run_validated' => 'success',  // processor sets status='validated' for dry-run success.
            'dry_run_success'   => 'success',  // defensive alias.
            'dry_run_partial'   => 'partial_success',
            'dry_run_failed'    => 'failed',
        ];
        return $map[$status] ?? $status;
    }

    /**
     * Format successful result using canonical nested response shape.
     *
     * @param array $result The processor result.
     * @return array Formatted result.
     */
    protected static function format_result(array $result): array {
        global $CFG;

        $items = [];
        foreach ($result['items'] as $item) {
            $formatteditem = [
                'item_id' => $item['item_id'] ?? '',
                'status' => $item['status'],
                'type' => $item['type'],
                'section' => $item['section'],
                'cmid' => $item['cmid'] ?? 0,
                'instanceid' => $item['instanceid'] ?? 0,
            ];

            if (isset($item['error'])) {
                $formatteditem['error'] = [
                    'code' => $item['error']['code'] ?? '',
                    'message' => $item['error']['message'] ?? '',
                ];
            }

            $items[] = $formatteditem;
        }

        $requestid = $result['request_id'];

        return [
            'request_id' => $requestid,
            'courseid' => $result['courseid'],
            'status' => self::map_status($result['status']),
            'created_count' => $result['created_count'],
            'failed_count' => $result['failed_count'],
            'items' => $items,
            'debug' => [
                'moodle_request_log_id' => $result['debug']['moodle_request_log_id'] ?? 0,
                'request_debug_url' => $CFG->wwwroot . '/local/json2activity/logs.php?requestid='
                    . urlencode($requestid),
            ],
        ];
    }

    /**
     * Format error result using canonical nested response shape.
     *
     * @param string $requestid The request ID.
     * @param string $code The error code.
     * @param string $message The error message.
     * @return array Formatted error result.
     */
    protected static function format_error(string $requestid, string $code, string $message): array {
        global $CFG;

        return [
            'request_id' => $requestid,
            'courseid' => 0,
            'status' => 'rejected',
            'created_count' => 0,
            'failed_count' => 0,
            'items' => [],
            'debug' => [
                'moodle_request_log_id' => 0,
                'request_debug_url' => $CFG->wwwroot . '/local/json2activity/logs.php?requestid='
                    . urlencode($requestid),
            ],
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * Generate a UUID v4.
     *
     * @return string
     */
    protected static function generate_uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'request_id' => new external_value(PARAM_RAW, 'Request ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Overall status'),
            'created_count' => new external_value(PARAM_INT, 'Number of items created'),
            'failed_count' => new external_value(PARAM_INT, 'Number of items failed'),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'item_id' => new external_value(PARAM_RAW, 'Item ID'),
                    'status' => new external_value(PARAM_ALPHANUMEXT, 'Item status'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Activity type'),
                    'section' => new external_value(PARAM_INT, 'Section number'),
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                    'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
                    'error' => new external_single_structure([
                        'code' => new external_value(PARAM_RAW, 'Error code'),
                        'message' => new external_value(PARAM_RAW, 'Error message'),
                    ], 'Error details', VALUE_OPTIONAL),
                ])
            ),
            'debug' => new external_single_structure([
                'moodle_request_log_id' => new external_value(PARAM_INT, 'Moodle request log ID'),
                'request_debug_url' => new external_value(PARAM_RAW, 'URL for debug log view'),
            ]),
            'error' => new external_single_structure([
                'code' => new external_value(PARAM_RAW, 'Error code'),
                'message' => new external_value(PARAM_RAW, 'Error message'),
            ], 'Error details', VALUE_OPTIONAL),
        ]);
    }
}
