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

namespace report_adeptus_insights\task;

use core\lock\lock_config;
use report_adeptus_insights\report_executor;

/**
 * Scheduled task to send scheduled reports via email.
 *
 * Runs hourly, checks for due schedules, generates CSV exports,
 * and emails them to configured recipients.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_scheduled_reports extends \core\task\scheduled_task {

    /** @var int Maximum consecutive failures before auto-pausing a schedule. */
    const MAX_FAILURES = 3;

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_scheduled_reports', 'report_adeptus_insights');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute(): void {
        global $CFG;

        // Check global enable setting.
        if (!get_config('report_adeptus_insights', 'enable_scheduled_reports')) {
            mtrace('Scheduled reports are globally disabled. Skipping.');
            return;
        }

        $schedules = $this->get_due_schedules();
        if (empty($schedules)) {
            mtrace('No scheduled reports due at this time.');
            return;
        }

        mtrace('Found ' . count($schedules) . ' scheduled report(s) due.');

        $lockfactory = lock_config::get_lock_factory('report_adeptus_insights_scheduler');

        foreach ($schedules as $schedule) {
            $lock = $lockfactory->get_lock("schedule_{$schedule->id}", 0);
            if (!$lock) {
                mtrace("Schedule {$schedule->id} ('{$schedule->label}') already locked — skipping.");
                continue;
            }

            try {
                $this->process_schedule($schedule);
            } catch (\Throwable $e) {
                mtrace("ERROR processing schedule {$schedule->id}: " . $e->getMessage());
                $this->handle_failure($schedule, $e->getMessage());
            } finally {
                $lock->release();
            }
        }

        // Clean up old log entries.
        $this->cleanup_schedule_logs();
    }

    /**
     * Get all schedules that are due to run.
     *
     * @return array Array of schedule records.
     */
    protected function get_due_schedules(): array {
        global $DB;

        $now = time();
        return $DB->get_records_select(
            'report_adeptus_insights_schedules',
            'active = 1 AND next_run IS NOT NULL AND next_run <= :now',
            ['now' => $now],
            'next_run ASC'
        );
    }

    /**
     * Process a single schedule: generate report, email recipients.
     *
     * @param \stdClass $schedule The schedule record.
     */
    protected function process_schedule(\stdClass $schedule): void {
        global $DB, $CFG;

        mtrace("Processing schedule {$schedule->id}: '{$schedule->label}' (report: {$schedule->reportid})");

        // Check tier allows this schedule.
        if (!$this->check_tier_allows_schedule($schedule)) {
            $this->log_run($schedule->id, 'skipped', [
                'error_message' => 'Subscription tier does not allow scheduled reports.',
            ]);
            // Still advance next_run so we don't retry every hour.
            $nextrun = $this->compute_next_run($schedule);
            $DB->update_record('report_adeptus_insights_schedules', (object) [
                'id' => $schedule->id,
                'next_run' => $nextrun,
                'timemodified' => time(),
            ]);
            mtrace("  Skipped — tier insufficient. Next run: " . userdate($nextrun));
            return;
        }

        // Generate the report via executor.
        $executor = new report_executor();
        $report = $executor->fetch_report_definition($schedule->reportid);

        $params = [];
        if (!empty($schedule->report_params)) {
            $params = json_decode($schedule->report_params, true) ?? [];
        }

        $rows = $executor->execute_report($report, $params);
        $headers = $executor->get_headers($rows);
        $filepath = $executor->export_to_csv($rows, $headers);

        $rowcount = count($rows);
        $filesize = filesize($filepath);

        // Build filename.
        $cleanname = clean_filename($schedule->label);
        $filename = $cleanname . '_' . date('Y-m-d') . '.csv';

        // Move to dataroot temp for email_to_user.
        $tempdirrel = 'temp/adeptus_schedules';
        $tempdir = $CFG->dataroot . '/' . $tempdirrel;
        make_writable_directory($tempdir);
        $destpath = $tempdir . '/' . $filename;
        rename($filepath, $destpath);

        // Resolve recipients and send.
        $recipients = $this->resolve_recipients($schedule);
        if (empty($recipients)) {
            mtrace("  No valid recipients for schedule {$schedule->id}.");
            $this->log_run($schedule->id, 'failed', [
                'error_message' => 'No valid recipients found.',
                'row_count' => $rowcount,
            ]);
            @unlink($destpath);
            return;
        }

        $sent = 0;
        $failed = 0;
        $sentemails = [];

        foreach ($recipients as $recipient) {
            $email = strtolower(trim($recipient->email));
            if (isset($sentemails[$email])) {
                continue; // De-duplicate.
            }
            $sentemails[$email] = true;

            if ($this->send_to_recipient($recipient, $schedule, $tempdirrel . '/' . $filename, $filename, $report, $rowcount)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        // Clean up temp file.
        @unlink($destpath);

        // Update schedule.
        $now = time();
        $nextrun = $this->compute_next_run($schedule);
        $DB->update_record('report_adeptus_insights_schedules', (object) [
            'id' => $schedule->id,
            'last_run' => $now,
            'next_run' => $nextrun,
            'failure_count' => 0,
            'timemodified' => $now,
        ]);

        // Log success.
        $this->log_run($schedule->id, 'success', [
            'recipients_sent' => $sent,
            'recipients_failed' => $failed,
            'export_format' => 'csv',
            'attachment_size' => $filesize,
            'row_count' => $rowcount,
        ]);

        mtrace("  Delivered to {$sent} recipient(s). Next run: " . userdate($nextrun));
    }

    /**
     * Resolve all recipients for a schedule into user-like objects.
     *
     * @param \stdClass $schedule The schedule record.
     * @return array Array of recipient objects with at least ->email, ->firstname, ->lastname.
     */
    protected function resolve_recipients(\stdClass $schedule): array {
        global $DB, $CFG;

        $reciprows = $DB->get_records('report_adeptus_insights_sched_recip', ['scheduleid' => $schedule->id]);
        $recipients = [];

        foreach ($reciprows as $row) {
            switch ($row->recipient_type) {
                case 'email':
                    if (!empty($row->email) && validate_email($row->email)) {
                        $recipient = new \stdClass();
                        $recipient->id = -1;
                        $recipient->email = $row->email;
                        $recipient->firstname = 'Report';
                        $recipient->lastname = 'Recipient';
                        $recipient->username = 'noreply';
                        $recipient->emailstop = 0;
                        $recipient->mailformat = 1;
                        $recipient->lang = $CFG->lang;
                        $recipient->deleted = 0;
                        $recipient->auth = 'email';
                        $recipients[] = $recipient;
                    }
                    break;

                case 'user':
                    if (!empty($row->userid)) {
                        $user = $DB->get_record('user', [
                            'id' => $row->userid,
                            'deleted' => 0,
                            'confirmed' => 1,
                        ]);
                        if ($user && !$user->emailstop) {
                            $recipients[] = $user;
                        }
                    }
                    break;

                case 'role':
                    if (!empty($row->roleid)) {
                        $context = \context_system::instance();
                        $roleusers = get_role_users(
                            $row->roleid,
                            $context,
                            false,
                            'u.id, u.email, u.firstname, u.lastname, u.emailstop, u.deleted, ' .
                            'u.confirmed, u.mailformat, u.lang, u.username, u.auth',
                            'u.lastname ASC'
                        );
                        foreach ($roleusers as $u) {
                            if (!$u->deleted && $u->confirmed && !$u->emailstop) {
                                $recipients[] = $u;
                            }
                        }
                    }
                    break;
            }
        }

        return $recipients;
    }

    /**
     * Send the report email to a single recipient.
     *
     * @param object $recipient The recipient user object.
     * @param \stdClass $schedule The schedule record.
     * @param string $attachmentpath Relative path from dataroot to the attachment.
     * @param string $attachmentname Display filename for the attachment.
     * @param \stdClass $report The report definition.
     * @param int $rowcount Number of rows in the report.
     * @return bool True on success.
     */
    protected function send_to_recipient(
        object $recipient,
        \stdClass $schedule,
        string $attachmentpath,
        string $attachmentname,
        \stdClass $report,
        int $rowcount
    ): bool {
        global $CFG;

        $noreply = \core_user::get_noreply_user();
        $sendername = get_config('report_adeptus_insights', 'scheduled_reports_sender_name');
        if (!empty($sendername)) {
            $noreply->firstname = $sendername;
            $noreply->lastname = '';
        }

        $sitename = format_string(get_site()->fullname);
        $reportname = isset($report->name) ? $report->name : $schedule->reportid;
        $datestr = userdate(time(), get_string('strftimedaydate', 'langconfig'));

        // Resolve subject.
        $subject = $schedule->email_subject;
        if (empty($subject)) {
            $a = new \stdClass();
            $a->site_name = $sitename;
            $a->report_name = $reportname;
            $a->date = $datestr;
            $subject = get_string('email_subject_default', 'report_adeptus_insights', $a);
        } else {
            $subject = str_replace('{{report_name}}', $reportname, $subject);
            $subject = str_replace('{{date}}', $datestr, $subject);
            $subject = str_replace('{{site_name}}', $sitename, $subject);
        }

        // Build body.
        $manageurl = new \moodle_url('/report/adeptus_insights/scheduled_reports.php');
        $a = new \stdClass();
        $a->firstname = $recipient->firstname ?? 'Recipient';
        $a->report_name = $reportname;
        $a->date = $datestr;
        $a->site_name = $sitename;
        $a->filename = $attachmentname;
        $a->row_count = $rowcount;
        $a->manage_url = $manageurl->out(false);

        if (!empty($schedule->email_body)) {
            $bodytext = $schedule->email_body;
            // Replace tokens in custom body.
            $bodytext = str_replace('{{firstname}}', $a->firstname, $bodytext);
            $bodytext = str_replace('{{report_name}}', $reportname, $bodytext);
            $bodytext = str_replace('{{date}}', $datestr, $bodytext);
            $bodytext = str_replace('{{site_name}}', $sitename, $bodytext);
        } else {
            $bodytext = get_string('email_body_default', 'report_adeptus_insights', $a);
        }

        $bodyhtml = text_to_html($bodytext, false, false, true);

        try {
            $result = email_to_user(
                $recipient,
                $noreply,
                $subject,
                $bodytext,
                $bodyhtml,
                $attachmentpath,
                $attachmentname
            );
            return (bool) $result;
        } catch (\Throwable $e) {
            mtrace("  Failed to email {$recipient->email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compute the next run timestamp for a schedule.
     *
     * @param \stdClass $schedule The schedule record.
     * @return int Unix timestamp of the next run.
     */
    protected function compute_next_run(\stdClass $schedule): int {
        $tz = \core_date::get_server_timezone_object();
        $now = new \DateTime('now', $tz);

        switch ($schedule->frequency) {
            case 'daily':
                $next = clone $now;
                $next->setTime((int) $schedule->run_hour, 0, 0);
                if ($next <= $now) {
                    $next->modify('+1 day');
                }
                return $next->getTimestamp();

            case 'weekly':
                $dayofweek = (int) ($schedule->run_dayofweek ?? 1); // Default Monday.
                $next = clone $now;
                $next->setTime((int) $schedule->run_hour, 0, 0);

                // PHP: 0=Sunday, 6=Saturday.
                $currentdow = (int) $next->format('w');
                $diff = $dayofweek - $currentdow;
                if ($diff < 0 || ($diff === 0 && $next <= $now)) {
                    $diff += 7;
                }
                if ($diff > 0) {
                    $next->modify("+{$diff} days");
                }
                return $next->getTimestamp();

            case 'monthly':
                $dayofmonth = (int) ($schedule->run_dayofmonth ?? 1);
                $dayofmonth = min($dayofmonth, 28); // Cap at 28.
                $next = clone $now;
                $next->setDate((int) $next->format('Y'), (int) $next->format('n'), $dayofmonth);
                $next->setTime((int) $schedule->run_hour, 0, 0);
                if ($next <= $now) {
                    $next->modify('+1 month');
                    // Re-set day in case month overflow.
                    $next->setDate((int) $next->format('Y'), (int) $next->format('n'), $dayofmonth);
                }
                return $next->getTimestamp();

            default:
                // Fallback: next hour.
                return time() + 3600;
        }
    }

    /**
     * Write a log entry for a schedule run.
     *
     * @param int $scheduleid The schedule ID.
     * @param string $status Status: success, failed, skipped.
     * @param array $stats Additional stats.
     */
    protected function log_run(int $scheduleid, string $status, array $stats = []): void {
        global $DB;

        $record = new \stdClass();
        $record->scheduleid = $scheduleid;
        $record->status = $status;
        $record->recipients_sent = $stats['recipients_sent'] ?? 0;
        $record->recipients_failed = $stats['recipients_failed'] ?? 0;
        $record->export_format = $stats['export_format'] ?? 'csv';
        $record->attachment_size = $stats['attachment_size'] ?? 0;
        $record->row_count = $stats['row_count'] ?? 0;
        $record->error_message = $stats['error_message'] ?? null;
        $record->timecreated = time();

        $DB->insert_record('report_adeptus_insights_sched_log', $record);
    }

    /**
     * Handle a schedule failure: increment failure count, auto-pause if threshold reached.
     *
     * @param \stdClass $schedule The schedule record.
     * @param string $error The error message.
     */
    protected function handle_failure(\stdClass $schedule, string $error): void {
        global $DB;

        $failurecount = ($schedule->failure_count ?? 0) + 1;
        $active = $schedule->active;
        $now = time();

        if ($failurecount >= self::MAX_FAILURES) {
            $active = 0;
            mtrace("  Schedule {$schedule->id} auto-paused after {$failurecount} consecutive failures.");

            // Notify site admin.
            $admin = get_admin();
            if ($admin) {
                $a = new \stdClass();
                $a->label = $schedule->label;
                $a->failures = $failurecount;
                $a->error = $error;
                $message = get_string('error_schedule_auto_paused', 'report_adeptus_insights', $a);

                $eventdata = new \core\message\message();
                $eventdata->component = 'report_adeptus_insights';
                $eventdata->name = 'scheduled_report_failure';
                $eventdata->userfrom = \core_user::get_noreply_user();
                $eventdata->userto = $admin;
                $eventdata->subject = get_string('task_send_scheduled_reports', 'report_adeptus_insights')
                    . ': ' . $schedule->label;
                $eventdata->fullmessage = $message;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml = text_to_html($message);
                $eventdata->smallmessage = $message;
                $eventdata->notification = 1;

                try {
                    message_send($eventdata);
                } catch (\Throwable $e) {
                    mtrace("  Warning: Could not notify admin about schedule failure: " . $e->getMessage());
                }
            }
        }

        // Advance next_run even on failure.
        $nextrun = $this->compute_next_run($schedule);

        $DB->update_record('report_adeptus_insights_schedules', (object) [
            'id' => $schedule->id,
            'failure_count' => $failurecount,
            'active' => $active,
            'next_run' => $nextrun,
            'timemodified' => $now,
        ]);

        $this->log_run($schedule->id, 'failed', [
            'error_message' => $error,
        ]);
    }

    /**
     * Check if the current subscription tier allows this schedule to run.
     *
     * @param \stdClass $schedule The schedule record.
     * @return bool True if allowed.
     */
    protected function check_tier_allows_schedule(\stdClass $schedule): bool {
        global $DB;

        $sub = $DB->get_record('report_adeptus_insights_subscription', [], '*', IGNORE_MULTIPLE);
        if (!$sub) {
            // No subscription record — treat as free tier, no schedules allowed.
            return false;
        }

        $planname = strtolower($sub->plan_name ?? '');

        // Free plan: no scheduled reports.
        if ($planname === 'free' || empty($planname)) {
            return false;
        }

        // Check schedule count limits.
        $activecount = $DB->count_records('report_adeptus_insights_schedules', ['active' => 1]);
        $limits = $this->get_tier_limits($planname);

        if ($limits['max_schedules'] > 0 && $activecount > $limits['max_schedules']) {
            return false;
        }

        return true;
    }

    /**
     * Get tier limits for a given plan name.
     *
     * @param string $planname The plan name (lowercase).
     * @return array Associative array with limit keys.
     */
    protected function get_tier_limits(string $planname): array {
        $tiers = [
            'basic' => [
                'max_schedules' => 3,
                'formats' => ['csv'],
            ],
            'pro' => [
                'max_schedules' => 10,
                'formats' => ['csv', 'pdf'],
            ],
            'enterprise' => [
                'max_schedules' => 0, // 0 = unlimited.
                'formats' => ['csv', 'pdf'],
            ],
        ];

        return $tiers[$planname] ?? $tiers['basic'];
    }

    /**
     * Clean up old schedule log entries based on retention setting.
     */
    protected function cleanup_schedule_logs(): void {
        global $DB;

        $retentiondays = (int) get_config('report_adeptus_insights', 'scheduled_reports_log_retention');
        if ($retentiondays <= 0) {
            $retentiondays = 90;
        }

        $cutoff = time() - ($retentiondays * DAYSECS);
        $deleted = $DB->delete_records_select(
            'report_adeptus_insights_sched_log',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($deleted) {
            mtrace("Cleaned up {$deleted} old schedule log entries (retention: {$retentiondays} days).");
        }
    }
}
