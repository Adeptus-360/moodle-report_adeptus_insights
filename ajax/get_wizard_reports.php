<?php
/**
 * AJAX endpoint to get wizard-generated reports for the Generated Reports page
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check for valid login
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set JSON response headers
header('Content-Type: application/json');

try {
    $userid = $USER->id;

    // Get generated reports from Moodle database
    $generated_reports = $DB->get_records_sql("
        SELECT g.*
        FROM {adeptus_generated_reports} g
        WHERE g.userid = ?
        ORDER BY g.generatedat DESC
    ", [$userid]);

    $reports = [];

    foreach ($generated_reports as $report) {
        // Parse saved parameters
        $parameters = [];
        if (!empty($report->parameters)) {
            $params = json_decode($report->parameters, true);
            if (is_array($params)) {
                $parameters = $params;
            }
        }

        // Create a slug from the report ID and timestamp
        $slug = 'wizard-' . md5($report->reportid . '-' . $report->generatedat);

        $reports[] = [
            'id' => $report->id,
            'slug' => $slug,
            'name' => $report->reportid,
            'description' => $report->reportid, // Use report name as description
            'category' => 'Wizard Report',
            'created_at' => date('c', $report->generatedat),
            'formatted_date' => userdate($report->generatedat, '%d %B %Y at %H:%M'),
            'parameters' => $parameters,
            'source' => 'wizard',
            'has_data' => false, // Data needs to be generated on demand
            'row_count' => null
        ];
    }

    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'count' => count($reports)
    ]);

} catch (Exception $e) {
    error_log('Error in get_wizard_reports.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching wizard reports: ' . $e->getMessage(),
        'reports' => []
    ]);
}
