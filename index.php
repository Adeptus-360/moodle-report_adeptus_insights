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
 * Main index page for Adeptus Insights.
 *
 * Redirects unregistered users to subscription.php for onboarding.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/lib.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/index.php'));
$PAGE->set_title(get_string('assistanttitle', 'report_adeptus_insights'));
// $PAGE->set_heading(get_string('pluginname', 'report_adeptus_insights'));

// Check authentication using the new token-based system
require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');
$auth_manager = new \report_adeptus_insights\token_auth_manager();

// Try to check auth without redirecting first
if (!$auth_manager->check_auth(false)) {
    // If not authenticated, show a login message instead of redirecting
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/report/adeptus_insights/index.php'));
    $PAGE->set_title(get_string('assistanttitle', 'report_adeptus_insights'));
    
    echo $OUTPUT->header();
    echo '<div class="alert alert-warning">';
    echo '<h2>Authentication Required</h2>';
    echo '<p>You need to be logged into Moodle to access this plugin.</p>';
    echo '<p><a href="' . $CFG->wwwroot . '/login/index.php" class="btn btn-primary">Login to Moodle</a></p>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Load installation manager
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
$installation_manager = new \report_adeptus_insights\installation_manager();

// Get authentication status for JavaScript
$auth_status = $auth_manager->get_auth_status();

// Debug: Log the auth status

// Load required AMD modules and CSS BEFORE header
$PAGE->requires->js_call_amd('report_adeptus_insights/auth_utils', 'initializeFromMoodle', array($auth_status));
$PAGE->requires->js_call_amd('report_adeptus_insights/readonly-mode', 'init');
$PAGE->requires->js_call_amd('report_adeptus_insights/lottie_loader', 'init');
$PAGE->requires->css('/report/adeptus_insights/styles.css');
$PAGE->requires->css('/report/adeptus_insights/styles/readonly-mode.css');
$PAGE->requires->css('/report/adeptus_insights/styles/notifications.css');


// If registered, show the main dashboard
echo $OUTPUT->header();

// Get subscription details for template
$subscription = $installation_manager->get_subscription_details();

// Debug: Log the subscription data

// Prepare template context
$templatecontext = [
    'subscription' => $subscription
];

// Debug: Log the template context

// Render the template
echo $OUTPUT->render_from_template('report_adeptus_insights/index', $templatecontext);

echo $OUTPUT->footer();
