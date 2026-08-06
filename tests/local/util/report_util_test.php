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

namespace local_archiving\local\util;


use core\exception\moodle_exception;

/**
 * Tests for the report util class.
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the report util class.
 */
final class report_util_test extends \advanced_testcase {
    /** @var \DOMDocument Shared DOM document for creating test elements */
    private \DOMDocument $doc;

    protected function setUp(): void {
        parent::setUp();
        $this->doc = new \DOMDocument();
    }

    /**
     * Creates an <img> DOMElement with an optional src attribute.
     *
     * @param string|null $src src attribute value, or null to omit the attribute
     * @return \DOMElement
     * @throws \DOMException
     */
    private function make_img(?string $src): \DOMElement {
        $img = $this->doc->createElement('img');
        $this->doc->appendChild($img);
        if ($src !== null) {
            $img->setAttribute('src', $src);
        }
        return $img;
    }

    /**
     * Tests that already-absolute URLs are returned unchanged.
     *
     * @dataProvider ensure_absolute_url_data_provider
     * @covers \local_archiving\local\util\report_util
     *
     * @param string $url Input URL.
     * @param string $base Base URL to resolve relative paths against.
     * @param string $expected Expected absolute URL.
     * @return void
     */
    public function test_ensure_absolute_url(string $url, string $base, string $expected): void {
        $this->assertEquals($expected, report_util::ensure_absolute_url($url, $base));
    }

    /**
     * Data provider for test_ensure_absolute_url.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function ensure_absolute_url_data_provider(): array {
        return [
            'already absolute http'      => ['http://example.com/img.png', 'https://base.com/', 'http://example.com/img.png'],
            'already absolute https'     => ['https://example.com/img.png', 'https://base.com/', 'https://example.com/img.png'],
            'relative path'              => ['img.png', 'https://example.com/path/', 'https://example.com/path/img.png'],
            'relative sub-path'          => ['sub/img.png', 'https://example.com/path/', 'https://example.com/path/sub/img.png'],
            'root-relative path'         => ['/img.png', 'https://example.com/path/', 'https://example.com/img.png'],
            'query string'               => ['?q=1', 'https://example.com/path/', 'https://example.com/path/?q=1'],
            'anchor'                     => ['#section', 'https://example.com/path/', 'https://example.com/path/#section'],
            'parent directory traversal' => ['../img.png', 'https://example.com/dir/sub/', 'https://example.com/dir/img.png'],
            'double parent traversal'    => ['../../img.png', 'https://example.com/a/b/c/', 'https://example.com/a/img.png'],
            'dot-slash prefix'           => ['./img.png', 'https://example.com/path/', 'https://example.com/path/img.png'],
        ];
    }

    /**
     * Tests that an <img> without a src attribute returns false.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     */
    public function test_convert_image_no_src_returns_false(): void {
        $img = $this->make_img(null);
        $this->assertFalse(report_util::convert_image_to_base64($img));
        $this->assertEquals('no source present', $img->getAttribute('x-debug-notice'));
    }

    /**
     * Tests that unsupported image file extensions cause an early return of false.
     *
     * @dataProvider unsupported_image_type_provider
     * @covers \local_archiving\local\util\report_util
     *
     * @param string $src Image src URL with unsupported extension.
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     */
    public function test_convert_image_unsupported_type_returns_false(string $src): void {
        $img = $this->make_img($src);
        $this->assertFalse(report_util::convert_image_to_base64($img));
        $this->assertEquals('image type not allowed', $img->getAttribute('x-debug-notice'));
    }

    /**
     * Data provider for test_convert_image_unsupported_type_returns_false.
     *
     * @return array<string, array{string}>
     */
    public static function unsupported_image_type_provider(): array {
        return [
            'php file'  => ['https://moodle.example.com/file.php'],
            'html file' => ['https://external.com/page.html'],
            'pdf file'  => ['https://external.com/document.pdf'],
            'no extension' => ['https://external.com/noextension'],
        ];
    }

    /**
     * Tests that non-HTTP(S) URL schemes (e.g. local file URIs, FTP) are rejected.
     *
     * @dataProvider non_http_url_provider
     * @covers \local_archiving\local\util\report_util
     *
     * @param string $src Image src with a non-HTTP(S) scheme.
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     */
    public function test_convert_image_non_http_url_returns_false(string $src): void {
        $img = $this->make_img($src);
        $this->assertFalse(report_util::convert_image_to_base64($img));
        $this->assertEquals('not a web URL', $img->getAttribute('x-debug-notice'));
    }

    /**
     * Data provider for test_convert_image_non_http_url_returns_false.
     *
     * @return array<string, array{string}>
     */
    public static function non_http_url_provider(): array {
        return [
            'local file URI with png extension'  => ['file:///etc/secret.png'],
            'local file URI with jpeg extension' => ['file:///var/data/photo.jpg'],
            'ftp URL'                            => ['ftp://files.example.com/img.png'],
        ];
    }

    /**
     * Tests that a valid src attribute is preserved as x-original-source.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     */
    public function test_convert_image_original_source_attribute_is_preserved(): void {
        $src = 'https://moodle.example.com/file.pdf';
        $img = $this->make_img($src);
        report_util::convert_image_to_base64($img);
        $this->assertEquals($src, $img->getAttribute('x-original-source'));
    }

    /**
     * Tests that a pluginfile URL for an existing Moodle file is inlined as base64.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_convert_pluginfile_image_success(): void {
        $this->resetAfterTest();

        $context = \context_system::instance();
        $imgdata = 'FAKE_PNG_IMAGE_DATA';
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_archiving',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.png',
        ], $imgdata);

        $url = new \moodle_url("/pluginfile.php/{$context->id}/local_archiving/unittest/0/test.png");
        $img = $this->make_img($url);

        $this->assertTrue(report_util::convert_image_to_base64($img));
        $this->assertEquals('MOODLE_URL_PLUGINFILE', $img->getAttribute('x-url-type'));
        $this->assertEquals(
            'data:image/png;base64,' . base64_encode($imgdata),
            $img->getAttribute('src')
        );
    }

    /**
     * Tests that a pluginfile URL with a JPEG file is inlined correctly.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \dml_exception
     */
    public function test_convert_pluginfile_image_jpeg_type(): void {
        $this->resetAfterTest();

        $context = \context_system::instance();
        $imgdata = 'FAKE_JPEG_IMAGE_DATA';
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_archiving',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'photo.jpeg',
        ], $imgdata);

        $url = new \moodle_url("/pluginfile.php/{$context->id}/local_archiving/unittest/0/photo.jpeg");
        $img = $this->make_img($url);

        $this->assertTrue(report_util::convert_image_to_base64($img));
        $this->assertStringStartsWith('data:image/jpeg;base64,', $img->getAttribute('src'));
    }

    /**
     * Tests that a pluginfile URL for a file that does not exist in Moodle FS returns false.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     */
    public function test_convert_pluginfile_image_missing_file_returns_false(): void {
        $this->resetAfterTest();

        $context = \context_system::instance();
        $url = new \moodle_url("/pluginfile.php/{$context->id}/local_archiving/unittest/0/nonexistent.png");
        $img = $this->make_img($url);

        $this->assertFalse(report_util::convert_image_to_base64($img));
        $this->assertEquals('moodledata file not found', $img->getAttribute('x-debug-notice'));
    }

    /**
     * Tests URL parameter stripping before pluginfile lookup.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_convert_pluginfile_image_strips_url_params(): void {
        $this->resetAfterTest();

        $context = \context_system::instance();
        $imgdata = 'FAKE_SVG_IMAGE_DATA';
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_archiving',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'icon.svg',
        ], $imgdata);

        $wwwroot = 'https://moodle.example.com';
        $url = "$wwwroot/pluginfile.php/{$context->id}/local_archiving/unittest/0/icon.svg?forcedownload=1&lang=de";
        $img = $this->make_img($url);

        $this->assertTrue(report_util::convert_image_to_base64($img, $wwwroot));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $img->getAttribute('src'));
    }

    /**
     * Tests that a question component pluginfile URL is resolved using the question-specific
     * regex and the correct file is inlined.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    public function test_convert_question_component_pluginfile_success(): void {
        $this->resetAfterTest();

        $context = \context_system::instance();
        $imgdata = 'FAKE_QUESTION_PNG_DATA';
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'question',
            'filearea'  => 'questiontext',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'qimg.png',
        ], $imgdata);

        // URL format for question component: /contextid/question/filearea/qbankid/slot/itemid/filename.
        $url = new \moodle_url("/pluginfile.php/{$context->id}/question/questiontext/1/2/0/qimg.png");
        $img = $this->make_img($url);

        $this->assertTrue(report_util::convert_image_to_base64($img));
        $this->assertEquals(
            'data:image/png;base64,' . base64_encode($imgdata),
            $img->getAttribute('src')
        );
    }

    /**
     * Tests that a question component URL that does not match the secondary (question-specific)
     * regex returns false.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     */
    public function test_convert_question_component_secondary_regex_mismatch_returns_false(): void {
        $this->resetAfterTest();

        // The 'notnumeric' in the itemid slot breaks the secondary question regex (\d+).
        $context = \context_system::instance();
        $url = new \moodle_url("/pluginfile.php/{$context->id}/question/questiontext/1/path/notnumeric/qimg.png");
        $img = $this->make_img($url);

        $this->assertFalse(report_util::convert_image_to_base64($img));
        $this->assertEquals('MOODLE_URL_PLUGINFILE_QUESTION_AND_QTYPE', $img->getAttribute('x-url-type'));
    }

    /**
     * Tests that a STACK plot URL for an existing plot file on disk is inlined as base64.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws \dml_exception
     */
    public function test_convert_stackplot_image_success(): void {
        global $CFG;
        $this->resetAfterTest();

        $plotdir = $CFG->dataroot . '/stack/plots';
        if (!is_dir($plotdir)) {
            mkdir($plotdir, 0777, true);
        }

        $filename = 'testplot_' . uniqid() . '.png';
        $imgdata = 'FAKE_STACK_PLOT_PNG';
        file_put_contents($plotdir . '/' . $filename, $imgdata);

        try {
            $wwwroot = 'https://moodle.example.com';
            $url = "$wwwroot/question/type/stack/plot.php/$filename";
            $img = $this->make_img($url);

            $this->assertTrue(report_util::convert_image_to_base64($img, $wwwroot));
            $this->assertEquals('MOODLE_URL_STACKPLOT', $img->getAttribute('x-url-type'));
            $this->assertEquals(
                'data:image/png;base64,' . base64_encode($imgdata),
                $img->getAttribute('src')
            );
        } finally {
            @unlink($plotdir . '/' . $filename);
        }
    }

    /**
     * Tests that a STACK plot URL pointing to a non-existent file on disk returns false.
     *
     * @covers \local_archiving\local\util\report_util
     * @return void
     * @throws \DOMException
     * @throws moodle_exception
     * @throws \dml_exception
     */
    public function test_convert_stackplot_image_missing_file_returns_false(): void {
        $this->resetAfterTest();

        $url = new \moodle_url("/question/type/stack/plot.php/nonexistent_plot_" . uniqid() . ".png");
        $img = $this->make_img($url);

        $this->assertFalse(report_util::convert_image_to_base64($img));
        $this->assertEquals('stack plot file not readable', $img->getAttribute('x-debug-notice'));
    }
}
