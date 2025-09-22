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

use local_archiving\activity_archiving_task;
use local_archiving\archive_job;
use local_archiving\type\activity_archiving_task_status;
use local_archiving\type\cm_state_fingerprint;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

global $CFG; // @codeCoverageIgnore
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php'); // @codeCoverageIgnore

/**
 * Tests generator for the local_archiving plugin
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_archiving_generator extends \testing_data_generator {

    /**
     * Creates a new archive job for a new course with a quiz activity.
     *
     * @param array $params Optional parameters to override defaults.
     * @param \stdClass|null $course Optional course object to use. If not provided, a new course will be created.
     * @param \stdClass|null $cm Optional course module object to use. If not provided, a new quiz module will be created.
     * @return archive_job Archive job object ready to be used.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function create_archive_job(array $params = [], ?\stdClass $course = null, ?\stdClass $cm = null): archive_job {
        global $USER;

        // Prepare course and activity.
        $course = $course ?? $this->create_course();
        $cm = $cm ?? $this->create_module('quiz', ['course' => $course->id]);

        // Prepare archive job data.
        $jobdefaults = [
            'context' => context_module::instance($cm->cmid),
            'userid' => $USER->id,
            'trigger' => 'manual',
            'settings' => (object) ['foo' => 'bar'],
            'cleansettings' => true,
        ];
        $data = array_merge($jobdefaults, $params);

        // Create new archive job.
        return archive_job::create(
            context: $data['context'],
            userid: $data['userid'],
            trigger: $data['trigger'],
            settings: $data['settings'],
            cleansettings: $data['cleansettings']
        );
    }

    /**
     * Creates a new activity archiving task for an given or created archive job.
     *
     * @param array $params Optional parameters to override defaults.
     * @param archive_job|null $job Optional archive job to use. If not provided, a new job will be created.
     * @return activity_archiving_task Freshly created activity archiving task.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function create_activity_archiving_task(array $params = [], ?archive_job $job = null): activity_archiving_task {
        global $USER;

        // Create default job if not explicitly provided.
        if ($job === null) {
            $job = $this->create_archive_job($params);
        }

        // Prepare task data.
        $taskdefaults = [
            'jobid' => $job->get_id(),
            'context' => $job->get_context(),
            'cmfingerprint' => cm_state_fingerprint::from_raw_value(str_repeat('0', 64)),
            'userid' => $USER->id,
            'archivingmodname' => 'quiz',
            'settings' => (object) ['foo' => 'bar'],
            'status' => activity_archiving_task_status::UNINITIALIZED,
        ];
        $data = array_merge($taskdefaults, $params);

        // Create and return the activity archiving task.
        return activity_archiving_task::create(
            jobid: $data['jobid'],
            context: $data['context'],
            cmfingerprint: $data['cmfingerprint'],
            userid: $data['userid'],
            archivingmodname: $data['archivingmodname'],
            settings: $data['settings'],
            status: $data['status']
        );
    }

    /**
     * Generates an stdClass that contains a full set of valid test data for file_handle objects.
     * Does not actually create the file_handle within the database.
     *
     * @param array $params Optional parameters to override defaults.
     * @return \stdClass Full set of file_handle data.
     */
    public function generate_file_handle_data(array $params = []): \stdClass {
        $defaults = [
            'id' => 1,
            'jobid' => 1,
            'archivingstorename' => 'localdir',
            'deleted' => false,
            'filename' => 'testfile.txt',
            'filepath' => '/',
            'filesize' => 1234,
            'sha256sum' => str_repeat('a', 64),
            'mimetype' => 'text/plain',
            'timecreated' => time(),
            'timemodified' => time(),
            'filekey' => 'testkey',
        ];

        $data = array_merge($defaults, $params);

        return (object) $data;
    }

    /**
     * Creates a new file_handle object with the given parameters or default if none are provided.
     *
     * @param array $params Optional parameters to override defaults.
     * @return \local_archiving\file_handle Freshly created file_handle object.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function create_file_handle(array $params = []): \local_archiving\file_handle {
        $data = $this->generate_file_handle_data($params);

        return \local_archiving\file_handle::create(
            jobid: $data->jobid,
            archivingstorename: $data->archivingstorename,
            filename: $data->filename,
            filepath: $data->filepath,
            filesize: $data->filesize,
            sha256sum: $data->sha256sum,
            mimetype: $data->mimetype,
            filekey: $data->filekey
        );
    }

    /**
     * Creates a temporary file within the TEMP file area within the context
     * of the admin user.
     *
     * @return stored_file Created temp file.
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function create_temp_file(): \stored_file {
        $uniqid = uniqid(more_entropy: true);

        return get_file_storage()->create_file_from_string(
            [
                'contextid'    => context_user::instance(get_admin()->id)->id,
                'component'    => \local_archiving\type\filearea::TEMP->get_component(),
                'filearea'     => \local_archiving\type\filearea::TEMP->value,
                'itemid'       => 0,
                'filepath'     => "/{$uniqid}/",
                'filename'     => "testfile-{$uniqid}.txt",
                'timecreated'  => time(),
                'timemodified' => time(),
            ],
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do '.
            'eiusmod tempor incididunt ut labore et dolore magna aliqua. '.
            'time='.time().' id='.$uniqid
        );
    }

    /**
     * Creates a test file within the filstore cache area.
     *
     * @param int $filehandleid Optional file handle ID to use as item ID for the file
     * @param int|null $timecreated Optional time created for the file, defaults to current time
     * @param int|null $timemodified Optional time modified for the file, defaults to current time
     * @return stored_file Created file in the filestore cache area
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function create_filestore_cache_file(
        int $filehandleid = 32,
        ?int $timecreated = null,
        ?int $timemodified = null
    ): \stored_file {
        $uniqid = uniqid(more_entropy: true);
        $timecreated = $timecreated ?? time();
        $timemodified = $timemodified ?? $timecreated;

        return get_file_storage()->create_file_from_string(
            [
                'contextid'    => context_user::instance(get_admin()->id)->id,
                'component'    => \local_archiving\type\filearea::FILESTORE_CACHE->get_component(),
                'filearea'     => \local_archiving\type\filearea::FILESTORE_CACHE->value,
                'itemid'       => $filehandleid,
                'filepath'     => '/',
                'filename'     => "testfile-{$uniqid}.txt",
                'timecreated'  => $timecreated,
                'timemodified' => $timemodified,
            ],
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do '.
            'eiusmod tempor incididunt ut labore et dolore magna aliqua. '.
            'time='.time().' id='.$uniqid
        );
    }

    /**
     * Creates a mocked Moodle course backup file inside the backup file area
     *
     * @param int|null $timecreated Optional time created for the file, defaults to current time
     * @param int|null $timemodified Optional time modified for the file, defaults to current time
     * @return stored_file Created Moodle course backup file in the backup area
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function create_moodle_course_backup_stub_file(
        ?int $timecreated = null,
        ?int $timemodified = null
    ): \stored_file {
        $uniqid = uniqid(more_entropy: true);
        $timecreated = $timecreated ?? time();
        $timemodified = $timemodified ?? $timecreated;

        return get_file_storage()->create_file_from_string(
            [
                'contextid'    => context_user::instance(get_admin()->id)->id,
                'component'    => 'backup',
                'filearea'     => 'course',
                'itemid'       => 0,
                'filepath'     => '/',
                'filename'     => "local_archiving-course-backup-{$timecreated}-{$uniqid}.mbz",
                'mimetype'     => 'application/vnd.moodle.backup',
                'timecreated'  => $timecreated,
                'timemodified' => $timemodified,
            ],
            'This is a test backup file with id '.$uniqid
        );
    }

    /**
     * Creates a new webservice for testing purposes.
     *
     * @return stdClass Webservice record object containing the created webservice data.
     * @throws dml_exception
     */
    public function create_webservice(): \stdClass {
        global $DB;

        $uniqid = uniqid(more_entropy: true);
        $webserviceid = $DB->insert_record('external_services', (object) [
            'name' => "Test Webservice {$uniqid}",
            'shortname' => "testws-{$uniqid}",
            'enabled' => 1,
            'requiredcapabilities' => '',
            'restrictedusers' => false,
            'downloadfiles' => true,
            'uploadfiles' => true,
            'timecreated' => time(),
        ]);

        return $DB->get_record('external_services', ['id' => $webserviceid], '*', MUST_EXIST);
    }

}
