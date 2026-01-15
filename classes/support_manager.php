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
 * Support Manager for Adeptus Insights.
 *
 * Handles support ticket submission, retrieval, and changelog fetching.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class support_manager {
    /** @var installation_manager */
    private $installationmanager;

    /** @var string Product key for changelog lookups */
    private const PRODUCT_KEY = 'insights';

    /** @var array Allowed ticket categories */
    public const TICKET_CATEGORIES = [
        'support' => 'Support',
        'bug' => 'Bug Report',
        'feature_request' => 'Feature Request',
        'other' => 'Other',
    ];

    /** @var array Allowed ticket priorities */
    public const TICKET_PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    /** @var int Maximum file size for attachments (10MB) */
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** @var array Allowed file extensions for attachments */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'txt', 'log', 'csv', 'json',
        'zip',
    ];

    public function __construct() {
        $this->installation_manager = new installation_manager();
    }

    /**
     * Check if the plugin is registered and can access support features.
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->installation_manager->is_registered();
    }

    /**
     * Create a new support ticket.
     *
     * @param string $category Ticket category
     * @param string $subject Ticket subject
     * @param string $message Ticket message
     * @param string|null $submittername Optional submitter name
     * @param string|null $submitteremail Optional submitter email
     * @param string $priority Priority level (default: medium)
     * @param array $attachments Array of file attachment info
     * @return array Result with success status and data/error
     */
    public function create_ticket(
        string $category,
        string $subject,
        string $message,
        ?string $submittername = null,
        ?string $submitteremail = null,
        string $priority = 'medium',
        array $attachments = []
    ): array {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'message' => get_string('support_not_available', 'report_adeptus_insights'),
            ];
        }

        // Validate category
        if (!array_key_exists($category, self::TICKET_CATEGORIES)) {
            return [
                'success' => false,
                'message' => get_string('invalid_category', 'report_adeptus_insights'),
            ];
        }

        // Validate priority
        if (!array_key_exists($priority, self::TICKET_PRIORITIES)) {
            $priority = 'medium';
        }

        // Validate subject and message
        if (empty(trim($subject))) {
            return [
                'success' => false,
                'message' => get_string('subject_required', 'report_adeptus_insights'),
            ];
        }

        if (empty(trim($message))) {
            return [
                'success' => false,
                'message' => get_string('message_required', 'report_adeptus_insights'),
            ];
        }

        try {
            $data = [
                'category' => $category,
                'subject' => trim($subject),
                'message' => trim($message),
                'priority' => $priority,
            ];

            if (!empty($submittername)) {
                $data['submitter_name'] = trim($submittername);
            }

            if (!empty($submitteremail)) {
                $data['submitter_email'] = trim($submitteremail);
            }

            // Check if we have file attachments with actual files
            $hasvalidfiles = false;
            if (!empty($attachments) && isset($attachments['tmp_name'])) {
                if (is_array($attachments['tmp_name'])) {
                    foreach ($attachments['tmp_name'] as $tmpname) {
                        if (!empty($tmpname) && is_uploaded_file($tmpname)) {
                            $hasvalidfiles = true;
                            break;
                        }
                    }
                } else if (!empty($attachments['tmp_name']) && is_uploaded_file($attachments['tmp_name'])) {
                    $hasvalidfiles = true;
                }
            }

            if ($hasvalidfiles) {
                // Use multipart form data for file uploads
                $response = $this->make_multipart_request('support/tickets', $data, $attachments);
            } else {
                // Standard JSON request without files
                $response = $this->installation_manager->make_api_request('support/tickets', $data);
            }

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'message' => get_string('ticket_created', 'report_adeptus_insights'),
                    'data' => $response['data'],
                    'ticket_number' => $response['data']['ticket_number'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? get_string('ticket_creation_failed', 'report_adeptus_insights'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('ticket_creation_failed', 'report_adeptus_insights'),
            ];
        }
    }

    /**
     * Make a multipart API request with file uploads.
     *
     * @param string $endpoint API endpoint
     * @param array $data Form data
     * @param array $files Files from $_FILES
     * @return array|null Response data
     */
    protected function make_multipart_request(string $endpoint, array $data, array $files): ?array {
        $apiurl = $this->installation_manager->get_api_url();
        $apikey = $this->installation_manager->get_api_key();
        $url = $apiurl . '/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Build multipart form data
        $postdata = $data;

        // Add files to the request
        if (isset($files['tmp_name']) && is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $index => $tmpname) {
                if (!empty($tmpname) && is_uploaded_file($tmpname)) {
                    $filename = $files['name'][$index] ?? 'attachment_' . $index;
                    $mimetype = $files['type'][$index] ?? 'application/octet-stream';

                    // Validate file
                    $validation = $this->validate_attachment([
                        'tmp_name' => $tmpname,
                        'name' => $filename,
                        'size' => $files['size'][$index] ?? 0,
                        'error' => $files['error'][$index] ?? UPLOAD_ERR_OK,
                    ]);

                    if ($validation['success']) {
                        $postdata['attachments[' . $index . ']'] = new \CURLFile($tmpname, $mimetype, $filename);
                    }
                }
            }
        } else if (isset($files['tmp_name']) && !empty($files['tmp_name']) && is_uploaded_file($files['tmp_name'])) {
            // Single file upload
            $filename = $files['name'] ?? 'attachment';
            $mimetype = $files['type'] ?? 'application/octet-stream';

            $validation = $this->validate_attachment($files);
            if ($validation['success']) {
                $postdata['attachments[0]'] = new \CURLFile($files['tmp_name'], $mimetype, $filename);
            }
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);

        // Headers for multipart (don't set Content-Type, let cURL handle it)
        $headers = [];
        if ($apikey) {
            $headers[] = 'Authorization: Bearer ' . $apikey;
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout for file uploads
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        // Check for HTTP error codes
        if ($httpcode >= 400) {
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Get all tickets for the current installation.
     *
     * @param string|null $status Filter by status (optional)
     * @param string|null $category Filter by category (optional)
     * @return array Result with success status and tickets array
     */
    public function get_tickets(?string $status = null, ?string $category = null): array {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'message' => get_string('support_not_available', 'report_adeptus_insights'),
                'tickets' => [],
            ];
        }

        try {
            $params = [];
            if ($status) {
                $params['status'] = $status;
            }
            if ($category) {
                $params['category'] = $category;
            }

            // Build query string for GET request
            $endpoint = 'support/tickets';
            if (!empty($params)) {
                $endpoint .= '?' . http_build_query($params);
            }

            $response = $this->installation_manager->make_api_request($endpoint, [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'tickets' => $response['data'] ?? [],
                    'total' => $response['meta']['total'] ?? count($response['data'] ?? []),
                ];
            }

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? get_string('failed_to_load_tickets', 'report_adeptus_insights'),
                'tickets' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('failed_to_load_tickets', 'report_adeptus_insights'),
                'tickets' => [],
            ];
        }
    }

    /**
     * Get a specific ticket with its replies.
     *
     * @param int $ticketid The ticket ID
     * @return array Result with success status and ticket data
     */
    public function get_ticket(int $ticketid): array {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'message' => get_string('support_not_available', 'report_adeptus_insights'),
            ];
        }

        try {
            $response = $this->installation_manager->make_api_request("support/tickets/{$ticketid}", [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'ticket' => $response['data'],
                ];
            }

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? get_string('ticket_not_found', 'report_adeptus_insights'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('ticket_not_found', 'report_adeptus_insights'),
            ];
        }
    }

    /**
     * Add a reply to an existing ticket.
     *
     * @param int $ticketid The ticket ID
     * @param string $message The reply message
     * @param string|null $sendername Optional sender name
     * @param array $attachments Array of file attachment info
     * @return array Result with success status and reply data
     */
    public function add_reply(int $ticketid, string $message, ?string $sendername = null, array $attachments = []): array {
        if (!$this->is_available()) {
            return [
                'success' => false,
                'message' => get_string('support_not_available', 'report_adeptus_insights'),
            ];
        }

        if (empty(trim($message))) {
            return [
                'success' => false,
                'message' => get_string('message_required', 'report_adeptus_insights'),
            ];
        }

        try {
            $data = [
                'message' => trim($message),
            ];

            if (!empty($sendername)) {
                $data['sender_name'] = trim($sendername);
            }

            // Check if we have file attachments with actual files
            $hasvalidfiles = false;
            if (!empty($attachments) && isset($attachments['tmp_name'])) {
                if (is_array($attachments['tmp_name'])) {
                    foreach ($attachments['tmp_name'] as $tmpname) {
                        if (!empty($tmpname) && is_uploaded_file($tmpname)) {
                            $hasvalidfiles = true;
                            break;
                        }
                    }
                } else if (!empty($attachments['tmp_name']) && is_uploaded_file($attachments['tmp_name'])) {
                    $hasvalidfiles = true;
                }
            }

            if ($hasvalidfiles) {
                // Use multipart form data for file uploads
                $response = $this->make_multipart_request("support/tickets/{$ticketid}/reply", $data, $attachments);
            } else {
                // Standard JSON request without files
                $response = $this->installation_manager->make_api_request("support/tickets/{$ticketid}/reply", $data);
            }

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'message' => get_string('reply_added', 'report_adeptus_insights'),
                    'data' => $response['data'],
                ];
            }

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? get_string('reply_failed', 'report_adeptus_insights'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('reply_failed', 'report_adeptus_insights'),
            ];
        }
    }

    /**
     * Get changelog entries for the plugin.
     *
     * @param int $limit Maximum number of entries to return
     * @param string|null $sinceversion Only get changelogs since this version
     * @return array Result with success status and changelog entries
     */
    public function get_changelog(int $limit = 20, ?string $sinceversion = null): array {
        try {
            $params = ['limit' => $limit];
            if ($sinceversion) {
                $params['since_version'] = $sinceversion;
            }

            $endpoint = 'changelog/' . self::PRODUCT_KEY;
            if (!empty($params)) {
                $endpoint .= '?' . http_build_query($params);
            }

            $response = $this->installation_manager->make_api_request($endpoint, [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'changelogs' => $response['data']['changelogs'] ?? [],
                    'latest_version' => $response['data']['latest_version'] ?? null,
                    'total' => $response['meta']['total'] ?? 0,
                ];
            }

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? get_string('failed_to_load_changelog', 'report_adeptus_insights'),
                'changelogs' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('failed_to_load_changelog', 'report_adeptus_insights'),
                'changelogs' => [],
            ];
        }
    }

    /**
     * Check if an update is available for the plugin.
     *
     * @param string|null $currentversion Current plugin version (null uses installed version)
     * @return array Result with update information
     */
    public function check_for_updates(?string $currentversion = null): array {
        try {
            if ($currentversion === null) {
                $currentversion = $this->installation_manager->get_plugin_version();
            }

            $endpoint = 'updates/check/' . self::PRODUCT_KEY . '?version=' . urlencode($currentversion);

            $response = $this->installation_manager->make_api_request($endpoint, [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'update_available' => $response['data']['update_available'] ?? false,
                    'current_version' => $response['data']['current_version'] ?? $currentversion,
                    'latest_version' => $response['data']['latest_version'] ?? null,
                    'versions_behind' => $response['data']['versions_behind'] ?? 0,
                    'latest_changelog' => $response['data']['latest_changelog'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $response['error']['message'] ?? get_string('failed_to_check_updates', 'report_adeptus_insights'),
                'update_available' => false,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('failed_to_check_updates', 'report_adeptus_insights'),
                'update_available' => false,
            ];
        }
    }

    /**
     * Validate a file for upload as attachment.
     *
     * @param array $file File info from $_FILES
     * @return array Validation result with success status
     */
    public function validate_attachment(array $file): array {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => get_string('file_upload_error', 'report_adeptus_insights'),
            ];
        }

        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return [
                'success' => false,
                'message' => get_string('file_too_large', 'report_adeptus_insights', '10MB'),
            ];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return [
                'success' => false,
                'message' => get_string('file_type_not_allowed', 'report_adeptus_insights'),
            ];
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Get the status label for a ticket status.
     *
     * @param string $status Status code
     * @return string Human-readable status label
     */
    public static function get_status_label(string $status): string {
        $statuses = [
            'open' => get_string('status_open', 'report_adeptus_insights'),
            'in_progress' => get_string('status_in_progress', 'report_adeptus_insights'),
            'resolved' => get_string('status_resolved', 'report_adeptus_insights'),
            'closed' => get_string('status_closed', 'report_adeptus_insights'),
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Get the CSS class for a ticket status badge.
     *
     * @param string $status Status code
     * @return string CSS class for the badge
     */
    public static function get_status_class(string $status): string {
        $classes = [
            'open' => 'badge-warning',
            'in_progress' => 'badge-info',
            'resolved' => 'badge-success',
            'closed' => 'badge-secondary',
        ];

        return $classes[$status] ?? 'badge-secondary';
    }

    /**
     * Get the priority label for a ticket priority.
     *
     * @param string $priority Priority code
     * @return string Human-readable priority label
     */
    public static function get_priority_label(string $priority): string {
        $priorities = [
            'low' => get_string('priority_low', 'report_adeptus_insights'),
            'medium' => get_string('priority_medium', 'report_adeptus_insights'),
            'high' => get_string('priority_high', 'report_adeptus_insights'),
        ];

        return $priorities[$priority] ?? $priority;
    }

    /**
     * Get the CSS class for a priority badge.
     *
     * @param string $priority Priority code
     * @return string CSS class for the badge
     */
    public static function get_priority_class(string $priority): string {
        $classes = [
            'low' => 'badge-secondary',
            'medium' => 'badge-warning',
            'high' => 'badge-danger',
        ];

        return $classes[$priority] ?? 'badge-secondary';
    }

    /**
     * Get the category label for a ticket category.
     *
     * @param string $category Category code
     * @return string Human-readable category label
     */
    public static function get_category_label(string $category): string {
        return self::TICKET_CATEGORIES[$category] ?? $category;
    }
}
