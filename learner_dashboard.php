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
 * Learner Dashboard — Personal progress view for students.
 *
 * Displays enrolled courses, completion progress, time spent, grades,
 * and activity completion details. Scoped to $USER->id for privacy.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Force Boost theme for consistent plugin UI.
$CFG->theme = 'boost';

require_login();

global $USER, $PAGE, $OUTPUT;

$context = context_system::instance();
require_capability('report/adeptus_insights:viewlearnerdashboard', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/learner_dashboard.php'));
$PAGE->set_title(get_string('learner_dashboard_title', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('learner_dashboard_title', 'report_adeptus_insights'));
$PAGE->set_pagelayout('report');

// Load required CSS.
$PAGE->requires->css('/report/adeptus_insights/styles.css');

echo $OUTPUT->header();

try {
    // Build the learner dashboard data.
    $learnerdashboard = new \report_adeptus_insights\learner_dashboard($USER->id);
    $dashboarddata = $learnerdashboard->get_template_data();

    // Add learner reports for the reports card section.
    $learnerreports = \report_adeptus_insights\role_helper::get_learner_reports($USER->id);
    $dashboarddata['has_learner_reports'] = !empty($learnerreports);
    $dashboarddata['learner_reports'] = array_map(function($report) {
        return [
            'key' => $report['key'],
            'title' => get_string($report['titlekey'], 'report_adeptus_insights'),
            'description' => get_string($report['desckey'], 'report_adeptus_insights'),
            'icon' => $report['icon'],
            'params_json' => json_encode($report['params']),
        ];
    }, $learnerreports);

    // Render the learner dashboard template.
    echo $OUTPUT->render_from_template('report_adeptus_insights/learner_dashboard', $dashboarddata);

} catch (\dml_exception $e) {
    echo $OUTPUT->notification(
        get_string('error_database', 'report_adeptus_insights'),
        \core\output\notification::NOTIFY_ERROR
    );
    debugging('Database error in learner dashboard: ' . $e->getMessage(), DEBUG_DEVELOPER);
} catch (Exception $e) {
    echo $OUTPUT->notification(
        get_string('error_generic', 'report_adeptus_insights'),
        \core\output\notification::NOTIFY_ERROR
    );
    debugging('Error in learner dashboard: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

echo $OUTPUT->footer();
