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
 * Alert logs viewing page.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('report/adeptus_insights:viewalerts', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$ruleid = optional_param('ruleid', 0, PARAM_INT);
$datefrom = optional_param('datefrom', 0, PARAM_INT);
$dateto = optional_param('dateto', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/alert_logs.php', [
    'courseid' => $courseid, 'ruleid' => $ruleid, 'page' => $page,
]));
$PAGE->set_title(get_string('alert_logs', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('alert_logs', 'report_adeptus_insights'));
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

// Navigation.
$rulesurl = new moodle_url('/report/adeptus_insights/alert_rules.php', ['courseid' => $courseid]);
echo html_writer::div(
    html_writer::link($rulesurl, get_string('alert_rules', 'report_adeptus_insights'), [
        'class' => 'btn btn-outline-secondary mb-3',
    ]) . ' ' .
    html_writer::link(
        new moodle_url('/report/adeptus_insights/index.php'),
        get_string('back_to_dashboard', 'report_adeptus_insights'),
        ['class' => 'btn btn-outline-secondary mb-3 ml-2']
    ),
    'mb-3'
);

// Filter by rule.
$allrules = $DB->get_records('report_adeptus_alert_rules', null, 'name ASC', 'id, name');
$ruleoptions = [0 => get_string('all')];
foreach ($allrules as $r) {
    $ruleoptions[$r->id] = format_string($r->name);
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'form-inline mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::label(get_string('alert_logs_filter_rule', 'report_adeptus_insights') . ': ', 'ruleid', true, ['class' => 'mr-2']);
echo html_writer::select($ruleoptions, 'ruleid', $ruleid, false, ['class' => 'form-control mr-3']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('alert_logs_filter', 'report_adeptus_insights'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

// Build query.
$where = [];
$params = [];
if ($ruleid) {
    $where[] = "l.rule_id = :ruleid";
    $params['ruleid'] = $ruleid;
}
if ($courseid) {
    $where[] = "(l.course_id = :courseid OR l.course_id IS NULL)";
    $params['courseid'] = $courseid;
}
if ($datefrom) {
    $where[] = "l.timecreated >= :datefrom";
    $params['datefrom'] = $datefrom;
}
if ($dateto) {
    $where[] = "l.timecreated <= :dateto";
    $params['dateto'] = $dateto;
}

$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countsql = "SELECT COUNT(*) FROM {report_adeptus_alert_logs} l $wheresql";
$total = $DB->count_records_sql($countsql, $params);

$sql = "SELECT l.*, r.name AS rule_name, r.rule_type,
               u.firstname, u.lastname, u.email,
               c.fullname AS coursename
        FROM {report_adeptus_alert_logs} l
        LEFT JOIN {report_adeptus_alert_rules} r ON r.id = l.rule_id
        LEFT JOIN {user} u ON u.id = l.user_id
        LEFT JOIN {course} c ON c.id = l.course_id
        $wheresql
        ORDER BY l.timecreated DESC";

$logs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

if (empty($logs)) {
    echo $OUTPUT->notification(get_string('alert_logs_empty', 'report_adeptus_insights'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('date'),
        get_string('alert_rule_name', 'report_adeptus_insights'),
        get_string('alert_rule_type', 'report_adeptus_insights'),
        get_string('alert_logs_user', 'report_adeptus_insights'),
        get_string('course'),
        get_string('alert_logs_value', 'report_adeptus_insights'),
        get_string('alert_logs_notified', 'report_adeptus_insights'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($logs as $log) {
        $row = [];
        $row[] = userdate($log->timecreated);
        $row[] = format_string($log->rule_name ?? '-');
        $row[] = $log->rule_type ?? '-';
        $row[] = $log->firstname ? fullname($log) : get_string('alert_logs_unknown_user', 'report_adeptus_insights');
        $row[] = $log->coursename ? format_string($log->coursename) : '-';
        $row[] = $log->triggered_value !== null ? format_float($log->triggered_value, 2) : '-';
        $row[] = $log->notified
            ? get_string('yes')
            : get_string('no');
        $table->data[] = $row;
    }

    echo html_writer::table($table);

    // Pagination.
    $baseurl = new moodle_url('/report/adeptus_insights/alert_logs.php', [
        'courseid' => $courseid, 'ruleid' => $ruleid,
    ]);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
