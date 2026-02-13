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

// Require login and capability.
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Check installation state FIRST — redirect to onboarding if not set up.
$installationmanager = new \report_adeptus_insights\installation_manager();

if (!$installationmanager->is_registered()) {
    redirect(new moodle_url('/report/adeptus_insights/register_plugin.php'));
}

$installationcompleted = get_config('report_adeptus_insights', 'installation_completed');
if (!$installationcompleted) {
    redirect(new moodle_url('/report/adeptus_insights/subscription_installation_step.php'));
}

// Plugin is registered and installed — now check backend API auth.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/index.php'));
$PAGE->set_title(get_string('assistanttitle', 'report_adeptus_insights'));

$authmanager = new \report_adeptus_insights\token_auth_manager();

if (!$authmanager->check_auth(false)) {
    echo $OUTPUT->header();
    echo '<div class="alert alert-warning">';
    echo '<h2>' . get_string('authentication_required', 'report_adeptus_insights') . '</h2>';
    echo '<p>' . get_string('login_required_message', 'report_adeptus_insights') . '</p>';
    echo '<p><a href="' . $CFG->wwwroot . '/login/index.php" class="btn btn-primary">';
    echo get_string('login_to_moodle', 'report_adeptus_insights') . '</a></p>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Installation manager already loaded above.

// Get authentication status for JavaScript.
$authstatus = $authmanager->get_auth_status();

// Debug: Log the auth status.

// Load required AMD modules and CSS BEFORE header.
$PAGE->requires->js_call_amd('report_adeptus_insights/auth_utils', 'initializeFromMoodle', [$authstatus]);
$PAGE->requires->js_call_amd('report_adeptus_insights/readonly_mode', 'init');
// Lottie is loaded dynamically by lottie_bridge.js to avoid RequireJS conflicts.
$PAGE->requires->js_call_amd('report_adeptus_insights/lottie_loader', 'init');
$PAGE->requires->js_call_amd('report_adeptus_insights/index_dashboard', 'init');
$PAGE->requires->css('/report/adeptus_insights/styles.css');
$PAGE->requires->css('/report/adeptus_insights/styles/readonly-mode.css');
$PAGE->requires->css('/report/adeptus_insights/styles/notifications.css');
$PAGE->requires->css('/report/adeptus_insights/styles/index.css');


// If registered, show the main dashboard.
echo $OUTPUT->header();

// Get subscription details for template.
$subscription = $installationmanager->get_subscription_details();

// Debug: Log the subscription data.

// Prepare template context.
$templatecontext = [
    'subscription' => $subscription,
];

// Debug: Log the template context.

// Render the template.
echo $OUTPUT->render_from_template('report_adeptus_insights/index', $templatecontext);

echo $OUTPUT->footer();
