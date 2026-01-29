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
 * AJAX endpoint for executing AI-generated report SQL locally.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/externallib.php');

require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    // Read JSON body for POST requests with application/json content type.
    $contenttype = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($contenttype, 'application/json') !== false) {
        $jsonbody = file_get_contents('php://input');
        $data = json_decode($jsonbody, true);

        // Validate sesskey from JSON body.
        if (empty($data['sesskey']) || !confirm_sesskey($data['sesskey'])) {
            throw new moodle_exception('invalidsesskey');
        }

        $sql = $data['sql'] ?? '';
        $params = isset($data['params']) ? json_encode($data['params']) : '{}';
    } else {
        // Fallback to standard form parameters.
        require_sesskey();
        $sql = required_param('sql', PARAM_RAW);
        $params = optional_param('params', '{}', PARAM_RAW);
    }

    $result = \report_adeptus_insights\external\execute_ai_report::execute($sql, $params);

    // The external service returns data and headers as JSON strings.
    // Decode them so the final response has arrays, not strings.
    if (isset($result['data']) && is_string($result['data'])) {
        $result['data'] = json_decode($result['data'], true) ?: [];
    }
    if (isset($result['headers']) && is_string($result['headers'])) {
        $result['headers'] = json_decode($result['headers'], true) ?: [];
    }

    // Ensure data is a proper indexed array for JSON encoding.
    if (isset($result['data']) && is_array($result['data'])) {
        $result['data'] = array_values($result['data']);
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'exception',
        'message' => $e->getMessage(),
        'data' => '[]',
        'headers' => '[]',
        'row_count' => 0,
    ]);
}
