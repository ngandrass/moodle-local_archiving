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

use local_archiving\type\activity_archiving_task_status;
use local_archiving\type\cm_state_fingerprint;
use local_archiving\type\db_table;
use local_archiving\type\filearea;
use local_archiving\type\task_content_metadata;
use mod_lti\search\activity;

/**
 * Tests for the archive_job class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for archive_job
 */
final class activity_archiving_task_test extends \advanced_testcase {

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
     * Tests creation and retrieval of an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_create_and_retrieve(): void {
        // Prepare archive job to create task for.
        $this->resetAfterTest();
        $job = $this->generator()->create_archive_job();

        // Create a new activity archiving task.
        $fingerprint = cm_state_fingerprint::from_raw_value(str_repeat('1234', 16));
        $task = activity_archiving_task::create(
            jobid: $job->get_id(),
            context: $job->get_context(),
            cmfingerprint: $fingerprint,
            userid: $job->get_userid(),
            archivingmodname: 'quiz',
            settings: (object) ['foo' => 'bar'],
        );
        $this->assertInstanceOf(activity_archiving_task::class, $task);
        $this->assertSame($job->get_id(), $task->get_jobid(), 'Job ID should match');
        $this->assertSame($job->get_context(), $task->get_context(), 'Context should match');
        $this->assertSame($job->get_userid(), $task->get_userid(), 'User ID should match');
        $this->assertSame('quiz', $task->get_archivingmodname(), 'Archiving mod name should match');
        $this->assertEquals($job, $task->get_job(), 'Job should match');
        $this->assertEquals($fingerprint, $task->get_fingerprint(), 'CM state fingerprint should match');

        // Check that the task can be retrieved by its ID.
        $retrievedtask = activity_archiving_task::get_by_id($task->get_id());
        $this->assertEquals($job, $retrievedtask->get_job(), 'Retrieved job should match created job');
        $this->assertEquals($task, $retrievedtask, 'Retrieved task should match created task');
    }

    /**
     * Tests retrieval of activity archiving tasks by the associated job ID.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_retrieve_by_jobid(): void {
        // Prepare task.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();

        // Try to retrieve the task by job ID.
        $tasks = activity_archiving_task::get_by_jobid($task->get_jobid());
        $this->assertCount(1, $tasks, 'Should retrieve exactly one task by its job ID');
        $this->assertEquals($task, $tasks[0], 'Retrieved task should match created task');
    }

    /**
     * Tests deletion of an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function test_delete(): void {
        global $DB;

        // Prepare task to delete, including an artifact file and task content metadata.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();
        $taskid = $task->get_id();

        $artifactfile = $this->generator()->create_temp_file();
        $task->link_artifact($artifactfile, takeownership: true);
        $task->store_task_content_metadata([new task_content_metadata($taskid, 42, null, null, 'foo')]);

        $task->get_logger()->fatal('This message should be kept until the job is deleted');
        $task->get_logger()->debug('This message should be kept until the job is deleted');

        $webservice = $this->generator()->create_webservice();
        $wstoken = $task->create_webservice_token($webservice->id, get_admin()->id, DAYSECS);

        // Delete the task and assert that the desired records are deleted from the database.
        $task->delete_from_db();

        $this->assertEmpty(
            $DB->get_records(db_table::ACTIVITY_TASK->value, ['id' => $taskid]),
            'Task record should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records(db_table::CONTENT->value, ['taskid' => $taskid]),
            'Task content metadata should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records(db_table::TEMPFILE->value, ['taskid' => $taskid]),
            'Temporary file links should be deleted from the database'
        );
        $this->assertEmpty(
            $DB->get_records('external_tokens', ['token' => $wstoken]),
            'Webservice token should be deleted from the database'
        );
        $this->assertNotEmpty(
            $DB->get_records(db_table::LOG->value, ['taskid' => $taskid]),
            'Task log entries should remain until the job is deleted'
        );
    }

    /**
     * Tests retrieval and clearing of settings in an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     * @dataProvider task_settings_data_provider
     *
     * @param object $settings
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_settings_retrieval_and_clearing(object $settings): void {
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task(['settings' => $settings]);
        $task->set_status(activity_archiving_task_status::FINISHED);

        // Test retrieval of settings.
        $retrievedsettings = $task->get_settings();
        $this->assertEquals($settings, $retrievedsettings, 'Retrieved settings should match');

        // Test clearing of settings.
        $task->clear_settings();
        $this->assertEmpty(get_object_vars($task->get_settings()), 'Settings should be empty after clearing');
    }

    /**
     * Data provider for test_settings_retrieval_and_clearing.
     *
     * @return array Various test data structures to store as task settings.
     */
    public static function task_settings_data_provider(): array {
        return [
            'Empty object' => [new \stdClass()],
            'Simple list' => [(object) [1, 2, 3]],
            'Simple object' => [(object) ['foo' => 'bar', 'baz' => 42]],
            'Nested object' => [(object) [
                'foo' => 'bar',
                'nested' => (object) [
                    'lorem' => 'ipsum',
                    'dolor' => 123,
                    'nestedagain' => (object) [
                        'foo' => 'bar',
                    ],
                ],
            ]],
        ];
    }

    /**
     * Tests that a task rejects clearing settings if it is not finished.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_settings_clearing_unfinished_task(): void {
        // Prepare an unfinished task with settings.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();
        $this->assertNotEmpty($task->get_settings(), 'Task should have settings initially');
        $this->assertFalse($task->is_completed(), 'Task should not be completed initially');

        // Clear settings without forcing.
        $this->expectException(\moodle_exception::class, 'Task should not allow clearing settings if not finished');
        $task->clear_settings();
    }

    /**
     * Tests status transitions and completion state of an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     * @dataProvider status_data_provider
     *
     * @param activity_archiving_task_status $oldstatus Initial task status.
     * @param activity_archiving_task_status $newstatus New task status to set.
     * @param bool $completed Expected completion state after status change.
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_status_and_completion(
        activity_archiving_task_status $oldstatus,
        activity_archiving_task_status $newstatus,
        bool $completed
    ): void {
        // Prepare task with old status.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task(['status' => $oldstatus]);
        $this->assertEquals($oldstatus, $task->get_status(), 'Initial status should match old status');

        // Set new status and check if it is set correctly.
        $task->set_status($newstatus);
        $this->assertEquals($newstatus, $task->get_status(), 'Status should match new status');
        $this->assertSame(
            $completed,
            $task->is_completed(),
            'Task completion status should match expected value'
        );
    }

    /**
     * Data provider for test_status_and_completion.
     *
     * @return array Test data for status transitions.
     */
    public static function status_data_provider(): array {
        $res = [];

        foreach (activity_archiving_task_status::cases() as $status) {
            $res[$status->name] = [
                'oldstatus' => activity_archiving_task_status::UNKNOWN,
                'newstatus' => $status,
                'completed' => match ($status) {
                    activity_archiving_task_status::FINISHED,
                    activity_archiving_task_status::CANCELED,
                    activity_archiving_task_status::TIMEOUT,
                    activity_archiving_task_status::FAILED,
                    => true,
                    default => false
                },
            ];
        }

        return $res;
    }

    /**
     * Tests storing and retrieving content metadata for an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_task_content_metadata(): void {
        // Prepare task without content metadata.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();
        $this->assertEmpty($task->get_task_content_metadata(), 'Task should not have content metadata initially');

        // Store some content metadata.
        $metadata = [
            new task_content_metadata($task->get_id(), 42, null, null, 'foo'),
            new task_content_metadata($task->get_id(), 43, null, null, 'bar'),
            new task_content_metadata($task->get_id(), 44, 'sometable', 1337, null),
            new task_content_metadata($task->get_id(), 44, 'sometable', 1234, null),
            new task_content_metadata($task->get_id(), 60, 'anothertable', 1, 'Lorem ipsum'),
        ];
        $task->store_task_content_metadata($metadata);
        $this->assertCount(count($metadata), $task->get_task_content_metadata(), 'Task should have stored content metadata');
        $this->assertEquals(
            array_values($metadata),
            array_values($task->get_task_content_metadata()),
            'Retrieved content metadata should match stored metadata'
        );

        // Add some more metadata.
        $moremetadata = [
            new task_content_metadata($task->get_id(), 45, null, null, 'baz'),
            new task_content_metadata($task->get_id(), 46, 'sometable', 1234, 'qux'),
        ];
        $task->store_task_content_metadata($moremetadata);
        $this->assertEquals(
            array_values(array_merge($metadata, $moremetadata)),
            array_values($task->get_task_content_metadata()),
            'Task should have stored more content metadata'
        );
    }

    /**
     * Tests that an activity archiving task rejects invalid content metadata rows.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_task_content_metadata_reject_invalid_rows(): void {
        // Prepare taask and partly invalid content metadata array.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();
        $metadata = [
            new task_content_metadata($task->get_id(), 42, null, null, 'foo'),
            new task_content_metadata($task->get_id(), 43, null, null, 'bar'),
            new \stdClass(),
            new task_content_metadata($task->get_id(), 60, 'anothertable', 1, 'Lorem ipsum'),
        ];

        // Try to add the metadata to the task.
        $this->expectException(\coding_exception::class, 'Task should reject invalid content metadata rows');
        $task->store_task_content_metadata($metadata);
    }

    /**
     * Tests creation, retrieval, and deletion of webservice tokens for an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_webservice_token(): void {
        global $DB;

        // Prepare task and webservice.
        $this->resetAfterTest();
        $webservice = $this->generator()->create_webservice();
        $task = $this->generator()->create_activity_archiving_task();

        // Ensure that the task starts without a webservice token.
        $this->assertNull($task->get_webservice_token(), 'Task should not have a webservice token initially');

        // Create a webservice token for the task.
        $wstoken = $task->create_webservice_token($webservice->id, get_admin()->id, DAYSECS);
        $this->assertSame($wstoken, $task->get_webservice_token(), 'Task should have a webservice token after creation');
        $this->assertCount(
            1,
            $DB->get_records('external_tokens', ['token' => $wstoken]),
            'Webservice token should be stored in the database'
        );

        // Create another webservice token for the same task.
        $newwstoken = $task->create_webservice_token($webservice->id, get_admin()->id, DAYSECS);
        $this->assertNotSame($wstoken, $newwstoken, 'New webservice token should be different from the old one');
        $this->assertCount(
            1,
            $DB->get_records('external_tokens', ['token' => $newwstoken]),
            'New webservice token should be stored in the database'
        );
        $this->assertEmpty(
            $DB->get_records('external_tokens', ['token' => $wstoken]),
            'Old webservice token should be removed from the database'
        );

        // Delete the latest webservice token.
        $task->delete_webservice_token();
        $this->assertNull($task->get_webservice_token(), 'Task should not have a webservice token after deletion');
        $this->assertEmpty(
            $DB->get_records('external_tokens', ['token' => $newwstoken]),
            'Webservice token should be removed from the database after deletion'
        );
    }

    /**
     * Tests setting and getting progress of an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     * @dataProvider progress_data_provider
     *
     * @param int $progress Progress value to set (0-100).
     * @param bool $isvalid Whether the progress value is valid (0-100).
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_progress(int $progress, bool $isvalid): void {
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();

        if (!$isvalid) {
            $this->expectException(\moodle_exception::class, 'Task should not allow invalid progress values');
        }

        // Task should start with 0% progress.
        $this->assertSame(0, $task->get_progress(), 'Initial progress should be 0%');

        // Set progress and check if it is set correctly.
        $task->set_progress($progress);
        $this->assertSame($progress, $task->get_progress(), 'Progress should match set value');
    }

    /**
     * Test data provider for test_progress.
     *
     * @return array[] Test data for progress validation.
     */
    public static function progress_data_provider(): array {
        return [
            '0%' => ['progress' => 0, 'isvalid' => true],
            '25%' => ['progress' => 25, 'isvalid' => true],
            '42%' => ['progress' => 42, 'isvalid' => true],
            '80%' => ['progress' => 80, 'isvalid' => true],
            '99%' => ['progress' => 99, 'isvalid' => true],
            '100%' => ['progress' => 100, 'isvalid' => true],
            '-1%' => ['progress' => -1, 'isvalid' => false],
            '101%' => ['progress' => 101, 'isvalid' => false],
        ];
    }

    /**
     * Tests artifact file linking.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function test_artifact_linking(): void {
        // Prepare an artifact file and an activity archiving task.
        $this->resetAfterTest();
        $artifactfile = $this->generator()->create_temp_file();
        $task = $this->generator()->create_activity_archiving_task();

        // New tasks should not have any linked artifacts.
        $this->assertEmpty($task->get_linked_artifacts(), 'New task should not have linked artifacts');

        // Link the artifact file to the task with ownership takeover.
        $task->link_artifact($artifactfile, takeownership: true);
        $this->assertFalse(
            get_file_storage()->get_file_by_id($artifactfile->get_id()),
            'Original artifact file should be deleted from storage after linking when takeownership is true'
        );

        $linkedartifacts = $task->get_linked_artifacts();
        $this->assertCount(1, $linkedartifacts, 'Task should have one linked artifact');
        $this->assertEquals(
            $artifactfile->get_contenthash(),
            array_shift($linkedartifacts)->get_contenthash(),
            'Linked artifact should match the original artifact file'
        );

        // Link another artifact file to the same task without ownership takeover.
        $anotherartifactfile = $this->generator()->create_temp_file();
        $task->link_artifact($anotherartifactfile, takeownership: false);
        $this->assertEquals(
            $anotherartifactfile,
            get_file_storage()->get_file_by_id($anotherartifactfile->get_id()),
            'Original artifact file should remain untouched when takeownership is false'
        );

        $linkedartifacts = $task->get_linked_artifacts();
        $this->assertCount(2, $linkedartifacts, 'Task should have two linked artifacts');
        $this->assertEquals(
            $artifactfile->get_contenthash(),
            array_shift($linkedartifacts)->get_contenthash(),
            'First linked artifact should still match the first original artifact file'
        );
        $this->assertEquals(
            $anotherartifactfile->get_contenthash(),
            array_shift($linkedartifacts)->get_contenthash(),
            'Second linked artifact should match the second original artifact file'
        );
    }

    /**
     * Tests unlinking of artifacts from an activity archiving task.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function test_artifact_unlinking(): void {
        // Prepare a task with linked artifacts.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();

        $artifacts = [
            $this->generator()->create_temp_file(),
            $this->generator()->create_temp_file(),
            $this->generator()->create_temp_file(),
        ];
        foreach ($artifacts as $artifact) {
            $task->link_artifact($artifact, takeownership: true);
        }

        // Assert that the three artifacts were linked correctly.
        $this->assertEquals(
            array_values(array_map(fn($a) => $a->get_contenthash(), $artifacts)),
            array_values(array_map(fn($a) => $a->get_contenthash(), $task->get_linked_artifacts())),
            'Task should have the exact three linked artifacts'
        );

        // Unlink  one artifact file and check if it is unlinked correctly.
        $linkedartifacts = $task->get_linked_artifacts();
        $artifacttounlink = array_shift($linkedartifacts);
        $task->unlink_artifact($artifacttounlink, delete: false);
        $this->assertSame(
            $artifacttounlink->get_contenthash(),
            get_file_storage()->get_file_by_id($artifacttounlink->get_id())->get_contenthash(),
            'Artifact file should remain in storage after unlinking with delete=false'
        );

        $this->assertEquals(
            array_values(array_map(fn($a) => $a->get_contenthash(), $linkedartifacts)),
            array_values(array_map(fn($a) => $a->get_contenthash(), $task->get_linked_artifacts())),
            'Task should have two linked artifacts after unlinking one'
        );

        // Unlink and delete the remaining two artifacts and check if the task has no linked artifacts left.
        foreach ($linkedartifacts as $artifact) {
            $task->unlink_artifact($artifact, delete: true);
            $this->assertFalse(
                get_file_storage()->get_file_by_id($artifact->get_id()),
                'Artifact file should be deleted from storage after unlinking with delete=true'
            );
        }
        $this->assertEmpty($task->get_linked_artifacts(), 'Task should have no linked artifacts after unlinking all');
    }

    /**
     * Tests the generation of fileinfo records for artifact files.
     *
     * @covers \local_archiving\activity_archiving_task
     *
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_artifact_fileinfo_generation(): void {
        // Prepare task.
        $this->resetAfterTest();
        $task = $this->generator()->create_activity_archiving_task();

        // Generate fileinfo for the task.
        $fileinfo = $task->generate_artifact_fileinfo('testfile.txt');
        $this->assertSame(
            $task->get_context()->get_course_context()->id,
            $fileinfo->contextid,
            'File should be in the course context'
        );
        $this->assertSame(
            'archivingmod_'.$task->get_archivingmodname().'_mock',
            $fileinfo->component,
            'Component should match archiving mod name'
        );
        $this->assertSame(filearea::ARTIFACT->value, $fileinfo->filearea, 'File area should be filearea::ARTIFACT');
        $this->assertNotNull($fileinfo->itemid, 'Item ID should exist');
        $this->assertNotEmpty($fileinfo->filepath, 'Filepath should not be empty');
        $this->assertSame('testfile.txt', $fileinfo->filename, 'Filename should match provided name');
    }

}
