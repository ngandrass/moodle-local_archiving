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

use local_archiving\type\db_table;
use local_archiving\type\log_level;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Provides logging functions for local_archiving and all its subplugins.
 */
class logger {

    /**
     * Crates a new log entry inside the database.
     *
     * This is the generic internal implementation.
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
    protected function write_log_entry_to_db(
        log_level $level,
        string $message,
        ?int $jobid = null,
        ?int $taskid = null
    ): void {
        global $DB;

        $DB->insert_record(db_table::LOG->value, [
            'level' => $level->value,
            'message' => $message,
            'jobid' => $jobid,
            'taskid' => $taskid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Retrieves log entries from the database, filtered according to the given
     * arguments.
     *
     * This is the generic internal implementation.
     *
     * @param log_level|null $minlevel If set, only log entries with this level or higher will be returned
     * @param int|null $jobid If set, only log entries for this job will be returned
     * @param int|null $taskid If set, only log entries for this task will be returned
     * @param int $aftertime Return only logs created after this unix timestamp
     * @param int $beforetime Return only logs created before this unix timestamp
     * @param int $limitnum Maximum number of log entries to return
     * @param int $limitfrom Offset for the returned log entries
     * @return array An array of log entries with level, message, jobid, taskid, and timecreated attributes
     * @throws \dml_exception
     */
    protected function get_log_entries_from_db(
        ?log_level $minlevel = null,
        ?int $jobid = null,
        ?int $taskid = null,
        int $aftertime = 0,
        int $beforetime = 9999999999,
        int $limitnum = 100,
        int $limitfrom = 0
    ): array {
        global $DB;

        // Build WHERE clause.
        $wheresql = "timecreated BETWEEN :aftertime AND :beforetime";
        $params = [
            'aftertime' => $aftertime,
            'beforetime' => $beforetime,
        ];

        if ($minlevel) {
            $wheresql .= " AND level >= :level";
            $params['level'] = $minlevel->value;
        }

        if ($jobid) {
            $wheresql .= " AND jobid = :jobid";
            $params['jobid'] = $jobid;
        }

        if ($taskid) {
            $wheresql .= "taskid = :taskid";
            $params['taskid'] = $taskid;
        }

        // Execute query.
        return $DB->get_records_sql("
                SELECT * FROM {".db_table::LOG->value."}
                WHERE {$wheresql}
                ORDER BY timecreated ASC
            ",
            $params,
            $limitfrom,
            $limitnum
        );

    }

    /**
     * Retrieves all log entries that are not linked to any job or task and
     * match the given criteria.
     *
     * @param log_level $minlevel Return only log entries with this level or higher
     * @param int $aftertime Return only logs created after this unix timestamp
     * @param int $beforetime Return only logs created before this unix timestamp
     * @param int $limitnum Maximum number of log entries to return
     * @param int $limitfrom Offset for the returned log entries
     * @return array An array of log entries with level, message, jobid, taskid, and timecreated attributes
     * @throws \dml_exception
     */
    public function get_logs(
        log_level $minlevel = log_level::TRACE,
        int       $aftertime = 0,
        int       $beforetime = 9999999999,
        int       $limitnum = 100,
        int       $limitfrom = 0
    ): array {
        return $this->get_log_entries_from_db(
            $minlevel,
            0,
            0,
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
    public function log(log_level $level, string $message): void {
        $this->write_log_entry_to_db($level, $message);
    }

    /**
     * Logs a message with the TRACE log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function trace(string $message): void {
        $this->write_log_entry_to_db(log_level::TRACE, $message);
    }

    /**
     * Logs a message with the DEBUG log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function debug(string $message): void {
        $this->write_log_entry_to_db(log_level::DEBUG, $message);
    }

    /**
     * Logs a message with the INFO log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function info(string $message): void {
        $this->write_log_entry_to_db(log_level::INFO, $message);
    }

    /**
     * Logs a message with the WARNING log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function warn(string $message): void {
        $this->write_log_entry_to_db(log_level::WARN, $message);
    }

    /**
     * Logs a message with the ERROR log level
     *
     * @param string $message
     * @return void
     * @throws \dml_exception
     */
    public function error(string $message): void {
        $this->write_log_entry_to_db(log_level::ERROR, $message);
    }

    /**
     * Logs a message with the FATAL log level
     *
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function fatal(string $message): void {
        $this->write_log_entry_to_db(log_level::FATAL, $message);
    }

    /**
     * Formats a log entry for display.
     *
     * @param \stdClass $logentry Log entry object
     * @return string Formatted log entry
     */
    public static function format_log_entry(\stdClass $logentry): string {
        return date('Y-m-d H:i:s', $logentry->timecreated).
            ' ['.str_pad(log_level::from($logentry->level)->name, 5, ' ', STR_PAD_LEFT).'] '.
            ($logentry->taskid ? ' -> ' : '').
            $logentry->message;
    }

}
