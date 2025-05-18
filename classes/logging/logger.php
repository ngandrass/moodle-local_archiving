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
 * This file defines the logger class
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\logging;

use local_archiving\type;
use local_archiving\type\log_level;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Provides logging functions for local_archiving and all its subplugins.
 */
class logger {

    /**
     * Crates a new log entry
     *
     * @param log_level $level Log level
     * @param string $message Log message
     * @param int|null $jobid Job ID. If given, the log will be linked to this
     * job
     * @param int|null $taskid Activity archiving task ID. If given, the log
     * will be linked to this task
     * @return void
     * @throws \dml_exception
     */
    protected function create_log_entry(
        log_level $level,
        string $message,
        ?int $jobid = null,
        ?int $taskid = null
    ): void {
        global $DB;

        $logentry = [
            'level' => $level->value,
            'message' => $message,
            'jobid' => $jobid,
            'taskid' => $taskid,
            'timecreated' => time(),
        ];

        $DB->insert_record(type\db_table::LOG->value, (object) $logentry);
    }

    /**
     * Logs a message with the given log level
     *
     * @param log_level $level Log level
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function log(log_level $level, string $message): void {
        $this->create_log_entry($level, $message);
    }

    /**
     * Logs a message with the TRACE log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function trace(string $message): void {
        $this->create_log_entry(log_level::TRACE, $message);
    }

    /**
     * Logs a message with the DEBUG log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function debug(string $message): void {
        $this->create_log_entry(log_level::DEBUG, $message);
    }

    /**
     * Logs a message with the INFO log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function info(string $message): void {
        $this->create_log_entry(log_level::INFO, $message);
    }

    /**
     * Logs a message with the WARNING log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function warn(string $message): void {
        $this->create_log_entry(log_level::WARNING, $message);
    }

    /**
     * Logs a message with the ERROR log level
     *
     * @param string $message
     * @return void
     * @throws \dml_exception
     */
    public function error(string $message): void {
        $this->create_log_entry(log_level::ERROR, $message);
    }

    /**
     * Logs a message with the FATAL log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function fatal(string $message): void {
        $this->create_log_entry(log_level::FATAL, $message);
    }

}
