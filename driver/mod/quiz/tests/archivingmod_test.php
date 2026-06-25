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
 * Tests for the archivingmod class
 *
 * @package   archivingmod_quiz
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;


// phpcs:ignore
use local_archiving\activity_archiving_task;
use local_archiving\exception\yield_exception;
use local_archiving\type\activity_archiving_task_status;

/**
 * Tests for the archivingmod class
 */
final class archivingmod_test extends \advanced_testcase {
    /**
     * Returns the data generator for the archivingmod_quiz plugin
     *
     * @return \archivingmod_quiz_generator The data generator for the archivingmod_quiz plugin
     */
    // phpcs:ignore
    public static function getDataGenerator(): \archivingmod_quiz_generator {
        return parent::getDataGenerator()->get_plugin_generator('archivingmod_quiz');
    }

    /**
     * Tests that the quiz activity archiving driver reports its readiness correctly.
     *
     * @covers \archivingmod_quiz\archivingmod
     *
     * @return void
     */
    public function test_is_ready(): void {
        $this->resetAfterTest();

        // Prepare a fully unconfigured plugin.
        set_config('worker_url', '', 'archivingmod_quiz');
        set_config('enablewebservices', false);
        set_config('webserviceprotocols', '');

        // Ensure that the plugin is considered not ready.
        $this->assertFalse(archivingmod::is_ready(), 'Plugin should not be ready when fully unconfigured.');

        // Set worker URL only.
        set_config('worker_url', 'https://example.com/worker', 'archivingmod_quiz');
        $this->assertFalse(archivingmod::is_ready(), 'Plugin should not be ready when only worker URL is set.');

        // Enable web services.
        set_config('enablewebservices', true);
        $this->assertFalse(archivingmod::is_ready(), 'Plugin should not be ready when web services are enabled but no protocol.');

        // Enable another web service protocol.
        set_config('webserviceprotocols', 'soap');
        $this->assertFalse(archivingmod::is_ready(), 'Plugin should not be ready when SOAP protocol is enabled but REST is not.');

        // Enable REST protocol.
        set_config('webserviceprotocols', 'soap,rest');
        $this->assertTrue(archivingmod::is_ready(), 'Plugin should be ready now.');
    }

    /**
     * Tests that supported activities are reported correctly.
     *
     * @covers \archivingmod_quiz\archivingmod
     *
     * @return void
     */
    public function test_get_supported_activities(): void {
        $this->assertEquals(['quiz'], archivingmod::get_supported_activities());
    }

    /**
     * Tests that the activity archiving driver identifies archivable quizzes correctly.
     *
     * @covers \archivingmod_quiz\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_can_be_archived(): void {
        $this->resetAfterTest();

        // Test that a quiz with a question and an attempt is considered archivable.
        $mocks = $this->getDataGenerator()->create_mock_quiz(createquestion: true, createattempt: true);
        $driver = new archivingmod($mocks->context);
        $this->assertTrue($driver->can_be_archived(), 'Quiz with questions and attempts should be archivable.');

        // Test that a quiz without an attempt is not considered archivable.
        $mocks = $this->getDataGenerator()->create_mock_quiz(createquestion: true, createattempt: false);
        $driver = new archivingmod($mocks->context);
        $this->assertFalse($driver->can_be_archived(), 'Quiz without attempts should not be archivable.');

        // Test that a quiz without a question is not considered archivable.
        $mocks = $this->getDataGenerator()->create_mock_quiz(createquestion: false, createattempt: false);
        $driver = new archivingmod($mocks->context);
        $this->assertFalse($driver->can_be_archived(), 'Quiz without questions should not be archivable.');
    }

    /**
     * Tests the task execution flow.
     *
     * @covers \archivingmod_quiz\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws yield_exception
     */
    public function test_execute(): void {
        // Prepare a mock task.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task();
        $driver = new archivingmod($mocks->context);

        /** @var activity_archiving_task $task */
        $task = $mocks->task;
        $task->set_status(activity_archiving_task_status::UNINITIALIZED);

        // First execution should result in AWAITING_PROCESSING status after yielding.
        try {
            $driver->execute_task($task);
            $this->fail('Expected task to yield for processing.');
        } catch (yield_exception) {
            $this->assertEquals(
                activity_archiving_task_status::AWAITING_PROCESSING,
                $task->get_status(),
                'Task should be in AWAITING_PROCESSING status after yielding.'
            );
        }

        // Another execution should result in a yield again since the worker handles the rest.
        try {
            $driver->execute_task($task);
            $this->fail('Expected task to yield for processing again.');
        } catch (yield_exception) {
            $this->assertEquals(
                activity_archiving_task_status::AWAITING_PROCESSING,
                $task->get_status(),
                'Task should remain in its state.'
            );
        }

        // Running tasks are processed in the worker...
        $task->set_status(activity_archiving_task_status::RUNNING);
        try {
            $driver->execute_task($task);
            $this->fail('Expected task to yield for processing while running.');
        } catch (yield_exception) {
            $this->assertEquals(
                activity_archiving_task_status::RUNNING,
                $task->get_status(),
                'Task should remain in its state.'
            );
        }

        // Finalizing task should also yield.
        $task->set_status(activity_archiving_task_status::FINALIZING);
        try {
            $driver->execute_task($task);
            $this->fail('Expected task to yield for processing while finalizing.');
        } catch (yield_exception) {
            $this->assertEquals(
                activity_archiving_task_status::FINALIZING,
                $task->get_status(),
                'Task should remain in its state.'
            );
        }

        // Finished task should just return.
        $task->set_status(activity_archiving_task_status::FINISHED);
        $driver->execute_task($task);
    }

    /**
     * Tests that task content metadata is generated correctly.
     *
     * @covers \archivingmod_quiz\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_task_content_metadata(): void {
        // Create a mock task.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task();
        $driver = new archivingmod($mocks->context);

        // Get and verify the task content metadata.
        $metadata = $driver->get_task_content_metadata($mocks->task);
        $this->assertCount(1, $metadata, 'There should be one metadata entry for the task.');
        $this->assertEquals($mocks->task->get_id(), $metadata[0]->taskid, 'Metadata task ID should match the task ID.');
        $this->assertEquals($mocks->attempts[0]->attemptid, $metadata[0]->refid, 'Metadata refid should match the attempt ID.');
        $this->assertEquals($mocks->attempts[0]->userid, $metadata[0]->userid, 'Metadata user ID should match the user ID.');
        $this->assertEquals('quiz_attempts', $metadata[0]->reftable, 'Metadata reftable should be "quiz_attempts".');
    }

    /**
     * Tests that fingerprinting works correctly.
     *
     * @covers \archivingmod_quiz\archivingmod
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_fingerprint(): void {
        global $DB;

        // Prepare a mock quiz.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_quiz(createquestion: true, createattempt: true);
        $driver = new archivingmod($mocks->context);

        // Get initial fingerprint.
        $fingerprint1 = $driver->fingerprint();
        $this->assertEquals($fingerprint1, $driver->fingerprint(), 'Fingerprint should be stable if nothing changes.');

        // Modify the quiz and assert that the fingerprint changed.
        $DB->set_field('quiz', 'timemodified', time() + 1, ['id' => $mocks->quiz->id]);
        $fingerprint2 = $driver->fingerprint();
        $this->assertNotEquals($fingerprint1, $fingerprint2, 'Fingerprint should change when quiz is modified.');
        $this->assertEquals($fingerprint2, $driver->fingerprint(), 'Fingerprint should be stable if nothing changes.');

        // Modify an attempt and assert that the fingerprint changed.
        $DB->set_field('quiz_attempts', 'timemodified', time() + 1, ['quiz' => $mocks->quiz->id]);
        $fingerprint3 = $driver->fingerprint();
        $this->assertNotEquals($fingerprint2, $fingerprint3, 'Fingerprint should change when an attempt is modified.');
        $this->assertNotEquals($fingerprint1, $fingerprint3, 'Fingerprint should change when an attempt is modified.');
        $this->assertEquals($fingerprint3, $driver->fingerprint(), 'Fingerprint should be stable if nothing changes.');
    }
}
