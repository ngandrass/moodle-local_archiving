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

use local_archiving\type\archive_job_status;

/**
 * Tests for the mod util class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the mod util class.
 */
final class mod_util_test extends \advanced_testcase {

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
     * Tests retrieval of course module info by context.
     *
     * @covers \local_archiving\util\mod_util
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_get_cm_info(): void {
        // Prepare a course with a module.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $module = $this->generator()->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('page', $module->cmid);

        // Test retrieval of cm info.
        $cminfo = mod_util::get_cm_info(\context_module::instance($cm->id));

        $this->assertNotEmpty($cminfo, 'CM info should not be empty');
        $this->assertEquals($cm->id, $cminfo->id, 'CM ID should match');
        $this->assertEquals($cm->name, $cminfo->name, 'CM name should match');
    }

    /**
     * Tests generation and retrieval of CM metadata.
     *
     * @covers \local_archiving\util\mod_util
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_cms_with_metadata(): void {
        // Prepare an empty course and make sure nothing is returned for it.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $this->assertEmpty(mod_util::get_cms_with_metadata($course->id));

        // Add some modules to the course.
        $page1 = $this->generator()->create_module('page', ['course' => $course->id]);
        $page2 = $this->generator()->create_module('page', ['course' => $course->id]);
        $quiz1 = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $quiz2 = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $assignment = $this->generator()->create_module('assign', ['course' => $course->id]);

        // Make quiz 1 being archived successfully in the past.
        $job = $this->generator()->create_archive_job(['context' => \context_module::instance($quiz1->cmid)]);
        $job->set_status(archive_job_status::COMPLETED);

        // Try to retrieve all cms with metadata.
        $cmmeta = mod_util::get_cms_with_metadata($course->id);
        $this->assertCount(5, $cmmeta, 'Should retrieve all 5 cms');
        foreach ($cmmeta as $cm) {
            $this->assertObjectHasProperty('cm', $cm, 'CM metadata should have cm attribute');
            $this->assertObjectHasProperty('supported', $cm, 'CM metadata should have supported attribute');
            $this->assertObjectHasProperty('enabled', $cm, 'CM metadata should have enabled attribute');
            $this->assertObjectHasProperty('ready', $cm, 'CM metadata should have ready attribute');
            $this->assertObjectHasProperty('lastarchived', $cm, 'CM metadata should have lastarchived attribute');
            $this->assertObjectHasProperty('dirty', $cm, 'CM metadata should have dirty attribute');
        }

        // Now try to retrieve only supported and enabled cms.
        $cmmeta = mod_util::get_cms_with_metadata($course->id, true);
        $this->assertCount(3, $cmmeta, 'Should retrieve 3 cms (2 quizzes and 1 assignment');
        foreach ($cmmeta as $cm) {
            $this->assertTrue($cm->supported, 'CM should be supported');
            $this->assertTrue($cm->enabled, 'CM should be enabled');
        }
    }

}
