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

// Require login and capability.
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Get parameters
$attachmentid = required_param('id', PARAM_INT);
$ticketid = required_param('ticket_id', PARAM_INT);

// Load the installation manager
$installationmanager = new \report_adeptus_insights\installation_manager();

if (!$installationmanager->is_registered()) {
    throw new moodle_exception('support_not_available', 'report_adeptus_insights');
}

// Build the API URL for downloading the attachment
$apiurl = $installationmanager->get_api_url();
$apikey = $installationmanager->get_api_key();

// Try the download endpoint - include ticket_id for authorization
$downloadurl = $apiurl . '/support/tickets/' . $ticketid . '/attachments/' . $attachmentid . '/download';

// Initialize Moodle curl wrapper.
$curl = new \curl();

// Set authorization header.
if ($apikey) {
    $curl->setHeader('Authorization: Bearer ' . $apikey);
}

// Set curl options.
$options = [
    'CURLOPT_FOLLOWLOCATION' => true,
    'CURLOPT_SSL_VERIFYPEER' => true,
    'CURLOPT_TIMEOUT' => 120,
];

// Execute the request.
$filecontent = $curl->get($downloadurl, [], $options);

// Get response info.
$info = $curl->get_info();
$httpcode = $info['http_code'] ?? 0;
$contenttype = $info['content_type'] ?? '';
$error = $curl->get_errno() ? $curl->error : '';

// Parse response headers from the curl wrapper.
$responseheaders = [];
$rawheaders = $curl->getResponse();
if (is_array($rawheaders)) {
    foreach ($rawheaders as $name => $value) {
        $responseheaders[strtolower($name)] = $value;
    }
}

// Check for errors
if ($filecontent === false) {
    throw new moodle_exception('attachment_download_failed', 'report_adeptus_insights');
}

if ($httpcode !== 200) {
    throw new moodle_exception('attachment_not_found', 'report_adeptus_insights');
}

// Extract filename from Content-Disposition header if available
$filename = 'attachment_' . $attachmentid;
if (isset($responseheaders['content-disposition'])) {
    $disposition = $responseheaders['content-disposition'];
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
if (empty($contenttype) || $contenttype === 'application/octet-stream') {
    // Try to detect from filename extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimetypes = [
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
    $contenttype = $mimetypes[$ext] ?? 'application/octet-stream';
}

// Set headers for file download
header('Content-Type: ' . $contenttype);
header('Content-Length: ' . strlen($filecontent));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the file content
echo $filecontent;
exit;
