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

// phpcs:disable moodle.Commenting.InlineComment.DocBlock

/**
 * Moodle fileareas used by this plugin
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Moodle fileareas used by this plugin
 */
enum filearea: string {

    /** @var string Filearea for temporary files */
    case TEMP = 'temp';

    /** @var string Filearea for artifacts during job / task execution */
    case ARTIFACT = 'artifact';

    /** @var string Filearea for caching previously stored artifacts for local access */
    case FILESTORE_CACHE = 'filestorecache';

    /** @var string Filearea for draft files and uploads */
    case DRAFT = 'draft';

    /** @var string Virtual filearea for TSP data */
    case TSP = 'tsp';

    /**
     * Returns the component name for this filearea
     *
     * @return string The component name for this filearea
     */
    public function get_component(): string {
        return 'local_archiving';
    }

    /**
     * Determines whether this filearea is virtual or not.
     *
     * Virtual fileareas do not contain actulal files but serve content that is
     * either stored in the database or generated on-the-fly. Therefore, "files"
     * within virtual fileareas are never to be found on the local filesystem.
     *
     * @return bool True, if this filearea is virtual, false otherwise
     */
    public function is_virtual(): bool {
        return $this === self::TSP;
    }

}
