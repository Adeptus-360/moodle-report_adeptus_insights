<?php
// Test script to check API key and registration status
require_once('../../../config.php');
require_once('classes/installation_manager.php');

echo "<h2>API Key and Registration Test</h2>";

try {
    $installation_manager = new \report_adeptus_insights\installation_manager();
    
    echo "<h3>Installation Manager Status:</h3>";
    echo "<p><strong>API Key:</strong> " . ($installation_manager->get_api_key() ?: 'NOT SET') . "</p>";
    echo "<p><strong>API URL:</strong> " . $installation_manager->get_api_url() . "</p>";
    echo "<p><strong>Installation ID:</strong> " . ($installation_manager->get_installation_id() ?: 'NOT SET') . "</p>";
    echo "<p><strong>Is Registered:</strong> " . ($installation_manager->is_registered() ? 'YES' : 'NO') . "</p>";
    
    // Check database table
    global $DB;
    echo "<h3>Database Status:</h3>";
    
    if ($DB->get_manager()->table_exists('adeptus_install_settings')) {
        echo "<p><strong>Table exists:</strong> YES</p>";
        
        $settings = $DB->get_record('adeptus_install_settings', ['id' => 1]);
        if ($settings) {
            echo "<p><strong>Settings record:</strong> FOUND</p>";
            echo "<p><strong>Stored API Key:</strong> " . ($settings->api_key ?: 'EMPTY') . "</p>";
            echo "<p><strong>Stored API URL:</strong> " . ($settings->api_url ?: 'EMPTY') . "</p>";
            echo "<p><strong>Stored Installation ID:</strong> " . ($settings->installation_id ?: 'NOT SET') . "</p>";
            echo "<p><strong>Stored Is Registered:</strong> " . ($settings->is_registered ? 'YES' : 'NO') . "</p>";
        } else {
            echo "<p><strong>Settings record:</strong> NOT FOUND</p>";
        }
    } else {
        echo "<p><strong>Table exists:</strong> NO</p>";
    }
    
    // Test backend connection
    echo "<h3>Backend Connection Test:</h3>";
    try {
        $status = $installation_manager->check_site_registration_status();
        echo "<p><strong>Backend Status Check:</strong> SUCCESS</p>";
        echo "<pre>" . print_r($status, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p><strong>Backend Status Check:</strong> FAILED - " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
