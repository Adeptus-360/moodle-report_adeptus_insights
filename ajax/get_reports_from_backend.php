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
 * Get reports from backend API AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and capability
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Set content type
header('Content-Type: application/json');

// Get parameters
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key.
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights'), 'debug' => 'Session validation failed']);
    exit;
}


try {
    // Get current Moodle version for compatibility filtering
    $moodleversion = $CFG->version;
    $moodleversionstring = '4.2'; // Hardcoded for now

    // Backend API configuration using centralized API config
    $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
    $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;
    $debugmode = isset($CFG->adeptus_debug_mode) ? $CFG->adeptus_debug_mode : false;


    if (!$backendenabled) {
        throw new Exception(get_string('error_backend_disabled', 'report_adeptus_insights'));
    }

    // Get API key for authentication (optional since API works without it)
    $apikey = '';
    try {
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $apikey = $installationmanager->get_api_key();
    } catch (Exception $e) {
        // Silently continue - API key is optional.
        debugging('API key retrieval failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // Prepare headers
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    // Add API key header only if we have one
    if (!empty($apikey)) {
        $headers[] = 'X-API-Key: ' . $apikey;
    }

    // Fetch reports from backend API
    // Use the reports/definitions endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $backendapiurl . '/reports/definitions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $apitimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Limit redirects
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlerror = curl_error($ch);
    $finalurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($debugmode) {
        debugging('Backend reports fetch completed, HTTP: ' . $httpcode, DEBUG_DEVELOPER);
    }

    if ($response === false) {
        throw new Exception(get_string('error_fetch_reports_failed', 'report_adeptus_insights') . ': ' . $curlerror);
    }

    if ($httpcode !== 200) {
        $errordetails = "HTTP $httpcode";
        if (!empty($curlerror)) {
            $errordetails .= ", cURL Error: $curlerror";
        }
        if (!empty($response)) {
            $errordetails .= ", Response: " . substr($response, 0, 100);
        }
        throw new Exception(get_string('error_fetch_reports_failed', 'report_adeptus_insights') . ': ' . $errordetails);
    }

    $backenddata = json_decode($response, true);
    if (!$backenddata || !$backenddata['success']) {
        throw new Exception(get_string('error_invalid_backend_response', 'report_adeptus_insights'));
    }

    $allreports = $backenddata['data'];

    // Load report validator for table/module compatibility checking

    // Filter reports for Moodle version AND table/module compatibility
    $compatiblereports = [];
    $filteredcount = 0;

    foreach ($allreports as $report) {
        $iscompatible = true;
        $filterreason = '';

        // Check minimum version
        if (!empty($report['min_moodle_version'])) {
            if (version_compare($moodleversionstring, $report['min_moodle_version'], '<')) {
                $iscompatible = false;
                $filterreason = 'moodle_version_min';
            }
        }

        // Check maximum version
        if (!empty($report['max_moodle_version'])) {
            if (version_compare($moodleversionstring, $report['max_moodle_version'], '>')) {
                $iscompatible = false;
                $filterreason = 'moodle_version_max';
            }
        }

        // Check table/module compatibility (NEW)
        if ($iscompatible) {
            $validation = \report_adeptus_insights\report_validator::validate_report($report);
            if (!$validation['valid']) {
                $iscompatible = false;
                $filterreason = 'missing_tables: ' . implode(', ', $validation['missing_tables']);

                if ($debugmode) {
                    debugging('Report filtered due to: ' . $filterreason, DEBUG_DEVELOPER);
                }
            }
        }

        if ($iscompatible && $report['isactive']) {
            $compatiblereports[] = $report;
        } else {
            $filteredcount++;
            if ($debugmode && !empty($filterreason)) {
                debugging('Filtered report: ' . ($report['name'] ?? 'unknown') . ' - ' . $filterreason, DEBUG_DEVELOPER);
            }
        }
    }

    if ($debugmode) {
        debugging('Total reports filtered: ' . $filteredcount, DEBUG_DEVELOPER);
    }

    // Organize reports by category
    $categories = [];
    foreach ($compatiblereports as $report) {
        $categoryname = $report['category'];

        if (!isset($categories[$categoryname])) {
            // Remove "Reports" from category name for display
            $displayname = str_replace(' Reports', '', $categoryname);

            $categories[$categoryname] = [
                'name' => $displayname,
                'original_name' => $categoryname,
                'icon' => 'fa-folder-o', // Default fallback icon
                'reports' => [],
                'report_count' => 0,
                'free_reports_count' => 0,
            ];
        }

        // Add report to category
        $categories[$categoryname]['reports'][] = [
            'id' => $report['name'], // Use name as ID since no local ID
            'name' => $report['name'],
            'description' => $report['description'],
            'charttype' => $report['charttype'],
            'sqlquery' => $report['sqlquery'],
            'parameters' => $report['parameters'],
            'is_free_tier' => false, // Will be set below
        ];
        $categories[$categoryname]['report_count']++;
    }

    // Apply free tier restrictions (same logic as before)
    $prioritykeywords = [
        'high' => ['overview', 'summary', 'total', 'count', 'basic', 'simple', 'main', 'general', 'all', 'complete'],
        'medium' => ['detailed', 'advanced', 'specific', 'custom', 'filtered', 'selected'],
        'low' => ['export', 'bulk', 'batch', 'comprehensive', 'extensive', 'full', 'complete', 'detailed analysis'],
    ];

    /**
     * Calculate the priority of a report based on keywords.
     *
     * @param array $report The report data.
     * @param array $prioritykeywords Priority keyword configuration.
     * @return int Priority value (1=high, 2=medium, 3=low).
     */
    function report_adeptus_insights_calculate_report_priority($report, $prioritykeywords) {
        $text = strtolower($report['name'] . ' ' . ($report['description'] ?? ''));

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

    foreach ($categories as $catkey => $category) {
        $totalreports = count($category['reports']);

        // Determine how many reports to allow for free tier
        // 1-4 reports = 1 free, 5-10 reports = 2 free, 10+ reports = 3 free (cap)
        if ($totalreports >= 1 && $totalreports <= 4) {
            $freecount = 1;
        } else if ($totalreports >= 5 && $totalreports <= 10) {
            $freecount = 2;
        } else {
            $freecount = 3; // Cap at 3 for categories with 10+ reports
        }

        // Sort reports by priority (1 = highest priority)
        usort($categories[$catkey]['reports'], function ($a, $b) use ($prioritykeywords) {
            $prioritya = report_adeptus_insights_calculate_report_priority($a, $prioritykeywords);
            $priorityb = report_adeptus_insights_calculate_report_priority($b, $prioritykeywords);
            return $prioritya <=> $priorityb;
        });

        // Mark free tier reports
        for ($i = 0; $i < count($categories[$catkey]['reports']); $i++) {
            $categories[$catkey]['reports'][$i]['is_free_tier'] = ($i < $freecount);
        }

        $categories[$catkey]['free_reports_count'] = $freecount;
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'categories' => array_values($categories),
        'total_reports' => count($compatiblereports),
        'moodle_version' => $moodleversionstring,
    ]);
} catch (Exception $e) {
    // Provide user-friendly error messages.
    $message = $e->getMessage();
    if (strpos($message, 'HTTP 301') !== false || strpos($message, 'HTTP 302') !== false) {
        $message = get_string('auth_required', 'report_adeptus_insights');
    } else if (strpos($message, 'Invalid session key') !== false) {
        $message = get_string('session_expired', 'report_adeptus_insights');
    }

    echo json_encode(['success' => false, 'message' => $message]);
}
