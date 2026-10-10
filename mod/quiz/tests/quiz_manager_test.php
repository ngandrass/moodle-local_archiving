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
 * Tests for the quiz_manager class
 *
 * @package   archivingmod_quiz
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;

use archivingmod_quiz\local\type\attempts_filter;

// phpcs:ignore
global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Tests for the quiz_manager class
 */
final class quiz_manager_test extends \advanced_testcase {
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
     * Checks if question type JACK is installed and skips test if not.
     *
     * @return void
     */
    protected function require_qtype_jack(): void {
        if (!\core_component::get_plugin_directory('qtype', 'jack')) {
            $this->markTestSkipped('qtype_jack is not installed.');
        }
    }

    /**
     * Tests creating a new quiz manager instance from an existing Moodle context.
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_creation(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['default']);

        $quiz = quiz_manager::from_context(\context_module::instance($rc->cm->id));
        $this->assertSame($rc->quiz->id, $quiz->get_quiz()->id, 'Quiz ID does not match');
        $this->assertSame($rc->course->id, $quiz->get_course()->id, 'Course ID does not match');
        $this->assertSame($rc->cm->id, $quiz->get_cm()->id, 'Course module ID does not match');
    }

    /**
     * Tests to get all the attempts of a quiz
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_attempts(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['default']);

        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);
        $attempts = $quiz->get_attempts();

        $this->assertNotEmpty($attempts, 'No attempts found');
        $this->assertCount(count($rc->attemptids), $attempts, 'Incorrect number of attempts found');
    }

    /**
     * Tests to get filtered attempts of a quiz
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_attempts_filtered(): void {

        // NOTE: Because there is currently only one filter available
        // NOTE: the combination of different filter results can not be properly
        // NOTE: tested, without adding mock filters to business logic code.
        // NOTE: Therefore provided combination logic was tested manually.
        // TODO (MDL-0): Expand test suite to feature filter combinations when
        // TODO (MDL-0): adding new filter options.

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['multiattempt']);

        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);
        $attempts = $quiz->get_attempts();
        $filteredattempts = $quiz->get_attempts([attempts_filter::LATEST->value]);

        $this->assertNotEmpty($attempts, 'No attempts found');
        $this->assertNotEmpty($filteredattempts, 'No attempts found for filter');
        $this->assertTrue(count($attempts) > count($filteredattempts), 'Filtering should reduce number of attempts');
    }

    /**
     * Tests to list attempts of a quiz filtered by a refids list.
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_attempts_by_refids(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['multiattempt']);
        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);

        // Restrict to a subset of attempts, including IDs that do not belong to this quiz.
        $allattemptids = array_column($quiz->get_attempts(), 'attemptid');
        $this->assertGreaterThan(1, count($allattemptids), 'Fixture must contain multiple attempts');
        $targetid = reset($allattemptids);

        $attempts = $quiz->get_attempts(refids: [$targetid, -1, -2]);
        $this->assertCount(1, $attempts, 'Only the targeted attempt should be returned');
        $this->assertEquals($targetid, $attempts[0]->attemptid, 'Targeted attempt ID does not match');

        // Refids that resolve to no attempt yield an empty result.
        $this->assertEmpty($quiz->get_attempts(refids: [-1, -2]), 'Invalid refids should not resolve to any attempt');

        // An empty list of refids is invalid.
        $this->expectException(\coding_exception::class);
        $quiz->get_attempts(refids: []);
    }

    /**
     * Tests that targeted attempt IDs and attempt filters are combined using an and-operator
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_attempts_by_refids_and_filters(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['multiattempt']);
        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);

        // Determine a latest and a non-latest attempt.
        $latestids = array_column($quiz->get_attempts([attempts_filter::LATEST->value]), 'attemptid');
        $nonlatestids = array_values(array_diff(array_column($quiz->get_attempts(), 'attemptid'), $latestids));
        $this->assertNotEmpty($nonlatestids, 'Fixture must contain non-latest attempts');

        // Targeted latest attempt must be kept.
        $attempts = $quiz->get_attempts([attempts_filter::LATEST->value], [$latestids[0]]);
        $this->assertCount(1, $attempts, 'Targeted latest attempt should be kept');
        $this->assertEquals($latestids[0], $attempts[0]->attemptid, 'Targeted latest attempt ID does not match');

        // Targeted non-latest attempt must be dropped by the filter.
        $this->assertEmpty(
            $quiz->get_attempts([attempts_filter::LATEST->value], [$nonlatestids[0]]),
            'Targeted non-latest attempt should be dropped by the LATEST filter'
        );

        // Mixed targets only keep the latest one.
        $attempts = $quiz->get_attempts([attempts_filter::LATEST->value], [$latestids[0], $nonlatestids[0]]);
        $this->assertEquals([$latestids[0]], array_column($attempts, 'attemptid'), 'Only the latest attempt should be kept');
    }

    /**
     * Tests to retrieve the latest attempt's id of each user
     *
     * @covers \archivingmod_quiz\quiz_manager::get_latest_attempt_of_each_user
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_latest_attempt_of_each_user(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['multiattempt']);
        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);

        // Retrieve latest attempts and assert that we have the expected number of attempts.
        $latestattempts = $quiz->get_latest_attempt_of_each_user();
        $this->assertNotEmpty($latestattempts, 'No latest attempts found for users');
        $this->assertCount(2, $latestattempts, 'Expected 2 of 3 attempts from 2 users here');

        // Assert that actually the latest attempt is retrieved.
        $latestattemptbyuserid = array_reduce($latestattempts, function ($carry, $attempt) {
            $carry[$attempt->userid] = $attempt->attemptid;
            return $carry;
        }, []);

        foreach ($rc->userids as $userid) {
            $userattempts = $DB->get_records('quiz_attempts', ['userid' => $userid], 'attempt DESC', 'id, userid');
            $this->assertSame(
                reset($userattempts)->id,
                $latestattemptbyuserid[$userid],
                'Latest attempt for user ' . $userid . ' does not match expected latest attempt'
            );
        }
    }

    /**
     * Tests to get the attempt metadata array for a quiz
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_attempts_metadata(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['default']);
        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);

        // Test without filters.
        $attempts = $quiz->get_attempts_metadata();
        $this->assertNotEmpty($attempts, 'No attempts found without filters set');
        $this->assertCount(count($rc->attemptids), $attempts, 'Incorrect number of attempts found without filters set');

        $attempt = array_shift($attempts);
        $this->assertNotEmpty($attempt->attemptid, 'Attempt metadata does not contain attemptid');
        $this->assertNotEmpty($attempt->userid, 'Attempt metadata does not contain userid');
        $this->assertNotEmpty($attempt->attempt, 'Attempt metadata does not contain attempt');
        $this->assertNotEmpty($attempt->state, 'Attempt metadata does not contain state');
        $this->assertNotEmpty($attempt->timestart, 'Attempt metadata does not contain timestart');
        $this->assertNotEmpty($attempt->timefinish, 'Attempt metadata does not contain timefinish');
        $this->assertNotEmpty($attempt->username, 'Attempt metadata does not contain username');
        $this->assertNotEmpty($attempt->firstname, 'Attempt metadata does not contain firstname');
        $this->assertNotEmpty($attempt->lastname, 'Attempt metadata does not contain lastname');
        $this->assertNotEmpty($attempt->email, 'Attempt metadata does not contain email');
        $this->assertNotNull($attempt->idnumber, 'Attempt metadata does not contain idnumber');  // ID number can be empty.

        // Test filtered.
        $attemptsfilteredexisting = $quiz->get_attempts_metadata($rc->attemptids);
        $this->assertNotEmpty($attemptsfilteredexisting, 'No attempts found with existing attempt ids');
        $this->assertCount(
            count($rc->attemptids),
            $attemptsfilteredexisting,
            'Incorrect number of attempts found with existing attempt ids'
        );

        $attemptsfilterednonexisting = $quiz->get_attempts_metadata([-1, -2, -3]);
        $this->assertEmpty($attemptsfilterednonexisting, 'Attempts found for non-existing attempt ids');
    }

    /**
     * Tests to retrieve existing and nonexisting attempts
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_attempt_exists(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['default']);

        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);

        $this->assertTrue($quiz->attempt_exists($rc->attemptids[0]), 'Existing attempt not found');
        $this->assertFalse($quiz->attempt_exists(-1), 'Non-existing attempt found');
    }

    /**
     * Tests to get the attachments of an attempt
     *
     * @covers \archivingmod_quiz\quiz_manager
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_attempt_attachments(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['default']);

        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);
        $attachments = $quiz->get_attempt_attachments($rc->attemptids[0]);
        $this->assertNotEmpty($attachments, 'No attachments found');

        // Find cake.md attachment.
        $this->assertNotEmpty(
            array_filter(
                $attachments,
                fn($a) => $a['file']->get_filename() === 'cake.md'
            ),
            'cake.md attachment not found'
        );

        // Check attachments metadata.
        $attachmentsmetadata = $quiz->get_attempt_attachments_metadata($rc->attemptids[0]);
        $this->assertNotEmpty($attachmentsmetadata, 'No attachments metadata found');
        $attachmentmetadata = array_shift($attachmentsmetadata);
        $this->assertNotEmpty($attachmentmetadata->slot, 'Attachment metadata does not contain slot');
        $this->assertEquals(
            'cake.md',
            $attachmentmetadata->filename,
            'Attachment metadata filename is incorrect'
        );
        $this->assertGreaterThan(0, $attachmentmetadata->filesize, 'Attachment metadata filesize is incorrect');
        $this->assertNotEmpty($attachmentmetadata->mimetype, 'Attachment metadata does not contain mimetype');
        $this->assertNotEmpty($attachmentmetadata->contenthash, 'Attachment metadata does not contain contenthash');
        $this->assertNotEmpty($attachmentmetadata->downloadurl, 'Attachment metadata does not contain downloadurl');
    }

    /**
     * Tests to get qType JACK specific code template files as attachments.
     *
     * @covers \quiz_archiver\Report::get_attempt_attachments
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function test_get_qtype_jack_code_templates_as_attachments(): void {
        $this->require_qtype_jack();

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $rc = $generator->import_reference_course(...$generator::QUIZ_FIXTURES['qtype_jack']);
        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);
        $attachments = $quiz->get_attempt_attachments($rc->attemptids[0]);
        $this->assertNotEmpty($attachments, 'No attachments found');

        // Find code template `CodeTemplateClass.java` attachment.
        $this->assertNotEmpty(
            array_filter(
                $attachments,
                fn($a) => $a['file']->get_filename() === 'CodeTemplateClass.java'
            ),
            'CodeTemplateClass.java code template not found'
        );
    }
}
