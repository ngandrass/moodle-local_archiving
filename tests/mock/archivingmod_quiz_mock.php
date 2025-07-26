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

/**
 * Mock activity archiving driver
 *
 * @package     archivingmod_assign
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use archivingmod_quiz\quiz_manager;
use local_archiving\activity_archiving_task;
use local_archiving\type\activity_archiving_task_status;
use local_archiving\type\task_content_metadata;

// @codingStandardsIgnoreFile
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Mock activity archiving driver
 */
class archivingmod_quiz_mock extends \local_archiving\driver\archivingmod {

    /** @var \stdClass Course the quiz lives in */
    protected \stdClass $course;

    /** @var \cm_info Info object of the associated course module */
    protected \cm_info $cm;

    /** @var int ID of the targeted quiz */
    protected int $quizid;

    public function __construct(\context_module $context) {
        parent::__construct($context);

        // Ensure this is not misused.
        if (!defined('PHPUNIT_TEST')) {
            die('This is a mock driver and should not be used outside of tests.');
        }

        // Try to get course, cm info, and quiz.
        list($this->course, $this->cm) = get_course_and_cm_from_cmid($this->cmid, 'quiz');
        if (empty($this->cm)) {
            throw new \moodle_exception('invalid_cmid', 'archivingmod_quiz');
        }
        if ($this->course->id != $this->courseid) {
            throw new \moodle_exception('invalid_courseid', 'archivingmod_quiz');
        }
        $this->quizid = $this->cm->instance;
    }

    #[\Override]
    public function is_enabled(): bool {
        return true;
    }

    #[\Override]
    public static function is_ready(): bool {
        return true;
    }

    #[\Override]
    public static function get_supported_activities(): array {
        return ['quiz'];
    }

    #[\Override]
    public function can_be_archived(): bool {
        return true;
    }

    #[\Override]
    public function get_job_create_form(string $handler, \cm_info $cminfo): \local_archiving\form\job_create_form {
        return new \local_archiving\form\job_create_form($handler, $cminfo);
    }

    #[\Override]
    public function execute_task(activity_archiving_task $task): void {
        if (!$task->is_completed()) {
            $tempfile = get_file_storage()->create_file_from_string(
                $task->generate_artifact_fileinfo('artifact.txt'),
                'Lorem ipsum dolor sit amet'
            );
            $task->link_artifact($tempfile);
            $task->set_progress(100);
            $task->set_status(activity_archiving_task_status::FINISHED);
        }
    }

    #[\Override]
    public function get_task_content_metadata(activity_archiving_task $task): array {
        $quizmanager = quiz_manager::from_context($task->get_context());

        $res = [];
        foreach ($quizmanager->get_attempts() as $attempt) {
            $res[] = new task_content_metadata(
                taskid: $task->get_id(),
                userid: $attempt->userid,
                reftable: 'quiz_attempts',
                refid: $attempt->attemptid,
                summary: null
            );
        }

        return $res;
    }

}
