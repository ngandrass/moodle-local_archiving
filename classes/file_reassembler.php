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
 * @package   archivingmod_quiz
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;

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
     * @param int $contextid
     * @param int $itemid
     * @param string $filepath
     * @param string $originalfilename Name of original file to reasamble.
     * @param int $artifactcount Number of chunks original file was split into.
     * @return stored_file|null
     * @throws \file_exception If chunk data can not be appended while reassembly.
     */
    public static function reassemble_chunked_file(
        int $contextid,
        int $itemid,
        string $filepath,
        string $originalfilename,
        int $artifactcount,
    ): ?stored_file {

        // Construct list of expected chunk file names.
        $chunkfilenames = array_map(
            fn ($x) => sprintf('%s.chunk%09d.bin', $originalfilename, $x),
            range(0, $artifactcount - 1),
        );

        // Create temporary file on disk to append chunks to one by one.
        // This is required because you can not write inside the file storage.
        $temporaryfile = tmpfile();
        $temporaryfilepath = stream_get_meta_data($temporaryfile)['uri']; // See php manual.

        foreach ($chunkfilenames as $i => $chunkfilename) {
            $chunkfile = get_file_storage()->get_file($contextid, 'user', 'draft', $itemid, $filepath, $chunkfilename);
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
                throw $e;
            } finally {
                fclose($chunkfilehandle);
            }

            // Remove appended chunk.
            $chunkfile->delete();
        }

        // Ensure changes are written to disk.
        fflush($temporaryfile);

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
            // Clean up temporaray file by closing its handle.
            fclose($temporaryfile);
        }

        return $originalfile;
    }
}
