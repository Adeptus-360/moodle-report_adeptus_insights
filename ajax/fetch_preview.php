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
 * Fetch preview data for Adeptus Insights reports.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
require_sesskey();
require_capability('report/adeptus_insights:view', context_system::instance());

header('Content-Type: application/json');

try {
    $question = required_param('question', PARAM_ALPHA);
    $fields   = optional_param('fields', [], PARAM_RAW);

    if (! is_array($fields)) {
        $fields = [$fields];
    }

    if (empty($fields)) {
        throw new moodle_exception('error_no_fields_selected', 'report_adeptus_insights');
    }

    $allowed     = ['logins', 'assignments_submitted', 'forum_posts', 'average_grade'];
    $validfields = array_intersect($fields, $allowed);

    if (empty($validfields)) {
        throw new moodle_exception('error_invalid_fields', 'report_adeptus_insights');
    }

    global $DB;
    $fieldlist = implode(', ', array_map(fn($f) => $f, $validfields));

    $sql     = "SELECT $fieldlist FROM {report_adeptus_insights_analytics} ORDER BY timecreated DESC LIMIT 20";
    $results = $DB->get_records_sql($sql);

    echo json_encode(array_values($results));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
