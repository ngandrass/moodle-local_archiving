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
 * Moodle fileareas used by this plugin
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Moodle fileareas used by this plugin
 */
enum filearea: string {

    /**
     * Returns the component name for this filearea
     *
     * @return string The component name for this filearea
     */
    public function get_component(): string {
        return 'local_archiving';
    }

    /** @var string Filearea for temporary files */
    case TEMP = 'temp';

    /** @var string Filearea for artifacts during job / task execution */
    case ARTIFACT = 'artifact';

    /** @var string Filearea for caching previously stored artifacts for local access */
    case FILESTORE_CACHE = 'filestorecache';

    /** @var string Filearea for draft files and uploads */
    case DRAFT = 'draft';

}
