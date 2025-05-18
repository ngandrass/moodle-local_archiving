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
 * This file defines the job_logger class
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
 * A logger instance that is tied to the specific job given in the constructor.
 * All log entries will be linked to the respective job.
 */
class job_logger extends logger {

    /**
     * Create a new logger instance that is tied to the given job
     *
     * @param int $jobid ID of the job to log for
     */
    public function __construct(
        protected readonly int $jobid,
    ) {
    }

    /**
     * Retrieves all log entries that are linked to the job this logger is tied
     * to and match the given criteria.
     *
     * @param log_level $minlevel Return only log entries with this level or higher
     * @param int $aftertime Return only log entries that were created after this time
     * @param int $beforetime Return only log entries that were created before this time
     * @param int $limitnum Maximum number of log entries to return
     * @param int $limitfrom Offset for the log entries to return
     * @return array An array of log entries with level, message, jobid, taskid, and timecreated attributes
     * @throws \dml_exception
     */
    #[\Override]
    public function get_logs(
        log_level $minlevel = log_level::TRACE,
        int       $aftertime = 0,
        int       $beforetime = 9999999999,
        int       $limitnum = 100,
        int       $limitfrom = 0
    ): array {
        return $this->get_log_entries_from_db(
            $minlevel,
            $this->jobid,
            null,
            $aftertime,
            $beforetime,
            $limitnum,
            $limitfrom
        );
    }


    /**
     * Logs a message with the given log level
     *
     * @param log_level $level Log level
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    #[\Override]
    public function log(log_level $level, string $message): void {
        $this->write_log_entry_to_db($level, $message, $this->jobid);
    }

    /**
     * Logs a message with the TRACE log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    #[\Override]
    public function trace(string $message): void {
        $this->write_log_entry_to_db(log_level::TRACE, $message, $this->jobid);
    }

    /**
     * Logs a message with the DEBUG log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    #[\Override]
    public function debug(string $message): void {
        $this->write_log_entry_to_db(log_level::DEBUG, $message, $this->jobid);
    }

    /**
     * Logs a message with the INFO log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    #[\Override]
    public function info(string $message): void {
        $this->write_log_entry_to_db(log_level::INFO, $message, $this->jobid);
    }

    /**
     * Logs a message with the WARNING log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    #[\Override]
    public function warn(string $message): void {
        $this->write_log_entry_to_db(log_level::WARN, $message, $this->jobid);
    }

    /**
     * Logs a message with the ERROR log level
     *
     * @param string $message
     * @return void
     * @throws \dml_exception
     */
    #[\Override]
    public function error(string $message): void {
        $this->write_log_entry_to_db(log_level::ERROR, $message, $this->jobid);
    }

    /**
     * Logs a message with the FATAL log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    #[\Override]
    public function fatal(string $message): void {
        $this->write_log_entry_to_db(log_level::FATAL, $message, $this->jobid);
    }

}
