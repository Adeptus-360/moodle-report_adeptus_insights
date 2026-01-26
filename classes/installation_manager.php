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
 * Installation Manager for Adeptus Insights.
 *
 * Handles plugin installation, registration, and subscription management.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Installation manager class for plugin registration and subscription management.
 *
 * Handles plugin registration, API key management, and subscription operations.
 */
class installation_manager {
    /** @var string API key for backend authentication. */
    private $apikey;

    /** @var string Base URL for API requests. */
    private $apiurl;

    /** @var string Installation ID for this Moodle instance. */
    private $installationid;

    /** @var bool Whether the installation is registered. */
    private $isregistered;

    /** @var string Last error message from API operations. */
    private $lasterror;

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;

        // Load existing settings
        try {
            // Get ANY existing record (not just id=1, as insert_record auto-generates IDs)
            $settings = $DB->get_record('report_adeptus_insights_settings', [], '*', IGNORE_MULTIPLE);
            if ($settings) {
                $this->api_key = $settings->api_key;
                $this->api_url = $settings->api_url;
                $this->installation_id = $settings->installation_id;
                $this->is_registered = (bool)$settings->is_registered;
            } else {
                $this->api_key = '';
                // Use centralized API config
                $this->api_url = api_config::get_backend_url();
                $this->installation_id = null;
                $this->is_registered = false;
            }
        } catch (\Exception $e) {
            $this->api_key = '';
            // Use centralized API config
            $this->api_url = api_config::get_backend_url();
            $this->installation_id = null;
            $this->is_registered = false;
        }
    }

    /**
     * Check if the installation is registered.
     *
     * @return bool True if registered, false otherwise.
     */
    public function is_registered() {
        global $DB;

        if ($this->is_registered) {
            return true;
        }

        // If not registered locally, check with backend API
        try {
            $status = $this->check_site_registration_status();
            if ($status && isset($status['success']) && $status['success']) {
                // Update local registration status
                $this->is_registered = true;
                $this->installation_id = $status['data']['installation_id'] ?? null;
                $this->api_key = $status['data']['api_key'] ?? '';

                // Save to local database
                $this->save_installation_settings();

                return true;
            }
        } catch (\Exception $e) {
            // Silently fail - registration check will return false.
            debugging('Registration check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return false;
    }

    /**
     * Get the API key for backend authentication.
     *
     * @return string The API key.
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Get the installation ID.
     *
     * @return string|null The installation ID or null if not set.
     */
    public function get_installation_id() {
        return $this->installation_id;
    }

    /**
     * Get the API URL for backend requests.
     *
     * @return string The API URL.
     */
    public function get_api_url() {
        return $this->api_url;
    }

    /**
     * Get the last error that occurred.
     *
     * @return array|null The last error details or null if no error.
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Clear the last error.
     */
    public function clear_last_error() {
        $this->last_error = null;
    }

    /**
     * Get the plugin version from the Moodle plugin manager.
     *
     * @return string The plugin version.
     */
    public function get_plugin_version() {
        $plugin = \core_plugin_manager::instance()->get_plugin_info('report_adeptus_insights');
        return $plugin ? $plugin->versiondb : '1.0.0';
    }

    /**
     * Register installation with backend API
     */
    public function register_installation($adminemail, $adminname, $siteurl = null, $sitename = null) {
        global $CFG, $DB;

        try {
            // Use provided site info or fall back to Moodle config
            $siteurl = $siteurl ?: $CFG->wwwroot;
            $sitename = $sitename ?: $CFG->fullname;

            // First check if the site is already registered
            $existingstatus = $this->check_site_registration_status();

            if ($existingstatus && isset($existingstatus['success']) && $existingstatus['success']) {
                // Use the existing installation data
                $this->api_key = $existingstatus['data']['api_key'] ?? '';
                $this->installation_id = $existingstatus['data']['installation_id'] ?? null;
                $this->is_registered = true;

                // Save settings to database
                $this->save_installation_settings();

                return [
                    'success' => true,
                    'message' => get_string('registration_success', 'report_adeptus_insights') . ' (Site was already registered)',
                    'data' => $existingstatus['data'],
                ];
            }

            // If not already registered, proceed with new registration
            $data = [
                'site_url' => $siteurl,
                'site_name' => $sitename,
                'admin_email' => $adminemail,
                'admin_name' => $adminname,
                'moodle_version' => $CFG->version,
                'php_version' => PHP_VERSION,
                'plugin_version' => $this->get_plugin_version(),
            ];

            $response = $this->make_api_request('installation/register', $data);

            if ($response && isset($response['success']) && $response['success']) {
                $this->api_key = $response['data']['api_key'] ?? '';
                $this->installation_id = $response['data']['installation_id'] ?? null;
                $this->is_registered = true;

                // Save settings to database
                $this->save_installation_settings();

                return [
                    'success' => true,
                    'message' => get_string('registration_success', 'report_adeptus_insights'),
                    'data' => $response['data'],
                ];
            } else {
                // Check if the error is due to site already existing
                if (isset($response['code']) && $response['code'] === 'SITE_EXISTS') {
                    // Set the existing installation data
                    $this->api_key = $response['data']['api_key'] ?? '';
                    $this->installation_id = $response['data']['existing_installation_id'] ?? null;
                    $this->is_registered = true;

                    // Save settings to database
                    $this->save_installation_settings();

                    // Ensure all required database tables exist
                    $this->ensure_database_tables_exist();

                    return [
                        'success' => false,
                        'message' => get_string('site_already_exists', 'report_adeptus_insights'),
                        'code' => 'SITE_EXISTS',
                        'redirect_to' => 'index',
                        'data' => $response['data'],
                    ];
                }

                $errormessage = $response['message'] ?? get_string('registration_error', 'report_adeptus_insights');
                $this->last_error = [
                    'message' => $errormessage,
                    'details' => $response['details'] ?? null,
                ];
                return [
                    'success' => false,
                    'message' => get_string('registration_error', 'report_adeptus_insights') . ': ' . $errormessage,
                ];
            }
        } catch (\Exception $e) {
            $this->last_error = [
                'message' => $e->getMessage(),
                'details' => $e->getTraceAsString(),
            ];
            return [
                'success' => false,
                'message' => get_string('registration_error', 'report_adeptus_insights') . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Setup starter subscription for new users
     */
    public function setup_starter_subscription($email, $name) {
        try {
            // Get available plans from backend
            $plansresponse = $this->make_api_request('subscription/plans', [], 'GET');

            if (!$plansresponse || !isset($plansresponse['success']) || !$plansresponse['success']) {
                return;
            }

            // Find free plan for Insights product
            $freeplan = null;
            foreach ($plansresponse['data']['plans'] as $plan) {
                $isfree = (isset($plan['tier']) && $plan['tier'] === 'free') ||
                           (isset($plan['is_free']) && $plan['is_free']);
                $isinsights = (isset($plan['product_key']) && $plan['product_key'] === 'insights');

                if ($isfree && $isinsights) {
                    $freeplan = $plan;
                    break;
                }
            }

            if (!$freeplan) {
                return;
            }

            // Activate free plan via backend
            $subscriptionresponse = $this->make_api_request('subscription/activate-free', [
                'plan_id' => $freeplan['id'],
                'billing_email' => $email,
            ]);

            if ($subscriptionresponse && isset($subscriptionresponse['success']) && $subscriptionresponse['success']) {
                // Update local subscription status
                $this->update_subscription_status([
                    'stripe_customer_id' => $subscriptionresponse['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $subscriptionresponse['data']['subscription_id'] ?? null,
                    'plan_name' => $freeplan['name'],
                    'plan_id' => $freeplan['id'],
                    'status' => $subscriptionresponse['data']['status'] ?? 'active',
                    'current_period_start' => $subscriptionresponse['data']['current_period_start'] ?? time(),
                    'current_period_end' => $subscriptionresponse['data']['current_period_end'] ?? (time() + 30 * 24 * 60 * 60),
                    'ai_credits_remaining' => $subscriptionresponse['data']['ai_credits_remaining'] ?? $freeplan['ai_credits'],
                    'ai_credits_pro_remaining' => $subscriptionresponse['data']['ai_credits_pro_remaining'] ?? ($freeplan['ai_credits_pro'] ?? 0),
                    'ai_credits_basic_remaining' => $subscriptionresponse['data']['ai_credits_basic_remaining'] ?? ($freeplan['ai_credits_basic'] ?? 0),
                    'exports_remaining' => $subscriptionresponse['data']['exports_remaining'] ?? $freeplan['exports'],
                    'billing_email' => $email,
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail - subscription sync is not critical.
            debugging('Subscription sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Set post-install notification for the admin.
     */
    private function set_post_install_notification() {
        global $DB;

        // Set a notification for the admin to complete setup
        $notification = [
            'type' => 'success',
            'message' => get_string('registration_complete', 'report_adeptus_insights'),
            'actions' => [
                [
                    'url' => new \moodle_url('/report/adeptus_insights/subscription.php'),
                    'text' => get_string('view_subscription', 'report_adeptus_insights'),
                ],
            ],
        ];

        // Store notification in session or database
        set_config('adeptus_insights_notification', json_encode($notification), 'report_adeptus_insights');
    }

    /**
     * Check registration status with backend API.
     *
     * @return bool True if registration is valid, false otherwise.
     */
    public function check_registration_status() {
        if (!$this->is_registered || !$this->api_key) {
            $this->set_registration_required_notification();
            return false;
        }

        try {
            $response = $this->make_api_request('installation/verify', []);

            if ($response && isset($response['success']) && $response['success']) {
                return true;
            } else {
                $this->is_registered = false;
                $this->set_registration_required_notification();
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if current site is already registered in backend
     */
    public function check_site_registration_status() {
        global $CFG, $DB;

        try {
            $siteurl = $CFG->wwwroot;
            $sitename = $CFG->fullname ?? $CFG->shortname ?? 'Moodle Site';

            // Try to get site name from database if config is not available
            if (empty($sitename) || $sitename === 'Moodle Site') {
                try {
                    $configrecord = $DB->get_record('config', ['name' => 'fullname']);
                    if ($configrecord && !empty($configrecord->value)) {
                        $sitename = $configrecord->value;
                    } else {
                        $configrecord = $DB->get_record('config', ['name' => 'shortname']);
                        if ($configrecord && !empty($configrecord->value)) {
                            $sitename = $configrecord->value;
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore database errors - site name lookup is optional.
                    debugging('Site name lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            $data = [
                'site_url' => $siteurl,
                'site_name' => $sitename,
                ];

            $response = $this->make_api_request('installation/status-by-site', $data);

            if ($response && isset($response['success']) && $response['success']) {
                return $response;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure all required database tables exist
     * This method creates any missing tables that are required for the plugin to function
     */
    private function ensure_database_tables_exist() {
        global $DB;

        try {
            // Check if report_adeptus_insights_subscription table exists
            if (!$DB->table_exists('report_adeptus_insights_subscription')) {
                $this->create_subscription_status_table();
            }

            // Check if report_adeptus_insights_settings table exists.
            if (!$DB->table_exists('report_adeptus_insights_settings')) {
                $this->create_install_settings_table();
            }
        } catch (\Exception $e) {
            // Ignore table creation errors - tables may already exist.
            debugging('Table creation check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Create report_adeptus_insights_subscription table if it doesn't exist.
     */
    private function create_subscription_status_table() {
        global $DB;

        try {
            $table = new \xmldb_table('report_adeptus_insights_subscription');

            if (!$DB->table_exists($table)) {
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
                $table->add_field('installation_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
                $table->add_field('subscription_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $table->add_field('plan_name', XMLDB_TYPE_CHAR, '100', null, null, null, null);
                $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, null, null, 'active');
                $table->add_field('current_period_start', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $table->add_field('current_period_end', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $table->add_field('ai_credits_remaining', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
                $table->add_field('exports_remaining', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
                $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $table->add_key('installation', XMLDB_KEY_FOREIGN, ['installation_id'], 'report_adeptus_insights_settings', ['id']);

                $DB->create_table($table);
            }
        } catch (\Exception $e) {
            // Ignore table creation errors - table may already exist.
            debugging('Subscription status table creation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Create report_adeptus_insights_settings table if it doesn't exist.
     */
    private function create_install_settings_table() {
        global $DB;

        try {
            $table = new \xmldb_table('report_adeptus_insights_settings');

            if (!$DB->table_exists($table)) {
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
                $table->add_field('api_key', XMLDB_TYPE_CHAR, '255', null, null, null, null);
                $table->add_field('api_url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
                $table->add_field('postinstall_redirect_stage', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
                $table->add_field('installation_step', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
                $table->add_field('installation_completed', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
                $table->add_field('installation_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $table->add_field('is_registered', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('registration_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $table->add_field('last_sync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $table->add_field('settings', XMLDB_TYPE_TEXT, null, null, null, null, null);
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
                $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

                $table->add_field('site_url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
                $table->add_field('site_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);

                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

                $DB->create_table($table);
            }
        } catch (\Exception $e) {
            // Ignore table creation errors - table may already exist.
            debugging('Install settings table creation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }


    /**
     * Verify API key with backend.
     *
     * @return bool True if API key is valid, false otherwise.
     */
    private function verify_api_key() {
        if (!$this->api_key) {
            return false;
        }

        try {
            $response = $this->make_api_request('installation/verify', []);
            return $response && isset($response['success']) && $response['success'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Set notification that registration is required.
     */
    private function set_registration_required_notification() {
        $notification = [
            'type' => 'warning',
            'message' => get_string('registration_required', 'report_adeptus_insights'),
            'actions' => [
                [
                    'url' => new \moodle_url('/report/adeptus_insights/subscription.php'),
                    'text' => get_string('register_now', 'report_adeptus_insights'),
                ],
            ],
        ];

        set_config('adeptus_insights_notification', json_encode($notification), 'report_adeptus_insights');
    }

    /**
     * Sync reports from the backend API.
     *
     * @return array Result with success status and message.
     */
    public function sync_reports_from_backend() {
        if (!$this->is_registered) {
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights'),
            ];
        }

        try {
            $response = $this->make_api_request('reports/sync', []);

            if ($response && isset($response['success']) && $response['success']) {
                $this->update_last_sync();
                return [
                    'success' => true,
                    'message' => get_string('sync_success', 'report_adeptus_insights'),
                    'data' => $response['data'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => get_string('sync_error', 'report_adeptus_insights') . ': ' . ($response['message'] ?? 'Unknown error'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('sync_error', 'report_adeptus_insights') . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check subscription status with backend API.
     *
     * @return bool True if subscription status was updated, false otherwise.
     */
    public function check_subscription_status() {
        if (!$this->is_registered) {
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights'),
            ];
        }

        try {
            $response = $this->make_api_request('subscription/status', []);

            if ($response && isset($response['success']) && $response['success']) {
                $this->update_subscription_status($response['data']);
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a new subscription.
     *
     * @param int $planid The plan ID to subscribe to.
     * @param string $paymentmethodid The Stripe payment method ID.
     * @param string $billingemail The billing email address.
     * @return array Result with success status and message.
     */
    public function create_subscription($planid, $paymentmethodid, $billingemail) {
        if (!$this->is_registered) {
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights'),
            ];
        }

        try {
            $requestdata = [
                'payment_method_id' => $paymentmethodid,
                'billing_email' => $billingemail,
                'plan_id' => $planid,
            ];

            $response = $this->make_api_request('subscription/create', $requestdata);

            if ($response && isset($response['success']) && $response['success']) {
                // Update local subscription status
                $subscriptiondata = [
                    'stripe_customer_id' => $response['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $response['data']['subscription_id'] ?? null,
                    'plan_name' => $response['data']['plan_name'] ?? 'Unknown Plan',
                    'plan_id' => $response['data']['plan_id'] ?? $planid,
                    'status' => $response['data']['status'] ?? 'active',
                    'current_period_start' => $response['data']['current_period_start'] ?? time(),
                    'current_period_end' => $response['data']['current_period_end'] ?? (time() + 30 * 24 * 60 * 60),
                    'ai_credits_remaining' => $response['data']['ai_credits_remaining'] ?? 0,
                    'ai_credits_pro_remaining' => $response['data']['ai_credits_pro_remaining'] ?? 0,
                    'ai_credits_basic_remaining' => $response['data']['ai_credits_basic_remaining'] ?? 0,
                    'exports_remaining' => $response['data']['exports_remaining'] ?? 0,
                    'billing_email' => $billingemail,
                ];

                $this->update_subscription_status($subscriptiondata);

                return [
                    'success' => true,
                    'message' => get_string('subscription_created', 'report_adeptus_insights'),
                    'data' => $response['data'],
                ];
            } else {
                $errormessage = isset($response['message']) ? $response['message'] : 'Unknown error';
                return [
                    'success' => false,
                    'message' => get_string('subscription_error', 'report_adeptus_insights') . ': ' . $errormessage,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('subscription_error', 'report_adeptus_insights') . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get subscription details from backend or local cache.
     *
     * @return array|null Subscription details or null if not found.
     */
    public function get_subscription_details() {
        // Get the API key from the local database
        $apikey = $this->get_api_key();
        if (!$apikey) {
            return null;
        }

        // Try primary subscription endpoint first
        try {
            $subscriptiondata = $this->get_backend_subscription_details($apikey);
            if ($subscriptiondata) {
                return $subscriptiondata;
            }
        } catch (\Exception $e) {
            // Primary endpoint failed - try fallback.
            debugging('Primary subscription endpoint failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Fallback: Try to get subscription data from installation/status endpoint.
        try {
            $subscriptiondata = $this->get_subscription_from_installation_status();
            if ($subscriptiondata) {
                return $subscriptiondata;
            }
        } catch (\Exception $e) {
            // Fallback also failed - return null below.
            debugging('Fallback subscription endpoint failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return null;
    }

    /**
     * Get subscription details from installation/status endpoint as fallback.
     *
     * This is used when the primary subscriptions/status endpoint fails,
     * typically when the customer record is missing on the backend.
     *
     * @return array|null Subscription data array or null on failure.
     */
    private function get_subscription_from_installation_status() {
        try {
            $response = $this->make_api_request('installation/status', [], 'POST');

            if (!$response || !isset($response['success']) || !$response['success']) {
                return null;
            }

            $data = $response['data'];
            $subscription = $data['subscription'] ?? [];

            if (empty($subscription) || empty($subscription['status'])) {
                return null;
            }

            $tier = $data['tier'] ?? 'free';

            // Get token data from backend response.
            $tokensused = $data['tokens_used'] ?? 0;
            $tokensremaining = $data['tokens_remaining'] ?? -1;
            $tokenslimit = $data['tokens_limit'] ?? -1;
            $exportsremaining = $data['exports_remaining'] ?? 0;

            // Calculate usage percentage.
            $tokensusagepercent = 0;
            if ($tokenslimit > 0 && $tokensremaining !== -1) {
                $tokensusagepercent = min(100, round(($tokensused / $tokenslimit) * 100));
            }

            // Build subscription data structure matching expected format.
            return [
                'plan_name' => ucfirst($tier) . ' Plan',
                'billing_cycle' => 'monthly',
                'status' => $subscription['status'] ?? $data['license_status'] ?? 'active',
                'exports_remaining' => $exportsremaining,
                'current_period_start' => null,
                'current_period_end' => $subscription['current_period_end'] ?? null,
                'next_billing' => $subscription['current_period_end'] ?? null,
                'is_trial' => false,
                'trial_ends_at' => null,
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
                'failed_payment_attempts' => 0,
                'last_payment_failed_at' => null,
                'last_payment_succeeded_at' => null,
                'is_active' => ($subscription['status'] ?? 'active') === 'active',
                'is_cancelled' => false,
                'has_payment_issues' => false,
                'should_disable_api_access' => false,
                'status_message' => get_string('active_subscription_status', 'report_adeptus_insights'),
                'is_registered' => true,
                'subscription_id' => null,
                'stripe_subscription_id' => null,
                'stripe_customer_id' => null,
                'status_details' => json_encode([]),
                'cancellation_info' => json_encode([]),
                'payment_info' => json_encode([]),
                'tier' => $tier,
                // Token-based usage metrics from backend.
                'tokens_used' => $tokensused,
                'tokens_remaining' => $tokensremaining,
                'tokens_limit' => $tokenslimit,
                'tokens_used_formatted' => $this->format_token_count($tokensused),
                'tokens_remaining_formatted' => $tokensremaining === -1 ? 'Unlimited' : $this->format_token_count($tokensremaining),
                'tokens_limit_formatted' => $tokenslimit === -1 ? 'Unlimited' : $this->format_token_count($tokenslimit),
                'tokens_usage_percent' => $tokensusagepercent,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get backend URL for API requests
     */
    public function get_backend_url() {
        return $this->api_url;
    }

    /**
     * Get subscription details from the backend API
     */
    private function get_backend_subscription_details($apikey) {
        $endpoint = 'subscriptions/status';

        $response = $this->make_api_request($endpoint, [], 'GET');

        if (!$response || !isset($response['success']) || !$response['success']) {
            return null;
        }

        $data = $response['data'];

        // Transform backend data to match expected format
        $subscription = $data['subscription'];
        $plan = $data['plan'];
        $usage = $data['usage'] ?? [];

        // Ensure plan_id is always included, with fallback
        $planid = $plan['id'] ?? $data['subscription']['plan_id'] ?? 1;

        // Token usage data from API (flattened fields first, then nested as fallback).
        $tokensused = $data['tokens_used'] ?? $usage['token_usage']['total_tokens_used'] ?? 0;
        $tokensremaining = $data['tokens_remaining'] ?? $usage['token_usage']['tokens_remaining'] ?? -1;
        $tokenslimit = $data['tokens_limit'] ?? $usage['token_usage']['tokens_limit'] ?? -1;

        // Calculate usage percentage
        $tokensusagepercent = 0;
        if ($tokenslimit > 0 && $tokensremaining !== -1) {
            $tokensusagepercent = min(100, round(($tokensused / $tokenslimit) * 100));
        }

        return [
            'plan_id' => $planid,
            'plan_name' => $plan['name'],
            'price' => $plan['price'],
            'billing_cycle' => $plan['billing_cycle'],
            'status' => $subscription['status'],
            'ai_credits_remaining' => $subscription['ai_credits_remaining'],
            'exports_remaining' => $subscription['exports_remaining'],
            'current_period_start' => $subscription['current_period_start'],
            'current_period_end' => $subscription['current_period_end'],
            'next_billing' => $subscription['current_period_end'],
            'is_trial' => $subscription['status_details']['is_trialing'] ?? false,
            'trial_ends_at' => $subscription['trial_ends_at'] ?? null,
            'cancel_at_period_end' => $subscription['cancel_at_period_end'] ?? false,
            'cancelled_at' => $subscription['cancelled_at'] ?? null,
            'failed_payment_attempts' => $subscription['failed_payment_attempts'] ?? 0,
            'last_payment_failed_at' => $subscription['last_payment_failed_at'] ?? null,
            'last_payment_succeeded_at' => $subscription['last_payment_succeeded_at'] ?? null,
            'is_active' => $subscription['is_active'] ?? true,
            'is_cancelled' => $subscription['is_cancelled'] ?? ($subscription['status'] === 'cancelled'),
            'has_payment_issues' => $subscription['has_payment_issues'] ?? false,
            'should_disable_api_access' => $subscription['should_disable_api_access'] ?? false,
            'status_message' => $subscription['status_message'] ?? get_string('active_subscription_status', 'report_adeptus_insights'),
            'is_registered' => true,
            'subscription_id' => $subscription['id'],
            'stripe_subscription_id' => $subscription['stripe_subscription_id'],
            'stripe_customer_id' => $subscription['stripe_customer_id'],
            // Enhanced status information (JSON encoded for external API compatibility)
            'status_details' => json_encode($subscription['status_details'] ?? []),
            'cancellation_info' => json_encode($subscription['cancellation_info'] ?? []),
            'payment_info' => json_encode($subscription['payment_info'] ?? []),
            // Legacy usage metrics
            'ai_credits_used_this_month' => $usage['ai_credits_used_this_month'] ?? 0,
            'reports_generated_this_month' => $usage['reports_generated_this_month'] ?? 0,
            'plan_ai_credits_limit' => $plan['ai_credits'],
            'plan_exports_limit' => $plan['exports'],
            // Token-based usage metrics
            'tokens_used' => $tokensused,
            'tokens_remaining' => $tokensremaining,
            'tokens_limit' => $tokenslimit,
            'tokens_used_formatted' => $this->format_token_count($tokensused),
            'tokens_remaining_formatted' => $tokensremaining === -1 ? 'Unlimited' : $this->format_token_count($tokensremaining),
            'tokens_limit_formatted' => $tokensremaining === -1 ? 'Unlimited' : $this->format_token_count($tokenslimit),
            'tokens_usage_percent' => $tokensusagepercent,
        ];
    }

    /**
     * Format token count for display (e.g., "50K", "1.2M").
     */
    private function format_token_count($tokens) {
        if ($tokens >= 1000000) {
            return round($tokens / 1000000, 1) . 'M';
        }
        if ($tokens >= 1000) {
            return round($tokens / 1000, 1) . 'K';
        }
        return (string) $tokens;
    }



    /**
     * Get payment configuration from backend.
     *
     * @return array Result with success status and payment config data.
     */
    public function get_payment_config() {
        try {
            $response = $this->make_api_request('subscription/config', [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to get payment configuration',
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('payment_not_configured', 'report_adeptus_insights'),
            ];
        }
    }

    /**
     * Cancel the current subscription.
     *
     * @return array Result with success status and message.
     */
    public function cancel_subscription() {
        try {
            $response = $this->make_api_request('subscription/cancel', []);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => get_string('subscription_cancelled', 'report_adeptus_insights'),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to cancel subscription',
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('subscription_error', 'report_adeptus_insights') . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update the subscription plan.
     *
     * @param int $planid The new plan ID.
     * @return array Result with success status and message.
     */
    public function update_subscription_plan($planid) {
        try {
            $response = $this->make_api_request('subscription/update', [
                'plan_id' => $planid,
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => get_string('subscription_plan_updated', 'report_adeptus_insights'),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? get_string('subscription_update_failed', 'report_adeptus_insights', ''),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('subscription_update_failed', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    /**
     * Get available subscription plans from backend.
     *
     * @return array Result with success status and plans array.
     */
    public function get_available_plans() {
        try {
            // Check if plugin is registered first
            if (!$this->is_registered()) {
                return [
                    'success' => false,
                    'message' => get_string('plugin_not_registered', 'report_adeptus_insights'),
                    'user_friendly_message' => get_string('please_register_plugin', 'report_adeptus_insights'),
                    'plans' => [],
                ];
            }

            $response = $this->make_api_request('subscription/plans', [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'plans' => $response['data']['plans'] ?? [],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? get_string('failed_to_get_plans', 'report_adeptus_insights'),
                    'user_friendly_message' => $this->get_user_friendly_error_message($response['message'] ?? ''),
                    'plans' => [],
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('failed_to_get_plans', 'report_adeptus_insights') . ': ' . $e->getMessage(),
                'user_friendly_message' => $this->get_user_friendly_error_message($e->getMessage()),
                'plans' => [],
            ];
        }
    }

    /**
     * Get usage statistics from backend
     */
    public function get_usage_stats() {
        try {
            $apikey = $this->get_api_key();
            if (!$apikey) {
                return null;
            }

            $response = $this->make_api_request('installation/status', [], 'POST');

            if (!$response || !isset($response['success']) || !$response['success']) {
                return null;
            }

            $data = $response['data'];
            $subscription = $data['subscription'];

            if (!$subscription) {
                return [
                    'ai_credits_used_this_month' => 0,
                    'reports_generated_this_month' => 0,
                    'reports_generated_total' => 0,
                    'current_period_start' => null,
                    'current_period_end' => null,
                ];
            }

            // Extract usage from the backend response structure.
            $usage = $data['usage'] ?? [];

            return [
                'ai_credits_used_this_month' => $usage['ai_credits_used_this_month'] ?? 0,
                'reports_generated_this_month' => $usage['reports_generated_this_month'] ?? 0,
                'reports_generated_total' => $usage['reports_generated_total'] ?? 0,
                'current_period_start' => $usage['current_period_start'] ?? null,
                'current_period_end' => $usage['current_period_end'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if user can use AI credits.
     *
     * @param int $amount Amount of credits to use.
     * @param string $type Credit type ('pro' or 'basic').
     * @return bool True if user has enough credits, false otherwise.
     */
    public function can_use_ai_credits($amount = 1, $type = 'pro') {
        $subscription = $this->get_subscription_details();
        if (!$subscription) {
            return false;
        }

        if ($type === 'pro') {
            return $subscription->ai_credits_pro_remaining >= $amount;
        } else {
            return $subscription->ai_credits_basic_remaining >= $amount;
        }
    }

    /**
     * Check if user can export reports.
     *
     * @return bool True if user has exports remaining, false otherwise.
     */
    public function can_export() {
        $subscription = $this->get_subscription_details();
        if (!$subscription) {
            return false;
        }

        return $subscription->exports_remaining > 0;
    }



    /**
     * Activate free plan
     * @param int $planid Plan ID
     * @return array Activation result
     */
    public function activate_free_plan($planid) {
        if (!$this->is_registered) {
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights'),
            ];
        }

        try {
            $response = $this->make_api_request('subscription/activate-free', [
                'plan_id' => $planid,
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                // Update local subscription status
                $subscriptiondata = [
                    'stripe_customer_id' => $response['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $response['data']['subscription_id'] ?? null,
                    'plan_name' => $response['data']['plan_name'] ?? 'Free Plan',
                    'plan_id' => $response['data']['plan_id'] ?? $planid,
                    'status' => $response['data']['status'] ?? 'active',
                    'current_period_start' => $response['data']['current_period_start'] ?? time(),
                    'current_period_end' => $response['data']['current_period_end'] ?? (time() + 30 * 24 * 60 * 60),
                    'ai_credits_remaining' => $response['data']['ai_credits_remaining'] ?? 0,
                    'ai_credits_pro_remaining' => $response['data']['ai_credits_pro_remaining'] ?? 0,
                    'ai_credits_basic_remaining' => $response['data']['ai_credits_basic_remaining'] ?? 0,
                    'exports_remaining' => $response['data']['exports_remaining'] ?? 0,
                    'billing_email' => $response['data']['billing_email'] ?? '',
                ];

                $this->update_subscription_status($subscriptiondata);

                return [
                    'success' => true,
                    'message' => get_string('free_plan_activated', 'report_adeptus_insights'),
                ];
            } else {
                $errormessage = isset($response['message']) ? $response['message'] : get_string('unknown_error', 'report_adeptus_insights');
                return [
                    'success' => false,
                    'message' => get_string('free_plan_activation_failed', 'report_adeptus_insights', $errormessage),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('free_plan_activation_failed', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    /**
     * Make an API request to the backend.
     *
     * Uses Moodle's curl wrapper for proper proxy support.
     *
     * @param string $endpoint The API endpoint.
     * @param array $data Request data.
     * @param string $method HTTP method (POST, GET, etc.).
     * @return array|null Decoded response or null on failure.
     * @throws \Exception On connection or response errors.
     */
    public function make_api_request($endpoint, $data = [], $method = 'POST') {
        $url = $this->api_url . '/' . $endpoint;

        // Use Moodle's curl wrapper for proxy support.
        $curl = new \curl();

        // Set headers.
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');

        // Add API key to headers for authenticated endpoints.
        if ($this->api_key && !in_array($endpoint, ['subscription/config'])) {
            $curl->setHeader('Authorization: Bearer ' . $this->api_key);
        }

        // Set curl options.
        $options = [
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        // Make request based on method.
        if ($method === 'GET') {
            $response = $curl->get($url, [], $options);
        } else if ($method === 'POST') {
            $response = $curl->post($url, json_encode($data), $options);
        } else {
            // For PUT, DELETE, PATCH etc.
            $options['CURLOPT_CUSTOMREQUEST'] = $method;
            $response = $curl->post($url, json_encode($data), $options);
        }

        // Get response info.
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;
        $error = $curl->get_errno() ? $curl->error : '';

        if ($response === false || $error) {
            throw new \Exception('API request failed: ' . $error . ' (URL: ' . $url . ')');
        }

        // Accept both 200 OK and 201 Created as success responses.
        if ($httpcode !== 200 && $httpcode !== 201) {
            throw new \Exception('API request failed: HTTP ' . $httpcode . ' - Response: ' . $response . ' (URL: ' . $url . ')');
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg() . ' - Response: ' . $response);
        }

        return $decoded;
    }

    /**
     * Save installation settings to the database.
     */
    private function save_installation_settings() {
        global $DB;

        try {
            $record = (object)[
                'api_key' => $this->api_key ?: '',
                'api_url' => $this->api_url ?: api_config::get_backend_url(),
                'installation_id' => $this->installation_id,
                'is_registered' => $this->is_registered ? 1 : 0,
                'registration_date' => time(),
                'last_sync' => time(),
                'timecreated' => time(),
                'timemodified' => time(),
            ];

            // Check if table exists
            if (!$DB->get_manager()->table_exists('report_adeptus_insights_settings')) {
                return;
            }

            // Try to find ANY existing record (not just id=1)
            $existing = $DB->get_record('report_adeptus_insights_settings', [], '*', IGNORE_MULTIPLE);

            if ($existing) {
                // Update existing record
                $record->id = $existing->id;
                $DB->update_record('report_adeptus_insights_settings', $record);
            } else {
                // Insert new record - let Moodle auto-generate the ID
                $newid = $DB->insert_record('report_adeptus_insights_settings', $record);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the registration.
            debugging('Installation settings save failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Update the last sync timestamp in the database.
     */
    private function update_last_sync() {
        global $DB;

        try {
            // Find the existing record
            $existing = $DB->get_record('report_adeptus_insights_settings', [], '*', IGNORE_MULTIPLE);
            if ($existing) {
                $DB->set_field('report_adeptus_insights_settings', 'last_sync', time(), ['id' => $existing->id]);
            }
        } catch (\Exception $e) {
            // Ignore sync timestamp update errors - non-critical.
            debugging('Last sync update failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Convert technical error messages to user-friendly messages.
     */
    private function get_user_friendly_error_message($technicalmessage) {
        $message = strtolower($technicalmessage);

        if (strpos($message, 'api key is required') !== false || strpos($message, '401') !== false) {
            return get_string('http_401_error', 'report_adeptus_insights') . '. ' . get_string('please_register_plugin', 'report_adeptus_insights');
        }

        if (strpos($message, '404') !== false) {
            return get_string('http_404_error', 'report_adeptus_insights') . '. ' . get_string('contact_administrator', 'report_adeptus_insights');
        }

        if (strpos($message, '500') !== false) {
            return get_string('http_500_error', 'report_adeptus_insights') . '. ' . get_string('try_again_later', 'report_adeptus_insights');
        }

        if (strpos($message, 'timeout') !== false) {
            return get_string('connection_timeout', 'report_adeptus_insights') . '. ' . get_string('try_again_later', 'report_adeptus_insights');
        }

        if (strpos($message, 'connection refused') !== false || strpos($message, 'could not resolve') !== false) {
            return get_string('network_error', 'report_adeptus_insights') . '. ' . get_string('contact_administrator', 'report_adeptus_insights');
        }

        return get_string('unknown_error', 'report_adeptus_insights') . '. ' . get_string('contact_administrator', 'report_adeptus_insights');
    }

    /**
     * Update subscription status in the database.
     *
     * @param array $subscriptiondata Subscription data to save.
     */
    private function update_subscription_status($subscriptiondata) {
        global $DB;

        try {
            // Check if table exists
            if (!$DB->get_manager()->table_exists('report_adeptus_insights_subscription')) {
                return;
            }

            $record = (object)[
                'stripe_customer_id' => $subscriptiondata['stripe_customer_id'] ?? null,
                'stripe_subscription_id' => $subscriptiondata['stripe_subscription_id'] ?? null,
                'plan_name' => $subscriptiondata['plan_name'] ?? 'Unknown',
                'plan_id' => $subscriptiondata['plan_id'] ?? null,
                'status' => $subscriptiondata['status'] ?? 'unknown',
                'current_period_start' => $subscriptiondata['current_period_start'] ?? null,
                'current_period_end' => $subscriptiondata['current_period_end'] ?? null,
                'ai_credits_remaining' => $subscriptiondata['ai_credits_remaining'] ?? 0,
                'ai_credits_pro_remaining' => $subscriptiondata['ai_credits_pro_remaining'] ?? 0,
                'ai_credits_basic_remaining' => $subscriptiondata['ai_credits_basic_remaining'] ?? 0,
                'exports_remaining' => $subscriptiondata['exports_remaining'] ?? 0,
                'billing_email' => $subscriptiondata['billing_email'] ?? null,
                'last_updated' => time(),
            ];

            // Find ANY existing record (not just id=1)
            $existing = $DB->get_record('report_adeptus_insights_subscription', [], '*', IGNORE_MULTIPLE);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('report_adeptus_insights_subscription', $record);
            } else {
                $newid = $DB->insert_record('report_adeptus_insights_subscription', $record);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the operation.
            debugging('Subscription status update failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Activate free plan manually (fallback method)
     */
    public function activate_free_plan_manually() {
        try {
            // Get available plans from backend
            $plansresponse = $this->make_api_request('subscription/plans', [], 'GET');

            if (!$plansresponse || !isset($plansresponse['success']) || !$plansresponse['success']) {
                return false;
            }

            // Find free plan for Insights product
            $freeplan = null;
            foreach ($plansresponse['data']['plans'] as $plan) {
                $isfree = (isset($plan['tier']) && $plan['tier'] === 'free') ||
                           (isset($plan['is_free']) && $plan['is_free']);
                $isinsights = (isset($plan['product_key']) && $plan['product_key'] === 'insights');

                if ($isfree && $isinsights) {
                    $freeplan = $plan;
                    break;
                }
            }

            if (!$freeplan) {
                return false;
            }

            // Activate free plan via backend
            $subscriptionresponse = $this->make_api_request('subscription/activate-free', [
                'plan_id' => $freeplan['id'],
                'billing_email' => $this->get_admin_email(),
            ]);

            if ($subscriptionresponse && isset($subscriptionresponse['success']) && $subscriptionresponse['success']) {
                // Update local subscription status
                $this->update_subscription_status([
                    'stripe_customer_id' => $subscriptionresponse['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $subscriptionresponse['data']['subscription_id'] ?? null,
                    'plan_name' => $freeplan['name'],
                    'plan_id' => $freeplan['id'],
                    'status' => $subscriptionresponse['data']['status'] ?? 'active',
                    'current_period_start' => $subscriptionresponse['data']['current_period_start'] ?? time(),
                    'current_period_end' => $subscriptionresponse['data']['current_period_end'] ?? (time() + 30 * 24 * 60 * 60),
                    'ai_credits_remaining' => $subscriptionresponse['data']['ai_credits_remaining'] ?? $freeplan['ai_credits'],
                    'ai_credits_pro_remaining' => $subscriptionresponse['data']['ai_credits_pro_remaining'] ?? ($freeplan['ai_credits_pro'] ?? 0),
                    'ai_credits_basic_remaining' => $subscriptionresponse['data']['ai_credits_basic_remaining'] ?? ($freeplan['ai_credits_basic'] ?? 0),
                    'exports_remaining' => $subscriptionresponse['data']['exports_remaining'] ?? $freeplan['exports'],
                    'billing_email' => $this->get_admin_email(),
                ]);

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create billing portal session for upgrades
     */
    public function create_billing_portal_session($returnurl = null, $planid = null, $action = null) {
        try {
            $data = [
                'return_url' => $returnurl ?: $this->get_site_url(),
            ];

            if ($planid) {
                $data['plan_id'] = $planid;
            }

            if ($action) {
                $data['action'] = $action;
            }

            // Check if user has a Stripe customer - if not, request customer creation
            $subscription = $this->get_subscription_details();
            $stripecustomerid = $subscription['stripe_customer_id'] ?? null;
            if (!$stripecustomerid) {
                $data['create_customer'] = true;
            }

            $response = $this->make_api_request('subscription/billing-portal', $data);

            // Log to file for debugging

            if ($response && isset($response['success']) && $response['success']) {
                $url = $response['data']['url'] ?? $response['data']['billing_portal_url'] ?? null;

                return [
                    'success' => true,
                    'data' => [
                        'url' => $url,
                    ],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? get_string('error_billing_portal_failed', 'report_adeptus_insights'),
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
     * Create Stripe Checkout session for new subscriptions
     *
     * @param int $planid The plan ID to subscribe to
     * @param string $stripepriceid Optional Stripe price ID
     * @param string $returnurl URL to return to after checkout
     * @return array Response with checkout_url on success
     */
    public function create_checkout_session($planid, $stripepriceid = null, $returnurl = null) {
        try {
            $data = [
                'plan_id' => $planid,
                'success_url' => $returnurl ?: $this->get_site_url(),
                'cancel_url' => $returnurl ?: $this->get_site_url(),
            ];

            if ($stripepriceid) {
                $data['stripe_price_id'] = $stripepriceid;
            }

            $response = $this->make_api_request('subscription/checkout', $data);

            if ($response && isset($response['success']) && $response['success']) {
                $checkouturl = $response['data']['checkout_url'] ?? null;

                return [
                    'success' => true,
                    'checkout_url' => $checkouturl,
                    'session_id' => $response['data']['session_id'] ?? null,
                ];
            } else {
                $errorcode = $response['error']['code'] ?? '';
                $errormessage = $response['error']['message'] ?? ($response['message'] ?? 'Failed to create checkout session');

                return [
                    'success' => false,
                    'error_code' => $errorcode,
                    'message' => $errormessage,
                ];
            }
        } catch (\Exception $e) {
            // Try to extract error code from exception message (contains JSON response).
            $errorcode = '';
            $errormessage = $e->getMessage();

            // Look for JSON in the exception message.
            if (preg_match('/Response: ({.+})/', $errormessage, $matches)) {
                $errorresponse = json_decode($matches[1], true);
                if ($errorresponse && isset($errorresponse['error']['code'])) {
                    $errorcode = $errorresponse['error']['code'];
                    $errormessage = $errorresponse['error']['message'] ?? $errormessage;
                }
            }

            return [
                'success' => false,
                'error_code' => $errorcode,
                'message' => $errormessage,
            ];
        }
    }

    /**
     * Verify a completed checkout session and update subscription
     *
     * @param string $sessionid Stripe checkout session ID
     * @return array Response with subscription details on success
     */
    public function verify_checkout_session($sessionid) {
        try {
            $response = $this->make_api_request('subscription/verify-checkout', [
                'session_id' => $sessionid,
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                // Clear local subscription cache to fetch fresh data
                $this->clear_subscription_cache();

                return [
                    'success' => true,
                    'subscription_id' => $response['data']['subscription_id'] ?? null,
                    'tier' => $response['data']['tier'] ?? 'pro',
                    'status' => $response['data']['status'] ?? 'active',
                    'plan_name' => $response['data']['plan_name'] ?? 'Pro',
                ];
            } else {
                $errorcode = $response['error']['code'] ?? '';
                $errormessage = $response['error']['message'] ?? ($response['message'] ?? 'Failed to verify checkout');

                return [
                    'success' => false,
                    'error_code' => $errorcode,
                    'message' => $errormessage,
                ];
            }
        } catch (\Exception $e) {
            // Try to extract error code from exception message (contains JSON response).
            $errorcode = '';
            $errormessage = $e->getMessage();

            // Look for JSON in the exception message.
            if (preg_match('/Response: ({.+})/', $errormessage, $matches)) {
                $errorresponse = json_decode($matches[1], true);
                if ($errorresponse && isset($errorresponse['error']['code'])) {
                    $errorcode = $errorresponse['error']['code'];
                    $errormessage = $errorresponse['error']['message'] ?? $errormessage;
                }
            }

            return [
                'success' => false,
                'error_code' => $errorcode,
                'message' => $errormessage,
            ];
        }
    }

    /**
     * Clear local subscription cache
     */
    public function clear_subscription_cache() {
        // Cache clearing is optional - don't fail if cache definition doesn't exist
        try {
            $cache = \cache::make('report_adeptus_insights', 'subscription_data');
            $cache->delete('subscription_details');
        } catch (\Exception $e) {
            // Cache definition may not exist - this is OK, subscription data will refresh naturally.
            debugging('Cache clear failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Create billing portal session for specific product upgrade/downgrade
     */
    public function create_product_portal_session($productid, $returnurl) {
        try {
            // Check if installation is registered
            if (!$this->is_registered()) {
                return ['success' => false, 'message' => get_string('error_installation_not_registered', 'report_adeptus_insights')];
            }

            // Get the subscription details
            $subscription = $this->get_subscription_details();
            if (!$subscription) {
                return ['success' => false, 'message' => get_string('error_no_subscription_found', 'report_adeptus_insights')];
            }

            // Get the target plan from the product ID
            $plan = $this->get_plan_by_stripe_product_id($productid);
            if (!$plan) {
                return ['success' => false, 'message' => get_string('error_target_plan_not_found', 'report_adeptus_insights')];
            }

            // Create or get Stripe customer
            $stripecustomerid = $subscription['stripe_customer_id'] ?? null;
            if (!$stripecustomerid) {
                // Create customer and subscription if they don't exist
                $customerresult = $this->create_stripe_customer_and_subscription($subscription, $plan);
                if (!$customerresult['success']) {
                    return $customerresult;
                }
                $stripecustomerid = $customerresult['stripe_customer_id'];
            }

            // Create billing portal session with return URL
            $portalresult = $this->create_stripe_portal_session($stripecustomerid, $returnurl);
            if (!$portalresult['success']) {
                return $portalresult;
            }

            return [
                'success' => true,
                'portal_url' => $portalresult['portal_url'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => get_string('error_portal_session_exception', 'report_adeptus_insights', $e->getMessage())];
        }
    }

    /**
     * Get plan by Stripe product ID.
     *
     * @param string $stripeproductid The Stripe product ID.
     * @return array|null The plan data or null if not found.
     */
    private function get_plan_by_stripe_product_id($stripeproductid) {
        // Get available plans from backend API
        $response = $this->get_available_plans();

        // Check if the API call was successful
        if (!$response || !isset($response['success']) || !$response['success']) {
            return null;
        }

        // Get the plans array from the response
        $plans = $response['plans'] ?? [];

        foreach ($plans as $plan) {
            if (isset($plan['stripe_product_id']) && $plan['stripe_product_id'] === $stripeproductid) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Get admin email for subscription creation.
     *
     * @return string The admin email address.
     */
    private function get_admin_email() {
        global $USER;
        return $USER->email ?? '';
    }

    /**
     * Get site URL for return redirects.
     *
     * @return string The site URL.
     */
    private function get_site_url() {
        global $CFG;
        return $CFG->wwwroot ?? '';
    }

    /**
     * Create Stripe customer and subscription.
     *
     * @param array $subscription Current subscription data.
     * @param array $plan Target plan data.
     * @return array Result with success status and customer ID.
     */
    private function create_stripe_customer_and_subscription($subscription, $plan) {
        try {
            // Call backend API to create customer and subscription
            $response = $this->make_api_request('subscription/billing-portal', [
                'return_url' => $this->get_site_url(),
                'create_customer' => true,
                'plan_id' => $plan['id'] ?? null,
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'stripe_customer_id' => $response['data']['stripe_customer_id'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? get_string('error_stripe_customer_failed', 'report_adeptus_insights'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_stripe_customer_exception', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    /**
     * Create Stripe portal session.
     *
     * @param string $stripecustomerid The Stripe customer ID.
     * @param string $returnurl URL to return to after portal.
     * @return array Result with success status and portal URL.
     */
    private function create_stripe_portal_session($stripecustomerid, $returnurl) {
        try {
            // Call backend API to create portal session
            $response = $this->make_api_request('subscription/billing-portal', [
                'return_url' => $returnurl,
                'stripe_customer_id' => $stripecustomerid,
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'portal_url' => $response['data']['url'] ?? $response['data']['billing_portal_url'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? get_string('error_portal_session_failed', 'report_adeptus_insights'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_portal_session_exception', 'report_adeptus_insights', $e->getMessage()),
            ];
        }
    }

    // =======================================================================
    // USAGE TRACKING METHODS (Enterprise-grade subscription tier management)
    // =======================================================================

    /**
     * Get subscription status with full usage data
     * Returns current usage, limits, and remaining quotas
     */
    public function get_subscription_with_usage() {
        try {
            if (!$this->is_registered()) {
                return $this->get_free_tier_defaults();
            }

            $response = $this->make_api_request('subscriptions/status', [], 'GET');

            if (!$response || !isset($response['success']) || !$response['success']) {
                return $this->get_free_tier_defaults();
            }

            $data = $response['data'];

            // Transform to standardized format
            return [
                'subscription' => $data['subscription'] ?? null,
                'plan' => $data['plan'] ?? null,
                'limits' => $data['limits'] ?? [],
                'usage' => $data['usage'] ?? [],
                'is_active' => $data['subscription']['is_active'] ?? false,
                'tier' => $data['plan']['tier'] ?? 'free',

                // Convenience accessors for usage
                'reports_remaining' => $data['usage']['reports_remaining'] ?? 10,
                'reports_total' => $data['usage']['reports_generated_total'] ?? 0,
                'reports_limit' => $data['usage']['reports_total_limit'] ?? 10,
                'is_over_report_limit' => $data['usage']['is_over_report_limit'] ?? false,

                'exports_remaining' => $data['usage']['exports_remaining'] ?? 3,
                'exports_used' => $data['usage']['exports_used_this_period'] ?? 0,
                'exports_limit' => $data['usage']['exports_limit'] ?? 3,

                'ai_credits_basic_remaining' => $data['usage']['ai_credits_basic_remaining'] ?? 100,
                'ai_credits_premium_remaining' => $data['usage']['ai_credits_premium_remaining'] ?? 5,

                // Billing period
                'billing_period_start' => $data['usage']['billing_period_start'] ?? null,
                'billing_period_end' => $data['usage']['billing_period_end'] ?? null,

                // Export formats
                'export_formats' => $data['limits']['export_formats'] ?? ['pdf'],
            ];
        } catch (\Exception $e) {
            return $this->get_free_tier_defaults();
        }
    }

    /**
     * Get free tier default values
     */
    private function get_free_tier_defaults() {
        return [
            'subscription' => null,
            'plan' => ['tier' => 'free', 'name' => 'Free'],
            'limits' => [
                'reports_total_limit' => 10,
                'exports_per_month' => 3,
                'ai_credits_basic' => 100,
                'ai_credits_premium' => 5,
                'export_formats' => ['pdf'],
            ],
            'usage' => [],
            'is_active' => false,
            'tier' => 'free',

            'reports_remaining' => 10,
            'reports_total' => 0,
            'reports_limit' => 10,
            'is_over_report_limit' => false,

            'exports_remaining' => 3,
            'exports_used' => 0,
            'exports_limit' => 3,

            'ai_credits_basic_remaining' => 100,
            'ai_credits_premium_remaining' => 5,

            'billing_period_start' => null,
            'billing_period_end' => null,

            'export_formats' => ['pdf'],
        ];
    }

    /**
     * Check if user can generate a specific report
     *
     * @param string $reportkey The report key to check
     * @return array Result with 'allowed', 'reason', 'message' keys
     */
    public function check_report_access($reportkey) {
        try {
            if (!$this->is_registered()) {
                // For unregistered installations, allow free tier reports only
                return [
                    'allowed' => true, // Allow generation but track locally
                    'reason' => 'unregistered',
                    'message' => get_string('installation_not_registered_free_tier', 'report_adeptus_insights'),
                    'tier' => 'free',
                ];
            }

            // Call backend API to check report access
            $response = $this->make_api_request('reports/check-access', [
                'report_key' => $reportkey,
            ]);

            if (!$response || !isset($response['success'])) {
                return [
                    'allowed' => true, // Fail open for now
                    'reason' => 'api_error',
                    'message' => get_string('access_verify_failed_default', 'report_adeptus_insights'),
                ];
            }

            if ($response['success'] && isset($response['data']['allowed'])) {
                return [
                    'allowed' => $response['data']['allowed'],
                    'reason' => $response['data']['reason'] ?? 'allowed',
                    'message' => $response['data']['message'] ?? '',
                    'required_tier' => $response['data']['required_tier'] ?? null,
                    'current_tier' => $response['data']['current_tier'] ?? null,
                    'current_usage' => $response['data']['current_usage'] ?? null,
                    'limit' => $response['data']['limit'] ?? null,
                    'upgrade_url' => $response['data']['upgrade_url'] ?? null,
                ];
            }

            return [
                'allowed' => false,
                'reason' => $response['data']['reason'] ?? 'unknown',
                'message' => $response['data']['message'] ?? get_string('error_access_denied', 'report_adeptus_insights'),
            ];
        } catch (\Exception $e) {
            return [
                'allowed' => true, // Fail open
                'reason' => 'exception',
                'message' => get_string('access_verify_failed_default', 'report_adeptus_insights'),
            ];
        }
    }

    /**
     * Track report generation after successful execution
     *
     * @param string $reportkey The report key that was generated
     * @param bool $isaigenerated Whether this is an AI-generated report
     * @return bool Success status
     */
    public function track_report_generation($reportkey, $isaigenerated = false) {
        try {
            if (!$this->is_registered()) {
                return false;
            }

            $response = $this->make_api_request('usage/track-report', [
                'report_key' => $reportkey,
                'is_ai_generated' => $isaigenerated,
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Track report deletion (decrements usage count)
     *
     * @param string $reportkey The report key that was deleted
     * @param bool $isaigenerated Whether this was an AI-generated report
     * @return bool Success status
     */
    public function track_report_deletion($reportkey, $isaigenerated = false) {
        try {
            if (!$this->is_registered()) {
                return false;
            }

            $response = $this->make_api_request('usage/track-report-deletion', [
                'report_key' => $reportkey,
                'is_ai_generated' => $isaigenerated,
            ]);

            return $response && isset($response['success']) && $response['success'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user can export in a specific format
     *
     * @param string $format Export format (pdf, csv, xlsx, json)
     * @return array Result with 'allowed', 'reason', 'message' keys
     */
    public function check_export_access($format) {
        try {
            $subscription = $this->get_subscription_with_usage();

            // Check format access
            $allowedformats = $subscription['export_formats'] ?? ['pdf'];
            if (!in_array(strtolower($format), array_map('strtolower', $allowedformats))) {
                return [
                    'allowed' => false,
                    'reason' => 'format_restricted',
                    'message' => "Export to {$format} requires a higher tier plan",
                    'allowed_formats' => $allowedformats,
                    'upgrade_required' => true,
                ];
            }

            // Check export limit
            $exportsremaining = $subscription['exports_remaining'] ?? 0;
            if ($exportsremaining !== -1 && $exportsremaining <= 0) {
                return [
                    'allowed' => false,
                    'reason' => 'limit_reached',
                    'message' => get_string('monthly_export_limit_reached', 'report_adeptus_insights'),
                    'remaining' => 0,
                    'limit' => $subscription['exports_limit'] ?? 3,
                    'upgrade_required' => true,
                ];
            }

            return [
                'allowed' => true,
                'remaining' => $exportsremaining,
                'format' => $format,
            ];
        } catch (\Exception $e) {
            return [
                'allowed' => true, // Fail open
                'reason' => 'exception',
                'message' => get_string('access_verify_failed_default', 'report_adeptus_insights'),
            ];
        }
    }

    /**
     * Track export usage
     *
     * @param string $format Export format used
     * @return bool Success status
     */
    public function track_export($format) {
        try {
            if (!$this->is_registered()) {
                return false;
            }

            $response = $this->make_api_request('usage/track-export', [
                'format' => $format,
            ]);

            return $response && isset($response['success']) && $response['success'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Track AI credit usage
     *
     * @param string $type Credit type ('basic' or 'premium')
     * @param int $amount Number of credits used
     * @return bool Success status
     */
    public function track_ai_credits($type, $amount) {
        try {
            if (!$this->is_registered()) {
                return false;
            }

            $response = $this->make_api_request('usage/track-ai-credits', [
                'type' => $type,
                'amount' => $amount,
            ]);

            return $response && isset($response['success']) && $response['success'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user is within a specific limit
     *
     * @param string $limittype The limit type to check ('reports_total', 'exports', 'ai_credits_basic', 'ai_credits_premium')
     * @return bool True if within limit
     */
    public function is_within_limit($limittype) {
        $subscription = $this->get_subscription_with_usage();

        switch ($limittype) {
            case 'reports_total':
                return !($subscription['is_over_report_limit'] ?? false);

            case 'exports':
                $remaining = $subscription['exports_remaining'] ?? 0;
                return $remaining === -1 || $remaining > 0;

            case 'ai_credits_basic':
                $remaining = $subscription['ai_credits_basic_remaining'] ?? 0;
                return $remaining === -1 || $remaining > 0;

            case 'ai_credits_premium':
                $remaining = $subscription['ai_credits_premium_remaining'] ?? 0;
                return $remaining === -1 || $remaining > 0;

            default:
                return true;
        }
    }

    /**
     * Get remaining quota for a limit type
     *
     * @param string $limittype The limit type
     * @return int Remaining quota (-1 for unlimited)
     */
    public function get_remaining_quota($limittype) {
        $subscription = $this->get_subscription_with_usage();

        switch ($limittype) {
            case 'reports_total':
                return $subscription['reports_remaining'] ?? 10;

            case 'exports':
                return $subscription['exports_remaining'] ?? 3;

            case 'ai_credits_basic':
                return $subscription['ai_credits_basic_remaining'] ?? 100;

            case 'ai_credits_premium':
                return $subscription['ai_credits_premium_remaining'] ?? 5;

            default:
                return -1;
        }
    }

    /**
     * Cache for feature permissions to avoid repeated API calls.
     *
     * @var array|null
     */
    private static $featurepermissionscache = null;

    /**
     * Check if a specific feature is enabled for the current subscription.
     *
     * This method calls the backend API to check if a feature is enabled.
     * The backend is the single source of truth - permissions are determined
     * by the product-price level of the installation's subscription.
     *
     * If the backend cannot be reached, features are disabled (no fallbacks).
     *
     * @param string $feature The feature name to check (e.g., 'alerts', 'advanced_export')
     * @return bool True if the feature is enabled, false otherwise
     */
    public function is_feature_enabled(string $feature): bool {
        $permissions = $this->get_feature_permissions();

        return (bool) ($permissions[$feature] ?? false);
    }

    /**
     * Get all feature permissions from the backend.
     *
     * Fetches permissions from the backend API and caches them.
     * The backend is the single source of truth for permissions.
     * If the backend cannot be reached, an empty array is returned
     * (all features disabled - no fallbacks).
     *
     * @param bool $forcerefresh Force a refresh from the backend
     * @return array Associative array of feature => enabled status
     */
    public function get_feature_permissions(bool $forcerefresh = false): array {
        // Return cached permissions if available and not forcing refresh.
        if (!$forcerefresh && self::$featurepermissionscache !== null) {
            return self::$featurepermissionscache;
        }

        try {
            // Call the backend permissions endpoint.
            $response = $this->make_api_request('features/permissions', [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                $permissions = $response['data']['permissions'] ?? $response['data'] ?? [];
                self::$featurepermissionscache = $permissions;
                return self::$featurepermissionscache;
            }
        } catch (\Exception $e) {
            // Backend unreachable - fall through to disable all features.
            debugging('Feature permissions fetch failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Backend unreachable - all features disabled (no fallbacks).
        self::$featurepermissionscache = [];
        return self::$featurepermissionscache;
    }

    /**
     * Clear the feature permissions cache.
     *
     * Call this when the subscription changes or is updated.
     */
    public function clear_feature_permissions_cache(): void {
        self::$featurepermissionscache = null;
    }
}
