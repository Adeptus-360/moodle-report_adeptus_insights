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
 * Attachment download proxy for Adeptus Insights.
 *
 * Fetches attachments from the backend API and streams them to the user.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Get parameters
$attachment_id = required_param('id', PARAM_INT);
$ticket_id = required_param('ticket_id', PARAM_INT);

// Load the installation manager
$installation_manager = new \report_adeptus_insights\installation_manager();

if (!$installation_manager->is_registered()) {
    throw new moodle_exception('support_not_available', 'report_adeptus_insights');
}

// Build the API URL for downloading the attachment
$api_url = $installation_manager->get_api_url();
$api_key = $installation_manager->get_api_key();

// Try the download endpoint - include ticket_id for authorization
$download_url = $api_url . '/support/tickets/' . $ticket_id . '/attachments/' . $attachment_id . '/download';

// Variables to capture response headers
$response_headers = [];

// Header callback to capture headers
$header_callback = function ($ch, $header) use (&$response_headers) {
    $len = strlen($header);
    $header = explode(':', $header, 2);
    if (count($header) < 2) {
        return $len;
    }
    $name = strtolower(trim($header[0]));
    $value = trim($header[1]);
    $response_headers[$name] = $value;
    return $len;
};

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $download_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, $header_callback);

// Set authorization header
$headers = [];
if ($api_key) {
    $headers[] = 'Authorization: Bearer ' . $api_key;
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

$file_content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

// Check for errors
if ($file_content === false) {
    throw new moodle_exception('attachment_download_failed', 'report_adeptus_insights');
}

if ($http_code !== 200) {
    throw new moodle_exception('attachment_not_found', 'report_adeptus_insights');
}

// Extract filename from Content-Disposition header if available
$filename = 'attachment_' . $attachment_id;
if (isset($response_headers['content-disposition'])) {
    $disposition = $response_headers['content-disposition'];
    // Try to extract filename from header
    if (preg_match('/filename[^;=\n]*=([\'"]?)([^\'"\n;]*)\1/', $disposition, $matches)) {
        $filename = $matches[2];
    } else if (preg_match('/filename\*=(?:UTF-8\'\')?(.+)/', $disposition, $matches)) {
        $filename = urldecode($matches[1]);
    }
}

// Clean filename for safety
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

// Determine content type if not set
if (empty($content_type) || $content_type === 'application/octet-stream') {
    // Try to detect from filename extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'log' => 'text/plain',
        'csv' => 'text/csv',
        'json' => 'application/json',
        'zip' => 'application/zip',
    ];
    $content_type = $mime_types[$ext] ?? 'application/octet-stream';
}

// Set headers for file download
header('Content-Type: ' . $content_type);
header('Content-Length: ' . strlen($file_content));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the file content
echo $file_content;
exit;
