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
 * Utility functions for rendering self-contained HTML reports
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\local\util;

use curl;
use local_archiving\local\type\image_type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Utility functions for rendering self-contained HTML reports
 */
class report_util {
    // @codingStandardsIgnoreStart
    /** @var string Regex for URLs of qtype_stack plots */
    protected const REGEX_MOODLE_URL_STACKPLOT = '/^(?P<wwwroot>https?:\/\/.+)?(\/question\/type\/stack\/plot\.php\/)(?P<filename>[^\/\#\?\&]+\.(png|svg))$/m';

    /** @var string Regex for Moodle file API URLs */
    protected const REGEX_MOODLE_URL_PLUGINFILE = '/^(?P<wwwroot>https?:\/\/.+)?(\/pluginfile\.php)(?P<fullpath>\/(?P<contextid>[^\/]+)\/(?P<component>[^\/]+)\/(?P<filearea>[^\/]+)(\/(?P<itemid>\d+))?(?P<args>(\/[^\/]+)*)\/(?P<filename>[^\/\?\&\#]+))$/m';

    /** @var string Regex for Moodle file API URLs of specific types: component=(question|qtype_.*) */
    protected const REGEX_MOODLE_URL_PLUGINFILE_QUESTION_AND_QTYPE = '/^(?P<wwwroot>https?:\/\/.+)?(\/pluginfile\.php)(?P<fullpath>\/(?P<contextid>[^\/]+)\/(?P<component>[^\/]+)\/(?P<filearea>[^\/]+)\/(?P<questionbank_id>[^\/]+)\/(?P<question_slot>[^\/]+)\/(?P<itemid>\d+)\/(?P<filename>[^\/\?\&\#]+))$/m';

    /** @var string Regex for Moodle theme image files */
    protected const REGEX_MOODLE_URL_THEME_IMAGE = '/^(?P<wwwroot>https?:\/\/.+)?(\/theme\/image\.php\/)(?P<themename>[^\/]+)\/(?P<component>[^\/]+)\/(?P<rev>[^\/]+)\/(?P<image>.+)$/m';
    // @codingStandardsIgnoreEnd

    /**
     * Tries to download and inline images of <img> tags with src attributes as base64 encoded strings. Replacement
     * happens in-place.
     *
     * @param \DOMElement $img The <img> element to process
     * @param string|null $wwwroot Internal Moodle base URL to use instead of $CFG->wwwroot, or null to use $CFG->wwwroot
     * @return bool true on success
     * @throws \dml_exception
     */
    public static function convert_image_to_base64(\DOMElement $img, ?string $wwwroot = null): bool {
        global $CFG;

        // Only process images with src attribute.
        if (!$img->getAttribute('src')) {
            $img->setAttribute('x-debug-notice', 'no source present');
            return false;
        } else {
            $img->setAttribute('x-original-source', $img->getAttribute('src'));
        }

        // Remove any parameters and anchors from URL.
        $imgsrc = preg_replace('/^([^\?\&\#]+).*$/', '${1}', $img->getAttribute('src'));

        // Convert relative URLs to absolute URLs.
        $moodlebaseurl = rtrim($wwwroot ?: $CFG->wwwroot, '/') . '/';
        if ($wwwroot) {
            $imgsrc = str_replace(rtrim($CFG->wwwroot, '/'), rtrim($wwwroot, '/'), $imgsrc);
        }
        $imgsrcurl = self::ensure_absolute_url($imgsrc, $moodlebaseurl);

        // Make sure to only process web URLs and nothing that somehow remained a valid local filepath.
        if (!str_starts_with($imgsrcurl, "http")) { // Yes, this includes https as well ;).
            $img->setAttribute('x-debug-notice', 'not a web URL');
            return false;
        }

        // Only process allowed image types.
        $imgext = strtolower(pathinfo($imgsrcurl, PATHINFO_EXTENSION));
        $imgtype = image_type::from_extension($imgext);
        if (!$imgtype) {
            // Edge case: Moodle theme images must not always contain extensions.
            if (!preg_match(self::REGEX_MOODLE_URL_THEME_IMAGE, $imgsrcurl)) {
                $img->setAttribute('x-debug-notice', 'image type not allowed');
                return false;
            }
        }

        // Try to get image content based on link type.
        $regexmatches = null;
        $imgdata = null;
        $imgmime = $imgtype?->mimetype();

        // Handle special internal URLs first.
        $isinternalurl = str_starts_with($imgsrcurl, $moodlebaseurl);
        if ($isinternalurl) {
            if (preg_match(self::REGEX_MOODLE_URL_PLUGINFILE, $imgsrcurl, $regexmatches)) {
                // Link type: Moodle pluginfile URL.
                $img->setAttribute('x-url-type', 'MOODLE_URL_PLUGINFILE');

                // Edge case: question / qtype files follow another pattern,
                // inserting questionbank_id and question_slot after filearea.
                if ($regexmatches['component'] == 'question' || strpos($regexmatches['component'], 'qtype_') === 0) {
                    $regexmatches = null;
                    if (!preg_match(self::REGEX_MOODLE_URL_PLUGINFILE_QUESTION_AND_QTYPE, $imgsrcurl, $regexmatches)) {
                        $img->setAttribute('x-url-type', 'MOODLE_URL_PLUGINFILE_QUESTION_AND_QTYPE');
                        return false;
                    }
                }

                // Decode RFC 3986 URL escaped sequences.
                $regexmatches['filename'] = urldecode($regexmatches['filename']);

                // Get file content via Moodle File API.
                $fs = get_file_storage();
                $file = $fs->get_file(
                    $regexmatches['contextid'],
                    $regexmatches['component'],
                    $regexmatches['filearea'],
                    !empty($regexmatches['itemid']) ? $regexmatches['itemid'] : 0,
                    '/', // Dirty simplification but works for now *sigh*.
                    $regexmatches['filename'],
                );

                if (!$file) {
                    $img->setAttribute('x-debug-notice', 'moodledata file not found');
                    return false;
                }
                $imgdata = $file->get_content();
            } else if (preg_match(self::REGEX_MOODLE_URL_STACKPLOT, $imgsrcurl, $regexmatches)) {
                // Link type: qtype_stack plotfile.
                $img->setAttribute('x-url-type', 'MOODLE_URL_STACKPLOT');

                // Decode RFC 3986 URL escaped sequences.
                $regexmatches['filename'] = urldecode($regexmatches['filename']);

                // Get STACK plot file from disk.
                $filename = $CFG->dataroot . '/stack/plots/' . clean_filename($regexmatches['filename']);
                if (!is_readable($filename)) {
                    $img->setAttribute('x-debug-notice', 'stack plot file not readable');
                    return false;
                }
                $imgdata = file_get_contents($filename);
            } else {
                $img->setAttribute('x-debug-internal-url-without-handler', '');
            }
        }

        // Fall back to generic URL handling if image data not already set by internal handling routines.
        if ($imgdata === null) {
            if (preg_match(self::REGEX_MOODLE_URL_THEME_IMAGE, $imgsrcurl)) {
                // Link type: Moodle theme image.
                // We should be able to download there images using a simple HTTP request.
                // Accessing them directly from disk is a little more complicated due to
                // caching and other logic (see: /theme/image.php).
                // Let's try to keep it this way until we encounter explicit problems.
                $img->setAttribute('x-url-type', 'MOODLE_URL_THEME_IMAGE');
            } else {
                // Link type: Generic.
                $img->setAttribute('x-url-type', 'GENERIC');
            }

            // No special local file access. Try to download via HTTP request.
            $c = new curl(['ignoresecurity' => $isinternalurl]);
            $imgdata = $c->get($imgsrcurl);  // Curl handle automatically closed.
            if ($c->get_info()['http_code'] !== 200 || $imgdata === false) {
                $img->setAttribute('x-debug-more', $imgdata);
                $img->setAttribute('x-debug-notice', 'HTTP request failed');
                return false;
            }

            // Check if we need to detect mime type from response headers.
            if (!$imgmime) {
                $imgmime = $c->get_info()['content_type'];
                if (!image_type::from_mimetype($imgmime)) {
                    $img->setAttribute('x-debug-notice', 'image type from response header is not allowed');
                    return false;
                }
            }
        }

        // Encode and replace image if present.
        if (!$imgdata) {
            $img->setAttribute('x-debug-notice', 'no image data');
            return false;
        }
        $imgbase64 = base64_encode($imgdata);
        $img->setAttribute('src', 'data:' . $imgmime . ';base64,' . $imgbase64);

        return true;
    }

    /**
     * Takes any URL and ensures that it will become an absolute URL. Relative
     * URLs will be prefixed with $base. Already absolute URLs will be returned
     * as they are.
     *
     * @param string $url URL to ensure to be absolute
     * @param string $base Base to prepend to relative URLs
     * @return string Absolute URL
     */
    public static function ensure_absolute_url(string $url, string $base): string {
        // Return if already absolute URL.
        if (parse_url($url, PHP_URL_SCHEME) != '') {
            return $url;
        }

        // Queries and anchors.
        if ($url[0] == '#' || $url[0] == '?') {
            return $base . $url;
        }

        // Parse base URL and convert to local variables: $scheme, $host, $path.
        $urlparsed = parse_url($base);
        $scheme = $urlparsed['scheme'];
        $host = $urlparsed['host'];
        $path = $urlparsed['path'];

        // Remove non-directory element from path.
        $path = preg_replace('#/[^/]*$#', '', $path);

        // Destroy path if relative url points to root.
        if ($url[0] == '/') {
            $path = '';
        }

        // Dirty absolute URL.
        $abs = "$host$path/$url";

        // Replace '//' or '/./' or '/foo/../' with '/'.
        $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {
            continue;
        }

        // Absolute URL is ready!
        return $scheme . '://' . $abs;
    }
}
