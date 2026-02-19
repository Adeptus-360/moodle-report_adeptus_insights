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
 * Alert rules management page.
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
$action = optional_param('action', '', PARAM_ALPHA);
$ruleid = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/alert_rules.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('alert_rules', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('alert_rules', 'report_adeptus_insights'));
$PAGE->set_pagelayout('report');

$canmanage = has_capability('report/adeptus_insights:managealerts', $context);

// Handle actions.
if ($action === 'delete' && $ruleid && $canmanage) {
    require_sesskey();
    if ($confirm) {
        $DB->delete_records('report_adeptus_alert_rules', ['id' => $ruleid]);
        // Also delete associated logs.
        $DB->delete_records('report_adeptus_alert_logs', ['rule_id' => $ruleid]);
        redirect(
            new moodle_url('/report/adeptus_insights/alert_rules.php', ['courseid' => $courseid]),
            get_string('alert_rule_deleted', 'report_adeptus_insights'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

if ($action === 'toggle' && $ruleid && $canmanage) {
    require_sesskey();
    $rule = $DB->get_record('report_adeptus_alert_rules', ['id' => $ruleid], '*', MUST_EXIST);
    $rule->enabled = $rule->enabled ? 0 : 1;
    $rule->timemodified = time();
    $DB->update_record('report_adeptus_alert_rules', $rule);
    redirect(
        new moodle_url('/report/adeptus_insights/alert_rules.php', ['courseid' => $courseid]),
        get_string('alert_rule_updated', 'report_adeptus_insights'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

// Delete confirmation.
if ($action === 'delete' && $ruleid && $canmanage && !$confirm) {
    $rule = $DB->get_record('report_adeptus_alert_rules', ['id' => $ruleid], '*', MUST_EXIST);
    echo $OUTPUT->confirm(
        get_string('alert_rule_delete_confirm', 'report_adeptus_insights', $rule->name),
        new moodle_url('/report/adeptus_insights/alert_rules.php', [
            'action' => 'delete', 'id' => $ruleid, 'confirm' => 1,
            'courseid' => $courseid, 'sesskey' => sesskey(),
        ]),
        new moodle_url('/report/adeptus_insights/alert_rules.php', ['courseid' => $courseid])
    );
    echo $OUTPUT->footer();
    exit;
}

// Add new rule button.
if ($canmanage) {
    $addurl = new moodle_url('/report/adeptus_insights/alert_rule_form.php', ['courseid' => $courseid]);
    echo html_writer::div(
        html_writer::link($addurl, get_string('alert_rule_add', 'report_adeptus_insights'), [
            'class' => 'btn btn-primary mb-3',
        ]),
        'mb-3'
    );
}

// Navigation links.
$logsurl = new moodle_url('/report/adeptus_insights/alert_logs.php', ['courseid' => $courseid]);
echo html_writer::div(
    html_writer::link($logsurl, get_string('alert_logs', 'report_adeptus_insights'), [
        'class' => 'btn btn-outline-secondary mb-3 ml-2',
    ]) . ' ' .
    html_writer::link(
        new moodle_url('/report/adeptus_insights/index.php'),
        get_string('back_to_dashboard', 'report_adeptus_insights'),
        ['class' => 'btn btn-outline-secondary mb-3 ml-2']
    ),
    'mb-3'
);

// Fetch rules.
$params = [];
$sql = "SELECT r.*, (SELECT MAX(l.timecreated) FROM {report_adeptus_alert_logs} l WHERE l.rule_id = r.id) AS last_triggered
        FROM {report_adeptus_alert_rules} r";
if ($courseid) {
    $sql .= " WHERE r.course_id = :courseid OR r.course_id IS NULL";
    $params['courseid'] = $courseid;
}
$sql .= " ORDER BY r.timecreated DESC";
$rules = $DB->get_records_sql($sql, $params);

if (empty($rules)) {
    echo $OUTPUT->notification(get_string('alert_no_rules', 'report_adeptus_insights'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('alert_rule_type', 'report_adeptus_insights'),
        get_string('alert_rule_threshold', 'report_adeptus_insights'),
        get_string('course'),
        get_string('alert_rule_enabled', 'report_adeptus_insights'),
        get_string('alert_rule_last_triggered', 'report_adeptus_insights'),
    ];
    if ($canmanage) {
        $table->head[] = get_string('actions');
    }
    $table->attributes['class'] = 'generaltable';

    $ruletypes = [
        'grade_below' => get_string('alert_rule_type_grade_below', 'report_adeptus_insights'),
        'completion_stalled' => get_string('alert_rule_type_completion_stalled', 'report_adeptus_insights'),
        'inactive_days' => get_string('alert_rule_type_inactive_days', 'report_adeptus_insights'),
        'login_gap' => get_string('alert_rule_type_login_gap', 'report_adeptus_insights'),
    ];

    foreach ($rules as $rule) {
        $row = [];
        $row[] = format_string($rule->name);
        $row[] = $ruletypes[$rule->rule_type] ?? $rule->rule_type;
        $row[] = format_float($rule->threshold, 2);

        // Course name.
        if ($rule->course_id) {
            $course = $DB->get_record('course', ['id' => $rule->course_id], 'id,fullname');
            $row[] = $course ? format_string($course->fullname) : get_string('all');
        } else {
            $row[] = get_string('all');
        }

        // Enabled status.
        if ($canmanage) {
            $toggleurl = new moodle_url('/report/adeptus_insights/alert_rules.php', [
                'action' => 'toggle', 'id' => $rule->id, 'courseid' => $courseid, 'sesskey' => sesskey(),
            ]);
            $icon = $rule->enabled ? 'i/hide' : 'i/show';
            $label = $rule->enabled
                ? get_string('alert_rule_status_enabled', 'report_adeptus_insights')
                : get_string('alert_rule_status_disabled', 'report_adeptus_insights');
            $row[] = html_writer::link($toggleurl, $OUTPUT->pix_icon($icon, $label) . ' ' . $label);
        } else {
            $row[] = $rule->enabled
                ? get_string('alert_rule_status_enabled', 'report_adeptus_insights')
                : get_string('alert_rule_status_disabled', 'report_adeptus_insights');
        }

        // Last triggered.
        $row[] = $rule->last_triggered ? userdate($rule->last_triggered) : get_string('never');

        // Actions.
        if ($canmanage) {
            $actions = [];
            $editurl = new moodle_url('/report/adeptus_insights/alert_rule_form.php', [
                'id' => $rule->id, 'courseid' => $courseid,
            ]);
            $actions[] = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));

            $deleteurl = new moodle_url('/report/adeptus_insights/alert_rules.php', [
                'action' => 'delete', 'id' => $rule->id, 'courseid' => $courseid, 'sesskey' => sesskey(),
            ]);
            $actions[] = html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));

            $row[] = implode(' ', $actions);
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
