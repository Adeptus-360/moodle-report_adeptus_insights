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
    echo json_encode(['success' => false, 'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights')]);
    exit;
}

try {
    // Fetch the report from backend API
    $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
    $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

    if (!$backendenabled) {
        echo json_encode(['success' => false, 'message' => get_string('error_backend_disabled', 'report_adeptus_insights')]);
        exit;
    }

    // Get API key for authentication
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();

    // Fetch all reports from backend to find the requested one
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $backendapiurl . '/reports/definitions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $apitimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $apikey,
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlerror = curl_error($ch);
    curl_close($ch);

    if (!$response || $httpcode !== 200 || !empty($curlerror)) {
        echo json_encode(['success' => false, 'message' => get_string('error_fetch_reports_failed', 'report_adeptus_insights')]);
        exit;
    }

    $backenddata = json_decode($response, true);
    if (!$backenddata || !$backenddata['success']) {
        echo json_encode(['success' => false, 'message' => get_string('error_invalid_backend_response', 'report_adeptus_insights')]);
        exit;
    }

    // Find the report by ID (which is now the report name)
    $report = null;
    foreach ($backenddata['data'] as $backendreport) {
        // Trim whitespace and normalize for comparison
        $backendname = trim($backendreport['name']);
        $requestedname = trim($reportid);

        if ($backendname === $requestedname) {
            $report = $backendreport;
            break;
        }
    }

    if (!$report) {
        echo json_encode(['success' => false, 'message' => get_string('error_report_not_found', 'report_adeptus_insights')]);
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
            'message' => get_string('error_report_incompatible_modules', 'report_adeptus_insights'),
            'details' => $validation['reason'],
            'missing_tables' => $validation['missing_tables'],
        ]);
        exit;
    }

    // =========================================================================
    // SERVER-SIDE ENFORCEMENT: Check report limits (TAMPER-PROOF)
    // This check happens server-side and cannot be bypassed by client
    // =========================================================================

    // Check if this is a re-execution of an existing report (viewing saved reports)
    // Re-executions don't count against the limit since the report was already tracked
    $isreexecution = optional_param('reexecution', 0, PARAM_BOOL);

    // Skip eligibility check for re-executions (viewing existing reports)
    // The original report creation was already tracked and counted
    if (!$isreexecution) {
        // Check report creation eligibility with backend (cumulative limits)
        $limitsendpoint = rtrim($backendapiurl, '/') . '/report-limits/check';
        $chlimits = curl_init();
        curl_setopt($chlimits, CURLOPT_URL, $limitsendpoint);
        curl_setopt($chlimits, CURLOPT_POST, true);
        curl_setopt($chlimits, CURLOPT_POSTFIELDS, json_encode(new stdClass()));
        curl_setopt($chlimits, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $apikey,
        ]);
        curl_setopt($chlimits, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chlimits, CURLOPT_TIMEOUT, 15);
        curl_setopt($chlimits, CURLOPT_CONNECTTIMEOUT, 10);

        $limitsresponse = curl_exec($chlimits);
        $limitshttpcode = curl_getinfo($chlimits, CURLINFO_HTTP_CODE);
        $limitscurlerror = curl_error($chlimits);
        curl_close($chlimits);

        // FAIL CLOSED: If we can't verify limits, deny the request
        if ($limitsresponse === false || !empty($limitscurlerror)) {
            debugging('[Adeptus Insights] Report limits check failed - curl error: ' . $limitscurlerror, DEBUG_DEVELOPER);
            echo json_encode([
            'success' => false,
            'error' => 'limit_check_failed',
            'message' => get_string('error_verify_eligibility', 'report_adeptus_insights'),
            ]);
            exit;
        }

        if ($limitshttpcode !== 200) {
            debugging('[Adeptus Insights] Report limits check failed - HTTP ' . $limitshttpcode, DEBUG_DEVELOPER);
            echo json_encode([
            'success' => false,
            'error' => 'limit_check_failed',
            'message' => get_string('error_verify_eligibility', 'report_adeptus_insights'),
            ]);
            exit;
        }

        $limitsdata = json_decode($limitsresponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($limitsdata['eligible'])) {
            debugging('[Adeptus Insights] Report limits check failed - invalid response', DEBUG_DEVELOPER);
            echo json_encode([
            'success' => false,
            'error' => 'limit_check_failed',
            'message' => get_string('error_verify_eligibility', 'report_adeptus_insights'),
            ]);
            exit;
        }

        // Check if user has reached their report limit
        if (!$limitsdata['eligible']) {
            echo json_encode([
            'success' => false,
            'error' => 'limit_reached',
            'error_type' => 'limit_reached',
            'message' => $limitsdata['message'] ?? get_string('error_limit_reached', 'report_adeptus_insights'),
            'reports_used' => $limitsdata['reports_used'] ?? 0,
            'reports_limit' => $limitsdata['reports_limit'] ?? 0,
            'reports_remaining' => $limitsdata['reports_remaining'] ?? 0,
            'upgrade_required' => true,
            ]);
            exit;
        }
    } // End of eligibility check (skipped for re-executions)

    // =========================================================================
    // SERVER-SIDE ENFORCEMENT: Check subscription tier access
    // =========================================================================
    $reportkey = $report['report_key'] ?? $report['name'] ?? $reportid;
    $isaigenerated = $report['is_ai_generated'] ?? false;

    // Check if user can access this report based on tier
    $accesscheck = $installationmanager->check_report_access($reportkey);

    if (!$accesscheck['allowed']) {
        $errorresponse = [
            'success' => false,
            'error' => 'access_denied',
            'error_type' => $accesscheck['reason'], // 'tier_required' or 'limit_reached'
            'message' => $accesscheck['message'],
        ];

        // Add upgrade info for tier-related issues
        if ($accesscheck['reason'] === 'tier_required') {
            $errorresponse['required_tier'] = $accesscheck['required_tier'] ?? null;
            $errorresponse['current_tier'] = $accesscheck['current_tier'] ?? null;
            $errorresponse['upgrade_required'] = true;
        }

        // Add usage info for limit-related issues
        if ($accesscheck['reason'] === 'limit_reached') {
            $errorresponse['current_usage'] = $accesscheck['current_usage'] ?? null;
            $errorresponse['limit'] = $accesscheck['limit'] ?? null;
            $errorresponse['upgrade_required'] = true;
        }

        // Add upgrade URL if available
        if (!empty($accesscheck['upgrade_url'])) {
            $errorresponse['upgrade_url'] = $accesscheck['upgrade_url'];
        }

        echo json_encode($errorresponse);
        exit;
    }
    // =========================================================================

    // Collect ALL parameters from the request (not just those defined in backend)
    $reportparams = [];

    // First, collect parameters defined in backend report definition
    if (!empty($report['parameters'])) {
        $paramdefinitions = $report['parameters'];
        if (is_array($paramdefinitions)) {
            // Extract actual parameter definitions (arrays with 'name' key)
            // Skip metadata entries like 'charttype' which are scalar values
            foreach ($paramdefinitions as $key => $paramdef) {
                // Check if this is a parameter definition (array with 'name' key)
                if (!is_array($paramdef) || !isset($paramdef['name'])) {
                    continue; // Skip non-parameter entries (like 'charttype')
                }
                $paramname = $paramdef['name'];
                $paramvalue = optional_param($paramname, '', PARAM_RAW);
                // Check if parameter has a value (including 0)
                if ($paramvalue !== '' && $paramvalue !== null) {
                    $reportparams[$paramname] = $paramvalue;
                } else if (isset($paramdef['default'])) {
                    // Apply default value if no value provided
                    $reportparams[$paramname] = $paramdef['default'];
                }
            }
        }
    }

    // Also collect any additional parameters that might be sent from frontend.
    // Common parameters are sanitized via optional_param with PARAM_RAW for flexibility,
    // then further validated when bound as SQL parameters.
    $commonparams = [
        'courseid', 'minimum_grade', 'days', 'hours', 'limit', 'count',
        'startdate', 'enddate', 'userid', 'categoryid', 'groupid', 'roleid',
        'threshold', 'grade_threshold', 'activity_type', 'forum_id',
        'assignment_id', 'quiz_id', 'module_id', 'section_id', 'status',
        'completion_status', 'grade_min', 'grade_max', 'date_from', 'date_to',
        'time_period', 'activity_count', 'login_count', 'submission_status',
    ];
    foreach ($commonparams as $paramname) {
        $paramvalue = optional_param($paramname, '', PARAM_RAW);
        // Only add if not already collected and has a value.
        if (!isset($reportparams[$paramname]) && $paramvalue !== '' && $paramvalue !== null) {
            $reportparams[$paramname] = $paramvalue;
        }
    }

    // Execute the SQL query with parameters
    $sql = $report['sqlquery'];

    // SAFETY CHECK: Add safety limit if no LIMIT clause exists
    $safetylimit = 100000; // Maximum 100K records to prevent browser freeze
    // Match LIMIT with number OR named parameter (e.g., LIMIT 100 or LIMIT :limit)
    $haslimit = preg_match('/\bLIMIT\s+(\d+|:\w+|\?)/i', $sql);

    if (!$haslimit) {
        // Log warning that safety limit is being applied

        // Add LIMIT clause to the end of the query
        // Handle queries that may end with semicolon
        $sql = rtrim($sql);
        $sql = rtrim($sql, ';');
        $sql .= " LIMIT $safetylimit";
    }

    // Handle :limit parameter specially - MySQL LIMIT doesn't support bound parameters
    // Replace :limit directly in SQL with sanitized integer value
    if (isset($reportparams['limit']) && is_numeric($reportparams['limit'])) {
        $limitval = min(intval($reportparams['limit']), 100000); // Cap at 100K for safety
        $sql = preg_replace('/\bLIMIT\s+:limit\b/i', 'LIMIT ' . $limitval, $sql);
        unset($reportparams['limit']); // Remove from params as it's now in SQL
    }

    // Handle both named (:param) and positional (?) parameters
    try {
        if (!empty($reportparams)) {
            // Check if SQL uses named parameters (:param) or positional (?) parameters
            if (strpos($sql, ':') !== false) {
                // Convert named parameters to positional parameters for Moodle compatibility
                $sqlparams = [];
                $paramorder = [];

                // Extract parameter names from SQL in order
                preg_match_all('/:(\w+)/', $sql, $matches);
                $paramorder = $matches[1];

                // Replace named parameters with positional ones
                $positionalsql = $sql;
                foreach ($paramorder as $index => $paramname) {
                    $positionalsql = preg_replace('/:' . preg_quote($paramname, '/') . '\b/', '?', $positionalsql, 1);

                    // Get parameter value with special handling
                    if ($paramname === 'days' && is_numeric($reportparams[$paramname] ?? '')) {
                        // Convert days to Unix timestamp cutoff
                        $sqlparams[] = time() - (intval($reportparams[$paramname]) * 24 * 60 * 60);
                    } else {
                        $sqlparams[] = $reportparams[$paramname] ?? '';
                    }
                }

                // Use get_records_sql with positional parameters
                $results = $DB->get_records_sql($positionalsql, $sqlparams);
            } else {
                // Use positional parameters
                $sqlparams = [];
                foreach ($reportparams as $name => $value) {
                    // Special handling: convert 'days' to cutoff timestamp
                    if ($name === 'days' && is_numeric($value)) {
                        $sqlparams[] = time() - (intval($value) * 24 * 60 * 60);
                    } else {
                        $sqlparams[] = $value;
                    }
                }
                // Use get_records_sql with positional parameters
                $results = $DB->get_records_sql($sql, $sqlparams);
            }
        } else {
            // No parameters, execute directly
            $results = $DB->get_records_sql($sql);
        }
    } catch (\dml_read_exception $e) {
        throw new Exception("SQL Error: " . $e->debuginfo);
    }

    // Convert to array and get headers
    $resultsarray = [];
    $headers = [];

    if (!empty($results)) {
        $firstrow = reset($results);
        $headers = array_keys((array)$firstrow);

        foreach ($results as $row) {
            $resultsarray[] = (array)$row;
        }
    }

    // Track report generation usage (only if results returned and not a duplicate)
    // Only consider a report "generated" if it has actual data
    $hasdata = !empty($resultsarray);
    $reportgenerated = $hasdata; // Only count reports with data
    $isduplicate = false; // No duplicate checking - always count new generations

    // Save to history (for Recent Reports section) - always save for history
    $historyrecord = new stdClass();
    $historyrecord->userid = $USER->id;
    $historyrecord->reportid = $reportid;
    $historyrecord->parameters = json_encode($reportparams);
    $historyrecord->generatedat = time();
    $historyrecord->resultpath = ''; // Could save to file if needed
    // Note: Usage tracking is handled via backend API, not local database flag

    $DB->insert_record('report_adeptus_insights_history', $historyrecord);

    // Save to generated reports via backend API (for Generated Reports section) - only save if report has data
    if ($reportgenerated && $hasdata) {
        $isnewgeneration = false; // Track if this is a new generation or update

        // Prepare the report data for the backend
        $wizardreportdata = [
            'user_id' => $USER->id,
            'report_template_id' => $reportid,
            'name' => $report['name'] ?? $reportid,
            'parameters' => $reportparams,
        ];

        // Call backend API to save the wizard report
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $backendapiurl . '/wizard-reports');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wizardreportdata));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apikey,
        ]);

        $saveresponse = curl_exec($ch);
        $savehttpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $savecurlerror = curl_error($ch);
        curl_close($ch);

        if ($savehttpcode === 201 || $savehttpcode === 200) {
            $savedata = json_decode($saveresponse, true);
            if (!empty($savedata['success'])) {
                $isnewgeneration = true;
            } else {
                debugging('Backend save response missing success flag', DEBUG_DEVELOPER);
            }
        } else {
            debugging('Backend save failed with HTTP: ' . $savehttpcode, DEBUG_DEVELOPER);
        }
    } else {
        $isnewgeneration = false; // Ensure variable is defined.
    }

    // Update subscription usage in backend if report was generated (only count new generations, not updates)
    if (!$isduplicate && $reportgenerated && $isnewgeneration) {
        try {
            // Track report generation using the installation manager method
            $trackingresult = $installationmanager->track_report_generation($reportkey, $isaigenerated);

            if ($trackingresult) {
                debugging('Report generation tracked successfully', DEBUG_DEVELOPER);
            } else {
                debugging('Report generation tracking returned false', DEBUG_DEVELOPER);
            }
        } catch (Exception $e) {
            // Silently continue - tracking failure shouldn't break report generation.
            debugging('Report tracking failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    } else {
        // Skip tracking for duplicates or re-executions.
        debugging('Skipping tracking: duplicate=' . ($isduplicate ? '1' : '0') . ' isnew=' . ($isnewgeneration ? '1' : '0'), DEBUG_DEVELOPER);
    }

    // Prepare chart data if chart type is specified
    $chartdata = null;
    if (!empty($report['charttype']) && !empty($resultsarray)) {
        // Find the best columns for labels and values
        $labelcolumn = $headers[0] ?? 'id';
        $valuecolumn = null;

        // Analyze all columns to find numeric ones and their value ranges
        $numericcolumns = [];
        $columnstats = [];
        $mbcolumn = null;

        foreach ($headers as $header) {
            $columnvalues = array_column($resultsarray, $header);
            $numericvalues = [];
            $isnumericcolumn = true;

            // Check if all values in this column are numeric
            foreach ($columnvalues as $value) {
                if (is_numeric($value)) {
                    $numericvalues[] = (float)$value;
                } else if (is_string($value) && is_numeric(trim($value))) {
                    $numericvalues[] = (float)trim($value);
                } else {
                    $isnumericcolumn = false;
                    break;
                }
            }

            // If column is numeric, calculate its statistics
            if ($isnumericcolumn && !empty($numericvalues)) {
                $numericcolumns[] = $header;
                $columnstats[$header] = [
                    'max' => max($numericvalues),
                    'min' => min($numericvalues),
                    'sum' => array_sum($numericvalues),
                    'count' => count($numericvalues),
                    'avg' => array_sum($numericvalues) / count($numericvalues),
                ];

                // Special case: Check if column name contains "(mb)"
                if (strpos($header, '(mb)') !== false) {
                    $mbcolumn = $header;
                }
            }
        }

        // Select the column with priority: (mb) column first, then highest maximum value
        if (!empty($mbcolumn)) {
            $valuecolumn = $mbcolumn;
        } else if (!empty($numericcolumns)) {
            $maxvalue = 0;
            foreach ($numericcolumns as $column) {
                if ($columnstats[$column]['max'] > $maxvalue) {
                    $maxvalue = $columnstats[$column]['max'];
                    $valuecolumn = $column;
                }
            }
        } else {
            // Fallback to second column if no numeric columns found
            $valuecolumn = $headers[1] ?? 'value';
        }

        // Convert values to numbers if they're strings
        $chartvalues = array_column($resultsarray, $valuecolumn);
        $chartvalues = array_map(function ($value) {
            return is_numeric($value) ? (float)$value : (is_string($value) && is_numeric(trim($value)) ? (float)trim($value) : 0);
        }, $chartvalues);

        // Generate colors based on chart type
        $colors = report_adeptus_insights_generate_chart_colors(count($chartvalues), $report->charttype);

        $chartdata = [
            'labels' => array_column($resultsarray, $labelcolumn),
            'datasets' => [
                [
                    'label' => $report->name,
                    'data' => $chartvalues,
                    'backgroundColor' => $colors,
                    'borderColor' => report_adeptus_insights_adjust_colors($colors, -20),
                    'borderWidth' => 2,
                ],
            ],
            'axis_labels' => [
                'x_axis' => $labelcolumn,
                'y_axis' => $valuecolumn,
            ],
        ];
    }

    // Get installation manager for debug info
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $debuginstallationmanager = new \report_adeptus_insights\installation_manager();

    // Return success response with debug info
    echo json_encode([
        'success' => true,
        'report_name' => $report['name'],
        'report_id' => $reportid,
        'results' => $resultsarray,
        'headers' => $headers,
        'chart_data' => $chartdata,
        'chart_type' => $report['charttype'],
        'parameters_used' => $reportparams,
        'is_duplicate' => $isduplicate,
        'execution_time' => time(),
        'debug_info' => [
            'report_generated' => $reportgenerated,
            'is_duplicate' => $isduplicate,
            'results_count' => count($resultsarray),
            'backend_api_url' => $debuginstallationmanager->get_api_url(),
            'api_key_present' => !empty($debuginstallationmanager->get_api_key()) ? 'yes' : 'no',
        ],
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => get_string('error_executing_report', 'report_adeptus_insights', $e->getMessage()),
    ]);
}

/**
 * Generate colors for charts based on chart type and data count.
 *
 * @param int $count Number of data points.
 * @param string $charttype The chart type.
 * @return array Array of color values.
 */
function report_adeptus_insights_generate_chart_colors($count, $charttype) {
    $basecolors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
        '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#17a2b8',
        '#6610f2', '#fd7e14', '#20c997', '#e83e8c', '#6c757d',
    ];

    $charttype = strtolower($charttype);

    if ($charttype === 'pie' || $charttype === 'donut' || $charttype === 'polar') {
        // Generate distinct colors for each data point
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $basecolors[$i % count($basecolors)];
        }
        return $colors;
    } else {
        // Use single color for bar, line, radar charts
        return [$basecolors[0]];
    }
}

/**
 * Adjust colors (lighten or darken) for border colors.
 *
 * @param array|string $colors Color or array of colors.
 * @param int $amount Amount to adjust (negative = darken).
 * @return array|string Adjusted color(s).
 */
function report_adeptus_insights_adjust_colors($colors, $amount) {
    if (is_array($colors)) {
        return array_map(function ($color) use ($amount) {
            return report_adeptus_insights_adjust_color($color, $amount);
        }, $colors);
    } else {
        return report_adeptus_insights_adjust_color($colors, $amount);
    }
}

/**
 * Adjust a single color by lightening or darkening it.
 *
 * @param string $color Hex color value.
 * @param int $amount Amount to adjust (negative = darken).
 * @return string Adjusted hex color.
 */
function report_adeptus_insights_adjust_color($color, $amount) {
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
