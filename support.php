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
 * Support & Updates page for Adeptus Insights.
 *
 * Allows administrators to submit support tickets and view changelog updates.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/lib.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/support.php'));
$PAGE->set_title(get_string('support_updates', 'report_adeptus_insights'));
$PAGE->set_pagelayout('report');

// Load the support manager
require_once($CFG->dirroot . '/report/adeptus_insights/classes/support_manager.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

$supportmanager = new \report_adeptus_insights\support_manager();
$installationmanager = new \report_adeptus_insights\installation_manager();

// Get current action and view
$action = optional_param('action', 'list', PARAM_ALPHA);
$view = optional_param('view', 'tickets', PARAM_ALPHA);
$ticketid = optional_param('ticket_id', 0, PARAM_INT);

// Process form submissions
$message = '';
$messagetype = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    // Use PARAM_ALPHANUMEXT to allow underscores in action names
    $postaction = required_param('action', PARAM_ALPHANUMEXT);

    if ($postaction === 'create_ticket') {
        $category = required_param('category', PARAM_ALPHA);
        $subject = required_param('subject', PARAM_TEXT);
        $ticketmessage = required_param('message', PARAM_RAW);
        $priority = optional_param('priority', 'medium', PARAM_ALPHA);
        $submittername = optional_param('submitter_name', '', PARAM_TEXT);
        $submitteremail = optional_param('submitter_email', '', PARAM_EMAIL);

        // Get uploaded files if any - check if actual files were uploaded
        $attachments = [];
        if (isset($_FILES['attachments']) && isset($_FILES['attachments']['tmp_name'])) {
            // Handle array of files (multiple upload)
            if (is_array($_FILES['attachments']['tmp_name'])) {
                $hasfiles = false;
                foreach ($_FILES['attachments']['tmp_name'] as $tmpname) {
                    if (!empty($tmpname) && is_uploaded_file($tmpname)) {
                        $hasfiles = true;
                        break;
                    }
                }
                if ($hasfiles) {
                    $attachments = $_FILES['attachments'];
                }
            } else {
                // Single file upload
                if (!empty($_FILES['attachments']['tmp_name']) && is_uploaded_file($_FILES['attachments']['tmp_name'])) {
                    $attachments = $_FILES['attachments'];
                }
            }
        }

        $result = $supportmanager->create_ticket(
            $category,
            $subject,
            $ticketmessage,
            $submittername ?: null,
            $submitteremail ?: null,
            $priority,
            $attachments
        );

        if ($result['success']) {
            $message = $result['message'];
            $messagetype = 'success';
            $action = 'list';
        } else {
            $message = $result['message'];
            $messagetype = 'error';
            $action = 'new';
        }
    } else if ($postaction === 'reply') {
        $replyticketid = required_param('ticket_id', PARAM_INT);
        $replymessage = required_param('reply_message', PARAM_RAW);
        $sendername = optional_param('sender_name', '', PARAM_TEXT);

        // Get uploaded files if any - check if actual files were uploaded
        $replyattachments = [];
        if (isset($_FILES['reply_attachments']) && isset($_FILES['reply_attachments']['tmp_name'])) {
            if (is_array($_FILES['reply_attachments']['tmp_name'])) {
                $hasfiles = false;
                foreach ($_FILES['reply_attachments']['tmp_name'] as $tmpname) {
                    if (!empty($tmpname) && is_uploaded_file($tmpname)) {
                        $hasfiles = true;
                        break;
                    }
                }
                if ($hasfiles) {
                    $replyattachments = $_FILES['reply_attachments'];
                }
            } else {
                if (!empty($_FILES['reply_attachments']['tmp_name']) && is_uploaded_file($_FILES['reply_attachments']['tmp_name'])) {
                    $replyattachments = $_FILES['reply_attachments'];
                }
            }
        }

        $result = $supportmanager->add_reply($replyticketid, $replymessage, $sendername ?: null, $replyattachments);

        if ($result['success']) {
            $message = $result['message'];
            $messagetype = 'success';
        } else {
            $message = $result['message'];
            $messagetype = 'error';
        }

        $action = 'view';
        $ticketid = $replyticketid;
    }
}

// Load CSS
$PAGE->requires->css('/report/adeptus_insights/styles.css');
$PAGE->requires->css('/report/adeptus_insights/styles/notifications.css');

echo $OUTPUT->header();

// Check if registered
$isregistered = $installationmanager->is_registered();

// Prepare common template context
$basecontext = [
    'wwwroot' => $CFG->wwwroot,
    'sesskey' => sesskey(),
    'is_registered' => $isregistered,
    'message' => $message,
    'message_type' => $messagetype,
    'show_message' => !empty($message),
    'current_view' => $view,
    'view_tickets' => $view === 'tickets',
    'view_changelog' => $view === 'changelog',
];

// Build the page content based on action
if ($view === 'changelog') {
    // Changelog view
    $changelogresult = $supportmanager->get_changelog(50);
    $updateresult = $supportmanager->check_for_updates();

    $changelogs = [];
    if ($changelogresult['success'] && !empty($changelogresult['changelogs'])) {
        foreach ($changelogresult['changelogs'] as $entry) {
            $changelogs[] = [
                'version' => $entry['version'],
                'title' => $entry['title'],
                'content' => $entry['content'],
                'release_date' => $entry['release_date'],
                'is_major' => $entry['is_major'] ?? false,
            ];
        }
    }

    $templatecontext = array_merge($basecontext, [
        'changelogs' => $changelogs,
        'has_changelogs' => !empty($changelogs),
        'update_available' => $updateresult['update_available'] ?? false,
        'current_version' => $updateresult['current_version'] ?? $installationmanager->get_plugin_version(),
        'latest_version' => $updateresult['latest_version'] ?? null,
        'versions_behind' => $updateresult['versions_behind'] ?? 0,
        'latest_changelog' => $updateresult['latest_changelog'] ?? null,
    ]);

    echo $OUTPUT->render_from_template('report_adeptus_insights/support_changelog', $templatecontext);
} else if ($action === 'new') {
    // New ticket form
    $categories = [];
    foreach (\report_adeptus_insights\support_manager::TICKET_CATEGORIES as $key => $label) {
        $categories[] = ['value' => $key, 'label' => $label];
    }

    $priorities = [];
    foreach (\report_adeptus_insights\support_manager::TICKET_PRIORITIES as $key => $label) {
        $priorities[] = ['value' => $key, 'label' => $label, 'selected' => $key === 'medium'];
    }

    $templatecontext = array_merge($basecontext, [
        'categories' => $categories,
        'priorities' => $priorities,
        'default_name' => fullname($USER),
        'default_email' => $USER->email,
    ]);

    echo $OUTPUT->render_from_template('report_adeptus_insights/support_new_ticket', $templatecontext);
} else if ($action === 'view' && $ticketid > 0) {
    // View ticket detail
    $ticketresult = $supportmanager->get_ticket($ticketid);

    if ($ticketresult['success']) {
        $ticket = $ticketresult['ticket'];

        // Format attachments for ticket
        $ticketattachments = [];
        if (!empty($ticket['attachments'])) {
            foreach ($ticket['attachments'] as $attachment) {
                $ticketattachments[] = [
                    'id' => $attachment['id'],
                    'filename' => $attachment['original_filename'],
                    'file_size' => $attachment['file_size_formatted'] ?? $attachment['file_size'],
                    'mime_type' => $attachment['mime_type'],
                    'is_image' => $attachment['is_image'] ?? false,
                    'download_url' => (new moodle_url('/report/adeptus_insights/attachment.php', [
                        'id' => $attachment['id'],
                        'ticket_id' => $ticket['id'],
                    ]))->out(false),
                ];
            }
        }

        // Format replies
        $replies = [];
        if (!empty($ticket['replies'])) {
            foreach ($ticket['replies'] as $reply) {
                // Format reply attachments
                $replyattachments = [];
                if (!empty($reply['attachments'])) {
                    foreach ($reply['attachments'] as $attachment) {
                        $replyattachments[] = [
                            'id' => $attachment['id'],
                            'filename' => $attachment['original_filename'],
                            'file_size' => $attachment['file_size_formatted'] ?? $attachment['file_size'],
                            'mime_type' => $attachment['mime_type'],
                            'is_image' => $attachment['is_image'] ?? false,
                            'download_url' => (new moodle_url('/report/adeptus_insights/attachment.php', [
                                'id' => $attachment['id'],
                                'ticket_id' => $ticket['id'],
                            ]))->out(false),
                        ];
                    }
                }

                $replies[] = [
                    'id' => $reply['id'],
                    'sender_name' => $reply['sender_name'],
                    'sender_type' => $reply['sender_type'],
                    'is_customer' => $reply['sender_type'] === 'customer',
                    'is_support' => $reply['sender_type'] === 'support',
                    'message' => nl2br(htmlspecialchars($reply['message'])),
                    'created_at' => $reply['created_at'],
                    'is_internal' => $reply['is_internal_note'] ?? false,
                    'attachments' => $replyattachments,
                    'has_attachments' => !empty($replyattachments),
                ];
            }
        }

        $templatecontext = array_merge($basecontext, [
            'ticket' => [
                'id' => $ticket['id'],
                'ticket_number' => $ticket['ticket_number'],
                'category' => $ticket['category'],
                'category_label' => \report_adeptus_insights\support_manager::get_category_label($ticket['category']),
                'subject' => $ticket['subject'],
                'message' => nl2br(htmlspecialchars($ticket['message'])),
                'status' => $ticket['status'],
                'status_label' => \report_adeptus_insights\support_manager::get_status_label($ticket['status']),
                'status_class' => \report_adeptus_insights\support_manager::get_status_class($ticket['status']),
                'priority' => $ticket['priority'],
                'priority_label' => \report_adeptus_insights\support_manager::get_priority_label($ticket['priority']),
                'priority_class' => \report_adeptus_insights\support_manager::get_priority_class($ticket['priority']),
                'submitter_name' => $ticket['submitter_name'] ?? '',
                'submitter_email' => $ticket['submitter_email'] ?? '',
                'created_at' => $ticket['created_at'],
                'is_closed' => $ticket['status'] === 'closed',
                'attachments' => $ticketattachments,
                'has_attachments' => !empty($ticketattachments),
            ],
            'replies' => $replies,
            'has_replies' => !empty($replies),
            'can_reply' => $ticket['status'] !== 'closed',
            'default_name' => fullname($USER),
        ]);

        echo $OUTPUT->render_from_template('report_adeptus_insights/support_ticket_detail', $templatecontext);
    } else {
        $templatecontext = array_merge($basecontext, [
            'error_message' => $ticketresult['message'],
        ]);
        echo $OUTPUT->render_from_template('report_adeptus_insights/support_error', $templatecontext);
    }
} else {
    // List tickets (default view)
    $statusfilter = optional_param('status', '', PARAM_ALPHA);
    $categoryfilter = optional_param('category', '', PARAM_ALPHA);

    $ticketsresult = $supportmanager->get_tickets(
        $statusfilter ?: null,
        $categoryfilter ?: null
    );

    $tickets = [];
    if ($ticketsresult['success'] && !empty($ticketsresult['tickets'])) {
        foreach ($ticketsresult['tickets'] as $ticket) {
            $tickets[] = [
                'id' => $ticket['id'],
                'ticket_number' => $ticket['ticket_number'],
                'category' => $ticket['category'],
                'category_label' => \report_adeptus_insights\support_manager::get_category_label($ticket['category']),
                'subject' => $ticket['subject'],
                'status' => $ticket['status'],
                'status_label' => \report_adeptus_insights\support_manager::get_status_label($ticket['status']),
                'status_class' => \report_adeptus_insights\support_manager::get_status_class($ticket['status']),
                'priority' => $ticket['priority'],
                'priority_label' => \report_adeptus_insights\support_manager::get_priority_label($ticket['priority']),
                'priority_class' => \report_adeptus_insights\support_manager::get_priority_class($ticket['priority']),
                'created_at' => $ticket['created_at'],
                'last_reply_at' => $ticket['last_reply_at'] ?? null,
                'view_url' => (new moodle_url('/report/adeptus_insights/support.php', ['action' => 'view', 'ticket_id' => $ticket['id']]))->out(false),
            ];
        }
    }

    // Build filter options
    $statusoptions = [
        ['value' => '', 'label' => get_string('all'), 'selected' => empty($statusfilter)],
        ['value' => 'open', 'label' => get_string('status_open', 'report_adeptus_insights'), 'selected' => $statusfilter === 'open'],
        ['value' => 'in_progress', 'label' => get_string('status_in_progress', 'report_adeptus_insights'), 'selected' => $statusfilter === 'in_progress'],
        ['value' => 'resolved', 'label' => get_string('status_resolved', 'report_adeptus_insights'), 'selected' => $statusfilter === 'resolved'],
        ['value' => 'closed', 'label' => get_string('status_closed', 'report_adeptus_insights'), 'selected' => $statusfilter === 'closed'],
    ];

    $categoryoptions = [
        ['value' => '', 'label' => get_string('all'), 'selected' => empty($categoryfilter)],
    ];
    foreach (\report_adeptus_insights\support_manager::TICKET_CATEGORIES as $key => $label) {
        $categoryoptions[] = ['value' => $key, 'label' => $label, 'selected' => $categoryfilter === $key];
    }

    $templatecontext = array_merge($basecontext, [
        'tickets' => $tickets,
        'has_tickets' => !empty($tickets),
        'total_tickets' => count($tickets),
        'status_options' => $statusoptions,
        'category_options' => $categoryoptions,
        'new_ticket_url' => (new moodle_url('/report/adeptus_insights/support.php', ['action' => 'new']))->out(false),
    ]);

    echo $OUTPUT->render_from_template('report_adeptus_insights/support_tickets_list', $templatecontext);
}

echo $OUTPUT->footer();
