<?php
define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->libdir . '/externallib.php');

echo "Testing external functions:\n";

// Check if our specific functions are in the database
global $DB;
$function = $DB->get_record('external_functions', array('name' => 'report_adeptus_insights_get_subscription_details'));
if ($function) {
    echo "✓ report_adeptus_insights_get_subscription_details function found in database.\n";
} else {
    echo "✗ report_adeptus_insights_get_subscription_details function NOT found in database.\n";
}

$function = $DB->get_record('external_functions', array('name' => 'report_adeptus_insights_create_billing_portal_session'));
if ($function) {
    echo "✓ report_adeptus_insights_create_billing_portal_session function found in database.\n";
} else {
    echo "✗ report_adeptus_insights_create_billing_portal_session function NOT found in database.\n";
}

// Check if the plugin's external functions are loaded
$plugin_path = 'report/adeptus_insights';
$external_file = $CFG->dirroot . '/' . $plugin_path . '/external.php';
if (file_exists($external_file)) {
    echo "✓ External functions file exists: $external_file\n";
} else {
    echo "✗ External functions file NOT found: $external_file\n";
}
echo "Done.\n";

