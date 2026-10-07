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

namespace archivingtrigger_event;

use local_archiving\archive_job;
use local_archiving\local\type\archive_job_status;

/**
 * Tests for the archivingtrigger_event implementation.
 *
 * @package   archivingtrigger_event
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the archivingtrigger_event implementation.
 */
final class archivingtrigger_test extends \advanced_testcase {
    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $PAGE->set_url('/');
    }

    /**
     * Creates a quiz and enables the course_module_viewed event of the quiz
     * archiving driver.
     *
     * We are using a very simple event so that we can easily test the trigger
     * without the need to create a lot of stuff and handle potential side
     * effects of other event consumers.
     *
     * @return \stdClass Object with the created quiz and its context
     */
    private function prepare_quiz_and_event_config(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Event names are stored with a leading backslash, as provided by plugin_util.
        set_config('sensitivity_quiz', '\\' . \mod_quiz\event\course_module_viewed::class, 'archivingtrigger_event');

        return (object) [
            'quiz' => $quiz,
            'context' => \context_module::instance($quiz->cmid),
        ];
    }

    /**
     * Just a plain test to ensure nothing breaks when instantiating this trigger.
     *
     * @covers \archivingtrigger_event\archivingtrigger
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_instantiation(): void {
        $trigger = new archivingtrigger();
        $this->assertSame('archivingtrigger', $trigger->get_plugin_type());
        $this->assertSame('event', $trigger->get_plugin_name());
    }

    /**
     * Tests that events the trigger is not sensitive to do not create jobs.
     *
     * @covers \archivingtrigger_event\archivingtrigger
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_handle_event_ignores_unconfigured_events(): void {
        global $DB;
        $this->resetAfterTest();
        $data = $this->prepare_quiz_and_event_config();
        set_config('sensitivity_quiz', '', 'archivingtrigger_event');

        archivingtrigger::handle_event(\mod_quiz\event\course_module_viewed::create([
            'objectid' => $data->quiz->id,
            'context' => $data->context,
        ]));

        $this->assertSame(
            0,
            $DB->count_records('local_archiving_job', ['contextid' => $data->context->id]),
            'No job should be created for unconfigured events'
        );
    }

    /**
     * Tests that a configured event creates a job and that no identical job is
     * created while a previous one is still pending.
     *
     * @covers \archivingtrigger_event\archivingtrigger
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_handle_event_skips_pending_identical_job(): void {
        global $DB;

        $this->resetAfterTest();
        $data = $this->prepare_quiz_and_event_config();
        $event = \mod_quiz\event\course_module_viewed::create([
            'objectid' => $data->quiz->id,
            'context' => $data->context,
        ]);

        // First event creates a job.
        archivingtrigger::handle_event($event);
        $jobs = $DB->get_records('local_archiving_job', ['contextid' => $data->context->id]);
        $this->assertCount(1, $jobs, 'First event should create a job');
        $job = archive_job::get_by_id(reset($jobs)->id);
        $this->assertSame(archive_job_status::QUEUED, $job->get_status());

        // Further events must not create a duplicate while the first job is pending.
        archivingtrigger::handle_event($event);
        $this->assertSame(
            1,
            $DB->count_records('local_archiving_job', ['contextid' => $data->context->id]),
            "No duplicate job should be created"
        );
    }

    /**
     * Tests that a new identical job is created once the previous one reached
     * a final state.
     *
     * @covers \archivingtrigger_event\archivingtrigger
     * @dataProvider final_status_data_provider
     *
     * @param archive_job_status $status Final status of the previous job
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \JsonException
     */
    public function test_handle_event_recreates_job_after_final_state(archive_job_status $status): void {
        global $DB;

        $this->resetAfterTest();
        $data = $this->prepare_quiz_and_event_config();
        $event = \mod_quiz\event\course_module_viewed::create([
            'objectid' => $data->quiz->id,
            'context' => $data->context,
        ]);

        archivingtrigger::handle_event($event);
        $jobs = $DB->get_records('local_archiving_job', ['contextid' => $data->context->id]);
        $this->assertCount(1, $jobs, 'First event should create a job');

        // Finish previous job and trigger again.
        $previousjob = archive_job::get_by_id(reset($jobs)->id);
        $previousjob->set_status($status);
        archivingtrigger::handle_event($event);

        $jobs = $DB->get_records('local_archiving_job', ['contextid' => $data->context->id], 'id ASC');
        $this->assertCount(2, $jobs, 'A new job should be created after the previous one reached a final state');
        $this->assertTrue(
            $previousjob->get_fingerprint()->equals(archive_job::get_by_id(end($jobs)->id)->get_fingerprint()),
            'Both jobs should have the same fingerprint'
        );
    }

    /**
     * Data provider for test_handle_event_recreates_job_after_final_state.
     *
     * @return array List of final job states
     */
    public static function final_status_data_provider(): array {
        $res = [];
        foreach (archive_job_status::get_final_states() as $status) {
            $res[$status->name] = [$status];
        }

        return $res;
    }

    /**
     * Tests that created jobs are limited to the object referenced by the event
     * and that jobs are only deduplicated for identical objects.
     *
     * @covers \archivingtrigger_event\archivingtrigger
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_handle_event_scopes_job_to_event_object(): void {
        global $DB;
        $this->resetAfterTest();

        // Import reference quiz with multiple attempts and enable the attempt_submitted event.
        $generator = $this->getDataGenerator()->get_plugin_generator('archivingmod_quiz');
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['multiattempt']);
        $context = \context_module::instance($rc->cm->id);
        set_config('sensitivity_quiz', '\\' . \mod_quiz\event\attempt_submitted::class, 'archivingtrigger_event');
        $this->assertGreaterThan(1, count($rc->attemptids), 'Fixture must contain multiple attempts');

        $event1 = \mod_quiz\event\attempt_submitted::create([
            'objectid' => $rc->attemptids[0],
            'relateduserid' => $rc->userids[0],
            'context' => $context,
            'other' => ['submitterid' => null, 'quizid' => $rc->quiz->id],
        ]);
        $event2 = \mod_quiz\event\attempt_submitted::create([
            'objectid' => $rc->attemptids[1],
            'relateduserid' => $rc->userids[1],
            'context' => $context,
            'other' => ['submitterid' => null, 'quizid' => $rc->quiz->id],
        ]);

        // First event creates a job scoped to the submitted attempt.
        archivingtrigger::handle_event($event1);
        $jobs = $DB->get_records('local_archiving_job', ['contextid' => $context->id], 'id ASC');
        $this->assertCount(1, $jobs, 'First event should create a job');
        $this->assertSame([$rc->attemptids[0]], archive_job::get_by_id(reset($jobs)->id)->get_refids());

        // Same attempt again must not create a duplicate while the first job is pending.
        archivingtrigger::handle_event($event1);
        $this->assertSame(
            1,
            $DB->count_records('local_archiving_job', ['contextid' => $context->id]),
            'No duplicate job should be created for the same attempt'
        );

        // Another attempt creates a separate job.
        archivingtrigger::handle_event($event2);
        $jobs = $DB->get_records('local_archiving_job', ['contextid' => $context->id], 'id ASC');
        $this->assertCount(2, $jobs, 'A different attempt should create a separate job');
        $this->assertSame([$rc->attemptids[1]], archive_job::get_by_id(end($jobs)->id)->get_refids());
    }

    /**
     * Tests that no job is created if the event references an object that can
     * not be archived.
     *
     * @covers \archivingtrigger_event\archivingtrigger
     *
     * @return void
     * @throws \JsonException
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_handle_event_skips_non_archivable_object(): void {
        global $DB;
        $this->resetAfterTest();

        // Import reference quiz with multiple attempts and enable the attempt_submitted event.
        $generator = $this->getDataGenerator()->get_plugin_generator('archivingmod_quiz');
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['multiattempt']);
        $context = \context_module::instance($rc->cm->id);
        set_config('sensitivity_quiz', '\\' . \mod_quiz\event\attempt_submitted::class, 'archivingtrigger_event');

        // Non-existing attempt.
        archivingtrigger::handle_event(\mod_quiz\event\attempt_submitted::create([
            'objectid' => -1,
            'relateduserid' => $rc->userids[0],
            'context' => $context,
            'other' => ['submitterid' => null, 'quizid' => $rc->quiz->id],
        ]));

        // Preview attempt.
        $DB->set_field('quiz_attempts', 'preview', 1, ['id' => $rc->attemptids[0]]);
        archivingtrigger::handle_event(\mod_quiz\event\attempt_submitted::create([
            'objectid' => $rc->attemptids[0],
            'relateduserid' => $rc->userids[0],
            'context' => $context,
            'other' => ['submitterid' => null, 'quizid' => $rc->quiz->id],
        ]));

        $this->assertSame(
            0,
            $DB->count_records('local_archiving_job', ['contextid' => $context->id]),
            'No job should be created for non-archivable objects'
        );
    }
}
