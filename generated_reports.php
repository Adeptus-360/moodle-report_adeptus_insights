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
 * Generated Reports page for Adeptus Insights.
 *
 * Displays all AI-generated and Wizard reports in a dedicated view.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Force Boost theme for consistent plugin UI
$CFG->theme = 'boost';

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/lib.php');

// Require login and capability.
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/generated_reports.php'));
$PAGE->set_title(get_string('generated_reports_title', 'report_adeptus_insights'));

// Get backend URL from config.
$backendurl = \report_adeptus_insights\api_config::get_backend_url();

// Check authentication using the new token-based system.
$authmanager = new \report_adeptus_insights\token_auth_manager();
$authenticated = $authmanager->check_auth(false);

// Get authentication data for JavaScript
$authdata = $authmanager->get_auth_status();

// Get subscription details to determine if user is on free plan
$installationmanager = new \report_adeptus_insights\installation_manager();
$subscription = $installationmanager->get_subscription_details();
$isfreeplan = true; // Default to free plan

if ($subscription) {
    $planname = strtolower($subscription['plan_name'] ?? '');
    $isfreeplan = (strpos($planname, 'free') !== false ||
                     strpos($planname, 'trial') !== false ||
                     ($subscription['price'] ?? 0) == 0);
}

// Load required AMD modules and CSS
$PAGE->requires->js_call_amd('report_adeptus_insights/auth_utils', 'initializeFromMoodle', [$authdata]);
$PAGE->requires->js_call_amd('report_adeptus_insights/readonly_mode', 'init');
$PAGE->requires->css('/report/adeptus_insights/styles.css');
$PAGE->requires->css('/report/adeptus_insights/styles/readonly-mode.css');
$PAGE->requires->css('/report/adeptus_insights/styles/notifications.css');
$PAGE->requires->css('/report/adeptus_insights/lib/vanilla-table-enhancer.css');
$PAGE->requires->js('/report/adeptus_insights/lib/vanilla-table-enhancer.js');

echo $OUTPUT->header();

// Prepare template context
$templatecontext = [
    'authenticated' => $authenticated,
    'wwwroot' => $CFG->wwwroot,
    'backendUrl' => $backendurl,
    'is_free_plan' => $isfreeplan,
];

echo $OUTPUT->render_from_template('report_adeptus_insights/generated_reports', $templatecontext);

echo $OUTPUT->footer();
