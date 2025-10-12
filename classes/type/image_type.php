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
 * Supported image types with their file extensions and mime types
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Supported image types with their file extensions and mime types
 */
enum image_type {
    case PNG;
    case JPEG;
    case SVG;
    case GIF;
    case WEBP;
    case BMP;
    case ICO;
    case TIFF;

    /**
     * Returns the mime type of this image type
     *
     * @return string The mime type of this image type
     */
    public function mimetype(): string {
        return match ($this) {
            self::PNG => 'image/png',
            self::JPEG => 'image/jpeg',
            self::SVG => 'image/svg+xml',
            self::GIF => 'image/gif',
            self::WEBP => 'image/webp',
            self::BMP => 'image/bmp',
            self::ICO => 'image/x-icon',
            self::TIFF => 'image/tiff',
        };
    }

    /**
     * Returns the suggested file extension for this image type
     *
     * @return string The suggested file extension for this image type
     */
    public function extension(): string {
        return match ($this) {
            self::PNG => 'png',
            self::JPEG => 'jpg',
            self::SVG => 'svg',
            self::GIF => 'gif',
            self::WEBP => 'webp',
            self::BMP => 'bmp',
            self::ICO => 'ico',
            self::TIFF => 'tiff',
        };
    }

    /**
     * Returns the image type for the given file extension
     *
     * @param string $extension The file extension to check
     * @return self|null The image type for the given file extension, or null if not found
     */
    public static function from_extension(string $extension): ?self {
        // Special case for JPEG long extension.
        if ($extension === 'jpeg') {
            return self::JPEG;
        }

        // Default case for all files with only a single expected extension.
        foreach (self::cases() as $imgtype) {
            if ($imgtype->extension() === $extension) {
                return $imgtype;
            }
        }

        return null;
    }

    /**
     * Returns the image type for the given mime type
     *
     * @param string $mimetype The mime type to check
     * @return self|null The image type for the given mime type, or null if not found
     */
    public static function from_mimetype(string $mimetype): ?self {
        foreach (self::cases() as $imgtype) {
            if ($imgtype->mimetype() === $mimetype) {
                return $imgtype;
            }
        }
        return null;
    }
}
