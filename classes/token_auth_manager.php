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
 * Token-based Authentication Manager for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class token_auth_manager {
    private $installation_manager;
    private $current_user;
    
    public function __construct() {
        global $CFG, $USER;
        
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
        $this->installation_manager = new \report_adeptus_insights\installation_manager();
        $this->current_user = $USER;
        
        // Initialize cache for API key storage - will be done when needed
        $this->cache = null;
    }
    
    /**
     * Check if user is authenticated for Adeptus plugin access
     * This replaces the old global auth function
     * 
     * @param bool $redirect Whether to redirect to login if not authenticated
     * @return bool True if authenticated, false otherwise
     */
    public function check_auth($redirect = true) {
        global $PAGE, $OUTPUT, $CFG;
        
        // Check if user is logged into Moodle
        if (!isloggedin()) {
            if ($redirect) {
                redirect(new \moodle_url('/login/index.php'));
            }
            return false;
        }
        
        // Check if user has required capability
        $context = \context_system::instance();
        if (!has_capability('report/adeptus_insights:view', $context)) {
            if ($redirect) {
                throw new \moodle_exception('nopermissions', 'error', '', get_string('report_adeptus_insights:view', 'report_adeptus_insights'));
            }
            return false;
        }
        
        // Check if plugin is registered and has valid API key
        if (!$this->installation_manager->is_registered()) {
            if ($redirect) {
                redirect(new \moodle_url('/report/adeptus_insights/register_plugin.php'));
            }
            return false;
        }
        
        $api_key = $this->installation_manager->get_api_key();
        if (empty($api_key)) {
            if ($redirect) {
                redirect(new \moodle_url('/report/adeptus_insights/register_plugin.php'));
            }
            return false;
        }
        
        // Skip email validation - any admin can use the plugin
        // Authentication is based on API key and site URL only
        
        return true;
    }
    
    /**
     * Get authentication status for JavaScript
     * Enhanced version with token-based validation
     * 
     * @return array Authentication status and data
     */
    public function get_auth_status() {
        global $CFG;
        
        $auth_status = [
            'is_registered' => false,
            'has_api_key' => false,
            'api_key' => '',
            'installation_id' => null,
            'subscription' => null,
            'usage' => null,
            'user_authorized' => false,
            'auth_errors' => [],
            'user' => [
                'id' => $this->current_user->id ?? null,
                'name' => fullname($this->current_user) ?? 'Unknown User',
                'username' => $this->current_user->username ?? null,
                'email' => $this->current_user->email ?? null
            ]
        ];
        
        if ($this->installation_manager->is_registered()) {
            $auth_status['is_registered'] = true;
            $api_key = $this->installation_manager->get_api_key();
            $installation_id = $this->installation_manager->get_installation_id();
            
            if (!empty($api_key)) {
                $auth_status['has_api_key'] = true;
                $auth_status['api_key'] = $api_key;
                $auth_status['installation_id'] = $installation_id;
                
                // Skip email validation - any admin can use the plugin
                $auth_status['user_authorized'] = true;
                
                // Get subscription details directly from Laravel backend
                $backend_data = $this->get_backend_subscription_data($api_key);
                if ($backend_data && $backend_data['success']) {
                    $data = $backend_data['data'];
                    
                    // Structure to match Laravel backend subscription/show response
                    if (isset($data['subscription']) && isset($data['plan'])) {
                        $subscription = $data['subscription'];
                        $plan = $data['plan'];
                        
                        $auth_status['subscription'] = [
                            'plan_name' => $subscription['plan_name'] ?? 'Unknown',
                            'status' => $subscription['status'] ?? 'unknown',
                            'ai_credits_remaining' => $subscription['ai_credits_remaining'] ?? 0,
                            'exports_remaining' => $subscription['exports_remaining'] ?? 0,
                            'ai_credits_used_this_month' => $subscription['ai_credits_used'] ?? 0,
                            'reports_generated_this_month' => $subscription['exports_used'] ?? 0,
                            'plan_ai_credits_limit' => $plan['ai_credits'] ?? 0,
                            'plan_exports_limit' => $plan['exports'] ?? 0
                        ];
                        
                        // Add plan details to match Laravel structure
                        $auth_status['plan'] = [
                            'name' => $plan['name'] ?? 'Unknown',
                            'ai_credits' => $plan['ai_credits'] ?? 0,
                            'exports' => $plan['exports'] ?? 0
                        ];
                        
                        // Add usage data for JavaScript compatibility
                        $auth_status['usage'] = [
                            'ai_credits_used_this_month' => $subscription['ai_credits_used'] ?? 0,
                            'reports_generated_this_month' => $subscription['exports_used'] ?? 0,
                        ];
                    }
                } else {
                    // Fallback to local data if backend fails
                    $subscription = $this->installation_manager->get_subscription_details();
                    if ($subscription) {
                        $auth_status['subscription'] = [
                            'plan_name' => $subscription->plan_name ?? 'Unknown',
                            'status' => $subscription->status ?? 'unknown',
                            'ai_credits_remaining' => $subscription->ai_credits_remaining ?? 0,
                            'exports_remaining' => $subscription->exports_remaining ?? 0,
                            'ai_credits_used_this_month' => $subscription->ai_credits_used_this_month ?? 0,
                            'reports_generated_this_month' => $subscription->reports_generated_this_month ?? 0,
                            'plan_ai_credits_limit' => $subscription->plan_ai_credits_limit ?? 0,
                            'plan_exports_limit' => $subscription->plan_exports_limit ?? 0
                        ];
                        
                        $auth_status['plan'] = [
                            'name' => $subscription->plan_name ?? 'Unknown',
                            'ai_credits' => $subscription->plan_ai_credits_limit ?? 0,
                            'exports' => $subscription->plan_exports_limit ?? 0
                        ];
                    }
                    
                    $auth_status['usage'] = $this->get_usage_data();
                }
            }
        }
        
        return $auth_status;
    }
    
    /**
     * Validate user email against backend admin email
     * DISABLED: Email validation removed - any admin can use the plugin
     * 
     * @param bool $show_error Whether to show error messages
     * @return array Validation result
     */
    private function validate_user_email($show_error = true) {
        // Email validation disabled - always return valid
        return [
            'valid' => true,
            'installation_id' => null
        ];
    }
    
    /**
     * Validate authentication with backend
     * DISABLED: Email validation removed - any admin can use the plugin
     * 
     * @param string $api_key
     * @param string $site_url
     * @param string $user_email
     * @return array Validation result
     */
    private function validate_with_backend($api_key, $site_url, $user_email) {
        // Email validation disabled - always return valid
        return [
            'valid' => true,
            'installation_id' => null,
            'subscription_status' => null
        ];
    }
    
    /**
     * Cache API key for performance (simplified - no external cache dependency)
     * 
     * @param string $api_key
     */
    private function cache_api_key($api_key) {
        // For now, we'll skip caching to avoid dependency issues
        // The API key is already available from installation_manager
    }
    
    /**
     * Get cached API key (simplified - no external cache dependency)
     * 
     * @return string|null
     */
    public function get_cached_api_key() {
        // For now, return null to force getting from installation_manager
        return null;
    }
    
    /**
     * Get subscription data directly from Laravel backend
     * 
     * @param string $api_key
     * @return array|null
     */
    private function get_backend_subscription_data($api_key) {
        try {
            $backend_url = $this->installation_manager->get_api_url();
            $status_endpoint = $backend_url . '/installation/status';
            
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            
            $data = [
                'api_key' => $api_key
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $status_endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($response === false) {
                debugging('Backend subscription data fetch failed: ' . $error);
                return null;
            }
            
            if ($http_code !== 200) {
                debugging('Backend subscription data fetch failed with HTTP code: ' . $http_code);
                return null;
            }
            
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['success']) && $decoded['success']) {
                return $decoded;
            } else {
                debugging('Backend subscription data fetch failed: Invalid response');
                return null;
            }
            
        } catch (\Exception $e) {
            debugging('Backend subscription data fetch failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get usage data for current month
     * 
     * @return array|null
     */
    private function get_usage_data() {
        try {
            global $DB;
            $currentMonthStart = strtotime('first day of this month');
            $currentMonthEnd = strtotime('last day of this month');
            
            $reportsThisMonth = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {adeptus_report_history} 
                 WHERE generatedat >= ? AND generatedat <= ?",
                [$currentMonthStart, $currentMonthEnd]
            );
            
            $aiCreditsThisMonth = $DB->get_field_sql(
                "SELECT COALESCE(SUM(credits_used), 0) FROM {adeptus_usage_tracking} 
                 WHERE usage_type = 'ai_chat' AND timecreated >= ? AND timecreated <= ?",
                [$currentMonthStart, $currentMonthEnd]
            );
            
            return [
                'reports_generated_this_month' => (int)$reportsThisMonth,
                'ai_credits_used_this_month' => (int)$aiCreditsThisMonth,
                'current_period_start' => $currentMonthStart,
                'current_period_end' => $currentMonthEnd
            ];
        } catch (\Exception $e) {
            debugging('Failed to get usage data: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log validation errors for debugging
     * 
     * @param array $validation_result
     */
    private function log_validation_error($validation_result) {
        debugging('Adeptus Insights validation failed: ' . json_encode($validation_result));
    }
    
    /**
     * Show unauthorized error message
     */
    private function show_unauthorized_error() {
        global $OUTPUT, $PAGE;
        
        $PAGE->set_title(get_string('unauthorized_access', 'report_adeptus_insights'));
        $PAGE->set_heading(get_string('unauthorized_access', 'report_adeptus_insights'));
        
        echo $OUTPUT->header();
        
        $error_message = get_string('user_not_authorized', 'report_adeptus_insights');
        $contact_admin = get_string('contact_admin_for_access', 'report_adeptus_insights');
        
        echo $OUTPUT->notification($error_message . ' ' . $contact_admin, 'error');
        
        echo $OUTPUT->footer();
        exit;
    }
    
    /**
     * Get authentication headers for API calls
     * 
     * @return array Headers for API requests
     */
    public function get_auth_headers() {
        global $CFG;
        
        $api_key = $this->get_cached_api_key() ?: $this->installation_manager->get_api_key();
        $site_url = $CFG->wwwroot;
        
        return [
            'X-API-Key: ' . $api_key,
            'X-Site-URL: ' . $site_url
        ];
    }
    
    /**
     * Check if plugin should be in read-only mode
     * 
     * @return bool True if read-only mode should be enabled
     */
    public function should_enable_readonly_mode() {
        $auth_status = $this->get_auth_status();
        
        // Only check API key availability - email validation removed
        return !$auth_status['has_api_key'];
    }
}
