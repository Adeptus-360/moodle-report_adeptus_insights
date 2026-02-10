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
 * Token-based Authentication Manager for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;
/**
 * Token authentication manager for backend API requests.
 *
 * Handles generation and validation of authentication tokens for API communication.
 */
class token_auth_manager {
    /** @var installation_manager Installation manager instance. */
    private $installationmanager;

    /** @var object Current user object. */
    private $currentuser;

    /**
     * Constructor.
     */
    public function __construct() {
        global $USER;

        $this->installation_manager = new installation_manager();
        $this->current_user = $USER;

        // Initialize cache for API key storage - will be done when needed.
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

        // Check if user is logged into Moodle.
        if (!isloggedin()) {
            if ($redirect) {
                redirect(new \moodle_url('/login/index.php'));
            }
            return false;
        }

        // Check if user has required capability.
        $context = \context_system::instance();
        if (!has_capability('report/adeptus_insights:view', $context)) {
            if ($redirect) {
                $capability = get_string('report_adeptus_insights:view', 'report_adeptus_insights');
                throw new \moodle_exception('nopermissions', 'error', '', $capability);
            }
            return false;
        }

        // Check if plugin is registered and has valid API key.
        if (!$this->installation_manager->is_registered()) {
            if ($redirect) {
                redirect(new \moodle_url('/report/adeptus_insights/register_plugin.php'));
            }
            return false;
        }

        $apikey = $this->installation_manager->get_api_key();
        if (empty($apikey)) {
            if ($redirect) {
                redirect(new \moodle_url('/report/adeptus_insights/register_plugin.php'));
            }
            return false;
        }

        // Skip email validation - any admin can use the plugin.
        // Authentication is based on API key and site URL only.

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

        $authstatus = [
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
                'email' => $this->current_user->email ?? null,
            ],
        ];

        if ($this->installation_manager->is_registered()) {
            $authstatus['is_registered'] = true;
            $apikey = $this->installation_manager->get_api_key();
            $installationid = $this->installation_manager->get_installation_id();

            if (!empty($apikey)) {
                $authstatus['has_api_key'] = true;
                $authstatus['api_key'] = $apikey;
                $authstatus['installation_id'] = $installationid;

                // Skip email validation - any admin can use the plugin.
                $authstatus['user_authorized'] = true;

                // Get subscription details directly from Laravel backend.
                $backenddata = $this->get_backend_subscription_data($apikey);
                if ($backenddata && $backenddata['success']) {
                    $data = $backenddata['data'];

                    // Structure to match Laravel backend subscription/show response.
                    if (isset($data['subscription']) && isset($data['plan'])) {
                        $subscription = $data['subscription'];
                        $plan = $data['plan'];

                        $authstatus['subscription'] = [
                            'plan_name' => $subscription['plan_name'] ?? 'Unknown',
                            'status' => $subscription['status'] ?? 'unknown',
                            'ai_credits_remaining' => $subscription['ai_credits_remaining'] ?? 0,
                            'exports_remaining' => $subscription['exports_remaining'] ?? 0,
                            'ai_credits_used_this_month' => $subscription['ai_credits_used'] ?? 0,
                            'reports_generated_this_month' => $subscription['exports_used'] ?? 0,
                            'plan_ai_credits_limit' => $plan['ai_credits'] ?? 0,
                            'plan_exports_limit' => $plan['exports'] ?? 0,
                        ];

                        // Add plan details to match Laravel structure.
                        $authstatus['plan'] = [
                            'name' => $plan['name'] ?? 'Unknown',
                            'ai_credits' => $plan['ai_credits'] ?? 0,
                            'exports' => $plan['exports'] ?? 0,
                        ];

                        // Add usage data for JavaScript compatibility.
                        $authstatus['usage'] = [
                            'ai_credits_used_this_month' => $subscription['ai_credits_used'] ?? 0,
                            'reports_generated_this_month' => $subscription['exports_used'] ?? 0,
                        ];
                    }
                } else {
                    // Fallback to local data if backend fails.
                    $subscription = $this->installation_manager->get_subscription_details();
                    if ($subscription) {
                        $authstatus['subscription'] = [
                            'plan_name' => $subscription->plan_name ?? 'Unknown',
                            'status' => $subscription->status ?? 'unknown',
                            'ai_credits_remaining' => $subscription->ai_credits_remaining ?? 0,
                            'exports_remaining' => $subscription->exports_remaining ?? 0,
                            'ai_credits_used_this_month' => $subscription->ai_credits_used_this_month ?? 0,
                            'reports_generated_this_month' => $subscription->reports_generated_this_month ?? 0,
                            'plan_ai_credits_limit' => $subscription->plan_ai_credits_limit ?? 0,
                            'plan_exports_limit' => $subscription->plan_exports_limit ?? 0,
                        ];

                        $authstatus['plan'] = [
                            'name' => $subscription->plan_name ?? 'Unknown',
                            'ai_credits' => $subscription->plan_ai_credits_limit ?? 0,
                            'exports' => $subscription->plan_exports_limit ?? 0,
                        ];
                    }

                    $authstatus['usage'] = $this->get_usage_data();
                }
            }
        }

        return $authstatus;
    }

    /**
     * Validate user email against backend admin email
     * DISABLED: Email validation removed - any admin can use the plugin
     *
     * @param bool $showerror Whether to show error messages
     * @return array Validation result
     */
    private function validate_user_email($showerror = true) {
        // Email validation disabled - always return valid.
        return [
            'valid' => true,
            'installation_id' => null,
        ];
    }

    /**
     * Validate authentication with backend
     * DISABLED: Email validation removed - any admin can use the plugin
     *
     * @param string $apikey
     * @param string $siteurl
     * @param string $useremail
     * @return array Validation result
     */
    private function validate_with_backend($apikey, $siteurl, $useremail) {
        // Email validation disabled - always return valid.
        return [
            'valid' => true,
            'installation_id' => null,
            'subscription_status' => null,
        ];
    }

    /**
     * Cache API key for performance (simplified - no external cache dependency)
     *
     * @param string $apikey
     */
    private function cache_api_key($apikey) {
        // For now, we'll skip caching to avoid dependency issues.
        // The API key is already available from installation_manager.
    }

    /**
     * Get cached API key (simplified - no external cache dependency)
     *
     * @return string|null
     */
    public function get_cached_api_key() {
        // For now, return null to force getting from installation_manager.
        return null;
    }

    /**
     * Get subscription data directly from Laravel backend
     *
     * @param string $apikey
     * @return array|null
     */
    private function get_backend_subscription_data($apikey) {
        try {
            $backendurl = $this->installation_manager->get_api_url();
            $statusendpoint = $backendurl . '/installation/status';

            $postdata = json_encode([
                'api_key' => $apikey,
            ]);

            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');

            $options = [
                'CURLOPT_TIMEOUT' => 10,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ];

            $response = $curl->post($statusendpoint, $postdata, $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;
            $error = $curl->get_errno() ? $curl->error : '';

            if ($response === false || !empty($error)) {
                return null;
            }

            if ($httpcode !== 200) {
                return null;
            }

            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['success']) && $decoded['success']) {
                return $decoded;
            } else {
                return null;
            }
        } catch (\Exception $e) {
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
            $currentmonthstart = strtotime('first day of this month');
            $currentmonthend = strtotime('last day of this month');

            $reportsthismonth = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {report_adeptus_insights_history}
                 WHERE generatedat >= ? AND generatedat <= ?",
                [$currentmonthstart, $currentmonthend]
            );

            $aicreditsthismonth = $DB->get_field_sql(
                "SELECT COALESCE(SUM(credits_used), 0) FROM {report_adeptus_insights_usage}
                 WHERE usage_type = 'ai_chat' AND timecreated >= ? AND timecreated <= ?",
                [$currentmonthstart, $currentmonthend]
            );

            return [
                'reports_generated_this_month' => (int)$reportsthismonth,
                'ai_credits_used_this_month' => (int)$aicreditsthismonth,
                'current_period_start' => $currentmonthstart,
                'current_period_end' => $currentmonthend,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log validation errors for debugging
     *
     * @param array $validationresult
     */
    private function log_validation_error($validationresult) {
    }

    /**
     * Show unauthorized error message
     */
    private function show_unauthorized_error() {
        global $OUTPUT, $PAGE;

        $PAGE->set_title(get_string('unauthorized_access', 'report_adeptus_insights'));
        $PAGE->set_heading(get_string('unauthorized_access', 'report_adeptus_insights'));

        echo $OUTPUT->header();

        $errormessage = get_string('user_not_authorized', 'report_adeptus_insights');
        $contactadmin = get_string('contact_admin_for_access', 'report_adeptus_insights');

        echo $OUTPUT->notification($errormessage . ' ' . $contactadmin, 'error');

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

        $apikey = $this->get_cached_api_key() ?: $this->installation_manager->get_api_key();
        $siteurl = $CFG->wwwroot;

        return [
            'X-API-Key: ' . $apikey,
            'X-Site-URL: ' . $siteurl,
        ];
    }

    /**
     * Check if plugin should be in read-only mode
     *
     * @return bool True if read-only mode should be enabled
     */
    public function should_enable_readonly_mode() {
        $authstatus = $this->get_auth_status();

        // Only check API key availability - email validation removed.
        return !$authstatus['has_api_key'];
    }
}
