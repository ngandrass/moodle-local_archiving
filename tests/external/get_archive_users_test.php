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

namespace local_archiving\external;

use core\exception\coding_exception;
use core_external\external_api;
use core_external\restricted_context_exception;
use local_archiving\local\type\task_content_metadata;

/**
 * Tests for the get_archive_users external function.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the get_archive_users external function.
 */
final class get_archive_users_test extends \advanced_testcase {
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
     * Creates a course with two activities and users in various relations to it
     *
     * @return \stdClass Object holding the created course, cms, and users
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function prepare_course(): \stdClass {
        $course = $this->generator()->create_course();
        $cm1 = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $cm2 = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $othercourse = $this->generator()->create_course();

        $data = (object) [
            'course' => $course,
            'cm1' => $cm1,
            'cm2' => $cm2,
            'enrolled' => $this->generator()->create_user(['firstname' => 'Enrolled']),
            'suspended' => $this->generator()->create_user(['firstname' => 'Suspended']),
            'archivedcm1' => $this->generator()->create_user(['firstname' => 'Archivedone']),
            'archivedcm2' => $this->generator()->create_user(['firstname' => 'Archivedtwo']),
            'othercourse' => $this->generator()->create_user(['firstname' => 'Othercourse']),
        ];

        $this->generator()->enrol_user($data->enrolled->id, $course->id, 'student');
        $this->generator()->enrol_user($data->suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->generator()->enrol_user($data->othercourse->id, $othercourse->id, 'student');

        // Users with archived data, but without enrolment.
        $this->create_archived_content($course, $cm1, $data->archivedcm1->id);
        $this->create_archived_content($course, $cm2, $data->archivedcm2->id);

        return $data;
    }

    /**
     * Creates an archive job inside the given activity that holds content of the given user
     *
     * @param \stdClass $course Course the activity belongs to
     * @param \stdClass $cm Activity to create the archive job for
     * @param int $userid ID of the user that owns the archived content
     * @return \local_archiving\archive_job Created archive job
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function create_archived_content(\stdClass $course, \stdClass $cm, int $userid): \local_archiving\archive_job {
        $job = $this->generator()->create_archive_job([], $course, $cm);
        $task = $this->generator()->create_activity_archiving_task([], $job);
        $task->store_task_content_metadata([
            new task_content_metadata($task->get_id(), $userid, null, null, 'Test content'),
        ]);

        return $job;
    }

    /**
     * Calls the external function and returns the IDs of all found users
     *
     * @param \context $ctx Context to search users in
     * @param string $query Search query
     * @return int[] IDs of all found users
     * @throws \coding_exception
     * @throws coding_exception
     * @throws restricted_context_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     * @throws \required_capability_exception
     */
    private function search_userids(\context $ctx, string $query = ''): array {
        $result = external_api::clean_returnvalue(
            get_archive_users::execute_returns(),
            get_archive_users::execute($ctx->id, $query)
        );

        return array_map(fn ($user) => (int) $user['id'], $result['list']);
    }

    /**
     * Tests that all course participants and all users with archived data in the course are found
     *
     * @covers \local_archiving\external\get_archive_users
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_course_context_candidates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->prepare_course();

        $ids = $this->search_userids(\context_course::instance($data->course->id));

        $this->assertContains((int) $data->enrolled->id, $ids, 'Enrolled user should be found');
        $this->assertContains((int) $data->suspended->id, $ids, 'Suspended user should be found');
        $this->assertContains((int) $data->archivedcm1->id, $ids, 'User with archived data in activity 1 should be found');
        $this->assertContains((int) $data->archivedcm2->id, $ids, 'User with archived data in activity 2 should be found');
        $this->assertNotContains((int) $data->othercourse->id, $ids, 'User from other course must not be found');
    }

    /**
     * Tests that only users with archived data in the current activity are added to the course participants
     *
     * @covers \local_archiving\external\get_archive_users
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_module_context_candidates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->prepare_course();

        $ids = $this->search_userids(\context_module::instance($data->cm1->cmid));

        $this->assertContains((int) $data->enrolled->id, $ids, 'Enrolled user should be found');
        $this->assertContains((int) $data->suspended->id, $ids, 'Suspended user should be found');
        $this->assertContains((int) $data->archivedcm1->id, $ids, 'User with archived data in this activity should be found');
        $this->assertNotContains(
            (int) $data->archivedcm2->id,
            $ids,
            'User with archived data only in another activity must not be found'
        );
        $this->assertNotContains((int) $data->othercourse->id, $ids, 'User from other course must not be found');
    }

    /**
     * Tests that the search query restricts the results
     *
     * @covers \local_archiving\external\get_archive_users
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->prepare_course();

        $ids = $this->search_userids(\context_course::instance($data->course->id), 'chivedon');

        $this->assertEquals([(int) $data->archivedcm1->id], $ids);
    }

    /**
     * Tests that the result is truncated and flagged if too many users are found
     *
     * @covers \local_archiving\external\get_archive_users
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_overflow(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->prepare_course();
        set_config('maxusersperpage', 2);

        $result = get_archive_users::execute(\context_course::instance($data->course->id)->id, '');

        $this->assertTrue($result['overflow']);
        $this->assertCount(2, $result['list']);
    }

    /**
     * Tests that users without the view capability are rejected
     *
     * @covers \local_archiving\external\get_archive_users
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_requires_view_capability(): void {
        $this->resetAfterTest();
        $data = $this->prepare_course();
        $this->setUser($data->enrolled);

        $this->expectException(\required_capability_exception::class);
        get_archive_users::execute(\context_course::instance($data->course->id)->id, '');
    }

    /**
     * Tests that contexts other than course or module contexts are rejected
     *
     * @covers \local_archiving\external\get_archive_users
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public function test_rejects_invalid_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        get_archive_users::execute(\context_system::instance()->id, '');
    }

    /**
     * Tests that single users are only exported if they are valid candidates
     *
     * @covers \local_archiving\external\get_archive_users
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_exported_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $data = $this->prepare_course();
        $cmctx = \context_module::instance($data->cm1->cmid);

        $user = get_archive_users::get_exported_user($data->archivedcm1->id, $cmctx);
        $this->assertNotNull($user);
        $this->assertEquals($data->archivedcm1->id, $user->id);
        $this->assertEquals(fullname($data->archivedcm1), $user->fullname);

        $this->assertNull(get_archive_users::get_exported_user($data->archivedcm2->id, $cmctx));
        $this->assertNull(get_archive_users::get_exported_user($data->othercourse->id, $cmctx));
        $this->assertNull(get_archive_users::get_exported_user(-1, $cmctx));
    }
}
