<?php
// This file is part of Moodle - http://moodle.org/
//
// Get wizard data for the Adeptus Insights Report Wizard

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and capability
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Set content type
header('Content-Type: application/json');

try {
    // Get user information
    global $USER, $CFG;
    
    // Generate session key
    $sesskey = sesskey();
    
    // Return wizard data
    echo json_encode([
        'success' => true,
        'data' => [
            'wwwroot' => $CFG->wwwroot,
            'sesskey' => $sesskey,
            'userid' => $USER->id,
            'username' => $USER->username,
            'fullname' => fullname($USER),
            'timezone' => $USER->timezone,
            'lang' => $USER->lang,
            'moodle_version' => $CFG->version,
            'plugin_version' => '1.0.0' // You can make this dynamic
        ]
    ]);

} catch (Exception $e) {
    error_log('Error in get_wizard_data.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load wizard data']);
}

exit;
