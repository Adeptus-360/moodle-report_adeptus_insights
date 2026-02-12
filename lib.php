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
 * Library functions for the Adeptus Insights report plugin.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Extend the user navigation to include Adeptus Insights link.
 *
 * This adds the plugin link to the user menu dropdown.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $user The user object.
 * @param context_user $usercontext The user context.
 * @param stdClass $course The course object.
 * @param context_course $coursecontext The course context.
 */
function report_adeptus_insights_extend_navigation_user_settings(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    stdClass $course,
    context_course $coursecontext
) {
    if (has_capability('report/adeptus_insights:view', context_system::instance())) {
        $url = new moodle_url('/report/adeptus_insights/index.php');
        $navigation->add(
            get_string('pluginname', 'report_adeptus_insights'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'adeptusinsights',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Add Adeptus Insights node to the user profile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object.
 * @param stdClass $user The user object.
 * @param bool $iscurrentuser Whether this is the current user.
 * @param stdClass $course The course object.
 * @return bool
 */
function report_adeptus_insights_myprofile_navigation(
    core_user\output\myprofile\tree $tree,
    $user,
    $iscurrentuser,
    $course
) {
    if (has_capability('report/adeptus_insights:view', context_system::instance())) {
        $url = new moodle_url('/report/adeptus_insights/index.php');
        $node = new core_user\output\myprofile\node(
            'reports',
            'adeptusinsights',
            get_string('pluginname', 'report_adeptus_insights'),
            null,
            $url
        );
        $tree->add_node($node);
    }
    return true;
}

/**
 * Callback executed before HTTP headers are sent.
 *
 * Loads local SweetAlert2 library on plugin pages.
 * The library file is bundled with the plugin in lib/sweetalert2/.
 */
function report_adeptus_insights_before_http_headers() {
    global $PAGE;

    // Only load JS on plugin pages to avoid impacting other parts of Moodle.
    try {
        $pageurl = $PAGE->url->out(false);
    } catch (Exception $e) {
        // URL not set yet, skip.
        return;
    }

    if (strpos($pageurl, '/report/adeptus_insights/') === false) {
        return;
    }

    // Load local SweetAlert2 library (bundled with plugin, no CDN).
    $PAGE->requires->js(
        new moodle_url('/report/adeptus_insights/lib/sweetalert2/sweetalert2.all.min.js'),
        true
    );
}
