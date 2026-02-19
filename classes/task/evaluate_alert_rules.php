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
 * Scheduled task to evaluate rule-based alert triggers.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Hourly task that evaluates alert rules and sends notifications for new alerts.
 */
class evaluate_alert_rules extends \core\task\scheduled_task {

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_evaluate_alert_rules', 'report_adeptus_insights');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $engine = new \report_adeptus_insights\alert\rule_engine();
        $newalerts = $engine::evaluate_all();

        mtrace("Rule engine evaluated — {$newalerts} new alert(s) logged.");

        if ($newalerts === 0) {
            return;
        }

        // Send notifications for pending alerts.
        $logs = $engine::get_and_mark_pending();
        $sent = 0;

        foreach ($logs as $log) {
            $rule = $DB->get_record('report_adeptus_alert_rules', ['id' => $log->rule_id]);
            if (!$rule) {
                continue;
            }

            $user = $DB->get_record('user', ['id' => $log->user_id]);
            if (!$user) {
                continue;
            }

            $coursename = '';
            if (!empty($log->course_id)) {
                $course = $DB->get_record('course', ['id' => $log->course_id], 'id,fullname');
                $coursename = $course ? $course->fullname : "ID {$log->course_id}";
            }

            // Build notification message.
            $subject = get_string('alert_rule_triggered_subject', 'report_adeptus_insights', $rule->name);
            $body = get_string('alert_rule_triggered_body', 'report_adeptus_insights', (object)[
                'rulename' => $rule->name,
                'ruletype' => $rule->rule_type,
                'username' => fullname($user),
                'coursename' => $coursename,
                'value' => round($log->triggered_value, 2),
                'threshold' => round($rule->threshold, 2),
            ]);

            // Determine recipients from notify_roles.
            $recipientroleids = !empty($rule->notify_roles) ? json_decode($rule->notify_roles, true) : [];
            if (empty($recipientroleids)) {
                // Default: notify managers at system level.
                $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
                if ($managerrole) {
                    $recipientroleids = [(int)$managerrole->id];
                }
            }

            // Get users with the specified roles.
            $recipients = [];
            $systemcontext = \context_system::instance();
            foreach ($recipientroleids as $roleid) {
                $roleusers = get_role_users($roleid, $systemcontext, false, 'u.id, u.email, u.firstname, u.lastname');
                foreach ($roleusers as $roleuser) {
                    $recipients[$roleuser->id] = $roleuser;
                }
            }

            // Send Moodle notification to each recipient.
            foreach ($recipients as $recipient) {
                $message = new \core\message\message();
                $message->component = 'report_adeptus_insights';
                $message->name = 'alert_rule_triggered';
                $message->userfrom = \core_user::get_noreply_user();
                $message->userto = $recipient;
                $message->subject = $subject;
                $message->fullmessage = $body;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = '<p>' . nl2br(s($body)) . '</p>';
                $message->smallmessage = $subject;
                $message->notification = 1;

                try {
                    message_send($message);
                    $sent++;
                } catch (\Exception $e) {
                    mtrace("Failed to notify user {$recipient->id}: " . $e->getMessage());
                }
            }
        }

        mtrace("Sent {$sent} notification(s) for rule-based alerts.");
    }
}
