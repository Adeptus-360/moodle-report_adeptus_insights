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
 * Generate report with parameters from wizard.
 *
 * This AJAX endpoint handles report generation requests from the Report Wizard,
 * executing SQL queries with user-provided parameters and returning results.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('READ_ONLY_SESSION', true); // Allow parallel requests

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/api_config.php'); // Load API config

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Release session lock early to allow parallel AJAX requests
\core\session\manager::write_close();

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
    $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();
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
    curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/reports/definitions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $api_key,
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

    // VALIDATE REPORT COMPATIBILITY
    // Check if this report can run on current Moodle installation
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/report_validator.php');
    $validation = \report_adeptus_insights\report_validator::validate_report($report);

    if (!$validation['valid']) {
        // Report cannot be executed - missing required tables
        $missing = implode(', ', $validation['missing_tables']);

        echo json_encode([
            'success' => false,
            'error' => 'report_incompatible',
            'message' => "This report requires Moodle modules or features that are not installed on your system.",
            'details' => $validation['reason'],
            'missing_tables' => $validation['missing_tables'],
        ]);
        exit;
    }

    // =========================================================================
    // SERVER-SIDE ENFORCEMENT: Check report limits (TAMPER-PROOF)
    // This check happens server-side and cannot be bypassed by client
    // =========================================================================

    // Check report creation eligibility with backend (cumulative limits)
    $limits_endpoint = rtrim($backendApiUrl, '/') . '/api/v1/report-limits/check';
    $ch_limits = curl_init();
    curl_setopt($ch_limits, CURLOPT_URL, $limits_endpoint);
    curl_setopt($ch_limits, CURLOPT_POST, true);
    curl_setopt($ch_limits, CURLOPT_POSTFIELDS, json_encode(new stdClass()));
    curl_setopt($ch_limits, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $api_key,
    ]);
    curl_setopt($ch_limits, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_limits, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch_limits, CURLOPT_CONNECTTIMEOUT, 10);

    $limits_response = curl_exec($ch_limits);
    $limits_http_code = curl_getinfo($ch_limits, CURLINFO_HTTP_CODE);
    $limits_curl_error = curl_error($ch_limits);
    curl_close($ch_limits);

    // FAIL CLOSED: If we can't verify limits, deny the request
    if ($limits_response === false || !empty($limits_curl_error)) {
        error_log('[Adeptus Insights] Report limits check failed - curl error: ' . $limits_curl_error);
        echo json_encode([
            'success' => false,
            'error' => 'limit_check_failed',
            'message' => 'Unable to verify report eligibility. Please try again later.',
        ]);
        exit;
    }

    if ($limits_http_code !== 200) {
        error_log('[Adeptus Insights] Report limits check failed - HTTP ' . $limits_http_code);
        echo json_encode([
            'success' => false,
            'error' => 'limit_check_failed',
            'message' => 'Unable to verify report eligibility. Please try again later.',
        ]);
        exit;
    }

    $limits_data = json_decode($limits_response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($limits_data['eligible'])) {
        error_log('[Adeptus Insights] Report limits check failed - invalid response');
        echo json_encode([
            'success' => false,
            'error' => 'limit_check_failed',
            'message' => 'Unable to verify report eligibility. Please try again later.',
        ]);
        exit;
    }

    // Check if user has reached their report limit
    if (!$limits_data['eligible']) {
        echo json_encode([
            'success' => false,
            'error' => 'limit_reached',
            'error_type' => 'limit_reached',
            'message' => $limits_data['message'] ?? 'You have reached your report limit. Delete existing reports or upgrade your plan.',
            'reports_used' => $limits_data['reports_used'] ?? 0,
            'reports_limit' => $limits_data['reports_limit'] ?? 0,
            'reports_remaining' => $limits_data['reports_remaining'] ?? 0,
            'upgrade_required' => true,
        ]);
        exit;
    }

    // =========================================================================
    // SERVER-SIDE ENFORCEMENT: Check subscription tier access
    // =========================================================================
    $report_key = $report['report_key'] ?? $report['name'] ?? $reportid;
    $is_ai_generated = $report['is_ai_generated'] ?? false;

    // Check if user can access this report based on tier
    $access_check = $installation_manager->check_report_access($report_key);

    if (!$access_check['allowed']) {
        $error_response = [
            'success' => false,
            'error' => 'access_denied',
            'error_type' => $access_check['reason'], // 'tier_required' or 'limit_reached'
            'message' => $access_check['message'],
        ];

        // Add upgrade info for tier-related issues
        if ($access_check['reason'] === 'tier_required') {
            $error_response['required_tier'] = $access_check['required_tier'] ?? null;
            $error_response['current_tier'] = $access_check['current_tier'] ?? null;
            $error_response['upgrade_required'] = true;
        }

        // Add usage info for limit-related issues
        if ($access_check['reason'] === 'limit_reached') {
            $error_response['current_usage'] = $access_check['current_usage'] ?? null;
            $error_response['limit'] = $access_check['limit'] ?? null;
            $error_response['upgrade_required'] = true;
        }

        // Add upgrade URL if available
        if (!empty($access_check['upgrade_url'])) {
            $error_response['upgrade_url'] = $access_check['upgrade_url'];
        }

        echo json_encode($error_response);
        exit;
    }
    // =========================================================================

    // Collect ALL parameters from the request (not just those defined in backend)
    $report_params = [];

    // First, collect parameters defined in backend report definition
    if (!empty($report['parameters'])) {
        $param_definitions = $report['parameters'];
        if (is_array($param_definitions)) {
            // Extract actual parameter definitions (arrays with 'name' key)
            // Skip metadata entries like 'charttype' which are scalar values
            foreach ($param_definitions as $key => $param_def) {
                // Check if this is a parameter definition (array with 'name' key)
                if (!is_array($param_def) || !isset($param_def['name'])) {
                    continue; // Skip non-parameter entries (like 'charttype')
                }
                $param_name = $param_def['name'];
                $param_value = optional_param($param_name, '', PARAM_RAW);
                // Check if parameter has a value (including 0)
                if ($param_value !== '' && $param_value !== null) {
                    $report_params[$param_name] = $param_value;
                } else if (isset($param_def['default'])) {
                    // Apply default value if no value provided
                    $report_params[$param_name] = $param_def['default'];
                }
            }
        }
    }

    // Also collect any additional parameters that might be sent from frontend.
    // Common parameters are sanitized via optional_param with PARAM_RAW for flexibility,
    // then further validated when bound as SQL parameters.
    $common_params = [
        'courseid', 'minimum_grade', 'days', 'hours', 'limit', 'count',
        'startdate', 'enddate', 'userid', 'categoryid', 'groupid', 'roleid',
        'threshold', 'grade_threshold', 'activity_type', 'forum_id',
        'assignment_id', 'quiz_id', 'module_id', 'section_id', 'status',
        'completion_status', 'grade_min', 'grade_max', 'date_from', 'date_to',
        'time_period', 'activity_count', 'login_count', 'submission_status',
    ];
    foreach ($common_params as $param_name) {
        $param_value = optional_param($param_name, '', PARAM_RAW);
        // Only add if not already collected and has a value.
        if (!isset($report_params[$param_name]) && $param_value !== '' && $param_value !== null) {
            $report_params[$param_name] = $param_value;
        }
    }

    // Execute the SQL query with parameters
    $sql = $report['sqlquery'];

    // SAFETY CHECK: Add safety limit if no LIMIT clause exists
    $SAFETY_LIMIT = 100000; // Maximum 100K records to prevent browser freeze
    // Match LIMIT with number OR named parameter (e.g., LIMIT 100 or LIMIT :limit)
    $has_limit = preg_match('/\bLIMIT\s+(\d+|:\w+|\?)/i', $sql);

    if (!$has_limit) {
        // Log warning that safety limit is being applied

        // Add LIMIT clause to the end of the query
        // Handle queries that may end with semicolon
        $sql = rtrim($sql);
        $sql = rtrim($sql, ';');
        $sql .= " LIMIT $SAFETY_LIMIT";
    }

    // Handle :limit parameter specially - MySQL LIMIT doesn't support bound parameters
    // Replace :limit directly in SQL with sanitized integer value
    if (isset($report_params['limit']) && is_numeric($report_params['limit'])) {
        $limit_val = min(intval($report_params['limit']), 100000); // Cap at 100K for safety
        $sql = preg_replace('/\bLIMIT\s+:limit\b/i', 'LIMIT ' . $limit_val, $sql);
        unset($report_params['limit']); // Remove from params as it's now in SQL
    }

    // Handle both named (:param) and positional (?) parameters
    try {
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
                    if ($param_name === 'days' && is_numeric($report_params[$param_name] ?? '')) {
                        // Convert days to Unix timestamp cutoff
                        $sql_params[] = time() - (intval($report_params[$param_name]) * 24 * 60 * 60);
                    } else {
                        $sql_params[] = $report_params[$param_name] ?? '';
                    }
                }

                // Use get_records_sql with positional parameters
                $results = $DB->get_records_sql($positional_sql, $sql_params);
            } else {
                // Use positional parameters
                $sql_params = [];
                foreach ($report_params as $name => $value) {
                    // Special handling: convert 'days' to cutoff timestamp
                    if ($name === 'days' && is_numeric($value)) {
                        $sql_params[] = time() - (intval($value) * 24 * 60 * 60);
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
    } catch (\dml_read_exception $e) {
        throw new Exception("SQL Error: " . $e->debuginfo);
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
    // Note: Usage tracking is handled via backend API, not local database flag

    $DB->insert_record('adeptus_report_history', $history_record);

    // Save to generated reports via backend API (for Generated Reports section) - only save if report has data
    if ($report_generated && $has_data) {
        $is_new_generation = false; // Track if this is a new generation or update

        // Prepare the report data for the backend
        $wizard_report_data = [
            'user_id' => $USER->id,
            'report_template_id' => $reportid,
            'name' => $report['name'] ?? $reportid,
            'parameters' => $report_params,
        ];

        // Call backend API to save the wizard report
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/wizard-reports');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wizard_report_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $api_key,
        ]);

        $save_response = curl_exec($ch);
        $save_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $save_curl_error = curl_error($ch);
        curl_close($ch);

        if ($save_http_code === 201 || $save_http_code === 200) {
            $save_data = json_decode($save_response, true);
            if (!empty($save_data['success'])) {
                $is_new_generation = true;
            } else {
            }
        } else {
        }
    } else {
        $is_new_generation = false; // Ensure variable is defined
    }

    // Update subscription usage in backend if report was generated (only count new generations, not updates)
    if (!$is_duplicate && $report_generated && $is_new_generation) {
        try {
            // Track report generation using the installation manager method
            $tracking_result = $installation_manager->track_report_generation($report_key, $is_ai_generated);

            if ($tracking_result) {
            } else {
            }
        } catch (Exception $e) {
            // Silently continue - tracking failure shouldn't break report generation.
        }
    } else {
    }

    // Prepare chart data if chart type is specified
    $chart_data = null;
    if (!empty($report['charttype']) && !empty($results_array)) {
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
                } else if (is_string($value) && is_numeric(trim($value))) {
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
                    'avg' => array_sum($numeric_values) / count($numeric_values),
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
        } else if (!empty($numeric_columns)) {
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
        $chart_values = array_map(function ($value) {
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
                    'borderWidth' => 2,
                ],
            ],
            'axis_labels' => [
                'x_axis' => $label_column,
                'y_axis' => $value_column,
            ],
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
            'api_key_present' => !empty($debug_installation_manager->get_api_key()) ? 'yes' : 'no',
        ],
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error executing report: ' . $e->getMessage(),
    ]);
}

/**
 * Generate colors for charts based on chart type and data count
 */
function generateChartColors($count, $chartType) {
    $baseColors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
        '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#17a2b8',
        '#6610f2', '#fd7e14', '#20c997', '#e83e8c', '#6c757d',
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
        return array_map(function ($color) use ($amount) {
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
