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

namespace local_archiving\local\admin\setting;

/**
 * Tests for the admin_setting_managecomponents class.
 *
 * @package   local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Tests for the admin_setting_managecomponents class.
 */
final class admin_setting_managecomponents_test extends \advanced_testcase {

    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
        global $PAGE;
        $PAGE->set_url('/');

        parent::setUp();
    }

    /**
     * Helper to get the test data generator for local_archiving
     *
     * @return \local_archiving_generator
     */
    private function generator(): \local_archiving_generator {
        /** @var \local_archiving_generator */ // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        return self::getDataGenerator()->get_plugin_generator('local_archiving');
    }

    /**
     * Tests that the setting can be instantiated and contains all available subplugin types.
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_managecomponents
     *
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     */
    public function test_definition(): void {
        // Generate output for setting component.
        $setting = new admin_setting_managecomponents('local_archiving/components');
        $html = $setting->output_html(null);

        // Verify that all subplugin types are present.
        $subplugins = \core_plugin_manager::instance()->get_subplugins_of_plugin('local_archiving');
        foreach ($subplugins as $subplugin) {
            $this->assertStringContainsString(
                get_string("subplugintype_{$subplugin->type}_plural", 'local_archiving'),
                $html,
                "The subplugin type '{$subplugin->type}' is not present in the setting output."
            );
            $this->assertStringContainsString(
                get_string("manage_components_{$subplugin->type}_desc", 'local_archiving'),
                $html,
                "The subplugin type description for '{$subplugin->type}' is not present in the setting output."
            );
        }
    }

    /**
     * Tests that the setting does not store any value and always returns true.
     *
     * @covers \local_archiving\local\admin\setting\admin_setting_managecomponents
     *
     * @return void
     * @throws \dml_exception
     */
    public function test_no_backed_setting_value(): void {
        $setting = new admin_setting_managecomponents('local_archiving/testcomponentssetting');
        $this->assertTrue($setting->get_setting(), 'The setting should always return true.');
        $this->assertTrue($setting->get_defaultsetting(), 'The default setting should always be true.');
        $this->assertSame(
            '',
            $setting->write_setting('foo bar baz'),
            'Writing a setting value should always return an empty string.'
        );
        $this->assertEmpty(
            get_config('local_archiving', 'testcomponentssetting'),
            'No setting value should be stored in the config table.'
        );
    }

    /**
     * Tests the is_related method with various queries.
     *
     * @dataProvider is_related_data_provider
     * @covers \local_archiving\local\admin\setting\admin_setting_managecomponents::is_related
     *
     * @param string $query The query string to test
     * @param bool $isrelated Expected result indicating if the query is related
     * @return void
     * @throws \coding_exception
     */
    public function test_is_related(string $query, bool $isrelated): void {
        $setting = new admin_setting_managecomponents('local_archiving/components');
        $this->assertSame(
            $isrelated,
            $setting->is_related($query),
            "The query '$query' should " . ($isrelated ? '' : 'not ') . 'be related.'
        );
    }

    /**
     * Data provider for test_is_related.
     *
     * @return array <string, array{string, bool}> List of test cases with query and expected result
     */
    public static function is_related_data_provider(): array {
        return [
            'Related: archiving' => ['archiving', true],
            'Related: archivingmod' => ['archivingmod', true],
            'Related: archivingstore' => ['archivingstore', true],
            'Related: archivingevent' => ['archivingevent', true],
            'Related: archivingtrigger' => ['archivingtrigger', true],
            'Unrelated: savepdf' => ['savepdf', false],
            'Unrelated: admin' => ['admin', false],
            'Unrelated: course' => ['course', false],
            'Unrelated: mod' => ['mod', false],
        ];
    }

}
