<?php
define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->libdir . '/externallib.php');

echo "=== CHECKING MISSING EXTERNAL FUNCTIONS ===\n\n";

$required_functions = [
    'report_adeptus_insights_send_message',
    'report_adeptus_insights_get_history',
    'report_adeptus_insights_get_subscription_details',
    'report_adeptus_insights_create_billing_portal_session'
];

echo "Checking required functions:\n";
foreach ($required_functions as $function_name) {
    $function = $DB->get_record('external_functions', array('name' => $function_name));
    if ($function) {
        echo "  ✓ {$function_name} - FOUND\n";
        echo "     Class: {$function->classname}\n";
        echo "     Method: {$function->methodname}\n";
    } else {
        echo "  ✗ {$function_name} - MISSING\n";
    }
    echo "\n";
}

echo "=== DATABASE REGISTRATION STATUS ===\n";
echo "Total external functions in database: " . $DB->count_records('external_functions') . "\n";
echo "Plugin external functions in database: " . $DB->count_records('external_functions', array(), '', 'name LIKE ?', array('report_adeptus_insights_%')) . "\n";

echo "\n=== PROBLEM ANALYSIS ===\n";
echo "1. The externallib.php file exists and has the external class\n";
echo "2. Some external functions are registered in the database\n";
echo "3. But the critical ones (get_subscription_details, create_billing_portal_session) are missing\n";
echo "4. This suggests the plugin installation didn't complete properly\n";
echo "5. OR the external function registration function didn't run\n";
echo "6. OR there's a classname mismatch between what's registered and what exists\n";

echo "\n=== SOLUTION OPTIONS ===\n";
echo "Option 1: Rename externallib.php to external.php (Moodle standard)\n";
echo "Option 2: Force reinstall the plugin to trigger external function registration\n";
echo "Option 3: Manually register the missing external functions\n";
echo "Option 4: Check if there's a classname mismatch in the database\n";
