<?php
namespace local_json2activity\form;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/formslib.php');

class importform extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $courseid = $this->_customdata['courseid'];

        $m->addElement('hidden', 'courseid', $courseid);
        $m->setType('courseid', PARAM_INT);

        $m->addElement('textarea', 'json', get_string('jsonpayload', 'local_json2activity'), 'rows="16" cols="100"');
        $m->setType('json', PARAM_RAW);
        $m->addRule('json', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('createactivity', 'local_json2activity'));
    }
}
