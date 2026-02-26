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
 * Main page for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url(new moodle_url('/local/json2activity/index.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('pluginname', 'local_json2activity'));
$PAGE->set_heading(format_string(get_course($courseid)->fullname));

require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');

$mform = new \local_json2activity\form\importform(null, ['courseid' => $courseid]);

echo $OUTPUT->header();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $mform->get_data()) {
    require_sesskey();

    try {
        $processor = new \local_json2activity\processor();
        $result = $processor->process_form_submission($data->json, $courseid);

        if ($result['created_count'] > 0) {
            \core\notification::success(
                get_string('activities_created', 'local_json2activity', $result['created_count'])
            );
        }
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $msg) {
                \core\notification::error($msg);
            }
        } else {
            redirect(new moodle_url('/course/view.php', ['id' => $courseid]), '', 0);
        }
    } catch (\Throwable $e) {
        \core\notification::error(get_string('error', 'moodle') . ': ' . $e->getMessage());
    }
}

$mform->display();
echo $OUTPUT->footer();
