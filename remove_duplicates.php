<?php
define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

echo "Removing Duplicate External Functions\n";
echo "====================================\n\n";

try {
    global $DB;
    
    $functions = $DB->get_records('external_functions', ['component' => 'report_adeptus_insights']);
    echo "Found " . count($functions) . " external functions\n";
    
    $duplicates = [];
    $seen = [];
    
    foreach ($functions as $func) {
        if (isset($seen[$func->name])) {
            $duplicates[] = $func;
        } else {
            $seen[$func->name] = $func;
        }
    }
    
    if (empty($duplicates)) {
        echo "✅ No duplicates found!\n";
    } else {
        echo "Found " . count($duplicates) . " duplicates:\n";
        foreach ($duplicates as $dup) {
            echo "- {$dup->name} (ID: {$dup->id})\n";
        }
        
        echo "\nRemoving duplicates...\n";
        
        foreach ($duplicates as $dup) {
            $DB->delete_records('external_functions', ['id' => $dup->id]);
            echo "✅ Removed: {$dup->name}\n";
        }
        
        echo "\n✅ All duplicates removed!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nYou can now go back to the admin dashboard and complete the plugin upgrade.\n";
?>
