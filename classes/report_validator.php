<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Report SQL Validator class.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;
/**
 * Report validator for checking report compatibility.
 *
 * Validates report definitions against available database tables and modules.
 */
class report_validator {
    /** @var array Cache of table existence checks */
    private static $tablecache = [];

    /** @var array Cache of module availability checks */
    private static $modulecache = null;

    /**
     * Validate if a report can be executed on this Moodle installation
     *
     * @param object $report Report definition from backend
     * @return array ['valid' => bool, 'reason' => string, 'missing_tables' => array]
     */
    public static function validate_report($report) {
        global $DB;

        $sql = $report['sqlquery'] ?? '';
        if (empty($sql)) {
            return ['valid' => false, 'reason' => 'No SQL query', 'missing_tables' => []];
        }

        // Extract all table references from SQL.
        $tables = self::extract_table_names($sql);

        // Check if all tables exist.
        $missingtables = [];
        foreach ($tables as $table) {
            if (!self::table_exists($table)) {
                $missingtables[] = $table;
            }
        }

        // Check for MySQL-specific functions that may fail.
        $mysqlfunctions = self::check_mysql_functions($sql);

        // Determine if valid.
        $valid = empty($missingtables);
        $reason = '';

        if (!$valid) {
            $reason = 'Missing required tables: ' . implode(', ', $missingtables);
        } else if (!empty($mysqlfunctions)) {
            // Still valid, but warn about potential issues.
            $reason = 'Warning: Uses database-specific functions';
        }

        return [
            'valid' => $valid,
            'reason' => $reason,
            'missing_tables' => $missingtables,
            'mysql_functions' => $mysqlfunctions,
        ];
    }

    /**
     * Extract table names from SQL query
     * Handles Moodle {prefix} notation
     *
     * @param string $sql SQL query
     * @return array Table names (without prefix)
     */
    private static function extract_table_names($sql) {
        $tables = [];

        // Match {tablename} pattern - Moodle standard.
        preg_match_all('/\{([a-z_][a-z0-9_]*)\}/i', $sql, $matches);
        if (!empty($matches[1])) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Remove duplicates.
        $tables = array_unique($tables);

        return $tables;
    }

    /**
     * Check if a table exists in Moodle database
     * Uses caching to avoid repeated checks
     *
     * @param string $table Table name (without prefix)
     * @return bool True if table exists
     */
    private static function table_exists($table) {
        global $DB;

        // Check cache first.
        if (isset(self::$tablecache[$table])) {
            return self::$tablecache[$table];
        }

        // Check if table exists.
        try {
            $dbman = $DB->get_manager();
            $tableobj = new \xmldb_table($table);
            $exists = $dbman->table_exists($tableobj);

            // Cache result.
            self::$tablecache[$table] = $exists;

            return $exists;
        } catch (\Exception $e) {
            // If check fails, assume table doesn't exist.
            self::$tablecache[$table] = false;
            return false;
        }
    }

    /**
     * Check for MySQL-specific functions in SQL
     *
     * @param string $sql SQL query
     * @return array List of MySQL-specific functions found
     */
    private static function check_mysql_functions($sql) {
        $functions = [];

        $mysqlspecific = [
            'DATE_FORMAT' => 'Date formatting function',
            'FROM_UNIXTIME' => 'Unix timestamp conversion',
            'UNIX_TIMESTAMP' => 'Timestamp conversion',
            'DATE_SUB' => 'Date arithmetic',
            'DATE_ADD' => 'Date arithmetic',
            'NOW\(' => 'Current timestamp',
            'CURDATE\(' => 'Current date',
            'GROUP_CONCAT' => 'String aggregation',
            'IF\(' => 'Conditional function',
            'YEAR\(' => 'Date extraction',
            'MONTH\(' => 'Date extraction',
            'DATE\(' => 'Date extraction',
        ];

        foreach ($mysqlspecific as $func => $description) {
            if (preg_match('/' . $func . '/i', $sql)) {
                $functions[] = $func;
            }
        }

        return $functions;
    }

    /**
     * Get list of installed Moodle activity modules
     *
     * @return array List of module names
     */
    private static function get_installed_modules() {
        if (self::$modulecache !== null) {
            return self::$modulecache;
        }

        // Get list of all installed activity modules.
        $modules = \core_component::get_plugin_list('mod');
        self::$modulecache = array_keys($modules);

        return self::$modulecache;
    }

    /**
     * Filter reports to only show those compatible with current installation
     *
     * @param array $reports Array of report definitions from backend
     * @return array Filtered reports with availability metadata
     */
    public static function filter_reports($reports) {
        $filtered = [];

        foreach ($reports as $report) {
            $validation = self::validate_report($report);

            // Add validation metadata to report.
            $report['is_available'] = $validation['valid'];
            $report['unavailable_reason'] = $validation['reason'];
            $report['missing_tables'] = $validation['missing_tables'];

            // Always include report but mark availability.
            $filtered[] = $report;
        }

        return $filtered;
    }

    /**
     * Clear validation cache (useful after installing new modules)
     */
    public static function clear_cache() {
        self::$tablecache = [];
        self::$modulecache = null;
    }
}
