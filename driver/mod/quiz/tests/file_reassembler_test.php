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
 * Tests for the file_reassembler class
 *
 * @package   archivingmod_quiz
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace archivingmod_quiz;

use archivingmod_quiz\file_reassembler;
use local_archiving\storage;

/**
 * Tests for the file_reassembler class
 */
final class file_reassembler_test extends \advanced_testcase {
    /**
     * Returns the data generator for the archivingmod_quiz plugin
     *
     * @return \archivingmod_quiz_generator The data generator for the archivingmod_quiz plugin
     */
    // phpcs:ignore
    public static function getDataGenerator(): \archivingmod_quiz_generator {
        return parent::getDataGenerator()->get_plugin_generator('archivingmod_quiz');
    }

    /**
     * Test reassembly of individually uploaded files to the file storage
     *
     * @covers \archivingmod_quiz\file_reassembler::reasemble_chunked_file
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_reasamble_chunked_file(): void {
        // Prepare mocks.
        $this->resetAfterTest();
        $userreference = $this->getDataGenerator()->create_user();
        $usercontext = \context_user::instance($userreference->id);
        $originalfilename = 'testfile.tar.gz';
        // NOTE: This SHA256 hash is precomputed based on the per file mock data,
        // defined in `create_draft_file` of the `quiz_archiver_generator` class.
        // Because we concatinate three dummy files, the expected value should be,
        // the SHA256 hash of the dummy data repeated three times.
        $expectedfilehash = 'b6b34e2b8247c3ff64a1cc6793c663bdb7226ffd801859549462bbd20b563f9a';

        // Create mock chunk files.
        $chunkfiles = [
            $this->getDataGenerator()->create_draft_file($originalfilename . '.chunk000000000.bin', userid: $userreference->id),
            $this->getDataGenerator()->create_draft_file($originalfilename . '.chunk000000001.bin', userid: $userreference->id),
            $this->getDataGenerator()->create_draft_file($originalfilename . '.chunk000000002.bin', userid: $userreference->id),
        ];
        foreach ($chunkfiles as $file) {
            $this->assertNotNull($file, 'Failed to create mock chunk file');
        }

        // Try to rassemble individual chunks to the "original file".
        $reassembledfile = file_reassembler::reassemble_chunked_file(
            $usercontext->id,
            0, // Always zero in test cases.
            '/', // Always '/' in test cases.
            $originalfilename,
            count($chunkfiles),
        );
        $this->assertNotNull($reassembledfile, 'File reassembly failed');

        // Check if reassembly creates true byte concatinated file.
        $actualhash = storage::hash_file($reassembledfile);
        $this->assertEquals(
            $expectedfilehash,
            $actualhash,
            'Reassembly of original file is not byte perfect: Mismatch in expected and actual SHA256 file hashes.'
        );

        // Check if individual chunks were cleaned up.
        foreach ($chunkfiles as $file) {
            $this->assertFalse(
                get_file_storage()->get_file(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                ),
                'Missing cleanup of at least one chunk file'
            );
        }
    }
}
