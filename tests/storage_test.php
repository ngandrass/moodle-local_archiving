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

namespace local_archiving;

use local_archiving\exception\storage_exception;
use local_archiving\type\archive_filename_variable;
use local_archiving\type\filearea;

/**
 * Tests for the storage helper class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the storage helper class.
 */
final class storage_test extends \advanced_testcase {
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
     * Tests checking given patterns for validity as filenames or folder names.
     *
     * @covers \local_archiving\storage
     * @dataProvider is_valid_filename_pattern_data_provider
     *
     * @param string $pattern Pattern to test
     * @param bool $isvalidfilename Whether the pattern is considered valid for filenames
     * @param bool $isvalidfoldername Whether the pattern is considered valid for folder names
     * @return void
     */
    public function test_is_valid_filename_pattern(string $pattern, bool $isvalidfilename, bool $isvalidfoldername): void {
        // Check if the pattern is valid for filenames.
        $this->assertSame(
            $isvalidfilename,
            storage::is_valid_filename_pattern(
                $pattern,
                archive_filename_variable::values(),
                storage::FILENAME_FORBIDDEN_CHARACTERS
            ),
            'Pattern "' . $pattern . '" should be ' . ($isvalidfilename ? 'valid' : 'invalid') . ' for filenames.'
        );

        // Check if the pattern is valid for folder names.
        $this->assertSame(
            $isvalidfoldername,
            storage::is_valid_filename_pattern(
                $pattern,
                archive_filename_variable::values(),
                storage::FOLDERNAME_FORBIDDEN_CHARACTERS
            ),
            'Pattern "' . $pattern . '" should be ' . ($isvalidfoldername ? 'valid' : 'invalid') . ' for folder names.'
        );
    }

    /**
     * Test data provider for test_is_valid_filename_pattern.
     *
     * @return array[] Test cases with valid and invalid patterns.
     */
    public static function is_valid_filename_pattern_data_provider(): array {
        return [
            // Always valid.
            'Default pattern' => [
                'pattern' => 'archive-${courseshortname}-${courseid}-${cmtype}-${cmname}-${cmid}_${date}-${time}',
                'isvalidfilename' => true,
                'isvalidfoldername' => true,
            ],
            'All allowed filename variables' => [
                'pattern' => array_reduce(
                    archive_filename_variable::values(),
                    fn ($carry, $item) => $carry . '${' . $item . '}',
                    ''
                ),
                'isvalidfilename' => true,
                'isvalidfoldername' => true,
            ],

            // Only valid as folder names.
            'Slashes' => [
                'pattern' => 'foo/bar',
                'isvalidfilename' => false,
                'isvalidfoldername' => true,
            ],

            // Invalid in any case.
            'Empty pattern' => [
                'pattern' => '',
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Forbidden characters' => [
                'pattern' => 'quiz-archive: foo!bar',
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Quotes' => [
                'pattern' => 'foo"bar"',
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Less than / greater than' => [
                'pattern' => 'foo<bar>baz',
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Orphaned dollar sign' => [
                'pattern' => 'foo$bar',
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Only invalid characters' => [
                'pattern' => '.!',
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Dot' => [
                'pattern' => '.',
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Null byte' => [
                'pattern' => "\0",
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
            'Valid pattern with null byte' => [
                'pattern' => "foo\0bar",
                'isvalidfilename' => false,
                'isvalidfoldername' => false,
            ],
        ];
    }

    /**
     * Tests sanitization of filenames.
     *
     * @covers       \local_archiving\storage
     * @dataProvider sanitize_filename_data_provider
     *
     * @param string $filename Filename to sanitize
     * @param string $expected Expected sanitized filename
     * @return void
     */
    public function test_sanitize_filename(string $filename, string $expected): void {
        $this->assertSame(
            $expected,
            storage::sanitize_filename($filename),
            'Filename "' . $filename . '" should be changed by sanitization.'
        );
    }

    /**
     * Test data provider for test_sanitize_filename.
     *
     * @return array[] Test cases with filenames to sanitize and whether they
     * should change during sanitization.
     */
    public static function sanitize_filename_data_provider(): array {
        $data = [
            // Valid filenames.
            'Simple filename' => ['filename', 'filename'],
            'Complex filename' => ['foo-bar-baz-1337_0101-2025_01-01-01-01', 'foo-bar-baz-1337_0101-2025_01-01-01-01'],
            'Filename with spaces' => ['file name', 'file name'],

            // Invalid filenames.
            'Too long filename' => [
                str_repeat('a', storage::FILENAME_MAX_LENGTH + 1),
                str_repeat('a', storage::FILENAME_MAX_LENGTH),
            ],
            'Filename with leading spaces' => [' filename', 'filename'],
            'Filename with trailing spaces' => ['filename ', 'filename'],
        ];

        // Add all forbidden filename characters.
        foreach (storage::FILENAME_FORBIDDEN_CHARACTERS as $char) {
            $data['Filename with forbidden character: ' . $char] = [
                'foo' . $char,
                'foo',
            ];
        }

        return $data;
    }

    /**
     * Tests sanitization of folder names.
     *
     * @covers       \local_archiving\storage
     * @dataProvider sanitize_foldername_data_provider
     *
     * @param string $foldername Folder name to sanitize
     * @param string $expected Expected sanitized folder name
     * @return void
     */
    public function test_sanitize_foldername(string $foldername, string $expected): void {
        $this->assertSame(
            $expected,
            storage::sanitize_foldername($foldername),
            'Folder name "' . $foldername . '" should be changed by sanitization.'
        );
    }

    /**
     * Test data provider for test_sanitize_foldername.
     *
     * @return array[] Test cases with folder names to sanitize and whether they
     * should change during sanitization.
     */
    public static function sanitize_foldername_data_provider(): array {
        $data = [
            // Valid folder names.
            'Simple folder name' => ['foldername', 'foldername'],
            'Complex folder name' => ['foo-bar-baz-1337_0101-2025_01-01-01-01', 'foo-bar-baz-1337_0101-2025_01-01-01-01'],
            'Folder name with spaces' => ['folder name', 'folder name'],

            // Invalid foldernames.
            'Too long folder name' => [
                str_repeat('a', storage::FILENAME_MAX_LENGTH + 1),
                str_repeat('a', storage::FILENAME_MAX_LENGTH),
            ],
            'Too long sub-folder name' => [
                'foo/' . str_repeat('a', storage::FILENAME_MAX_LENGTH + 1) . '/subfolder',
                'foo/' . str_repeat('a', storage::FILENAME_MAX_LENGTH) . '/subfolder',
            ],
            'Folder name with leading spaces' => [' foldername', 'foldername'],
            'Folder name with trailing spaces' => ['foldername ', 'foldername'],
            'Folder name with leading slashes' => ['/foldername', 'foldername'],
            'Folder name with trailing slashes' => ['foldername/', 'foldername'],
            'Folder name with leading and trailing slashes' => ['/foldername/', 'foldername'],
            'Folder name with multiple slashes' => ['foo//bar', 'foo/bar'],
            'Folder name with intermediate leading spaces' => ['foo/ bar', 'foo/bar'],
            'Folder name with intermediate trailing spaces' => ['foo /bar', 'foo/bar'],
        ];

        // Add all forbidden foldername characters.
        foreach (storage::FOLDERNAME_FORBIDDEN_CHARACTERS as $char) {
            $data['foldername with forbidden character: ' . $char] = [
                'foo' . $char,
                'foo',
            ];
        }

        return $data;
    }

    /**
     * Tests hashing of Moodle stored files.
     *
     * @covers \local_archiving\storage
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_hash_file(): void {
        // Prepare test files.
        $this->resetAfterTest();
        $files = [
            $this->generator()->create_temp_file(),
            $this->generator()->create_temp_file(),
            $this->generator()->create_temp_file(),
        ];

        // Hash every file and check that all hashes are distinct.
        $hashmap = [];
        foreach ($files as $file) {
            $hash = storage::hash_file($file);
            $this->assertNotEmpty($hash, 'Hash of file should not be empty.');
            $this->assertArrayNotHasKey($hash, $hashmap, 'Hash of file should be unique.');
            $hashmap[$hash] = $file;
        }
    }

    /**
     * Tests hashing of Moodle stored files with an invalid algorithm.
     *
     * @covers \local_archiving\storage
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_hash_file_invalid_algo(): void {
        // Prepare test file.
        $this->resetAfterTest();
        $file = $this->generator()->create_temp_file();

        // Try to hash the file with an invalid algorithm.
        $this->assertNull(
            storage::hash_file($file, 'myultrasafecustomcrypto-lolpleaseneverdothis'),
            'Hashing with an invalid algorithm should return null.'
        );
    }

    /**
     * Tests the validation of SHA256 checksums.
     *
     * @covers \local_archiving\storage
     * @dataProvider is_valid_sha256sum_data_provider
     *
     * @param string $sample Sample string to check as SHA256 checksum
     * @param bool $isvalid Whether the sample should be considered a valid SHA256 checksum
     * @return void
     */
    public function test_is_valid_sha256sum(string $sample, bool $isvalid): void {
        $this->assertSame(
            $isvalid,
            storage::is_valid_sha256sum($sample),
            'SHA256 checksum "' . $sample . '" should be considered ' . ($isvalid ? 'valid' : 'invalid') . '.'
        );
    }

    /**
     * Test data provider for test_is_valid_sha256sum.
     *
     * @return array[] Test cases with SHA256 checksums to test and whether they
     * should be considered valid.
     */
    public static function is_valid_sha256sum_data_provider(): array {
        return [
            'Valid SHA256 checksum' => [
                '64ec88ca00b268e5ba1a35678a1b5316d212f4f366b2477232534a8aeca37f3c',
                true,
            ],
            'Invalid SHA256 checksum (too short)' => [
                '64ec88ca00b268e5ba1a35678a1b5316d212f4f366b2477232534a8aeca37f3',
                false,
            ],
            'Invalid SHA256 checksum (too long)' => [
                '64ec88ca00b268e5ba1a35678a1b5316d212f4f366b2477232534a8aeca37f3c0',
                false,
            ],
            'Invalid SHA256 checksum (non-hex characters)' => [
                'h4ec88ca00b268e5ba1a35678a1b5316d212f4f366b2477232534a8aeca37f3c',
                false,
            ],
            'Empty string' => ['', false],
        ];
    }

    /**
     * Tests calculating usage statistics for different storage drivers.
     *
     * @covers \local_archiving\storage
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_calculate_archivingstore_stats(): void {
        // Prepare fake stats.
        $this->resetAfterTest();
        $this->generator()->create_file_handle(['archivingstorename' => 'localdir', 'jobid' => 1, 'filesize' => 100]);
        $this->generator()->create_file_handle(['archivingstorename' => 'localdir', 'jobid' => 1, 'filesize' => 200]);
        $this->generator()->create_file_handle(['archivingstorename' => 'moodle', 'jobid' => 2, 'filesize' => 1024]);

        // Get stats and verify them.
        $stats = storage::calculate_archivingstore_stats('localdir');
        $this->assertEquals(300, $stats->usagebytes, '[localdir] Usage bytes should does not match.');
        $this->assertEquals(2, $stats->filecount, '[localdir] File count should not match.');
        $this->assertEquals(1, $stats->jobcount, '[localdir] Job count should not match.');

        $stats = storage::calculate_archivingstore_stats('moodle');
        $this->assertEquals(1024, $stats->usagebytes, '[moodle] Usage bytes should does not match.');
        $this->assertEquals(1, $stats->filecount, '[moodle] File count should not match.');
        $this->assertEquals(1, $stats->jobcount, '[moodle] Job count should not match.');
    }

    /**
     * Tests checking if a directory is empty.
     *
     * @covers \local_archiving\storage
     *
     * @return void
     */
    public function test_is_dir_empty(): void {
        $this->resetAfterTest();

        // Check if a non-existing directory is considered empty.
        $this->assertFalse(
            storage::is_dir_empty('/this/should/be/an/absolutely/non/existing/directory'),
            'Non-existing directory should not be considered empty.'
        );

        // Create a temporary directory and check if it is empty.
        $tempdir = make_temp_directory('local_archiving_test_' . time());
        $this->assertNotEmpty($tempdir, 'Temporary directory should be created successfully.');
        $this->assertTrue(storage::is_dir_empty($tempdir), 'Freshly created directory should be considered empty.');

        // Create a file in the temporary directory and check if it is still considered empty.
        $tempfile = $tempdir . '/testfile.txt';
        file_put_contents($tempfile, 'Test content');
        $this->assertFalse(storage::is_dir_empty($tempdir), 'Directory with a file should not be considered empty.');

        // Remove file and check again.
        unlink($tempfile);
        $this->assertTrue(storage::is_dir_empty($tempdir), 'Directory should be considered empty after removing the file.');

        // Cleanup.
        rmdir($tempdir);
    }
}
