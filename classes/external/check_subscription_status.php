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
 * External service to check subscription status.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_subscription_status extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Check subscription status for the current installation.
     *
     * @return array Subscription status data
     */
    public static function execute(): array {
        global $DB, $USER;

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get installation manager.
            $installationmanager = new \report_adeptus_insights\installation_manager();

            // Get subscription details.
            $subscription = $installationmanager->get_subscription_details();

            // Determine if user is on free plan.
            $isfreeplan = false;
            $usagetype = 'monthly';
            $reportsgeneratedthismonth = 0;

            if ($subscription) {
                $planname = strtolower($subscription['plan_name'] ?? '');
                $isfreeplan = (strpos($planname, 'free') !== false ||
                                 strpos($planname, 'trial') !== false ||
                                 ($subscription['price'] ?? 0) == 0);

                // Set usage type based on plan.
                $usagetype = $isfreeplan ? 'all-time' : 'monthly';

                // Get reports count from subscription for paid plans.
                $reportsgeneratedthismonth = $subscription['reports_generated_this_month'] ?? 0;
            } else {
                // Default to free plan if no subscription data.
                $isfreeplan = true;
                $usagetype = 'all-time';
            }

            // For free plan users, count actual reports from database.
            if ($isfreeplan) {
                try {
                    $reportsgeneratedthismonth = $DB->count_records('report_adeptus_insights_generated', ['userid' => $USER->id]);
                } catch (\Exception $e) {
                    $reportsgeneratedthismonth = 0;
                }
            }

            // Get exports used - calculate from limit and remaining.
            $exportsused = 0;
            $exportslimit = 10; // Default for free plan.

            if ($isfreeplan) {
                // For free plan users, count exports from tracking table.
                try {
                    $exportsused = $DB->count_records('report_adeptus_insights_exports', ['userid' => $USER->id]);
                } catch (\Exception $e) {
                    $exportsused = 0;
                }
                $exportslimit = 10; // Free plan limit.
                $exportsremaining = max(0, $exportslimit - $exportsused);
            } else if ($subscription) {
                // For paid plan users, get from subscription.
                $exportslimit = $subscription['plan_exports_limit'] ?? 100;
                $exportsremaining = $subscription['exports_remaining'] ?? $exportslimit;
                $exportsused = max(0, $exportslimit - $exportsremaining);
            } else {
                $exportsremaining = $exportslimit;
            }

            // Extract effective credits and status from subscription data.
            $status = $subscription['status'] ?? 'unknown';
            $credittype = $subscription['credit_type'] ?? 'basic';
            $totalcreditsused = $subscription['total_credits_used_this_month'] ?? 0;
            $plantotalcreditslimit = $subscription['plan_total_credits_limit'] ?? 1000;

            return [
                'success' => true,
                'error' => false,
                'data' => [
                    'is_free_plan' => $isfreeplan,
                    'plan_name' => $subscription['plan_name'] ?? 'Free Plan',
                    'plan_price' => (string) ($subscription['price'] ?? '0'),
                    'status' => $status,
                    'credit_type' => $credittype,
                    'total_credits_used_this_month' => (int) $totalcreditsused,
                    'plan_total_credits_limit' => (int) $plantotalcreditslimit,
                    'usage_type' => $usagetype,
                    'reports_generated_this_month' => (int) $reportsgeneratedthismonth,
                    'plan_exports_limit' => (int) ($subscription['plan_exports_limit'] ?? 10),
                    'exports_used' => (int) $exportsused,
                    'exports_remaining' => (int) ($subscription['exports_remaining'] ?? 10),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => true,
                'data' => [
                    'is_free_plan' => true,
                    'plan_name' => 'Free Plan',
                    'plan_price' => '0',
                    'status' => 'error',
                    'credit_type' => 'basic',
                    'total_credits_used_this_month' => 0,
                    'plan_total_credits_limit' => 1000,
                    'usage_type' => 'all-time',
                    'reports_generated_this_month' => 0,
                    'plan_exports_limit' => 10,
                    'exports_used' => 0,
                    'exports_remaining' => 10,
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
                'is_free_plan' => new external_value(PARAM_BOOL, 'Whether user is on free plan'),
                'plan_name' => new external_value(PARAM_TEXT, 'Name of the current plan'),
                'plan_price' => new external_value(PARAM_TEXT, 'Price of the plan'),
                'status' => new external_value(PARAM_TEXT, 'Subscription status'),
                'credit_type' => new external_value(PARAM_TEXT, 'Credit type'),
                'total_credits_used_this_month' => new external_value(PARAM_INT, 'Total credits used this month'),
                'plan_total_credits_limit' => new external_value(PARAM_INT, 'Plan total credits limit'),
                'usage_type' => new external_value(PARAM_TEXT, 'Usage type (monthly or all-time)'),
                'reports_generated_this_month' => new external_value(PARAM_INT, 'Reports generated this month'),
                'plan_exports_limit' => new external_value(PARAM_INT, 'Plan exports limit'),
                'exports_used' => new external_value(PARAM_INT, 'Exports used'),
                'exports_remaining' => new external_value(PARAM_INT, 'Exports remaining'),
            ]),
        ]);
    }
}
