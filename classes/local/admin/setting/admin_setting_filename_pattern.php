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

use local_archiving\storage;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Custom admin setting for filename pattern input fields
 *
 * @codeCoverageIgnore
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_filename_pattern extends \admin_setting_configtext {

    /**
     * @var string[] $allowedvariables Allowed variable names in the filename pattern
     */
    protected array $allowedvariables;

    /**
     * @var string[] $forbiddenchars Forbidden characters in the filename pattern
     */
    protected array $forbiddenchars;

    /**
     * Creates a new instance of this setting
     *
     * @param string $name unique ascii name for setting
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting Default value
     * @param string[] $allowedvariables Variable names to allow during validation
     * @param string[] $forbiddenchars Characters to forbid during validation
     * @param mixed $paramtype int means PARAM_XXX type, string is a allowed format in regex
     * @param int|null $size default field size
     */
    #[\Override]
    public function __construct(
        string $name,
        string $visiblename,
        string $description,
        string $defaultsetting,
        array $allowedvariables,
        array $forbiddenchars,
        mixed $paramtype = PARAM_RAW,
        ?int $size = null
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, $paramtype, $size);

        $this->allowedvariables = $allowedvariables;
        $this->forbiddenchars = $forbiddenchars;
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
        // Basic data validation.
        $parentvalidation = parent::validate($data);
        if ($parentvalidation !== true) {
            return $parentvalidation;
        }

        // Validate filename pattern.
        if (!storage::is_valid_filename_pattern($data, $this->allowedvariables, $this->forbiddenchars)) {
            return get_string('error_invalid_filename_pattern', 'local_archiving');
        }

        return true;
    }

}
