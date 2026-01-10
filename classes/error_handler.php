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
 * Error Handler for Adeptus Insights
 * Provides professional error messages and user-friendly error handling
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class error_handler {
    private $error_codes;
    private $recovery_actions;
    private $admin_contact;

    public function __construct() {
        $this->initializeErrorCodes();
        $this->initializeRecoveryActions();
        $this->initializeAdminContact();
    }

    /**
     * Initialize error codes and their user-friendly messages
     */
    private function initializeErrorCodes() {
        $this->error_codes = [
            'MISSING_HEADERS' => [
                'title' => 'Missing Authentication Information',
                'message' => 'Required authentication headers are missing from your request.',
                'user_message' => 'The plugin is missing required authentication information. This usually indicates a configuration issue.',
                'severity' => 'error',
                'recovery' => 'refresh_page',
            ],

            'INVALID_API_KEY_FORMAT' => [
                'title' => 'Invalid API Key Format',
                'message' => 'The API key format is invalid or corrupted.',
                'user_message' => 'The plugin\'s API key appears to be corrupted or in an invalid format.',
                'severity' => 'error',
                'recovery' => 'contact_admin',
            ],

            'INVALID_API_KEY' => [
                'title' => 'Invalid API Key',
                'message' => 'The API key is invalid or has been revoked.',
                'user_message' => 'Your plugin\'s API key is no longer valid. This may happen if the key was revoked or expired.',
                'severity' => 'error',
                'recovery' => 'contact_admin',
            ],

            'SITE_URL_MISMATCH' => [
                'title' => 'Site URL Mismatch',
                'message' => 'The site URL does not match the registered installation.',
                'user_message' => 'The current site URL doesn\'t match what\'s registered with the plugin service. This often happens after site migrations or URL changes.',
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'UNAUTHORIZED_USER' => [
                'title' => 'User Not Authorized',
                'message' => 'Your user account is not authorized to access this plugin.',
                'user_message' => 'Your user account doesn\'t have permission to access the Adeptus Insights plugin. Please contact your administrator.',
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'SUBSCRIPTION_INACTIVE' => [
                'title' => 'Subscription Inactive',
                'message' => 'Your subscription is inactive or has expired.',
                'user_message' => 'Your plugin subscription is currently inactive. Please contact your administrator to renew or activate your subscription.',
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'INSUFFICIENT_TOKENS' => [
                'title' => 'Insufficient Tokens',
                'message' => 'You have insufficient tokens for this operation.',
                'user_message' => 'You don\'t have enough tokens to perform this operation. Please contact your administrator to purchase more tokens.',
                'severity' => 'warning',
                'recovery' => 'contact_admin',
            ],

            'BACKEND_CONNECTION_FAILED' => [
                'title' => 'Service Unavailable',
                'message' => 'Unable to connect to the plugin service.',
                'user_message' => 'The plugin service is currently unavailable. This may be a temporary issue. Please try again later.',
                'severity' => 'warning',
                'recovery' => 'retry_later',
            ],

            'VALIDATION_ERROR' => [
                'title' => 'Authentication Error',
                'message' => 'An error occurred during authentication validation.',
                'user_message' => 'There was an error validating your authentication. This may be a temporary system issue.',
                'severity' => 'error',
                'recovery' => 'refresh_page',
            ],

            'DATABASE_ERROR' => [
                'title' => 'System Error',
                'message' => 'A database error occurred during validation.',
                'user_message' => 'A system error occurred while validating your access. Please try again later.',
                'severity' => 'error',
                'recovery' => 'retry_later',
            ],

            'UNKNOWN_ERROR' => [
                'title' => 'Unexpected Error',
                'message' => 'An unexpected error occurred.',
                'user_message' => 'An unexpected error occurred. Please contact your administrator for assistance.',
                'severity' => 'error',
                'recovery' => 'contact_admin',
            ],
        ];
    }

    /**
     * Initialize recovery actions
     */
    private function initializeRecoveryActions() {
        $this->recovery_actions = [
            'refresh_page' => [
                'action' => 'refresh_page',
                'label' => 'Refresh Page',
                'description' => 'Try refreshing the page to resolve temporary issues.',
                'icon' => 'fa-refresh',
                'button_class' => 'btn-primary',
            ],

            'contact_admin' => [
                'action' => 'contact_admin',
                'label' => 'Contact Administrator',
                'description' => 'Contact your plugin administrator for assistance.',
                'icon' => 'fa-envelope',
                'button_class' => 'btn-info',
            ],

            'retry_later' => [
                'action' => 'retry_later',
                'label' => 'Try Again Later',
                'description' => 'Wait a few minutes and try again.',
                'icon' => 'fa-clock-o',
                'button_class' => 'btn-secondary',
            ],

            'check_documentation' => [
                'action' => 'check_documentation',
                'label' => 'View Documentation',
                'description' => 'Check the troubleshooting guide for solutions.',
                'icon' => 'fa-book',
                'button_class' => 'btn-outline-info',
            ],
        ];
    }

    /**
     * Initialize admin contact information
     */
    private function initializeAdminContact() {
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
     * @param string $error_code The error code
     * @return array|null Error information or null if not found
     */
    public function getErrorInfo($error_code) {
        return $this->error_codes[$error_code] ?? $this->error_codes['UNKNOWN_ERROR'];
    }

    /**
     * Get recovery action information
     *
     * @param string $action_key The recovery action key
     * @return array|null Recovery action information or null if not found
     */
    public function getRecoveryAction($action_key) {
        return $this->recovery_actions[$action_key] ?? null;
    }

    /**
     * Get admin contact information
     *
     * @return array Admin contact information
     */
    public function getAdminContact() {
        return $this->admin_contact;
    }

    /**
     * Create a professional error message
     *
     * @param string $error_code The error code
     * @param array $additional_data Additional error data
     * @return array Formatted error message
     */
    public function createErrorMessage($error_code, $additional_data = []) {
        $error_info = $this->getErrorInfo($error_code);
        $recovery_action = $this->getRecoveryAction($error_info['recovery']);

        $error_message = [
            'error_code' => $error_code,
            'title' => $error_info['title'],
            'message' => $error_info['message'],
            'user_message' => $error_info['user_message'],
            'severity' => $error_info['severity'],
            'recovery_action' => $recovery_action,
            'admin_contact' => $this->admin_contact,
            'timestamp' => time(),
            'additional_data' => $additional_data,
            'suggestions' => $this->getSuggestions($error_code, $additional_data),
        ];

        return $error_message;
    }

    /**
     * Get suggestions for resolving the error
     *
     * @param string $error_code The error code
     * @param array $additional_data Additional error data
     * @return array Array of suggestions
     */
    private function getSuggestions($error_code, $additional_data = []) {
        $suggestions = [];

        switch ($error_code) {
            case 'MISSING_HEADERS':
                $suggestions = [
                    'Check if the plugin is properly configured',
                    'Verify that all required settings are saved',
                    'Try logging out and logging back in',
                ];
                break;

            case 'INVALID_API_KEY':
            case 'INVALID_API_KEY_FORMAT':
                $suggestions = [
                    'The API key may have been corrupted',
                    'Contact your administrator to regenerate the key',
                    'Check if the plugin was recently updated',
                ];
                break;

            case 'SITE_URL_MISMATCH':
                $suggestions = [
                    'Your site may have been moved to a new URL',
                    'Check if the site URL in Moodle settings has changed',
                    'Contact your administrator to update the registration',
                ];
                break;

            case 'UNAUTHORIZED_USER':
                $suggestions = [
                    'Verify you have the correct permissions',
                    'Check if your role has been changed',
                    'Contact your administrator to grant access',
                ];
                break;

            case 'SUBSCRIPTION_INACTIVE':
                $suggestions = [
                    'Your subscription may have expired',
                    'Check if payment information needs updating',
                    'Contact your administrator to renew the subscription',
                ];
                break;

            case 'INSUFFICIENT_TOKENS':
                $suggestions = [
                    'You may need to purchase more tokens',
                    'Check your current token balance',
                    'Contact your administrator to add more tokens',
                ];
                break;

            case 'BACKEND_CONNECTION_FAILED':
                $suggestions = [
                    'Check your internet connection',
                    'The service may be temporarily unavailable',
                    'Try again in a few minutes',
                ];
                break;

            default:
                $suggestions = [
                    'Try refreshing the page',
                    'Check if the issue persists',
                    'Contact your administrator for assistance',
                ];
        }

        return $suggestions;
    }

    /**
     * Log error for debugging purposes
     *
     * @param string $error_code The error code
     * @param array $additional_data Additional error data
     * @param string $user_context User context information
     */
    public function logError($error_code, $additional_data = [], $user_context = '') {
        global $USER;

        $error_info = $this->getErrorInfo($error_code);

        $log_data = [
            'error_code' => $error_code,
            'error_title' => $error_info['title'],
            'error_message' => $error_info['message'],
            'user_id' => $USER->id ?? 0,
            'user_email' => $USER->email ?? '',
            'user_context' => $user_context,
            'additional_data' => $additional_data,
            'timestamp' => time(),
            'ip_address' => getremoteaddr(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        // Log to Moodle's debugging system
        debugging('Adeptus Insights Error: ' . json_encode($log_data));

        // Log to plugin-specific log if enabled
        if (get_config('report_adeptus_insights', 'enable_error_logging')) {
            $this->writeToPluginLog($log_data);
        }
    }

    /**
     * Write error to plugin-specific log file
     *
     * @param array $log_data Log data to write
     */
    private function writeToPluginLog($log_data) {
        global $CFG;

        $log_dir = $CFG->dataroot . '/adeptus_insights/logs';
        $log_file = $log_dir . '/errors.log';

        // Create log directory if it doesn't exist
        if (!is_dir($log_dir)) {
            make_upload_directory('adeptus_insights/logs');
        }

        // Format log entry
        $log_entry = date('Y-m-d H:i:s') . ' - ' . json_encode($log_data) . PHP_EOL;

        // Write to log file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get error statistics for admin dashboard
     *
     * @param int $days Number of days to look back
     * @return array Error statistics
     */
    public function getErrorStatistics($days = 7) {
        global $CFG;

        $log_file = $CFG->dataroot . '/adeptus_insights/logs/errors.log';

        if (!file_exists($log_file)) {
            return [
                'total_errors' => 0,
                'errors_by_code' => [],
                'errors_by_user' => [],
                'recent_errors' => [],
            ];
        }

        $log_content = file_get_contents($log_file);
        $log_lines = explode(PHP_EOL, $log_content);

        $errors_by_code = [];
        $errors_by_user = [];
        $recent_errors = [];
        $total_errors = 0;

        $cutoff_time = time() - ($days * 24 * 60 * 60);

        foreach ($log_lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $log_data = json_decode(substr($line, strpos($line, ' - ') + 3), true);
            if (!$log_data) {
                continue;
            }

            $total_errors++;

            // Count by error code
            $error_code = $log_data['error_code'] ?? 'UNKNOWN';
            $errors_by_code[$error_code] = ($errors_by_code[$error_code] ?? 0) + 1;

            // Count by user
            $user_email = $log_data['user_email'] ?? 'unknown';
            $errors_by_user[$user_email] = ($errors_by_user[$user_email] ?? 0) + 1;

            // Recent errors
            if ($log_data['timestamp'] >= $cutoff_time) {
                $recent_errors[] = $log_data;
            }
        }

        return [
            'total_errors' => $total_errors,
            'errors_by_code' => $errors_by_code,
            'errors_by_user' => $errors_by_user,
            'recent_errors' => array_slice($recent_errors, -10), // Last 10 errors
        ];
    }

    /**
     * Clear error logs
     *
     * @return bool True if successful
     */
    public function clearErrorLogs() {
        global $CFG;

        $log_file = $CFG->dataroot . '/adeptus_insights/logs/errors.log';

        if (file_exists($log_file)) {
            return unlink($log_file);
        }

        return true;
    }
}
