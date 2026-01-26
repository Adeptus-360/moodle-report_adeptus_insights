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
 * Report Wizard page for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Force Boost theme for consistent plugin UI
$CFG->theme = 'boost';

require_once($CFG->libdir . '/adminlib.php');

// Require login
require_login();

// Load authentication manager
require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');
$authmanager = new \report_adeptus_insights\token_auth_manager();

// Check authentication
$authstatus = $authmanager->get_auth_status();
if (!$authstatus['user_authorized'] || !$authstatus['has_api_key']) {
    // Redirect to main page if not authenticated
    redirect(new moodle_url('/report/adeptus_insights/index.php'));
}

// Set up the page
$PAGE->set_url('/report/adeptus_insights/wizard.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Report Wizard');
$PAGE->set_pagelayout('report');

// Load plugin version for cache busting
$plugin = new stdClass();
require(__DIR__ . '/version.php');

// Add CSS and JS with cache busting
$cachebuster = '?v=' . $plugin->version;
$PAGE->requires->css('/report/adeptus_insights/styles/wizard.css' . $cachebuster);

// Chart.js is loaded by wizard.js itself (no need for AMD loader)
// Removed: $PAGE->requires->js_call_amd('core/chartjs'); to avoid RequireJS conflicts

// Then load our wizard JavaScript with version parameter
$PAGE->requires->js('/report/adeptus_insights/js/wizard.js' . $cachebuster);

// Check if user has capability to view reports
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Category icons mapping - Using FontAwesome 6 Free icons
$categoryicons = [
    'USER and ENROLLMENT Reports' => 'fa-users',
    'COURSE Reports' => 'fa-book',
    'ROLES and PERMISSIONS Reports' => 'fa-shield-halved',
    'GRADES and ASSESSMENT Reports' => 'fa-chart-bar',
    'SYSTEM USAGE Reports' => 'fa-desktop',
    'COMMUNICATION Reports' => 'fa-comments',
    'QUIZ and ASSESSMENT Reports' => 'fa-circle-question',
    'ATTENDANCE Reports' => 'fa-calendar',
    'FINANCIAL Reports' => 'fa-dollar-sign',
    'ENGAGEMENT Reports' => 'fa-heart',
    'COMPLETION Reports' => 'fa-trophy',
    'CONTENT Reports' => 'fa-file-lines',
    'ANALYTICS Reports' => 'fa-chart-line',
    'COMPLIANCE Reports' => 'fa-clipboard',
    'TEACHER Reports' => 'fa-chalkboard-user',
    'STUDENT Reports' => 'fa-user-graduate',
    'BADGES Reports' => 'fa-award',
    'COHORTS Reports' => 'fa-user-group',
    'COMPETENCIES Reports' => 'fa-bullseye',
    'COURSE DESIGN Reports' => 'fa-pen-ruler',
    'GROUP Reports' => 'fa-people-group',
    'LOG Reports' => 'fa-clock-rotate-left',
    'MESSAGING Reports' => 'fa-envelope',
    'SCALES Reports' => 'fa-scale-balanced',
    'USER FILES Reports' => 'fa-folder-open',
];

// Define report priority keywords for free tier selection
$prioritykeywords = [
    'high' => ['overview', 'summary', 'total', 'count', 'basic', 'simple', 'main', 'general', 'all', 'complete'],
    'medium' => ['detailed', 'advanced', 'specific', 'custom', 'filtered', 'selected'],
    'low' => ['export', 'bulk', 'batch', 'comprehensive', 'extensive', 'full', 'complete', 'detailed analysis'],
];

/**
 * Calculate report priority based on name and description.
 *
 * @param object $report The report object.
 * @param array $prioritykeywords Priority keyword configuration.
 * @return int Priority value (1=high, 2=medium, 3=low).
 */
function report_adeptus_insights_calculate_report_priority($report, $prioritykeywords) {
    $text = strtolower($report->name . ' ' . ($report->description ?? ''));

    foreach ($prioritykeywords['high'] as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 1; // High priority
        }
    }

    foreach ($prioritykeywords['medium'] as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 2; // Medium priority
        }
    }

    foreach ($prioritykeywords['low'] as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 3; // Low priority
        }
    }

    return 2; // Default to medium priority
}

// Get subscription details to determine if user is on free plan
$installationmanager = new \report_adeptus_insights\installation_manager();
$subscription = $installationmanager->get_subscription_details();
$isfreeplan = false;

if ($subscription) {
    $planname = strtolower($subscription['plan_name'] ?? '');
    $isfreeplan = (strpos($planname, 'free') !== false ||
                     strpos($planname, 'trial') !== false ||
                     ($subscription['price'] ?? 0) == 0);
} else {
    // Default to free plan if no subscription data
    $isfreeplan = true;
}

// Reports are now fetched dynamically from backend API via JavaScript
// This PHP file only provides the template structure
$categories = []; // Will be populated by JavaScript

// Report processing moved to JavaScript - fetching from backend API

// Free tier restrictions and report processing now handled in JavaScript

// Get user's recent reports and bookmarks
$userid = $USER->id;
$recentreports = $DB->get_records_sql("
    SELECT h.*
    FROM {report_adeptus_insights_history} h
    WHERE h.userid = ?
    AND h.id IN (
        SELECT MAX(h2.id)
        FROM {report_adeptus_insights_history} h2
        WHERE h2.userid = ?
        GROUP BY h2.reportid
    )
    ORDER BY h.generatedat DESC
", [$userid, $userid]);

// Parse parameters for recent reports
foreach ($recentreports as $key => $recentreport) {
    // Add formatted date with time
    $recentreports[$key]->formatted_date = userdate($recentreport->generatedat, '%d %B %Y at %H:%M');

    // Parse saved parameters
    $recentreports[$key]->saved_parameters = [];
    if (!empty($recentreport->parameters)) {
        $params = json_decode($recentreport->parameters, true);
        if (is_array($params)) {
            $recentreports[$key]->saved_parameters = $params;
        }
    }

    // Since reportid is now the report name (string), use it directly
    $recentreports[$key]->name = $recentreport->reportid;
    $recentreports[$key]->category = 'Unknown'; // Will be populated by frontend
    $recentreports[$key]->description = 'Report from history'; // Will be populated by frontend
}

// Get generated reports (for Generated Reports section)
$generatedreports = $DB->get_records_sql("
    SELECT g.*
    FROM {report_adeptus_insights_generated} g
    WHERE g.userid = ?
    ORDER BY g.generatedat DESC
", [$userid]);

// Parse parameters for generated reports
foreach ($generatedreports as $key => $generatedreport) {
    // Add formatted date with time
    $generatedreports[$key]->formatted_date = userdate($generatedreport->generatedat, '%d %B %Y at %H:%M');

    // Parse saved parameters
    $generatedreports[$key]->saved_parameters = [];
    if (!empty($generatedreport->parameters)) {
        $params = json_decode($generatedreport->parameters, true);
        if (is_array($params)) {
            $generatedreports[$key]->saved_parameters = $params;
        }
    }

    // Since reportid is now the report name (string), use it directly
    $generatedreports[$key]->name = $generatedreport->reportid;
    $generatedreports[$key]->category = 'Unknown'; // Will be populated by frontend
    $generatedreports[$key]->description = 'Generated report'; // Will be populated by frontend
    $generatedreports[$key]->has_data = true; // All generated reports have data
}

$bookmarks = $DB->get_records_sql("
    SELECT b.*
    FROM {report_adeptus_insights_bookmarks} b
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
$bookmarkedreportids = [];
if (!empty($bookmarks)) {
    $bookmarkedreportids = array_column($bookmarks, 'reportid');
}

// Add bookmark status to all reports
foreach ($categories as $catkey => $category) {
    foreach ($category['reports'] as $repkey => $report) {
        $categories[$catkey]['reports'][$repkey]['is_bookmarked'] = in_array($report['id'], $bookmarkedreportids);
    }
}

// Get API key for export tracking
$apikey = $installationmanager->get_api_key();
$backendapiurl = $installationmanager->get_api_url();

// Prepare template data
$templatedata = [
    'categories' => array_values($categories),
    'recent_reports' => array_values($recentreports),
    'generated_reports' => array_values($generatedreports),
    'bookmarks' => array_values($bookmarks),
    'has_recent_reports' => !empty($recentreports),
    'has_generated_reports' => !empty($generatedreports),
    'has_bookmarks' => !empty($bookmarks),
    'user_fullname' => fullname($USER),
    'wizard_title' => 'Report Wizard',
    'wwwroot' => $CFG->wwwroot,
    'sesskey' => sesskey(),
    'api_key' => $apikey,
    'backend_api_url' => $backendapiurl,
    'categories_json' => json_encode([]), // Categories loaded dynamically from backend
    'recent_reports_json' => json_encode(array_values($recentreports)),
    'generated_reports_json' => json_encode(array_values($generatedreports)),
    'bookmarks_json' => json_encode(array_values($bookmarks)),
    'bookmarked_report_ids' => json_encode($bookmarkedreportids),
    'is_free_plan' => $isfreeplan,
    'subscription' => $subscription,
    'subscription_json' => json_encode($subscription),
];

// Output the page
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_adeptus_insights/wizard', $templatedata);
echo $OUTPUT->footer();
