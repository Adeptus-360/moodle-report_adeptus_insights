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
 * AJAX endpoint for generating reports.
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
    $reportid = required_param('reportid', PARAM_TEXT);
    $parameters = optional_param('parameters', '{}', PARAM_RAW);
    $reexecution = optional_param('reexecution', false, PARAM_BOOL);

    $result = \report_adeptus_insights\external\generate_report::execute($reportid, $parameters, $reexecution);

    // Transform results from Moodle's complex format [{cells: [{key, value}]}]
    // to simple format [{col1: val1, col2: val2}] that the block expects.
    if (!empty($result['results']) && is_array($result['results'])) {
        $simpleresults = [];
        foreach ($result['results'] as $row) {
            if (isset($row['cells']) && is_array($row['cells'])) {
                $simplerow = [];
                foreach ($row['cells'] as $cell) {
                    if (isset($cell['key'])) {
                        $simplerow[$cell['key']] = $cell['value'] ?? '';
                    }
                }
                $simpleresults[] = $simplerow;
            }
        }
        $result['results'] = $simpleresults;
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'exception',
        'message' => $e->getMessage(),
        'results' => [],
        'headers' => [],
    ]);
}
