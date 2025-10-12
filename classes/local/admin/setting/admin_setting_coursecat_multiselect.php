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


/**
 * Course category multi-selection
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_coursecat_multiselect extends \core_admin\local\settings\autocomplete {
    /** @var bool If false, at least one course category must be selected to be able to save */
    protected bool $allowempty;

    /**
     * Constructor
     *
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting'
     * for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param bool $allowempty if false, at least one option must be selected
     * @throws \coding_exception
     */
    public function __construct($name, $visiblename, $description, $allowempty = false) {
        $this->allowempty = $allowempty;

        $choices = [0 => get_string('top')] + \core_course_category::make_categories_list('', 0, ' / ');

        parent::__construct($name, $visiblename, $description, [0], $choices, [
            'manageurl' => '',
            'managetext' => '',
        ]);
    }

    /**
     * Saves setting(s) provided through $data, ensuring that at least one option
     * is selected if $this->allowempty is false
     *
     * @param array $data
     * @throws \coding_exception
     */
    public function write_setting($data) {
        if (!$this->allowempty) {
            if (empty($data) || array_keys($data) == ['xxxxx']) {
                return get_string('error_at_least_one_coursecat_required', 'local_archiving');
            }
        }

        return parent::write_setting($data);
    }
}
