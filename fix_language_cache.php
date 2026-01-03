<?php
require_once('../../config.php');

// Check if user is admin
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h1>Language Cache Fix for Adeptus Insights</h1>";

// Check if language file exists and has content
$lang_file = __DIR__ . '/lang/en/report_adeptus_insights.php';
if (file_exists($lang_file)) {
    $file_size = filesize($lang_file);
    echo "<p><strong>Language file status:</strong> ";
    if ($file_size > 0) {
        echo "<span style='color: green;'>✓ Exists and has content (" . $file_size . " bytes)</span>";
    } else {
        echo "<span style='color: red;'>✗ Exists but is empty</span>";
    }
    echo "</p>";
} else {
    echo "<p><strong>Language file status:</strong> <span style='color: red;'>✗ File not found</span></p>";
}

// Test a few language strings
echo "<h2>Language String Test</h2>";
try {
    $pluginname = get_string('pluginname', 'report_adeptus_insights');
    echo "<p><strong>Plugin Name:</strong> <span style='color: green;'>" . $pluginname . "</span></p>";
} catch (Exception $e) {
    echo "<p><strong>Plugin Name:</strong> <span style='color: red;'>Error: " . $e->getMessage() . "</span></p>";
}

try {
    $welcome = get_string('welcome_to_adeptus', 'report_adeptus_insights');
    echo "<p><strong>Welcome:</strong> <span style='color: green;'>" . $welcome . "</span></p>";
} catch (Exception $e) {
    echo "<p><strong>Welcome:</strong> <span style='color: red;'>Error: " . $e->getMessage() . "</span></p>";
}

try {
    $description = get_string('welcome_description', 'report_adeptus_insights');
    echo "<p><strong>Description:</strong> <span style='color: green;'>" . $description . "</span></p>";
} catch (Exception $e) {
    echo "<p><strong>Description:</strong> <span style='color: red;'>Error: " . $e->getMessage() . "</span></p>";
}

// Check plugin installation status
echo "<h2>Plugin Installation Status</h2>";
$plugin_manager = core_plugin_manager::instance();
$plugin_info = $plugin_manager->get_plugin_info('report_adeptus_insights');

if ($plugin_info) {
    echo "<p><strong>Plugin Status:</strong> <span style='color: green;'>✓ Installed</span></p>";
    echo "<p><strong>Version:</strong> " . $plugin_info->versiondb . "</p>";
    echo "<p><strong>Release:</strong> " . $plugin_info->release . "</p>";
    echo "<p><strong>Maturity:</strong> " . $plugin_info->maturity . "</p>";
} else {
    echo "<p><strong>Plugin Status:</strong> <span style='color: red;'>✗ Not found</span></p>";
}

// Instructions for fixing
echo "<h2>How to Fix Language Cache Issues</h2>";
echo "<ol>";
echo "<li><strong>Purge All Caches:</strong> Go to Site Administration → Development → Purge all caches</li>";
echo "<li><strong>Alternative:</strong> Add ?purgecaches=1 to any URL (e.g., " . $CFG->wwwroot . "?purgecaches=1)</li>";
echo "<li><strong>Check Language Settings:</strong> Go to Site Administration → Language → Language settings</li>";
echo "<li><strong>Reinstall Plugin:</strong> If caches don't work, try reinstalling the plugin</li>";
echo "</ol>";

echo "<h2>Quick Cache Purge</h2>";
echo "<p><a href='" . $CFG->wwwroot . "?purgecaches=1' class='btn btn-primary'>Purge All Caches Now</a></p>";

echo "<h2>Test the Plugin</h2>";
echo "<p><a href='index.php' class='btn btn-success'>Go to Plugin Index</a></p>";

echo "<h2>Summary</h2>";
echo "<p>If the language strings are still showing as placeholders after purging caches, there might be a deeper issue with the plugin installation.</p>";
echo "<p>The most common solution is purging all caches, which forces Moodle to reload all language files.</p>";
?>
