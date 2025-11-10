<?php
/**
 * AJAX endpoint to check subscription status for frontend
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
    $installation_manager = new \report_adeptus_insights\installation_manager();
    
    // Get subscription details
    $subscription = $installation_manager->get_subscription_details();
    
    // Debug: Log what we received from backend
    error_log('check_subscription_status.php - Backend subscription data: ' . json_encode($subscription));
    
    // Determine if user is on free plan
    $is_free_plan = false;
    $usage_type = 'monthly';
    $reports_generated_this_month = 0;
    
    if ($subscription) {
        $plan_name = strtolower($subscription['plan_name'] ?? '');
        $is_free_plan = (strpos($plan_name, 'free') !== false || 
                         strpos($plan_name, 'trial') !== false ||
                         ($subscription['price'] ?? 0) == 0);
        
        // Set usage type based on plan
        $usage_type = $is_free_plan ? 'all-time' : 'monthly';
        
        // Get reports count from subscription for paid plans
        $reports_generated_this_month = $subscription['reports_generated_this_month'] ?? 0;
    } else {
        // Default to free plan if no subscription data
        $is_free_plan = true;
        $usage_type = 'all-time';
    }
    
    // For free plan users, count actual reports from database
    if ($is_free_plan) {
        try {
            $reports_generated_this_month = $DB->count_records('adeptus_generated_reports', array('userid' => $USER->id));
        } catch (Exception $e) {
            error_log('Error counting generated reports for free plan: ' . $e->getMessage());
            $reports_generated_this_month = 0;
        }
    }
    
    // Get exports used - calculate from limit and remaining
    $exports_used = 0;
    $exports_limit = 10; // Default for free plan
    
    if ($is_free_plan) {
        // For free plan users, count exports from tracking table
        try {
            $exports_used = $DB->count_records('adeptus_export_tracking', array('userid' => $USER->id));
        } catch (Exception $e) {
            error_log('Error counting exports for free plan: ' . $e->getMessage());
            $exports_used = 0;
        }
        $exports_limit = 10; // Free plan limit
        $exports_remaining = max(0, $exports_limit - $exports_used);
    } else if ($subscription) {
        // For paid plan users, get from subscription
        $exports_limit = $subscription['plan_exports_limit'] ?? 100;
        $exports_remaining = $subscription['exports_remaining'] ?? $exports_limit;
        $exports_used = max(0, $exports_limit - $exports_remaining);
    } else {
        $exports_remaining = $exports_limit;
    }
    
    // Extract effective credits and status from subscription data
    $status = $subscription['status'] ?? 'unknown';
    $credit_type = $subscription['credit_type'] ?? 'basic';
    $total_credits_used = $subscription['total_credits_used_this_month'] ?? 0;
    $plan_total_credits_limit = $subscription['plan_total_credits_limit'] ?? 1000; // Default for free plan
    
    // Return subscription status with all required fields
    echo json_encode([
        'success' => true,
        'data' => [
            'is_free_plan' => $is_free_plan,
            'subscription' => $subscription,
            
            // Plan info
            'plan_name' => $subscription['plan_name'] ?? 'Free Plan',
            'plan_price' => $subscription['price'] ?? '0',
            'status' => $status, // ✅ NOW INCLUDED
            
            // Credits info (tier-based effective credits)
            'credit_type' => $credit_type, // ✅ NOW INCLUDED
            'total_credits_used_this_month' => $total_credits_used, // ✅ NOW INCLUDED
            'plan_total_credits_limit' => $plan_total_credits_limit, // ✅ NOW INCLUDED
            
            // Reports and exports
            'usage_type' => $usage_type,
            'reports_generated_this_month' => $reports_generated_this_month,
            'plan_exports_limit' => $subscription['plan_exports_limit'] ?? 10,
            'exports_used' => $exports_used,
            'exports_remaining' => $subscription['exports_remaining'] ?? 10
        ]
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
            'plan_exports_limit' => 10
        ]
    ]);
}
