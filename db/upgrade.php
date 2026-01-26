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

/**
 * Upgrade the plugin database schema.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_report_adeptus_insights_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Upgrade to version 2026012601: Add adeptus_generated_reports table.
    if ($oldversion < 2026012601) {
        // Define table adeptus_generated_reports to be created.
        $table = new xmldb_table('adeptus_generated_reports');

        // Adding fields to table adeptus_generated_reports.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reportid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('generatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('resultpath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('counted_for_usage', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table adeptus_generated_reports.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table adeptus_generated_reports.
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('generatedat_idx', XMLDB_INDEX_NOTUNIQUE, ['generatedat']);

        // Conditionally create the table (if it doesn't already exist).
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adeptus Insights savepoint reached.
        upgrade_plugin_savepoint(true, 2026012601, 'report', 'adeptus_insights');
    }

    return true;
}
