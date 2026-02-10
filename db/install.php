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
 * Installation script for Adeptus Insights.
 *
 * This file handles post-installation tasks only. Database tables are defined
 * in install.xml and created automatically by Moodle's XMLDB system.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Plugin install callback - adds an entry to the custom user menu and sets up initial configuration.
 *
 * Note: Database tables are created automatically from install.xml by Moodle's XMLDB system.
 * This function only handles post-installation configuration tasks.
 *
 * @return bool True on success.
 */
function xmldb_report_adeptus_insights_install() {
    global $CFG;

    // Define the menu entry: title|url.
    $entry = get_string('pluginname', 'report_adeptus_insights') . '|/report/adeptus_insights/index.php';
    $custom = get_config('moodle', 'customusermenuitems');
    $lines = $custom === false ? [] : preg_split('/\r\n?|\n/', $custom);

    // Remove any existing Adeptus entries to avoid duplicates.
    $lines = array_filter($lines, function ($line) {
        return strpos($line, '/report/adeptus_insights/') === false;
    });

    // Add the new entry.
    $lines[] = $entry;
    set_config('customusermenuitems', implode("\n", $lines));

    // Set post-install redirect stage to subscription page.
    set_config('postinstall_redirect_stage', 2, 'report_adeptus_insights');

    return true;
}
