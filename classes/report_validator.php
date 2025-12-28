<?php
// This file is part of Moodle - http://moodle.org/
//
// Report SQL Validator - Checks if reports are compatible with current Moodle installation

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class report_validator {

    /** @var array Cache of table existence checks */
    private static $table_cache = [];

    /** @var array Cache of module availability checks */
    private static $module_cache = null;

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

        // Extract all table references from SQL
        $tables = self::extract_table_names($sql);

        // Check if all tables exist
        $missing_tables = [];
        foreach ($tables as $table) {
            if (!self::table_exists($table)) {
                $missing_tables[] = $table;
            }
        }

        // Check for MySQL-specific functions that may fail
        $mysql_functions = self::check_mysql_functions($sql);

        // Determine if valid
        $valid = empty($missing_tables);
        $reason = '';

        if (!$valid) {
            $reason = 'Missing required tables: ' . implode(', ', $missing_tables);
        } else if (!empty($mysql_functions)) {
            // Still valid, but warn about potential issues
            $reason = 'Warning: Uses database-specific functions';
        }

        return [
            'valid' => $valid,
            'reason' => $reason,
            'missing_tables' => $missing_tables,
            'mysql_functions' => $mysql_functions
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

        // Match {tablename} pattern - Moodle standard
        preg_match_all('/\{([a-z_][a-z0-9_]*)\}/i', $sql, $matches);
        if (!empty($matches[1])) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Remove duplicates
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

        // Check cache first
        if (isset(self::$table_cache[$table])) {
            return self::$table_cache[$table];
        }

        // Check if table exists
        try {
            $dbman = $DB->get_manager();
            $table_obj = new \xmldb_table($table);
            $exists = $dbman->table_exists($table_obj);

            // Cache result
            self::$table_cache[$table] = $exists;

            return $exists;
        } catch (\Exception $e) {
            // If check fails, assume table doesn't exist
            self::$table_cache[$table] = false;
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

        $mysql_specific = [
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
            'DATE\(' => 'Date extraction'
        ];

        foreach ($mysql_specific as $func => $description) {
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
        if (self::$module_cache !== null) {
            return self::$module_cache;
        }

        // Get list of all installed activity modules
        $modules = \core_component::get_plugin_list('mod');
        self::$module_cache = array_keys($modules);

        return self::$module_cache;
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

            // Add validation metadata to report
            $report['is_available'] = $validation['valid'];
            $report['unavailable_reason'] = $validation['reason'];
            $report['missing_tables'] = $validation['missing_tables'];

            // Always include report but mark availability
            $filtered[] = $report;
        }

        return $filtered;
    }

    /**
     * Clear validation cache (useful after installing new modules)
     */
    public static function clear_cache() {
        self::$table_cache = [];
        self::$module_cache = null;
    }
}
