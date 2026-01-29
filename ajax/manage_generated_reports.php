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
 * AJAX endpoint for managing generated reports.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/externallib.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHA);
    $reportid = optional_param('report_id', 0, PARAM_INT);

    $result = \report_adeptus_insights\external\manage_generated_reports::execute($action, $reportid);

    $response = [
        'success' => $result['success'],
        'message' => $result['message'] ?? '',
    ];

    if (!empty($result['reports']) && $result['reports'] !== '[]') {
        $response['reports'] = json_decode($result['reports'], true);
    }

    if (!empty($result['report']) && $result['report'] !== '') {
        $response['report'] = json_decode($result['report'], true);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
