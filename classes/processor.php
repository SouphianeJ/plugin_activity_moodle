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
 * Main processor for JSON2Activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_json2activity;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/modlib.php');
require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');

/**
 * Class processor - handles JSON processing and activity creation.
 */
class processor {

    /** @var array Supported activity types */
    protected const SUPPORTED_TYPES = ['label', 'page', 'url'];

    /** @var \stdClass The course object */
    protected $course;

    /** @var array Module IDs cache */
    protected $moduleids = [];

    /**
     * Process a JSON payload from form submission (legacy mode).
     *
     * @param string $json The JSON string.
     * @param int $courseid The course ID.
     * @return array Result array with created_count, failed_count, errors.
     */
    public function process_form_submission(string $json, int $courseid): array {
        global $DB;

        // Decode the JSON.
        $items = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // Support both old format (array of items) and new format (envelope with items).
        if (isset($items['items']) && is_array($items['items'])) {
            $itemsarray = $items['items'];
        } elseif (is_array($items) && !isset($items['schema'])) {
            // Old format - direct array of items.
            $itemsarray = $items;
        } else {
            throw new \moodle_exception('Input must be a JSON array of items or envelope with items.');
        }

        $this->course = get_course($courseid);

        // Prepare sections.
        $this->prepare_sections($itemsarray);

        // Cache module IDs.
        $this->cache_module_ids();

        $created = [];
        $errors = [];

        foreach ($itemsarray as $i => $item) {
            try {
                $result = $this->process_item($item, $i);
                $created[] = $result;
            } catch (\Throwable $e) {
                $errors[] = "Item #$i: " . $e->getMessage();
            }
        }

        return [
            'created_count' => count($created),
            'failed_count' => count($errors),
            'created' => $created,
            'errors' => $errors,
        ];
    }

    /**
     * Process a full JSON payload with envelope (v1 schema).
     *
     * @param array $payload The decoded payload.
     * @param string $requestid The request ID.
     * @param int|null $userid The user ID (from WS token).
     * @param string|null $clientid The client ID.
     * @param string|null $remoteip The remote IP.
     * @return array Full result array.
     */
    public function process_payload(array $payload, string $requestid, ?int $userid = null,
            ?string $clientid = null, ?string $remoteip = null): array {
        global $DB, $USER;

        $starttime = microtime(true);

        if ($userid === null) {
            $userid = $USER->id;
        }

        // Validate schema.
        if (empty($payload['schema']) || $payload['schema'] !== 'json2activity/v1') {
            throw new \moodle_exception('error_invalid_schema', 'local_json2activity');
        }

        // Validate request_id.
        if (empty($payload['request_id']) || $payload['request_id'] !== $requestid) {
            throw new \moodle_exception('error_invalid_requestid', 'local_json2activity');
        }

        // Validate courseid.
        if (empty($payload['courseid']) || !is_numeric($payload['courseid'])) {
            throw new \moodle_exception('error_invalid_courseid', 'local_json2activity');
        }
        $courseid = (int)$payload['courseid'];

        // Check course exists.
        $this->course = $DB->get_record('course', ['id' => $courseid]);
        if (!$this->course) {
            throw new \moodle_exception('error_course_not_found', 'local_json2activity');
        }

        // Get settings.
        $maxitems = get_config('local_json2activity', 'max_items_per_request') ?: 200;
        $defaultmode = get_config('local_json2activity', 'default_mode') ?: 'partial';

        // Validate items.
        if (empty($payload['items']) || !is_array($payload['items'])) {
            throw new \moodle_exception('error_no_items', 'local_json2activity');
        }

        if (count($payload['items']) > $maxitems) {
            throw new \moodle_exception('error_too_many_items', 'local_json2activity', '', $maxitems);
        }

        $dryrun = !empty($payload['dry_run']);
        $mode = $payload['mode'] ?? $defaultmode;

        // Check capability.
        $context = \context_course::instance($courseid);
        require_capability('moodle/course:manageactivities', $context);

        // Create request log.
        $reqlog = $this->create_request_log($requestid, $clientid, $courseid, $userid,
            $remoteip, $mode, $dryrun, count($payload['items']), $payload);

        // Prepare sections.
        $this->prepare_sections($payload['items']);

        // Cache module IDs.
        $this->cache_module_ids();

        $items = [];
        $createdcount = 0;
        $failedcount = 0;

        if ($mode === 'atomic') {
            $transaction = $DB->start_delegated_transaction();
        }

        try {
            foreach ($payload['items'] as $i => $item) {
                $itemresult = $this->process_item_with_logging($item, $i, $reqlog->id, $dryrun);
                $items[] = $itemresult;

                if ($itemresult['status'] === 'created' || $itemresult['status'] === 'validated') {
                    $createdcount++;
                } else {
                    $failedcount++;
                    if ($mode === 'atomic') {
                        throw new \moodle_exception('Atomic mode: item failed, rolling back');
                    }
                }
            }

            if (isset($transaction)) {
                $transaction->allow_commit();
            }
        } catch (\Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            $failedcount = count($payload['items']);
            $createdcount = 0;
        }

        // Determine overall status.
        if ($failedcount === 0) {
            $status = $dryrun ? 'validated' : 'success';
        } elseif ($createdcount === 0) {
            $status = 'failed';
        } else {
            $status = 'partial';
        }

        $durationms = (int)((microtime(true) - $starttime) * 1000);

        // Update request log.
        $this->update_request_log($reqlog->id, $status, $createdcount, $failedcount, $durationms, $items);

        return [
            'request_id' => $requestid,
            'courseid' => $courseid,
            'status' => $dryrun ? 'dry_run_' . $status : $status,
            'created_count' => $createdcount,
            'failed_count' => $failedcount,
            'items' => $items,
            'debug' => [
                'moodle_request_log_id' => $reqlog->id,
            ],
        ];
    }

    /**
     * Prepare sections for all items.
     *
     * @param array $items The items array.
     */
    protected function prepare_sections(array $items): void {
        $sections = [];
        foreach ($items as $item) {
            $sec = isset($item['section']) ? (int)$item['section'] : 0;
            $sections[] = max(0, $sec);
        }
        $sections = array_values(array_unique($sections));
        if ($sections) {
            course_create_sections_if_missing($this->course->id, $sections);
        }
    }

    /**
     * Cache module IDs for supported types.
     */
    protected function cache_module_ids(): void {
        global $DB;

        foreach (self::SUPPORTED_TYPES as $type) {
            $this->moduleids[$type] = (int)$DB->get_field('modules', 'id', ['name' => $type]);
        }
    }

    /**
     * Process a single item.
     *
     * @param array $item The item data.
     * @param int $index The item index.
     * @return array The result array.
     */
    protected function process_item(array $item, int $index): array {
        // Validate activity structure.
        if (empty($item['activity']) || !is_array($item['activity'])) {
            throw new \moodle_exception("Missing 'activity' object");
        }

        $activity = $item['activity'];
        $type = $activity['type'] ?? null;
        $data = $activity['data'] ?? $activity; // Support both nested and flat data.

        if (empty($type)) {
            throw new \moodle_exception("Missing activity type");
        }

        if (!in_array($type, self::SUPPORTED_TYPES)) {
            throw new \moodle_exception(
                get_string('error_invalid_activity_type', 'local_json2activity', $type)
            );
        }

        $sectionnum = isset($item['section']) ? max(0, (int)$item['section']) : 0;

        // Build module info.
        $mi = $this->build_module_info($type, $data, $sectionnum);

        // Create the activity.
        $result = add_moduleinfo($mi, $this->course, null);

        return [
            'index' => $index,
            'item_id' => $item['item_id'] ?? null,
            'type' => $type,
            'section' => $sectionnum,
            'cmid' => $result->coursemodule,
            'instanceid' => $result->instance,
        ];
    }

    /**
     * Process item with logging for v1 schema.
     *
     * @param array $item The item data.
     * @param int $index The item index.
     * @param int $reqid The request log ID.
     * @param bool $dryrun Whether this is a dry run.
     * @return array The result array.
     */
    protected function process_item_with_logging(array $item, int $index, int $reqid, bool $dryrun): array {
        global $DB;

        $itemid = $item['item_id'] ?? null;
        $sectionnum = isset($item['section']) ? max(0, (int)$item['section']) : 0;
        $type = $item['activity']['type'] ?? 'unknown';

        // Create item log.
        $itemlog = new \stdClass();
        $itemlog->reqid = $reqid;
        $itemlog->itemindex = $index;
        $itemlog->itemid = $itemid;
        $itemlog->sectionnum = $sectionnum;
        $itemlog->type = $type;
        $itemlog->status = 'pending';

        $itemlogid = $DB->insert_record('local_json2activity_item', $itemlog);

        try {
            // Validate activity structure.
            if (empty($item['activity']) || !is_array($item['activity'])) {
                throw new \moodle_exception('VALIDATION_ERROR', 'local_json2activity', '', null,
                    "Missing 'activity' object");
            }

            $activity = $item['activity'];
            $data = $activity['data'] ?? $activity;

            if (empty($type) || $type === 'unknown') {
                throw new \moodle_exception('VALIDATION_ERROR', 'local_json2activity', '', null,
                    "Missing activity type");
            }

            if (!in_array($type, self::SUPPORTED_TYPES)) {
                throw new \moodle_exception('UNSUPPORTED_TYPE', 'local_json2activity', '', null,
                    "Unsupported activity type: $type");
            }

            // Build module info.
            $mi = $this->build_module_info($type, $data, $sectionnum);

            if ($dryrun) {
                // Dry run - just validate.
                $status = 'validated';
                $cmid = null;
                $instanceid = null;
            } else {
                // Create the activity.
                $result = add_moduleinfo($mi, $this->course, null);
                $status = 'created';
                $cmid = $result->coursemodule;
                $instanceid = $result->instance;
            }

            // Update item log.
            $DB->update_record('local_json2activity_item', (object)[
                'id' => $itemlogid,
                'status' => $status,
                'cmid' => $cmid,
                'instanceid' => $instanceid,
            ]);

            return [
                'item_id' => $itemid,
                'status' => $status,
                'type' => $type,
                'section' => $sectionnum,
                'cmid' => $cmid,
                'instanceid' => $instanceid,
            ];

        } catch (\Throwable $e) {
            $errorcode = 'PROCESSING_ERROR';
            if (strpos($e->getMessage(), 'VALIDATION_ERROR') !== false) {
                $errorcode = 'VALIDATION_ERROR';
            } elseif (strpos($e->getMessage(), 'UNSUPPORTED_TYPE') !== false) {
                $errorcode = 'UNSUPPORTED_TYPE';
            }

            $storestacktraces = get_config('local_json2activity', 'store_stacktraces');
            $debuginfo = $storestacktraces ? $e->getTraceAsString() : null;

            $DB->update_record('local_json2activity_item', (object)[
                'id' => $itemlogid,
                'status' => 'failed',
                'errorcode' => $errorcode,
                'errormessage' => substr($e->getMessage(), 0, 1000),
                'debuginfo' => $debuginfo ? substr($debuginfo, 0, 5000) : null,
            ]);

            return [
                'item_id' => $itemid,
                'status' => 'failed',
                'type' => $type,
                'section' => $sectionnum,
                'error' => [
                    'code' => $errorcode,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Build module info object for add_moduleinfo().
     *
     * @param string $type The activity type.
     * @param array $data The activity data.
     * @param int $sectionnum The section number.
     * @return \stdClass The module info object.
     */
    protected function build_module_info(string $type, array $data, int $sectionnum): \stdClass {
        // Check for script tags if configured.
        $rejectscripts = get_config('local_json2activity', 'reject_script_tags');
        if ($rejectscripts) {
            $this->check_for_script_tags($data);
        }

        $mi = new \stdClass();

        // Common fields.
        $mi->modulename = $type;
        $mi->module = $this->moduleids[$type] ?? 0;
        if ($mi->module === 0) {
            throw new \moodle_exception("Module '$type' not found in Moodle");
        }
        $mi->coursemodule = 0;
        $mi->course = $this->course->id;
        $mi->section = $sectionnum;
        $mi->cmidnumber = '';
        $mi->groupmode = $this->course->groupmode ?? 0;
        $mi->groupingid = 0;
        $mi->availability = null;
        $mi->completion = !empty($this->course->enablecompletion) ? 1 : 0;
        $mi->visible = isset($data['visible']) ? (int)!empty($data['visible']) : 1;
        $mi->visibleoncoursepage = 1;
        $mi->showdescription = 0;

        // Type-specific fields.
        switch ($type) {
            case 'label':
                $mi->intro = $data['html'] ?? '';
                $mi->introformat = FORMAT_HTML;
                break;

            case 'page':
                if (empty($data['name'])) {
                    throw new \moodle_exception(
                        get_string('error_missing_field', 'local_json2activity', 'name')
                    );
                }
                $mi->name = $data['name'];
                $mi->content = $data['html'] ?? $data['content'] ?? '';
                $mi->contentformat = FORMAT_HTML;
                $mi->intro = $data['intro'] ?? '';
                $mi->introformat = FORMAT_HTML;
                $mi->display = $data['display'] ?? 5; // RESOURCELIB_DISPLAY_OPEN.
                $mi->printintro = $data['printintro'] ?? 0;
                $mi->printlastmodified = $data['printlastmodified'] ?? 1;
                break;

            case 'url':
                if (empty($data['name'])) {
                    throw new \moodle_exception(
                        get_string('error_missing_field', 'local_json2activity', 'name')
                    );
                }
                if (empty($data['url'])) {
                    throw new \moodle_exception(
                        get_string('error_missing_field', 'local_json2activity', 'url')
                    );
                }
                $mi->name = $data['name'];
                $mi->externalurl = $data['url'];
                $mi->intro = $data['intro'] ?? '';
                $mi->introformat = FORMAT_HTML;
                $mi->display = $data['display'] ?? 0;
                break;

            default:
                throw new \moodle_exception(
                    get_string('error_invalid_activity_type', 'local_json2activity', $type)
                );
        }

        return $mi;
    }

    /**
     * Check for script tags in data.
     *
     * @param array $data The data to check.
     * @throws \moodle_exception If script tags found.
     */
    protected function check_for_script_tags(array $data): void {
        $json = json_encode($data);
        if (preg_match('/<script[^>]*>/i', $json)) {
            throw new \moodle_exception('error_script_tags', 'local_json2activity');
        }
    }

    /**
     * Create request log entry.
     *
     * @param string $requestid The request ID.
     * @param string|null $clientid The client ID.
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @param string|null $remoteip The remote IP.
     * @param string $mode The processing mode.
     * @param bool $dryrun Whether this is a dry run.
     * @param int $itemcount The item count.
     * @param array $payload The full payload.
     * @return \stdClass The created log record.
     */
    protected function create_request_log(string $requestid, ?string $clientid, int $courseid,
            int $userid, ?string $remoteip, string $mode, bool $dryrun, int $itemcount, array $payload): \stdClass {
        global $DB;

        $storepayload = get_config('local_json2activity', 'store_payload') ?: 'full';
        $payloadjson = json_encode($payload);
        $payloadhash = hash('sha256', $payloadjson);

        switch ($storepayload) {
            case 'hash':
                $payloadjson = null;
                break;
            case 'truncated':
                $payloadjson = substr($payloadjson, 0, 10240);
                break;
            // 'full' - keep as is.
        }

        $record = new \stdClass();
        $record->requestid = $requestid;
        $record->clientid = $clientid;
        $record->courseid = $courseid;
        $record->userid = $userid;
        $record->remoteip = $remoteip;
        $record->receivedat = time();
        $record->status = 'processing';
        $record->mode = $mode;
        $record->dryrun = $dryrun ? 1 : 0;
        $record->itemcount = $itemcount;
        $record->createdcount = 0;
        $record->failedcount = 0;
        $record->payloadhash = $payloadhash;
        $record->payloadjson = $payloadjson;
        $record->sigvalid = 1;
        $record->replayrejected = 0;

        $record->id = $DB->insert_record('local_json2activity_req', $record);

        return $record;
    }

    /**
     * Update request log after processing.
     *
     * @param int $id The log ID.
     * @param string $status The final status.
     * @param int $createdcount The created count.
     * @param int $failedcount The failed count.
     * @param int $durationms The duration in ms.
     * @param array $items The items result.
     */
    protected function update_request_log(int $id, string $status, int $createdcount,
            int $failedcount, int $durationms, array $items): void {
        global $DB;

        $storepayload = get_config('local_json2activity', 'store_payload') ?: 'full';
        $responsejson = json_encode([
            'status' => $status,
            'created_count' => $createdcount,
            'failed_count' => $failedcount,
            'items' => $items,
        ]);

        if ($storepayload === 'hash') {
            $responsejson = null;
        } elseif ($storepayload === 'truncated') {
            $responsejson = substr($responsejson, 0, 10240);
        }

        $errsummary = null;
        foreach ($items as $item) {
            if (isset($item['error'])) {
                $errsummary = ($errsummary ?? '') . $item['error']['message'] . '; ';
            }
        }
        if ($errsummary) {
            $errsummary = substr($errsummary, 0, 1000);
        }

        $DB->update_record('local_json2activity_req', (object)[
            'id' => $id,
            'status' => $status,
            'createdcount' => $createdcount,
            'failedcount' => $failedcount,
            'durationms' => $durationms,
            'responsejson' => $responsejson,
            'errsummary' => $errsummary,
        ]);
    }
}
