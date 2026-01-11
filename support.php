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

$support_manager = new \report_adeptus_insights\support_manager();
$installation_manager = new \report_adeptus_insights\installation_manager();

// Get current action and view
$action = optional_param('action', 'list', PARAM_ALPHA);
$view = optional_param('view', 'tickets', PARAM_ALPHA);
$ticket_id = optional_param('ticket_id', 0, PARAM_INT);

// Process form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $post_action = required_param('action', PARAM_ALPHA);

    if ($post_action === 'create_ticket') {
        $category = required_param('category', PARAM_ALPHA);
        $subject = required_param('subject', PARAM_TEXT);
        $ticket_message = required_param('message', PARAM_RAW);
        $priority = optional_param('priority', 'medium', PARAM_ALPHA);
        $submitter_name = optional_param('submitter_name', '', PARAM_TEXT);
        $submitter_email = optional_param('submitter_email', '', PARAM_EMAIL);

        // Get uploaded files if any
        $attachments = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['tmp_name'])) {
            $attachments = $_FILES['attachments'];
        }

        $result = $support_manager->create_ticket(
            $category,
            $subject,
            $ticket_message,
            $submitter_name ?: null,
            $submitter_email ?: null,
            $priority,
            $attachments
        );

        if ($result['success']) {
            $message = $result['message'];
            $message_type = 'success';
            $action = 'list';
        } else {
            $message = $result['message'];
            $message_type = 'error';
            $action = 'new';
        }
    } elseif ($post_action === 'reply') {
        $reply_ticket_id = required_param('ticket_id', PARAM_INT);
        $reply_message = required_param('reply_message', PARAM_RAW);
        $sender_name = optional_param('sender_name', '', PARAM_TEXT);

        $result = $support_manager->add_reply($reply_ticket_id, $reply_message, $sender_name ?: null);

        if ($result['success']) {
            $message = $result['message'];
            $message_type = 'success';
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }

        $action = 'view';
        $ticket_id = $reply_ticket_id;
    }
}

// Load CSS
$PAGE->requires->css('/report/adeptus_insights/styles.css');
$PAGE->requires->css('/report/adeptus_insights/styles/notifications.css');

echo $OUTPUT->header();

// Check if registered
$is_registered = $installation_manager->is_registered();

// Prepare common template context
$basecontext = [
    'wwwroot' => $CFG->wwwroot,
    'sesskey' => sesskey(),
    'is_registered' => $is_registered,
    'message' => $message,
    'message_type' => $message_type,
    'show_message' => !empty($message),
    'current_view' => $view,
    'view_tickets' => $view === 'tickets',
    'view_changelog' => $view === 'changelog',
];

// Build the page content based on action
if ($view === 'changelog') {
    // Changelog view
    $changelog_result = $support_manager->get_changelog(50);
    $update_result = $support_manager->check_for_updates();

    $changelogs = [];
    if ($changelog_result['success'] && !empty($changelog_result['changelogs'])) {
        foreach ($changelog_result['changelogs'] as $entry) {
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
        'update_available' => $update_result['update_available'] ?? false,
        'current_version' => $update_result['current_version'] ?? $installation_manager->get_plugin_version(),
        'latest_version' => $update_result['latest_version'] ?? null,
        'versions_behind' => $update_result['versions_behind'] ?? 0,
        'latest_changelog' => $update_result['latest_changelog'] ?? null,
    ]);

    echo $OUTPUT->render_from_template('report_adeptus_insights/support_changelog', $templatecontext);

} elseif ($action === 'new') {
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

} elseif ($action === 'view' && $ticket_id > 0) {
    // View ticket detail
    $ticket_result = $support_manager->get_ticket($ticket_id);

    if ($ticket_result['success']) {
        $ticket = $ticket_result['ticket'];

        // Format replies
        $replies = [];
        if (!empty($ticket['replies'])) {
            foreach ($ticket['replies'] as $reply) {
                $replies[] = [
                    'id' => $reply['id'],
                    'sender_name' => $reply['sender_name'],
                    'sender_type' => $reply['sender_type'],
                    'is_customer' => $reply['sender_type'] === 'customer',
                    'is_support' => $reply['sender_type'] === 'support',
                    'message' => nl2br(htmlspecialchars($reply['message'])),
                    'created_at' => $reply['created_at'],
                    'is_internal' => $reply['is_internal_note'] ?? false,
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
            ],
            'replies' => $replies,
            'has_replies' => !empty($replies),
            'can_reply' => $ticket['status'] !== 'closed',
            'default_name' => fullname($USER),
        ]);

        echo $OUTPUT->render_from_template('report_adeptus_insights/support_ticket_detail', $templatecontext);
    } else {
        $templatecontext = array_merge($basecontext, [
            'error_message' => $ticket_result['message'],
        ]);
        echo $OUTPUT->render_from_template('report_adeptus_insights/support_error', $templatecontext);
    }

} else {
    // List tickets (default view)
    $status_filter = optional_param('status', '', PARAM_ALPHA);
    $category_filter = optional_param('category', '', PARAM_ALPHA);

    $tickets_result = $support_manager->get_tickets(
        $status_filter ?: null,
        $category_filter ?: null
    );

    $tickets = [];
    if ($tickets_result['success'] && !empty($tickets_result['tickets'])) {
        foreach ($tickets_result['tickets'] as $ticket) {
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
                'view_url' => new moodle_url('/report/adeptus_insights/support.php', ['action' => 'view', 'ticket_id' => $ticket['id']]),
            ];
        }
    }

    // Build filter options
    $status_options = [
        ['value' => '', 'label' => get_string('all'), 'selected' => empty($status_filter)],
        ['value' => 'open', 'label' => get_string('status_open', 'report_adeptus_insights'), 'selected' => $status_filter === 'open'],
        ['value' => 'in_progress', 'label' => get_string('status_in_progress', 'report_adeptus_insights'), 'selected' => $status_filter === 'in_progress'],
        ['value' => 'resolved', 'label' => get_string('status_resolved', 'report_adeptus_insights'), 'selected' => $status_filter === 'resolved'],
        ['value' => 'closed', 'label' => get_string('status_closed', 'report_adeptus_insights'), 'selected' => $status_filter === 'closed'],
    ];

    $category_options = [
        ['value' => '', 'label' => get_string('all'), 'selected' => empty($category_filter)],
    ];
    foreach (\report_adeptus_insights\support_manager::TICKET_CATEGORIES as $key => $label) {
        $category_options[] = ['value' => $key, 'label' => $label, 'selected' => $category_filter === $key];
    }

    $templatecontext = array_merge($basecontext, [
        'tickets' => $tickets,
        'has_tickets' => !empty($tickets),
        'total_tickets' => count($tickets),
        'status_options' => $status_options,
        'category_options' => $category_options,
        'new_ticket_url' => new moodle_url('/report/adeptus_insights/support.php', ['action' => 'new']),
    ]);

    echo $OUTPUT->render_from_template('report_adeptus_insights/support_tickets_list', $templatecontext);
}

echo $OUTPUT->footer();
