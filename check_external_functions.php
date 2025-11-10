<?php
define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->libdir . '/externallib.php');

echo "=== ADEPTUS INSIGHTS PLUGIN EXTERNAL FUNCTIONS DIAGNOSTIC ===\n\n";

// Check what external functions exist for this plugin
$functions = $DB->get_records('external_functions', array(), '', 'name, classname, methodname, capabilities');
echo "All external functions for this plugin:\n";
$plugin_functions = [];
foreach ($functions as $func) {
    if (strpos($func->name, 'report_adeptus_insights_') === 0) {
        $plugin_functions[] = $func;
        echo "  ✓ {$func->name} -> {$func->classname}::{$func->methodname}\n";
    }
}

if (empty($plugin_functions)) {
    echo "  ✗ No external functions found for report_adeptus_insights\n";
}

echo "\n=== PLUGIN FILES STATUS ===\n";

// Check if externallib.php exists and has the right class
$externallib_path = __DIR__ . '/externallib.php';
if (file_exists($externallib_path)) {
    echo "✓ externallib.php exists\n";
    
    // Check if the external class is defined
    $content = file_get_contents($externallib_path);
    if (strpos($content, 'class external extends') !== false) {
        echo "✓ external class found in externallib.php\n";
    } else {
        echo "✗ external class NOT found in externallib.php\n";
    }
} else {
    echo "✗ externallib.php NOT found\n";
}

// Check if external.php exists (Moodle's preferred location)
$external_path = __DIR__ . '/external.php';
if (file_exists($external_path)) {
    echo "✓ external.php exists\n";
} else {
    echo "✗ external.php NOT found (Moodle expects this)\n";
}

// Check if classes/external.php exists
$classes_external_path = __DIR__ . '/classes/external.php';
if (file_exists($classes_external_path)) {
    echo "✓ classes/external.php exists\n";
} else {
    echo "✗ classes/external.php NOT found\n";
}

echo "\n=== SERVICES.PHP STATUS ===\n";
$services_path = __DIR__ . '/db/services.php';
if (file_exists($services_path)) {
    echo "✓ db/services.php exists\n";
    $services_content = file_get_contents($services_path);
    echo "  Functions defined: " . substr_count($services_content, 'report_adeptus_insights_') . "\n";
} else {
    echo "✗ db/services.php NOT found\n";
}

echo "\n=== INSTALL.PHP STATUS ===\n";
$install_path = __DIR__ . '/db/install.php';
if (file_exists($install_path)) {
    echo "✓ db/install.php exists\n";
    $install_content = file_get_contents($install_path);
    if (strpos($install_content, 'report_adeptus_insights_register_external_functions') !== false) {
        echo "✓ External function registration function exists\n";
    } else {
        echo "✗ External function registration function NOT found\n";
    }
} else {
    echo "✗ db/install.php NOT found\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Moodle expects external functions in 'external.php' in the plugin root\n";
echo "2. Current 'externallib.php' is not being loaded by Moodle\n";
echo "3. Need to either rename externallib.php to external.php OR\n";
echo "4. Ensure Moodle loads the externallib.php file properly\n";
echo "5. External functions are not registered in database\n";
echo "6. Plugin installation may not have completed properly\n";
