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
 * Report Builder — execute and view report results.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('report/adeptus_insights:usebuilder', $context);

$id = required_param('id', PARAM_INT);
$format = optional_param('format', '', PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/builder_report_view.php', ['id' => $id]));
$PAGE->set_pagelayout('report');

$client = new \report_adeptus_insights\builder_api_client();

// Fetch the report definition.
$report = $client->get_report($id);
if (!$report) {
    throw new moodle_exception('error_report_not_found', 'report_adeptus_insights');
}

$PAGE->set_title(format_string($report->name));
$PAGE->set_heading(format_string($report->name));

// Execute the report via backend.
$result = $client->execute_report($id);

// Handle CSV export.
if ($format === 'csv' && !empty($result->data)) {
    \report_adeptus_insights\builder_helper::export_csv($report->name, $result->columns ?? [], $result->data);
    exit;
}

// Prepare template data.
$columns = [];
if (!empty($result->columns)) {
    foreach ($result->columns as $col) {
        $columns[] = ['name' => $col];
    }
}

$rows = [];
if (!empty($result->data)) {
    foreach ($result->data as $row) {
        $cells = [];
        foreach ($result->columns as $col) {
            $cells[] = ['value' => $row->$col ?? ''];
        }
        $rows[] = ['cells' => $cells];
    }
}

$templatedata = [
    'reportname' => format_string($report->name),
    'reportdescription' => format_text($report->description ?? '', FORMAT_PLAIN),
    'hasresults' => !empty($rows),
    'columns' => $columns,
    'rows' => $rows,
    'rowcount' => count($rows),
    'executioncount' => $result->execution_count ?? 0,
    'csvurl' => (new moodle_url('/report/adeptus_insights/builder_report_view.php', [
        'id' => $id,
        'format' => 'csv',
    ]))->out(false),
    'editurl' => (new moodle_url('/report/adeptus_insights/builder_report_form.php', ['id' => $id]))->out(false),
    'listurl' => (new moodle_url('/report/adeptus_insights/builder_reports.php'))->out(false),
    'warnings' => !empty($result->warnings) ? $result->warnings : [],
    'haswarnings' => !empty($result->warnings),
    'sql' => $result->sql ?? '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_adeptus_insights/builder_report_view', $templatedata);
echo $OUTPUT->footer();
