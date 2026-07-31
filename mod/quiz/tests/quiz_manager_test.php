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
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;


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
        $rc = $this->getDataGenerator()->import_reference_course();

        $quiz = quiz_manager::from_context(\context_module::instance($rc->cm->id));
        $this->assertSame($rc->quiz->id, $quiz->get_quiz()->id, 'Quiz ID does not match');
        $this->assertSame($rc->course->id, $quiz->get_course()->id, 'Course ID does not match');
        $this->assertSame($rc->cm->id, $quiz->get_cm()->id, 'Course module ID does not match');
    }

    /**
     * Tests to get the attempts of a quiz
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
        $rc = $this->getDataGenerator()->import_reference_course();

        $quiz = new quiz_manager($rc->course->id, $rc->cm->id);
        $attempts = $quiz->get_attempts();

        $this->assertNotEmpty($attempts, 'No attempts found');
        $this->assertCount(count($rc->attemptids), $attempts, 'Incorrect number of attempts found');
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
        $rc = $this->getDataGenerator()->import_reference_course();
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
        $rc = $this->getDataGenerator()->import_reference_course();
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
        $rc = $this->getDataGenerator()->import_reference_course();
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
}
