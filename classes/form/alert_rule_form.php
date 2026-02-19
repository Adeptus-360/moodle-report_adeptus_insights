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
 * Alert rule form class.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing alert rules.
 */
class alert_rule_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        // Hidden rule ID for editing.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', 0);
        $mform->setType('courseid', PARAM_INT);

        // Rule name.
        $mform->addElement('text', 'name', get_string('alert_rule_name', 'report_adeptus_insights'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Rule type.
        $types = [
            'grade_below' => get_string('alert_rule_type_grade_below', 'report_adeptus_insights'),
            'completion_stalled' => get_string('alert_rule_type_completion_stalled', 'report_adeptus_insights'),
            'inactive_days' => get_string('alert_rule_type_inactive_days', 'report_adeptus_insights'),
            'login_gap' => get_string('alert_rule_type_login_gap', 'report_adeptus_insights'),
        ];
        $mform->addElement('select', 'rule_type', get_string('alert_rule_type', 'report_adeptus_insights'), $types);
        $mform->addRule('rule_type', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('rule_type', 'alert_rule_type', 'report_adeptus_insights');

        // Threshold.
        $mform->addElement('text', 'threshold', get_string('alert_rule_threshold', 'report_adeptus_insights'), ['size' => 10]);
        $mform->setType('threshold', PARAM_FLOAT);
        $mform->addRule('threshold', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('threshold', 'alert_rule_threshold', 'report_adeptus_insights');

        // Enabled.
        $mform->addElement('advcheckbox', 'enabled', get_string('alert_rule_enabled', 'report_adeptus_insights'));
        $mform->setDefault('enabled', 1);

        // Course selector.
        $courses = [0 => get_string('alert_rule_all_courses', 'report_adeptus_insights')];
        $allcourses = get_courses();
        foreach ($allcourses as $c) {
            if ($c->id == SITEID) {
                continue;
            }
            $courses[$c->id] = format_string($c->fullname);
        }
        $mform->addElement('select', 'course_id', get_string('alert_rule_course', 'report_adeptus_insights'), $courses);

        // Notification recipients (role-based).
        $roles = [];
        $allroles = role_get_names(\context_system::instance(), ROLENAME_ORIGINAL);
        foreach ($allroles as $role) {
            $roles[$role->id] = $role->localname;
        }
        $select = $mform->addElement(
            'select',
            'notify_roles',
            get_string('alert_rule_notify_roles', 'report_adeptus_insights'),
            $roles
        );
        $select->setMultiple(true);
        $mform->addHelpButton('notify_roles', 'alert_rule_notify_roles', 'report_adeptus_insights');

        $this->add_action_buttons();
    }

    /**
     * Validate the form data.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['name'])) {
            $errors['name'] = get_string('required');
        }

        if (!is_numeric($data['threshold']) || $data['threshold'] < 0) {
            $errors['threshold'] = get_string('alert_rule_threshold_error', 'report_adeptus_insights');
        }

        return $errors;
    }
}
