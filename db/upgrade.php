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
 * Upgrade script for local_json2activity.
 *
 * @package    local_json2activity
 * @copyright  2025 JSON2Activity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_json2activity plugin.
 *
 * @param int $oldversion The old version.
 * @return bool
 */
function xmldb_local_json2activity_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025022600) {
        // Define table local_json2activity_req.
        $table = new xmldb_table('local_json2activity_req');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('requestid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('remoteip', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('receivedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'accepted');
        $table->add_field('mode', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'partial');
        $table->add_field('dryrun', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('createdcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('failedcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('payloadhash', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('payloadjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('responsejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sigvalid', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('replayrejected', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('errsummary', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('durationms', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('ix_requestid', XMLDB_INDEX_UNIQUE, ['requestid']);
        $table->add_index('ix_clientid', XMLDB_INDEX_NOTUNIQUE, ['clientid']);
        $table->add_index('ix_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('ix_receivedat', XMLDB_INDEX_NOTUNIQUE, ['receivedat']);
        $table->add_index('ix_status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_json2activity_item.
        $table = new xmldb_table('local_json2activity_item');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reqid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemindex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('sectionnum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('type', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('errorcode', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('debuginfo', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_reqid', XMLDB_KEY_FOREIGN, ['reqid'], 'local_json2activity_req', ['id']);

        $table->add_index('ix_reqid', XMLDB_INDEX_NOTUNIQUE, ['reqid']);
        $table->add_index('ix_itemid', XMLDB_INDEX_NOTUNIQUE, ['itemid']);
        $table->add_index('ix_status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_json2activity_client.
        $table = new xmldb_table('local_json2activity_client');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sharedsecret', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('allowedipranges', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('ix_clientid', XMLDB_INDEX_UNIQUE, ['clientid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_json2activity_nonce.
        $table = new xmldb_table('local_json2activity_nonce');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('nonce', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('ix_clientid_nonce', XMLDB_INDEX_UNIQUE, ['clientid', 'nonce']);
        $table->add_index('ix_expiresat', XMLDB_INDEX_NOTUNIQUE, ['expiresat']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025022600, 'local', 'json2activity');
    }

    return true;
}
