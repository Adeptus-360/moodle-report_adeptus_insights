<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
// Explicitly include the ETL task class in case the autoloader cache is not yet rebuilt.
require_once(__DIR__ . '/../classes/task/build_analytics_base.php');

use report_adeptus_insights\task\build_analytics_base;

try {
    $task = new build_analytics_base();
    $task->execute();
    echo "✅ ETL task completed successfully.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
