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

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * External API for generating reports with SQL execution.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_report extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'reportid' => new external_value(PARAM_TEXT, 'Report name/identifier'),
            'parameters' => new external_value(PARAM_RAW, 'JSON-encoded report parameters', VALUE_DEFAULT, '{}'),
            'reexecution' => new external_value(PARAM_BOOL, 'Whether this is a re-execution of saved report', VALUE_DEFAULT, false),
            'cohortids' => new external_value(PARAM_RAW, 'JSON-encoded array of cohort IDs to filter by', VALUE_DEFAULT, '[]'),
            'groupids' => new external_value(PARAM_RAW, 'JSON-encoded array of group IDs to filter by', VALUE_DEFAULT, '[]'),
        ]);
    }

    /**
     * Generate a report by executing its SQL query with parameters.
     *
     * @param string $reportid Report name/identifier.
     * @param string $parametersjson JSON-encoded parameters.
     * @param bool $reexecution Whether this is a re-execution.
     * @return array Report results with data and chart info.
     */
    public static function execute($reportid, $parametersjson = '{}', $reexecution = false, $cohortids = '[]', $groupids = '[]') {
        global $CFG, $DB, $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'reportid' => $reportid,
            'parameters' => $parametersjson,
            'reexecution' => $reexecution,
            'cohortids' => $cohortids ?? '[]',
            'groupids' => $groupids ?? '[]',
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check.
        require_capability('report/adeptus_insights:view', $context);

        // Parse parameters JSON.
        $reportparams = json_decode($params['parameters'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $reportparams = [];
        }

        // Check if backend is enabled.
        $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
        if (!$backendenabled) {
            return self::error_response('error_backend_disabled');
        }

        // Get API configuration.
        $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
        $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

        // Get API key for authentication.
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $apikey = $installationmanager->get_api_key();

        // Fetch the report from backend.
        $report = self::fetch_report_from_backend($backendapiurl, $apikey, $apitimeout, $params['reportid']);
        if (!$report) {
            return self::error_response('error_report_not_found');
        }

        // Validate report compatibility.
        $validation = \report_adeptus_insights\report_validator::validate_report($report);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'report_incompatible',
                'message' => get_string('error_report_incompatible_modules', 'report_adeptus_insights'),
                'results' => [],
                'headers' => [],
                'chart_data' => null,
                'chart_type' => '',
                'report_name' => $report['name'] ?? '',
                'parameters_used' => json_encode($reportparams),
                'is_duplicate' => false,
            ];
        }

        // Check report limits (skip for re-executions).
        if (!$params['reexecution']) {
            $limitscheck = self::check_report_limits($backendapiurl, $apikey);
            if (!$limitscheck['eligible']) {
                return [
                    'success' => false,
                    'error' => 'limit_reached',
                    'message' => $limitscheck['message'] ?? get_string('error_limit_reached', 'report_adeptus_insights'),
                    'results' => [],
                    'headers' => [],
                    'chart_data' => null,
                    'chart_type' => '',
                    'report_name' => $report['name'] ?? '',
                    'parameters_used' => json_encode($reportparams),
                    'is_duplicate' => false,
                    'reports_used' => $limitscheck['reports_used'] ?? 0,
                    'reports_limit' => $limitscheck['reports_limit'] ?? 0,
                ];
            }
        }

        // Check subscription tier access.
        $reportkey = $report['report_key'] ?? $report['name'] ?? $params['reportid'];
        $accesscheck = $installationmanager->check_report_access($reportkey);
        if (!$accesscheck['allowed']) {
            return [
                'success' => false,
                'error' => 'access_denied',
                'message' => $accesscheck['message'],
                'results' => [],
                'headers' => [],
                'chart_data' => null,
                'chart_type' => '',
                'report_name' => $report['name'] ?? '',
                'parameters_used' => json_encode($reportparams),
                'is_duplicate' => false,
            ];
        }

        // Merge report-defined parameters with provided ones.
        $reportparams = self::merge_parameters($report, $reportparams);

        // Apply cohort and group filters to the SQL query.
        $reportquery = $report['sqlquery'];
        $parsedcohortids = json_decode($params['cohortids'], true) ?: [];
        $parsedgroupids = json_decode($params['groupids'], true) ?: [];

        if (!empty($parsedcohortids) || !empty($parsedgroupids)) {
            debugging('Applying filters: cohorts=' . json_encode($parsedcohortids) . ', groups=' . json_encode($parsedgroupids), DEBUG_DEVELOPER);
            $reportquery = self::apply_user_filters($reportquery, $parsedcohortids, $parsedgroupids);
            debugging('Filtered SQL: ' . substr($reportquery, 0, 500), DEBUG_DEVELOPER);
        }

        // Execute the SQL query.
        try {
            $queryresult = self::execute_query($reportquery, $reportparams, $DB);
            $results = $queryresult['results'];
            $headers = $queryresult['headers'];
        } catch (\Exception $e) {
            debugging('SQL execution failed: ' . $e->getMessage() . ' | SQL: ' . substr($reportquery, 0, 1000), DEBUG_DEVELOPER);
            return self::error_response('error_executing_report', $e->getMessage());
        }

        // Track report generation.
        $hasdata = !empty($results);
        if ($hasdata && !$params['reexecution']) {
            self::track_report_generation($report, $reportparams, $apikey, $backendapiurl, $installationmanager, $USER, $DB);
        }

        // Generate chart data if applicable.
        $chartdata = null;
        if (!empty($report['charttype']) && !empty($results)) {
            $chartdata = self::generate_chart_data($results, $headers, $report);
        }

        // Format results for return.
        $formattedresults = [];
        foreach ($results as $row) {
            $formattedrow = [];
            foreach ($row as $key => $value) {
                $formattedrow[] = [
                    'key' => $key,
                    'value' => (string) ($value ?? ''),
                ];
            }
            $formattedresults[] = ['cells' => $formattedrow];
        }

        $charttype = !empty($report['charttype']) ? $report['charttype'] : null;

        return [
            'success' => true,
            'error' => null,
            'message' => '',
            'results' => $formattedresults,
            'headers' => $headers,
            'chart_data' => $chartdata ? json_encode($chartdata) : null,
            'chart_type' => $charttype,
            'report_name' => $report['name'] ?? '',
            'parameters_used' => json_encode($reportparams),
            'is_duplicate' => false,
        ];
    }

    /**
     * Create an error response.
     *
     * @param string $stringkey Language string key.
     * @param string $extra Extra info for the message.
     * @return array Error response array.
     */
    private static function error_response($stringkey, $extra = '') {
        // Properly interpolate {$a} placeholder with the extra info.
        if ($extra) {
            $message = get_string($stringkey, 'report_adeptus_insights', $extra);
        } else {
            $message = get_string($stringkey, 'report_adeptus_insights');
        }
        return [
            'success' => false,
            'error' => $stringkey,
            'message' => $message,
            'results' => [],
            'headers' => [],
            'chart_data' => null,
            'chart_type' => '',
            'report_name' => '',
            'parameters_used' => '{}',
            'is_duplicate' => false,
        ];
    }

    /**
     * Fetch report definition from backend.
     *
     * @param string $backendapiurl Backend API URL.
     * @param string $apikey API key.
     * @param int $timeout Request timeout.
     * @param string $reportid Report ID to find.
     * @return array|null Report data or null if not found.
     */
    private static function fetch_report_from_backend($backendapiurl, $apikey, $timeout, $reportid) {
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('X-API-Key: ' . $apikey);
        $options = [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $response = $curl->get($backendapiurl . '/reports/definitions', [], $options);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if (!$response || $httpcode !== 200) {
            return null;
        }

        $backenddata = json_decode($response, true);
        if (!$backenddata || !$backenddata['success']) {
            return null;
        }

        foreach ($backenddata['data'] as $report) {
            if (trim($report['name']) === trim($reportid)) {
                return $report;
            }
        }

        return null;
    }

    /**
     * Check report generation limits with backend.
     *
     * @param string $backendapiurl Backend API URL.
     * @param string $apikey API key.
     * @return array Limits check result.
     */
    private static function check_report_limits($backendapiurl, $apikey) {
        $limitsendpoint = rtrim($backendapiurl, '/') . '/report-limits/check';
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('X-API-Key: ' . $apikey);
        $options = [
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $response = $curl->post($limitsendpoint, json_encode(new \stdClass()), $options);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if (!$response || $httpcode !== 200) {
            // Fail closed - deny if can't verify.
            return ['eligible' => false, 'message' => get_string('error_verify_eligibility', 'report_adeptus_insights')];
        }

        $limitsdata = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($limitsdata['eligible'])) {
            return ['eligible' => false, 'message' => get_string('error_verify_eligibility', 'report_adeptus_insights')];
        }

        return $limitsdata;
    }

    /**
     * Merge report-defined parameters with user-provided ones.
     *
     * @param array $report Report definition.
     * @param array $providedparams User-provided parameters.
     * @return array Merged parameters.
     */
    private static function merge_parameters($report, $providedparams) {
        $mergedparams = $providedparams;

        if (!empty($report['parameters']) && is_array($report['parameters'])) {
            foreach ($report['parameters'] as $paramdef) {
                if (!is_array($paramdef) || !isset($paramdef['name'])) {
                    continue;
                }
                $paramname = $paramdef['name'];
                if (!isset($mergedparams[$paramname]) && isset($paramdef['default'])) {
                    $mergedparams[$paramname] = $paramdef['default'];
                }
            }
        }

        return $mergedparams;
    }

    /**
     * Public static wrapper for apply_user_filters, callable from other external services.
     *
     * @param string $sql Original SQL query.
     * @param array $cohortids Array of cohort IDs to filter by.
     * @param array $groupids Array of group IDs to filter by.
     * @return string Modified SQL query.
     */
    public static function apply_user_filters_static($sql, array $cohortids, array $groupids) {
        return self::apply_user_filters($sql, $cohortids, $groupids);
    }

    /**
     * Apply cohort and group user filters to the report SQL.
     *
     * Detects the user ID column in the query (userid, user_id, id from {user}) and
     * wraps the query with an additional WHERE clause filtering by cohort/group membership.
     *
     * @param string $sql Original SQL query.
     * @param array $cohortids Array of cohort IDs to filter by.
     * @param array $groupids Array of group IDs to filter by.
     * @return string Modified SQL query.
     */
    private static function apply_user_filters($sql, array $cohortids, array $groupids) {
        // Build a user ID subquery combining cohort and group membership.
        $usersubqueries = [];

        if (!empty($cohortids)) {
            $cohortidlist = implode(',', array_map('intval', $cohortids));
            $usersubqueries[] = "SELECT cm.userid FROM {cohort_members} cm WHERE cm.cohortid IN ($cohortidlist)";
        }

        if (!empty($groupids)) {
            $groupidlist = implode(',', array_map('intval', $groupids));
            $usersubqueries[] = "SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid IN ($groupidlist)";
        }

        if (empty($usersubqueries)) {
            return $sql;
        }

        // If both cohort and group filters, use UNION (users in EITHER cohort or group).
        // Changed from INTERSECT to UNION for more inclusive filtering.
        if (count($usersubqueries) > 1) {
            $filtersubquery = $usersubqueries[0] . " UNION " . $usersubqueries[1];
        } else {
            $filtersubquery = $usersubqueries[0];
        }

        // Detect the user column in the SELECT clause.
        $useridcol = self::detect_userid_column($sql);

        if ($useridcol) {
            // Wrap the original query and filter by user membership.
            $sql = rtrim(rtrim($sql), ';');
            $sql = "SELECT filtered_report.* FROM ($sql) filtered_report "
                . "WHERE filtered_report.$useridcol IN ($filtersubquery)";
            debugging('apply_user_filters: Detected userid col=' . $useridcol . ', wrapped SQL.', DEBUG_DEVELOPER);
        } else {
            // Fallback: try to JOIN against the user filter using common table references.
            // This handles cases where we can't detect the userid column in SELECT,
            // but the query does reference the {user} table.
            $sql = rtrim(rtrim($sql), ';');
            $fallbackcol = self::detect_userid_column_fallback($sql);
            if ($fallbackcol) {
                $sql = "SELECT filtered_report.* FROM ($sql) filtered_report "
                    . "WHERE filtered_report.$fallbackcol IN ($filtersubquery)";
                debugging('apply_user_filters: Used fallback col=' . $fallbackcol . ', wrapped SQL.', DEBUG_DEVELOPER);
            } else {
                // Last resort: try all common user ID column names.
                // Wrap with a subquery that checks multiple possible column names.
                debugging('apply_user_filters: Could not detect userid column. Trying brute-force approach.', DEBUG_DEVELOPER);
                $sql = self::apply_user_filters_bruteforce($sql, $filtersubquery);
            }
        }

        return $sql;
    }

    /**
     * Detect the user ID column name in a SQL query by analysing the SELECT clause.
     *
     * Focuses on the SELECT clause to find the output column name that represents
     * a user ID, which is what will be available in the wrapped outer query.
     *
     * @param string $sql The SQL query.
     * @return string|null The user ID column alias, or null if not detected.
     */
    private static function detect_userid_column($sql) {
        // Extract the SELECT clause (everything between SELECT and FROM).
        // Handle nested subqueries by finding the main FROM.
        $selectclause = self::extract_select_clause($sql);
        if (empty($selectclause)) {
            return null;
        }

        // Priority 1: Explicit alias "AS userid" or "AS user_id" in SELECT.
        if (preg_match('/\bAS\s+userid\b/i', $selectclause)) {
            return 'userid';
        }
        if (preg_match('/\bAS\s+user_id\b/i', $selectclause)) {
            return 'user_id';
        }
        if (preg_match('/\bAS\s+learnerid\b/i', $selectclause)) {
            return 'learnerid';
        }
        if (preg_match('/\bAS\s+studentid\b/i', $selectclause)) {
            return 'studentid';
        }

        // Priority 2: Bare column references like "u.userid", "ue.userid", etc.
        // When selected as "table.userid", the output column name is "userid".
        if (preg_match('/\b\w+\.userid\b/i', $selectclause)) {
            return 'userid';
        }
        if (preg_match('/\b\w+\.user_id\b/i', $selectclause)) {
            return 'user_id';
        }

        // Priority 3: Just "userid" as a column in SELECT.
        if (preg_match('/\buserid\b/i', $selectclause)) {
            return 'userid';
        }

        // Priority 4: "u.id" pattern (very common: SELECT u.id, u.firstname, ...).
        // The output column will be "id".
        if (preg_match('/\bu\.id\b/i', $selectclause)) {
            return 'id';
        }

        // Priority 5: Any table.id where the table is likely a user table alias.
        // Common aliases: u, usr, users, student, learner.
        if (preg_match('/\b(u|usr|users?|student|learner)\.id\b/i', $selectclause)) {
            return 'id';
        }

        return null;
    }

    /**
     * Extract the SELECT clause from a SQL query (between SELECT and the main FROM).
     *
     * @param string $sql The SQL query.
     * @return string The SELECT clause content, or empty string.
     */
    private static function extract_select_clause($sql) {
        $sql = trim($sql);
        // Remove leading SELECT keyword.
        if (!preg_match('/^\s*SELECT\s+(DISTINCT\s+)?/is', $sql, $m)) {
            return '';
        }

        $start = strlen($m[0]);
        $depth = 0;
        $len = strlen($sql);

        // Walk through to find the main FROM (not inside subqueries).
        for ($i = $start; $i < $len; $i++) {
            $char = $sql[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($depth === 0) {
                // Check for FROM keyword at this level.
                if (preg_match('/\bFROM\b/i', substr($sql, $i, 5))) {
                    return substr($sql, $start, $i - $start);
                }
            }
        }

        return '';
    }

    /**
     * Fallback user ID column detection: scan the full SQL for clues.
     *
     * @param string $sql The SQL query.
     * @return string|null Column name or null.
     */
    private static function detect_userid_column_fallback($sql) {
        // Check if the query references {user} table — if so, the join likely uses an id column.
        if (preg_match('/\{user\}\s+(\w+)/i', $sql, $m)) {
            $alias = $m[1];
            // Check if this alias's id appears in SELECT.
            $selectclause = self::extract_select_clause($sql);
            if (!empty($selectclause) && preg_match('/\b' . preg_quote($alias, '/') . '\.id\b/i', $selectclause)) {
                return 'id';
            }
        }

        // Check for userid anywhere in SELECT.
        $selectclause = self::extract_select_clause($sql);
        if (!empty($selectclause)) {
            // Look for any column ending in 'userid' or 'user_id'.
            if (preg_match('/\b(\w*userid)\b/i', $selectclause, $m)) {
                return strtolower($m[1]);
            }
            if (preg_match('/\b(\w*user_id)\b/i', $selectclause, $m)) {
                return strtolower($m[1]);
            }
        }

        // If query references {user} at all, try 'id' as the column.
        if (preg_match('/\{user\}/i', $sql)) {
            return 'id';
        }

        return null;
    }

    /**
     * Brute-force filter application: try wrapping with common user ID column names.
     * Tests each candidate and returns the first valid wrapping.
     *
     * @param string $sql The original SQL (already rtrimmed).
     * @param string $filtersubquery The user filter subquery.
     * @return string Modified SQL.
     */
    private static function apply_user_filters_bruteforce($sql, $filtersubquery) {
        global $DB;

        // Try to execute a limited version to discover column names.
        $testsql = rtrim(rtrim($sql), ';');
        try {
            $testresult = $DB->get_records_sql($testsql . " LIMIT 1", []);
            if (!empty($testresult)) {
                $firstrow = reset($testresult);
                $columns = array_keys((array) $firstrow);

                // Look for user ID columns in the result set.
                $candidates = ['userid', 'user_id', 'id', 'learnerid', 'studentid', 'uid'];
                foreach ($candidates as $candidate) {
                    if (in_array($candidate, $columns)) {
                        debugging('apply_user_filters_bruteforce: Found column ' . $candidate . ' in result set.', DEBUG_DEVELOPER);
                        return "SELECT filtered_report.* FROM ($sql) filtered_report "
                            . "WHERE filtered_report.$candidate IN ($filtersubquery)";
                    }
                }

                // Try any column containing 'user' and 'id'.
                foreach ($columns as $col) {
                    $lower = strtolower($col);
                    if (strpos($lower, 'user') !== false && strpos($lower, 'id') !== false) {
                        debugging('apply_user_filters_bruteforce: Found user-id-like column ' . $col, DEBUG_DEVELOPER);
                        return "SELECT filtered_report.* FROM ($sql) filtered_report "
                            . "WHERE filtered_report.$col IN ($filtersubquery)";
                    }
                }
            }
        } catch (\Exception $e) {
            debugging('apply_user_filters_bruteforce: Test query failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // If all detection fails, return original SQL unchanged and log it.
        debugging('apply_user_filters: WARNING - Could not detect any user ID column. '
            . 'Filter NOT applied. SQL starts with: ' . substr($sql, 0, 200), DEBUG_DEVELOPER);
        return $sql;
    }

    /**
     * Execute the SQL query with parameters.
     *
     * @param string $sql SQL query.
     * @param array $params Query parameters.
     * @param object $DB Database object.
     * @return array Results and headers.
     */
    private static function execute_query($sql, $params, $DB) {
        // Safety limit.
        $safetylimit = 100000;
        $haslimit = preg_match('/\bLIMIT\s+(\d+|:\w+|\?)/i', $sql);

        if (!$haslimit) {
            $sql = rtrim(rtrim($sql), ';');
            $sql .= " LIMIT $safetylimit";
        }

        // Handle :limit parameter.
        if (isset($params['limit']) && is_numeric($params['limit'])) {
            $limitval = min(intval($params['limit']), 100000);
            $sql = preg_replace('/\bLIMIT\s+:limit\b/i', 'LIMIT ' . $limitval, $sql);
            unset($params['limit']);
        }

        // Execute query.
        $results = [];
        $headers = [];

        if (!empty($params) && strpos($sql, ':') !== false) {
            // Named parameters - convert to positional.
            $sqlparams = [];
            preg_match_all('/:(\w+)/', $sql, $matches);
            $paramorder = $matches[1];

            $positionalsql = $sql;
            foreach ($paramorder as $paramname) {
                $positionalsql = preg_replace('/:' . preg_quote($paramname, '/') . '\b/', '?', $positionalsql, 1);

                if ($paramname === 'days' && is_numeric($params[$paramname] ?? '')) {
                    $sqlparams[] = time() - (intval($params[$paramname]) * 24 * 60 * 60);
                } else {
                    $sqlparams[] = $params[$paramname] ?? '';
                }
            }

            $queryresults = $DB->get_records_sql($positionalsql, $sqlparams);
        } else if (!empty($params)) {
            $sqlparams = [];
            foreach ($params as $name => $value) {
                if ($name === 'days' && is_numeric($value)) {
                    $sqlparams[] = time() - (intval($value) * 24 * 60 * 60);
                } else {
                    $sqlparams[] = $value;
                }
            }
            $queryresults = $DB->get_records_sql($sql, $sqlparams);
        } else {
            $queryresults = $DB->get_records_sql($sql);
        }

        if (!empty($queryresults)) {
            $firstrow = reset($queryresults);
            $headers = array_keys((array) $firstrow);

            foreach ($queryresults as $row) {
                $results[] = (array) $row;
            }
        }

        return ['results' => $results, 'headers' => $headers];
    }

    /**
     * Track report generation in backend and local DB.
     *
     * @param array $report Report definition.
     * @param array $params Report parameters.
     * @param string $apikey API key.
     * @param string $backendapiurl Backend URL.
     * @param object $installationmanager Installation manager.
     * @param object $USER Current user.
     * @param object $DB Database object.
     */
    private static function track_report_generation($report, $params, $apikey, $backendapiurl, $installationmanager, $USER, $DB) {
        // Save to local history.
        $historyrecord = new \stdClass();
        $historyrecord->userid = $USER->id;
        $historyrecord->reportid = $report['name'];
        $historyrecord->parameters = json_encode($params);
        $historyrecord->generatedat = time();
        $historyrecord->resultpath = '';

        $DB->insert_record('report_adeptus_insights_history', $historyrecord);

        // Save to backend.
        $wizardreportdata = [
            'user_id' => $USER->id,
            'report_template_id' => $report['name'],
            'name' => $report['name'],
            'parameters' => $params,
        ];

        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('Authorization: Bearer ' . $apikey);
        $options = [
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $curl->post($backendapiurl . '/wizard-reports', json_encode($wizardreportdata), $options);

        // Track usage.
        $reportkey = $report['report_key'] ?? $report['name'];
        $isaigenerated = $report['is_ai_generated'] ?? false;
        $installationmanager->track_report_generation($reportkey, $isaigenerated);
    }

    /**
     * Generate chart data from results.
     *
     * @param array $results Query results.
     * @param array $headers Column headers.
     * @param array $report Report definition.
     * @return array Chart data structure.
     */
    private static function generate_chart_data($results, $headers, $report) {
        $labelcolumn = $headers[0] ?? 'id';
        $valuecolumn = null;

        // Find best numeric column for values.
        foreach ($headers as $header) {
            if ($header === $labelcolumn) {
                continue;
            }
            $values = array_column($results, $header);
            $isnumeric = true;
            foreach ($values as $val) {
                if (!is_numeric($val)) {
                    $isnumeric = false;
                    break;
                }
            }
            if ($isnumeric) {
                $valuecolumn = $header;
                break;
            }
        }

        if (!$valuecolumn) {
            $valuecolumn = $headers[1] ?? 'value';
        }

        $chartvalues = array_map(function ($val) {
            return is_numeric($val) ? (float) $val : 0;
        }, array_column($results, $valuecolumn));

        $colors = self::generate_chart_colors(count($chartvalues), $report['charttype'] ?? 'bar');

        return [
            'labels' => array_column($results, $labelcolumn),
            'datasets' => [
                [
                    'label' => $report['name'],
                    'data' => $chartvalues,
                    'backgroundColor' => $colors,
                    'borderColor' => self::adjust_colors($colors, -20),
                    'borderWidth' => 2,
                ],
            ],
            'axis_labels' => [
                'x_axis' => $labelcolumn,
                'y_axis' => $valuecolumn,
            ],
        ];
    }

    /**
     * Generate chart colors.
     *
     * @param int $count Number of data points.
     * @param string $charttype Chart type.
     * @return array Colors array.
     */
    private static function generate_chart_colors($count, $charttype) {
        $basecolors = [
            '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
            '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#17a2b8',
        ];

        $charttype = strtolower($charttype);
        if (in_array($charttype, ['pie', 'donut', 'polar'])) {
            $colors = [];
            for ($i = 0; $i < $count; $i++) {
                $colors[] = $basecolors[$i % count($basecolors)];
            }
            return $colors;
        }

        return [$basecolors[0]];
    }

    /**
     * Adjust colors for border.
     *
     * @param array $colors Colors to adjust.
     * @param int $amount Adjustment amount.
     * @return array Adjusted colors.
     */
    private static function adjust_colors($colors, $amount) {
        return array_map(function ($color) use ($amount) {
            $color = ltrim($color, '#');
            $r = max(0, min(255, hexdec(substr($color, 0, 2)) + $amount));
            $g = max(0, min(255, hexdec(substr($color, 2, 2)) + $amount));
            $b = max(0, min(255, hexdec(substr($color, 4, 2)) + $amount));
            return sprintf("#%02x%02x%02x", $r, $g, $b);
        }, $colors);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'error' => new external_value(PARAM_TEXT, 'Error code if not successful', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_RAW, 'Error or status message', VALUE_OPTIONAL),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'cells' => new external_multiple_structure(
                        new external_single_structure([
                            'key' => new external_value(PARAM_TEXT, 'Column name'),
                            'value' => new external_value(PARAM_RAW, 'Cell value'),
                        ]),
                        'Row cells'
                    ),
                ]),
                'Query results'
            ),
            'headers' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Column header'),
                'Column headers'
            ),
            'chart_data' => new external_value(PARAM_RAW, 'JSON-encoded chart data', VALUE_OPTIONAL),
            'chart_type' => new external_value(PARAM_ALPHANUMEXT, 'Chart type', VALUE_OPTIONAL),
            'report_name' => new external_value(PARAM_TEXT, 'Report name'),
            'parameters_used' => new external_value(PARAM_RAW, 'JSON-encoded parameters used'),
            'is_duplicate' => new external_value(PARAM_BOOL, 'Whether this is a duplicate report'),
            'reports_used' => new external_value(PARAM_INT, 'Reports used this period', VALUE_OPTIONAL),
            'reports_limit' => new external_value(PARAM_INT, 'Reports limit', VALUE_OPTIONAL),
        ]);
    }
}
