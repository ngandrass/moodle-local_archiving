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
 * Manages reassambly of chunked uploads to its original file.
 */
class file_reassembler {
    /**
     * Reassembles the individual uploaded chunks from the draft file area and stores them as the original file.
     *
     * @param int $contextid
     * @param int $itemid
     * @param string $filepath
     * @param string $originalfilename Name of original file to reasamble.
     * @param array $chunkfilenames Array of chunk filenames to be reasambled.
     * @return stored_file|null
     */
    public static function reassamble_chunked_file(
        int $contextid,
        int $itemid,
        string $filepath,
        string $originalfilename,
        array $chunkfilenames,
    ): ?stored_file {

        // Ensure chunk file names are in order for reasambly.
        sort($chunkfilenames);

        // Create temporary file on disk to append chunks to one by one.
        // This is required because you can not write inside the file storage.
        $temporaryfile = tmpfile();
        $temporaryfilepath = stream_get_meta_data($temporaryfile)['uri']; // See php manual.

        foreach ($chunkfilenames as $i => $chunkfilename) {
            $chunkfile = get_file_storage()->get_file($contextid, 'user', 'draft', $itemid, $filepath, $chunkfilename);
            $chunkfilehandle = $chunkfile->get_content_file_handle(stored_file::FILE_HANDLE_FOPEN);

            // Append chunk files content in mini chunks of 4KB.
            while (!feof($chunkfilehandle)) {
                $buffer = fread($chunkfilehandle, 4096);
                fwrite($temporaryfile, $buffer);
            }
            fclose($chunkfilehandle);

            // Remove appended chunk.
            $chunkfile->delete();
        }

        // Ensure changes are written to disk.
        fflush($temporaryfile);

        // Import temporary file into file storage.
        $fileinfo = [
            'contextid' => $contextid,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => $originalfilename,
        ];
        $originalfile = get_file_storage()->create_file_from_pathname($fileinfo, $temporaryfilepath);

        // Clean up temporaray file by closing its handle.
        fclose($temporaryfile);

        return $originalfile;
    }
}
