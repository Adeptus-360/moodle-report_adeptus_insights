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
 * AJAX endpoint for batch KPI data fetching.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

try {
    $reportids = required_param('reportids', PARAM_RAW);

    $result = \report_adeptus_insights\external\batch_kpi_data::execute($reportids);

    // The external service returns 'reports' as a JSON string.
    // Decode it so the final response has reports as an object.
    if (isset($result['reports']) && is_string($result['reports'])) {
        $result['reports'] = json_decode($result['reports'], true);
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'reports' => '{}',
        'total_time_ms' => 0,
        'report_count' => 0,
    ]);
}
