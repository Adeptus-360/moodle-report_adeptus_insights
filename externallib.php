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
 * External library functions for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/lib.php');

/**
 * External library class for Adeptus Insights report plugin.
 *
 * Provides external web service functions for subscription and checkout operations.
 */
class external extends \external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function send_message_parameters() {
        return new \external_function_parameters(
            [
                'message' => new \external_value(PARAM_TEXT, 'The message to send'),
            ]
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function send_message_returns() {
        return new \external_single_structure(
            [
                'message' => new \external_value(PARAM_TEXT, 'The AI response message'),
                'data' => new \external_single_structure(
                    [
                        'columns' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Column name')
                        ),
                        'rows' => new \external_multiple_structure(
                            new \external_multiple_structure(
                                new \external_value(PARAM_TEXT, 'Cell value')
                            )
                        ),
                    ],
                    'Report data',
                    VALUE_OPTIONAL
                ),
                'visualizations' => new \external_single_structure(
                    [
                        'type' => new \external_value(PARAM_TEXT, 'Chart type'),
                        'labels' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Label')
                        ),
                        'datasets' => new \external_multiple_structure(
                            new \external_single_structure(
                                [
                                    'label' => new \external_value(PARAM_TEXT, 'Dataset label'),
                                    'data' => new \external_multiple_structure(
                                        new \external_value(PARAM_FLOAT, 'Data point')
                                    ),
                                    'backgroundColor' => new \external_value(PARAM_TEXT, 'Background color'),
                                    'borderColor' => new \external_value(PARAM_TEXT, 'Border color'),
                                ]
                            )
                        ),
                        'title' => new \external_value(PARAM_TEXT, 'Chart title'),
                    ],
                    'Visualization data',
                    VALUE_OPTIONAL
                ),
            ]
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
        $params = self::validate_parameters(self::send_message_parameters(), ['message' => $message]);

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
        return new \external_function_parameters([]);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_history_returns() {
        return new \external_single_structure(
            [
                'messages' => new \external_multiple_structure(
                    new \external_single_structure(
                        [
                            'text' => new \external_value(PARAM_TEXT, 'Message text'),
                            'type' => new \external_value(PARAM_TEXT, 'Message type (user/ai)'),
                            'timestamp' => new \external_value(PARAM_INT, 'Message timestamp'),
                        ]
                    )
                ),
            ]
        );
    }

    /**
     * Get chat history
     * @return array Chat history
     */
    public static function get_history() {
        global $USER;

        // Parameter validation (even with no params, call for consistency).
        $params = self::validate_parameters(self::get_history_parameters(), []);

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Call the Laravel backend to get history
        $response = self::call_laravel_backend('get_history');

        return ['messages' => $response['messages']];
    }

    /**
     * Returns description of method parameters for registration
     * @return external_function_parameters
     */
    public static function register_installation_parameters() {
        return new \external_function_parameters(
            [
                'action' => new \external_value(PARAM_TEXT, 'Action type'),
                'admin_name' => new \external_value(PARAM_TEXT, 'Administrator name'),
                'admin_email' => new \external_value(PARAM_EMAIL, 'Administrator email'),
                'ajax' => new \external_value(PARAM_BOOL, 'Is AJAX request', VALUE_DEFAULT, true),
                'sesskey' => new \external_value(PARAM_TEXT, 'Session key'),
            ]
        );
    }

    /**
     * Returns description of method result value for registration
     * @return external_description
     */
    public static function register_installation_returns() {
        return new \external_single_structure(
            [
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'message' => new \external_value(PARAM_TEXT, 'Response message'),
                'data' => new \external_single_structure(
                    [
                        'installation_id' => new \external_value(PARAM_INT, 'Installation ID', VALUE_OPTIONAL),
                        'api_key' => new \external_value(PARAM_TEXT, 'API key', VALUE_OPTIONAL),
                        'subscription_plans' => new \external_multiple_structure(
                            new \external_single_structure(
                                [
                                    'id' => new \external_value(PARAM_INT, 'Plan ID'),
                                    'name' => new \external_value(PARAM_TEXT, 'Plan name'),
                                    'price' => new \external_value(PARAM_TEXT, 'Plan price'),
                                    'billing_cycle' => new \external_value(PARAM_TEXT, 'Billing cycle'),
                                    'ai_credits' => new \external_value(PARAM_INT, 'AI credits'),
                                    'exports' => new \external_value(PARAM_INT, 'Exports'),
                                    'description' => new \external_value(PARAM_TEXT, 'Plan description'),
                                ]
                            ),
                            'Subscription plans',
                            VALUE_OPTIONAL
                        ),
                    ],
                    'Response data',
                    VALUE_OPTIONAL
                ),
            ]
        );
    }

    /**
     * Register installation
     * @param string $action Action type
     * @param string $adminname Administrator name
     * @param string $adminemail Administrator email
     * @param bool $ajax Is AJAX request
     * @param string $sesskey Session key
     * @return array Registration result
     */
    public static function register_installation($action, $adminname, $adminemail, $ajax = true, $sesskey = '') {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(
            self::register_installation_parameters(),
            ['action' => $action, 'admin_name' => $adminname, 'admin_email' => $adminemail, 'ajax' => $ajax, 'sesskey' => $sesskey]
        );

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
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $result = $installationmanager->register_installation($adminemail, $adminname);

        return $result;
    }

    /**
     * Returns description of method parameters for cancel subscription
     * @return external_function_parameters
     */
    public static function cancel_subscription_parameters() {
        return new \external_function_parameters(
            [
                'action' => new \external_value(PARAM_TEXT, 'Action type'),
                'sesskey' => new \external_value(PARAM_TEXT, 'Session key'),
            ]
        );
    }

    /**
     * Returns description of method result value for cancel subscription
     * @return external_description
     */
    public static function cancel_subscription_returns() {
        return new \external_single_structure(
            [
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'message' => new \external_value(PARAM_TEXT, 'Response message'),
            ]
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
        $params = self::validate_parameters(
            self::cancel_subscription_parameters(),
            ['action' => $action, 'sesskey' => $sesskey]
        );

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
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $result = $installationmanager->cancel_subscription();

        return $result;
    }

    /**
     * Returns description of method parameters for activate free plan
     * @return external_function_parameters
     */
    public static function activate_free_plan_parameters() {
        return new \external_function_parameters(
            [
                'action' => new \external_value(PARAM_TEXT, 'Action type'),
                'plan_id' => new \external_value(PARAM_INT, 'Plan ID'),
                'sesskey' => new \external_value(PARAM_TEXT, 'Session key'),
            ]
        );
    }

    /**
     * Returns description of method result value for activate free plan
     * @return external_description
     */
    public static function activate_free_plan_returns() {
        return new \external_single_structure(
            [
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'message' => new \external_value(PARAM_TEXT, 'Response message'),
            ]
        );
    }

    /**
     * Activate free plan
     * @param string $action Action type
     * @param int $planid Plan ID
     * @param string $sesskey Session key
     * @return array Activation result
     */
    public static function activate_free_plan($action, $planid, $sesskey) {
        global $USER;

        // Parameter validation
        $params = self::validate_parameters(
            self::activate_free_plan_parameters(),
            ['action' => $action, 'plan_id' => $planid, 'sesskey' => $sesskey]
        );

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
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $result = $installationmanager->activate_free_plan($planid);

        return $result;
    }

    /**
     * Returns description of method parameters for get auth status
     * @return external_function_parameters
     */
    public static function get_auth_status_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Returns description of method result value for get auth status
     * @return external_description
     */
    public static function get_auth_status_returns() {
        return new \external_single_structure(
            [
                'success' => new \external_value(PARAM_BOOL, 'Success status'),
                'data' => new \external_single_structure(
                    [
                        'is_registered' => new \external_value(PARAM_BOOL, 'Plugin registration status'),
                        'has_api_key' => new \external_value(PARAM_BOOL, 'API key availability'),
                        'api_key' => new \external_value(PARAM_TEXT, 'API key'),
                        'installation_id' => new \external_value(PARAM_INT, 'Installation ID'),
                        'subscription' => new \external_single_structure(
                            [
                                'plan_id' => new \external_value(PARAM_INT, 'Plan ID'),
                                'plan_name' => new \external_value(PARAM_TEXT, 'Plan name'),
                                'status' => new \external_value(PARAM_TEXT, 'Subscription status'),
                                'ai_credits_remaining' => new \external_value(PARAM_INT, 'AI credits remaining'),
                                'exports_remaining' => new \external_value(PARAM_INT, 'Exports remaining'),
                            ],
                            'Subscription information',
                            VALUE_OPTIONAL
                        ),
                        'usage' => new \external_single_structure(
                            [
                                'reports_generated_this_month' => new \external_value(PARAM_INT, 'Reports generated this month'),
                                'ai_credits_used_this_month' => new \external_value(PARAM_INT, 'AI credits used this month'),
                                'current_period_start' => new \external_value(PARAM_INT, 'Current period start timestamp'),
                                'current_period_end' => new \external_value(PARAM_INT, 'Current period end timestamp'),
                            ],
                            'Usage information',
                            VALUE_OPTIONAL
                        ),
                    ]
                ),
            ]
        );
    }

    /**
     * Get authentication status for the plugin
     * @return array Authentication status and data
     */
    public static function get_auth_status() {
        global $USER;

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking
        require_capability('report/adeptus_insights:view', $context);

        // Get auth status using the token auth manager
        $authmanager = new \report_adeptus_insights\token_auth_manager();
        $authstatus = $authmanager->get_auth_status();

        return [
            'success' => true,
            'data' => $authstatus,
        ];
    }

    /**
     * Returns description of method parameters for create product portal session.
     *
     * @return external_function_parameters
     */
    public static function create_product_portal_session_parameters() {
        return new \external_function_parameters([
            'product_id' => new \external_value(PARAM_TEXT, 'Stripe product ID to upgrade/downgrade to'),
            'return_url' => new \external_value(PARAM_URL, 'Return URL after portal session'),
            'sesskey' => new \external_value(PARAM_TEXT, 'Session key for security'),
        ]);
    }

    /**
     * Returns description of method result value for create product portal session.
     *
     * @return external_description
     */
    public static function create_product_portal_session_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'portal_url' => new \external_value(PARAM_URL, 'URL to redirect user to', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Response message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Create product portal session for upgrades/downgrades.
     *
     * @param string $productid Stripe product ID.
     * @param string $returnurl Return URL after portal session.
     * @param string $sesskey Session key for security.
     * @return array Portal session result.
     */
    public static function create_product_portal_session($productid, $returnurl, $sesskey) {
        global $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::create_product_portal_session_parameters(), [
            'product_id' => $productid,
            'return_url' => $returnurl,
            'sesskey' => $sesskey,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking.
        require_capability('report/adeptus_insights:view', $context);

        // Validate session key.
        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $result = $installationmanager->create_product_portal_session($productid, $returnurl);

            if ($result['success']) {
                return [
                    'success' => true,
                    'portal_url' => $result['portal_url'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message'],
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_portal_session_exception', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    /**
     * Returns description of method parameters for get subscription details.
     *
     * @return external_function_parameters
     */
    public static function get_subscription_details_parameters() {
        return new \external_function_parameters([]);
    }

    /**
     * Returns description of method result value for get subscription details.
     *
     * @return external_description
     */
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
                // Enhanced status (as raw structures)
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

    /**
     * Get subscription details.
     *
     * @return array Subscription details.
     */
    public static function get_subscription_details() {
        global $USER;

        // Parameter validation (even with no params, call for consistency).
        $params = self::validate_parameters(self::get_subscription_details_parameters(), []);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking.
        require_capability('report/adeptus_insights:view', $context);

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $subscription = $installationmanager->get_subscription_details();

            if ($subscription) {
                return [
                    'success' => true,
                    'data' => $subscription,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => get_string('error_no_subscription_found', 'report_adeptus_insights'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_subscription_details_exception', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    /**
     * Returns description of method parameters for create billing portal session.
     *
     * @return external_function_parameters
     */
    public static function create_billing_portal_session_parameters() {
        return new \external_function_parameters([
            'return_url' => new \external_value(PARAM_URL, 'Return URL after portal session'),
            'sesskey' => new \external_value(PARAM_TEXT, 'Session key for security'),
            'plan_id' => new \external_value(PARAM_TEXT, 'Plan ID for upgrade/downgrade', VALUE_OPTIONAL),
            'action' => new \external_value(PARAM_TEXT, 'Action to perform', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns description of method result value for create billing portal session.
     *
     * @return external_description
     */
    public static function create_billing_portal_session_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'portal_url' => new \external_value(PARAM_URL, 'URL to redirect user to', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Response message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Create billing portal session.
     *
     * @param string $returnurl Return URL after portal session.
     * @param string $sesskey Session key for security.
     * @param string|null $planid Optional plan ID for upgrade/downgrade.
     * @param string|null $action Optional action to perform.
     * @return array Portal session result.
     */
    public static function create_billing_portal_session($returnurl, $sesskey, $planid = null, $action = null) {
        global $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::create_billing_portal_session_parameters(), [
            'return_url' => $returnurl,
            'sesskey' => $sesskey,
            'plan_id' => $planid,
            'action' => $action,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking.
        require_capability('report/adeptus_insights:view', $context);

        // Validate session key.
        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $result = $installationmanager->create_billing_portal_session($returnurl, $planid, $action);

            if ($result['success']) {
                return [
                    'success' => true,
                    'portal_url' => $result['data']['url'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message'],
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_billing_portal_exception', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    /**
     * Returns description of method parameters for create checkout session.
     *
     * @return external_function_parameters
     */
    public static function create_checkout_session_parameters() {
        return new \external_function_parameters([
            'plan_id' => new \external_value(PARAM_INT, 'Plan ID to subscribe to'),
            'stripe_price_id' => new \external_value(PARAM_TEXT, 'Stripe price ID', VALUE_OPTIONAL),
            'return_url' => new \external_value(PARAM_URL, 'Return URL after checkout'),
            'sesskey' => new \external_value(PARAM_TEXT, 'Session key for security'),
        ]);
    }

    /**
     * Returns description of method result value for create checkout session.
     *
     * @return external_description
     */
    public static function create_checkout_session_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'checkout_url' => new \external_value(PARAM_URL, 'URL to redirect user to Stripe Checkout', VALUE_OPTIONAL),
            'session_id' => new \external_value(PARAM_TEXT, 'Stripe checkout session ID', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Response message', VALUE_OPTIONAL),
            'error_code' => new \external_value(PARAM_TEXT, 'Error code for specific handling', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Create Stripe checkout session for new subscriptions.
     *
     * @param int $planid Plan ID to subscribe to.
     * @param string $stripepriceid Stripe price ID.
     * @param string $returnurl Return URL after checkout.
     * @param string $sesskey Session key for security.
     * @return array Checkout session result.
     */
    public static function create_checkout_session($planid, $stripepriceid, $returnurl, $sesskey) {
        global $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::create_checkout_session_parameters(), [
            'plan_id' => $planid,
            'stripe_price_id' => $stripepriceid,
            'return_url' => $returnurl,
            'sesskey' => $sesskey,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking.
        require_capability('report/adeptus_insights:view', $context);

        // Validate session key.
        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $result = $installationmanager->create_checkout_session($planid, $stripepriceid, $returnurl);

            if ($result['success']) {
                return [
                    'success' => true,
                    'checkout_url' => $result['checkout_url'],
                    'session_id' => $result['session_id'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_checkout_session_exception', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    /**
     * Returns description of method parameters for verify checkout session.
     *
     * @return external_function_parameters
     */
    public static function verify_checkout_session_parameters() {
        return new \external_function_parameters([
            'session_id' => new \external_value(PARAM_TEXT, 'Stripe checkout session ID'),
            'sesskey' => new \external_value(PARAM_TEXT, 'Session key for security'),
        ]);
    }

    /**
     * Returns description of method result value for verify checkout session.
     *
     * @return external_description
     */
    public static function verify_checkout_session_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the verification was successful'),
            'tier' => new \external_value(PARAM_TEXT, 'Subscription tier after upgrade', VALUE_OPTIONAL),
            'plan_name' => new \external_value(PARAM_TEXT, 'Plan name', VALUE_OPTIONAL),
            'message' => new \external_value(PARAM_TEXT, 'Response message', VALUE_OPTIONAL),
            'error_code' => new \external_value(PARAM_TEXT, 'Error code for specific handling', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Verify Stripe checkout session and update subscription.
     *
     * @param string $sessionid Stripe checkout session ID.
     * @param string $sesskey Session key for security.
     * @return array Verification result.
     */
    public static function verify_checkout_session($sessionid, $sesskey) {
        global $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::verify_checkout_session_parameters(), [
            'session_id' => $sessionid,
            'sesskey' => $sesskey,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability checking.
        require_capability('report/adeptus_insights:view', $context);

        // Validate session key.
        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $result = $installationmanager->verify_checkout_session($sessionid);

            if ($result['success']) {
                return [
                    'success' => true,
                    'tier' => $result['tier'] ?? 'pro',
                    'plan_name' => $result['plan_name'] ?? 'Pro',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_verify_checkout_exception', 'report_adeptus_insights', $e->getMessage()),
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

        $backendurl = get_config('report_adeptus_insights', 'backend_url');
        if (empty($backendurl)) {
            throw new \moodle_exception('Backend URL not configured');
        }

        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');

        $postdata = json_encode([
            'message' => $message,
            'user_id' => get_config('report_adeptus_insights', 'user_id'),
            'token' => get_config('report_adeptus_insights', 'api_token'),
        ]);

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $response = $curl->post($backendurl . '/chat', $postdata, $options);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode !== 200) {
            $error = $curl->get_errno() ? $curl->error : $response;
            throw new \moodle_exception('Failed to communicate with backend: ' . $error);
        }

        return json_decode($response, true);
    }
}
