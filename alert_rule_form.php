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
 * Alert rule create/edit page.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('report/adeptus_insights:managealerts', $context);

$ruleid = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/alert_rule_form.php', [
    'id' => $ruleid, 'courseid' => $courseid,
]));
$PAGE->set_pagelayout('report');

$returnurl = new moodle_url('/report/adeptus_insights/alert_rules.php', ['courseid' => $courseid]);

if ($ruleid) {
    $rule = $DB->get_record('report_adeptus_alert_rules', ['id' => $ruleid], '*', MUST_EXIST);
    $PAGE->set_title(get_string('alert_rule_edit', 'report_adeptus_insights'));
    $PAGE->set_heading(get_string('alert_rule_edit', 'report_adeptus_insights'));
} else {
    $rule = new stdClass();
    $rule->id = 0;
    $rule->courseid = $courseid;
    $PAGE->set_title(get_string('alert_rule_add', 'report_adeptus_insights'));
    $PAGE->set_heading(get_string('alert_rule_add', 'report_adeptus_insights'));
}

$form = new \report_adeptus_insights\form\alert_rule_form(null, null, 'post', '', null, true, [
    'id' => $ruleid,
    'courseid' => $courseid,
]);

// Set form defaults for editing.
if ($ruleid && $rule) {
    $defaults = (array) $rule;
    // Decode notify_roles JSON to array for multi-select.
    if (!empty($rule->notify_roles)) {
        $defaults['notify_roles'] = json_decode($rule->notify_roles, true) ?: [];
    }
    $form->set_data($defaults);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $now = time();

    $record = new stdClass();
    $record->name = $data->name;
    $record->rule_type = $data->rule_type;
    $record->threshold = $data->threshold;
    $record->enabled = !empty($data->enabled) ? 1 : 0;
    $record->course_id = !empty($data->course_id) ? $data->course_id : null;
    $record->notify_roles = !empty($data->notify_roles) ? json_encode(array_values($data->notify_roles)) : null;
    $record->timemodified = $now;

    if ($data->id) {
        $record->id = $data->id;
        $DB->update_record('report_adeptus_alert_rules', $record);
        $message = get_string('alert_rule_updated', 'report_adeptus_insights');
    } else {
        $record->created_by = $USER->id;
        $record->timecreated = $now;
        $DB->insert_record('report_adeptus_alert_rules', $record);
        $message = get_string('alert_rule_created', 'report_adeptus_insights');
    }

    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
