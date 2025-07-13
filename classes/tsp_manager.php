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
 * This file defines the tsp_manager class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use local_archiving\type\db_table;
use local_archiving\type\filearea;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Manages all Time-Stamp Protocol (TSP) related tasks.
 */
class tsp_manager {

    /** @var file_handle Handle of the file this TSP manager instance is for */
    protected file_handle $filehandle;

    /** @var \stdClass Moodle config object for this plugin */
    protected \stdClass $config;

    /** @var string File extension of the TSP query file */
    public const TSP_QUERY_FILE_EXTENSION = 'tsq';

    /** @var string File extension of the TSP reply file */
    public const TSP_REPLY_FILE_EXTENSION = 'tsr';

    /**
     * Creates a new tsp_manager instance.
     *
     * @param file_handle $filehandle The file handle this TSP manager operates on
     * @throws \dml_exception If the plugin config could not be loaded
     */
    public function __construct(file_handle $filehandle) {
        $this->config = get_config('local_archiving');
        $this->filehandle = $filehandle;
    }

    /**
     * Determines if automatic TSP signing is enabled globally.
     *
     * @return bool True if automatic TSP signing is enabled, false otherwise
     * @throws \dml_exception
     */
    public static function is_automatic_tsp_signing_enabled(): bool {
        $config = get_config('local_archiving');
        return $config->tsp_enable && $config->tsp_automatic_signing;
    }

    /**
     * Provides a tsp_client instance for this tsp_manager.
     *
     * @return tsp_client A fresh Timestamp-Protocol client instance
     */
    protected function get_tsp_client(): tsp_client {
        return new tsp_client($this->config->tsp_server_url);
    }

    /**
     * Checks if the associated file wants an automatically generated TSP
     * timestamp.
     *
     * @return bool True if the associated file wants a TSP timestamp to be
     *              automatically generated, false otherwise
     * @throws \dml_exception On database error
     */
    public function wants_tsp_timestamp(): bool {
        if ($this->config->tsp_enable &&
            $this->config->tsp_automatic_signing &&
            $this->has_tsp_timestamp() === false
        ) {
                return true;
        }

        return false;
    }

    /**
     * Checks if the associated file already has a TSP timestamp.
     *
     * @return bool True if the associated file already has a TSP timestamp
     * @throws \dml_exception On database error
     */
    public function has_tsp_timestamp(): bool {
        global $DB;

        $numtsprecords = $DB->count_records(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
        ]);

        return $numtsprecords > 0;
    }

    /**
     * Returns the TSP data for the associated file.
     *
     * @return ?\stdClass TSP data for the associated file or null if no TSP
     * data was found
     * @throws \dml_exception On database error
     */
    public function get_tsp_data(): ?\stdClass {
        global $DB;

        $tspdata = $DB->get_record(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
        ]);

        return ($tspdata !== false) ? (object) [
            'server' => $tspdata->server,
            'timecreated' => $tspdata->timecreated,
            'query' => $tspdata->timestampquery,
            'reply' => $tspdata->timestampreply,
        ] : null;
    }

    /**
     * Deletes all stored TSP data for the associated file.
     *
     * @return void
     * @throws \dml_exception On database error
     */
    public function delete_tsp_data(): void {
        global $DB;

        $DB->delete_records(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
        ]);
    }

    /**
     * Issues a TSP timestamp for the associated file
     *
     * @return void
     * @throws \dml_exception On database error
     * @throws \Exception On TSP error
     * @throws \RuntimeException If the associated file has no valid artifact
     */
    public function timestamp(): void {
        global $DB;

        // Check if TSP signing globally is enabled.
        if (!$this->config->tsp_enable) {
            throw new \Exception(get_string('artifact_signing_failed_tsp_disabled', 'local_archiving'));
        }

        // Issue TSP timestamp.
        $tspclient = $this->get_tsp_client();
        $tspdata = $tspclient->sign($this->filehandle->sha256sum);

        // Store TSP data.
        $DB->insert_record(db_table::TSP->value, [
            'filehandleid' => $this->filehandle->id,
            'timecreated' => time(),
            'server' => $tspclient->get_serverurl(),
            'timestampquery' => $tspdata['query'],
            'timestampreply' => $tspdata['reply'],
        ]);
    }

    /**
     * Generates a download URL for the requested virtual TSP file
     *
     * @param string $fileextension File extension of the requested TSP file
     * (TSP_QUERY_FILE_EXTENSION or TSP_REPLY_FILE_EXTENSION)
     * @return ?\moodle_url Download URL for the requested TSP file or null if
     * the requested file name is invalid or no TSP data was found
     * @throws \dml_exception On database error
     */
    protected function get_tsp_file_download_url(string $fileextension): ?\moodle_url {
        // Get TSP data.
        $tspdata = $this->get_tsp_data();
        if (!$tspdata) {
            return null;
        }

        // Validate requested file type.
        switch ($fileextension) {
            case self::TSP_QUERY_FILE_EXTENSION:
            case self::TSP_REPLY_FILE_EXTENSION:
                // Valid TSP file type.
                break;
            default:
                // Invalid TSP file type.
                return null;
        }

        // Generate download URL.
        $job = archive_job::get_by_id($this->filehandle->jobid);
        return \moodle_url::make_pluginfile_url(
            contextid: $job->get_context()->id,
            component: filearea::TSP->get_component(),
            area: filearea::TSP->value,
            itemid: 0,
            pathname: "/{$this->filehandle->id}/",
            filename: "{$this->filehandle->sha256sum}.{$fileextension}",
            forcedownload: true
        );
    }

    /**
     * Generates a download URL for the TSP query file of the associated artifact file
     *
     * @return \moodle_url|null Download URL for the TSP query file or null if no TSP data was found
     * @throws \dml_exception On database error
     */
    public function get_query_download_url(): ?\moodle_url {
        return $this->get_tsp_file_download_url(self::TSP_QUERY_FILE_EXTENSION);
    }

    /**
     * Generates a download URL for the TSP reply file of the associated artifact file
     *
     * @return \moodle_url|null Download URL for the TSP reply file or null if no TSP data was found
     * @throws \dml_exception On database error
     */
    public function get_reply_download_url(): ?\moodle_url {
        return $this->get_tsp_file_download_url(self::TSP_REPLY_FILE_EXTENSION);
    }

    /**
     * Sends a virtual TSP file to the client
     *
     * ATTENTION: This method takes full control over the output buffer and
     * will terminate all further script execution after sending the file!
     *
     * @param string $path Path to the virtual TSP file, according to the pluginfile URL
     * @param string $filename Name of the virtual TSP file to be sent, according to the pluginfile URL
     * @return void None, this method will terminate script execution after sending the file!
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function send_virtual_tsp_file(string $path, string $filename): void {
        // Validate file path and name.
        if (!preg_match('/^\/[0-9]+\/$/', $path)) {
            throw new \moodle_exception($path, 'local_archiving');
        }

        if (!preg_match('/^[a-fA-F0-9]{64}\.(tsq|tsr)$/', $filename)) {
            throw new \moodle_exception('invalid_tsp_file_name', 'local_archiving');
        }

        // Get file handle ID from path.
        $filehandleid = (int) trim($path, '/');
        $filehandle = file_handle::get_by_id($filehandleid);

        // Retrieve TSP data for the file handle.
        $tspmanager = new self($filehandle);
        $tspdata = $tspmanager->get_tsp_data();
        if (!$tspdata) {
            throw new \moodle_exception('tsp_data_not_found_for_file', 'local_archiving');
        }

        // Determine the requested file type and contents.
        switch (pathinfo($filename, PATHINFO_EXTENSION)) {
            case self::TSP_QUERY_FILE_EXTENSION:
                $filecontents = $tspdata->query;
                break;
            case self::TSP_REPLY_FILE_EXTENSION:
                $filecontents = $tspdata->reply;
                break;
            default:
                throw new \moodle_exception('invalid_tsp_file_type', 'local_archiving');
        }

        // Send virtual TSP file to the client.
        \core\session\manager::write_close(); // Unlock session during file serving.
        ob_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, no-transform');
        header('Pragma: no-cache');
        header('Content-Length: '.strlen($filecontents));
        echo $filecontents;
        ob_flush();

        // Do not kill tests.
        if (PHPUNIT_TEST === true) {
            return;
        }

        die;
    }

}
