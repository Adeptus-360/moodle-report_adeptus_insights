<?php
define('CLI_SCRIPT', true);
require_once('../../config.php');

echo "Testing language strings:\n";
echo "Plugin name: " . get_string('pluginname', 'report_adeptus_insights') . "\n";
echo "Welcome: " . get_string('welcome_to_adeptus', 'report_adeptus_insights') . "\n";
echo "Report wizard: " . get_string('report_wizard', 'report_adeptus_insights') . "\n";
echo "Done.\n";


