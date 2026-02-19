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

namespace report_adeptus_insights\task;

use report_adeptus_insights\alert_engine;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: send at-risk learner digest emails.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_alert_digest extends \core\task\scheduled_task {

    /**
     * Return task name.
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_alert_digest', 'report_adeptus_insights');
    }

    /**
     * Execute the digest task.
     */
    public function execute(): void {
        global $CFG;

        // Feature gate: enterprise only.
        if (!alert_engine::is_available()) {
            mtrace('Alert digest: skipped — not enterprise tier.');
            return;
        }

        // Check enabled.
        if (!alert_engine::is_enabled()) {
            mtrace('Alert digest: disabled in settings.');
            return;
        }

        $engine = new alert_engine();

        // Deduplicate: check if already sent for this period.
        $period = $engine->get_current_period_key();
        if ($engine->was_digest_sent($period)) {
            mtrace("Alert digest: already sent for period {$period}.");
            return;
        }

        // Gather at-risk learners.
        $atrisk = $engine->get_at_risk_learners();
        $totallearners = 0;
        foreach ($atrisk as $group) {
            foreach ($group as $coursedata) {
                $totallearners += count($coursedata['learners']);
            }
        }

        if ($totallearners === 0) {
            mtrace('Alert digest: no at-risk learners found.');
            // Still log so we don't re-run.
            $engine->log_digest($period, 0, 0);
            return;
        }

        // Get recipients.
        $recipients = $engine->get_digest_recipients();
        if (empty($recipients)) {
            mtrace('Alert digest: no recipients configured.');
            $engine->log_digest($period, 0, $totallearners);
            return;
        }

        // Build email content.
        $html = $this->render_digest_email($atrisk, $period);
        $subject = get_string('alert_digest_subject', 'report_adeptus_insights', $period);

        // Create a noreply user for sending.
        $noreply = \core_user::get_noreply_user();

        $sentcount = 0;
        foreach ($recipients as $recipient) {
            $success = email_to_user(
                $recipient,
                $noreply,
                $subject,
                html_to_text($html),
                $html
            );
            if ($success) {
                $sentcount++;
            } else {
                mtrace("Alert digest: failed to email user {$recipient->id}.");
            }
        }

        $engine->log_digest($period, $sentcount, $totallearners);
        mtrace("Alert digest: sent to {$sentcount} recipients, {$totallearners} at-risk learners found.");
    }

    /**
     * Render the digest email HTML using the mustache template.
     *
     * @param array $atrisk At-risk data from alert_engine.
     * @param string $period Period key.
     * @return string HTML content.
     */
    protected function render_digest_email(array $atrisk, string $period): string {
        global $OUTPUT, $CFG;

        $inactivitydays = (int) get_config('report_adeptus_insights', 'alert_inactivity_days') ?: 14;
        $completionthreshold = (int) get_config('report_adeptus_insights', 'alert_completion_threshold') ?: 30;

        // Prepare template data.
        $inactivecourses = [];
        foreach ($atrisk['inactive'] as $coursedata) {
            $learners = [];
            foreach ($coursedata['learners'] as $l) {
                $lastaccess = (int) $l->lastaccess;
                $learners[] = [
                    'fullname' => fullname($l),
                    'email' => $l->email,
                    'lastaccess' => $lastaccess > 0 ? userdate($lastaccess) : get_string('never'),
                ];
            }
            $inactivecourses[] = [
                'coursename' => $coursedata['coursename'],
                'learners' => $learners,
                'learnercount' => count($learners),
            ];
        }

        $lowcompletioncourses = [];
        foreach ($atrisk['low_completion'] as $coursedata) {
            $learners = [];
            foreach ($coursedata['learners'] as $l) {
                $learners[] = [
                    'fullname' => fullname($l),
                    'email' => $l->email,
                    'completion_pct' => $l->completion_pct ?? 0,
                ];
            }
            $lowcompletioncourses[] = [
                'coursename' => $coursedata['coursename'],
                'learners' => $learners,
                'learnercount' => count($learners),
            ];
        }

        $data = [
            'period' => $period,
            'siteurl' => $CFG->wwwroot,
            'inactivity_days' => $inactivitydays,
            'completion_threshold' => $completionthreshold,
            'has_inactive' => !empty($inactivecourses),
            'inactive_courses' => $inactivecourses,
            'has_low_completion' => !empty($lowcompletioncourses),
            'low_completion_courses' => $lowcompletioncourses,
            'generated_at' => userdate(time()),
        ];

        return $OUTPUT->render_from_template('report_adeptus_insights/alert_digest_email', $data);
    }
}
