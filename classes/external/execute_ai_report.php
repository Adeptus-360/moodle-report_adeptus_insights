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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * External service to execute AI-generated report SQL locally.
 *
 * This handles SQL execution for AI-generated reports, executing queries
 * directly on the local Moodle database. This supports the SaaS model
 * where customer data stays on their own server.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execute_ai_report extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sql' => new external_value(PARAM_RAW, 'SQL query to execute'),
            'params' => new external_value(PARAM_RAW, 'JSON-encoded parameters', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Execute AI-generated report SQL.
     *
     * @param string $sql SQL query
     * @param string $params JSON-encoded parameters
     * @return array Result
     */
    public static function execute(string $sql, string $params = '{}'): array {
        global $CFG, $DB;

        // Validate parameters.
        $validatedparams = self::validate_parameters(self::execute_parameters(), [
            'sql' => $sql,
            'params' => $params,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        $sql = $validatedparams['sql'];

        // Validate SQL is provided.
        if (empty($sql)) {
            return [
                'success' => false,
                'error' => 'missing_sql',
                'message' => get_string('error_sql_required', 'report_adeptus_insights'),
                'data' => '[]',
                'headers' => '[]',
                'row_count' => 0,
            ];
        }

        // Parse parameters if JSON string.
        $reportparams = [];
        if (!empty($validatedparams['params'])) {
            $reportparams = json_decode($validatedparams['params'], true) ?: [];
        }

        // Security: Basic SQL validation - only allow SELECT statements (read-only).
        $sqltrimmed = trim($sql);
        $sqlupper = strtoupper(substr($sqltrimmed, 0, 6));
        if ($sqlupper !== 'SELECT') {
            return [
                'success' => false,
                'error' => 'invalid_sql',
                'message' => get_string('error_sql_only_select', 'report_adeptus_insights'),
                'data' => '[]',
                'headers' => '[]',
                'row_count' => 0,
            ];
        }

        // Block dangerous SQL patterns.
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
                return [
                    'success' => false,
                    'error' => 'dangerous_sql',
                    'message' => get_string('error_sql_dangerous', 'report_adeptus_insights'),
                    'data' => '[]',
                    'headers' => '[]',
                    'row_count' => 0,
                ];
            }
        }

        try {
            // Handle table prefix replacement.
            $prefix = $CFG->prefix;

            // Replace common prefix patterns.
            $sql = str_replace('mdl_', $prefix, $sql);
            $sql = str_replace('prefix_', $prefix, $sql);

            // Execute the query.
            $results = [];

            if (!empty($reportparams)) {
                // Check if SQL uses named parameters (:param) or positional (?) parameters.
                if (strpos($sql, ':') !== false) {
                    // Convert named parameters to positional parameters for Moodle compatibility.
                    $sqlparams = [];

                    // Extract parameter names from SQL in order.
                    preg_match_all('/:(\w+)/', $sql, $matches);
                    $paramorder = $matches[1];

                    // Replace named parameters with positional ones.
                    $positionalsql = $sql;
                    foreach ($paramorder as $paramname) {
                        $positionalsql = preg_replace('/:' . preg_quote($paramname, '/') . '\b/', '?', $positionalsql, 1);

                        // Get parameter value with special handling.
                        if ($paramname === 'days' && is_numeric($reportparams[$paramname] ?? '')) {
                            // Convert days to Unix timestamp cutoff.
                            $sqlparams[] = time() - (intval($reportparams[$paramname]) * 24 * 60 * 60);
                        } else {
                            $sqlparams[] = $reportparams[$paramname] ?? '';
                        }
                    }

                    $results = $DB->get_records_sql($positionalsql, $sqlparams);
                } else {
                    // Use positional parameters.
                    $sqlparams = [];
                    foreach ($reportparams as $name => $value) {
                        // Special handling: convert 'days' to cutoff timestamp.
                        if ($name === 'days' && is_numeric($value)) {
                            $sqlparams[] = time() - (intval($value) * 24 * 60 * 60);
                        } else {
                            $sqlparams[] = $value;
                        }
                    }
                    $results = $DB->get_records_sql($sql, $sqlparams);
                }
            } else {
                // No parameters, execute directly.
                $results = $DB->get_records_sql($sql);
            }

            // Convert to array and get headers.
            $resultsarray = [];
            $headers = [];

            if (!empty($results)) {
                $firstrow = reset($results);
                $headers = array_keys((array)$firstrow);

                foreach ($results as $row) {
                    $resultsarray[] = (array)$row;
                }
            }

            return [
                'success' => true,
                'error' => '',
                'message' => '',
                'data' => json_encode($resultsarray),
                'headers' => json_encode($headers),
                'row_count' => count($resultsarray),
            ];
        } catch (\dml_read_exception $e) {
            return [
                'success' => false,
                'error' => 'sql_error',
                'message' => get_string('error_sql_execution_failed', 'report_adeptus_insights'),
                'data' => '[]',
                'headers' => '[]',
                'row_count' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'execution_error',
                'message' => get_string('error_report_execution_failed', 'report_adeptus_insights'),
                'data' => '[]',
                'headers' => '[]',
                'row_count' => 0,
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'error' => new external_value(PARAM_TEXT, 'Error code if any'),
            'message' => new external_value(PARAM_TEXT, 'Error message if any'),
            'data' => new external_value(PARAM_RAW, 'JSON-encoded results array'),
            'headers' => new external_value(PARAM_RAW, 'JSON-encoded headers array'),
            'row_count' => new external_value(PARAM_INT, 'Number of rows returned'),
        ]);
    }
}
