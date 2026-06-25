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

namespace archivingtrigger_cron;


/**
 * Tests for the archivingtrigger_cron implementation.
 *
 * @package   archivingtrigger_cron
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingtrigger_cron implementation.
 */
final class archivingtrigger_test extends \advanced_testcase {
    /**
     * Tests selection of activities for automatic archiving based on their location and status
     *
     * @covers \archivingtrigger_cron\archivingtrigger
     *
     * @return void
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_cms_to_archive(): void {
        // Prepare course categories, courses, and activities to archive.
        $this->resetAfterTest();

        $archivingcat = $this->getDataGenerator()->create_category(['name' => 'Archiving Category']);
        $ignorecat = $this->getDataGenerator()->create_category(['name' => 'Ignore Category']);

        $archivingcourse = $this->getDataGenerator()->create_course(['category' => $archivingcat->id]);
        $ignorecourse = $this->getDataGenerator()->create_course(['category' => $ignorecat->id]);

        $archivingquiz1 = $this->getDataGenerator()->create_module('quiz', ['course' => $archivingcourse->id, 'name' => 'Quiz 1']);
        $archivingquiz2 = $this->getDataGenerator()->create_module('quiz', ['course' => $archivingcourse->id, 'name' => 'Quiz 2']);
        $this->getDataGenerator()->create_module('quiz', ['course' => $ignorecourse->id, 'name' => 'Ignore Quiz']);

        // Create archiving trigger with mocked CM metadata.
        $triggermock = $this->getMockBuilder(archivingtrigger::class)
            ->onlyMethods(['get_course_cms_with_metadata'])
            ->getMock();
        $triggermock->method('get_course_cms_with_metadata')->willReturnCallback(
            function ($courseid) use ($archivingcourse, $archivingquiz1, $archivingquiz2) {
                if ($courseid != $archivingcourse->id) {
                    return [];
                }

                $courseinfo = get_fast_modinfo($archivingcourse);

                return [
                    // Quiz 1 was never archived, so it should be selected.
                    (object) [
                        'cm' => $courseinfo->get_cm($archivingquiz1->cmid),
                        'supported' => true,
                        'enabled' => true,
                        'ready' => true,
                        'lastarchived' => null,
                        'dirty' => true,
                    ],
                    // Quiz 2 was archived and is unchanged, so it should not be selected unless forced.
                    (object) [
                        'cm' => $courseinfo->get_cm($archivingquiz2->cmid),
                        'supported' => true,
                        'enabled' => true,
                        'ready' => true,
                        'lastarchived' => time(),
                        'dirty' => false,
                    ],
                ];
            }
        );

        // Retrieve activities to archive, excluding unchanged activities.
        ob_start();
        $cms = $triggermock->get_cms_to_archive(includeunchanged: false);
        ob_end_clean();
        $this->assertCount(1, $cms, 'Expected one activity to be selected for archiving.');
        $this->assertEquals($archivingquiz1->cmid, $cms[0]->cm->id, 'Expected Quiz 1 to be selected for archiving.');

        // Retrieve activities to archive, including unchanged activities.
        ob_start();
        $cms = $triggermock->get_cms_to_archive(includeunchanged: true);
        ob_end_clean();
        $this->assertCount(2, $cms, 'Expected two activities to be selected for archiving.');
        $cmids = array_map(fn($cm) => (int) $cm->cm->id, $cms);
        $this->assertContains($archivingquiz1->cmid, $cmids, 'Expected Quiz 1 to be included.');
        $this->assertContains($archivingquiz2->cmid, $cmids, 'Expected Quiz 2 to be included.');

        // Create archive job for Quiz 1 to simulate it being in progress and test that it is not selected again.
        $triggermock->create_archive_job($cms[0]->cm);
        ob_start();
        $cms = $triggermock->get_cms_to_archive(includeunchanged: false);
        ob_end_clean();
        $this->assertCount(0, $cms, 'Expected no activities to be selected for archiving since Quiz 1 is in progress.');
    }

    /**
     * Tests that archive jobs are created correctly
     *
     * @covers \archivingtrigger_cron\archivingtrigger
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_archive_job(): void {
        global $DB;
        $this->resetAfterTest();

        // Create a course with two quizzes.
        $course = $this->getDataGenerator()->create_course();
        $quiz1 = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $quiz2 = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $courseinfo = get_fast_modinfo($course);
        $cminfo1 = $courseinfo->get_cm($quiz1->cmid);
        $cminfo2 = $courseinfo->get_cm($quiz2->cmid);

        // Prepare all necessary job defaults for headless job creation to work.
        set_config('job_preset_storage_driver', 'moodle', 'local_archiving');

        // Create two archive jobs.
        $trigger = new archivingtrigger();
        $trigger->create_archive_job($cminfo1);
        $trigger->create_archive_job($cminfo2);

        // Check that one job was created for each quiz.
        $jobs = $DB->get_records(\local_archiving\type\db_table::JOB->value);
        $this->assertCount(2, $jobs, 'Expected two archive jobs to be created.');
        foreach ($jobs as $job) {
            $this->assertContains(
                (int) $job->contextid,
                [$cminfo1->context->id, $cminfo2->context->id],
                'Unexpected context ID in archive job.'
            );
        }
    }
}
