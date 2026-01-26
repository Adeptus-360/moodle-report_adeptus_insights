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
 * Execute AI-generated report SQL locally.
 *
 * This AJAX endpoint handles SQL execution for AI-generated reports,
 * executing queries directly on the local Moodle database instead of
 * the backend server. This supports the SaaS model where customer data
 * stays on their own server.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('READ_ONLY_SESSION', true); // Allow parallel requests

require_once(__DIR__ . '/../../../config.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Release session lock early to allow parallel AJAX requests
\core\session\manager::write_close();

// Set content type
header('Content-Type: application/json');

// Get parameters - accept both GET and POST, and JSON body
$requestmethod = $_SERVER['REQUEST_METHOD'];
$inputdata = [];

if ($requestmethod === 'POST') {
    // Try to get JSON body first
    $jsoninput = file_get_contents('php://input');
    if (!empty($jsoninput)) {
        $inputdata = json_decode($jsoninput, true) ?: [];
    }
    // Merge with POST data (POST takes precedence for sesskey)
    $inputdata = array_merge($inputdata, $_POST);
}

// Get SQL - required
$sql = $inputdata['sql'] ?? optional_param('sql', '', PARAM_RAW);
$sesskey = $inputdata['sesskey'] ?? required_param('sesskey', PARAM_ALPHANUM);
$params = $inputdata['params'] ?? optional_param('params', '{}', PARAM_RAW);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'invalid_sesskey',
        'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights'),
    ]);
    exit;
}

// Validate SQL is provided
if (empty($sql)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'missing_sql',
        'message' => get_string('error_sql_required', 'report_adeptus_insights'),
    ]);
    exit;
}

// Parse parameters if JSON string
$reportparams = [];
if (!empty($params)) {
    if (is_string($params)) {
        $reportparams = json_decode($params, true) ?: [];
    } else if (is_array($params)) {
        $reportparams = $params;
    }
}

// Security: Basic SQL validation
// Only allow SELECT statements (read-only)
$sqltrimmed = trim($sql);
$sqlupper = strtoupper(substr($sqltrimmed, 0, 6));
if ($sqlupper !== 'SELECT') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'invalid_sql',
        'message' => get_string('error_sql_only_select', 'report_adeptus_insights'),
    ]);
    exit;
}

// Block dangerous SQL patterns
$dangerouspatterns = [
    '/\bDROP\b/i',
    '/\bDELETE\b/i',
    '/\bTRUNCATE\b/i',
    '/\bUPDATE\b/i',
    '/\bINSERT\b/i',
    '/\bALTER\b/i',
    '/\bCREATE\b/i',
    '/\bGRANT\b/i',
    '/\bREVOKE\b/i',
    '/\bEXEC\b/i',
    '/\bEXECUTE\b/i',
    '/INTO\s+OUTFILE/i',
    '/INTO\s+DUMPFILE/i',
    '/LOAD_FILE/i',
];

foreach ($dangerouspatterns as $pattern) {
    if (preg_match($pattern, $sql)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'dangerous_sql',
            'message' => get_string('error_sql_dangerous', 'report_adeptus_insights'),
        ]);
        exit;
    }
}

try {
    // Handle table prefix replacement
    // Backend may use 'mdl_' or 'prefix_' placeholders
    global $CFG;
    $prefix = $CFG->prefix;

    // Replace common prefix patterns
    $sql = str_replace('mdl_', $prefix, $sql);
    $sql = str_replace('prefix_', $prefix, $sql);

    // Execute the query
    $results = [];

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

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $resultsarray,
        'headers' => $headers,
        'row_count' => count($resultsarray),
        'executed_locally' => true,
    ]);
} catch (\dml_read_exception $e) {
    // SQL syntax or execution error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'sql_error',
        'message' => get_string('error_sql_execution_failed', 'report_adeptus_insights'),
        'details' => $e->debuginfo ?? $e->getMessage(),
    ]);
} catch (\Exception $e) {
    // General error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'execution_error',
        'message' => get_string('error_report_execution_failed', 'report_adeptus_insights'),
        'details' => $e->getMessage(),
    ]);
}
