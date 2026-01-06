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

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/external.php');
require_once($CFG->dirroot . '/report/adeptus_insights/lib.php');

class external extends \external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function send_message_parameters() {
        return new \external_function_parameters(
            array(
                'message' => new \external_value(PARAM_TEXT, 'The message to send')
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function send_message_returns() {
        return new \external_single_structure(
            array(
                'message' => new \external_value(PARAM_TEXT, 'The AI response message'),
                'data' => new \external_single_structure(
                    array(
                        'columns' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Column name')
                        ),
                        'rows' => new \external_multiple_structure(
                            new \external_multiple_structure(
                                new \external_value(PARAM_TEXT, 'Cell value')
                            )
                        )
                    ),
                    'Report data',
                    VALUE_OPTIONAL
                ),
                'visualizations' => new \external_single_structure(
                    array(
                        'type' => new \external_value(PARAM_TEXT, 'Chart type'),
                        'labels' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Label')
                        ),
                        'datasets' => new \external_multiple_structure(
                            new \external_single_structure(
                                array(
                                    'label' => new \external_value(PARAM_TEXT, 'Dataset label'),
                                    'data' => new \external_multiple_structure(
                                        new \external_value(PARAM_FLOAT, 'Data point')
                                    ),
                                    'backgroundColor' => new \external_value(PARAM_TEXT, 'Background color'),
                                    'borderColor' => new \external_value(PARAM_TEXT, 'Border color')
                                )
                            )
                        ),
                        'title' => new \external_value(PARAM_TEXT, 'Chart title')
                    ),
                    'Visualization data',
                    VALUE_OPTIONAL
                )
            )
        );
    }

    /**
     * Send a message to the AI assistant
     * @param string $message The message to send
     * @return array The AI response
     */
    public static function send_message($message) {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::send_message_parameters(), array('message' => $message));

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Call the Laravel backend
        $response = self::call_laravel_backend($message);

        return $response;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_history_parameters() {
        return new \external_function_parameters(array());
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_history_returns() {
        return new \external_single_structure(
            array(
                'messages' => new \external_multiple_structure(
                    new \external_single_structure(
                        array(
                            'text' => new \external_value(PARAM_TEXT, 'Message text'),
                            'type' => new \external_value(PARAM_TEXT, 'Message type (user/ai)'),
                            'timestamp' => new \external_value(PARAM_INT, 'Message timestamp')
                        )
                    )
                )
            )
        );
    }

    /**
     * Get chat history
     * @return array Chat history
     */
    public static function get_history() {
        global $USER;

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Call the Laravel backend to get history
        $response = self::call_laravel_backend('get_history');

        return array('messages' => $response['messages']);
    }

    /**
     * Returns description of method parameters for registration
     * @return external_function_parameters
     */
    public static function register_installation_parameters() {
        return new \external_function_parameters(
            array(
                'action' => new \external_value(PARAM_TEXT, 'Action type'),
                'admin_name' => new \external_value(PARAM_TEXT, 'Administrator name'),
                'admin_email' => new \external_value(PARAM_EMAIL, 'Administrator email'),
                'ajax' => new \external_value(PARAM_BOOL, 'Is AJAX request', VALUE_DEFAULT, true),
                'sesskey' => new \external_value(PARAM_TEXT, 'Session key')
            )
        );
    }

    /**
     * Returns description of method result value for registration
     * @return external_description
     */
    public static function register_installation_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'message' => new \external_value(PARAM_TEXT, 'Response message'),
                'data' => new \external_single_structure(
                    array(
                        'installation_id' => new \external_value(PARAM_INT, 'Installation ID', VALUE_OPTIONAL),
                        'api_key' => new \external_value(PARAM_TEXT, 'API key', VALUE_OPTIONAL),
                        'subscription_plans' => new \external_multiple_structure(
                            new \external_single_structure(
                                array(
                                    'id' => new \external_value(PARAM_INT, 'Plan ID'),
                                    'name' => new \external_value(PARAM_TEXT, 'Plan name'),
                                    'price' => new \external_value(PARAM_TEXT, 'Plan price'),
                                    'billing_cycle' => new \external_value(PARAM_TEXT, 'Billing cycle'),
                                    'ai_credits' => new \external_value(PARAM_INT, 'AI credits'),
                                    'exports' => new \external_value(PARAM_INT, 'Exports'),
                                    'description' => new \external_value(PARAM_TEXT, 'Plan description')
                                )
                            ),
                            'Subscription plans',
                            VALUE_OPTIONAL
                        )
                    ),
                    'Response data',
                    VALUE_OPTIONAL
                )
            )
        );
    }

    /**
     * Register installation
     * @param string $action Action type
     * @param string $admin_name Administrator name
     * @param string $admin_email Administrator email
     * @param bool $ajax Is AJAX request
     * @param string $sesskey Session key
     * @return array Registration result
     */
    public static function register_installation($action, $admin_name, $admin_email, $ajax = true, $sesskey = '') {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::register_installation_parameters(), 
            array('action' => $action, 'admin_name' => $admin_name, 'admin_email' => $admin_email, 'ajax' => $ajax, 'sesskey' => $sesskey));

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Validate session key
        if (!confirm_sesskey($sesskey)) {
            throw new \moodle_exception('invalidsesskey');
        }

        // Call the installation manager
        $installation_manager = new \report_adeptus_insights\installation_manager();
        $result = $installation_manager->register_installation($admin_email, $admin_name);

        return $result;
    }

    /**
     * Returns description of method parameters for cancel subscription
     * @return external_function_parameters
     */
    public static function cancel_subscription_parameters() {
        return new \external_function_parameters(
            array(
                'action' => new \external_value(PARAM_TEXT, 'Action type'),
                'sesskey' => new \external_value(PARAM_TEXT, 'Session key')
            )
        );
    }

    /**
     * Returns description of method result value for cancel subscription
     * @return external_description
     */
    public static function cancel_subscription_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'message' => new \external_value(PARAM_TEXT, 'Response message')
            )
        );
    }

    /**
     * Cancel subscription
     * @param string $action Action type
     * @param string $sesskey Session key
     * @return array Cancellation result
     */
    public static function cancel_subscription($action, $sesskey) {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::cancel_subscription_parameters(), 
            array('action' => $action, 'sesskey' => $sesskey));

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Validate session key
        if (!confirm_sesskey($sesskey)) {
            throw new \moodle_exception('invalidsesskey');
        }

        // Call the installation manager
        $installation_manager = new \report_adeptus_insights\installation_manager();
        $result = $installation_manager->cancel_subscription();

        return $result;
    }

    /**
     * Returns description of method parameters for activate free plan
     * @return external_function_parameters
     */
    public static function activate_free_plan_parameters() {
        return new \external_function_parameters(
            array(
                'action' => new \external_value(PARAM_TEXT, 'Action type'),
                'plan_id' => new \external_value(PARAM_INT, 'Plan ID'),
                'sesskey' => new \external_value(PARAM_TEXT, 'Session key')
            )
        );
    }

    /**
     * Returns description of method result value for activate free plan
     * @return external_description
     */
    public static function activate_free_plan_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'message' => new \external_value(PARAM_TEXT, 'Response message')
            )
        );
    }

    /**
     * Activate free plan
     * @param string $action Action type
     * @param int $plan_id Plan ID
     * @param string $sesskey Session key
     * @return array Activation result
     */
    public static function activate_free_plan($action, $plan_id, $sesskey) {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::activate_free_plan_parameters(), 
            array('action' => $action, 'plan_id' => $plan_id, 'sesskey' => $sesskey));

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Validate session key
        if (!confirm_sesskey($sesskey)) {
            throw new \moodle_exception('invalidsesskey');
        }

        // Call the installation manager
        $installation_manager = new \report_adeptus_insights\installation_manager();
        $result = $installation_manager->activate_free_plan($plan_id);

        return $result;
    }



    /**
     * Returns description of method parameters for create billing portal
     * @return external_function_parameters
     */
    public static function create_billing_portal_parameters() {
        return new \external_function_parameters(
            array(
                'return_url' => new \external_value(PARAM_URL, 'Return URL after billing portal session', VALUE_DEFAULT, ''),
                'sesskey' => new \external_value(PARAM_TEXT, 'Session key for security')
            )
        );
    }

    /**
     * Returns description of method result value for create billing portal
     * @return external_description
     */
    public static function create_billing_portal_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'message' => new \external_value(PARAM_TEXT, 'Response message'),
                'data' => new \external_single_structure(
                    array(
                        'url' => new \external_value(PARAM_URL, 'Billing portal URL')
                    ),
                    'Billing portal data',
                    VALUE_OPTIONAL
                )
            )
        );
    }

    /**
     * Create billing portal session for subscription management
     * @param string $return_url Return URL after billing portal session
     * @param string $sesskey Session key for security
     * @return array Billing portal session data
     */
    public static function create_billing_portal($return_url, $sesskey) {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(self::create_billing_portal_parameters(), array(
            'return_url' => $return_url,
            'sesskey' => $sesskey
        ));

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Verify session key
        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        try {
            // Get installation manager
            $installation_manager = new \report_adeptus_insights\installation_manager();
            
            // Check if plugin is registered
            if (!$installation_manager->is_registered()) {
                return array(
                    'success' => false,
                    'message' => 'Plugin not registered. Please register first.'
                );
            }

            // Create billing portal session
            $result = $installation_manager->create_billing_portal_session();
            
            if ($result['success']) {
                return array(
                    'success' => true,
                    'message' => 'Billing portal session created successfully',
                    'data' => array(
                        'url' => $result['data']['url']
                    )
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create billing portal session'
                );
            }

        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error creating billing portal session: ' . $e->getMessage()
            );
        }
    }

    /**
     * Create billing portal session for specific product upgrade/downgrade
     */
    public static function create_product_portal_session_parameters() {
        return new \external_function_parameters([
            'product_id' => new \external_value(PARAM_TEXT, 'Stripe product ID to upgrade/downgrade to'),
            'return_url' => new \external_value(PARAM_URL, 'Return URL after portal session'),
            'sesskey' => new \external_value(PARAM_TEXT, 'Session key for security'),
        ]);
    }

    public static function create_product_portal_session_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'portal_url' => new \external_value(PARAM_URL, 'URL to redirect user to', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Response message', VALUE_OPTIONAL),
        ]);
    }

    public static function create_product_portal_session($product_id, $return_url, $sesskey) {
        global $USER, $DB;
        
        // Validate session key
        if (!confirm_sesskey($sesskey)) {
            return ['success' => false, 'message' => 'Invalid session key'];
        }
        
        // Check user capabilities
        $context = \context_system::instance();
        if (!has_capability('report/adeptus_insights:view', $context)) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }
        
        try {
            $installation_manager = new \report_adeptus_insights\installation_manager();
            $result = $installation_manager->create_product_portal_session($product_id, $return_url);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'portal_url' => $result['portal_url']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message']
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating portal session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get subscription details
     * @return array Subscription details
     */
    public static function get_subscription_details_parameters() {
        return new \external_function_parameters([]);
    }

    public static function get_subscription_details_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'data' => new \external_single_structure([
                // Basic plan info
                'plan_id' => new \external_value(PARAM_INT, 'Plan ID', VALUE_OPTIONAL),
                'plan_name' => new \external_value(PARAM_TEXT, 'Plan name', VALUE_OPTIONAL),
                'price' => new \external_value(PARAM_TEXT, 'Plan price', VALUE_OPTIONAL),
                'billing_cycle' => new \external_value(PARAM_TEXT, 'Billing cycle', VALUE_OPTIONAL),
                'status' => new \external_value(PARAM_TEXT, 'Subscription status', VALUE_OPTIONAL),
                // Legacy credits
                'ai_credits_remaining' => new \external_value(PARAM_INT, 'AI credits remaining', VALUE_OPTIONAL),
                'exports_remaining' => new \external_value(PARAM_INT, 'Exports remaining', VALUE_OPTIONAL),
                // Billing period
                'current_period_start' => new \external_value(PARAM_TEXT, 'Current period start', VALUE_OPTIONAL),
                'current_period_end' => new \external_value(PARAM_TEXT, 'Current period end', VALUE_OPTIONAL),
                'next_billing' => new \external_value(PARAM_TEXT, 'Next billing date', VALUE_OPTIONAL),
                // Trial and cancellation
                'is_trial' => new \external_value(PARAM_BOOL, 'Is trial subscription', VALUE_OPTIONAL),
                'trial_ends_at' => new \external_value(PARAM_TEXT, 'Trial end date', VALUE_OPTIONAL),
                'cancel_at_period_end' => new \external_value(PARAM_BOOL, 'Cancel at period end', VALUE_OPTIONAL),
                'cancelled_at' => new \external_value(PARAM_TEXT, 'Cancellation date', VALUE_OPTIONAL),
                // Payment info
                'failed_payment_attempts' => new \external_value(PARAM_INT, 'Failed payment attempts', VALUE_OPTIONAL),
                'last_payment_failed_at' => new \external_value(PARAM_TEXT, 'Last failed payment date', VALUE_OPTIONAL),
                'last_payment_succeeded_at' => new \external_value(PARAM_TEXT, 'Last successful payment date', VALUE_OPTIONAL),
                // Status flags
                'is_active' => new \external_value(PARAM_BOOL, 'Is subscription active', VALUE_OPTIONAL),
                'is_cancelled' => new \external_value(PARAM_BOOL, 'Is subscription cancelled', VALUE_OPTIONAL),
                'has_payment_issues' => new \external_value(PARAM_BOOL, 'Has payment issues', VALUE_OPTIONAL),
                'should_disable_api_access' => new \external_value(PARAM_BOOL, 'Should disable API access', VALUE_OPTIONAL),
                'status_message' => new \external_value(PARAM_TEXT, 'Status message', VALUE_OPTIONAL),
                'is_registered' => new \external_value(PARAM_BOOL, 'Is registered', VALUE_OPTIONAL),
                // Subscription IDs
                'subscription_id' => new \external_value(PARAM_INT, 'Subscription ID', VALUE_OPTIONAL),
                'stripe_subscription_id' => new \external_value(PARAM_TEXT, 'Stripe subscription ID', VALUE_OPTIONAL),
                'stripe_customer_id' => new \external_value(PARAM_TEXT, 'Stripe customer ID', VALUE_OPTIONAL),
                // Enhanced status (as raw structures - Moodle will accept any structure)
                'status_details' => new \external_value(PARAM_RAW, 'Status details JSON', VALUE_OPTIONAL),
                'cancellation_info' => new \external_value(PARAM_RAW, 'Cancellation info JSON', VALUE_OPTIONAL),
                'payment_info' => new \external_value(PARAM_RAW, 'Payment info JSON', VALUE_OPTIONAL),
                // Legacy usage metrics
                'ai_credits_used_this_month' => new \external_value(PARAM_INT, 'AI credits used this month', VALUE_OPTIONAL),
                'reports_generated_this_month' => new \external_value(PARAM_INT, 'Reports generated this month', VALUE_OPTIONAL),
                'plan_ai_credits_limit' => new \external_value(PARAM_INT, 'Plan AI credits limit', VALUE_OPTIONAL),
                'plan_exports_limit' => new \external_value(PARAM_INT, 'Plan exports limit', VALUE_OPTIONAL),
                // Token-based usage metrics
                'tokens_used' => new \external_value(PARAM_INT, 'Tokens used this period', VALUE_OPTIONAL),
                'tokens_remaining' => new \external_value(PARAM_INT, 'Tokens remaining (-1 for unlimited)', VALUE_OPTIONAL),
                'tokens_limit' => new \external_value(PARAM_INT, 'Token limit for plan', VALUE_OPTIONAL),
                'tokens_used_formatted' => new \external_value(PARAM_TEXT, 'Formatted tokens used', VALUE_OPTIONAL),
                'tokens_remaining_formatted' => new \external_value(PARAM_TEXT, 'Formatted tokens remaining', VALUE_OPTIONAL),
                'tokens_limit_formatted' => new \external_value(PARAM_TEXT, 'Formatted token limit', VALUE_OPTIONAL),
                'tokens_usage_percent' => new \external_value(PARAM_INT, 'Token usage percentage', VALUE_OPTIONAL),
            ], 'Subscription data', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Response message', VALUE_OPTIONAL),
        ]);
    }

    public static function get_subscription_details() {
        global $USER;
        
        // Check user capabilities
        $context = \context_system::instance();
        if (!has_capability('report/adeptus_insights:view', $context)) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }
        
        try {
            $installation_manager = new \report_adeptus_insights\installation_manager();
            $subscription = $installation_manager->get_subscription_details();
            
            if ($subscription) {
                debugging('External function returning subscription data: ' . json_encode($subscription));
                return [
                    'success' => true,
                    'data' => $subscription
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No subscription found'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting subscription details: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create billing portal session
     * @return array Billing portal session details
     */
    public static function create_billing_portal_session_parameters() {
        return new \external_function_parameters([
            'return_url' => new \external_value(PARAM_URL, 'Return URL after portal session'),
            'sesskey' => new \external_value(PARAM_TEXT, 'Session key for security'),
            'plan_id' => new \external_value(PARAM_TEXT, 'Plan ID for upgrade/downgrade', VALUE_OPTIONAL),
            'action' => new \external_value(PARAM_TEXT, 'Action to perform', VALUE_OPTIONAL),
        ]);
    }

    public static function create_billing_portal_session_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'portal_url' => new \external_value(PARAM_URL, 'URL to redirect user to', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Response message', VALUE_OPTIONAL),
        ]);
    }

    public static function create_billing_portal_session($return_url, $sesskey, $plan_id = null, $action = null) {
        global $USER;
        
        // Validate session key
        if (!confirm_sesskey($sesskey)) {
            return ['success' => false, 'message' => 'Invalid session key'];
        }
        
        // Check user capabilities
        $context = \context_system::instance();
        if (!has_capability('report/adeptus_insights:view', $context)) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }
        
        try {
            $installation_manager = new \report_adeptus_insights\installation_manager();
            $result = $installation_manager->create_billing_portal_session($return_url, $plan_id, $action);
            
            // Log to file for debugging
            
            debugging('External function received result: ' . json_encode($result));
            debugging('Result success: ' . ($result['success'] ?? 'NOT_SET'));
            debugging('Result data: ' . json_encode($result['data'] ?? 'NOT_SET'));
            debugging('URL in result[data][url]: ' . ($result['data']['url'] ?? 'NOT_FOUND'));
            debugging('URL in result[portal_url]: ' . ($result['portal_url'] ?? 'NOT_FOUND'));
            
            if ($result['success']) {
                $portal_url = $result['data']['url'] ?? $result['portal_url'] ?? null;
                debugging('Final portal_url to return: ' . ($portal_url ?? 'NULL'));
                
                return [
                    'success' => true,
                    'portal_url' => $portal_url
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message']
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating billing portal session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Call the Laravel backend
     * @param string $message The message to send
     * @return array The response from the backend
     */
    private static function call_laravel_backend($message) {
        global $CFG;

        $backend_url = get_config('report_adeptus_insights', 'backend_url');
        if (empty($backend_url)) {
            throw new \moodle_exception('Backend URL not configured');
        }

        $ch = curl_init($backend_url . '/chat');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'message' => $message,
            'user_id' => get_config('report_adeptus_insights', 'user_id'),
        'token' => get_config('report_adeptus_insights', 'api_token')
        )));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json'
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new \moodle_exception('Failed to communicate with backend: ' . $response);
        }

        return json_decode($response, true);
    }
} 