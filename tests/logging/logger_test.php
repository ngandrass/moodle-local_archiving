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

namespace local_archiving\logging;

use local_archiving\type\log_level;

/**
 * Tests for the logger classes.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the logger classes.
 */
final class logger_test extends \advanced_testcase {
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
     * Test data provider for basic logger tests.
     *
     * @return array[] Test data with log level and message
     */
    public static function log_data_provider(): array {
        return [
            'TRACE' => [log_level::TRACE, 'Trace message'],
            'DEBUG' => [log_level::DEBUG, 'Debug message'],
            'INFO' => [log_level::INFO, 'Info message'],
            'WARN' => [log_level::WARN, 'Warning message'],
            'ERROR' => [log_level::ERROR, 'Error message'],
            'FATAL' => [log_level::FATAL, 'Critical message'],
        ];
    }

    /**
     * Tests logging and retrieving logs using the base logger.
     *
     * @covers       \local_archiving\logging\logger
     * @dataProvider log_data_provider
     *
     * @param log_level $level Log level
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function test_base_logger_log(log_level $level, string $message): void {
        // Prepare logger that logs everything.
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $logger = new logger();

        // Test direct logging.
        $logger->log($level, $message);
        $logs = $logger->get_logs();
        $this->assertCount(1, $logs, "Expected one log entry.");

        $log = array_shift($logs);
        $this->assertEquals($level->value, $log->level, 'Log level does not match expected value.');
        $this->assertSame($message, $log->message, 'Log message does not match expected value.');
        $this->assertGreaterThan(time() - MINSECS, $log->timecreated, 'Log time is not within the expected range.');

        // Test logging via the convenience method.
        $logger->{strtolower($level->name)}($message);
        $logs = array_values($logger->get_logs());
        $this->assertCount(2, $logs, "Expected two log entries after convenience method call.");

        $this->assertSame($logs[0]->level, $logs[1]->level, 'Log levels do not match after convenience method call.');
        $this->assertSame($logs[0]->message, $logs[1]->message, 'Log messages do not match after convenience method call.');
    }

    /**
     * Tests logging and retrieving logs using the job-specific logger.
     *
     * @covers       \local_archiving\logging\job_logger
     * @dataProvider log_data_provider
     *
     * @param log_level $level Log level
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function test_job_logger_log(log_level $level, string $message): void {
        // Prepare logger that logs everything.
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $jobid = 42;
        $logger = new job_logger($jobid);

        // Test direct logging.
        $logger->log($level, $message);
        $logs = $logger->get_logs();
        $this->assertCount(1, $logs, "Expected one job-log entry.");

        $log = array_shift($logs);
        $this->assertEquals($level->value, $log->level, 'Job-log level does not match expected value.');
        $this->assertSame($message, $log->message, 'Job-log message does not match expected value.');
        $this->assertGreaterThan(time() - MINSECS, $log->timecreated, 'Job-log time is not within the expected range.');
        $this->assertEquals($jobid, $log->jobid, 'Job-log jobid does not match expected value.');

        // Test logging via the convenience method.
        $logger->{strtolower($level->name)}($message);
        $logs = array_values($logger->get_logs());
        $this->assertCount(2, $logs, "Expected two job-log entries after convenience method call.");

        $this->assertSame($logs[0]->level, $logs[1]->level, 'Job-log levels do not match after convenience method call.');
        $this->assertSame($logs[0]->message, $logs[1]->message, 'Job-log messages do not match after convenience method call.');
        $this->assertSame($logs[0]->jobid, $logs[1]->jobid, 'Job-log jobids do not match after convenience method call.');

        // Create a second logger and assert that it does not interfere with the first logger.
        $logger2 = new logger();
        $logger2->log($level, $message);

        $this->assertCount(3, $logger2->get_logs(), "Expected three logs in the generic base logger.");
        $this->assertCount(2, $logger->get_logs(), "Expected two log entries for the job logger.");
    }

    /**
     * Tests logging and retrieving logs using the task logger.
     *
     * @covers       \local_archiving\logging\task_logger
     * @dataProvider log_data_provider
     *
     * @param log_level $level Log level
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function test_task_logger_log(log_level $level, string $message): void {
        // Prepare logger that logs everything.
        $this->resetAfterTest();
        set_config('log_level', log_level::TRACE->value, 'local_archiving');
        $jobid = 42;
        $taskid = 1337;
        $logger = new task_logger($jobid, $taskid);

        // Test direct logging.
        $logger->log($level, $message);
        $logs = $logger->get_logs();
        $this->assertCount(1, $logs, "Expected one log entry.");

        $log = array_shift($logs);
        $this->assertEquals($level->value, $log->level, 'Log level does not match expected value.');
        $this->assertSame($message, $log->message, 'Log message does not match expected value.');
        $this->assertGreaterThan(time() - MINSECS, $log->timecreated, 'Log time is not within the expected range.');
        $this->assertEquals($jobid, $log->jobid, 'Log jobid does not match expected value.');
        $this->assertEquals($taskid, $log->taskid, 'Log taskid does not match expected value.');

        // Test logging via the convenience method.
        $logger->{strtolower($level->name)}($message);
        $logs = array_values($logger->get_logs());
        $this->assertCount(2, $logs, "Expected two log entries after convenience method call.");

        $this->assertEquals($logs[0]->level, $logs[1]->level, 'Log levels do not match after convenience method call.');
        $this->assertSame($logs[0]->message, $logs[1]->message, 'Log messages do not match after convenience method call.');
        $this->assertSame($logs[0]->jobid, $logs[1]->jobid, 'Log jobids do not match after convenience method call.');
        $this->assertSame($logs[0]->taskid, $logs[1]->taskid, 'Log taskids do not match after convenience method call.');

        // Create a second task logger for the same job and assert that it does not interfere with the first logger.
        $logger2 = new task_logger($jobid, $taskid + 1);
        $logger2->log($level, $message);

        $this->assertCount(2, $logger->get_logs(), "Expected two log entries for the first task logger.");
        $this->assertCount(1, $logger2->get_logs(), "Expected one log entry for the second task logger.");

        // Create a job logger and assert that it combines the logs from the associated tasks correctly.
        $joblogger = new job_logger($jobid);
        $joblogs = $joblogger->get_logs();
        $this->assertCount(3, $joblogs, "Expected three log entries in the job logger.");
    }

    /**
     * Tests that only log entries with a level equal to or higher than the
     * configured minimum are actually logged.
     *
     * @covers       \local_archiving\logging\logger
     * @covers       \local_archiving\logging\job_logger
     * @covers       \local_archiving\logging\task_logger
     * @dataProvider log_data_provider
     *
     * @param log_level $level Threshold log level
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function test_log_level(log_level $level, string $message): void {
        // Prepare loggers.
        $this->resetAfterTest();
        set_config('log_level', $level->value, 'local_archiving');
        $logger = new logger();
        $joblogger = new job_logger(42);
        $tasklogger = new task_logger(42, 1337);

        // Log message with different log levels.
        $expectednumlogs = 0;
        foreach (log_level::cases() as $curlevel) {
            // Decide if the current log level should be logged.
            if ($curlevel->value >= $level->value) {
                $expectednumlogs++;
            }

            // Trigger log events for all loggers.
            $logger->log($curlevel, $message);
            $joblogger->log($curlevel, $message);
            $tasklogger->log($curlevel, $message);
        }

        // Assert that the loggers logged the expected number of logs.
        $this->assertCount(
            $expectednumlogs * 3,
            $logger->get_logs(),
            "Expected 3x {$expectednumlogs} log entries in the base logger."
        );
        $this->assertCount(
            $expectednumlogs * 2,
            $joblogger->get_logs(),
            "Expected 2x {$expectednumlogs} log entries in the job logger."
        );
        $this->assertCount(
            $expectednumlogs,
            $tasklogger->get_logs(),
            "Expected 1x {$expectednumlogs} log entries in the task logger."
        );
    }

    /**
     * Tests formatting log entries.
     *
     * @covers       \local_archiving\logging\logger
     * @dataProvider log_data_provider
     *
     * @param log_level $level Log level
     * @param string $message Log message
     * @return void
     * @throws \dml_exception
     */
    public function test_format_log_entry(log_level $level, string $message): void {
        // Prepare a log entry.
        $this->resetAfterTest();
        set_config('log_level', $level->value, 'local_archiving');

        $logger = new logger();
        $logger->log($level, $message);

        $logs = $logger->get_logs();
        $log = array_pop($logs);

        // Format the log entry and assert that the information is included.
        $formatted = $logger->format_log_entry($log);
        $this->assertStringContainsString($level->name, $formatted, 'Formatted log entry does not contain the log level.');
        $this->assertStringContainsString($message, $formatted, 'Formatted log entry does not contain the log message.');
    }
}
