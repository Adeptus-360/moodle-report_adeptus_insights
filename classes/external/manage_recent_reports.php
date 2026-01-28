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
 * External service to manage recent reports.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_recent_reports extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_TEXT, 'Action: clear_all or remove_single'),
            'reportid' => new external_value(PARAM_TEXT, 'Report ID (required for remove_single)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Manage recent reports.
     *
     * @param string $action Action to perform
     * @param string $reportid Report ID (for remove_single)
     * @return array Result
     */
    public static function execute(string $action, string $reportid = ''): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'reportid' => $reportid,
        ]);

        // Trim whitespace from action parameter.
        $params['action'] = trim($params['action']);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            $userid = $USER->id;

            if ($params['action'] === 'clear_all') {
                // Clear all recent reports for this user.
                $DB->delete_records('report_adeptus_insights_history', ['userid' => $userid]);

                return [
                    'success' => true,
                    'error' => false,
                    'message' => get_string('recent_reports_cleared', 'report_adeptus_insights'),
                    'action' => 'clear_all',
                    'reportid' => '',
                ];
            } else if ($params['action'] === 'remove_single') {
                if (empty($params['reportid'])) {
                    return [
                        'success' => false,
                        'error' => true,
                        'message' => get_string('error_report_id_required', 'report_adeptus_insights'),
                        'action' => '',
                        'reportid' => '',
                    ];
                }

                // Remove all history entries for this specific report for this user.
                $deleted = $DB->delete_records('report_adeptus_insights_history', [
                    'userid' => $userid,
                    'reportid' => $params['reportid'],
                ]);

                if ($deleted) {
                    return [
                        'success' => true,
                        'error' => false,
                        'message' => get_string('recent_report_removed', 'report_adeptus_insights'),
                        'action' => 'remove_single',
                        'reportid' => $params['reportid'],
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => true,
                        'message' => get_string('error_recent_report_not_found', 'report_adeptus_insights'),
                        'action' => '',
                        'reportid' => $params['reportid'],
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => true,
                    'message' => get_string('error_invalid_action', 'report_adeptus_insights'),
                    'action' => '',
                    'reportid' => '',
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => true,
                'message' => get_string('error_database', 'report_adeptus_insights'),
                'action' => '',
                'reportid' => '',
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
            'error' => new external_value(PARAM_BOOL, 'Whether an error occurred'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'action' => new external_value(PARAM_TEXT, 'Action performed'),
            'reportid' => new external_value(PARAM_TEXT, 'Report ID if applicable'),
        ]);
    }
}
