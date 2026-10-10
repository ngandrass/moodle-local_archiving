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
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;


use local_archiving\activity_archiving_task;
use local_archiving\archive_job;
use local_archiving\local\exception\yield_exception;
use local_archiving\local\type\activity_archiving_task_status;

/**
 * Tests for the archivingmod class
 */
final class archivingmod_test extends \advanced_testcase {
    /**
     * Returns the data generator for the archivingmod_assign plugin
     *
     * @return \archivingmod_assign_generator The data generator for the archivingmod_assign plugin
     */
    // phpcs:ignore
    public static function getDataGenerator(): \archivingmod_assign_generator {
        return parent::getDataGenerator()->get_plugin_generator('archivingmod_assign');
    }

    /**
     * Tests that the assignment activity archiving driver reports its readiness correctly.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     */
    public function test_is_ready(): void {
        $this->resetAfterTest();

        // Prepare a fully unconfigured plugin.
        set_config('worker_url', '', 'archivingmod_assign');
        set_config('enablewebservices', false);
        set_config('webserviceprotocols', '');

        // Ensure that the plugin is considered not ready.
        $this->assertFalse(archivingmod::is_ready(), 'Plugin should not be ready when fully unconfigured.');

        // Set worker URL only.
        set_config('worker_url', 'https://example.com/worker', 'archivingmod_assign');
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
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     */
    public function test_get_supported_activities(): void {
        $this->assertEquals(['assign'], archivingmod::get_supported_activities());
    }

    /**
     * Tests that the activity archiving driver identifies archivable assignments correctly.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_can_be_archived(): void {
        $this->resetAfterTest();

        // Test that an assignment without any submission is not considered archivable.
        $mocks = $this->getDataGenerator()->create_assignment();
        $context = \context_module::instance($mocks->cm->id);
        $driver = new archivingmod($context);
        $this->assertFalse($driver->can_be_archived(), 'Assignment without submissions should not be archivable.');

        // Test that an assignment with a submitted submission is considered archivable.
        $mocks = $this->getDataGenerator()->create_assignment_with_submission(['onlinetext' => 'Test submission text.']);
        $context = \context_module::instance($mocks->cm->id);
        $driver = new archivingmod($context);
        $this->assertTrue($driver->can_be_archived(), 'Assignment with a submitted submission should be archivable.');
    }

    /**
     * Tests the task execution flow.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws yield_exception
     */
    public function test_execute(): void {
        // Prepare a mock task.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task(populateassignment: true);
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
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_task_content_metadata(): void {
        // Create a mock task.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task(populateassignment: true);
        $driver = new archivingmod($mocks->context);

        // Get and verify the task content metadata.
        $metadata = $driver->get_task_content_metadata($mocks->task);
        $this->assertCount(1, $metadata, 'There should be one metadata entry for the task.');
        $this->assertEquals($mocks->task->get_id(), $metadata[0]->taskid, 'Metadata task ID should match the task ID.');
        $this->assertEquals($mocks->submission->id, $metadata[0]->refid, 'Metadata refid should match the submission ID.');
        $this->assertEquals($mocks->student->id, $metadata[0]->userid, 'Metadata user ID should match the user ID.');
        $this->assertEquals('assign_submission', $metadata[0]->reftable, 'Metadata reftable should be "assign_submission".');
    }

    /**
     * Tests that task execution fails if the assignment has no submissions.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_fails_without_submissions(): void {
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task(populateassignment: false);

        $this->expectException(\RuntimeException::class);
        (new archivingmod($mocks->context))->execute_task($mocks->task);
    }

    /**
     * Tests that task content metadata only contains the submissions explicitly
     * targeted by the job (refids).
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_task_content_metadata_respects_refids(): void {
        $this->resetAfterTest();
        $testdata = $this->getDataGenerator()->create_assignment_with_text_submission();
        $context = \context_module::instance($testdata->cm->id);

        // Target the existing submission as well as a submission ID that does not exist.
        $job = archive_job::create($context, get_admin()->id, 'manual', new \stdClass(), refids: [$testdata->submission->id, -1]);
        $task = activity_archiving_task::create(
            $job->get_id(),
            $context,
            \local_archiving\local\type\cm_state_fingerprint::from_raw_value(str_repeat('0', 64)),
            get_admin()->id,
            'assign'
        );
        $metadata = (new archivingmod($context))->get_task_content_metadata($task);

        $this->assertCount(1, $metadata, 'Only targeted submissions should be part of the content metadata');
        $this->assertEquals(
            $testdata->submission->id,
            $metadata[0]->refid,
            'Content metadata should reference the targeted submission'
        );
    }

    /**
     * Tests that task execution only considers the submissions explicitly targeted
     * by the job (refids) and fails if no targeted submission is left.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_execute_respects_refids(): void {
        $this->resetAfterTest();
        $testdata = $this->getDataGenerator()->create_assignment_with_text_submission();
        $context = \context_module::instance($testdata->cm->id);
        $driver = new archivingmod($context);

        // Target the existing submission.
        $job = archive_job::create($context, get_admin()->id, 'manual', new \stdClass(), refids: [$testdata->submission->id]);
        $task = activity_archiving_task::create(
            $job->get_id(),
            $context,
            \local_archiving\local\type\cm_state_fingerprint::from_raw_value(str_repeat('0', 64)),
            get_admin()->id,
            'assign'
        );
        try {
            $driver->execute_task($task);
            $this->fail('Expected task to yield for processing.');
        } catch (yield_exception) {
            $this->assertEquals(
                1,
                $task->get_job()->get_metadata_entry('num_attempts'),
                'Only the targeted submission should be counted'
            );
        }

        // Target only submissions that do not exist.
        $job = archive_job::create($context, get_admin()->id, 'manual', new \stdClass(), refids: [-1, -2]);
        $task = activity_archiving_task::create(
            $job->get_id(),
            $context,
            \local_archiving\local\type\cm_state_fingerprint::from_raw_value(str_repeat('0', 64)),
            get_admin()->id,
            'assign'
        );
        $this->expectException(\RuntimeException::class);
        $driver->execute_task($task);
    }

    /**
     * Tests that fingerprinting works correctly.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_fingerprint(): void {
        global $DB;

        // Prepare a mock assignment.
        $this->resetAfterTest();
        $mocks = $this->getDataGenerator()->create_mock_task(populateassignment: true);
        $driver = new archivingmod($mocks->context);

        // Get initial fingerprint.
        $fingerprint1 = $driver->fingerprint();
        $this->assertEquals($fingerprint1, $driver->fingerprint(), 'Fingerprint should be stable if nothing changes.');

        // Modify the assignment and assert that the fingerprint changed.
        $DB->set_field('assign', 'timemodified', time() + 1, ['id' => $mocks->assignment->id]);
        $fingerprint2 = $driver->fingerprint();
        $this->assertNotEquals($fingerprint1, $fingerprint2, 'Fingerprint should change when assignment is modified.');
        $this->assertEquals($fingerprint2, $driver->fingerprint(), 'Fingerprint should be stable if nothing changes.');

        // Modify a submission and assert that the fingerprint changed.
        $DB->set_field('assign_submission', 'timemodified', time() + 1, ['assignment' => $mocks->assignment->id]);
        $fingerprint3 = $driver->fingerprint();
        $this->assertNotEquals($fingerprint2, $fingerprint3, 'Fingerprint should change when a submission is modified.');
        $this->assertNotEquals($fingerprint1, $fingerprint3, 'Fingerprint should change when a submission is modified.');
        $this->assertEquals($fingerprint3, $driver->fingerprint(), 'Fingerprint should be stable if nothing changes.');
    }

    /**
     * Tests that assessable_submitted events resolve to the submitted submission
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_refids_for_event_assessable_submitted(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $testdata = $generator->create_assignment();
        $driver = new archivingmod(\context_module::instance($testdata->cm->id));

        // Capture the assessable_submitted event fired by the assignment (submission drafts are disabled).
        $sink = $this->redirectEvents();
        /** @var \mod_assign_generator $assigngen */
        $assigngen = $generator->get_plugin_generator('mod_assign');
        $assigngen->create_submission([
            'cmid' => $testdata->cm->id,
            'userid' => $testdata->student->id,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'onlinetext' => 'Test submission text.',
        ]);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_assign\event\assessable_submitted);
        $sink->close();
        $this->assertNotEmpty($events, 'Expected an assessable_submitted event');
        $event = reset($events);
        $submissionid = (int) $event->objectid;

        // Should resolve to the submitted submission.
        $this->assertSame([$submissionid], $driver->get_refids_for_event($event), 'Submission of the assignment');

        // Submissions of other assignments are not targeted.
        $otherdata = $generator->create_assignment();
        $otherdriver = new archivingmod(\context_module::instance($otherdata->cm->id));
        $this->assertSame([], $otherdriver->get_refids_for_event($event), 'Submission of another assignment');

        // Submissions that are not submitted are not targeted.
        $DB->set_field('assign_submission', 'status', ASSIGN_SUBMISSION_STATUS_DRAFT, ['id' => $submissionid]);
        $this->assertSame([], $driver->get_refids_for_event($event), 'Draft submission');
    }

    /**
     * Tests that submission_graded events resolve to the graded submission.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_refids_for_event_submission_graded(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $testdata = $generator->create_assignment_with_text_submission();
        $context = \context_module::instance($testdata->cm->id);
        $assign = new \assign($context, $testdata->cm, $testdata->course);
        $driver = new archivingmod($context);

        // Graded submission.
        $grade = $generator->grade_submission($testdata);
        $event = \mod_assign\event\submission_graded::create_from_grade($assign, $grade);
        $this->assertSame([(int) $testdata->submission->id], $driver->get_refids_for_event($event));

        // Grade without any submission.
        $otheruser = $generator->create_and_enrol($testdata->course, 'student');
        $othergrade = $assign->get_user_grade($otheruser->id, true);
        $event = \mod_assign\event\submission_graded::create_from_grade($assign, $othergrade);
        $this->assertSame([], $driver->get_refids_for_event($event), 'Grade without submission');
    }

    /**
     * Tests that submission_locked events resolve to the latest submission of the locked user.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_refids_for_event_submission_locked(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $testdata = $generator->create_assignment_with_text_submission();
        $context = \context_module::instance($testdata->cm->id);
        $assign = new \assign($context, $testdata->cm, $testdata->course);
        $driver = new archivingmod($context);

        // User with submission.
        $event = \mod_assign\event\submission_locked::create_from_user($assign, $testdata->student);
        $this->assertSame([(int) $testdata->submission->id], $driver->get_refids_for_event($event), 'User with submission');

        // User without submission.
        $otheruser = $generator->create_and_enrol($testdata->course, 'student');
        $event = \mod_assign\event\submission_locked::create_from_user($assign, $otheruser);
        $this->assertSame([], $driver->get_refids_for_event($event), 'User without submission');
    }

    /**
     * Tests that events that do not reference submissions target the whole assignment.
     *
     * @covers \archivingmod_assign\archivingmod
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_get_refids_for_event_unrelated_event(): void {
        $this->resetAfterTest();
        $testdata = $this->getDataGenerator()->create_assignment_with_text_submission();
        $context = \context_module::instance($testdata->cm->id);

        $event = \mod_assign\event\course_module_viewed::create([
            'objectid' => $testdata->assignment->id,
            'context' => $context,
        ]);

        $this->assertNull((new archivingmod($context))->get_refids_for_event($event));
    }
}
