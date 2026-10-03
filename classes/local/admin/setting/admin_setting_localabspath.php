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

namespace local_archiving\local\admin\setting;


// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

require_once($CFG->libdir . '/adminlib.php'); // @codeCoverageIgnore

/**
 * Custom admin setting for local absolute paths
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_localabspath extends \admin_setting_configtext {
    /**
     * Creates a new instance of this setting
     *
     * @param string $name unique ascii name for setting
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting Default value
     * @param int|null $size default field size
     */
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        $size = null
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW, $size);
    }

    /**
     * Validate data before storing
     *
     * @param string $data data
     * @return mixed true if ok string if error found
     * @throws \coding_exception
     */
    #[\Override]
    public function validate($data) {
        global $CFG;

        // Basic data validation.
        $parentvalidation = parent::validate($data);
        if ($parentvalidation !== true) {
            return $parentvalidation;
        }

        // This is required.
        if (empty($data)) {
            return get_string('required');
        }

        // Ensure we have an absolute path without relative "double dots" in the middle or dots at the end.
        $data = trim($data);
        if (!preg_match('/^(?!\.)(?!.*\.\.)(?!.*\.$)(([A-Z]:\\\\)|(\/)).*/', $data)) {
            return get_string('error_localpath_must_be_absolute', 'local_archiving');
        }

        // Skip the chekcs during installations / upgrades and for the default value, which must be
        // settable during upgradess automatically.
        if (defined('CLI_UPGRADE_RUNNING') || !empty($CFG->upgraderunning) || $data === $this->defaultsetting) {
            return true; // @codeCoverageIgnore
        }

        return $this->validate_location($data) ?? true;
    }

    /**
     * Checks whether the given absolute path is a suitable storage location
     *
     * @param string $path Absolute path to check
     * @return string|null Error message of the first problem that was found or null if the path is suitable
     * @throws \coding_exception
     */
    private function validate_location(string $path): ?string {
        global $CFG;

        // If the path is an existing file, it cannot be used as a storage location.
        if (is_file($path)) {
            return get_string('error_localpath_must_be_directory', 'local_archiving');
        }

        // Resolve the existing ancestors realpath and determine the respective
        // full target realpath for the given path to validate.
        [$existingpath, $fullpath] = self::resolve_path($path);
        if ($fullpath === null) {
            return get_string('error_localpath_could_not_be_created', 'local_archiving');
        }

        // Prevent overlapping with Moodle code directory.
        foreach (array_filter([realpath($CFG->dirroot), isset($CFG->root) ? realpath($CFG->root) : false]) as $codedir) {
            $codedir = self::normalize_path($codedir);
            // The latter condition ensures, that we can not set an archiving path
            // from which a Moodle code directory path is constructable.
            if (str_starts_with($fullpath, $codedir) || str_starts_with($codedir, $fullpath)) {
                return get_string('error_localpath_overlaps_dirroot', 'local_archiving');
            }
        }

        // Prevent writing directly into moodledata. Subdirectories are fine though.
        $dataroot = self::normalize_path(realpath($CFG->dataroot));
        if (str_starts_with($dataroot, $fullpath)) {
            return get_string('error_localpath_overlaps_dataroot', 'local_archiving');
        }

        // Check if the existing path is writable. If so, we are fine!
        return (is_dir($existingpath) && is_writable($existingpath))
            ? null
            : get_string('error_localpath_could_not_be_created', 'local_archiving');
    }

    /**
     * Resolves the closet existing ancestor and the fully resolved path without
     * requiring the full target path itself to exist yet.
     *
     * @param string $path Absolute path to resolve
     * @return array{string, string|null} Closest existing ancestor first,
     * fully resolved target path second or null if the path could not be resolved
     */
    private static function resolve_path(string $path): array {
        $path = self::normalize_path($path);

        // Locate closest existing ancestor.
        $existing = $path;
        while (!file_exists($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }

        // Resolve all symlinks in the existing ancestor.
        $realexisting = realpath($existing);
        if ($realexisting === false) {
            return [$existing, null];
        }

        // Cut away the existing ancestor from given path and append the remainder to the resolved real ancestor path.
        $remainder = substr($path, strlen(self::normalize_path($existing)));
        return [$existing, self::normalize_path($realexisting) . $remainder];
    }

    /**
     * Normalizes the given directory path to use forward slashes and exactly one trailing slash
     *
     * ... may I just mention that file systems using \ as a path separator are a bad idea? ;)
     *
     * @param string $path Given directory path
     * @return string Normalized directory path
     */
    private static function normalize_path(string $path): string {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    /**
     * Validates and stores the setting. Creates the directory if it does not exist yet.
     *
     * @param string $data Path to store
     * @return string Empty string on success, an error message otherwise
     * @throws \coding_exception
     */
    #[\Override]
    public function write_setting($data) {
        global $CFG;

        // Validate before writing.
        $data = trim($data);
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }

        // Create the target directory if missing and we are not within an installation / upgrade.
        $skipcreation = (defined('CLI_UPGRADE_RUNNING') || !empty($CFG->upgraderunning) || $data === $this->defaultsetting);
        if (!$skipcreation && !is_dir($data)) {
            if (!@mkdir($data, $CFG->directorypermissions, true)) {
                return get_string('error_localpath_could_not_be_created', 'local_archiving'); // @codeCoverageIgnore
            }
        }

        // Finally write the setting.
        return parent::write_setting($data);
    }
}
