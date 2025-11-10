<?php
// This file is part of Moodle - http://moodle.org/
//
// Clear adeptus reports tables

require_once(__DIR__ . '/../../../config.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

echo '<!DOCTYPE html><html><head><title>Clear Reports</title></head><body><pre>';

try {
    // Clear all tables
    $DB->delete_records('adeptus_reports');
    $DB->delete_records('adeptus_report_history');
    $DB->delete_records('adeptus_report_bookmarks');
    
    echo "Successfully cleared all report data.\n";
    echo "You can now run the seeder to populate with fresh data.\n";
    
} catch (Exception $e) {
    echo "Error clearing data: " . $e->getMessage() . "\n";
}

echo '</pre></body></html>'; 