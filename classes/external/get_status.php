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
 * External function for retrieving the status of a previously submitted request.
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
 * Class get_status - External function for querying JSON2Activity request status.
 */
class get_status extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_ALPHANUMEXT, 'Request ID (UUID v4)'),
            'courseid' => new external_value(PARAM_INT, 'Course ID (optional, for extra validation)',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Retrieve the status and items for a previously submitted request.
     *
     * @param string $requestid The request UUID.
     * @param int $courseid Optional course ID to validate ownership.
     * @return array The canonical nested response.
     */
    public static function execute(string $requestid, int $courseid = 0): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'requestid' => $requestid,
            'courseid' => $courseid,
        ]);

        $requestid = $params['requestid'];
        $courseid = $params['courseid'];

        // Build query conditions.
        $conditions = ['requestid' => $requestid];
        if ($courseid > 0) {
            $conditions['courseid'] = $courseid;
        }

        $req = $DB->get_record('local_json2activity_req', $conditions);
        if (!$req) {
            return self::format_not_found($requestid);
        }

        // Check capability on the course.
        $context = \context_course::instance($req->courseid);
        require_capability('local/json2activity:execute', $context);

        // Fetch item logs.
        $itemrecords = $DB->get_records(
            'local_json2activity_item',
            ['reqid' => $req->id],
            'itemindex ASC'
        );

        $items = [];
        foreach ($itemrecords as $itemrec) {
            $item = [
                'item_id'    => $itemrec->itemid ?? '',
                'status'     => $itemrec->status,
                'type'       => $itemrec->type,
                'section'    => (int)$itemrec->sectionnum,
                'cmid'       => (int)($itemrec->cmid ?? 0),
                'instanceid' => (int)($itemrec->instanceid ?? 0),
            ];
            if (!empty($itemrec->errorcode)) {
                $item['error'] = [
                    'code'    => $itemrec->errorcode,
                    'message' => $itemrec->errormessage ?? '',
                ];
            }
            $items[] = $item;
        }

        return [
            'request_id'    => $requestid,
            'courseid'      => (int)$req->courseid,
            'status'        => self::map_status($req->status, (bool)$req->dryrun),
            'created_count' => (int)$req->createdcount,
            'failed_count'  => (int)$req->failedcount,
            'items'         => $items,
            'debug'         => [
                'moodle_request_log_id' => (int)$req->id,
                'request_debug_url'     => $CFG->wwwroot
                    . '/local/json2activity/logs.php?requestid=' . urlencode($requestid),
            ],
        ];
    }

    /**
     * Map stored request status to canonical API status.
     *
     * @param string $status The status stored in the DB.
     * @param bool $dryrun Whether the original request was a dry-run.
     * @return string Canonical status string.
     */
    protected static function map_status(string $status, bool $dryrun): string {
        if ($dryrun) {
            $map = [
                'success'  => 'success',
                'partial'  => 'partial_success',
                'failed'   => 'failed',
                // Values stored with dry_run_ prefix (processor prefixes status with 'dry_run_').
                'dry_run_validated' => 'success',
                'dry_run_success'   => 'success',
                'dry_run_partial'   => 'partial_success',
                'dry_run_failed'    => 'failed',
            ];
        } else {
            $map = [
                'partial' => 'partial_success',
            ];
        }
        return $map[$status] ?? $status;
    }

    /**
     * Format a not-found error response.
     *
     * @param string $requestid The request ID.
     * @return array Formatted error result.
     */
    protected static function format_not_found(string $requestid): array {
        global $CFG;

        return [
            'request_id'    => $requestid,
            'courseid'      => 0,
            'status'        => 'rejected',
            'created_count' => 0,
            'failed_count'  => 0,
            'items'         => [],
            'debug'         => [
                'moodle_request_log_id' => 0,
                'request_debug_url'     => $CFG->wwwroot
                    . '/local/json2activity/logs.php?requestid=' . urlencode($requestid),
            ],
            'error'         => [
                'code'    => 'NOT_FOUND',
                'message' => get_string('error_request_not_found', 'local_json2activity'),
            ],
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'request_id'    => new external_value(PARAM_RAW, 'Request ID'),
            'courseid'      => new external_value(PARAM_INT, 'Course ID'),
            'status'        => new external_value(PARAM_ALPHANUMEXT, 'Overall status'),
            'created_count' => new external_value(PARAM_INT, 'Number of items created'),
            'failed_count'  => new external_value(PARAM_INT, 'Number of items failed'),
            'items'         => new external_multiple_structure(
                new external_single_structure([
                    'item_id'    => new external_value(PARAM_RAW, 'Item ID'),
                    'status'     => new external_value(PARAM_ALPHANUMEXT, 'Item status'),
                    'type'       => new external_value(PARAM_ALPHANUMEXT, 'Activity type'),
                    'section'    => new external_value(PARAM_INT, 'Section number'),
                    'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
                    'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
                    'error'      => new external_single_structure([
                        'code'    => new external_value(PARAM_RAW, 'Error code'),
                        'message' => new external_value(PARAM_RAW, 'Error message'),
                    ], 'Error details', VALUE_OPTIONAL),
                ])
            ),
            'debug' => new external_single_structure([
                'moodle_request_log_id' => new external_value(PARAM_INT, 'Moodle request log ID'),
                'request_debug_url'     => new external_value(PARAM_RAW, 'URL for debug log view'),
            ]),
            'error' => new external_single_structure([
                'code'    => new external_value(PARAM_RAW, 'Error code'),
                'message' => new external_value(PARAM_RAW, 'Error message'),
            ], 'Error details', VALUE_OPTIONAL),
        ]);
    }
}
