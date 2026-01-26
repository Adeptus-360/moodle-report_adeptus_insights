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
 * Notification Manager for Adeptus Insights.
 *
 * Handles display of professional error messages and user notifications.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Notification manager class for displaying user notifications.
 *
 * Handles display of professional error messages and user notifications.
 */
class notification_manager {
    /** @var error_handler Error handler instance. */
    private $error_handler;

    /** @var array Array of notification messages. */
    private $notifications;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->error_handler = new \report_adeptus_insights\error_handler();
        $this->notifications = [];
    }

    /**
     * Display a professional error message
     *
     * @param string $errorcode The error code
     * @param array $additionaldata Additional error data
     * @param bool $logerror Whether to log the error
     * @return string HTML for the error message
     */
    public function display_error($errorcode, $additionaldata = [], $logerror = true) {
        if ($logerror) {
            $this->error_handler->log_error($errorcode, $additionaldata);
        }

        $errormessage = $this->error_handler->create_error_message($errorcode, $additionaldata);
        $html = $this->render_error_message($errormessage);

        // Store notification for potential reuse
        $this->notifications[] = $errormessage;

        return $html;
    }

    /**
     * Display a success notification
     *
     * @param string $title The notification title
     * @param string $message The notification message
     * @param array $actions Optional actions to display
     * @return string HTML for the success message
     */
    public function display_success($title, $message, $actions = []) {
        $notification = [
            'type' => 'success',
            'title' => $title,
            'message' => $message,
            'actions' => $actions,
            'timestamp' => time(),
        ];

        $this->notifications[] = $notification;

        return $this->render_notification($notification);
    }

    /**
     * Display a warning notification
     *
     * @param string $title The notification title
     * @param string $message The notification message
     * @param array $actions Optional actions to display
     * @return string HTML for the warning message
     */
    public function display_warning($title, $message, $actions = []) {
        $notification = [
            'type' => 'warning',
            'title' => $title,
            'message' => $message,
            'actions' => $actions,
            'timestamp' => time(),
        ];

        $this->notifications[] = $notification;

        return $this->render_notification($notification);
    }

    /**
     * Display an info notification
     *
     * @param string $title The notification title
     * @param string $message The notification message
     * @param array $actions Optional actions to display
     * @return string HTML for the info message
     */
    public function display_info($title, $message, $actions = []) {
        $notification = [
            'type' => 'info',
            'title' => $title,
            'message' => $message,
            'actions' => $actions,
            'timestamp' => time(),
        ];

        $this->notifications[] = $notification;

        return $this->render_notification($notification);
    }

    /**
     * Render an error message with professional styling
     *
     * @param array $errormessage The error message data
     * @return string HTML for the error message
     */
    private function render_error_message($errormessage) {
        $severityclass = $this->get_severity_class($errormessage['severity']);
        $iconclass = $this->get_severity_icon($errormessage['severity']);

        $html = '<div class="adeptus-error-message ' . $severityclass . '" role="alert">';
        $html .= '<div class="adeptus-error-header">';
        $html .= '<i class="fa ' . $iconclass . '" aria-hidden="true"></i>';
        $html .= '<strong>' . htmlspecialchars($errormessage['title']) . '</strong>';
        $html .= '</div>';

        $html .= '<div class="adeptus-error-content">';
        $html .= '<p>' . htmlspecialchars($errormessage['user_message']) . '</p>';

        // Display suggestions if available
        if (!empty($errormessage['suggestions'])) {
            $html .= '<div class="adeptus-error-suggestions">';
            $html .= '<h4>Suggestions:</h4>';
            $html .= '<ul>';
            foreach ($errormessage['suggestions'] as $suggestion) {
                $html .= '<li>' . htmlspecialchars($suggestion) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Display recovery actions
        if ($errormessage['recovery_action']) {
            $html .= $this->render_recovery_actions($errormessage['recovery_action'], $errormessage['admin_contact']);
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a general notification
     *
     * @param array $notification The notification data
     * @return string HTML for the notification
     */
    private function render_notification($notification) {
        $typeclass = 'adeptus-notification-' . $notification['type'];
        $iconclass = $this->get_notification_icon($notification['type']);

        $html = '<div class="adeptus-notification ' . $typeclass . '" role="alert">';
        $html .= '<div class="adeptus-notification-header">';
        $html .= '<i class="fa ' . $iconclass . '" aria-hidden="true"></i>';
        $html .= '<strong>' . htmlspecialchars($notification['title']) . '</strong>';
        $html .= '</div>';

        $html .= '<div class="adeptus-notification-content">';
        $html .= '<p>' . htmlspecialchars($notification['message']) . '</p>';

        // Display actions if available
        if (!empty($notification['actions'])) {
            $html .= $this->render_notification_actions($notification['actions']);
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render recovery actions
     *
     * @param array $recoveryaction The recovery action data
     * @param array $admincontact Admin contact information
     * @return string HTML for recovery actions
     */
    private function render_recovery_actions($recoveryaction, $admincontact) {
        $html = '<div class="adeptus-recovery-actions">';
        $html .= '<h4>What you can do:</h4>';

        // Primary recovery action
        $html .= '<div class="adeptus-primary-action">';
        $html .= '<button type="button" class="btn ' . $recoveryaction['button_class'] . ' btn-sm" ';
        $html .= 'onclick="this.handleRecoveryAction(\'' . $recoveryaction['action'] . '\')">';
        $html .= '<i class="fa ' . $recoveryaction['icon'] . '" aria-hidden="true"></i> ';
        $html .= htmlspecialchars($recoveryaction['label']);
        $html .= '</button>';
        $html .= '<small>' . htmlspecialchars($recoveryaction['description']) . '</small>';
        $html .= '</div>';

        // Additional actions
        $html .= '<div class="adeptus-additional-actions">';

        // Contact admin action
        $html .= '<button type="button" class="btn btn-outline-info btn-sm" ';
        $html .= 'onclick="this.contactAdministrator()">';
        $html .= '<i class="fa fa-envelope" aria-hidden="true"></i> Contact Administrator';
        $html .= '</button>';

        // Documentation action
        $html .= '<button type="button" class="btn btn-outline-secondary btn-sm" ';
        $html .= 'onclick="this.viewDocumentation()">';
        $html .= '<i class="fa fa-book" aria-hidden="true"></i> View Documentation';
        $html .= '</button>';

        $html .= '</div>';

        // Admin contact information
        if ($admincontact['email']) {
            $html .= '<div class="adeptus-admin-contact">';
            $html .= '<small><strong>Need immediate help?</strong> ';
            $html .= 'Contact: <a href="mailto:' . htmlspecialchars($admincontact['email']) . '">';
            $html .= htmlspecialchars($admincontact['email']) . '</a></small>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render notification actions
     *
     * @param array $actions The actions to display
     * @return string HTML for notification actions
     */
    private function render_notification_actions($actions) {
        if (empty($actions)) {
            return '';
        }

        $html = '<div class="adeptus-notification-actions">';
        foreach ($actions as $action) {
            $html .= '<button type="button" class="btn btn-sm ' . ($action['class'] ?? 'btn-outline-primary') . '" ';
            if (isset($action['onclick'])) {
                $html .= 'onclick="' . htmlspecialchars($action['onclick']) . '"';
            }
            $html .= '>';
            if (isset($action['icon'])) {
                $html .= '<i class="fa ' . $action['icon'] . '" aria-hidden="true"></i> ';
            }
            $html .= htmlspecialchars($action['label']);
            $html .= '</button>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Get CSS class for severity level
     *
     * @param string $severity The severity level
     * @return string CSS class
     */
    private function get_severity_class($severity) {
        switch ($severity) {
            case 'error':
                return 'adeptus-error-severity-error';
            case 'warning':
                return 'adeptus-error-severity-warning';
            case 'info':
                return 'adeptus-error-severity-info';
            default:
                return 'adeptus-error-severity-error';
        }
    }

    /**
     * Get icon class for severity level
     *
     * @param string $severity The severity level
     * @return string Icon class
     */
    private function get_severity_icon($severity) {
        switch ($severity) {
            case 'error':
                return 'fa-exclamation-circle';
            case 'warning':
                return 'fa-exclamation-triangle';
            case 'info':
                return 'fa-info-circle';
            default:
                return 'fa-exclamation-circle';
        }
    }

    /**
     * Get icon class for notification type
     *
     * @param string $type The notification type
     * @return string Icon class
     */
    private function get_notification_icon($type) {
        switch ($type) {
            case 'success':
                return 'fa-check-circle';
            case 'warning':
                return 'fa-exclamation-triangle';
            case 'info':
                return 'fa-info-circle';
            case 'error':
                return 'fa-exclamation-circle';
            default:
                return 'fa-info-circle';
        }
    }

    /**
     * Display all stored notifications
     *
     * @return string HTML for all notifications
     */
    public function display_all_notifications() {
        if (empty($this->notifications)) {
            return '';
        }

        $html = '<div class="adeptus-notifications-container">';
        foreach ($this->notifications as $notification) {
            if (isset($notification['error_code'])) {
                $html .= $this->render_error_message($notification);
            } else {
                $html .= $this->render_notification($notification);
            }
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Clear all stored notifications
     */
    public function clear_notifications() {
        $this->notifications = [];
    }

    /**
     * Get notification count
     *
     * @return int Number of notifications
     */
    public function get_notification_count() {
        return count($this->notifications);
    }

    /**
     * Check if there are any error notifications
     *
     * @return bool True if there are error notifications
     */
    public function has_errors() {
        foreach ($this->notifications as $notification) {
            if (
                isset($notification['error_code']) ||
                (isset($notification['type']) && $notification['type'] === 'error')
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get notifications as JSON for JavaScript
     *
     * @return string JSON string of notifications
     */
    public function get_notifications_as_json() {
        return json_encode($this->notifications);
    }

    /**
     * Display a toast notification (for JavaScript integration)
     *
     * @param string $type The notification type
     * @param string $title The notification title
     * @param string $message The notification message
     * @param int $duration Duration in milliseconds (0 for manual close)
     * @return string JavaScript code for toast notification
     */
    public function display_toast($type, $title, $message, $duration = 5000) {
        $notification = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'duration' => $duration,
        ];

        $this->notifications[] = $notification;

        $js = '<script type="text/javascript">';
        $js .= 'document.addEventListener("DOMContentLoaded", function() {';
        $js .= 'if (typeof AdeptusNotifications !== "undefined") {';
        $js .= 'AdeptusNotifications.showToast(' . json_encode($notification) . ');';
        $js .= '}';
        $js .= '});';
        $js .= '</script>';

        return $js;
    }
}
