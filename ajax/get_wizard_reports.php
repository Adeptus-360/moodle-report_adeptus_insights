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
 * Get wizard reports AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../classes/api_config.php');

// Check for valid login
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set JSON response headers
header('Content-Type: application/json');

try {
    $userid = $USER->id;

    // Get API key and backend URL
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $api_key = $installation_manager->get_api_key();
    $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($api_key)) {
        echo json_encode([
            'success' => false,
            'message' => 'API key not configured',
            'reports' => [],
        ]);
        exit;
    }

    // Fetch wizard reports from backend API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/wizard-reports?user_id=' . $userid);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch wizard reports from server',
            'reports' => [],
        ]);
        exit;
    }

    $data = json_decode($response, true);

    if (empty($data['success'])) {
        echo json_encode([
            'success' => false,
            'message' => $data['message'] ?? 'Failed to fetch wizard reports',
            'reports' => [],
        ]);
        exit;
    }

    // The backend already returns properly formatted reports
    $reports = $data['reports'] ?? [];

    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'count' => count($reports),
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching wizard reports: ' . $e->getMessage(),
        'reports' => [],
    ]);
}
