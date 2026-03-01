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
 * Report Builder — list saved reports.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('report/adeptus_insights:usebuilder', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$reportid = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/builder_reports.php'));
$PAGE->set_title(get_string('builder_reports', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('builder_reports', 'report_adeptus_insights'));
$PAGE->set_pagelayout('report');

// Handle delete action.
if ($action === 'delete' && $reportid) {
    require_sesskey();
    if ($confirm) {
        $client = new \report_adeptus_insights\builder_api_client();
        $client->delete_report($reportid);
        redirect(
            new moodle_url('/report/adeptus_insights/builder_reports.php'),
            get_string('builder_report_deleted', 'report_adeptus_insights'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Fetch reports from backend.
$client = new \report_adeptus_insights\builder_api_client();
$reports = $client->list_reports();
$tierinfo = $client->get_tier_info();

// Prepare template data.
$templatedata = [
    'reports' => [],
    'createurl' => (new moodle_url('/report/adeptus_insights/builder_report_form.php'))->out(false),
    'cancreate' => $tierinfo['can_create'],
    'tierwarning' => $tierinfo['warning'] ?? '',
    'hasreports' => !empty($reports),
    'sesskey' => sesskey(),
];

foreach ($reports as $report) {
    $templatedata['reports'][] = [
        'id' => $report->id,
        'name' => format_string($report->name),
        'datasource' => $report->definition->entity ?? '-',
        'lastrun' => !empty($report->last_executed_at) ? userdate(strtotime($report->last_executed_at)) : '-',
        'executioncount' => $report->execution_count ?? 0,
        'editurl' => (new moodle_url('/report/adeptus_insights/builder_report_form.php', ['id' => $report->id]))->out(false),
        'viewurl' => (new moodle_url('/report/adeptus_insights/builder_report_view.php', ['id' => $report->id]))->out(false),
        'deleteurl' => (new moodle_url('/report/adeptus_insights/builder_reports.php', [
            'action' => 'delete',
            'id' => $report->id,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_adeptus_insights/builder_reports_list', $templatedata);
echo $OUTPUT->footer();
