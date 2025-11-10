<?php
// This file is part of Moodle - http://moodle.org/
//
// Generate report with parameters from wizard

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Set content type
header('Content-Type: application/json');

// Get parameters
$reportid = required_param('reportid', PARAM_TEXT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    exit;
}

try {
    // Fetch the report from backend API
    $backendEnabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendApiUrl = isset($CFG->adeptus_backend_api_url) ? $CFG->adeptus_backend_api_url : 'https://ai-backend.stagingwithswift.com/api';
    $apiTimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;
    
    if (!$backendEnabled) {
        echo json_encode(['success' => false, 'message' => 'Backend API is disabled']);
        exit;
    }
    
    // Get API key for authentication
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $api_key = $installation_manager->get_api_key();
    
    // Fetch all reports from backend to find the requested one
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/adeptus-reports/all');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if (!$response || $httpCode !== 200 || !empty($curlError)) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch reports from backend']);
        exit;
    }
    
    $backendData = json_decode($response, true);
    if (!$backendData || !$backendData['success']) {
        echo json_encode(['success' => false, 'message' => 'Invalid response from backend']);
        exit;
    }
    
    // Find the report by ID (which is now the report name)
    $report = null;
    foreach ($backendData['data'] as $backendReport) {
        // Trim whitespace and normalize for comparison
        $backendName = trim($backendReport['name']);
        $requestedName = trim($reportid);
        
        if ($backendName === $requestedName) {
            $report = $backendReport;
            break;
        }
    }
    
    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }

    // Collect ALL parameters from the request (not just those defined in backend)
    $report_params = [];
    
    // First, collect parameters defined in backend report definition
    if (!empty($report['parameters'])) {
        $param_definitions = $report['parameters'];
        if (is_array($param_definitions)) {
            foreach ($param_definitions as $param_def) {
                $param_name = $param_def['name'];
                $param_value = optional_param($param_name, '', PARAM_RAW);
                // Check if parameter has a value (including 0)
                if ($param_value !== '' && $param_value !== null) {
                $report_params[$param_name] = $param_value;
                }
            }
        }
    }
    
    // Also collect any additional parameters that might be sent from frontend
    // Common parameters that might be sent: courseid, minimum_grade, days, etc.
    $common_params = ['courseid', 'minimum_grade', 'days', 'hours', 'limit', 'count', 'startdate', 'enddate', 'userid', 'categoryid', 'groupid', 'roleid', 'threshold', 'grade_threshold', 'activity_type', 'forum_id', 'assignment_id', 'quiz_id'];
    foreach ($common_params as $param_name) {
        $param_value = optional_param($param_name, '', PARAM_RAW);
        // Only add if not already collected and has a value
        if (!isset($report_params[$param_name]) && $param_value !== '' && $param_value !== null) {
            $report_params[$param_name] = $param_value;
        }
    }
    
    // Fallback: collect ALL parameters from POST data that aren't already collected
    foreach ($_POST as $key => $value) {
        // Skip system parameters
        if (in_array($key, ['reportid', 'sesskey'])) {
            continue;
        }
        // Add any parameter not already collected
        if (!isset($report_params[$key]) && $value !== '' && $value !== null) {
            $report_params[$key] = $value;
        }
    }
    
    // Debug: Log all request data and collected parameters (only in debug mode)
    if (defined('CFG_DEBUG') && CFG_DEBUG) {
        error_log("=== REPORT GENERATION DEBUG ===");
        error_log("Report: {$report['name']}");
        error_log("All POST data: " . json_encode($_POST));
        error_log("Collected parameters: " . json_encode($report_params));
        error_log("Backend report parameters definition: " . json_encode($report['parameters'] ?? []));
    }

    // Execute the SQL query with parameters
    $sql = $report['sqlquery'];
    
    // Handle both named (:param) and positional (?) parameters
    if (!empty($report_params)) {
        // Check if SQL uses named parameters (:param) or positional (?) parameters
        if (strpos($sql, ':') !== false) {
            // Convert named parameters to positional parameters for Moodle compatibility
            $sql_params = [];
            $param_order = [];
            
            // Extract parameter names from SQL in order
            preg_match_all('/:(\w+)/', $sql, $matches);
            $param_order = $matches[1];
            
            // Replace named parameters with positional ones
            $positional_sql = $sql;
            foreach ($param_order as $index => $param_name) {
                $positional_sql = preg_replace('/:' . preg_quote($param_name, '/') . '\b/', '?', $positional_sql, 1);
                
                // Get parameter value with special handling
                if ($param_name === 'days' && is_numeric($report_params[$param_name])) {
                    $sql_params[] = time() - ($report_params[$param_name] * 24 * 60 * 60);
                } else {
                    $sql_params[] = $report_params[$param_name] ?? '';
                }
            }
            
            // Use get_records_sql with positional parameters
            if (defined('CFG_DEBUG') && CFG_DEBUG) {
                error_log("Original SQL: " . $sql);
                error_log("Positional SQL: " . $positional_sql);
                error_log("SQL Parameters: " . json_encode($sql_params));
            }
            $results = $DB->get_records_sql($positional_sql, $sql_params);
        } else {
            // Use positional parameters
            $sql_params = [];
        foreach ($report_params as $name => $value) {
            // Special handling: convert 'days' to cutoff timestamp
            if ($name === 'days' && is_numeric($value)) {
                $sql_params[] = time() - ($value * 24 * 60 * 60);
                } else {
                $sql_params[] = $value;
                }
            }
            // Use get_records_sql with positional parameters
            $results = $DB->get_records_sql($sql, $sql_params);
        }
    } else {
        // No parameters, execute directly
        $results = $DB->get_records_sql($sql);
    }
    
    // Convert to array and get headers
    $results_array = [];
    $headers = [];
    
    if (!empty($results)) {
        $first_row = reset($results);
        $headers = array_keys((array)$first_row);
        
        foreach ($results as $row) {
            $results_array[] = (array)$row;
        }
    }

    // Track report generation usage (only if results returned and not a duplicate)
    // Only consider a report "generated" if it has actual data
    $has_data = !empty($results_array);
    $report_generated = $has_data; // Only count reports with data
    $is_duplicate = false; // No duplicate checking - always count new generations
    
    // Save to history (for Recent Reports section) - always save for history
    $history_record = new stdClass();
    $history_record->userid = $USER->id;
    $history_record->reportid = $reportid;
    $history_record->parameters = json_encode($report_params);
    $history_record->generatedat = time();
    $history_record->resultpath = ''; // Could save to file if needed
    $history_record->counted_for_usage = (!$is_duplicate && $report_generated) ? 1 : 0; // Flag to track if this generation was counted
    
    $DB->insert_record('adeptus_report_history', $history_record);
    
    // Save to generated reports (for Generated Reports section) - only save if report has data
    if ($report_generated && $has_data) {
        error_log("DEBUG: Saving to generated reports table - report_generated: {$report_generated}");
        
        // Check if a record already exists with same userid, reportid, and parameters
        // Use sql_compare_text() for TEXT column comparison
        $params_json = json_encode($report_params);
        $sql = "SELECT * FROM {adeptus_generated_reports} 
                WHERE userid = :userid 
                AND reportid = :reportid 
                AND " . $DB->sql_compare_text('parameters') . " = " . $DB->sql_compare_text(':parameters');
        
        $existing_records = $DB->get_records_sql($sql, array(
            'userid' => $USER->id,
            'reportid' => $reportid,
            'parameters' => $params_json
        ));
        
        $existing_record = !empty($existing_records) ? reset($existing_records) : null;
        
        $is_new_generation = false; // Track if this is a new generation or update
        
        if ($existing_record) {
            // Update existing record with new timestamp
            error_log("DEBUG: Found existing generated report record, updating timestamp");
            $existing_record->generatedat = time();
            $existing_record->counted_for_usage = (!$is_duplicate) ? 1 : 0; // Update usage flag
            $DB->update_record('adeptus_generated_reports', $existing_record);
            error_log("DEBUG: Generated report updated with ID: {$existing_record->id}");
            $is_new_generation = false; // This is an update, not a new generation
        } else {
            // Insert new record
            error_log("DEBUG: No existing record found, creating new generated report record");
            $generated_record = new stdClass();
            $generated_record->userid = $USER->id;
            $generated_record->reportid = $reportid;
            $generated_record->parameters = json_encode($report_params);
            $generated_record->generatedat = time();
            $generated_record->resultpath = ''; // Could save to file if needed
            $generated_record->counted_for_usage = (!$is_duplicate) ? 1 : 0; // Flag to track if this generation was counted
            
            error_log("DEBUG: Generated record data: " . json_encode($generated_record));
            
            $insert_id = $DB->insert_record('adeptus_generated_reports', $generated_record);
            error_log("DEBUG: Generated report saved with ID: {$insert_id}");
            $is_new_generation = true; // This is a new generation
        }
    } else {
        error_log("DEBUG: Not saving to generated reports - report_generated: {$report_generated}");
        $is_new_generation = false; // Ensure variable is defined
    }
    
    // Update subscription usage in backend if report was generated (only count new generations, not updates)
    if (!$is_duplicate && $report_generated && $is_new_generation) {
        error_log("DEBUG: Attempting to track report generation - is_duplicate: {$is_duplicate}, report_generated: {$report_generated}, is_new_generation: {$is_new_generation}");
        try {
            // Get API configuration from installation manager
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
        $installation_manager = new \report_adeptus_insights\installation_manager();
            
            $backend_api_url = $installation_manager->get_api_url();
        $api_key = $installation_manager->get_api_key();
        
            error_log("DEBUG: Backend API URL: {$backend_api_url}");
            error_log("DEBUG: API Key present: " . (!empty($api_key) ? 'YES' : 'NO'));
            
            $update_data = [
                'report_name' => $report['name'],
                'result_count' => count($results_array)
            ];
            
            error_log("DEBUG: Update data: " . json_encode($update_data));
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backend_api_url . '/subscription/track-report-generation');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-Key: ' . $api_key
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("DEBUG: Backend API response - HTTP Code: {$http_code}, Response: {$response}");
            
            if ($http_code === 200) {
                error_log("Successfully tracked report generation for user {$USER->id}: {$response}");
            } else {
                error_log("Failed to track report generation: HTTP {$http_code}, Response: {$response}");
            }
        } catch (Exception $e) {
            error_log("Error tracking report generation: " . $e->getMessage());
        }
    } else {
        error_log("DEBUG: Skipping backend tracking - is_duplicate: {$is_duplicate}, report_generated: {$report_generated}");
    }

    // Prepare chart data if chart type is specified
    $chart_data = null;
    if (!empty($report->charttype) && !empty($results_array)) {
        // Find the best columns for labels and values
        $label_column = $headers[0] ?? 'id';
        $value_column = null;
        
        // Analyze all columns to find numeric ones and their value ranges
        $numeric_columns = [];
        $column_stats = [];
        $mb_column = null;
        
        foreach ($headers as $header) {
            $column_values = array_column($results_array, $header);
            $numeric_values = [];
            $is_numeric_column = true;
            
            // Check if all values in this column are numeric
            foreach ($column_values as $value) {
                if (is_numeric($value)) {
                    $numeric_values[] = (float)$value;
                } elseif (is_string($value) && is_numeric(trim($value))) {
                    $numeric_values[] = (float)trim($value);
                } else {
                    $is_numeric_column = false;
                    break;
                }
            }
            
            // If column is numeric, calculate its statistics
            if ($is_numeric_column && !empty($numeric_values)) {
                $numeric_columns[] = $header;
                $column_stats[$header] = [
                    'max' => max($numeric_values),
                    'min' => min($numeric_values),
                    'sum' => array_sum($numeric_values),
                    'count' => count($numeric_values),
                    'avg' => array_sum($numeric_values) / count($numeric_values)
                ];
                
                // Special case: Check if column name contains "(mb)"
                if (strpos($header, '(mb)') !== false) {
                    $mb_column = $header;
                }
            }
        }
        
        // Select the column with priority: (mb) column first, then highest maximum value
        if (!empty($mb_column)) {
            $value_column = $mb_column;
        } elseif (!empty($numeric_columns)) {
            $max_value = 0;
            foreach ($numeric_columns as $column) {
                if ($column_stats[$column]['max'] > $max_value) {
                    $max_value = $column_stats[$column]['max'];
                    $value_column = $column;
                }
            }
        } else {
            // Fallback to second column if no numeric columns found
            $value_column = $headers[1] ?? 'value';
        }
        
        // Convert values to numbers if they're strings
        $chart_values = array_column($results_array, $value_column);
        $chart_values = array_map(function($value) {
            return is_numeric($value) ? (float)$value : (is_string($value) && is_numeric(trim($value)) ? (float)trim($value) : 0);
        }, $chart_values);
        
        // Generate colors based on chart type
        $colors = generateChartColors(count($chart_values), $report->charttype);
        
        $chart_data = [
            'labels' => array_column($results_array, $label_column),
            'datasets' => [
                [
                    'label' => $report->name,
                    'data' => $chart_values,
                    'backgroundColor' => $colors,
                    'borderColor' => adjustColors($colors, -20),
                    'borderWidth' => 2
                ]
            ],
            'axis_labels' => [
                'x_axis' => $label_column,
                'y_axis' => $value_column
            ]
        ];
    }

    // Get installation manager for debug info
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $debug_installation_manager = new \report_adeptus_insights\installation_manager();
    
    // Return success response with debug info
    echo json_encode([
        'success' => true,
        'report_name' => $report['name'],
        'report_id' => $reportid,
        'results' => $results_array,
        'headers' => $headers,
        'chart_data' => $chart_data,
        'chart_type' => $report['charttype'],
        'parameters_used' => $report_params,
        'is_duplicate' => $is_duplicate,
        'execution_time' => time(),
        'debug_info' => [
            'report_generated' => $report_generated,
            'is_duplicate' => $is_duplicate,
            'results_count' => count($results_array),
            'backend_api_url' => $debug_installation_manager->get_api_url(),
            'api_key_present' => !empty($debug_installation_manager->get_api_key()) ? 'yes' : 'no'
        ]
    ]);

} catch (Exception $e) {
    error_log('Error in generate_report.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error executing report: ' . $e->getMessage(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}

/**
 * Generate colors for charts based on chart type and data count
 */
function generateChartColors($count, $chartType) {
    $baseColors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
        '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#17a2b8',
        '#6610f2', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'
    ];
    
    $chartType = strtolower($chartType);
    
    if ($chartType === 'pie' || $chartType === 'donut' || $chartType === 'polar') {
        // Generate distinct colors for each data point
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $baseColors[$i % count($baseColors)];
        }
        return $colors;
    } else {
        // Use single color for bar, line, radar charts
        return [$baseColors[0]];
    }
}

/**
 * Adjust colors (lighten or darken) for border colors
 */
function adjustColors($colors, $amount) {
    if (is_array($colors)) {
        return array_map(function($color) use ($amount) {
            return adjustColor($color, $amount);
        }, $colors);
    } else {
        return adjustColor($colors, $amount);
    }
}

/**
 * Adjust a single color by lightening or darkening it
 */
function adjustColor($color, $amount) {
    // Remove # if present
    $color = ltrim($color, '#');
    
    // Convert to RGB
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    // Adjust each component
    $r = max(0, min(255, $r + $amount));
    $g = max(0, min(255, $g + $amount));
    $b = max(0, min(255, $b + $amount));
    
    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

exit; 