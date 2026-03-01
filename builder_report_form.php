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
 * Report Builder — create/edit a custom report.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('report/adeptus_insights:usebuilder', $context);

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/builder_report_form.php', ['id' => $id]));
$PAGE->set_pagelayout('report');

$client = new \report_adeptus_insights\builder_api_client();

// Load existing report if editing.
$report = null;
if ($id) {
    $report = $client->get_report($id);
    $PAGE->set_title(get_string('builder_edit_report', 'report_adeptus_insights'));
    $PAGE->set_heading(get_string('builder_edit_report', 'report_adeptus_insights'));
} else {
    $PAGE->set_title(get_string('builder_create_report', 'report_adeptus_insights'));
    $PAGE->set_heading(get_string('builder_create_report', 'report_adeptus_insights'));
}

// Fetch data catalog for the form.
$catalog = $client->get_data_catalog();

// Create and handle the form.
$form = new \report_adeptus_insights\form\builder_report_form(null, [
    'id' => $id,
    'catalog' => $catalog,
    'report' => $report,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/report/adeptus_insights/builder_reports.php'));
}

if ($data = $form->get_data()) {
    // Build the definition JSON from form data.
    $definition = \report_adeptus_insights\builder_helper::build_definition_from_form($data, $catalog);

    $payload = [
        'name' => $data->name,
        'description' => $data->description ?? '',
        'definition' => $definition,
        'user_id' => (int) $USER->id,
    ];

    if ($id) {
        $client->update_report($id, $payload);
        $message = get_string('builder_report_updated', 'report_adeptus_insights');
    } else {
        $result = $client->create_report($payload);
        $id = $result->id ?? 0;
        $message = get_string('builder_report_created', 'report_adeptus_insights');
    }

    redirect(
        new moodle_url('/report/adeptus_insights/builder_reports.php'),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load AMD module for dynamic UI.
$PAGE->requires->js_call_amd('report_adeptus_insights/builder', 'init', [
    'catalogJson' => json_encode($catalog),
    'reportJson' => $report ? json_encode($report) : '{}',
]);

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
