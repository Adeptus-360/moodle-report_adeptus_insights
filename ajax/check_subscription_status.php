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
 * AJAX endpoint to check subscription status for frontend.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

// Check for valid login
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set JSON response headers
header('Content-Type: application/json');

try {
    // Get installation manager
    $installationmanager = new \report_adeptus_insights\installation_manager();

    // Get subscription details
    $subscription = $installationmanager->get_subscription_details();

    // Debug: Log what we received from backend

    // Determine if user is on free plan
    $isfreeplan = false;
    $usagetype = 'monthly';
    $reportsgeneratedthismonth = 0;

    if ($subscription) {
        $planname = strtolower($subscription['plan_name'] ?? '');
        $isfreeplan = (strpos($planname, 'free') !== false ||
                         strpos($planname, 'trial') !== false ||
                         ($subscription['price'] ?? 0) == 0);

        // Set usage type based on plan
        $usagetype = $isfreeplan ? 'all-time' : 'monthly';

        // Get reports count from subscription for paid plans
        $reportsgeneratedthismonth = $subscription['reports_generated_this_month'] ?? 0;
    } else {
        // Default to free plan if no subscription data
        $isfreeplan = true;
        $usagetype = 'all-time';
    }

    // For free plan users, count actual reports from database
    if ($isfreeplan) {
        try {
            $reportsgeneratedthismonth = $DB->count_records('report_adeptus_insights_generated', ['userid' => $USER->id]);
        } catch (Exception $e) {
            $reportsgeneratedthismonth = 0;
        }
    }

    // Get exports used - calculate from limit and remaining
    $exportsused = 0;
    $exportslimit = 10; // Default for free plan

    if ($isfreeplan) {
        // For free plan users, count exports from tracking table
        try {
            $exportsused = $DB->count_records('report_adeptus_insights_exports', ['userid' => $USER->id]);
        } catch (Exception $e) {
            $exportsused = 0;
        }
        $exportslimit = 10; // Free plan limit
        $exportsremaining = max(0, $exportslimit - $exportsused);
    } else if ($subscription) {
        // For paid plan users, get from subscription
        $exportslimit = $subscription['plan_exports_limit'] ?? 100;
        $exportsremaining = $subscription['exports_remaining'] ?? $exportslimit;
        $exportsused = max(0, $exportslimit - $exportsremaining);
    } else {
        $exportsremaining = $exportslimit;
    }

    // Extract effective credits and status from subscription data
    $status = $subscription['status'] ?? 'unknown';
    $credittype = $subscription['credit_type'] ?? 'basic';
    $totalcreditsused = $subscription['total_credits_used_this_month'] ?? 0;
    $plantotalcreditslimit = $subscription['plan_total_credits_limit'] ?? 1000; // Default for free plan

    // Return subscription status with all required fields
    echo json_encode([
        'success' => true,
        'data' => [
            'is_free_plan' => $isfreeplan,
            'subscription' => $subscription,

            // Plan info
            'plan_name' => $subscription['plan_name'] ?? 'Free Plan',
            'plan_price' => $subscription['price'] ?? '0',
            'status' => $status, // ✅ NOW INCLUDED

            // Credits info (tier-based effective credits)
            'credit_type' => $credittype, // ✅ NOW INCLUDED
            'total_credits_used_this_month' => $totalcreditsused, // ✅ NOW INCLUDED
            'plan_total_credits_limit' => $plantotalcreditslimit, // ✅ NOW INCLUDED

            // Reports and exports
            'usage_type' => $usagetype,
            'reports_generated_this_month' => $reportsgeneratedthismonth,
            'plan_exports_limit' => $subscription['plan_exports_limit'] ?? 10,
            'exports_used' => $exportsused,
            'exports_remaining' => $subscription['exports_remaining'] ?? 10,
        ],
    ]);
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'data' => [
            'is_free_plan' => true, // Default to free plan on error
            'subscription' => null,
            'plan_name' => 'Free Plan',
            'plan_price' => '0',
            'usage_type' => 'all-time',
            'reports_generated_this_month' => 0,
            'plan_exports_limit' => 10,
        ],
    ]);
}
