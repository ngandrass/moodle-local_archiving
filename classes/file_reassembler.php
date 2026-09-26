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
 * This file defines the file_reassembler class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving;

use stored_file;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

/**
 * Manages reassembly of chunked uploads to its original file.
 */
class file_reassembler {
    /**
     * Reassembles the individual uploaded chunks from the draft file area and stores them as the original file.
     *
     * @param int $contextid ID of the Moodle context the target file chunks are part of
     * @param int $itemid Item ID of the target file chunks
     * @param string $filepath File path of the target file chunks
     * @param string $originalfilename Name of original file to reasamble.
     * @param int $artifactcount Number of chunks original file was split into.
     * @return stored_file|null Reassembled file or null if one of the chunks is missing
     * @throws \coding_exception If the number of chunks is invalid.
     * @throws \file_exception If chunk data can not be appended while reassembly.
     */
    public static function reassemble_chunked_file(
        int $contextid,
        int $itemid,
        string $filepath,
        string $originalfilename,
        int $artifactcount,
    ): ?stored_file {
        if ($artifactcount < 1) {
            throw new \coding_exception('The number of chunks must be at least 1');
        }

        // Create temporary file on disk to append chunks to one by one.
        // This is required because you can not write inside the file storage.
        $temporarydirectory = make_request_directory();
        $temporaryfilepath = tempnam($temporarydirectory, 'reassembly');
        $temporaryfile = fopen($temporaryfilepath, 'w');

        for ($i = 0; $i < $artifactcount; $i++) {
            $chunkfilename = sprintf('%s.chunk%09d.bin', $originalfilename, $i);
            $chunkfile = get_file_storage()->get_file($contextid, 'user', 'draft', $itemid, $filepath, $chunkfilename);

            // A missing chunk is fatal. Clean up and bail out.
            if (!$chunkfile) {
                fclose($temporaryfile);
                unlink($temporaryfilepath);
                return null;
            }

            $chunkfilehandle = $chunkfile->get_content_file_handle(stored_file::FILE_HANDLE_FOPEN);

            // Append chunk files content in mini chunks of 4KB.
            try {
                while (!feof($chunkfilehandle)) {
                    $buffer = fread($chunkfilehandle, 4096);
                    $writesuccess = fwrite($temporaryfile, $buffer);
                    if (is_bool($writesuccess) && !$writesuccess) {
                        throw new \file_exception('Could not write to temporary reassembly file while dechunking. Aborting');
                    }
                }
            } catch (\file_exception $e) {
                // Clean up temporary file and rethrow fatal exception.
                fclose($temporaryfile);
                unlink($temporaryfilepath);
                throw $e;
            } finally {
                fclose($chunkfilehandle);
            }

            // Remove appended chunk.
            $chunkfile->delete();
        }

        // Ensure changes are written to disk.
        fflush($temporaryfile);
        fclose($temporaryfile);

        // Import temporary file into file storage.
        $originalfile = null;
        try {
            $fileinfo = [
                'contextid' => $contextid,
                'component' => 'user',
                'filearea'  => 'draft',
                'itemid'    => $itemid,
                'filepath'  => $filepath,
                'filename'  => $originalfilename,
            ];
            $originalfile = get_file_storage()->create_file_from_pathname($fileinfo, $temporaryfilepath);
        } finally {
            // Clean up temporaray file.
            unlink($temporaryfilepath);
        }

        return $originalfile;
    }
}
