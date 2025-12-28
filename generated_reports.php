<?php
/**
 * Adeptus Insights - Generated Reports Page
 * Displays all AI-generated reports in a dedicated view
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/lib.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/generated_reports.php'));
$PAGE->set_title(get_string('generated_reports_title', 'report_adeptus_insights'));

// Check authentication using the new token-based system
require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');
$auth_manager = new \report_adeptus_insights\token_auth_manager();
$authenticated = $auth_manager->check_auth(false);

// Get authentication data for JavaScript
$auth_data = $auth_manager->get_auth_status();

// Load required AMD modules and CSS
$PAGE->requires->js_call_amd('report_adeptus_insights/auth_utils', 'initializeFromMoodle', [$auth_data]);
$PAGE->requires->js_call_amd('report_adeptus_insights/readonly-mode', 'init');
$PAGE->requires->css('/report/adeptus_insights/styles.css');
$PAGE->requires->css('/report/adeptus_insights/styles/readonly-mode.css');
$PAGE->requires->css('/report/adeptus_insights/styles/notifications.css');
$PAGE->requires->css('/report/adeptus_insights/lib/vanilla-table-enhancer.css');
$PAGE->requires->js('/report/adeptus_insights/lib/vanilla-table-enhancer.js');

echo $OUTPUT->header();

// Prepare template context
$templatecontext = [
    'authenticated' => $authenticated,
    'wwwroot' => $CFG->wwwroot
];

echo $OUTPUT->render_from_template('report_adeptus_insights/generated_reports', $templatecontext);

echo $OUTPUT->footer();
