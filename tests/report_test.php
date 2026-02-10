<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Basic unit tests for the Adeptus Insights report plugin.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;
/**
 * Basic test case for report_adeptus_insights plugin.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \report_adeptus_insights\api_config
 * @covers      \report_adeptus_insights\report_validator
 */
final class report_test extends \advanced_testcase {
    /**
     * Test that the plugin version file is valid.
     */
    public function test_version_file_exists(): void {
        global $CFG;
        $versionfile = $CFG->dirroot . '/report/adeptus_insights/version.php';
        $this->assertFileExists($versionfile);
    }

    /**
     * Test that the plugin component name is correctly set.
     */
    public function test_plugin_component(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/report/adeptus_insights/version.php');
        $this->assertEquals('report_adeptus_insights', $plugin->component);
    }

    /**
     * Test that the plugin requires at least Moodle 4.1.
     */
    public function test_plugin_requires_moodle_41(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/report/adeptus_insights/version.php');
        // Moodle 4.1 version number.
        $this->assertGreaterThanOrEqual(2022112800, $plugin->requires);
    }

    /**
     * Test that the view capability is defined.
     */
    public function test_capability_defined(): void {
        $this->resetAfterTest();
        // Check that the capability exists in the system.
        $capabilities = get_all_capabilities();
        $capnames = array_column($capabilities, 'name');
        $this->assertContains('report/adeptus_insights:view', $capnames);
    }

    /**
     * Test that API config class can be instantiated and returns a URL.
     */
    public function test_api_config_returns_backend_url(): void {
        $url = api_config::get_backend_url();
        $this->assertNotEmpty($url);
        $this->assertStringStartsWith('https://', $url);
    }

    /**
     * Test that the report validator class exists and can be used.
     */
    public function test_report_validator_class_exists(): void {
        $this->assertTrue(class_exists('\report_adeptus_insights\report_validator'));
    }

    /**
     * Test that the token auth manager class exists.
     */
    public function test_token_auth_manager_class_exists(): void {
        $this->assertTrue(class_exists('\report_adeptus_insights\token_auth_manager'));
    }

    /**
     * Test that the installation manager class exists.
     */
    public function test_installation_manager_class_exists(): void {
        $this->assertTrue(class_exists('\report_adeptus_insights\installation_manager'));
    }

    /**
     * Test that the error handler class exists.
     */
    public function test_error_handler_class_exists(): void {
        $this->assertTrue(class_exists('\report_adeptus_insights\error_handler'));
    }

    /**
     * Test that language strings are loadable.
     */
    public function test_language_strings_exist(): void {
        $pluginname = get_string('pluginname', 'report_adeptus_insights');
        $this->assertEquals('Adeptus Insights', $pluginname);

        $viewcap = get_string('adeptus_insights:view', 'report_adeptus_insights');
        $this->assertNotEmpty($viewcap);
    }

    /**
     * Test that scheduled tasks are defined.
     */
    public function test_scheduled_tasks_defined(): void {
        $tasks = \core\task\manager::get_all_scheduled_tasks();
        $taskclasses = array_map(function ($task) {
            return get_class($task);
        }, $tasks);

        $this->assertContains(
            'report_adeptus_insights\task\build_materialized_table',
            $taskclasses
        );
        $this->assertContains(
            'report_adeptus_insights\task\build_analytics_base',
            $taskclasses
        );
    }

    /**
     * Test that a user with the view capability can access the plugin.
     */
    public function test_capability_access(): void {
        $this->resetAfterTest();

        // Create a user and assign them the manager role.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = \context_system::instance();
        $managerroleid = $this->getDataGenerator()->create_role([
            'shortname' => 'testmanager',
            'name' => 'Test Manager',
        ]);
        assign_capability('report/adeptus_insights:view', CAP_ALLOW, $managerroleid, $context->id);
        role_assign($managerroleid, $user->id, $context->id);

        // Verify capability.
        $this->assertTrue(has_capability('report/adeptus_insights:view', $context));
    }
}
