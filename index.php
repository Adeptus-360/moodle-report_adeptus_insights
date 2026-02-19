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

// Force Boost theme for consistent plugin UI.
$CFG->theme = 'boost';

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/lib.php');

// Require login and capability.
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/index.php'));
$PAGE->set_title(get_string('assistanttitle', 'report_adeptus_insights'));

// Check authentication using the new token-based system.
$authmanager = new \report_adeptus_insights\token_auth_manager();

// Try to check auth without redirecting first.
if (!$authmanager->check_auth(false)) {
    // If not authenticated, show a login message instead of redirecting.
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/report/adeptus_insights/index.php'));
    $PAGE->set_title(get_string('assistanttitle', 'report_adeptus_insights'));

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

// Load installation manager.
$installationmanager = new \report_adeptus_insights\installation_manager();

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


// Determine role-based view mode.
$viewmode = \report_adeptus_insights\role_helper::get_view_mode();

// If registered, show the main dashboard.
echo $OUTPUT->header();

try {
    // Get subscription details for template.
    $subscription = $installationmanager->get_subscription_details();

    // Prepare template context.
    $templatecontext = [
        'subscription' => $subscription,
        'viewmode' => $viewmode,
        'is_admin_mode' => ($viewmode === \report_adeptus_insights\role_helper::MODE_ADMIN),
        'is_teacher_mode' => ($viewmode === \report_adeptus_insights\role_helper::MODE_TEACHER),
        'is_learner_mode' => ($viewmode === \report_adeptus_insights\role_helper::MODE_LEARNER),
    ];

    // Add teacher-specific data.
    if ($viewmode === \report_adeptus_insights\role_helper::MODE_TEACHER) {
        $courseids = \report_adeptus_insights\role_helper::get_teacher_course_ids();
        $templatecontext['teacher_courseids'] = implode(',', $courseids);
        $templatecontext['teacher_course_count'] = count($courseids);
        $teacherreports = \report_adeptus_insights\role_helper::get_teacher_reports($courseids);
        $templatecontext['teacher_reports'] = array_map(function($report) {
            return [
                'key' => $report['key'],
                'title' => get_string($report['titlekey'], 'report_adeptus_insights'),
                'description' => get_string($report['desckey'], 'report_adeptus_insights'),
                'icon' => $report['icon'],
                'params_json' => json_encode($report['params']),
            ];
        }, $teacherreports);
    }

    // Add learner-specific data and render learner dashboard.
    if ($viewmode === \report_adeptus_insights\role_helper::MODE_LEARNER) {
        global $USER;

        // Build the full learner dashboard.
        $learnerdashboard = new \report_adeptus_insights\learner_dashboard($USER->id);
        $dashboarddata = $learnerdashboard->get_template_data();

        // Add learner reports for the reports card section.
        $learnerreports = \report_adeptus_insights\role_helper::get_learner_reports($USER->id);
        $dashboarddata['has_learner_reports'] = !empty($learnerreports);
        $dashboarddata['learner_reports'] = array_map(function($report) {
            return [
                'key' => $report['key'],
                'title' => get_string($report['titlekey'], 'report_adeptus_insights'),
                'description' => get_string($report['desckey'], 'report_adeptus_insights'),
                'icon' => $report['icon'],
                'params_json' => json_encode($report['params']),
            ];
        }, $learnerreports);

        echo $OUTPUT->render_from_template('report_adeptus_insights/learner_dashboard', $dashboarddata);
    } else {
        // Render the standard template for admin/teacher.
        echo $OUTPUT->render_from_template('report_adeptus_insights/index', $templatecontext);
    }
} catch (\dml_exception $e) {
    echo $OUTPUT->notification(
        get_string('error_database', 'report_adeptus_insights'),
        \core\output\notification::NOTIFY_ERROR
    );
    debugging('Database error in Adeptus Insights index: ' . $e->getMessage(), DEBUG_DEVELOPER);
} catch (Exception $e) {
    echo $OUTPUT->notification(
        get_string('error_generic', 'report_adeptus_insights'),
        \core\output\notification::NOTIFY_ERROR
    );
    debugging('Error in Adeptus Insights index: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

echo $OUTPUT->footer();
