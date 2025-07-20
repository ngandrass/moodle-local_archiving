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
     * @return \local_archiving\archive_job Archive job object ready to be used.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function create_archive_job(array $params = []): \local_archiving\archive_job {
        global $USER;

        // Prepare course and activity
        $course = $this->create_course();
        $cm = $this->create_module('quiz', ['course' => $course->id]);

        // Prepare archive job data.
        $jobdefaults = [
            'context' => context_module::instance($cm->cmid),
            'userid' => $USER->id,
            'settings' => (object) ['foo' => 'bar'],
            'cleansettings' => true,
        ];
        $data = array_merge($jobdefaults, $params);

        // Create new archive job.
        return \local_archiving\archive_job::create(
            context: $data['context'],
            userid: $data['userid'],
            settings: $data['settings'],
            cleansettings: $data['cleansettings']
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
        return get_file_storage()->create_file_from_string(
            [
                'contextid'    => context_user::instance(get_admin()->id)->id,
                'component'    => \local_archiving\type\filearea::TEMP->get_component(),
                'filearea'     => \local_archiving\type\filearea::TEMP->value,
                'itemid'       => 0,
                'filepath'     => '/'.uniqid(more_entropy: true).'/',
                'filename'     => 'testfile.txt',
                'timecreated'  => time(),
                'timemodified' => time(),
            ],
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do '.
            'eiusmod tempor incididunt ut labore et dolore magna aliqua.'
        );
    }

}
