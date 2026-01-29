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
 * AJAX endpoint for managing report categories.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/externallib.php');

// Require login and validate session.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json; charset=utf-8');

try {
    // Get parameters.
    $action = required_param('action', PARAM_ALPHA);
    $categoryid = optional_param('category_id', 0, PARAM_INT);
    $name = optional_param('name', '', PARAM_TEXT);
    $color = optional_param('color', '#6c757d', PARAM_TEXT);

    // Call the external function.
    $result = \report_adeptus_insights\external\manage_category::execute(
        $action,
        $categoryid,
        $name,
        $color
    );

    // Parse the JSON-encoded fields back to arrays for the response.
    $response = [
        'success' => $result['success'],
        'message' => $result['message'],
    ];

    // Handle categories (for list action) - always set data field.
    if (!empty($result['categories'])) {
        $decoded = json_decode($result['categories'], true);
        $response['data'] = is_array($decoded) ? $decoded : [];
    } else {
        $response['data'] = [];
    }

    // Handle single category (for create/update actions).
    if (!empty($result['category']) && $result['category'] !== '') {
        $response['category'] = json_decode($result['category'], true);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
