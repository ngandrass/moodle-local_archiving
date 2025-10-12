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

namespace local_archiving\driver;

/**
 * Tests for the driver factory.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the driver factory.
 */
final class factory_test extends \advanced_testcase {
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
     * Tests that the sub-plugin class resolver returns correct mocks during unit tests
     *
     * @covers \local_archiving\driver\factory
     * @dataProvider get_subplugin_class_mock_data_provider
     *
     * @param string $plugintype
     * @param string $pluginname
     * @return void
     * @throws \coding_exception
     */
    public function test_get_subplugin_class_mock(string $plugintype, string $pluginname): void {
        $this->assertEquals("\\{$plugintype}_{$pluginname}", factory::get_subplugin_class($plugintype, $pluginname));
    }

    /**
     * Data provider for test_get_subplugin_class_mock.
     *
     * @return array[] Frankenstyle names of sub-plugins to test.
     */
    public static function get_subplugin_class_mock_data_provider(): array {
        return [
            'archivingmod_quiz' => ['archivingmod', 'quiz_mock'],
            'archivingstore_moodle' => ['archivingstore', 'moodle_mock'],
            'archivingevent_course' => ['archivingevent', 'course_mock'],
            'archivingtrigger_manual' => ['archivingtrigger', 'manual_mock'],
        ];
    }

    /**
     * Tests creation of a mocked activity archiving driver instance.
     *
     * @covers \local_archiving\driver\factory
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_activity_archiving_driver_mock(): void {
        // Prepare course with a quiz module.
        $this->resetAfterTest();
        $course = $this->generator()->create_course();
        $cm = $this->generator()->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($cm->cmid);

        // Get a new mock driver instance.
        $instance = factory::activity_archiving_driver('quiz', $context);
        $this->assertInstanceOf(\archivingmod_quiz_mock::class, $instance, 'Expected mocked driver instance');
    }

    /**
     * Tests creation of a mocked storag driver instance.
     *
     * @covers \local_archiving\driver\factory
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_storage_driver_mock(): void {
        $instance = factory::storage_driver('localdir');
        $this->assertInstanceOf(\archivingstore_localdir_mock::class, $instance, 'Expected mocked storage driver instance');
    }

    /**
     * Tests creation of a mocked event connector instance.
     *
     * @covers \local_archiving\driver\factory
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_event_connector_mock(): void {
        $instance = factory::event_connector('stub');
        $this->assertInstanceOf(\archivingevent_stub_mock::class, $instance, 'Expected mocked event connector instance');
    }

    /**
     * Tests creation of a mocked archiving trigger instance.
     *
     * @covers \local_archiving\driver\factory
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_archiving_trigger_mock(): void {
        $instance = factory::archiving_trigger('manual');
        $this->assertInstanceOf(\archivingtrigger_manual_mock::class, $instance, 'Expected mocked archiving trigger instance');
    }
}
