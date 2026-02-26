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
 * Settings for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_json2activity', get_string('pluginname', 'local_json2activity'));
    $ADMIN->add('localplugins', $settings);

    // Security settings header.
    $settings->add(new admin_setting_heading(
        'local_json2activity/security_heading',
        get_string('settings_heading_security', 'local_json2activity'),
        get_string('settings_heading_security_desc', 'local_json2activity')
    ));

    // Max timestamp skew.
    $settings->add(new admin_setting_configtext(
        'local_json2activity/max_timestamp_skew',
        get_string('setting_max_timestamp_skew', 'local_json2activity'),
        get_string('setting_max_timestamp_skew_desc', 'local_json2activity'),
        300,
        PARAM_INT
    ));

    // Require nonce.
    $settings->add(new admin_setting_configcheckbox(
        'local_json2activity/require_nonce',
        get_string('setting_require_nonce', 'local_json2activity'),
        get_string('setting_require_nonce_desc', 'local_json2activity'),
        0
    ));

    // Nonce TTL.
    $settings->add(new admin_setting_configtext(
        'local_json2activity/nonce_ttl',
        get_string('setting_nonce_ttl', 'local_json2activity'),
        get_string('setting_nonce_ttl_desc', 'local_json2activity'),
        86400,
        PARAM_INT
    ));

    // Max payload size (default 5MB).
    $settings->add(new admin_setting_configtext(
        'local_json2activity/max_payload_bytes',
        get_string('setting_max_payload_bytes', 'local_json2activity'),
        get_string('setting_max_payload_bytes_desc', 'local_json2activity'),
        5 * 1024 * 1024,
        PARAM_INT
    ));

    // Max items per request.
    $settings->add(new admin_setting_configtext(
        'local_json2activity/max_items_per_request',
        get_string('setting_max_items_per_request', 'local_json2activity'),
        get_string('setting_max_items_per_request_desc', 'local_json2activity'),
        200,
        PARAM_INT
    ));

    // Reject script tags.
    $settings->add(new admin_setting_configcheckbox(
        'local_json2activity/reject_script_tags',
        get_string('setting_reject_script_tags', 'local_json2activity'),
        get_string('setting_reject_script_tags_desc', 'local_json2activity'),
        1
    ));

    // Store payload mode.
    $settings->add(new admin_setting_configselect(
        'local_json2activity/store_payload',
        get_string('setting_store_payload', 'local_json2activity'),
        get_string('setting_store_payload_desc', 'local_json2activity'),
        'full',
        [
            'full' => get_string('setting_store_payload_full', 'local_json2activity'),
            'hash' => get_string('setting_store_payload_hash', 'local_json2activity'),
            'truncated' => get_string('setting_store_payload_truncated', 'local_json2activity'),
        ]
    ));

    // Store stacktraces.
    $settings->add(new admin_setting_configcheckbox(
        'local_json2activity/store_stacktraces',
        get_string('setting_store_stacktraces', 'local_json2activity'),
        get_string('setting_store_stacktraces_desc', 'local_json2activity'),
        0
    ));

    // Processing settings header.
    $settings->add(new admin_setting_heading(
        'local_json2activity/processing_heading',
        get_string('settings_heading_processing', 'local_json2activity'),
        get_string('settings_heading_processing_desc', 'local_json2activity')
    ));

    // Default mode.
    $settings->add(new admin_setting_configselect(
        'local_json2activity/default_mode',
        get_string('setting_default_mode', 'local_json2activity'),
        get_string('setting_default_mode_desc', 'local_json2activity'),
        'partial',
        [
            'partial' => get_string('setting_mode_partial', 'local_json2activity'),
            'atomic' => get_string('setting_mode_atomic', 'local_json2activity'),
        ]
    ));

    // Async threshold.
    $settings->add(new admin_setting_configtext(
        'local_json2activity/async_threshold',
        get_string('setting_async_threshold', 'local_json2activity'),
        get_string('setting_async_threshold_desc', 'local_json2activity'),
        50,
        PARAM_INT
    ));

    // Retention settings header.
    $settings->add(new admin_setting_heading(
        'local_json2activity/retention_heading',
        get_string('settings_heading_retention', 'local_json2activity'),
        get_string('settings_heading_retention_desc', 'local_json2activity')
    ));

    // Retain logs days.
    $settings->add(new admin_setting_configtext(
        'local_json2activity/retain_logs_days',
        get_string('setting_retain_logs_days', 'local_json2activity'),
        get_string('setting_retain_logs_days_desc', 'local_json2activity'),
        30,
        PARAM_INT
    ));

    // Add link to manage clients page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_json2activity_clients',
        get_string('manageclients', 'local_json2activity'),
        new moodle_url('/local/json2activity/clients.php')
    ));

    // Add link to debug logs page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_json2activity_logs',
        get_string('debuglogs', 'local_json2activity'),
        new moodle_url('/local/json2activity/logs.php')
    ));
}
