<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_archiving\util;

use core_course_category;
use local_archiving\util\course_util;

/**
 * Tests for the course util class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the course util class.
 */
final class course_util_test extends \advanced_testcase {

    /**
     * Helper to get the test data generator for local_archiving
     *
     * @return \local_archiving_generator
     */
    private function generator(): \local_archiving_generator {
        /** @var \local_archiving_generator */ // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        return self::getDataGenerator()->get_plugin_generator('local_archiving');
    }

    /**
     * Tests user group retrieval in various scenarios.
     *
     * @covers \local_archiving\util\course_util
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_get_user_groups(): void {
        // Prepare a course with groups and users.
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'idnumber' => 'g1']);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'idnumber' => 'g2']);

        // Enroll users in the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course->id);

        // Add one user to multiple groups, one to a single group, one to none.
        $this->assertTrue(groups_add_member($group1->id, $user1->id), 'Failed to add user1 to group1');
        $this->assertTrue(groups_add_member($group1->id, $user2->id), 'Failed to add user2 to group1');
        $this->assertTrue(groups_add_member($group2->id, $user2->id), 'Failed to add user2 to group2');

        // Ensure that user1 is only in group1.
        $groupids = array_map(fn ($group) => $group->id, course_util::get_user_groups($course->id, $user1->id));
        $this->assertEqualsCanonicalizing(
            [$group1->id],
            array_values($groupids),
            'User1 should only be in group1'
        );

        // Ensure that user2 is in group1 and group2.
        $groupids = array_map(fn ($group) => $group->id, course_util::get_user_groups($course->id, $user2->id));
        $this->assertEqualsCanonicalizing(
            [$group1->id, $group2->id],
            array_values($groupids),
            'User2 should be in group1 and group2'
        );

        // Ensure that user3 has no groups.
        $this->assertEmpty(course_util::get_user_groups($course->id, $user3->id), 'User3 should not be in any group');
    }

    /**
     * Tests that global archiving enablement works for top-level and nested courses.
     *
     * @covers \local_archiving\util\course_util
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_archiving_enabled_for_course_global(): void {
        // Prepare nested categories with courses.
        $this->resetAfterTest();

        $coursecat1 = $this->getDataGenerator()->create_category();
        $coursecat1sub = $this->getDataGenerator()->create_category(['parent' => $coursecat1->id]);
        $coursecat2 = $this->getDataGenerator()->create_category();
        $coursecat2sub = $this->getDataGenerator()->create_category(['parent' => $coursecat2->id]);
        $coursecat3 = $this->getDataGenerator()->create_category();

        $course1 = $this->getDataGenerator()->create_course(['category' => $coursecat1->id]);
        $course1sub = $this->getDataGenerator()->create_course(['category' => $coursecat1sub->id]);
        $course2 = $this->getDataGenerator()->create_course(['category' => $coursecat2->id]);
        $course2sub = $this->getDataGenerator()->create_course(['category' => $coursecat2sub->id]);
        $course3 = $this->getDataGenerator()->create_course(['category' => $coursecat3->id]);

        // Enable archiving for all courses.
        set_config('coursecat_whitelist', '0', 'local_archiving');

        // Ensure that archiving is enabled for all courses.
        $this->assertTrue(course_util::archiving_enabled_for_course($course1->id), 'Archiving should be enabled for course1');
        $this->assertTrue(course_util::archiving_enabled_for_course($course1sub->id), 'Archiving should be enabled for course1sub');
        $this->assertTrue(course_util::archiving_enabled_for_course($course2->id), 'Archiving should be enabled for course2');
        $this->assertTrue(course_util::archiving_enabled_for_course($course2sub->id), 'Archiving should be enabled for course2sub');
        $this->assertTrue(course_util::archiving_enabled_for_course($course3->id), 'Archiving should be enabled for course3');
    }

    /**
     * Tests that selective archiving enablement works for top-level and nested courses.
     *
     * @covers \local_archiving\util\course_util
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_archiving_enabled_for_course_selective(): void {
        // Prepare nested categories with courses.
        $this->resetAfterTest();

        $coursecat1 = $this->getDataGenerator()->create_category();
        $coursecat1sub = $this->getDataGenerator()->create_category(['parent' => $coursecat1->id]);
        $coursecat2 = $this->getDataGenerator()->create_category();
        $coursecat2sub = $this->getDataGenerator()->create_category(['parent' => $coursecat2->id]);
        $coursecat3 = $this->getDataGenerator()->create_category();

        $course1 = $this->getDataGenerator()->create_course(['category' => $coursecat1->id]);
        $course1sub = $this->getDataGenerator()->create_course(['category' => $coursecat1sub->id]);
        $course2 = $this->getDataGenerator()->create_course(['category' => $coursecat2->id]);
        $course2sub = $this->getDataGenerator()->create_course(['category' => $coursecat2sub->id]);
        $course3 = $this->getDataGenerator()->create_course(['category' => $coursecat3->id]);

        // Enable archiving for selected course categories.
        set_config(
            'coursecat_whitelist',
            implode(',', [$coursecat1->id, $coursecat2sub->id]), // Covers Cat1 + Cat1Sub + Cat2Sub.
            'local_archiving'
        );

        // Ensure that archiving is enabled for all courses inside configured categories.
        $this->assertTrue(course_util::archiving_enabled_for_course($course1->id), 'Archiving should be enabled for course1');
        $this->assertTrue(course_util::archiving_enabled_for_course($course1sub->id), 'Archiving should be enabled for course1sub');
        $this->assertTrue(course_util::archiving_enabled_for_course($course2sub->id), 'Archiving should be enabled for course2sub');

        $this->assertFalse(course_util::archiving_enabled_for_course($course3->id), 'Archiving should be disabled for course3');
        $this->assertFalse(course_util::archiving_enabled_for_course($course2->id), 'Archiving should be disabled for course2');
    }

    /**
     * Tests that that trying to assess archiving enablement for a non-existing course throws an exception.
     *
     * @covers \local_archiving\util\course_util
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_archiving_enabled_for_course_invalid_course(): void {
        // Limit archiving to a specific category to avoid global enablement case.
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        set_config('coursecat_whitelist', $category->id, 'local_archiving');

        // Ensure that checking a non-existing course ID throws an exception.
        $this->expectException(\moodle_exception::class);
        course_util::archiving_enabled_for_course(999999); // Non-existing course ID.
    }

    /**
     * Tests retrieval of archivable course categories in various scenarios.
     *
     * @covers \local_archiving\util\course_util
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_get_archivable_course_category_ids(): void {
        // Prepare nested categories with courses.
        $this->resetAfterTest();

        $coursecat1 = $this->getDataGenerator()->create_category();
        $coursecat1sub = $this->getDataGenerator()->create_category(['parent' => $coursecat1->id]);
        $coursecat2 = $this->getDataGenerator()->create_category();
        $coursecat2sub = $this->getDataGenerator()->create_category(['parent' => $coursecat2->id]);
        $coursecat3 = $this->getDataGenerator()->create_category();

        // Check that everything is returned if we enable global archiving.
        set_config('coursecat_whitelist', '0', 'local_archiving');
        $allcatids = core_course_category::top()->get_all_children_ids();
        $this->assertEqualsCanonicalizing(
            $allcatids,
            course_util::get_archivable_course_category_ids(),
            'All course categories should be returned when global archiving is enabled'
        );

        // Check that only selected categories are returned if we enable selective archiving.
        set_config(
            'coursecat_whitelist',
            implode(',', [$coursecat1->id, $coursecat2sub->id]), // Covers Cat1 + Cat1Sub + Cat2Sub.
            'local_archiving'
        );
        $this->assertEqualsCanonicalizing(
            [$coursecat1->id, $coursecat1sub->id, $coursecat2sub->id],
            course_util::get_archivable_course_category_ids(),
            'Only selected course categories should be returned when selective archiving is enabled'
        );
    }

}
