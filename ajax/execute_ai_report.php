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
$requestMethod = $_SERVER['REQUEST_METHOD'];
$inputData = [];

if ($requestMethod === 'POST') {
    // Try to get JSON body first
    $jsonInput = file_get_contents('php://input');
    if (!empty($jsonInput)) {
        $inputData = json_decode($jsonInput, true) ?: [];
    }
    // Merge with POST data (POST takes precedence for sesskey)
    $inputData = array_merge($inputData, $_POST);
}

// Get SQL - required
$sql = $inputData['sql'] ?? optional_param('sql', '', PARAM_RAW);
$sesskey = $inputData['sesskey'] ?? required_param('sesskey', PARAM_ALPHANUM);
$params = $inputData['params'] ?? optional_param('params', '{}', PARAM_RAW);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'invalid_sesskey',
        'message' => 'Invalid session key'
    ]);
    exit;
}

// Validate SQL is provided
if (empty($sql)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'missing_sql',
        'message' => 'SQL query is required'
    ]);
    exit;
}

// Parse parameters if JSON string
$report_params = [];
if (!empty($params)) {
    if (is_string($params)) {
        $report_params = json_decode($params, true) ?: [];
    } else if (is_array($params)) {
        $report_params = $params;
    }
}

// Security: Basic SQL validation
// Only allow SELECT statements (read-only)
$sql_trimmed = trim($sql);
$sql_upper = strtoupper(substr($sql_trimmed, 0, 6));
if ($sql_upper !== 'SELECT') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'invalid_sql',
        'message' => 'Only SELECT queries are allowed'
    ]);
    exit;
}

// Block dangerous SQL patterns
$dangerous_patterns = [
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

foreach ($dangerous_patterns as $pattern) {
    if (preg_match($pattern, $sql)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'dangerous_sql',
            'message' => 'Query contains disallowed SQL commands'
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

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $results_array,
        'headers' => $headers,
        'row_count' => count($results_array),
        'executed_locally' => true
    ]);

} catch (\dml_read_exception $e) {
    // SQL syntax or execution error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'sql_error',
        'message' => 'SQL execution failed',
        'details' => $e->debuginfo ?? $e->getMessage()
    ]);
} catch (\Exception $e) {
    // General error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'execution_error',
        'message' => 'Failed to execute report',
        'details' => $e->getMessage()
    ]);
}
