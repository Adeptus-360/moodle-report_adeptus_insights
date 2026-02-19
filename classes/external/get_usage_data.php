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
 * External service to get usage data.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_usage_data extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get usage data for the current installation.
     *
     * @return array Usage data
     */
    public static function execute(): array {
        global $DB;

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get current month timestamps.
            $currentmonthstart = strtotime('first day of this month');
            $currentmonthend = strtotime('last day of this month');

            // Get reports generated this month.
            $reportsthismonth = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {report_adeptus_insights_history}
                 WHERE generatedat >= ? AND generatedat <= ?",
                [$currentmonthstart, $currentmonthend]
            );

            // Get AI credits used this month.
            $aicreditsthismonth = $DB->get_field_sql(
                "SELECT COALESCE(SUM(credits_used), 0) FROM {report_adeptus_insights_usage}
                 WHERE usage_type = 'ai_chat' AND timecreated >= ? AND timecreated <= ?",
                [$currentmonthstart, $currentmonthend]
            );

            // Get subscription details for limits.
            $subscription = $DB->get_record('report_adeptus_insights_subscription', ['id' => 1]);

            return [
                'success' => true,
                'error' => false,
                'data' => [
                    'reports_generated_this_month' => (int) $reportsthismonth,
                    'ai_credits_used_this_month' => (int) $aicreditsthismonth,
                    'current_period_start' => (int) $currentmonthstart,
                    'current_period_end' => (int) $currentmonthend,
                    'last_updated' => time(),
                    'max_reports_per_month' => $subscription ? (int) ($subscription->exports_remaining ?? 0) : 0,
                    'ai_credits_per_month' => $subscription ? (int) ($subscription->ai_credits_remaining ?? 0) : 0,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => true,
                'data' => [
                    'reports_generated_this_month' => 0,
                    'ai_credits_used_this_month' => 0,
                    'current_period_start' => time(),
                    'current_period_end' => time(),
                    'last_updated' => time(),
                    'max_reports_per_month' => 0,
                    'ai_credits_per_month' => 0,
                ],
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
            'data' => new external_single_structure([
                'reports_generated_this_month' => new external_value(PARAM_INT, 'Reports generated this month'),
                'ai_credits_used_this_month' => new external_value(PARAM_INT, 'AI credits used this month'),
                'current_period_start' => new external_value(PARAM_INT, 'Current period start timestamp'),
                'current_period_end' => new external_value(PARAM_INT, 'Current period end timestamp'),
                'last_updated' => new external_value(PARAM_INT, 'Last updated timestamp'),
                'max_reports_per_month' => new external_value(PARAM_INT, 'Maximum reports per month'),
                'ai_credits_per_month' => new external_value(PARAM_INT, 'AI credits per month'),
            ]),
        ]);
    }
}
