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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
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

    // Upgrade to version 2026021901: Add scheduled reports tables.
    if ($oldversion < 2026021901) {
        // Table 1: report_adeptus_insights_schedules.
        $table = new xmldb_table('report_adeptus_insights_schedules');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('frequency', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'weekly');
        $table->add_field('run_hour', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '7');
        $table->add_field('run_dayofweek', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('run_dayofmonth', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        $table->add_field('export_format', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'csv');
        $table->add_field('report_params', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('email_subject', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('email_body', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('last_run', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('next_run', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('failure_count', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('created_by', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_created_by', XMLDB_KEY_FOREIGN, ['created_by'], 'user', ['id']);
        $table->add_index('idx_next_run_active', XMLDB_INDEX_NOTUNIQUE, ['next_run', 'active']);
        $table->add_index('idx_reportid', XMLDB_INDEX_NOTUNIQUE, ['reportid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table 2: report_adeptus_insights_sched_recip.
        $table = new xmldb_table('report_adeptus_insights_sched_recip');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scheduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('recipient_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_scheduleid', XMLDB_KEY_FOREIGN, ['scheduleid'], 'report_adeptus_insights_schedules', ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('fk_roleid', XMLDB_KEY_FOREIGN, ['roleid'], 'role', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table 3: report_adeptus_insights_sched_log.
        $table = new xmldb_table('report_adeptus_insights_sched_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scheduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('recipients_sent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('recipients_failed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('export_format', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('attachment_size', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('row_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_scheduleid', XMLDB_KEY_FOREIGN, ['scheduleid'], 'report_adeptus_insights_schedules', ['id']);
        $table->add_index('idx_scheduleid_timecreated', XMLDB_INDEX_NOTUNIQUE, ['scheduleid', 'timecreated']);
        $table->add_index('idx_status_timecreated', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026021901, 'report', 'adeptus_insights');
    }

    return true;
}
