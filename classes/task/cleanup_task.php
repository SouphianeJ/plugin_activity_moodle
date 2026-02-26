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
 * Cleanup task for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_json2activity\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Task to clean up expired nonces and old logs.
 */
class cleanup_task extends \core\task\scheduled_task {

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'local_json2activity') . ' cleanup';
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        global $DB;

        // Clean up expired nonces.
        $DB->delete_records_select('local_json2activity_nonce', 'expiresat < ?', [time()]);

        // Clean up old logs.
        $retaindays = get_config('local_json2activity', 'retain_logs_days') ?: 30;
        $threshold = time() - ($retaindays * 86400);

        $todeleteids = $DB->get_fieldset_select('local_json2activity_req', 'id', 'receivedat < ?', [$threshold]);

        if ($todeleteids) {
            list($insql, $params) = $DB->get_in_or_equal($todeleteids);
            $DB->delete_records_select('local_json2activity_item', "reqid $insql", $params);
            $DB->delete_records_select('local_json2activity_req', "id $insql", $params);

            mtrace("Cleaned up " . count($todeleteids) . " old request logs.");
        }
    }
}
