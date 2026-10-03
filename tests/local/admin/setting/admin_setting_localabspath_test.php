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

namespace local_archiving\local\admin\setting;

/**
 * Tests for the admin_setting_localabspath class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the admin_setting_localabspath class.
 */
final class admin_setting_localabspath_test extends \advanced_testcase {
    /**
     * Creates a new setting instance
     *
     * @return admin_setting_localabspath Setting instance
     */
    private function setting(): admin_setting_localabspath {
        return new admin_setting_localabspath(
            'archivingstore_localdir/storage_path',
            'Storage path',
            'Path to store archives in',
            '/var/moodle/archiving'
        );
    }

    /**
     * Tests that invalid paths are rejected
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_localabspath
     * @dataProvider invalid_paths_data_provider
     *
     * @param string $path Path that must be rejected
     * @return void
     * @throws \coding_exception
     */
    public function test_validate_rejects_invalid_paths(string $path): void {
        $this->assertIsString($this->setting()->validate($path), 'The path must be rejected.');
    }

    /**
     * Data provider for test_validate_rejects_invalid_paths
     *
     * @return array[] Test data
     */
    public static function invalid_paths_data_provider(): array {
        global $CFG;

        $data = [
            'Empty path' => [''],
            'Relative path' => ['relative/path'],
            'Path with double dots' => ['/var/archiving/../elsewhere'],
            'Existing file' => [__FILE__],
            'Moodle code directory' => [$CFG->dirroot],
            'Inside Moodle code directory' => [$CFG->dirroot . '/archives'],
            'Filesystem root' => ['/'],
            'Moodledata directory' => [$CFG->dataroot],
            'Parent of Moodledata directory' => [dirname($CFG->dataroot)],
        ];

        // Moodle >= 5.1.
        if (!empty($CFG->root)) {
            $data['Moodle >= 5.1 root directory'] = [$CFG->root];
        }

        return $data;
    }

    /**
     * Tests that suitable paths are accepted and that validation does not touch the file system
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_localabspath
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_validate_accepts_suitable_paths(): void {
        global $CFG;

        // Prepare tempdir.
        $this->resetAfterTest();
        $tempdir = make_request_directory();

        // Existing writable directory must be accepted.
        $this->assertTrue($this->setting()->validate($tempdir), 'An existing writable directory must be accepted.');

        // Sub-dir of an existing writable directory must be accepted, but not created.
        $missing = $tempdir . '/new/child';
        $this->assertTrue($this->setting()->validate($missing), 'A missing directory inside a writable one must be accepted.');
        $this->assertFileDoesNotExist($missing, 'Validation must not create the directory.');

        // Allow sub-dirs of moodledata.
        $this->assertTrue(
            $this->setting()->validate($CFG->dataroot . '/local_archiving_test_archives'),
            'Sub-directories of the data directory are allowed.'
        );
    }

    /**
     * Tests that directories that are not writable, or can not be created, are rejected
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_localabspath
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_validate_rejects_unwritable_directories(): void {
        // Prepare a read-only tempdir.
        $this->resetAfterTest();
        $dir = make_request_directory() . '/readonly';
        mkdir($dir);
        chmod($dir, 0555);

        // Try to validate a sub-folder inside the tempdir. Should fail.
        try {
            if (is_writable($dir)) {
                $this->markTestSkipped('Directories can not be made read-only here (e.g. running as root or on Windows).');
            }

            $this->assertSame(
                get_string('error_localpath_could_not_be_created', 'local_archiving'),
                $this->setting()->validate($dir),
                'An existing read-only directory must be rejected.'
            );
            $this->assertSame(
                get_string('error_localpath_could_not_be_created', 'local_archiving'),
                $this->setting()->validate($dir . '/child'),
                'A directory that can not be created inside of a read-only directory must be rejected.'
            );
        } finally {
            chmod($dir, 0755);
            rmdir($dir);
        }
    }

    /**
     * Tests that saving a new settings value creates the directory.
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_localabspath
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_write_setting_creates_directory(): void {
        $this->resetAfterTest();
        $path = make_request_directory() . '/archives';

        $this->assertSame('', $this->setting()->write_setting($path), 'Saving a valid path must succeed.');
        $this->assertDirectoryExists($path);
        $this->assertSame($path, get_config('archivingstore_localdir', 'storage_path'));
    }

    /**
     * Tests that saving an invalid path neither stores it nor creates anything
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_localabspath
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_write_setting_rejects_invalid_path(): void {
        global $CFG;

        $this->resetAfterTest();
        $path = dirname($CFG->dataroot);

        $this->assertNotSame('', $this->setting()->write_setting($path), 'Saving an invalid path must fail.');
        $this->assertNotSame($path, get_config('archivingstore_localdir', 'storage_path'));
    }
}
