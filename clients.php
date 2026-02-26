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
 * API clients management page for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/json2activity:managelogs', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/json2activity/clients.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manageclients', 'local_json2activity'));
$PAGE->set_heading(get_string('manageclients', 'local_json2activity'));

// Handle delete.
if ($action === 'delete' && $id) {
    require_sesskey();
    $DB->delete_records('local_json2activity_client', ['id' => $id]);
    \core\notification::success(get_string('clientdeleted', 'local_json2activity'));
    redirect(new moodle_url('/local/json2activity/clients.php'));
}

// Handle form submission.
if ($action === 'save') {
    require_sesskey();

    $clientid = required_param('clientid', PARAM_ALPHANUMEXT);
    $sharedsecret = required_param('sharedsecret', PARAM_RAW);
    $allowedipranges = optional_param('allowedipranges', '', PARAM_RAW);
    $enabled = optional_param('enabled', 0, PARAM_INT);
    $editid = optional_param('editid', 0, PARAM_INT);

    $record = new stdClass();
    $record->clientid = $clientid;
    $record->sharedsecret = $sharedsecret;
    $record->allowedipranges = $allowedipranges;
    $record->enabled = $enabled ? 1 : 0;
    $record->timemodified = time();

    if ($editid) {
        $record->id = $editid;
        $DB->update_record('local_json2activity_client', $record);
        \core\notification::success(get_string('clientupdated', 'local_json2activity'));
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_json2activity_client', $record);
        \core\notification::success(get_string('clientcreated', 'local_json2activity'));
    }

    redirect(new moodle_url('/local/json2activity/clients.php'));
}

echo $OUTPUT->header();

// Edit/Add form.
if ($action === 'edit' || $action === 'add') {
    $client = null;
    if ($action === 'edit' && $id) {
        $client = $DB->get_record('local_json2activity_client', ['id' => $id]);
    }

    $formurl = new moodle_url('/local/json2activity/clients.php', ['action' => 'save', 'sesskey' => sesskey()]);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl, 'class' => 'mform']);

    if ($client) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'editid', 'value' => $client->id]);
    }

    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', get_string('clientid', 'local_json2activity'), [
        'class' => 'col-sm-3 col-form-label',
        'for' => 'clientid',
    ]);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'clientid',
        'id' => 'clientid',
        'class' => 'form-control',
        'value' => $client ? $client->clientid : '',
        'required' => 'required',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Generate secret button.
    $newsecret = bin2hex(random_bytes(32));

    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', get_string('sharedsecret', 'local_json2activity'), [
        'class' => 'col-sm-3 col-form-label',
        'for' => 'sharedsecret',
    ]);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::start_div('input-group');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'sharedsecret',
        'id' => 'sharedsecret',
        'class' => 'form-control',
        'value' => $client ? $client->sharedsecret : $newsecret,
        'required' => 'required',
    ]);
    echo html_writer::start_div('input-group-append');
    echo html_writer::tag('button', get_string('generatesecret', 'local_json2activity'), [
        'type' => 'button',
        'class' => 'btn btn-outline-secondary',
        'id' => 'generatesecret-btn',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // JavaScript to generate random secret on button click.
    echo html_writer::script("
        document.getElementById('generatesecret-btn').addEventListener('click', function() {
            var array = new Uint8Array(32);
            window.crypto.getRandomValues(array);
            var hex = Array.from(array, function(byte) {
                return ('0' + byte.toString(16)).slice(-2);
            }).join('');
            document.getElementById('sharedsecret').value = hex;
        });
    ");

    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', get_string('allowedipranges', 'local_json2activity'), [
        'class' => 'col-sm-3 col-form-label',
        'for' => 'allowedipranges',
    ]);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::tag('textarea', $client ? $client->allowedipranges : '', [
        'name' => 'allowedipranges',
        'id' => 'allowedipranges',
        'class' => 'form-control',
        'rows' => '4',
        'placeholder' => "192.168.1.0/24\n10.0.0.0/8",
    ]);
    echo html_writer::tag('small', 'One CIDR range per line. Leave empty to allow all IPs.', ['class' => 'form-text text-muted']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('form-group row');
    echo html_writer::start_div('col-sm-3');
    echo html_writer::end_div();
    echo html_writer::start_div('col-sm-9');
    echo html_writer::start_div('form-check');
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'name' => 'enabled',
        'id' => 'enabled',
        'class' => 'form-check-input',
        'value' => '1',
        'checked' => ($client ? $client->enabled : 1) ? 'checked' : null,
    ]);
    echo html_writer::tag('label', get_string('clientenabled', 'local_json2activity'), [
        'class' => 'form-check-label',
        'for' => 'enabled',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('form-group row');
    echo html_writer::start_div('col-sm-3');
    echo html_writer::end_div();
    echo html_writer::start_div('col-sm-9');
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savechanges'),
        'class' => 'btn btn-primary',
    ]);
    echo ' ';
    echo html_writer::link(
        new moodle_url('/local/json2activity/clients.php'),
        get_string('cancel'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}

// List clients.
echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url('/local/json2activity/clients.php', ['action' => 'add']),
    get_string('addclient', 'local_json2activity'),
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();

$clients = $DB->get_records('local_json2activity_client', [], 'clientid ASC');

if (!$clients) {
    echo html_writer::tag('p', get_string('norecordsfound', 'local_json2activity'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('clientid', 'local_json2activity'),
        get_string('allowedipranges', 'local_json2activity'),
        get_string('clientenabled', 'local_json2activity'),
        '',
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($clients as $client) {
        $ipranges = $client->allowedipranges ? nl2br(htmlspecialchars(substr($client->allowedipranges, 0, 100))) : '-';

        $editlink = html_writer::link(
            new moodle_url('/local/json2activity/clients.php', ['action' => 'edit', 'id' => $client->id]),
            get_string('edit'),
            ['class' => 'btn btn-sm btn-secondary']
        );

        $deleteurl = new moodle_url('/local/json2activity/clients.php', [
            'action' => 'delete',
            'id' => $client->id,
            'sesskey' => sesskey(),
        ]);
        $deletelink = html_writer::link(
            $deleteurl,
            get_string('delete'),
            [
                'class' => 'btn btn-sm btn-danger',
                'onclick' => "return confirm('" . get_string('areyousure', 'moodle') . "');",
            ]
        );

        $table->data[] = [
            html_writer::tag('code', $client->clientid),
            $ipranges,
            $client->enabled ? '✓' : '✗',
            $editlink . ' ' . $deletelink,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
