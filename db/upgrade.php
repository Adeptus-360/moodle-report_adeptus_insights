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
 * Database upgrade script for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_report_adeptus_insights_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025010805) {
        // Define table adeptus_export_tracking to be created.
        $table = new xmldb_table('adeptus_export_tracking');

        // Adding fields to table adeptus_export_tracking.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reportname', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('format', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('exportedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table adeptus_export_tracking.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table adeptus_export_tracking.
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('exportedat_idx', XMLDB_INDEX_NOTUNIQUE, ['exportedat']);

        // Conditionally launch create table for adeptus_export_tracking.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adeptus_insights savepoint reached.
        upgrade_plugin_savepoint(true, 2025010805, 'report', 'adeptus_insights');
    }

    // Version 2025111930: Wizard reports moved to backend API
    // The adeptus_generated_reports local table is deprecated but kept for backwards compatibility
    // All new wizard reports are now stored on the backend for unified management and subscription control
    if ($oldversion < 2025111930) {
        // No local database changes needed - wizard reports are now stored on the backend
        upgrade_plugin_savepoint(true, 2025111930, 'report', 'adeptus_insights');
    }

    // Version 2026010301: Plugin renamed from "Adeptus AI Insights" to "Adeptus Insights"
    // Clean up duplicate user menu entries and ensure only one entry exists
    if ($oldversion < 2026010301) {
        $custom = get_config('moodle', 'customusermenuitems');
        if ($custom !== false) {
            $lines = preg_split('/\r\n?|\n/', $custom);
            // Remove ALL existing Adeptus entries
            $lines = array_filter($lines, function($line) {
                return strpos($line, '/report/adeptus_insights/') === false;
            });
            // Add single correct entry with new name
            $entry = get_string('pluginname', 'report_adeptus_insights') . '|/report/adeptus_insights/index.php';
            $lines[] = $entry;
            set_config('customusermenuitems', implode("\n", $lines));
        }
        upgrade_plugin_savepoint(true, 2026010301, 'report', 'adeptus_insights');
    }

    // Version 2026010535: Change reportid column from INT to CHAR to store report names
    if ($oldversion < 2026010535) {
        $table = new xmldb_table('adeptus_report_history');
        $field = new xmldb_field('reportid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'userid');

        // Change the field type from int to char
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026010535, 'report', 'adeptus_insights');
    }

    return true;
}
?>
