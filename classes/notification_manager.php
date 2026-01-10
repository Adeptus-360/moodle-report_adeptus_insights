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
 * Notification Manager for Adeptus Insights
 * Handles display of professional error messages and user notifications
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class notification_manager {
    private $error_handler;
    private $notifications;

    public function __construct() {
        $this->error_handler = new \report_adeptus_insights\error_handler();
        $this->notifications = [];
    }

    /**
     * Display a professional error message
     *
     * @param string $error_code The error code
     * @param array $additional_data Additional error data
     * @param bool $log_error Whether to log the error
     * @return string HTML for the error message
     */
    public function displayError($error_code, $additional_data = [], $log_error = true) {
        if ($log_error) {
            $this->error_handler->logError($error_code, $additional_data);
        }

        $error_message = $this->error_handler->createErrorMessage($error_code, $additional_data);
        $html = $this->renderErrorMessage($error_message);

        // Store notification for potential reuse
        $this->notifications[] = $error_message;

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
    public function displaySuccess($title, $message, $actions = []) {
        $notification = [
            'type' => 'success',
            'title' => $title,
            'message' => $message,
            'actions' => $actions,
            'timestamp' => time(),
        ];

        $this->notifications[] = $notification;

        return $this->renderNotification($notification);
    }

    /**
     * Display a warning notification
     *
     * @param string $title The notification title
     * @param string $message The notification message
     * @param array $actions Optional actions to display
     * @return string HTML for the warning message
     */
    public function displayWarning($title, $message, $actions = []) {
        $notification = [
            'type' => 'warning',
            'title' => $title,
            'message' => $message,
            'actions' => $actions,
            'timestamp' => time(),
        ];

        $this->notifications[] = $notification;

        return $this->renderNotification($notification);
    }

    /**
     * Display an info notification
     *
     * @param string $title The notification title
     * @param string $message The notification message
     * @param array $actions Optional actions to display
     * @return string HTML for the info message
     */
    public function displayInfo($title, $message, $actions = []) {
        $notification = [
            'type' => 'info',
            'title' => $title,
            'message' => $message,
            'actions' => $actions,
            'timestamp' => time(),
        ];

        $this->notifications[] = $notification;

        return $this->renderNotification($notification);
    }

    /**
     * Render an error message with professional styling
     *
     * @param array $error_message The error message data
     * @return string HTML for the error message
     */
    private function renderErrorMessage($error_message) {
        $severity_class = $this->getSeverityClass($error_message['severity']);
        $icon_class = $this->getSeverityIcon($error_message['severity']);

        $html = '<div class="adeptus-error-message ' . $severity_class . '" role="alert">';
        $html .= '<div class="adeptus-error-header">';
        $html .= '<i class="fa ' . $icon_class . '" aria-hidden="true"></i>';
        $html .= '<strong>' . htmlspecialchars($error_message['title']) . '</strong>';
        $html .= '</div>';

        $html .= '<div class="adeptus-error-content">';
        $html .= '<p>' . htmlspecialchars($error_message['user_message']) . '</p>';

        // Display suggestions if available
        if (!empty($error_message['suggestions'])) {
            $html .= '<div class="adeptus-error-suggestions">';
            $html .= '<h4>Suggestions:</h4>';
            $html .= '<ul>';
            foreach ($error_message['suggestions'] as $suggestion) {
                $html .= '<li>' . htmlspecialchars($suggestion) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Display recovery actions
        if ($error_message['recovery_action']) {
            $html .= $this->renderRecoveryActions($error_message['recovery_action'], $error_message['admin_contact']);
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
    private function renderNotification($notification) {
        $type_class = 'adeptus-notification-' . $notification['type'];
        $icon_class = $this->getNotificationIcon($notification['type']);

        $html = '<div class="adeptus-notification ' . $type_class . '" role="alert">';
        $html .= '<div class="adeptus-notification-header">';
        $html .= '<i class="fa ' . $icon_class . '" aria-hidden="true"></i>';
        $html .= '<strong>' . htmlspecialchars($notification['title']) . '</strong>';
        $html .= '</div>';

        $html .= '<div class="adeptus-notification-content">';
        $html .= '<p>' . htmlspecialchars($notification['message']) . '</p>';

        // Display actions if available
        if (!empty($notification['actions'])) {
            $html .= $this->renderNotificationActions($notification['actions']);
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render recovery actions
     *
     * @param array $recovery_action The recovery action data
     * @param array $admin_contact Admin contact information
     * @return string HTML for recovery actions
     */
    private function renderRecoveryActions($recovery_action, $admin_contact) {
        $html = '<div class="adeptus-recovery-actions">';
        $html .= '<h4>What you can do:</h4>';

        // Primary recovery action
        $html .= '<div class="adeptus-primary-action">';
        $html .= '<button type="button" class="btn ' . $recovery_action['button_class'] . ' btn-sm" ';
        $html .= 'onclick="this.handleRecoveryAction(\'' . $recovery_action['action'] . '\')">';
        $html .= '<i class="fa ' . $recovery_action['icon'] . '" aria-hidden="true"></i> ';
        $html .= htmlspecialchars($recovery_action['label']);
        $html .= '</button>';
        $html .= '<small>' . htmlspecialchars($recovery_action['description']) . '</small>';
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
        if ($admin_contact['email']) {
            $html .= '<div class="adeptus-admin-contact">';
            $html .= '<small><strong>Need immediate help?</strong> ';
            $html .= 'Contact: <a href="mailto:' . htmlspecialchars($admin_contact['email']) . '">';
            $html .= htmlspecialchars($admin_contact['email']) . '</a></small>';
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
    private function renderNotificationActions($actions) {
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
    private function getSeverityClass($severity) {
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
    private function getSeverityIcon($severity) {
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
    private function getNotificationIcon($type) {
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
    public function displayAllNotifications() {
        if (empty($this->notifications)) {
            return '';
        }

        $html = '<div class="adeptus-notifications-container">';
        foreach ($this->notifications as $notification) {
            if (isset($notification['error_code'])) {
                $html .= $this->renderErrorMessage($notification);
            } else {
                $html .= $this->renderNotification($notification);
            }
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Clear all stored notifications
     */
    public function clearNotifications() {
        $this->notifications = [];
    }

    /**
     * Get notification count
     *
     * @return int Number of notifications
     */
    public function getNotificationCount() {
        return count($this->notifications);
    }

    /**
     * Check if there are any error notifications
     *
     * @return bool True if there are error notifications
     */
    public function hasErrors() {
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
    public function getNotificationsAsJson() {
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
    public function displayToast($type, $title, $message, $duration = 5000) {
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
