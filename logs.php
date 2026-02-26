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
 * Request logs page for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/json2activity:viewlogs', $context);

$requestid = optional_param('requestid', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/json2activity/logs.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('debuglogs', 'local_json2activity'));
$PAGE->set_heading(get_string('debuglogs', 'local_json2activity'));

$canmanage = has_capability('local/json2activity:managelogs', $context);

// Handle purge action.
if ($action === 'purge' && $canmanage) {
    require_sesskey();
    $retaindays = get_config('local_json2activity', 'retain_logs_days') ?: 30;
    $threshold = time() - ($retaindays * 86400);

    // Get request IDs to delete.
    $todeleteids = $DB->get_fieldset_select('local_json2activity_req', 'id', 'receivedat < ?', [$threshold]);

    if ($todeleteids) {
        // Delete items first.
        list($insql, $params) = $DB->get_in_or_equal($todeleteids);
        $DB->delete_records_select('local_json2activity_item', "reqid $insql", $params);
        // Delete requests.
        $DB->delete_records_select('local_json2activity_req', "id $insql", $params);
    }

    // Clean up expired nonces.
    $DB->delete_records_select('local_json2activity_nonce', 'expiresat < ?', [time()]);

    \core\notification::success(get_string('purge_success', 'local_json2activity', count($todeleteids)));
    redirect(new moodle_url('/local/json2activity/logs.php'));
}

echo $OUTPUT->header();

// View single request detail.
if ($requestid) {
    $request = $DB->get_record('local_json2activity_req', ['requestid' => $requestid]);
    if (!$request) {
        \core\notification::error(get_string('norecordsfound', 'local_json2activity'));
        echo $OUTPUT->footer();
        exit;
    }

    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', get_string('requestdetails', 'local_json2activity'));
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    $table = new html_table();
    $table->attributes['class'] = 'table table-striped';
    $table->data = [
        [get_string('requestid', 'local_json2activity'), $request->requestid],
        [get_string('clientidlabel', 'local_json2activity'), $request->clientid ?: '-'],
        [get_string('courseidlabel', 'local_json2activity'), $request->courseid],
        [get_string('receivedat', 'local_json2activity'), userdate($request->receivedat)],
        [get_string('status', 'local_json2activity'), get_string('status_' . $request->status, 'local_json2activity')],
        [get_string('items', 'local_json2activity'), $request->itemcount],
        [get_string('created', 'local_json2activity'), $request->createdcount],
        [get_string('failed', 'local_json2activity'), $request->failedcount],
        [get_string('duration', 'local_json2activity'), $request->durationms . ' ms'],
    ];
    echo html_writer::table($table);

    // Show payload if user can manage.
    if ($canmanage && $request->payloadjson) {
        echo html_writer::tag('h6', get_string('payloadjson', 'local_json2activity'));
        echo html_writer::tag('pre', htmlspecialchars(
            json_encode(json_decode($request->payloadjson), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ), ['class' => 'p-3 bg-light border rounded', 'style' => 'max-height: 400px; overflow: auto;']);
    }

    // Show response.
    if ($request->responsejson) {
        echo html_writer::tag('h6', get_string('responsejson', 'local_json2activity'));
        echo html_writer::tag('pre', htmlspecialchars(
            json_encode(json_decode($request->responsejson), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ), ['class' => 'p-3 bg-light border rounded', 'style' => 'max-height: 400px; overflow: auto;']);
    }

    // Show error summary.
    if ($request->errsummary) {
        echo html_writer::tag('h6', get_string('errsummary', 'local_json2activity'));
        echo html_writer::tag('div', htmlspecialchars($request->errsummary), ['class' => 'alert alert-danger']);
    }

    echo html_writer::end_div();
    echo html_writer::end_div();

    // Show items.
    $items = $DB->get_records('local_json2activity_item', ['reqid' => $request->id], 'itemindex ASC');
    if ($items) {
        echo html_writer::tag('h5', get_string('itemdetails', 'local_json2activity'));

        $table = new html_table();
        $table->head = [
            '#',
            get_string('itemid', 'local_json2activity'),
            get_string('type', 'local_json2activity'),
            get_string('section', 'local_json2activity'),
            get_string('status', 'local_json2activity'),
            get_string('cmid', 'local_json2activity'),
            get_string('error', 'local_json2activity'),
        ];
        $table->attributes['class'] = 'table table-sm table-striped';

        foreach ($items as $item) {
            $statusclass = '';
            if ($item->status === 'created' || $item->status === 'validated') {
                $statusclass = 'text-success';
            } elseif ($item->status === 'failed') {
                $statusclass = 'text-danger';
            }

            $table->data[] = [
                $item->itemindex,
                $item->itemid ?: '-',
                $item->type,
                $item->sectionnum,
                html_writer::tag('span', $item->status, ['class' => $statusclass]),
                $item->cmid ?: '-',
                $item->errormessage ? html_writer::tag('small', substr($item->errormessage, 0, 100)) : '-',
            ];
        }

        echo html_writer::table($table);
    }

    echo html_writer::link(
        new moodle_url('/local/json2activity/logs.php'),
        get_string('back'),
        ['class' => 'btn btn-secondary']
    );

    echo $OUTPUT->footer();
    exit;
}

// List all requests.
$totalcount = $DB->count_records('local_json2activity_req');

$requests = $DB->get_records_sql(
    "SELECT * FROM {local_json2activity_req} ORDER BY receivedat DESC",
    [],
    $page * $perpage,
    $perpage
);

// Purge button.
if ($canmanage) {
    $retaindays = get_config('local_json2activity', 'retain_logs_days') ?: 30;
    echo html_writer::start_div('mb-3');
    $purgeurl = new moodle_url('/local/json2activity/logs.php', ['action' => 'purge', 'sesskey' => sesskey()]);
    echo html_writer::link(
        $purgeurl,
        get_string('purge_now', 'local_json2activity'),
        [
            'class' => 'btn btn-warning',
            'onclick' => "return confirm('" . get_string('purge_confirm', 'local_json2activity', $retaindays) . "');",
        ]
    );
    echo html_writer::end_div();
}

if (!$requests) {
    echo html_writer::tag('p', get_string('norecordsfound', 'local_json2activity'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('requestid', 'local_json2activity'),
        get_string('clientidlabel', 'local_json2activity'),
        get_string('courseidlabel', 'local_json2activity'),
        get_string('receivedat', 'local_json2activity'),
        get_string('status', 'local_json2activity'),
        get_string('items', 'local_json2activity'),
        get_string('duration', 'local_json2activity'),
        '',
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($requests as $req) {
        $statusclass = '';
        if ($req->status === 'success') {
            $statusclass = 'badge badge-success bg-success';
        } elseif ($req->status === 'partial') {
            $statusclass = 'badge badge-warning bg-warning';
        } elseif ($req->status === 'failed' || $req->status === 'rejected') {
            $statusclass = 'badge badge-danger bg-danger';
        } else {
            $statusclass = 'badge badge-secondary bg-secondary';
        }

        $viewlink = html_writer::link(
            new moodle_url('/local/json2activity/logs.php', ['requestid' => $req->requestid]),
            get_string('viewdetails', 'local_json2activity'),
            ['class' => 'btn btn-sm btn-primary']
        );

        $table->data[] = [
            html_writer::tag('code', substr($req->requestid, 0, 8) . '...'),
            $req->clientid ?: '-',
            $req->courseid ?: '-',
            userdate($req->receivedat, get_string('strftimedatetime')),
            html_writer::tag('span', $req->status, ['class' => $statusclass]),
            $req->createdcount . '/' . $req->itemcount,
            ($req->durationms ?: '-') . ' ms',
            $viewlink,
        ];
    }

    echo html_writer::table($table);

    // Pagination.
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, new moodle_url('/local/json2activity/logs.php'));
}

echo $OUTPUT->footer();
