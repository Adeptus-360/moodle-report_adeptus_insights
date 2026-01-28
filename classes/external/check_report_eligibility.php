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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * External service to check report creation eligibility.
 *
 * This endpoint calls the backend API to check if the user can create a report.
 * The backend is the single source of truth for report limits.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_report_eligibility extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Check if user is eligible to create a report.
     *
     * @return array Eligibility status
     */
    public static function execute(): array {
        global $CFG;

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get installation manager and use get_subscription_with_usage() which.
            // properly fetches subscription data from the backend.
            $installationmanager = new \report_adeptus_insights\installation_manager();

            if (!$installationmanager->is_registered()) {
                // Not registered - return free tier defaults.
                return [
                    'success' => true,
                    'error' => false,
                    'eligible' => true,
                    'message' => 'Free tier - limited reports available',
                    'reason' => '',
                    'reports_used' => 0,
                    'reports_limit' => 10,
                    'reports_remaining' => 10,
                ];
            }

            // Get subscription with usage data from backend.
            $subscription = $installationmanager->get_subscription_with_usage();

            if (!$subscription) {
                debugging('[Adeptus Insights] Report eligibility check - no subscription data', DEBUG_DEVELOPER);
                return [
                    'success' => false,
                    'error' => true,
                    'eligible' => false,
                    'message' => get_string('error_verify_eligibility', 'report_adeptus_insights'),
                    'reason' => 'no_subscription_data',
                    'reports_used' => 0,
                    'reports_limit' => 0,
                    'reports_remaining' => 0,
                ];
            }

            // Extract report usage data from subscription.
            $reportslimit = $subscription['reports_limit'] ?? 10;
            $reportsremaining = $subscription['reports_remaining'] ?? 10;
            $reportstotal = $subscription['reports_total'] ?? 0;
            $isoverlimit = $subscription['is_over_report_limit'] ?? false;

            // Calculate reports used (total generated minus remaining, or use total directly).
            $reportsused = $reportstotal;

            // Determine eligibility: -1 means unlimited.
            $eligible = ($reportslimit === -1) || ($reportsremaining > 0 && !$isoverlimit);

            return [
                'success' => true,
                'error' => false,
                'eligible' => $eligible,
                'message' => $eligible ? 'Eligible to create reports' : 'Report limit reached',
                'reason' => $eligible ? '' : 'limit_reached',
                'reports_used' => (int) $reportsused,
                'reports_limit' => (int) $reportslimit,
                'reports_remaining' => (int) $reportsremaining,
            ];
        } catch (\Exception $e) {
            debugging('[Adeptus Insights] Report eligibility check exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // FAIL CLOSED - deny on any error.
            return [
                'success' => false,
                'error' => true,
                'eligible' => false,
                'message' => get_string('error_verify_eligibility', 'report_adeptus_insights') . ': ' . $e->getMessage(),
                'reason' => 'exception',
                'reports_used' => 0,
                'reports_limit' => 0,
                'reports_remaining' => 0,
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
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'error' => new external_value(PARAM_BOOL, 'Whether an error occurred'),
            'eligible' => new external_value(PARAM_BOOL, 'Whether user is eligible to create reports'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'reason' => new external_value(PARAM_TEXT, 'Reason for ineligibility if applicable'),
            'reports_used' => new external_value(PARAM_INT, 'Number of reports used'),
            'reports_limit' => new external_value(PARAM_INT, 'Report limit'),
            'reports_remaining' => new external_value(PARAM_INT, 'Reports remaining'),
        ]);
    }
}
