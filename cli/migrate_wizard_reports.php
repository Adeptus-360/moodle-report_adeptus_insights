<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI script to migrate wizard reports from local Moodle DB to backend API.
 *
 * Usage: php migrate_wizard_reports.php
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// CLI options.
[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'dry-run' => false,
    'delete-after' => false,
], [
    'h' => 'help',
    'd' => 'dry-run',
]);

if ($options['help']) {
    echo "
Migrate wizard reports from local Moodle database to backend API.

Usage:
  php migrate_wizard_reports.php [options]

Options:
  -h, --help          Show this help message
  -d, --dry-run       Show what would be migrated without actually doing it
  --delete-after      Delete local records after successful migration

";
    exit(0);
}

$dryrun = $options['dry-run'];
$deleteafter = $options['delete-after'];

cli_heading('Wizard Reports Migration');

// Get API configuration.
$installationmanager = new \report_adeptus_insights\installation_manager();
$apikey = $installationmanager->get_api_key();
$backendapiurl = \report_adeptus_insights\api_config::get_backend_url();

if (empty($apikey)) {
    cli_error('API key not configured. Please complete plugin registration first.');
}

cli_writeln("Backend URL: {$backendapiurl}");
cli_writeln("Dry run: " . ($dryrun ? 'Yes' : 'No'));
cli_writeln("");

// Fetch all local wizard reports.
$localreports = $DB->get_records('report_adeptus_insights_generated', null, 'generatedat DESC');
$total = count($localreports);

cli_writeln("Found {$total} local wizard reports to migrate.");
cli_writeln("");

if ($total === 0) {
    cli_writeln("Nothing to migrate.");
    exit(0);
}

$migrated = 0;
$failed = 0;
$skipped = 0;

foreach ($localreports as $report) {
    $reportname = $report->reportid;
    $userid = $report->userid;

    cli_write("Migrating report '{$reportname}' for user {$userid}... ");

    // Parse parameters.
    $parameters = [];
    if (!empty($report->parameters)) {
        $params = json_decode($report->parameters, true);
        if (is_array($params)) {
            $parameters = $params;
        }
    }

    // Prepare data for backend.
    $wizardreportdata = [
        'user_id' => $userid,
        'report_template_id' => $reportname,
        'name' => $reportname,
        'parameters' => $parameters,
    ];

    if ($dryrun) {
        cli_writeln("WOULD MIGRATE (dry-run)");
        $migrated++;
        continue;
    }

    // Call backend API to save the wizard report using Moodle's curl wrapper.
    $curl = new \curl();
    $curl->setHeader('Content-Type: application/json');
    $curl->setHeader('Accept: application/json');
    $curl->setHeader('Authorization: Bearer ' . $apikey);

    $options = [
        'CURLOPT_TIMEOUT' => 30,
        'CURLOPT_SSL_VERIFYPEER' => true,
    ];

    $response = $curl->post($backendapiurl . '/wizard-reports', json_encode($wizardreportdata), $options);
    $info = $curl->get_info();
    $httpcode = $info['http_code'] ?? 0;
    $curlerror = $curl->get_errno() ? $curl->error : '';

    if ($httpcode === 201 || $httpcode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['success'])) {
            cli_writeln("OK (slug: " . ($data['report']['slug'] ?? 'unknown') . ")");
            $migrated++;

            // Delete local record if requested.
            if ($deleteafter) {
                $DB->delete_records('report_adeptus_insights_generated', ['id' => $report->id]);
                cli_writeln("  -> Deleted local record");
            }
        } else {
            cli_writeln("FAILED: " . ($data['message'] ?? 'Unknown error'));
            $failed++;
        }
    } else {
        cli_writeln("FAILED: HTTP {$httpcode} - {$curlerror}");
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data['message'])) {
                cli_writeln("  -> " . $data['message']);
            }
        }
        $failed++;
    }
}

cli_writeln("");
cli_writeln("Migration complete!");
cli_writeln("  Migrated: {$migrated}");
cli_writeln("  Failed: {$failed}");
cli_writeln("  Skipped: {$skipped}");
cli_writeln("  Total: {$total}");

if ($dryrun) {
    cli_writeln("");
    cli_writeln("This was a dry run. Run without --dry-run to actually migrate.");
}

if (!$deleteafter && $migrated > 0 && !$dryrun) {
    cli_writeln("");
    cli_writeln("Note: Local records were NOT deleted. Run with --delete-after to clean up.");
}
