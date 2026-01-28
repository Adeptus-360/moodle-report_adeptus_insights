<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Error Handler for Adeptus Insights.
 *
 * Provides professional error messages and user-friendly error handling.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Error handler class for professional error management.
 *
 * Provides user-friendly error messages and recovery suggestions.
 */
class error_handler {
    /** @var array Error code definitions. */
    private $errorcodes;

    /** @var array Recovery action suggestions. */
    private $recoveryactions;

    /** @var array Admin contact information. */
    private $admincontact;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->initialize_error_codes();
        $this->initialize_recovery_actions();
        $this->initialize_admin_contact();
    }

    /**
     * Initialize error codes and their user-friendly messages
     */
    private function initialize_error_codes() {
        $this->error_codes = [
            'MISSING_HEADERS' => [
                'title' => get_string('errorhandler_missing_headers_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_missing_headers_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_missing_headers_user', 'report_adeptus_insights'),
                'severity' => 'error',
                'recovery' => 'refresh_page',
            ],

            'INVALID_API_KEY_FORMAT' => [
                'title' => get_string('errorhandler_invalid_api_key_format_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_invalid_api_key_format_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_invalid_api_key_format_user', 'report_adeptus_insights'),
                'severity' => 'error',
                'recovery' => 'contact_admin',
            ],

            'INVALID_API_KEY' => [
                'title' => get_string('errorhandler_invalid_api_key_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_invalid_api_key_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_invalid_api_key_user', 'report_adeptus_insights'),
                'severity' => 'error',
                'recovery' => 'contact_admin',
            ],

            'SITE_URL_MISMATCH' => [
                'title' => get_string('errorhandler_site_url_mismatch_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_site_url_mismatch_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_site_url_mismatch_user', 'report_adeptus_insights'),
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'UNAUTHORIZED_USER' => [
                'title' => get_string('errorhandler_unauthorized_user_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_unauthorized_user_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_unauthorized_user_user', 'report_adeptus_insights'),
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'SUBSCRIPTION_INACTIVE' => [
                'title' => get_string('errorhandler_subscription_inactive_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_subscription_inactive_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_subscription_inactive_user', 'report_adeptus_insights'),
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'INSUFFICIENT_TOKENS' => [
                'title' => get_string('errorhandler_insufficient_tokens_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_insufficient_tokens_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_insufficient_tokens_user', 'report_adeptus_insights'),
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'BACKEND_CONNECTION_FAILED' => [
                'title' => get_string('errorhandler_backend_connection_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_backend_connection_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_backend_connection_user', 'report_adeptus_insights'),
                'severity' => 'warning',
                'recovery' => 'retry_later',
            ],

            'VALIDATION_ERROR' => [
                'title' => get_string('errorhandler_authentication_error_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_authentication_error_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_authentication_error_user', 'report_adeptus_insights'),
                'severity' => 'error',
                'recovery' => 'refresh_page',
            ],

            'DATABASE_ERROR' => [
                'title' => get_string('errorhandler_database_error_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_database_error_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_database_error_user', 'report_adeptus_insights'),
                'severity' => 'error',
                'recovery' => 'retry_later',
            ],

            'UNKNOWN_ERROR' => [
                'title' => get_string('errorhandler_unknown_error_title', 'report_adeptus_insights'),
                'message' => get_string('errorhandler_unknown_error_message', 'report_adeptus_insights'),
                'user_message' => get_string('errorhandler_unknown_error_user', 'report_adeptus_insights'),
                'severity' => 'error',
                'recovery' => 'contact_admin',
            ],
        ];
    }

    /**
     * Initialize recovery actions
     */
    private function initialize_recovery_actions() {
        $this->recovery_actions = [
            'refresh_page' => [
                'action' => 'refresh_page',
                'label' => get_string('errorhandler_refresh_page', 'report_adeptus_insights'),
                'description' => get_string('errorhandler_refresh_page_desc', 'report_adeptus_insights'),
                'icon' => 'fa-refresh',
                'button_class' => 'btn-primary',
            ],

            'contact_admin' => [
                'action' => 'contact_admin',
                'label' => get_string('errorhandler_contact_admin', 'report_adeptus_insights'),
                'description' => get_string('errorhandler_contact_admin_desc', 'report_adeptus_insights'),
                'icon' => 'fa-envelope',
                'button_class' => 'btn-info',
            ],

            'retry_later' => [
                'action' => 'retry_later',
                'label' => get_string('errorhandler_retry_later', 'report_adeptus_insights'),
                'description' => get_string('errorhandler_retry_later_desc', 'report_adeptus_insights'),
                'icon' => 'fa-clock-o',
                'button_class' => 'btn-secondary',
            ],

            'check_documentation' => [
                'action' => 'check_documentation',
                'label' => get_string('errorhandler_view_documentation', 'report_adeptus_insights'),
                'description' => get_string('errorhandler_view_documentation_desc', 'report_adeptus_insights'),
                'icon' => 'fa-book',
                'button_class' => 'btn-outline-info',
            ],
        ];
    }

    /**
     * Initialize admin contact information
     */
    private function initialize_admin_contact() {
        global $CFG;

        $this->admin_contact = [
            'email' => get_config('report_adeptus_insights', 'notification_email') ?: 'admin@' . parse_url($CFG->wwwroot, PHP_URL_HOST),
            'support_url' => get_config('report_adeptus_insights', 'support_url') ?: null,
            'documentation_url' => get_config('report_adeptus_insights', 'documentation_url') ?: '/report/adeptus_insights/docs/',
            'phone' => get_config('report_adeptus_insights', 'support_phone') ?: null,
        ];
    }

    /**
     * Get error information by error code
     *
     * @param string $errorcode The error code
     * @return array|null Error information or null if not found
     */
    public function get_error_info($errorcode) {
        return $this->error_codes[$errorcode] ?? $this->error_codes['UNKNOWN_ERROR'];
    }

    /**
     * Get recovery action information
     *
     * @param string $actionkey The recovery action key
     * @return array|null Recovery action information or null if not found
     */
    public function get_recovery_action($actionkey) {
        return $this->recovery_actions[$actionkey] ?? null;
    }

    /**
     * Get admin contact information
     *
     * @return array Admin contact information
     */
    public function get_admin_contact() {
        return $this->admin_contact;
    }

    /**
     * Create a professional error message
     *
     * @param string $errorcode The error code
     * @param array $additionaldata Additional error data
     * @return array Formatted error message
     */
    public function create_error_message($errorcode, $additionaldata = []) {
        $errorinfo = $this->get_error_info($errorcode);
        $recoveryaction = $this->get_recovery_action($errorinfo['recovery']);

        $errormessage = [
            'error_code' => $errorcode,
            'title' => $errorinfo['title'],
            'message' => $errorinfo['message'],
            'user_message' => $errorinfo['user_message'],
            'severity' => $errorinfo['severity'],
            'recovery_action' => $recoveryaction,
            'admin_contact' => $this->admin_contact,
            'timestamp' => time(),
            'additional_data' => $additionaldata,
            'suggestions' => $this->get_suggestions($errorcode, $additionaldata),
        ];

        return $errormessage;
    }

    /**
     * Get suggestions for resolving the error
     *
     * @param string $errorcode The error code
     * @param array $additionaldata Additional error data
     * @return array Array of suggestions
     */
    private function get_suggestions($errorcode, $additionaldata = []) {
        $suggestions = [];

        switch ($errorcode) {
            case 'MISSING_HEADERS':
                $suggestions = [
                    get_string('errorhandler_suggestion_check_config', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_verify_settings', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_logout_login', 'report_adeptus_insights'),
                ];
                break;

            case 'INVALID_API_KEY':
            case 'INVALID_API_KEY_FORMAT':
                $suggestions = [
                    get_string('errorhandler_suggestion_api_key_corrupted', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_contact_admin_regen_key', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_plugin_updated', 'report_adeptus_insights'),
                ];
                break;

            case 'SITE_URL_MISMATCH':
                $suggestions = [
                    get_string('errorhandler_suggestion_site_moved', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_check_site_url', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_contact_admin_update', 'report_adeptus_insights'),
                ];
                break;

            case 'UNAUTHORIZED_USER':
                $suggestions = [
                    get_string('errorhandler_suggestion_check_permissions', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_check_role', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_contact_admin_access', 'report_adeptus_insights'),
                ];
                break;

            case 'SUBSCRIPTION_INACTIVE':
                $suggestions = [
                    get_string('errorhandler_suggestion_check_subscription', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_payment_info', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_contact_admin_renew', 'report_adeptus_insights'),
                ];
                break;

            case 'INSUFFICIENT_TOKENS':
                $suggestions = [
                    get_string('errorhandler_suggestion_purchase_tokens', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_check_token_balance', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_contact_admin_tokens', 'report_adeptus_insights'),
                ];
                break;

            case 'BACKEND_CONNECTION_FAILED':
                $suggestions = [
                    get_string('errorhandler_suggestion_check_connection', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_service_unavailable', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_try_again', 'report_adeptus_insights'),
                ];
                break;

            default:
                $suggestions = [
                    get_string('errorhandler_suggestion_refresh_page', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_check_issue_persists', 'report_adeptus_insights'),
                    get_string('errorhandler_suggestion_contact_admin', 'report_adeptus_insights'),
                ];
        }

        return $suggestions;
    }

    /**
     * Log error for debugging purposes
     *
     * @param string $errorcode The error code
     * @param array $additionaldata Additional error data
     * @param string $usercontext User context information
     */
    public function log_error($errorcode, $additionaldata = [], $usercontext = '') {
        global $USER;

        $errorinfo = $this->get_error_info($errorcode);

        $logdata = [
            'error_code' => $errorcode,
            'error_title' => $errorinfo['title'],
            'error_message' => $errorinfo['message'],
            'user_id' => $USER->id ?? 0,
            'user_email' => $USER->email ?? '',
            'user_context' => $usercontext,
            'additional_data' => $additionaldata,
            'timestamp' => time(),
            'ip_address' => getremoteaddr(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? clean_param($_SERVER['HTTP_USER_AGENT'], PARAM_TEXT) : '',
        ];

        // Log to Moodle's debugging system

        // Log to plugin-specific log if enabled
        if (get_config('report_adeptus_insights', 'enable_error_logging')) {
            $this->write_to_plugin_log($logdata);
        }
    }

    /**
     * Write error to plugin-specific log file
     *
     * @param array $logdata Log data to write
     */
    private function write_to_plugin_log($logdata) {
        global $CFG;

        $logdir = $CFG->dataroot . '/adeptus_insights/logs';
        $logfile = $logdir . '/errors.log';

        // Create log directory if it doesn't exist
        if (!is_dir($logdir)) {
            make_upload_directory('adeptus_insights/logs');
        }

        // Format log entry
        $logentry = date('Y-m-d H:i:s') . ' - ' . json_encode($logdata) . PHP_EOL;

        // Write to log file
        file_put_contents($logfile, $logentry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get error statistics for admin dashboard
     *
     * @param int $days Number of days to look back
     * @return array Error statistics
     */
    public function get_error_statistics($days = 7) {
        global $CFG;

        $logfile = $CFG->dataroot . '/adeptus_insights/logs/errors.log';

        if (!file_exists($logfile)) {
            return [
                'total_errors' => 0,
                'errors_by_code' => [],
                'errors_by_user' => [],
                'recent_errors' => [],
            ];
        }

        $logcontent = file_get_contents($logfile);
        $loglines = explode(PHP_EOL, $logcontent);

        $errorsbycode = [];
        $errorsbyuser = [];
        $recenterrors = [];
        $totalerrors = 0;

        $cutofftime = time() - ($days * 24 * 60 * 60);

        foreach ($loglines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $logdata = json_decode(substr($line, strpos($line, ' - ') + 3), true);
            if (!$logdata) {
                continue;
            }

            $totalerrors++;

            // Count by error code
            $errorcode = $logdata['error_code'] ?? 'UNKNOWN';
            $errorsbycode[$errorcode] = ($errorsbycode[$errorcode] ?? 0) + 1;

            // Count by user
            $useremail = $logdata['user_email'] ?? 'unknown';
            $errorsbyuser[$useremail] = ($errorsbyuser[$useremail] ?? 0) + 1;

            // Recent errors
            if ($logdata['timestamp'] >= $cutofftime) {
                $recenterrors[] = $logdata;
            }
        }

        return [
            'total_errors' => $totalerrors,
            'errors_by_code' => $errorsbycode,
            'errors_by_user' => $errorsbyuser,
            'recent_errors' => array_slice($recenterrors, -10), // Last 10 errors
        ];
    }

    /**
     * Clear error logs
     *
     * @return bool True if successful
     */
    public function clear_error_logs() {
        global $CFG;

        $logfile = $CFG->dataroot . '/adeptus_insights/logs/errors.log';

        if (file_exists($logfile)) {
            return unlink($logfile);
        }

        return true;
    }
}
