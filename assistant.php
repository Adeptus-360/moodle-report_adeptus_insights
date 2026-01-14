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
 * AI Assistant page for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();

require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_url(new moodle_url('/report/adeptus_insights/assistant.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('assistanttitle', 'report_adeptus_insights'));
$PAGE->set_pagelayout('report');

// Check authentication using the new token-based system
require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

$auth_manager = new \report_adeptus_insights\token_auth_manager();
$authenticated = $auth_manager->check_auth(false); // Don't redirect, just check status

// Get authentication data for JavaScript
$auth_data = $auth_manager->get_auth_status();

// Get subscription details to determine if user is on free plan
$installation_manager = new \report_adeptus_insights\installation_manager();
$subscription = $installation_manager->get_subscription_details();
$is_free_plan = true; // Default to free plan

if ($subscription) {
    $plan_name = strtolower($subscription['plan_name'] ?? '');
    $is_free_plan = (strpos($plan_name, 'free') !== false ||
                     strpos($plan_name, 'trial') !== false ||
                     ($subscription['price'] ?? 0) == 0);
}

// Load required AMD modules and CSS
$PAGE->requires->js_call_amd('report_adeptus_insights/assistant', 'init', [$authenticated, $is_free_plan]);
$PAGE->requires->js_call_amd('report_adeptus_insights/auth_utils', 'initializeFromMoodle', [$auth_data]);
$PAGE->requires->js_call_amd('report_adeptus_insights/readonly_mode', 'init');
$PAGE->requires->css('/report/adeptus_insights/styles/readonly-mode.css');
$PAGE->requires->css('/report/adeptus_insights/styles/notifications.css');
$PAGE->requires->css('/report/adeptus_insights/lib/vanilla-table-enhancer.css');
$PAGE->requires->js('/report/adeptus_insights/lib/vanilla-table-enhancer.js');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_adeptus_insights/assistant', [
    'authenticated' => $authenticated,
    'is_free_plan' => $is_free_plan,
    'wwwroot' => $CFG->wwwroot,
]);
echo $OUTPUT->footer();
