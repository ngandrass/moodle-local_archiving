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
 * An asynchronous activity archiving task
 *
 * @package     local_archiving
 * @category    driver
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\driver\mod;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * An asynchronous activity archiving task. Holds all information and state for a single
 * activity archiving task.
 */
class task {

    /** @var int ID of the archive job this task is associated with */
    protected int $jobid;

    /** @var int ID of this activity archiving task */
    protected int $taskid;

    /** @var int Moodle context this task is run in */
    protected int $context;

    /** @var ?\stdClass Task specific settings (lazy-loaded) */
    protected ?\stdClass $settings;

    /**
     * Builds a new activity archiving task object. This does not create entries
     * in the database. For creating or loading tasks use the respective static
     * methods.
     *
     * @param int $jobid
     * @param int $taskid
     */
    protected function __construct(int $jobid, int $taskid) {
        $this->jobid = $jobid;
        $this->taskid = $taskid;
        $this->settings = null;
    }

    public static function create(int $jobid, \stdClass $tasksettings): task {
        // TODO: Create in DB ...
        $taskid = 42;

        return new self($jobid, $taskid);
    }

    public static function load(int $taskid): task {
        // TODO: Actually get stuff from the DB lol ...

        return new self(0, $taskid);
    }

    public function get_settings(): \stdClass {
        if (is_null($this->settings)) {
            $this->settings = new \stdClass(); // TODO: Implement DB query.
        }

        return $this->settings;
    }

    public function get_status(): int {
        // TODO: Implement DB query.
        return task_status::STATUS_UNINITIALIZED;
    }

    public function set_status(int $status): void {
        // TODO: Implement DB query.
    }

    public function get_progress(): int {
        // TODO: Implement.
        return 0;
    }

    public function set_progress(int $progress): void {
        // TODO: Implement.
    }

}
