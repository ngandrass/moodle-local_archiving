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
 * Supported paper formats when archiving to PDF
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

use local_archiving\trait\enum_listable;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Supported paper formats when archiving to PDF
 */
enum paper_format: string {
    use enum_listable;

    /** @var string DIN A0 paper format */
    case DIN_A0 = 'A0';

    /** @var string DIN A1 paper format */
    case DIN_A1 = 'A1';

    /** @var string DIN A2 paper format */
    case DIN_A2 = 'A2';

    /** @var string DIN A3 paper format */
    case DIN_A3 = 'A3';

    /** @var string DIN A4 paper format */
    case DIN_A4 = 'A4';

    /** @var string DIN A5 paper format */
    case DIN_A5 = 'A5';

    /** @var string DIN A6 paper format */
    case DIN_A6 = 'A6';

    /** @var string US Letter paper format */
    case LETTER = 'Letter';

    /** @var string US Legal paper format */
    case LEGAL = 'Legal';

    /** @var string US Tabloid paper format */
    case TABLOID = 'Tabloid';

    /** @var string US Ledger paper format */
    case LEDGER = 'Ledger';

}
