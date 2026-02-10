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
 * Privacy provider tests for the Adeptus Insights report plugin.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use report_adeptus_insights\privacy\provider;

/**
 * Privacy provider test case for report_adeptus_insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \report_adeptus_insights\privacy\provider
 */
final class privacy_test extends provider_testcase {
    /**
     * Test that the provider implements the required interfaces.
     */
    public function test_provider_implements_interfaces(): void {
        $this->assertTrue(
            is_a(
                provider::class,
                \core_privacy\local\metadata\provider::class,
                true
            )
        );
        $this->assertTrue(
            is_a(
                provider::class,
                \core_privacy\local\request\plugin\provider::class,
                true
            )
        );
        $this->assertTrue(
            is_a(
                provider::class,
                \core_privacy\local\request\core_userlist_provider::class,
                true
            )
        );
    }

    /**
     * Test that metadata is returned correctly.
     */
    public function test_get_metadata(): void {
        $collection = new collection('report_adeptus_insights');
        $collection = provider::get_metadata($collection);

        $items = $collection->get_collection();
        $this->assertNotEmpty($items);

        // Check that we have database table entries.
        $tablenames = [];
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablenames[] = $item->get_name();
            }
        }

        $this->assertContains('report_adeptus_insights_analytics', $tablenames);
        $this->assertContains('report_adeptus_insights_cache', $tablenames);
        $this->assertContains('report_adeptus_insights_config', $tablenames);
        $this->assertContains('report_adeptus_insights_history', $tablenames);
        $this->assertContains('report_adeptus_insights_bookmarks', $tablenames);
        $this->assertContains('report_adeptus_insights_usage', $tablenames);
        $this->assertContains('report_adeptus_insights_exports', $tablenames);

        // Check that we have an external location link.
        $hasexternal = false;
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\external_location) {
                $hasexternal = true;
                break;
            }
        }
        $this->assertTrue($hasexternal, 'Privacy provider should declare external data link');
    }

    /**
     * Test that get_contexts_for_userid returns an empty list for a user with no data.
     */
    public function test_get_contexts_for_userid_no_data(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * Test that get_users_in_context returns empty for a non-system context.
     */
    public function test_get_users_in_context_non_system(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $userlist = new userlist($context, 'report_adeptus_insights');
        provider::get_users_in_context($userlist);

        $this->assertEmpty($userlist->get_userids());
    }

    /**
     * Test that delete_data_for_all_users_in_context handles non-system context gracefully.
     */
    public function test_delete_data_for_non_system_context(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Should not throw any exceptions.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertTrue(true); // If we get here, the test passed.
    }

    /**
     * Test that delete_data_for_users handles empty user list gracefully.
     */
    public function test_delete_data_for_empty_users(): void {
        $this->resetAfterTest();

        $context = \context_system::instance();
        $userlist = new approved_userlist($context, 'report_adeptus_insights', []);

        // Should not throw any exceptions.
        provider::delete_data_for_users($userlist);
        $this->assertTrue(true);
    }
}
