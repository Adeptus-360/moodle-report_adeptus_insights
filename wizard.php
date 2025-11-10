<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require login
require_login();

// Load authentication manager
require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');
$auth_manager = new \report_adeptus_insights\token_auth_manager();

// Check authentication
$auth_status = $auth_manager->get_auth_status();
if (!$auth_status['user_authorized'] || !$auth_status['has_api_key']) {
    // Redirect to main page if not authenticated
    redirect(new moodle_url('/report/adeptus_insights/index.php'));
}

// Set up the page
$PAGE->set_url('/report/adeptus_insights/wizard.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Report Wizard');
$PAGE->set_pagelayout('standard');

// Load plugin version for cache busting
$plugin = new stdClass();
require(__DIR__ . '/version.php');

// Add CSS and JS with cache busting
$cache_buster = '?v=' . $plugin->version;
$PAGE->requires->css('/report/adeptus_insights/styles/wizard.css' . $cache_buster);

// Load Chart.js from Moodle's core AMD system
$PAGE->requires->js_call_amd('core/chartjs');

// Then load our wizard JavaScript with version parameter
$PAGE->requires->js('/report/adeptus_insights/js/wizard.js' . $cache_buster);

// Check if user has capability to view reports
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Category icons mapping - Using free FontAwesome icons only
$category_icons = [
    'USER and ENROLLMENT Reports' => 'fa-users',
    'COURSE Reports' => 'fa-book',
    'ROLES and PERMISSIONS Reports' => 'fa-shield',
    'GRADES and ASSESSMENT Reports' => 'fa-bar-chart',
    'SYSTEM USAGE Reports' => 'fa-desktop',
    'COMMUNICATION Reports' => 'fa-comments',
    'QUIZ and ASSESSMENT Reports' => 'fa-question-circle',
    'ATTENDANCE Reports' => 'fa-calendar',
    'FINANCIAL Reports' => 'fa-dollar',
    'ENGAGEMENT Reports' => 'fa-heart',
    'COMPLETION Reports' => 'fa-trophy',
    'CONTENT Reports' => 'fa-file-text',
    'ANALYTICS Reports' => 'fa-line-chart',
    'COMPLIANCE Reports' => 'fa-clipboard',
    'TEACHER Reports' => 'fa-user',
    'STUDENT Reports' => 'fa-user'
];

// Define report priority keywords for free tier selection
$priority_keywords = [
    'high' => ['overview', 'summary', 'total', 'count', 'basic', 'simple', 'main', 'general', 'all', 'complete'],
    'medium' => ['detailed', 'advanced', 'specific', 'custom', 'filtered', 'selected'],
    'low' => ['export', 'bulk', 'batch', 'comprehensive', 'extensive', 'full', 'complete', 'detailed analysis']
];

/**
 * Calculate report priority based on name and description
 */
function calculate_report_priority($report, $priority_keywords) {
    $text = strtolower($report->name . ' ' . ($report->description ?? ''));
    
    foreach ($priority_keywords['high'] as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 1; // High priority
        }
    }
    
    foreach ($priority_keywords['medium'] as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 2; // Medium priority
        }
    }
    
    foreach ($priority_keywords['low'] as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 3; // Low priority
        }
    }
    
    return 2; // Default to medium priority
}

// Get subscription details to determine if user is on free plan
$installation_manager = new \report_adeptus_insights\installation_manager();
$subscription = $installation_manager->get_subscription_details();
$is_free_plan = false;

if ($subscription) {
    $plan_name = strtolower($subscription['plan_name'] ?? '');
    $is_free_plan = (strpos($plan_name, 'free') !== false || 
                     strpos($plan_name, 'trial') !== false ||
                     ($subscription['price'] ?? 0) == 0);
} else {
    // Default to free plan if no subscription data
    $is_free_plan = true;
}

// Reports are now fetched dynamically from backend API via JavaScript
// This PHP file only provides the template structure
$categories = []; // Will be populated by JavaScript

// Report processing moved to JavaScript - fetching from backend API

// Free tier restrictions and report processing now handled in JavaScript

// Get user's recent reports and bookmarks
$userid = $USER->id;
$recent_reports = $DB->get_records_sql("
    SELECT h.*
    FROM {adeptus_report_history} h 
    WHERE h.userid = ? 
    AND h.id IN (
        SELECT MAX(h2.id) 
        FROM {adeptus_report_history} h2 
        WHERE h2.userid = ? 
        GROUP BY h2.reportid
    )
    ORDER BY h.generatedat DESC 
", [$userid, $userid]);

// Parse parameters for recent reports
foreach ($recent_reports as $key => $recent_report) {
    // Add formatted date with time
    $recent_reports[$key]->formatted_date = userdate($recent_report->generatedat, '%d %B %Y at %H:%M');
    
    // Parse saved parameters
    $recent_reports[$key]->saved_parameters = [];
    if (!empty($recent_report->parameters)) {
        $params = json_decode($recent_report->parameters, true);
        if (is_array($params)) {
            $recent_reports[$key]->saved_parameters = $params;
        }
    }
    
    // Since reportid is now the report name (string), use it directly
    $recent_reports[$key]->name = $recent_report->reportid;
    $recent_reports[$key]->category = 'Unknown'; // Will be populated by frontend
    $recent_reports[$key]->description = 'Report from history'; // Will be populated by frontend
}

// Get generated reports (for Generated Reports section)
$generated_reports = $DB->get_records_sql("
    SELECT g.*
    FROM {adeptus_generated_reports} g 
    WHERE g.userid = ? 
    ORDER BY g.generatedat DESC 
", [$userid]);

// Parse parameters for generated reports
foreach ($generated_reports as $key => $generated_report) {
    // Add formatted date with time
    $generated_reports[$key]->formatted_date = userdate($generated_report->generatedat, '%d %B %Y at %H:%M');
    
    // Parse saved parameters
    $generated_reports[$key]->saved_parameters = [];
    if (!empty($generated_report->parameters)) {
        $params = json_decode($generated_report->parameters, true);
        if (is_array($params)) {
            $generated_reports[$key]->saved_parameters = $params;
        }
    }
    
    // Since reportid is now the report name (string), use it directly
    $generated_reports[$key]->name = $generated_report->reportid;
    $generated_reports[$key]->category = 'Unknown'; // Will be populated by frontend
    $generated_reports[$key]->description = 'Generated report'; // Will be populated by frontend
    $generated_reports[$key]->has_data = true; // All generated reports have data
}

$bookmarks = $DB->get_records_sql("
    SELECT b.*
    FROM {adeptus_report_bookmarks} b 
    WHERE b.userid = ? 
    ORDER BY b.createdat DESC
", [$userid]);

// Add formatted date for bookmarks and map field names
foreach ($bookmarks as $key => $bookmark) {
    $bookmarks[$key]->formatted_date = userdate($bookmark->createdat, '%d %B %Y at %H:%M');
    
    // Since reportid is now the report name (string), use it directly
    $bookmarks[$key]->name = $bookmark->reportid;
    $bookmarks[$key]->category = 'Unknown'; // Will be populated by frontend
    $bookmarks[$key]->description = 'Report from bookmarks'; // Will be populated by frontend
}

// Get all bookmarked report IDs for checking bookmark status
$bookmarked_report_ids = [];
if (!empty($bookmarks)) {
    $bookmarked_report_ids = array_column($bookmarks, 'reportid');
}

// Add bookmark status to all reports
foreach ($categories as $cat_key => $category) {
    foreach ($category['reports'] as $rep_key => $report) {
        $categories[$cat_key]['reports'][$rep_key]['is_bookmarked'] = in_array($report['id'], $bookmarked_report_ids);
    }
}

// Get API key for export tracking
$api_key = $installation_manager->get_api_key();
$backend_api_url = $installation_manager->get_api_url();

// Prepare template data
$templatedata = [
    'categories' => array_values($categories),
    'recent_reports' => array_values($recent_reports),
    'generated_reports' => array_values($generated_reports),
    'bookmarks' => array_values($bookmarks),
    'has_recent_reports' => !empty($recent_reports),
    'has_generated_reports' => !empty($generated_reports),
    'has_bookmarks' => !empty($bookmarks),
    'user_fullname' => fullname($USER),
    'wizard_title' => 'Report Wizard',
    'wwwroot' => $CFG->wwwroot,
    'sesskey' => sesskey(),
    'api_key' => $api_key,
    'backend_api_url' => $backend_api_url,
    'categories_json' => json_encode([]), // Categories loaded dynamically from backend
    'recent_reports_json' => json_encode(array_values($recent_reports)),
    'generated_reports_json' => json_encode(array_values($generated_reports)),
    'bookmarks_json' => json_encode(array_values($bookmarks)),
    'bookmarked_report_ids' => json_encode($bookmarked_report_ids),
    'is_free_plan' => $is_free_plan,
    'subscription' => $subscription,
    'subscription_json' => json_encode($subscription)
];

// Output the page
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_adeptus_insights/wizard', $templatedata);
echo $OUTPUT->footer();
