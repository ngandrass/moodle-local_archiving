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
 * This file defines the remote_archive_worker class.
 *
 * @package   archivingmod_assign
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_assign;

use archivingmod_assign\local\type\attachment_type;
use archivingmod_assign\local\type\submission_report_section;
use archivingmod_assign\local\type\worker_status;
use curl;
use local_archiving\activity_archiving_task;
use local_archiving\local\type\activity_archiving_task_status;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * A client to interface the remote archive worker service
 */
class remote_archive_worker {
    /** @var int Version of the used API */
    public const API_VERSION = 1;

    /**
     * RemoteArchiveWorker constructor
     *
     * @param string $serverurl URL of the remote archive worker instance
     * @param string $moodlebaseurl Base URL of the Moodle instance the archive worker should call back to
     * @param int $connectiontimeoutsec Seconds to wait until a connection can be established before aborting
     * @param int $requesttimeoutsec Seconds to wait for the request to complete before aborting
     */
    public function __construct(
        /** @var string URL of the remote archive worker instance */
        protected string $serverurl,
        /** @var string Base URL of the Moodle instance the archive worker should call back to */
        protected string $moodlebaseurl,
        /** @var int Seconds to wait until a connection can be established before aborting */
        protected int $connectiontimeoutsec = 5,
        /** @var int Seconds to wait for the request to complete before aborting */
        protected int $requesttimeoutsec = 20,
    ) {
        $this->serverurl = rtrim($this->serverurl, '/');
        $this->moodlebaseurl = rtrim($this->moodlebaseurl, '/');
    }

    /**
     * Creates a new instance of the remote archive worker with default values
     *
     * @return remote_archive_worker New instance of the remote archive worker
     * @throws \dml_exception
     */
    public static function instance(): remote_archive_worker {
        global $CFG;

        return new self(
            get_config('archivingmod_assign', 'worker_url'),
            get_config('archivingmod_assign', 'internal_wwwroot') ?: $CFG->wwwroot
        );
    }

    /**
     * Queries the worker service for its current status
     *
     * @return \stdClass Object containing 'status' and 'queue_len' properties
     * @throws \moodle_exception If the request failed or the response was invalid
     */
    public function get_status(): \stdClass {
        // Execute request.
        // Moodle curl wrapper automatically closes curl handle after requests. No need to call curl_close() manually.
        // Ignore URL filter since we require custom ports and the URL is only configurable by admins.
        $c = new curl(['ignoresecurity' => true]);
        $result = $c->get($this->serverurl . '/status', [], [
            'CURLOPT_CONNECTTIMEOUT' => $this->connectiontimeoutsec,
            'CURLOPT_TIMEOUT' => $this->requesttimeoutsec,
        ]);

        $httpstatus = $c->get_info()['http_code'];  // Invalid PHPDoc in Moodle curl wrapper. Array returned instead of string.
        $data = json_decode($result);

        // @codeCoverageIgnoreStart

        // Handle errors.
        if ($data === null) {
            throw new \moodle_exception('remote_worker_get_status_failed', 'archivingmod_assign', $httpstatus);
        }
        if ($httpstatus != 200) {
            throw new \moodle_exception('a', 'archivingmod_assign', $data->error);
        }
        foreach (['status', 'queue_len'] as $key) {
            if (!isset($data->{$key})) {
                throw new \moodle_exception('remote_worker_missing_return_param', 'archivingmod_assign', $key);
            }
        }

        // Return response.
        return (object) [
            'status' => worker_status::from($data->status),
            'queue_len' => (int) $data->queue_len,
        ];

        // @codeCoverageIgnoreEnd
    }

    /**
     * Generates the payload for a new job creation request based on the given task and submission IDs.
     *
     * This function also validates that all required job settings are present in the task / job settings object.
     *
     * @param string $wstoken Moodle webservice token to use
     * @param activity_archiving_task $task Activity archiving task this request belongs to
     * @param int[] $submissionids List of submission IDs to be archived
     * @return array Payload for the job creation request
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    protected function generate_job_payload_from_task(string $wstoken, activity_archiving_task $task, array $submissionids): array {
        // Check submissionids.
        if (empty($submissionids)) {
            throw new \coding_exception('No submission IDs provided for job creation');
        }

        // Get job settings and validate that all required parameters are present.
        $settings = $task->get_job()->get_settings();
        $expectedkeys = array_merge([
            'paper_format',
            'keep_html_files',
            'image_optimize',
            'image_optimize_width',
            'image_optimize_height',
            'image_optimize_quality',
            'submission_foldername_pattern',
            'submission_filename_pattern',
        ], array_map(
            fn($section) => "report_section_{$section->value}",
            submission_report_section::cases()
        ), array_map(
            fn($section) => "attachment_{$section->value}",
            attachment_type::cases()
        ));

        foreach ($expectedkeys as $key) {
            if (!isset($settings->{$key})) {
                throw new \coding_exception('Missing required job setting: ' . $key);
            }
        }

        // Generate report sections and attachments from settings.
        $sections = [];
        foreach (submission_report_section::cases() as $section) {
            $sections[$section->value] = (bool) $settings->{"report_section_{$section->value}"};
        }

        $attachments = [];
        foreach (attachment_type::cases() as $attachment) {
            $attachments[$attachment->value] = (bool) $settings->{"attachment_{$attachment->value}"};
        }

        // Determine maximal upload byte size.
        $maxbytes = intval(get_config('core', 'maxbytes'));
        if ($maxbytes == 0) {
            $maxbytes = get_max_upload_file_size();
        }

        // Build job creation request payload.
        return [
            "api_version" => self::API_VERSION,
            "taskid" => $task->get_id(),
            "moodle_api" => [
                "wstoken" => $wstoken,
                "base_url" => $this->moodlebaseurl,
                "webservice_url" => $this->moodlebaseurl . '/webservice/rest/server.php',
                "upload_url" => $this->moodlebaseurl . '/webservice/upload.php',
                "max_upload_bytes" => $maxbytes,
            ],
            "job" => [
                "submissionids" => $submissionids,
                "report_sections" => $sections,
                "attachments" => $attachments,
                "paper_format" => $settings->paper_format,
                "archive_filename" => $task->get_job()->generate_archive_name_prefix(),
                "foldername_pattern" => $settings->submission_foldername_pattern,
                "filename_pattern" => $settings->submission_filename_pattern,
                "image_optimize" => $settings->image_optimize ? [
                    "width" => $settings->image_optimize_width,
                    "height" => $settings->image_optimize_height,
                    "quality" => $settings->image_optimize_quality,
                ] : false,
                "archive_filehashes" => true,
                "archive_flatten" => (bool) $settings->archive_flatten,
                "keep_html_files" => (bool) $settings->keep_html_files,
                "fetch_metadata" => true,
            ],
        ];
    }

    /**
     * Tries to enqueue a new archive job at the archive worker service
     *
     * @param string $wstoken Moodle webervice token to use
     * @param activity_archiving_task $task Activity archiving task this request belongs to
     * @param int[] $submissionids List of submission IDs to be archived
     *
     * @return \stdClass Job information returned from the archive worker on success
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function enqueue_archive_job(
        string $wstoken,
        activity_archiving_task $task,
        array $submissionids
    ): \stdClass {
        // Prepare request payload.
        $payload = json_encode(self::generate_job_payload_from_task($wstoken, $task, $submissionids));

        // Execute request.
        // Moodle curl wrapper automatically closes curl handle after requests. No need to call curl_close() manually.
        // Ignore URL filter since we require custom ports and the URL is only configurable by admins.
        $c = new curl(['ignoresecurity' => true]);
        $result = $c->post($this->serverurl . '/archive/archivingmod_assign', $payload, [
            'CURLOPT_CONNECTTIMEOUT' => $this->connectiontimeoutsec,
            'CURLOPT_TIMEOUT' => $this->requesttimeoutsec,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
        ]);

        $httpstatus = $c->get_info()['http_code'];
        $data = json_decode($result);

        // @codeCoverageIgnoreStart
        // Handle errors.
        if ($data === null) {
            if ($httpstatus) {
                $details = "HTTP " . $httpstatus;
            } else {
                $details = curl_strerror($c->get_errno());
            }
            throw new \moodle_exception('remote_worker_enqueue_job_failed_a', 'archivingmod_assign', a: $details);
        }
        if ($httpstatus != 200) {
            throw new \moodle_exception('a', 'archivingmod_assign', a: $data->error);
        }
        foreach (['jobid', 'status'] as $key) {
            if (!isset($data->{$key})) {
                throw new \moodle_exception('remote_worker_missing_return_param', 'archivingmod_assign', a: $key);
            }
        }

        // Decoded JSON data containing jobid and job_status returned on success.
        return (object) [
            'uuid' => $data->jobid,
            'status' => activity_archiving_task_status::from((int) $data->status),
        ];
        // @codeCoverageIgnoreEnd
    }
}
