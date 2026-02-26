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
 * Language strings for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name and general.
$string['pluginname'] = 'JSON2Activity';
$string['privacy:metadata'] = 'The JSON2Activity plugin stores request logs with user-submitted data for debugging purposes.';

// Form strings.
$string['jsonpayload'] = 'JSON Payload';
$string['createactivity'] = 'Create Activities';

// Capabilities.
$string['json2activity:use'] = 'Use JSON2Activity form';
$string['json2activity:viewlogs'] = 'View JSON2Activity logs';
$string['json2activity:managelogs'] = 'Manage JSON2Activity logs';
$string['json2activity:execute'] = 'Execute JSON2Activity web service';

// Settings page.
$string['settings'] = 'JSON2Activity Settings';
$string['settings_heading_security'] = 'Security Settings';
$string['settings_heading_security_desc'] = 'Configure security options for the API.';
$string['settings_heading_processing'] = 'Processing Settings';
$string['settings_heading_processing_desc'] = 'Configure how requests are processed.';
$string['settings_heading_retention'] = 'Data Retention';
$string['settings_heading_retention_desc'] = 'Configure log retention policies.';

$string['setting_max_timestamp_skew'] = 'Max timestamp skew (seconds)';
$string['setting_max_timestamp_skew_desc'] = 'Maximum allowed difference between request timestamp and server time.';
$string['setting_require_nonce'] = 'Require nonce';
$string['setting_require_nonce_desc'] = 'Require a unique nonce in addition to request_id for anti-replay.';
$string['setting_nonce_ttl'] = 'Nonce TTL (seconds)';
$string['setting_nonce_ttl_desc'] = 'Time-to-live for used nonces before they expire.';
$string['setting_max_payload_bytes'] = 'Max payload size (bytes)';
$string['setting_max_payload_bytes_desc'] = 'Maximum size of the JSON payload in bytes.';
$string['setting_max_items_per_request'] = 'Max items per request';
$string['setting_max_items_per_request_desc'] = 'Maximum number of items allowed in a single request.';
$string['setting_reject_script_tags'] = 'Reject script tags';
$string['setting_reject_script_tags_desc'] = 'Reject any HTML content containing script tags.';
$string['setting_store_payload'] = 'Store payload';
$string['setting_store_payload_desc'] = 'How to store the request payload in logs.';
$string['setting_store_payload_full'] = 'Full payload';
$string['setting_store_payload_hash'] = 'Hash only';
$string['setting_store_payload_truncated'] = 'Truncated (first 10KB)';
$string['setting_store_stacktraces'] = 'Store stacktraces';
$string['setting_store_stacktraces_desc'] = 'Store full stacktraces on errors (disable in production).';

$string['setting_default_mode'] = 'Default processing mode';
$string['setting_default_mode_desc'] = 'Default mode when not specified in request.';
$string['setting_mode_partial'] = 'Partial (continue on errors)';
$string['setting_mode_atomic'] = 'Atomic (all or nothing)';
$string['setting_async_threshold'] = 'Async threshold (items)';
$string['setting_async_threshold_desc'] = 'Process requests asynchronously when item count exceeds this threshold.';

$string['setting_retain_logs_days'] = 'Retain logs (days)';
$string['setting_retain_logs_days_desc'] = 'Number of days to keep request logs.';
$string['purge_now'] = 'Purge old logs now';
$string['purge_confirm'] = 'Are you sure you want to purge logs older than {$a} days?';
$string['purge_success'] = 'Successfully purged {$a} old log entries.';

// Client management.
$string['manageclients'] = 'Manage API Clients';
$string['addclient'] = 'Add Client';
$string['editclient'] = 'Edit Client';
$string['deleteclient'] = 'Delete Client';
$string['clientid'] = 'Client ID';
$string['sharedsecret'] = 'Shared Secret';
$string['allowedipranges'] = 'Allowed IP Ranges (CIDR)';
$string['clientenabled'] = 'Enabled';
$string['clientcreated'] = 'Client created successfully.';
$string['clientupdated'] = 'Client updated successfully.';
$string['clientdeleted'] = 'Client deleted successfully.';
$string['generatesecret'] = 'Generate Secret';
$string['regeneratesecret'] = 'Regenerate Secret';

// Debug page.
$string['debuglogs'] = 'Request Logs';
$string['requestid'] = 'Request ID';
$string['clientidlabel'] = 'Client';
$string['courseidlabel'] = 'Course ID';
$string['receivedat'] = 'Received At';
$string['status'] = 'Status';
$string['items'] = 'Items';
$string['created'] = 'Created';
$string['failed'] = 'Failed';
$string['duration'] = 'Duration (ms)';
$string['viewdetails'] = 'View Details';
$string['requestdetails'] = 'Request Details';
$string['payloadjson'] = 'Payload JSON';
$string['responsejson'] = 'Response JSON';
$string['itemdetails'] = 'Item Details';
$string['norecordsfound'] = 'No records found.';

// Status strings.
$string['status_accepted'] = 'Accepted';
$string['status_queued'] = 'Queued';
$string['status_processing'] = 'Processing';
$string['status_success'] = 'Success';
$string['status_partial'] = 'Partial Success';
$string['status_failed'] = 'Failed';
$string['status_rejected'] = 'Rejected';

// Item status.
$string['itemstatus_created'] = 'Created';
$string['itemstatus_skipped'] = 'Skipped';
$string['itemstatus_failed'] = 'Failed';
$string['itemstatus_validated'] = 'Validated';

// Error messages.
$string['error_invalid_schema'] = 'Invalid or missing schema version.';
$string['error_invalid_requestid'] = 'Invalid or missing request_id.';
$string['error_invalid_courseid'] = 'Invalid or missing courseid.';
$string['error_course_not_found'] = 'Course not found.';
$string['error_no_items'] = 'No items to process.';
$string['error_too_many_items'] = 'Too many items in request (max: {$a}).';
$string['error_payload_too_large'] = 'Payload exceeds maximum size (max: {$a} bytes).';
$string['error_invalid_timestamp'] = 'Request timestamp is outside acceptable window.';
$string['error_invalid_signature'] = 'Invalid HMAC signature.';
$string['error_replay_detected'] = 'Replay attack detected (duplicate request_id).';
$string['error_client_not_found'] = 'Client not found or disabled.';
$string['error_ip_not_allowed'] = 'IP address not in allowlist.';
$string['error_script_tags'] = 'HTML content contains forbidden script tags.';
$string['error_invalid_activity_type'] = 'Unsupported activity type: {$a}.';
$string['error_missing_field'] = 'Missing required field: {$a}.';
$string['error_invalid_section'] = 'Invalid section number.';
$string['error_no_capability'] = 'User does not have required capability on this course.';

// Activity types.
$string['activitytype_label'] = 'Label';
$string['activitytype_page'] = 'Page';
$string['activitytype_url'] = 'URL';
$string['activitytype_quiz'] = 'Quiz';
$string['activitytype_assign'] = 'Assignment';
$string['activitytype_resource'] = 'File Resource';
$string['activitytype_forum'] = 'Forum';

// Success messages.
$string['activities_created'] = '{$a} activity(ies) created successfully.';
$string['dry_run_completed'] = 'Dry run completed. {$a} items validated.';
