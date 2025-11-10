<?php
require_once __DIR__ . '/../../../config.php';
require_login();
require_sesskey();

header('Content-Type: application/json');

try {
    $question = required_param('question', PARAM_ALPHA);
    $fields   = optional_param('fields', [], PARAM_RAW);

    if (! is_array($fields)) {
        $fields = [$fields];
    }

    if (empty($fields)) {
        throw new moodle_exception('No fields selected');
    }

    $allowed     = ['logins', 'assignments_submitted', 'forum_posts', 'average_grade'];
    $validfields = array_intersect($fields, $allowed);

    if (empty($validfields)) {
        throw new moodle_exception('Invalid or unapproved fields requested');
    }

    global $DB;
    $fieldlist = implode(', ', array_map(fn($f) => $f, $validfields));

    $sql     = "SELECT $fieldlist FROM {ai_analytics_base} ORDER BY timecreated DESC LIMIT 20";
    $results = $DB->get_records_sql($sql);

    echo json_encode(array_values($results));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
