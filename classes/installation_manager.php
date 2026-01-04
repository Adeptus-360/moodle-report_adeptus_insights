<?php
/**
 * Installation Manager for Adeptus Insights
 * Handles plugin installation, registration, and subscription management
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

class installation_manager {
    private $api_key;
    private $api_url;
    private $installation_id;
    private $is_registered;
    private $last_error;
    
    public function __construct() {
        global $DB;

        // Load existing settings
        try {
            // Get ANY existing record (not just id=1, as insert_record auto-generates IDs)
            $settings = $DB->get_record('adeptus_install_settings', [], '*', IGNORE_MULTIPLE);
            if ($settings) {
                $this->api_key = $settings->api_key;
                $this->api_url = $settings->api_url;
                $this->installation_id = $settings->installation_id;
                $this->is_registered = (bool)$settings->is_registered;
                debugging('Loaded installation settings: registered=' . ($this->is_registered ? 'true' : 'false') . ', id=' . $settings->id);
            } else {
                $this->api_key = '';
                // Use centralized API config
                $this->api_url = api_config::get_backend_url();
                $this->installation_id = null;
                $this->is_registered = false;
                debugging('No installation settings found in database');
            }
        } catch (\Exception $e) {
            debugging('Failed to load installation settings: ' . $e->getMessage());
            $this->api_key = '';
            // Use centralized API config
            $this->api_url = api_config::get_backend_url();
            $this->installation_id = null;
            $this->is_registered = false;
        }
    }
    
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
                
                debugging('Site is registered in backend. Updated local status.');
                return true;
            }
        } catch (\Exception $e) {
            debugging('Failed to check registration status with backend: ' . $e->getMessage());
        }
        
        return false;
    }
    
    public function get_api_key() {
        return $this->api_key;
    }
    
    public function get_installation_id() {
        return $this->installation_id;
    }
    
    public function get_api_url() {
        return $this->api_url;
    }
    
    public function get_last_error() {
        return $this->last_error;
    }
    
    public function clear_last_error() {
        $this->last_error = null;
    }
    
    public function get_plugin_version() {
        $plugin = \core_plugin_manager::instance()->get_plugin_info('report_adeptus_insights');
        return $plugin ? $plugin->versiondb : '1.0.0';
    }
    
    /**
     * Register installation with backend API
     */
    public function register_installation($admin_email, $admin_name, $site_url = null, $site_name = null) {
        global $CFG, $DB;
        
        try {
            // Log registration attempt
            debugging('Registration attempt for: ' . $admin_email);
            
            // Use provided site info or fall back to Moodle config
            $site_url = $site_url ?: $CFG->wwwroot;
            $site_name = $site_name ?: $CFG->fullname;
            
            // First check if the site is already registered
            debugging('Checking if site is already registered...');
            $existing_status = $this->check_site_registration_status();
            
            if ($existing_status && isset($existing_status['success']) && $existing_status['success']) {
                debugging('Site is already registered. Using existing installation.');
                
                // Use the existing installation data
                $this->api_key = $existing_status['data']['api_key'] ?? '';
                $this->installation_id = $existing_status['data']['installation_id'] ?? null;
                $this->is_registered = true;
                
                // Save settings to database
                $this->save_installation_settings();
                
                debugging('Successfully synchronized with existing installation. Installation ID: ' . $this->installation_id);
                
                return [
                    'success' => true,
                    'message' => get_string('registration_success', 'report_adeptus_insights') . ' (Site was already registered)',
                    'data' => $existing_status['data']
                ];
            }
            
            // If not already registered, proceed with new registration
            debugging('Site not found in backend. Proceeding with new registration.');
            
            $data = [
                'site_url' => $site_url,
                'site_name' => $site_name,
                'admin_email' => $admin_email,
                'admin_name' => $admin_name,
                'moodle_version' => $CFG->version,
                'php_version' => PHP_VERSION,
                'plugin_version' => $this->get_plugin_version()
            ];
            
            debugging('Registration data: ' . json_encode($data));
            
            $response = $this->make_api_request('installation/register', $data);
            
            debugging('Registration response: ' . json_encode($response));
            
            if ($response && isset($response['success']) && $response['success']) {
                $this->api_key = $response['data']['api_key'] ?? '';
                $this->installation_id = $response['data']['installation_id'] ?? null;
                $this->is_registered = true;
                
                // Save settings to database
                $this->save_installation_settings();
                
                // Create starter subscription
                $this->setup_starter_subscription($admin_email, $admin_name);
                
                // Set post-install notification
                $this->set_post_install_notification();
                
                debugging('Registration successful. Installation ID: ' . $this->installation_id);
                
                return [
                    'success' => true,
                    'message' => get_string('registration_success', 'report_adeptus_insights'),
                    'data' => $response['data']
                ];
            } else {
                // Check if the error is due to site already existing
                if (isset($response['code']) && $response['code'] === 'SITE_EXISTS') {
                    debugging('Site already exists on backend. Setting up existing installation.');
                    
                    // Set the existing installation data
                    $this->api_key = $response['data']['api_key'] ?? '';
                    $this->installation_id = $response['data']['existing_installation_id'] ?? null;
                    $this->is_registered = true;
                    
                    // Save settings to database
                    $this->save_installation_settings();
                    
                    // IMPORTANT: Complete all setup steps as if this was a fresh installation
                    // This ensures the plugin works correctly even when skipping installation
                    
                    // 1. Setup starter subscription (this creates local subscription records)
                    $this->setup_starter_subscription($admin_email, $admin_name);
                    
                    // 2. Set post-install notification (marks installation as complete)
                    $this->set_post_install_notification();
                    
                    // 3. Ensure all required database tables exist
                    $this->ensure_database_tables_exist();
                    
                    debugging('Existing installation setup completed. Installation ID: ' . $this->installation_id);
                    
                    return [
                        'success' => false,
                        'message' => 'Site already exists on backend',
                        'code' => 'SITE_EXISTS',
                        'redirect_to' => 'index',
                        'data' => $response['data']
                    ];
                }
                
                $error_message = $response['message'] ?? get_string('registration_error', 'report_adeptus_insights');
                $this->last_error = [
                    'message' => $error_message,
                    'details' => $response['details'] ?? null
                ];
                debugging('Registration failed: ' . $error_message);
                return [
                    'success' => false,
                    'message' => get_string('registration_error', 'report_adeptus_insights') . ': ' . $error_message
                ];
            }
            
        } catch (\Exception $e) {
            $this->last_error = [
                'message' => $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
            debugging('Registration exception: ' . $e->getMessage());
            debugging('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => get_string('registration_error', 'report_adeptus_insights') . ': ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Setup starter subscription for new users
     */
    public function setup_starter_subscription($email, $name) {
        try {
            debugging('Setting up starter subscription for: ' . $email);
            
            // Get available plans from backend
            $plans_response = $this->make_api_request('subscription/plans', [], 'GET');
            
            if (!$plans_response || !isset($plans_response['success']) || !$plans_response['success']) {
                debugging('Failed to get plans for starter subscription');
                return;
            }
            
            // Find free plan for Insights product
            $free_plan = null;
            foreach ($plans_response['data']['plans'] as $plan) {
                $is_free = (isset($plan['tier']) && $plan['tier'] === 'free') ||
                           (isset($plan['is_free']) && $plan['is_free']);
                $is_insights = (isset($plan['product_key']) && $plan['product_key'] === 'insights');

                if ($is_free && $is_insights) {
                    $free_plan = $plan;
                    break;
                }
            }

            if (!$free_plan) {
                debugging('No free Insights plan found for starter subscription');
                return;
            }

            debugging('Found free plan: ' . $free_plan['name']);
            
            // Activate free plan via backend
            $subscription_response = $this->make_api_request('subscription/activate-free', [
                'plan_id' => $free_plan['id'],
                'billing_email' => $email
            ]);
            
            if ($subscription_response && isset($subscription_response['success']) && $subscription_response['success']) {
                debugging('Starter subscription created successfully');
                
                // Update local subscription status
                $this->update_subscription_status([
                    'stripe_customer_id' => $subscription_response['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $subscription_response['data']['subscription_id'] ?? null,
                    'plan_name' => $free_plan['name'],
                    'plan_id' => $free_plan['id'],
                    'status' => $subscription_response['data']['status'] ?? 'active',
                    'current_period_start' => $subscription_response['data']['current_period_start'] ?? time(),
                    'current_period_end' => $subscription_response['data']['current_period_end'] ?? (time() + 30*24*60*60),
                    'ai_credits_remaining' => $subscription_response['data']['ai_credits_remaining'] ?? $free_plan['ai_credits'],
                    'ai_credits_pro_remaining' => $subscription_response['data']['ai_credits_pro_remaining'] ?? ($free_plan['ai_credits_pro'] ?? 0),
                    'ai_credits_basic_remaining' => $subscription_response['data']['ai_credits_basic_remaining'] ?? ($free_plan['ai_credits_basic'] ?? 0),
                    'exports_remaining' => $subscription_response['data']['exports_remaining'] ?? $free_plan['exports'],
                    'billing_email' => $email
                ]);
            } else {
                debugging('Failed to create starter subscription: ' . ($subscription_response['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            debugging('Failed to setup starter subscription: ' . $e->getMessage());
        }
    }
    
    private function set_post_install_notification() {
        global $DB;
        
        // Set a notification for the admin to complete setup
        $notification = [
            'type' => 'success',
            'message' => get_string('registration_complete', 'report_adeptus_insights'),
            'actions' => [
                [
                    'url' => new \moodle_url('/report/adeptus_insights/subscription.php'),
                    'text' => get_string('view_subscription', 'report_adeptus_insights')
                ]
            ]
        ];
        
        // Store notification in session or database
        set_config('adeptus_insights_notification', json_encode($notification), 'report_adeptus_insights');
    }
    
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
            debugging('Failed to verify registration: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if current site is already registered in backend
     */
    public function check_site_registration_status() {
        global $CFG, $DB;
        
        try {
            $site_url = $CFG->wwwroot;
            $site_name = $CFG->fullname ?? $CFG->shortname ?? 'Moodle Site';
            
            // Try to get site name from database if config is not available
            if (empty($site_name) || $site_name === 'Moodle Site') {
                try {
                    $config_record = $DB->get_record('config', ['name' => 'fullname']);
                    if ($config_record && !empty($config_record->value)) {
                        $site_name = $config_record->value;
                    } else {
                        $config_record = $DB->get_record('config', ['name' => 'shortname']);
                        if ($config_record && !empty($config_record->value)) {
                            $site_name = $config_record->value;
                        }
                    }
                } catch (\Exception $e) {
                    debugging('Could not retrieve site name from database: ' . $e->getMessage());
                }
            }

            $data = [
                'site_url' => $site_url,
                'site_name' => $site_name,
                ];

            debugging('Checking site registration status for: ' . $site_url . ' with site name: ' . $site_name);

            $response = $this->make_api_request('installation/status-by-site', $data);

            debugging('Site registration status response: ' . json_encode($response));

            if ($response && isset($response['success']) && $response['success']) {
                return $response;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            debugging('Failed to check site registration status: ' . $e->getMessage());
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
            debugging('Ensuring all required database tables exist...');
            
            // Check if adeptus_subscription_status table exists
            if (!$DB->table_exists('adeptus_subscription_status')) {
                debugging('Creating adeptus_subscription_status table...');
                $this->create_subscription_status_table();
            }
            
            // Check if adeptus_install_settings table exists
            if (!$DB->table_exists('adeptus_install_settings')) {
                debugging('Creating adeptus_install_settings table...');
                $this->create_install_settings_table();
            }
            
            debugging('All required database tables verified.');
            
        } catch (\Exception $e) {
            debugging('Error ensuring database tables exist: ' . $e->getMessage());
        }
    }
    
    /**
     * Create adeptus_subscription_status table if it doesn't exist
     */
    private function create_subscription_status_table() {
        global $DB;
        
        try {
            $table = new \xmldb_table('adeptus_subscription_status');
            
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
                
                $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
                $table->add_key('installation', XMLDB_KEY_FOREIGN, array('installation_id'), 'adeptus_install_settings', array('id'));
                
                $DB->create_table($table);
                debugging('adeptus_subscription_status table created successfully.');
            }
        } catch (\Exception $e) {
            debugging('Error creating adeptus_subscription_status table: ' . $e->getMessage());
        }
    }
    
    /**
     * Create adeptus_install_settings table if it doesn't exist
     */
    private function create_install_settings_table() {
        global $DB;
        
        try {
            $table = new \xmldb_table('adeptus_install_settings');
            
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
                
                $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
                
                $DB->create_table($table);
                debugging('adeptus_install_settings table created successfully.');
            }
        } catch (\Exception $e) {
            debugging('Error creating adeptus_install_settings table: ' . $e->getMessage());
        }
    }

    
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
    
    private function set_registration_required_notification() {
        $notification = [
            'type' => 'warning',
            'message' => get_string('registration_required', 'report_adeptus_insights'),
            'actions' => [
                [
                    'url' => new \moodle_url('/report/adeptus_insights/subscription.php'),
                    'text' => get_string('register_now', 'report_adeptus_insights')
                ]
            ]
        ];
        
        set_config('adeptus_insights_notification', json_encode($notification), 'report_adeptus_insights');
    }
    
    public function sync_reports_from_backend() {
        if (!$this->is_registered) {
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights')
            ];
        }
        
        try {
            $response = $this->make_api_request('reports/sync', []);
            
            if ($response && isset($response['success']) && $response['success']) {
                $this->update_last_sync();
                return [
                    'success' => true,
                    'message' => get_string('sync_success', 'report_adeptus_insights'),
                    'data' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => get_string('sync_error', 'report_adeptus_insights') . ': ' . ($response['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            debugging('Sync failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => get_string('sync_error', 'report_adeptus_insights') . ': ' . $e->getMessage()
            ];
        }
    }
    
    public function check_subscription_status() {
        if (!$this->is_registered) {
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights')
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
            debugging('Failed to check subscription status: ' . $e->getMessage());
            return false;
        }
    }
    
    public function create_subscription($plan_id, $payment_method_id, $billing_email) {
        if (!$this->is_registered) {
            debugging('Subscription creation failed: Installation not registered');
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights')
            ];
        }
        
        try {
            debugging('Creating subscription with plan_id: ' . $plan_id . ', email: ' . $billing_email);
            
            $request_data = [
                'payment_method_id' => $payment_method_id,
                'billing_email' => $billing_email,
                'plan_id' => $plan_id
            ];
            
            debugging('API request data: ' . json_encode($request_data));
            
            $response = $this->make_api_request('subscription/create', $request_data);
            
            debugging('API response: ' . json_encode($response));
            
            if ($response && isset($response['success']) && $response['success']) {
                // Update local subscription status
                $subscription_data = [
                    'stripe_customer_id' => $response['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $response['data']['subscription_id'] ?? null,
                    'plan_name' => $response['data']['plan_name'] ?? 'Unknown Plan',
                    'plan_id' => $response['data']['plan_id'] ?? $plan_id,
                    'status' => $response['data']['status'] ?? 'active',
                    'current_period_start' => $response['data']['current_period_start'] ?? time(),
                    'current_period_end' => $response['data']['current_period_end'] ?? (time() + 30*24*60*60),
                    'ai_credits_remaining' => $response['data']['ai_credits_remaining'] ?? 0,
                    'ai_credits_pro_remaining' => $response['data']['ai_credits_pro_remaining'] ?? 0,
                    'ai_credits_basic_remaining' => $response['data']['ai_credits_basic_remaining'] ?? 0,
                    'exports_remaining' => $response['data']['exports_remaining'] ?? 0,
                    'billing_email' => $billing_email
                ];
                
                debugging('Updating subscription status with data: ' . json_encode($subscription_data));
                $this->update_subscription_status($subscription_data);
                
                return [
                    'success' => true,
                    'message' => get_string('subscription_created', 'report_adeptus_insights'),
                    'data' => $response['data']
                ];
            } else {
                $error_message = isset($response['message']) ? $response['message'] : 'Unknown error';
                debugging('Subscription creation failed: ' . $error_message);
                return [
                    'success' => false,
                    'message' => get_string('subscription_error', 'report_adeptus_insights') . ': ' . $error_message
                ];
            }
        } catch (\Exception $e) {
            debugging('Subscription creation exception: ' . $e->getMessage());
            debugging('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => get_string('subscription_error', 'report_adeptus_insights') . ': ' . $e->getMessage()
            ];
        }
    }
    
    public function get_subscription_details() {
        try {
            // Get the API key from the local database
            $api_key = $this->get_api_key();
            if (!$api_key) {
                debugging('No API key found for subscription details');
                return null;
            }
            
            // Get subscription details from backend
            $subscription_data = $this->get_backend_subscription_details($api_key);
            if (!$subscription_data) {
                debugging('Failed to get subscription details from backend');
                return null;
            }
            
            return $subscription_data;
            
        } catch (\Exception $e) {
            debugging('Failed to get subscription details: ' . $e->getMessage());
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
    private function get_backend_subscription_details($api_key) {
        $endpoint = 'subscriptions/status';

        $response = $this->make_api_request($endpoint, [], 'GET');
        
        if (!$response || !isset($response['success']) || !$response['success']) {
            debugging('Backend subscription API failed: ' . json_encode($response));
            return null;
        }
        
        $data = $response['data'];
        
        debugging('Backend subscription response data: ' . json_encode($data));
        
        // Transform backend data to match expected format
        $subscription = $data['subscription'];
        $plan = $data['plan'];
        $usage = $data['usage'] ?? [];
        
        debugging('Plan data from backend: ' . json_encode($plan));
        debugging('Plan ID extracted: ' . ($plan['id'] ?? 'NULL'));
        
        // Ensure plan_id is always included, with fallback
        $plan_id = $plan['id'] ?? $data['subscription']['plan_id'] ?? 1;
        
        // Token usage data from API (flattened fields first, then nested as fallback)
        $tokens_used = $data['tokens_used'] ?? 0;
        $tokens_remaining = $data['tokens_remaining'] ?? -1;
        $tokens_limit = $data['tokens_limit'] ?? $usage['token_usage']['tokens_limit'] ?? 50000;

        // Calculate usage percentage
        $tokens_usage_percent = 0;
        if ($tokens_limit > 0 && $tokens_remaining !== -1) {
            $tokens_usage_percent = min(100, round(($tokens_used / $tokens_limit) * 100));
        }

        return [
            'plan_id' => $plan_id,
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
            'status_message' => $subscription['status_message'] ?? 'Active subscription',
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
            'tokens_used' => $tokens_used,
            'tokens_remaining' => $tokens_remaining,
            'tokens_limit' => $tokens_limit,
            'tokens_used_formatted' => $this->format_token_count($tokens_used),
            'tokens_remaining_formatted' => $tokens_remaining === -1 ? 'Unlimited' : $this->format_token_count($tokens_remaining),
            'tokens_limit_formatted' => $tokens_remaining === -1 ? 'Unlimited' : $this->format_token_count($tokens_limit),
            'tokens_usage_percent' => $tokens_usage_percent,
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
    

    
    public function get_payment_config() {
        try {
            debugging('Getting payment configuration from backend API');
            
            $response = $this->make_api_request('subscription/config', [], 'GET');
            
            debugging('Payment config response: ' . json_encode($response));
            
            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']
                ];
            } else {
                debugging('Failed to get payment config: ' . ($response['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to get payment configuration'
                ];
            }
        } catch (\Exception $e) {
            debugging('Failed to get payment config: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment processing is not configured. Please contact support.'
            ];
        }
    }
    
    public function cancel_subscription() {
        try {
            $response = $this->make_api_request('subscription/cancel', []);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => get_string('subscription_cancelled', 'report_adeptus_insights')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to cancel subscription'
                ];
            }
        } catch (\Exception $e) {
            debugging('Failed to cancel subscription: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => get_string('subscription_error', 'report_adeptus_insights') . ': ' . $e->getMessage()
            ];
        }
    }
    
    public function update_subscription_plan($plan_id) {
        try {
            $response = $this->make_api_request('subscription/update', [
                'plan_id' => $plan_id
            ]);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Subscription plan updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to update subscription plan'
                ];
            }
        } catch (\Exception $e) {
            debugging('Failed to update subscription plan: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update subscription plan: ' . $e->getMessage()
            ];
        }

    }
    
    public function get_available_plans() {
        try {
            debugging('Getting available plans from backend API');
            
            // Check if plugin is registered first
            if (!$this->is_registered()) {
                return [
                    'success' => false,
                    'message' => get_string('plugin_not_registered', 'report_adeptus_insights'),
                    'user_friendly_message' => get_string('please_register_plugin', 'report_adeptus_insights'),
                    'plans' => []
                ];
            }
            
            $response = $this->make_api_request('subscription/plans', [], 'GET');
            
            debugging('Plans API response: ' . json_encode($response));
            
            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'plans' => $response['data']['plans'] ?? []
                ];
            } else {
                debugging('Failed to get plans from API: ' . ($response['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'message' => $response['message'] ?? get_string('failed_to_get_plans', 'report_adeptus_insights'),
                    'user_friendly_message' => $this->get_user_friendly_error_message($response['message'] ?? ''),
                    'plans' => []
                ];
            }
        } catch (\Exception $e) {
            debugging('Exception getting plans: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => get_string('failed_to_get_plans', 'report_adeptus_insights') . ': ' . $e->getMessage(),
                'user_friendly_message' => $this->get_user_friendly_error_message($e->getMessage()),
                'plans' => []
            ];
        }
    }
    
    /**
     * Get usage statistics from backend
     */
    public function get_usage_stats() {
        try {
            $api_key = $this->get_api_key();
            if (!$api_key) {
                debugging('No API key found for usage stats');
                return null;
            }
            
            $response = $this->make_api_request('installation/status', [], 'POST');
            
            if (!$response || !isset($response['success']) || !$response['success']) {
                debugging('Backend usage API failed: ' . json_encode($response));
                return null;
            }
            
            $data = $response['data'];
            $subscription = $data['subscription'];
            
            if (!$subscription) {
                return [
                    'ai_credits_used_this_month' => 0,
                    'reports_generated_this_month' => 0,
                    'current_period_start' => null,
                    'current_period_end' => null
                ];
            }
            
            // Extract usage from the backend response structure
            $usage = $data['usage'] ?? [];
            
            return [
                'ai_credits_used_this_month' => $usage['ai_credits_used_this_month'] ?? 0,
                'reports_generated_this_month' => $usage['reports_generated_this_month'] ?? 0,
                'current_period_start' => $usage['current_period_start'] ?? null,
                'current_period_end' => $usage['current_period_end'] ?? null
            ];
            
        } catch (\Exception $e) {
            debugging('Failed to get usage stats: ' . $e->getMessage());
            return null;
        }
    }
    
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
    
    public function can_export() {
        $subscription = $this->get_subscription_details();
        if (!$subscription) {
            return false;
        }
        
        return $subscription->exports_remaining > 0;
    }
    

    
    /**
     * Activate free plan
     * @param int $plan_id Plan ID
     * @return array Activation result
     */
    public function activate_free_plan($plan_id) {
        if (!$this->is_registered) {
            return [
                'success' => false,
                'message' => get_string('not_registered', 'report_adeptus_insights')
            ];
        }
        
        try {
            debugging('Activating free plan with ID: ' . $plan_id);
            
            $response = $this->make_api_request('subscription/activate-free', [
                'plan_id' => $plan_id
            ]);
            
            debugging('Free plan activation response: ' . json_encode($response));
            
            if ($response && isset($response['success']) && $response['success']) {
                // Update local subscription status
                $subscription_data = [
                    'stripe_customer_id' => $response['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $response['data']['subscription_id'] ?? null,
                    'plan_name' => $response['data']['plan_name'] ?? 'Free Plan',
                    'plan_id' => $response['data']['plan_id'] ?? $plan_id,
                    'status' => $response['data']['status'] ?? 'active',
                    'current_period_start' => $response['data']['current_period_start'] ?? time(),
                    'current_period_end' => $response['data']['current_period_end'] ?? (time() + 30*24*60*60),
                    'ai_credits_remaining' => $response['data']['ai_credits_remaining'] ?? 0,
                    'ai_credits_pro_remaining' => $response['data']['ai_credits_pro_remaining'] ?? 0,
                    'ai_credits_basic_remaining' => $response['data']['ai_credits_basic_remaining'] ?? 0,
                    'exports_remaining' => $response['data']['exports_remaining'] ?? 0,
                    'billing_email' => $response['data']['billing_email'] ?? ''
                ];
                
                debugging('Updating subscription status with data: ' . json_encode($subscription_data));
                $this->update_subscription_status($subscription_data);
                
                return [
                    'success' => true,
                    'message' => 'Free plan activated successfully'
                ];
            } else {
                $error_message = isset($response['message']) ? $response['message'] : 'Unknown error';
                debugging('Free plan activation failed: ' . $error_message);
                return [
                    'success' => false,
                    'message' => 'Failed to activate free plan: ' . $error_message
                ];
            }
        } catch (\Exception $e) {
            debugging('Free plan activation exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to activate free plan: ' . $e->getMessage()
            ];
        }
    }
    
    public function make_api_request($endpoint, $data = [], $method = 'POST') {
        $url = $this->api_url . '/' . $endpoint;
        
        debugging('Making API request to: ' . $url);
        debugging('Request method: ' . $method);
        debugging('Request data: ' . json_encode($data));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Only set POSTFIELDS for non-GET requests
        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Add API key to headers for authenticated endpoints
        $headers = ['Content-Type: application/json'];
        if ($this->api_key && !in_array($endpoint, ['subscription/config'])) {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
            debugging('Adding API key (Bearer token) to headers for endpoint: ' . $endpoint);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        // Capture verbose output for debugging
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // Get verbose output
        rewind($verbose);
        $verbose_log = stream_get_contents($verbose);
        fclose($verbose);
        
        debugging('CURL verbose log: ' . $verbose_log);
        debugging('HTTP response code: ' . $http_code);
        debugging('CURL error: ' . $error);
        debugging('Response: ' . $response);
        
        curl_close($ch);
        
        if ($response === false) {
            debugging('API request failed: ' . $error . ' (URL: ' . $url . ')');
            throw new \Exception('API request failed: ' . $error . ' (URL: ' . $url . ')');
        }
        
        if ($http_code !== 200) {
            debugging('API request failed: HTTP ' . $http_code . ' - Response: ' . $response . ' (URL: ' . $url . ')');
            throw new \Exception('API request failed: HTTP ' . $http_code . ' - Response: ' . $response . ' (URL: ' . $url . ')');
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('Invalid JSON response: ' . json_last_error_msg() . ' - Response: ' . $response);
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg() . ' - Response: ' . $response);
        }
        
        debugging('Decoded response: ' . json_encode($decoded));
        return $decoded;
    }
    
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
                'timemodified' => time()
            ];

            // Check if table exists
            if (!$DB->get_manager()->table_exists('adeptus_install_settings')) {
                debugging('adeptus_install_settings table does not exist, it should be created by install.xml');
                return;
            }

            // Try to find ANY existing record (not just id=1)
            $existing = $DB->get_record('adeptus_install_settings', [], '*', IGNORE_MULTIPLE);

            if ($existing) {
                // Update existing record
                $record->id = $existing->id;
                $DB->update_record('adeptus_install_settings', $record);
                debugging('Updated existing installation settings record with id: ' . $existing->id);
            } else {
                // Insert new record - let Moodle auto-generate the ID
                $newid = $DB->insert_record('adeptus_install_settings', $record);
                debugging('Inserted new installation settings record with id: ' . $newid);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the registration
            debugging('Failed to save installation settings: ' . $e->getMessage());
            debugging('Exception trace: ' . $e->getTraceAsString());
        }
    }
    
    private function update_last_sync() {
        global $DB;

        try {
            // Find the existing record
            $existing = $DB->get_record('adeptus_install_settings', [], '*', IGNORE_MULTIPLE);
            if ($existing) {
                $DB->set_field('adeptus_install_settings', 'last_sync', time(), ['id' => $existing->id]);
            }
        } catch (\Exception $e) {
            debugging('Failed to update last sync: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert technical error messages to user-friendly messages
     */
    private function get_user_friendly_error_message($technical_message) {
        $message = strtolower($technical_message);
        
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
    
    private function update_subscription_status($subscription_data) {
        global $DB;

        try {
            // Check if table exists
            if (!$DB->get_manager()->table_exists('adeptus_subscription_status')) {
                debugging('adeptus_subscription_status table does not exist, it should be created by install.xml');
                return;
            }

            $record = (object)[
                'stripe_customer_id' => $subscription_data['stripe_customer_id'] ?? null,
                'stripe_subscription_id' => $subscription_data['stripe_subscription_id'] ?? null,
                'plan_name' => $subscription_data['plan_name'] ?? 'Unknown',
                'plan_id' => $subscription_data['plan_id'] ?? null,
                'status' => $subscription_data['status'] ?? 'unknown',
                'current_period_start' => $subscription_data['current_period_start'] ?? null,
                'current_period_end' => $subscription_data['current_period_end'] ?? null,
                'ai_credits_remaining' => $subscription_data['ai_credits_remaining'] ?? 0,
                'ai_credits_pro_remaining' => $subscription_data['ai_credits_pro_remaining'] ?? 0,
                'ai_credits_basic_remaining' => $subscription_data['ai_credits_basic_remaining'] ?? 0,
                'exports_remaining' => $subscription_data['exports_remaining'] ?? 0,
                'billing_email' => $subscription_data['billing_email'] ?? null,
                'last_updated' => time()
            ];

            // Find ANY existing record (not just id=1)
            $existing = $DB->get_record('adeptus_subscription_status', [], '*', IGNORE_MULTIPLE);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('adeptus_subscription_status', $record);
                debugging('Updated subscription status record with id: ' . $existing->id);
            } else {
                $newid = $DB->insert_record('adeptus_subscription_status', $record);
                debugging('Inserted subscription status record with id: ' . $newid);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the operation
            debugging('Failed to update subscription status: ' . $e->getMessage());
            debugging('Exception trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Activate free plan manually (fallback method)
     */
    public function activate_free_plan_manually() {
        try {
            debugging('Attempting manual free plan activation');
            
            // Get available plans from backend
            $plans_response = $this->make_api_request('subscription/plans', [], 'GET');
            
            if (!$plans_response || !isset($plans_response['success']) || !$plans_response['success']) {
                debugging('Failed to get plans for manual free plan activation');
                return false;
            }
            
            // Find free plan for Insights product
            $free_plan = null;
            foreach ($plans_response['data']['plans'] as $plan) {
                $is_free = (isset($plan['tier']) && $plan['tier'] === 'free') ||
                           (isset($plan['is_free']) && $plan['is_free']);
                $is_insights = (isset($plan['product_key']) && $plan['product_key'] === 'insights');

                if ($is_free && $is_insights) {
                    $free_plan = $plan;
                    break;
                }
            }

            if (!$free_plan) {
                debugging('No free Insights plan found for manual activation');
                return false;
            }

            debugging('Found free plan for manual activation: ' . $free_plan['name']);
            
            // Activate free plan via backend
            $subscription_response = $this->make_api_request('subscription/activate-free', [
                'plan_id' => $free_plan['id'],
                'billing_email' => $this->get_admin_email()
            ]);
            
            if ($subscription_response && isset($subscription_response['success']) && $subscription_response['success']) {
                debugging('Manual free plan activation successful');
                
                // Update local subscription status
                $this->update_subscription_status([
                    'stripe_customer_id' => $subscription_response['data']['customer_id'] ?? null,
                    'stripe_subscription_id' => $subscription_response['data']['subscription_id'] ?? null,
                    'plan_name' => $free_plan['name'],
                    'plan_id' => $free_plan['id'],
                    'status' => $subscription_response['data']['status'] ?? 'active',
                    'current_period_start' => $subscription_response['data']['current_period_start'] ?? time(),
                    'current_period_end' => $subscription_response['data']['current_period_end'] ?? (time() + 30*24*60*60),
                    'ai_credits_remaining' => $subscription_response['data']['ai_credits_remaining'] ?? $free_plan['ai_credits'],
                    'ai_credits_pro_remaining' => $subscription_response['data']['ai_credits_pro_remaining'] ?? ($free_plan['ai_credits_pro'] ?? 0),
                    'ai_credits_basic_remaining' => $subscription_response['data']['ai_credits_basic_remaining'] ?? ($free_plan['ai_credits_basic'] ?? 0),
                    'exports_remaining' => $subscription_response['data']['exports_remaining'] ?? $free_plan['exports'],
                    'billing_email' => $this->get_admin_email()
                ]);
                
                return true;
            } else {
                debugging('Failed to manually activate free plan: ' . ($subscription_response['message'] ?? 'Unknown error'));
                return false;
            }
            
        } catch (\Exception $e) {
            debugging('Failed to manually activate free plan: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create billing portal session for upgrades
     */
    public function create_billing_portal_session($return_url = null, $plan_id = null, $action = null) {
        try {
            debugging('Creating billing portal session for upgrade');

            $data = [
                'return_url' => $return_url ?: $this->get_site_url()
            ];

            if ($plan_id) {
                $data['plan_id'] = $plan_id;
            }

            if ($action) {
                $data['action'] = $action;
            }

            // Check if user has a Stripe customer - if not, request customer creation
            $subscription = $this->get_subscription_details();
            $stripe_customer_id = $subscription['stripe_customer_id'] ?? null;
            if (!$stripe_customer_id) {
                debugging('No Stripe customer found, requesting customer creation');
                $data['create_customer'] = true;
            }

            $response = $this->make_api_request('subscription/billing-portal', $data);
            
            // Log to file for debugging
            error_log('[BILLING_PORTAL] Response: ' . json_encode($response));
            error_log('[BILLING_PORTAL] Success: ' . ($response['success'] ?? 'NOT_SET'));
            error_log('[BILLING_PORTAL] Data: ' . json_encode($response['data'] ?? 'NOT_SET'));
            error_log('[BILLING_PORTAL] URL in data.url: ' . ($response['data']['url'] ?? 'NOT_FOUND'));
            error_log('[BILLING_PORTAL] URL in data.billing_portal_url: ' . ($response['data']['billing_portal_url'] ?? 'NOT_FOUND'));
            
            debugging('Billing portal session response: ' . json_encode($response));
            debugging('Response success: ' . ($response['success'] ?? 'NOT_SET'));
            debugging('Response data: ' . json_encode($response['data'] ?? 'NOT_SET'));
            debugging('URL in data.url: ' . ($response['data']['url'] ?? 'NOT_FOUND'));
            debugging('URL in data.billing_portal_url: ' . ($response['data']['billing_portal_url'] ?? 'NOT_FOUND'));
            
            if ($response && isset($response['success']) && $response['success']) {
                $url = $response['data']['url'] ?? $response['data']['billing_portal_url'] ?? null;
                debugging('Final extracted URL: ' . ($url ?? 'NULL'));
                
                return [
                    'success' => true,
                    'data' => [
                        'url' => $url
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to create billing portal session'
                ];
            }
            
        } catch (\Exception $e) {
            debugging('Failed to create billing portal session: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create billing portal session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create Stripe Checkout session for new subscriptions
     *
     * @param int $plan_id The plan ID to subscribe to
     * @param string $stripe_price_id Optional Stripe price ID
     * @param string $return_url URL to return to after checkout
     * @return array Response with checkout_url on success
     */
    public function create_checkout_session($plan_id, $stripe_price_id = null, $return_url = null) {
        try {
            debugging('Creating checkout session for plan: ' . $plan_id);

            $data = [
                'plan_id' => $plan_id,
                'success_url' => $return_url ?: $this->get_site_url(),
                'cancel_url' => $return_url ?: $this->get_site_url()
            ];

            if ($stripe_price_id) {
                $data['stripe_price_id'] = $stripe_price_id;
            }

            $response = $this->make_api_request('subscription/checkout', $data);

            error_log('[CHECKOUT] Response: ' . json_encode($response));

            if ($response && isset($response['success']) && $response['success']) {
                $checkout_url = $response['data']['checkout_url'] ?? null;
                debugging('Checkout session created, URL: ' . ($checkout_url ?? 'NULL'));

                return [
                    'success' => true,
                    'checkout_url' => $checkout_url,
                    'session_id' => $response['data']['session_id'] ?? null
                ];
            } else {
                $error_code = $response['error']['code'] ?? '';
                $error_message = $response['error']['message'] ?? ($response['message'] ?? 'Failed to create checkout session');

                return [
                    'success' => false,
                    'error_code' => $error_code,
                    'message' => $error_message
                ];
            }

        } catch (\Exception $e) {
            debugging('Failed to create checkout session: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create checkout session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify a completed checkout session and update subscription
     *
     * @param string $session_id Stripe checkout session ID
     * @return array Response with subscription details on success
     */
    public function verify_checkout_session($session_id) {
        try {
            debugging('Verifying checkout session: ' . $session_id);

            $response = $this->make_api_request('subscription/verify-checkout', [
                'session_id' => $session_id
            ]);

            error_log('[VERIFY_CHECKOUT] Response: ' . json_encode($response));

            if ($response && isset($response['success']) && $response['success']) {
                debugging('Checkout verified successfully, tier: ' . ($response['data']['tier'] ?? 'unknown'));

                // Clear local subscription cache to fetch fresh data
                $this->clear_subscription_cache();

                return [
                    'success' => true,
                    'subscription_id' => $response['data']['subscription_id'] ?? null,
                    'tier' => $response['data']['tier'] ?? 'pro',
                    'status' => $response['data']['status'] ?? 'active',
                    'plan_name' => $response['data']['plan_name'] ?? 'Pro'
                ];
            } else {
                $error_code = $response['error']['code'] ?? '';
                $error_message = $response['error']['message'] ?? ($response['message'] ?? 'Failed to verify checkout');

                return [
                    'success' => false,
                    'error_code' => $error_code,
                    'message' => $error_message
                ];
            }

        } catch (\Exception $e) {
            debugging('Failed to verify checkout session: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to verify checkout session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clear local subscription cache
     */
    public function clear_subscription_cache() {
        $cache = \cache::make('report_adeptus_insights', 'subscription_data');
        $cache->delete('subscription_details');
    }

    /**
     * Create billing portal session for specific product upgrade/downgrade
     */
    public function create_product_portal_session($product_id, $return_url) {
        try {
            // Check if installation is registered
            if (!$this->is_registered()) {
                return ['success' => false, 'message' => 'Installation not registered'];
            }
            
            // Get the subscription details
            $subscription = $this->get_subscription_details();
            if (!$subscription) {
                return ['success' => false, 'message' => 'Subscription not found'];
            }
            
            // Get the target plan from the product ID
            $plan = $this->get_plan_by_stripe_product_id($product_id);
            if (!$plan) {
                return ['success' => false, 'message' => 'Target plan not found'];
            }
            
            // Create or get Stripe customer
            $stripe_customer_id = $subscription['stripe_customer_id'] ?? null;
            if (!$stripe_customer_id) {
                // Create customer and subscription if they don't exist
                $customer_result = $this->create_stripe_customer_and_subscription($subscription, $plan);
                if (!$customer_result['success']) {
                    return $customer_result;
                }
                $stripe_customer_id = $customer_result['stripe_customer_id'];
            }
            
            // Create billing portal session with return URL
            $portal_result = $this->create_stripe_portal_session($stripe_customer_id, $return_url);
            if (!$portal_result['success']) {
                return $portal_result;
            }
            
            return [
                'success' => true,
                'portal_url' => $portal_result['portal_url']
            ];
            
        } catch (\Exception $e) {
            error_log('Error creating product portal session: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating portal session: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get plan by Stripe product ID
     */
    private function get_plan_by_stripe_product_id($stripe_product_id) {
        // Get available plans from backend API
        $response = $this->get_available_plans();
        
        // Check if the API call was successful
        if (!$response || !isset($response['success']) || !$response['success']) {
            debugging('Failed to get available plans: ' . ($response['message'] ?? 'Unknown error'));
            return null;
        }
        
        // Get the plans array from the response
        $plans = $response['plans'] ?? [];
        
        debugging('Looking for product ID: ' . $stripe_product_id . ' in ' . count($plans) . ' plans');
        
        foreach ($plans as $plan) {
            debugging('Checking plan: ' . json_encode($plan));
            if (isset($plan['stripe_product_id']) && $plan['stripe_product_id'] === $stripe_product_id) {
                debugging('Found matching plan: ' . json_encode($plan));
                return $plan;
            }
        }
        
        debugging('No plan found with product ID: ' . $stripe_product_id);
        return null;
    }
    
    /**
     * Get admin email for subscription creation
     */
    private function get_admin_email() {
        global $USER;
        return $USER->email ?? '';
    }
    
    /**
     * Get site URL for return redirects
     */
    private function get_site_url() {
        global $CFG;
        return $CFG->wwwroot ?? '';
    }
    
    /**
     * Create Stripe customer and subscription
     */
    private function create_stripe_customer_and_subscription($subscription, $plan) {
        try {
            // Call backend API to create customer and subscription
            $response = $this->make_api_request('subscription/billing-portal', [
                'return_url' => $this->get_site_url(),
                'create_customer' => true,
                'plan_id' => $plan['id'] ?? null
            ]);
            
            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'stripe_customer_id' => $response['data']['stripe_customer_id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to create Stripe customer'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating Stripe customer: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create Stripe portal session
     */
    private function create_stripe_portal_session($stripe_customer_id, $return_url) {
        try {
            // Call backend API to create portal session
            $response = $this->make_api_request('subscription/billing-portal', [
                'return_url' => $return_url,
                'stripe_customer_id' => $stripe_customer_id
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'portal_url' => $response['data']['url'] ?? $response['data']['billing_portal_url'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to create portal session'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating portal session: ' . $e->getMessage()
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
                debugging('Failed to get subscription with usage: ' . json_encode($response));
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
            debugging('Exception getting subscription with usage: ' . $e->getMessage());
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
     * @param string $report_key The report key to check
     * @return array Result with 'allowed', 'reason', 'message' keys
     */
    public function check_report_access($report_key) {
        try {
            if (!$this->is_registered()) {
                // For unregistered installations, allow free tier reports only
                return [
                    'allowed' => true, // Allow generation but track locally
                    'reason' => 'unregistered',
                    'message' => 'Installation not registered - using free tier defaults',
                    'tier' => 'free'
                ];
            }

            // Call backend API to check report access
            $response = $this->make_api_request('v1/reports/check-access', [
                'report_key' => $report_key
            ]);

            if (!$response || !isset($response['success'])) {
                debugging('Failed to check report access: ' . json_encode($response));
                return [
                    'allowed' => true, // Fail open for now
                    'reason' => 'api_error',
                    'message' => 'Could not verify access - allowing by default',
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
                'message' => $response['data']['message'] ?? 'Access denied',
            ];

        } catch (\Exception $e) {
            debugging('Exception checking report access: ' . $e->getMessage());
            return [
                'allowed' => true, // Fail open
                'reason' => 'exception',
                'message' => 'Could not verify access - allowing by default',
            ];
        }
    }

    /**
     * Track report generation after successful execution
     *
     * @param string $report_key The report key that was generated
     * @param bool $is_ai_generated Whether this is an AI-generated report
     * @return bool Success status
     */
    public function track_report_generation($report_key, $is_ai_generated = false) {
        try {
            if (!$this->is_registered()) {
                debugging('Cannot track report generation - not registered');
                return false;
            }

            $response = $this->make_api_request('v1/usage/track-report', [
                'report_key' => $report_key,
                'is_ai_generated' => $is_ai_generated,
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                debugging('Report generation tracked: ' . $report_key);
                return true;
            }

            debugging('Failed to track report generation: ' . json_encode($response));
            return false;

        } catch (\Exception $e) {
            debugging('Exception tracking report generation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Track report deletion (decrements usage count)
     *
     * @param string $report_key The report key that was deleted
     * @param bool $is_ai_generated Whether this was an AI-generated report
     * @return bool Success status
     */
    public function track_report_deletion($report_key, $is_ai_generated = false) {
        try {
            if (!$this->is_registered()) {
                return false;
            }

            $response = $this->make_api_request('v1/usage/track-report-deletion', [
                'report_key' => $report_key,
                'is_ai_generated' => $is_ai_generated,
            ]);

            return $response && isset($response['success']) && $response['success'];

        } catch (\Exception $e) {
            debugging('Exception tracking report deletion: ' . $e->getMessage());
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
            $allowed_formats = $subscription['export_formats'] ?? ['pdf'];
            if (!in_array(strtolower($format), array_map('strtolower', $allowed_formats))) {
                return [
                    'allowed' => false,
                    'reason' => 'format_restricted',
                    'message' => "Export to {$format} requires a higher tier plan",
                    'allowed_formats' => $allowed_formats,
                    'upgrade_required' => true,
                ];
            }

            // Check export limit
            $exports_remaining = $subscription['exports_remaining'] ?? 0;
            if ($exports_remaining !== -1 && $exports_remaining <= 0) {
                return [
                    'allowed' => false,
                    'reason' => 'limit_reached',
                    'message' => 'Monthly export limit reached',
                    'remaining' => 0,
                    'limit' => $subscription['exports_limit'] ?? 3,
                    'upgrade_required' => true,
                ];
            }

            return [
                'allowed' => true,
                'remaining' => $exports_remaining,
                'format' => $format,
            ];

        } catch (\Exception $e) {
            debugging('Exception checking export access: ' . $e->getMessage());
            return [
                'allowed' => true, // Fail open
                'reason' => 'exception',
                'message' => 'Could not verify access - allowing by default',
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

            $response = $this->make_api_request('v1/usage/track-export', [
                'format' => $format,
            ]);

            return $response && isset($response['success']) && $response['success'];

        } catch (\Exception $e) {
            debugging('Exception tracking export: ' . $e->getMessage());
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

            $response = $this->make_api_request('v1/usage/track-ai-credits', [
                'type' => $type,
                'amount' => $amount,
            ]);

            return $response && isset($response['success']) && $response['success'];

        } catch (\Exception $e) {
            debugging('Exception tracking AI credits: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is within a specific limit
     *
     * @param string $limit_type The limit type to check ('reports_total', 'exports', 'ai_credits_basic', 'ai_credits_premium')
     * @return bool True if within limit
     */
    public function is_within_limit($limit_type) {
        $subscription = $this->get_subscription_with_usage();

        switch ($limit_type) {
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
     * @param string $limit_type The limit type
     * @return int Remaining quota (-1 for unlimited)
     */
    public function get_remaining_quota($limit_type) {
        $subscription = $this->get_subscription_with_usage();

        switch ($limit_type) {
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
} 