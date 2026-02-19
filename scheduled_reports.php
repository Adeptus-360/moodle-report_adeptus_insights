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
 * Scheduled Reports management page for Adeptus Insights.
 *
 * Lists all scheduled reports with create/edit/delete/toggle actions.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$CFG->theme = 'boost';

require_once($CFG->libdir . '/adminlib.php');

require_login();

$context = context_system::instance();
require_capability('report/adeptus_insights:viewschedules', $context);

$canmanage = has_capability('report/adeptus_insights:manageschedules', $context);

// Handle actions.
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if ($action && $canmanage) {
    require_sesskey();

    switch ($action) {
        case 'delete':
            if ($id) {
                $DB->delete_records('report_adeptus_insights_sched_recip', ['scheduleid' => $id]);
                $DB->delete_records('report_adeptus_insights_sched_log', ['scheduleid' => $id]);
                $DB->delete_records('report_adeptus_insights_schedules', ['id' => $id]);
                \core\notification::add(
                    get_string('schedule_deleted', 'report_adeptus_insights'),
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            redirect(new moodle_url('/report/adeptus_insights/scheduled_reports.php'));
            break;

        case 'toggle':
            if ($id) {
                $schedule = $DB->get_record('report_adeptus_insights_schedules', ['id' => $id], '*', MUST_EXIST);
                $newactive = $schedule->active ? 0 : 1;
                $DB->update_record('report_adeptus_insights_schedules', (object) [
                    'id' => $id,
                    'active' => $newactive,
                    'timemodified' => time(),
                ]);
            }
            redirect(new moodle_url('/report/adeptus_insights/scheduled_reports.php'));
            break;
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/scheduled_reports.php'));
$PAGE->set_title(get_string('scheduled_reports', 'report_adeptus_insights'));
$PAGE->set_pagelayout('report');
$PAGE->requires->css('/report/adeptus_insights/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('scheduled_reports', 'report_adeptus_insights'));

// Create button.
if ($canmanage) {
    $createurl = new moodle_url('/report/adeptus_insights/schedule_form.php');
    echo html_writer::div(
        $OUTPUT->single_button($createurl, get_string('schedule_create', 'report_adeptus_insights'), 'get'),
        'mb-3'
    );
}

// Fetch all schedules.
$schedules = $DB->get_records('report_adeptus_insights_schedules', null, 'timecreated DESC');

if (empty($schedules)) {
    echo $OUTPUT->notification(
        get_string('no_schedules_found', 'report_adeptus_insights'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    // Build table.
    $table = new html_table();
    $table->head = [
        get_string('schedule_label', 'report_adeptus_insights'),
        get_string('report_name', 'report_adeptus_insights'),
        get_string('frequency', 'report_adeptus_insights'),
        get_string('schedule_next_run', 'report_adeptus_insights'),
        get_string('recipients', 'report_adeptus_insights'),
        get_string('status', 'report_adeptus_insights'),
    ];
    if ($canmanage) {
        $table->head[] = get_string('actions', 'moodle');
    }
    $table->attributes['class'] = 'generaltable';

    foreach ($schedules as $schedule) {
        // Recipient count.
        $recipcount = $DB->count_records('report_adeptus_insights_sched_recip', ['scheduleid' => $schedule->id]);

        // Frequency display.
        $freqstr = get_string('frequency_' . $schedule->frequency, 'report_adeptus_insights');
        if ($schedule->frequency === 'weekly' && $schedule->run_dayofweek !== null) {
            $days = [
                get_string('sunday', 'calendar'),
                get_string('monday', 'calendar'),
                get_string('tuesday', 'calendar'),
                get_string('wednesday', 'calendar'),
                get_string('thursday', 'calendar'),
                get_string('friday', 'calendar'),
                get_string('saturday', 'calendar'),
            ];
            $freqstr .= ' (' . ($days[$schedule->run_dayofweek] ?? '') . ')';
        } else if ($schedule->frequency === 'monthly' && $schedule->run_dayofmonth !== null) {
            $freqstr .= ' (' . get_string('run_dayofmonth', 'report_adeptus_insights') . ': ' . $schedule->run_dayofmonth . ')';
        }
        $freqstr .= ' @ ' . sprintf('%02d:00', $schedule->run_hour);

        // Next run.
        $nextrun = $schedule->next_run ? userdate($schedule->next_run) : '-';

        // Status badge.
        if (!$schedule->active && $schedule->failure_count >= 3) {
            $statusclass = 'badge badge-danger';
            $statustext = get_string('schedule_status_failed', 'report_adeptus_insights');
        } else if (!$schedule->active) {
            $statusclass = 'badge badge-warning';
            $statustext = get_string('schedule_status_paused', 'report_adeptus_insights');
        } else if (empty($schedule->last_run)) {
            $statusclass = 'badge badge-secondary';
            $statustext = get_string('schedule_status_never_run', 'report_adeptus_insights');
        } else {
            $statusclass = 'badge badge-success';
            $statustext = get_string('schedule_status_active', 'report_adeptus_insights');
        }
        $statushtml = html_writer::span($statustext, $statusclass);

        $row = [
            format_string($schedule->label),
            format_string($schedule->reportid),
            $freqstr,
            $nextrun,
            $recipcount,
            $statushtml,
        ];

        if ($canmanage) {
            $actions = [];

            // Edit.
            $editurl = new moodle_url('/report/adeptus_insights/schedule_form.php', ['id' => $schedule->id]);
            $actions[] = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit')));

            // Toggle active/paused.
            $toggleurl = new moodle_url('/report/adeptus_insights/scheduled_reports.php', [
                'action' => 'toggle',
                'id' => $schedule->id,
                'sesskey' => sesskey(),
            ]);
            if ($schedule->active) {
                $actions[] = $OUTPUT->action_icon($toggleurl, new pix_icon('t/hide', get_string('schedule_status_paused', 'report_adeptus_insights')));
            } else {
                $actions[] = $OUTPUT->action_icon($toggleurl, new pix_icon('t/show', get_string('schedule_status_active', 'report_adeptus_insights')));
            }

            // Delete.
            $deleteurl = new moodle_url('/report/adeptus_insights/scheduled_reports.php', [
                'action' => 'delete',
                'id' => $schedule->id,
                'sesskey' => sesskey(),
            ]);
            $actions[] = $OUTPUT->action_icon(
                $deleteurl,
                new pix_icon('t/delete', get_string('delete')),
                new confirm_action(get_string('schedule_delete_confirm', 'report_adeptus_insights'))
            );

            $row[] = implode(' ', $actions);
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
