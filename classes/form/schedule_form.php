<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace report_adeptus_insights\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing a scheduled report.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;
        $reports = $this->_customdata['reports'] ?? [];
        $roles = $this->_customdata['roles'] ?? [];

        // Hidden ID for editing.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // ── Section 1: Report ──────────────────────────────────────────────
        $mform->addElement('header', 'reporthdr', get_string('report', 'report_adeptus_insights'));

        // Report selector.
        $reportoptions = ['' => get_string('select_a_report', 'report_adeptus_insights')];
        foreach ($reports as $report) {
            $name = $report->name ?? $report->reportid ?? '';
            $label = $report->label ?? $report->title ?? $name;
            $reportoptions[$name] = $label;
        }
        $mform->addElement('select', 'reportid', get_string('report_name', 'report_adeptus_insights'), $reportoptions);
        $mform->addRule('reportid', null, 'required', null, 'client');
        $mform->setType('reportid', PARAM_TEXT);

        // Schedule label.
        $mform->addElement('text', 'label', get_string('schedule_label', 'report_adeptus_insights'), ['size' => 60]);
        $mform->setType('label', PARAM_TEXT);
        $mform->addRule('label', null, 'required', null, 'client');
        $mform->addHelpButton('label', 'schedule_label', 'report_adeptus_insights');

        // ── Section 2: Frequency ───────────────────────────────────────────
        $mform->addElement('header', 'frequencyhdr', get_string('frequency', 'report_adeptus_insights'));

        $freqoptions = [
            'daily' => get_string('frequency_daily', 'report_adeptus_insights'),
            'weekly' => get_string('frequency_weekly', 'report_adeptus_insights'),
            'monthly' => get_string('frequency_monthly', 'report_adeptus_insights'),
        ];
        $mform->addElement('select', 'frequency', get_string('frequency', 'report_adeptus_insights'), $freqoptions);
        $mform->setDefault('frequency', 'weekly');
        $mform->setType('frequency', PARAM_ALPHA);

        // Hour picker.
        $hours = [];
        for ($h = 0; $h <= 23; $h++) {
            $hours[$h] = sprintf('%02d:00', $h);
        }
        $mform->addElement('select', 'run_hour', get_string('run_hour', 'report_adeptus_insights'), $hours);
        $mform->setDefault('run_hour', 7);
        $mform->addHelpButton('run_hour', 'run_hour', 'report_adeptus_insights');
        $mform->setType('run_hour', PARAM_INT);

        // Day of week (shown for weekly).
        $daysoptions = [
            0 => get_string('sunday', 'calendar'),
            1 => get_string('monday', 'calendar'),
            2 => get_string('tuesday', 'calendar'),
            3 => get_string('wednesday', 'calendar'),
            4 => get_string('thursday', 'calendar'),
            5 => get_string('friday', 'calendar'),
            6 => get_string('saturday', 'calendar'),
        ];
        $mform->addElement('select', 'run_dayofweek', get_string('run_dayofweek', 'report_adeptus_insights'), $daysoptions);
        $mform->setDefault('run_dayofweek', 1);
        $mform->hideIf('run_dayofweek', 'frequency', 'neq', 'weekly');
        $mform->setType('run_dayofweek', PARAM_INT);

        // Day of month (shown for monthly).
        $monthdayoptions = [];
        for ($d = 1; $d <= 28; $d++) {
            $monthdayoptions[$d] = $d;
        }
        $mform->addElement('select', 'run_dayofmonth', get_string('run_dayofmonth', 'report_adeptus_insights'), $monthdayoptions);
        $mform->setDefault('run_dayofmonth', 1);
        $mform->addHelpButton('run_dayofmonth', 'run_dayofmonth', 'report_adeptus_insights');
        $mform->hideIf('run_dayofmonth', 'frequency', 'neq', 'monthly');
        $mform->setType('run_dayofmonth', PARAM_INT);

        // ── Section 3: Export Format ───────────────────────────────────────
        $mform->addElement('header', 'formathdr', get_string('export', 'report_adeptus_insights'));

        $formatoptions = [
            'csv' => 'CSV',
            'pdf' => 'PDF',
        ];
        $mform->addElement('select', 'export_format', get_string('export', 'report_adeptus_insights'), $formatoptions);
        $mform->setDefault('export_format', 'csv');
        $mform->setType('export_format', PARAM_ALPHA);

        // ── Section 4: Email ───────────────────────────────────────────────
        $mform->addElement('header', 'emailhdr', get_string('email', 'report_adeptus_insights'));

        $mform->addElement('text', 'email_subject', get_string('email_subject', 'report_adeptus_insights'), ['size' => 80]);
        $mform->setType('email_subject', PARAM_TEXT);

        $mform->addElement('textarea', 'email_body', get_string('email_body', 'report_adeptus_insights'),
            ['rows' => 5, 'cols' => 80]);
        $mform->setType('email_body', PARAM_TEXT);

        // ── Section 5: Recipients ──────────────────────────────────────────
        $mform->addElement('header', 'recipientshdr', get_string('recipients', 'report_adeptus_insights'));

        // Ad-hoc email addresses.
        $mform->addElement('textarea', 'recipients_emails',
            get_string('recipients_email', 'report_adeptus_insights'),
            ['rows' => 4, 'cols' => 60]);
        $mform->addHelpButton('recipients_emails', 'recipients_email', 'report_adeptus_insights');
        $mform->setType('recipients_emails', PARAM_TEXT);

        // Moodle users autocomplete (multi-select).
        $useroptions = [];
        $mform->addElement('autocomplete', 'recipients_userids',
            get_string('recipients_users', 'report_adeptus_insights'),
            $useroptions,
            [
                'multiple' => true,
                'ajax' => 'core_user/form_user_selector',
                'valuehtmlcallback' => function($userid) {
                    global $DB, $OUTPUT;
                    $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
                    if (!$user) {
                        return false;
                    }
                    return fullname($user) . ' (' . $user->email . ')';
                },
            ]
        );
        $mform->addHelpButton('recipients_userids', 'recipients_users', 'report_adeptus_insights');
        $mform->setType('recipients_userids', PARAM_INT);

        // Role-based recipients.
        if (!empty($roles)) {
            $roleoptions = ['' => ''];
            foreach ($roles as $role) {
                $roleoptions[$role->id] = role_get_name($role);
            }
            $mform->addElement('select', 'recipients_roleid',
                get_string('recipients_role', 'report_adeptus_insights'), $roleoptions);
            $mform->addHelpButton('recipients_roleid', 'recipients_role', 'report_adeptus_insights');
            $mform->setType('recipients_roleid', PARAM_INT);
        }

        // ── Active toggle ──────────────────────────────────────────────────
        $mform->addElement('header', 'statushdr', get_string('status', 'report_adeptus_insights'));
        $mform->addElement('advcheckbox', 'active',
            get_string('schedule_status_active', 'report_adeptus_insights'));
        $mform->setDefault('active', 1);
        $mform->setType('active', PARAM_INT);

        // ── Buttons ────────────────────────────────────────────────────────
        $this->add_action_buttons(true, get_string('save', 'report_adeptus_insights'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data Form data.
     * @param array $files File data.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['reportid'])) {
            $errors['reportid'] = get_string('error_missing_required_field', 'report_adeptus_insights', 'reportid');
        }

        if (empty($data['label'])) {
            $errors['label'] = get_string('error_missing_required_field', 'report_adeptus_insights', 'label');
        }

        if ($data['run_hour'] < 0 || $data['run_hour'] > 23) {
            $errors['run_hour'] = get_string('error_invalid_input', 'report_adeptus_insights');
        }

        if ($data['frequency'] === 'monthly') {
            $dom = $data['run_dayofmonth'] ?? 0;
            if ($dom < 1 || $dom > 28) {
                $errors['run_dayofmonth'] = get_string('error_invalid_input', 'report_adeptus_insights');
            }
        }

        if (!in_array($data['export_format'], ['csv', 'pdf'])) {
            $errors['export_format'] = get_string('error_invalid_input', 'report_adeptus_insights');
        }

        // Check at least one recipient.
        $hasemails = !empty(trim($data['recipients_emails'] ?? ''));
        $hasusers = !empty($data['recipients_userids']);
        $hasrole = !empty($data['recipients_roleid'] ?? 0);
        if (!$hasemails && !$hasusers && !$hasrole) {
            $errors['recipients_emails'] = get_string('recipients_none', 'report_adeptus_insights');
        }

        // Validate email addresses if provided.
        if ($hasemails) {
            $raw = $data['recipients_emails'];
            $emails = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($emails as $email) {
                if (!validate_email(trim($email))) {
                    $errors['recipients_emails'] = get_string('error_invalid_email', 'report_adeptus_insights');
                    break;
                }
            }
        }

        return $errors;
    }
}
