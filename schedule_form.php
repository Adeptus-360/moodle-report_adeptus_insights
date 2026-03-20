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
 * Schedule form page — create or edit a scheduled report.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$CFG->theme = 'boost';

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

$context = context_system::instance();
require_capability('report/adeptus_insights:manageschedules', $context);

$id = optional_param('id', 0, PARAM_INT);
$returnurl = new moodle_url('/report/adeptus_insights/scheduled_reports.php');

// Fetch available reports from backend API.
$reports = [];
try {
    $executor = new \report_adeptus_insights\report_executor();
    // Try to get reports list — fall back to empty array.
    $settings = $DB->get_record('report_adeptus_insights_settings', [], '*', IGNORE_MULTIPLE);
    if ($settings && !empty($settings->api_key) && !empty($settings->api_url)) {
        $url = rtrim($settings->api_url, '/') . '/ai-reports';
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $headers = [
            'Authorization: Bearer ' . $settings->api_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if (!empty($settings->installation_id)) {
            $headers[] = 'X-Installation-Id: ' . $settings->installation_id;
        }
        $curl->setHeader($headers);
        $response = $curl->get($url);
        $httpcode = $curl->get_info()['http_code'] ?? 0;
        if ($httpcode === 200 && !empty($response)) {
            $data = json_decode($response);
            if (is_array($data)) {
                $reports = $data;
            } else if (isset($data->reports) && is_array($data->reports)) {
                $reports = $data->reports;
            }
        }
    }
} catch (\Exception $e) {
    // Silently continue with empty reports list.
    debugging('Failed to fetch reports: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

// Fetch system roles for role-based recipients.
$roles = $DB->get_records('role', null, 'sortorder ASC');

// Build the form.
$customdata = [
    'reports' => $reports,
    'roles' => $roles,
];
$mform = new \report_adeptus_insights\form\schedule_form(null, $customdata);

// Load existing schedule for editing.
if ($id) {
    $schedule = $DB->get_record('report_adeptus_insights_schedules', ['id' => $id], '*', MUST_EXIST);
    $formdata = (array) $schedule;

    // Load recipients.
    $recipients = $DB->get_records('report_adeptus_insights_sched_recip', ['scheduleid' => $id]);
    $emails = [];
    $userids = [];
    $roleid = 0;
    foreach ($recipients as $recip) {
        switch ($recip->recipient_type) {
            case 'email':
                $emails[] = $recip->email;
                break;
            case 'user':
                $userids[] = $recip->userid;
                break;
            case 'role':
                $roleid = $recip->roleid;
                break;
        }
    }
    $formdata['recipients_emails'] = implode("\n", $emails);
    $formdata['recipients_userids'] = $userids;
    $formdata['recipients_roleid'] = $roleid;

    $mform->set_data($formdata);
}

// Handle cancellation.
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

// Handle submission.
if ($data = $mform->get_data()) {
    $now = time();

    // Build schedule record.
    $record = new stdClass();
    $record->reportid = $data->reportid;
    $record->label = $data->label;
    $record->frequency = $data->frequency;
    $record->run_hour = $data->run_hour;
    $record->run_dayofweek = ($data->frequency === 'weekly') ? ($data->run_dayofweek ?? 1) : null;
    $record->run_dayofmonth = ($data->frequency === 'monthly') ? ($data->run_dayofmonth ?? 1) : null;
    $record->export_format = $data->export_format;
    $record->email_subject = $data->email_subject ?? '';
    $record->email_body = $data->email_body ?? '';
    $record->active = $data->active ?? 1;
    $record->timemodified = $now;

    // Compute next run.
    $record->next_run = compute_next_run_timestamp($record);

    if ($id) {
        // Update existing.
        $record->id = $id;
        $DB->update_record('report_adeptus_insights_schedules', $record);
        $scheduleid = $id;

        // Clear existing recipients.
        $DB->delete_records('report_adeptus_insights_sched_recip', ['scheduleid' => $scheduleid]);
    } else {
        // Create new.
        $record->created_by = $USER->id;
        $record->timecreated = $now;
        $record->failure_count = 0;
        $scheduleid = $DB->insert_record('report_adeptus_insights_schedules', $record);
    }

    // Save recipients — emails.
    $emailsraw = trim($data->recipients_emails ?? '');
    if (!empty($emailsraw)) {
        $emaillist = preg_split('/[\s,;]+/', $emailsraw, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($emaillist as $email) {
            $email = trim($email);
            if (validate_email($email)) {
                $DB->insert_record('report_adeptus_insights_sched_recip', (object) [
                    'scheduleid' => $scheduleid,
                    'recipient_type' => 'email',
                    'email' => $email,
                    'timecreated' => $now,
                ]);
            }
        }
    }

    // Save recipients — users.
    if (!empty($data->recipients_userids)) {
        $userids = is_array($data->recipients_userids) ? $data->recipients_userids : [$data->recipients_userids];
        foreach ($userids as $uid) {
            if (!empty($uid)) {
                $DB->insert_record('report_adeptus_insights_sched_recip', (object) [
                    'scheduleid' => $scheduleid,
                    'recipient_type' => 'user',
                    'userid' => (int) $uid,
                    'timecreated' => $now,
                ]);
            }
        }
    }

    // Save recipients — role.
    if (!empty($data->recipients_roleid)) {
        $DB->insert_record('report_adeptus_insights_sched_recip', (object) [
            'scheduleid' => $scheduleid,
            'recipient_type' => 'role',
            'roleid' => (int) $data->recipients_roleid,
            'timecreated' => $now,
        ]);
    }

    \core\notification::add(
        get_string($id ? 'schedule_updated' : 'schedule_created', 'report_adeptus_insights'),
        \core\output\notification::NOTIFY_SUCCESS
    );
    redirect($returnurl);
}

// Display form.
$title = $id ? get_string('schedule_edit', 'report_adeptus_insights') : get_string('schedule_create', 'report_adeptus_insights');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/schedule_form.php', ['id' => $id]));
$PAGE->set_title($title);
$PAGE->set_pagelayout('report');
$PAGE->requires->css('/report/adeptus_insights/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$mform->display();

echo $OUTPUT->footer();

/**
 * Compute the next run timestamp for a schedule.
 *
 * @param stdClass $schedule Schedule record with frequency, run_hour, run_dayofweek, run_dayofmonth.
 * @return int Unix timestamp.
 */
function compute_next_run_timestamp(stdClass $schedule): int {
    $now = time();
    $hour = (int) $schedule->run_hour;

    // Use server timezone for Moodle's timezone-aware date functions.
    $today = usergetdate($now);

    switch ($schedule->frequency) {
        case 'daily':
            // Next occurrence of run_hour. If already past today, schedule for tomorrow.
            $candidate = mktime($hour, 0, 0, $today['mon'], $today['mday'], $today['year']);
            if ($candidate <= $now) {
                $candidate = mktime($hour, 0, 0, $today['mon'], $today['mday'] + 1, $today['year']);
            }
            return $candidate;

        case 'weekly':
            $targetdow = (int) ($schedule->run_dayofweek ?? 1);
            $currentdow = (int) date('w', $now);
            $daysahead = ($targetdow - $currentdow + 7) % 7;
            if ($daysahead === 0) {
                // Same day — check if hour has passed.
                $candidate = mktime($hour, 0, 0, $today['mon'], $today['mday'], $today['year']);
                if ($candidate <= $now) {
                    $daysahead = 7;
                }
            }
            return mktime($hour, 0, 0, $today['mon'], $today['mday'] + $daysahead, $today['year']);

        case 'monthly':
            $dom = (int) ($schedule->run_dayofmonth ?? 1);
            $candidate = mktime($hour, 0, 0, $today['mon'], $dom, $today['year']);
            if ($candidate <= $now) {
                $candidate = mktime($hour, 0, 0, $today['mon'] + 1, $dom, $today['year']);
            }
            return $candidate;

        default:
            return $now + 86400;
    }
}
