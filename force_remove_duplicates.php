<?php
define('CLI_SCRIPT', true);
require_once('../../config.php');

echo "Force Removing All External Functions for report_adeptus_insights\n";
echo "=============================================================\n\n";

try {
    global $DB;
    
    // First, let's see what we have
    $functions = $DB->get_records('external_functions', ['component' => 'report_adeptus_insights']);
    echo "Current external functions for report_adeptus_insights: " . count($functions) . "\n";
    
    if (!empty($functions)) {
        echo "Functions found:\n";
        foreach ($functions as $func) {
            echo "- {$func->name} (ID: {$func->id})\n";
        }
        
        echo "\nRemoving ALL external functions for this plugin...\n";
        
        // Delete all functions for this component
        $deleted = $DB->delete_records('external_functions', ['component' => 'report_adeptus_insights']);
        
        echo "✅ Deleted {$deleted} external functions\n";
        echo "✅ Database is now clean for this plugin\n";
    } else {
        echo "✅ No external functions found - database is already clean\n";
    }
    
    // Verify deletion
    $remaining = $DB->get_records('external_functions', ['component' => 'report_adeptus_insights']);
    echo "\nVerification: " . count($remaining) . " functions remaining\n";
    
    if (count($remaining) === 0) {
        echo "✅ SUCCESS: All external functions removed!\n";
        echo "\nNow you can:\n";
        echo "1. Go to Moodle admin dashboard\n";
        echo "2. Run the plugin upgrade\n";
        echo "3. External functions will be recreated cleanly\n";
    } else {
        echo "❌ ERROR: Some functions still remain\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
