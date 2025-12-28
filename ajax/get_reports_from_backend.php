<?php
// This file is part of Moodle - http://moodle.org/
//
// Fetch reports from backend API with version compatibility filtering

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/api_config.php'); // Load API config

// Require login and capability
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Set content type
header('Content-Type: application/json');

// Get parameters
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    error_log("Invalid session key provided: " . $sesskey);
    echo json_encode(['success' => false, 'message' => 'Invalid session key', 'debug' => 'Session validation failed']);
    exit;
}


try {
    // Get current Moodle version for compatibility filtering
    $moodle_version = $CFG->version;
    $moodle_version_string = '4.2'; // Hardcoded for now

    // Backend API configuration using centralized API config
    $backendEnabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();
    $apiTimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;
    $debugMode = isset($CFG->adeptus_debug_mode) ? $CFG->adeptus_debug_mode : false;
    
    
    if (!$backendEnabled) {
        throw new Exception('Backend API is disabled');
    }
    
    // Get API key for authentication (optional since API works without it)
    $api_key = '';
    try {
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
        $installation_manager = new \report_adeptus_insights\installation_manager();
        $api_key = $installation_manager->get_api_key();
    } catch (Exception $e) {
        if ($debugMode) {
            error_log("Could not get API key: " . $e->getMessage());
        }
    }
    
    // Prepare headers
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    // Add API key header only if we have one
    if (!empty($api_key)) {
        $headers[] = 'X-API-Key: ' . $api_key;
    }
    
    // Fetch reports from backend API
    // Use the reports/definitions endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/reports/definitions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Limit redirects
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    if ($debugMode) {
        error_log("Backend API call for reports: HTTP $httpCode, Response: " . substr($response, 0, 200));
        error_log("Final URL after redirects: " . $finalUrl);
    }
    
    if ($response === false) {
        throw new Exception('Failed to fetch reports from backend: cURL Error - ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        $errorDetails = "HTTP $httpCode";
        if (!empty($curlError)) {
            $errorDetails .= ", cURL Error: $curlError";
        }
        if (!empty($response)) {
            $errorDetails .= ", Response: " . substr($response, 0, 100);
        }
        throw new Exception('Failed to fetch reports from backend: ' . $errorDetails);
    }
    
    $backendData = json_decode($response, true);
    if (!$backendData || !$backendData['success']) {
        throw new Exception('Invalid response from backend API');
    }
    
    $all_reports = $backendData['data'];

    // Load report validator for table/module compatibility checking
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/report_validator.php');

    // Filter reports for Moodle version AND table/module compatibility
    $compatible_reports = [];
    $filtered_count = 0;

    foreach ($all_reports as $report) {
        $is_compatible = true;
        $filter_reason = '';

        // Check minimum version
        if (!empty($report['min_moodle_version'])) {
            if (version_compare($moodle_version_string, $report['min_moodle_version'], '<')) {
                $is_compatible = false;
                $filter_reason = 'moodle_version_min';
            }
        }

        // Check maximum version
        if (!empty($report['max_moodle_version'])) {
            if (version_compare($moodle_version_string, $report['max_moodle_version'], '>')) {
                $is_compatible = false;
                $filter_reason = 'moodle_version_max';
            }
        }

        // Check table/module compatibility (NEW)
        if ($is_compatible) {
            $validation = \report_adeptus_insights\report_validator::validate_report($report);
            if (!$validation['valid']) {
                $is_compatible = false;
                $filter_reason = 'missing_tables: ' . implode(', ', $validation['missing_tables']);

                if ($debugMode) {
                    error_log("Report '{$report['name']}' filtered out: {$validation['reason']}");
                }
            }
        }

        if ($is_compatible && $report['isactive']) {
            $compatible_reports[] = $report;
        } else {
            $filtered_count++;
            if ($debugMode && !empty($filter_reason)) {
                error_log("Filtered report '{$report['name']}': {$filter_reason}");
            }
        }
    }

    if ($debugMode) {
        error_log("Report filtering: " . count($all_reports) . " total, " . count($compatible_reports) . " compatible, {$filtered_count} filtered");
    }
    
    // Organize reports by category
    $categories = [];
    foreach ($compatible_reports as $report) {
        $category_name = $report['category'];
        
        if (!isset($categories[$category_name])) {
            // Remove "Reports" from category name for display
            $display_name = str_replace(' Reports', '', $category_name);
            
            $categories[$category_name] = [
                'name' => $display_name,
                'original_name' => $category_name,
                'icon' => 'fa-folder-o', // Default fallback icon
                'reports' => [],
                'report_count' => 0,
                'free_reports_count' => 0
            ];
        }
        
        // Add report to category
        $categories[$category_name]['reports'][] = [
            'id' => $report['name'], // Use name as ID since no local ID
            'name' => $report['name'],
            'description' => $report['description'],
            'charttype' => $report['charttype'],
            'sqlquery' => $report['sqlquery'],
            'parameters' => $report['parameters'],
            'is_free_tier' => false // Will be set below
        ];
        $categories[$category_name]['report_count']++;
    }
    
    // Apply free tier restrictions (same logic as before)
    $priority_keywords = [
        'high' => ['overview', 'summary', 'total', 'count', 'basic', 'simple', 'main', 'general', 'all', 'complete'],
        'medium' => ['detailed', 'advanced', 'specific', 'custom', 'filtered', 'selected'],
        'low' => ['export', 'bulk', 'batch', 'comprehensive', 'extensive', 'full', 'complete', 'detailed analysis']
    ];
    
    function calculate_report_priority($report, $priority_keywords) {
        $text = strtolower($report['name'] . ' ' . ($report['description'] ?? ''));
        
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
    
    foreach ($categories as $cat_key => $category) {
        $total_reports = count($category['reports']);
        
        // Determine how many reports to allow for free tier
        // 1-4 reports = 1 free, 5-10 reports = 2 free, 10+ reports = 3 free (cap)
        if ($total_reports >= 1 && $total_reports <= 4) {
            $free_count = 1;
        } elseif ($total_reports >= 5 && $total_reports <= 10) {
            $free_count = 2;
        } else {
            $free_count = 3; // Cap at 3 for categories with 10+ reports
        }
        
        // Sort reports by priority (1 = highest priority)
        usort($categories[$cat_key]['reports'], function($a, $b) use ($priority_keywords) {
            $priority_a = calculate_report_priority($a, $priority_keywords);
            $priority_b = calculate_report_priority($b, $priority_keywords);
            return $priority_a <=> $priority_b;
        });
        
        // Mark free tier reports
        for ($i = 0; $i < count($categories[$cat_key]['reports']); $i++) {
            $categories[$cat_key]['reports'][$i]['is_free_tier'] = ($i < $free_count);
        }
        
        $categories[$cat_key]['free_reports_count'] = $free_count;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'categories' => array_values($categories),
        'total_reports' => count($compatible_reports),
        'moodle_version' => $moodle_version_string
    ]);

} catch (Exception $e) {
    error_log('Error in get_reports_from_backend.php: ' . $e->getMessage());
    
    // Provide user-friendly error messages
    $message = $e->getMessage();
    if (strpos($message, 'HTTP 301') !== false || strpos($message, 'HTTP 302') !== false) {
        $message = 'Authentication required. Please log in to access reports.';
    } elseif (strpos($message, 'Invalid session key') !== false) {
        $message = 'Session expired. Please refresh the page and log in again.';
    }
    
    echo json_encode(['success' => false, 'message' => $message]);
}
