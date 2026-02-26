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
 * Web service definitions for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_json2activity_process' => [
        'classname'     => 'local_json2activity\external\process',
        'methodname'    => 'execute',
        'description'   => 'Process a JSON payload to create activities in a Moodle course.',
        'type'          => 'write',
        'ajax'          => false,
        'capabilities'  => 'local/json2activity:execute',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE, 'json2activity'],
    ],
    'local_json2activity_get_status' => [
        'classname'     => 'local_json2activity\external\get_status',
        'methodname'    => 'execute',
        'description'   => 'Retrieve the status and items of a previously submitted JSON2Activity request.',
        'type'          => 'read',
        'ajax'          => false,
        'capabilities'  => 'local/json2activity:execute',
        'services'      => ['json2activity'],
    ],
];

$services = [
    'JSON2Activity API' => [
        'functions' => ['local_json2activity_process', 'local_json2activity_get_status'],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'json2activity',
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
