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
 * Import form for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_json2activity\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for importing JSON payload.
 */
class importform extends \moodleform {

    /**
     * Define the form elements.
     */
    public function definition() {
        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('textarea', 'json', get_string('jsonpayload', 'local_json2activity'), 'rows="16" cols="100"');
        $mform->setType('json', PARAM_RAW);
        $mform->addRule('json', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('createactivity', 'local_json2activity'));
    }
}
