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


use local_archiving\type\filearea;

/**
 * Tests for the unit test data generator for local_archiving
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for the unit test data generator for local_archiving
 */
final class generator_test extends \advanced_testcase {

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
     * Tests creating a test archive job for a freshly created course and course module.
     *
     * @covers \local_archiving_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_archive_job(): void {
        // Create test job.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job(['userid' => 1337]);

        // Check that the job was created with the correct data.
        $this->assertSame(1337, $job->get_userid(), 'The job was not created with the correct user ID');
        $context = $job->get_context();
        $this->assertNotNull($context, 'The job context should not be null');

        // Assert that course and module exist.
        $cmid = $context->instanceid;
        $course = $context->get_course_context()->instanceid;

        $coursemodinfo = get_fast_modinfo($course);
        $this->assertNotNull($coursemodinfo, 'The course referenced by the context should exist');
        $this->assertNotNull($coursemodinfo->get_cm($cmid), 'The course module referenced by the context should exist');

        // Assert that the job got some settings attached.
        $this->assertNotEmpty(get_object_vars($job->get_settings()), 'The job should have some settings attached');
    }

    /**
     * Tests creating an activity archiving task with a fresh archive job.
     *
     * @covers \local_archiving_generator
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_activity_archving_task(): void {
        // Create a new activity archiving task.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();

        // Check that the task was created with the correct data.
        $this->assertFalse($task->is_completed(), 'The task should not be completed upon creation');
        $this->assertNotNull($task->get_job(), 'The task should be associated with a job');
        $this->assertNotEmpty(get_object_vars($task->get_settings()), 'The task should have some settings attached');
        $this->assertNotEmpty($task->get_archivingmodname(), 'The task should have an archivingmod plugin set');
    }

    /**
     * Tests creating a file handle record and then creating an actual file handle from it.
     *
     * @covers \local_archiving_generator
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_file_handle(): void {
        // Prepare a file handle record.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();
        $filehandlerecord = $this->generator()->generate_file_handle_data([
            'jobid' => $job->get_id(),
            'filekey' => 'lorem-ipsum-42',
        ]);

        // Check the file handle record data.
        $filehandledatakeys = [
            'id',
            'jobid',
            'archivingstorename',
            'deleted',
            'filename',
            'filepath',
            'filesize',
            'sha256sum',
            'mimetype',
            'timecreated',
            'timemodified',
            'filekey',
        ];
        foreach ($filehandledatakeys as $key) {
            $this->assertNotNull($filehandlerecord->{$key}, "The file handle record should have a {$key} property");
        }

        $this->assertSame(
            $job->get_id(),
            $filehandlerecord->jobid,
            'The file handle record should be associated with the correct job'
        );
        $this->assertSame(
            'lorem-ipsum-42',
            $filehandlerecord->filekey,
            'The file handle record should have the correct file key'
        );

        // Create an actual file handle from the record data.
        $filehandle = $this->generator()->create_file_handle(get_object_vars($filehandlerecord));
        foreach (['jobid', 'archivingstorename', 'filename', 'filepath', 'filesize', 'sha256sum', 'mimetype', 'filekey'] as $key) {
            $this->assertSame(
                $filehandlerecord->{$key},
                $filehandle->{$key},
                "The file handle should have the correct {$key} property"
            );
        }
    }

    /**
     * Test creating a temporary file within the Moodle file store.
     *
     * @covers \local_archiving_generator
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_create_temp_file(): void {
        // Create a temporary file.
        $this->resetAfterTest();
        $file = $this->generator()->create_temp_file();

        // Check that the file was created with the correct data.
        $this->assertNotNull($file, 'The temporary file should not be null');
        $this->assertNotEmpty($file->get_contenthash(), 'The temporary file should have a content hash');
        $this->assertSame(
            filearea::TEMP->get_component(),
            $file->get_component(),
            'The temporary file should have the TEMP component'
        );
        $this->assertSame(filearea::TEMP->value,
            $file->get_filearea(),
            'The temporary file should be in the TEMP file area'
        );
    }

    /**
     * Teste creating a stub file within the filestore cache.
     *
     * @covers \local_archiving_generator
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_create_filestore_cache_file(): void {
        // Create a new filestore cache file.
        $this->resetAfterTest();
        $file = $this->generator()->create_filestore_cache_file(
            filehandleid: 42,
            timecreated: 1337,
            timemodified: 4242
        );

        // Check that the file was created with the correct data.
        $this->assertNotNull($file, 'The filestore cache file should not be null');
        $this->assertSame(
            filearea::FILESTORE_CACHE->get_component(),
            $file->get_component(),
            'The filestore cache file should have the FILESTORE_CACHE component'
        );
        $this->assertSame(
            filearea::FILESTORE_CACHE->value,
            $file->get_filearea(),
            'The filestore cache file should be in the FILESTORE_CACHE file area'
        );
        $this->assertSame(
            42,
            $file->get_itemid(),
            'The filestore cache file should have the correct item ID (file handle ID)'
        );
        $this->assertSame(
            1337,
            $file->get_timecreated(),
            'The filestore cache file should have the correct creation time'
        );
        $this->assertSame(
            4242,
            $file->get_timemodified(),
            'The filestore cache file should have the correct modification time'
        );
    }

    /**
     * Teste creating a stub Moodle course backup file.
     *
     * @covers \local_archiving_generator
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_create_moodle_course_backup_stub_file(): void {
        // Create a new Moodle course backup stub file.
        $this->resetAfterTest();
        $file = $this->generator()->create_moodle_course_backup_stub_file(
            timecreated: 1337,
            timemodified: 4242
        );

        // Check that the file was created with the correct data.
        $this->assertNotNull($file, 'The Moodle course backup stub file should not be null');
        $this->assertSame(
            'backup',
            $file->get_component(),
            'The Moodle course backup stub file should have the "backup" component'
        );
        $this->assertSame(
            'course',
            $file->get_filearea(),
            'The Moodle course backup stub file should be in the "course" file area'
        );
        $this->assertStringStartsWith(
            'local_archiving-course-backup-',
            $file->get_filename(),
            'The Moodle course backup stub file should have a filename starting with "local_archiving-course-backup-"'
        );
        $this->assertSame(
            'application/vnd.moodle.backup',
            $file->get_mimetype(),
            'The Moodle course backup stub file should have the correct MIME type'
        );
        $this->assertSame(
            1337,
            $file->get_timecreated(),
            'The Moodle course backup stub file should have the correct creation time'
        );
        $this->assertSame(
            4242,
            $file->get_timemodified(),
            'The Moodle course backup stub file should have the correct modification time'
        );
    }

    /**
     * Teste creating a stub web service.
     *
     * @covers \local_archiving_generator
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_create_webservice(): void {
        global $DB;

        // Create a new web service.
        $this->resetAfterTest();
        $webserviceid = $this->generator()->create_webservice()->id;

        // Check that the web service was created with the correct data.
        $webservice = $DB->get_record('external_services', ['id' => $webserviceid], '*', MUST_EXIST);
        $this->assertNotNull($webservice, 'The web service should not be null');
        $this->assertSame(
            '1',
            $webservice->enabled,
            'The web service should be enabled'
        );
        $this->assertStringStartsWith(
            'testws-',
            $webservice->shortname,
            'The web service should have a shortname starting with "testws-"'
        );
        $this->assertSame(
            '0',
            $webservice->restrictedusers,
            'The web service should not be restricted to specific users'
        );
        $this->assertSame(
            '1',
            $webservice->downloadfiles,
            'The web service should allow file downloads'
        );
        $this->assertSame(
            '1',
            $webservice->uploadfiles,
            'The web service should allow file uploads'
        );
    }

}
