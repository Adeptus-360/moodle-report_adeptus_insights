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
 * Installation script for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin install callback - adds an entry to the custom user menu and redirects to settings.
 */
function xmldb_report_adeptus_insights_install() {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    // Define the menu entry: title|url
    $entry = get_string('pluginname', 'report_adeptus_insights') . '|/report/adeptus_insights/index.php';
    $custom = get_config('moodle', 'customusermenuitems');
    $lines = $custom === false ? [] : preg_split('/\r\n?|\n/', $custom);

    // Remove any existing Adeptus entries (old or new name) to avoid duplicates
    $lines = array_filter($lines, function ($line) {
        return strpos($line, '/report/adeptus_insights/') === false;
    });

    // Add the new entry
    $lines[] = $entry;
    set_config('customusermenuitems', implode("\n", $lines));

    // Create all required tables
    create_adeptus_tables($dbman);

    // Reports are now seeded from the backend API
    // The plugin no longer uses local seeders

    // Register external functions via Moodle's proper mechanism
    require_once($CFG->libdir . '/upgradelib.php');
    external_update_descriptions('report_adeptus_insights');

    // --- NEW: direct post-install redirect to subscription ---
    // Stage 2 = redirect directly to subscription page
    set_config('postinstall_redirect_stage', 2, 'report_adeptus_insights');
}

/**
 * Create all required tables for Adeptus Insights
 */
function create_adeptus_tables($dbman) {
    // adeptus_reports table removed - reports are now fetched from backend API
    // $table = new xmldb_table('adeptus_reports'); // Reports now fetched from backend
    /* Reports now fetched from backend API
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sqlquery', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('charttype', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('isactive', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }
    */

    // 2. adeptus_report_history
    $table = new xmldb_table('adeptus_report_history');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reportid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('generatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('resultpath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 3. adeptus_report_bookmarks
    $table = new xmldb_table('adeptus_report_bookmarks');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 4. adeptus_generated_reports (separate table for Generated Reports section)
    $table = new xmldb_table('adeptus_generated_reports');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reportid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('parameters', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('generatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('resultpath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('counted_for_usage', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('generatedat', XMLDB_INDEX_NOTUNIQUE, ['generatedat']);
        $dbman->create_table($table);
    }

    // 5. ai_analytics_base
    $table = new xmldb_table('ai_analytics_base');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 6. ai_report_cache
    $table = new xmldb_table('ai_report_cache');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cachekey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 6. ai_report_config
    $table = new xmldb_table('ai_report_config');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 7. adeptus_install_settings
    $table = new xmldb_table('adeptus_install_settings');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('setting_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('setting_value', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 8. adeptus_subscription_status
    $table = new xmldb_table('adeptus_subscription_status');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('plan_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('stripe_customer_id', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('stripe_subscription_id', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('updatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 9. adeptus_stripe_webhooks
    $table = new xmldb_table('adeptus_stripe_webhooks');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('webhook_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('event_type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('event_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('processed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // 10. adeptus_usage_tracking
    $table = new xmldb_table('adeptus_usage_tracking');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }
}

/**
 * Plugin uninstall callback - removes the entry from the custom user menu and drops all tables.
 */
function xmldb_report_adeptus_insights_uninstall() {
    global $DB;
    $dbman = $DB->get_manager();

    // Remove ALL custom user menu entries pointing to this plugin (handles renamed plugin)
    $custom = get_config('moodle', 'customusermenuitems');
    if ($custom !== false) {
        $lines = preg_split('/\r\n?|\n/', $custom);
        $lines = array_filter($lines, function ($line) {
            return strpos($line, '/report/adeptus_insights/') === false;
        });
        set_config('customusermenuitems', implode("\n", $lines));
    }

    // Drop all plugin tables
    $tables = [
        'ai_analytics_base',
        'ai_report_cache',
        'ai_report_config',
        'adeptus_reports',
        'adeptus_report_history',
        'adeptus_report_bookmarks',
        'adeptus_install_settings',
        'adeptus_subscription_status',
        'adeptus_stripe_webhooks',
        'adeptus_usage_tracking',
    ];

    foreach ($tables as $tablename) {
        $table = new xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    // Remove plugin configuration
    unset_config('redirect_to_settings', 'report_adeptus_insights');
}

// Plugin seeder functionality removed - reports are now seeded from backend API
