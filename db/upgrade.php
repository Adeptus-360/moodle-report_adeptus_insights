<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database upgrade script for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

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

    // Upgrade to version 2026012602: Add adeptus_stripe_config table and Stripe SDK.
    if ($oldversion < 2026012602) {
        // Define table adeptus_stripe_config to be created.
        $table = new xmldb_table('adeptus_stripe_config');

        // Adding fields to table adeptus_stripe_config.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('publishable_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('secret_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('webhook_secret', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('is_test_mode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'GBP');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table adeptus_stripe_config.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally create the table (if it doesn't already exist).
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adeptus Insights savepoint reached.
        upgrade_plugin_savepoint(true, 2026012602, 'report', 'adeptus_insights');
    }

    // Upgrade to version 2026012701: Rename tables to use frankenstyle prefix.
    if ($oldversion < 2026012701) {
        // Table rename mapping: old name => new name.
        $tablerenames = [
            'ai_analytics_base' => 'report_adeptus_insights_analytics',
            'ai_report_cache' => 'report_adeptus_insights_cache',
            'ai_report_config' => 'report_adeptus_insights_config',
            'adeptus_reports' => 'report_adeptus_insights_reports',
            'adeptus_report_history' => 'report_adeptus_insights_history',
            'adeptus_report_bookmarks' => 'report_adeptus_insights_bookmarks',
            'adeptus_install_settings' => 'report_adeptus_insights_settings',
            'adeptus_subscription_status' => 'report_adeptus_insights_subscription',
            'adeptus_stripe_webhooks' => 'report_adeptus_insights_webhooks',
            'adeptus_usage_tracking' => 'report_adeptus_insights_usage',
            'adeptus_export_tracking' => 'report_adeptus_insights_exports',
            'adeptus_generated_reports' => 'report_adeptus_insights_generated',
            'adeptus_stripe_config' => 'report_adeptus_insights_stripe',
            'adeptus_stripe_plans' => 'report_adeptus_insights_plans',
        ];

        // Rename each table if old name exists and new name doesn't.
        foreach ($tablerenames as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            $newtable = new xmldb_table($newname);

            if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
                $dbman->rename_table($oldtable, $newname);
            }
        }

        // Adeptus Insights savepoint reached.
        upgrade_plugin_savepoint(true, 2026012701, 'report', 'adeptus_insights');
    }

    return true;
}
