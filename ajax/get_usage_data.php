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
 * Get usage data AJAX endpoint.
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

// Set content type
header('Content-Type: application/json');

try {
    global $DB;

    // Get current month timestamps
    $currentMonthStart = strtotime('first day of this month');
    $currentMonthEnd = strtotime('last day of this month');

    // Get reports generated this month
    $reportsThisMonth = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {adeptus_report_history} 
         WHERE generatedat >= ? AND generatedat <= ?",
        [$currentMonthStart, $currentMonthEnd]
    );

    // Get AI credits used this month
    $aiCreditsThisMonth = $DB->get_field_sql(
        "SELECT COALESCE(SUM(credits_used), 0) FROM {adeptus_usage_tracking} 
         WHERE usage_type = 'ai_chat' AND timecreated >= ? AND timecreated <= ?",
        [$currentMonthStart, $currentMonthEnd]
    );

    // Get subscription details for limits
    $subscription = $DB->get_record('adeptus_subscription_status', ['id' => 1]);

    $usageData = [
        'reports_generated_this_month' => (int)$reportsThisMonth,
        'ai_credits_used_this_month' => (int)$aiCreditsThisMonth,
        'current_period_start' => $currentMonthStart,
        'current_period_end' => $currentMonthEnd,
        'last_updated' => time(),
        'subscription_limits' => [
            'max_reports_per_month' => $subscription ? ($subscription->exports_remaining ?? 0) : 0,
            'ai_credits_per_month' => $subscription ? ($subscription->ai_credits_remaining ?? 0) : 0,
        ],
    ];

    echo json_encode([
        'success' => true,
        'data' => $usageData,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get usage data: ' . $e->getMessage(),
        'data' => [
            'reports_generated_this_month' => 0,
            'ai_credits_used_this_month' => 0,
            'current_period_start' => time(),
            'current_period_end' => time(),
            'last_updated' => time(),
            'subscription_limits' => [
                'max_reports_per_month' => 0,
                'ai_credits_per_month' => 0,
            ],
        ],
    ]);
}
