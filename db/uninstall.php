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
 * Uninstall script for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin uninstall callback - removes the entry from the custom user menu,
 * notifies the backend, and drops all tables.
 */
function xmldb_report_adeptus_insights_uninstall() {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    // First, try to notify the backend that this installation is being uninstalled
    try {
        // Get API key and URL before dropping the settings table
        $settings = $DB->get_record('report_adeptus_insights_settings', ['id' => 1]);
        if ($settings && !empty($settings->api_key) && !empty($settings->api_url)) {
            $url = rtrim($settings->api_url, '/') . '/installation/uninstall';

            // Use Moodle's curl wrapper instead of direct curl_init.
            $curl = new \curl();
            $curl->setHeader('Authorization: Bearer ' . $settings->api_key);
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');

            $options = [
                'CURLOPT_TIMEOUT' => 10,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ];

            $postdata = json_encode([
                'site_url' => $CFG->wwwroot,
                'reason' => 'plugin_uninstalled',
            ]);

            $curl->post($url, $postdata, $options);
        }
    } catch (Exception $e) {
        // Silently fail - don't prevent uninstall if backend notification fails.
        debugging('Backend uninstall notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // Remove ALL custom user menu entries pointing to this plugin
    $custom = get_config('moodle', 'customusermenuitems');
    if ($custom !== false) {
        $lines = preg_split('/\r\n?|\n/', $custom);
        $lines = array_filter($lines, function ($line) {
            return strpos($line, '/report/adeptus_insights/') === false;
        });
        set_config('customusermenuitems', implode("\n", $lines));
    }

    // Drop all plugin tables.
    $tables = [
        'report_adeptus_insights_exports',
        'report_adeptus_insights_generated',
        'report_adeptus_insights_stripe',
        'report_adeptus_insights_analytics',
        'report_adeptus_insights_cache',
        'report_adeptus_insights_config',
        'report_adeptus_insights_reports',
        'report_adeptus_insights_history',
        'report_adeptus_insights_bookmarks',
        'report_adeptus_insights_settings',
        'report_adeptus_insights_subscription',
        'report_adeptus_insights_webhooks',
        'report_adeptus_insights_usage',
        'report_adeptus_insights_plans',
    ];

    foreach ($tables as $tablename) {
        $table = new xmldb_table($tablename);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    // Remove all plugin configuration
    $configs = [
        'redirect_to_settings',
        'postinstall_redirect_stage',
        'installation_completed',
        'installation_step',
    ];

    foreach ($configs as $config) {
        unset_config($config, 'report_adeptus_insights');
    }

    return true;
}
