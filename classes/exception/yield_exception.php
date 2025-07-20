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

/**
 * Exception used to signal an early yield to allow freeing resources used in a
 * try ... catch block
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\exception;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Used to signal a yield from within a try ... catch block to allow grouping
 * code to free up resources without having to nest everything inside 12 layers
 * of if statements ...
 *
 * I'll get the developer that brings the Python "with" statement to PHP a beer ...
 */
class yield_exception extends \moodle_exception {

    public function __construct($errorcode = '', $module = '', $link = '', $a = null, $debuginfo = null) {
        $errorcode = $errorcode ?: 'yield';
        $module = $module ?: 'local_archiving';
        parent::__construct($errorcode, $module, $link, $a, $debuginfo);
    }

}
