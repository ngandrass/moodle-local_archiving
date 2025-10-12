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

namespace local_archiving;

use core\exception\moodle_exception;
use local_archiving\type\filearea;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

global $CFG;
require_once($CFG->dirroot . '/local/archiving/lib.php');

/**
 * Tests for legacy lib definitions.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class local_archiving_lib_test extends \advanced_testcase {
    /**
     * Test navigation node injection for course and module contexts using Moodle generators.
     *
     * @covers ::local_archiving_extend_settings_navigation
     *
     * @return void
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public function test_extend_settings_navigation_injects_nodes(): void {
        // Prepare test data.
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $this->setUser($user);

        $module = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('page', $module->cmid);

        // Get course and cm contexts.
        $coursecontext = \context_course::instance($course->id);
        $modulecontext = \context_module::instance($cm->id);

        // Assign capability to user for both contexts.
        $roleid = $generator->create_role();
        assign_capability('local/archiving:view', CAP_ALLOW, $roleid, $coursecontext->id);
        assign_capability('local/archiving:view', CAP_ALLOW, $roleid, $modulecontext->id);
        role_assign($roleid, $user->id, $coursecontext->id);
        role_assign($roleid, $user->id, $modulecontext->id);

        // Use a real settings_navigation object with a dummy page.
        $page = new \moodle_page();
        $page->set_context($coursecontext);
        $settingsnav = new \settings_navigation($page);
        // Add dummy parent nodes for test injection.
        $courseadminnode = $settingsnav->add(
            'Course admin',
            null,
            \navigation_node::TYPE_CONTAINER,
            'courseadmin',
            'courseadmin'
        );
        $modulesettingsnode = $settingsnav->add(
            'Module settings',
            null,
            \navigation_node::TYPE_CONTAINER,
            'modulesettings',
            'modulesettings'
        );

        // Test course context injection.
        local_archiving_extend_settings_navigation($settingsnav, $coursecontext);
        $foundnode = $courseadminnode->find('local_archiving', \navigation_node::TYPE_SETTING);
        $this->assertNotNull($foundnode, 'Course context navigation node should be added');
        $this->assertEquals(get_string('pluginname', 'local_archiving'), $foundnode->text);

        // Test module context injection.
        local_archiving_extend_settings_navigation($settingsnav, $modulecontext);
        $foundnode = $modulesettingsnode->find('local_archiving', \navigation_node::TYPE_SETTING);
        $this->assertNotNull($foundnode, 'Module context navigation node should be added');
        $this->assertEquals(get_string('pluginname', 'local_archiving'), $foundnode->text);
    }

    /**
     * Test pluginfile serving for valid and invalid fileareas using Moodle generators.
     *
     * @covers ::local_archiving_pluginfile
     * @dataProvider pluginfile_serving_data_provider
     *
     * @param filearea $filearea The filearea to test.
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_pluginfile_serving(filearea $filearea): void {
        // Prepare test data.
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $this->setUser($user);

        // Enrol user into course.
        $generator->enrol_user($user->id, $course->id);

        // Create module and get proper cm object.
        $module = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('page', $module->cmid);
        $context = \context_module::instance($cm->id);

        // Test that a user without view permissions is rejected.
        $this->assertFalse(local_archiving_pluginfile(
            course: $course,
            cm: $cm,
            context: $context,
            filearea: $filearea->value,
            args: [],
            forcedownload: false
        ), 'User without capability should not be able to access files');

        // Assign capability to user.
        $roleid = $generator->create_role();
        assign_capability('local/archiving:view', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);

        // Test that a user with view permissions is granted access.
        $this->assertSame('PHPUNIT_TEST', local_archiving_pluginfile(
            course: $course,
            cm: $cm,
            context: $context,
            filearea: $filearea->value,
            args: [],
            forcedownload: false
        ), 'User with capability should be able to access files');
    }

    /**
     * Data provider for test_pluginfile_serving.
     *
     * @return array[] List of valid fileareas.
     */
    public static function pluginfile_serving_data_provider(): array {
        return [
            'Filestore Cache' => [filearea::FILESTORE_CACHE],
            'TSP' => [filearea::TSP],
        ];
    }

    /**
     * Tests that the pluginfile serving function declines invalid fileareas.
     *
     * @covers ::local_archiving_pluginfile
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function test_pluginfile_serving_invalid_type(): void {
        $this->assertFalse(local_archiving_pluginfile(
            course: new \stdClass(),
            cm: new \stdClass(),
            context: new \stdClass(),
            filearea: 'invalidfileares',
            args: [],
            forcedownload: false
        ), 'Invalid filearea should return false');
    }
}
