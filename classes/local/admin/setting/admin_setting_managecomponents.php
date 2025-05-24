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

// @codingStandardsIgnoreLine
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\util\plugin_util;


/**
 * Admin setting for managing sub-plugins of local_archiving
 *
 * This class creates tables for each supported sub-plugin type and allows
 * management of installed plugins. It supports enabling/disabling plugins and
 * provides a convenient way to access their settings.
 *
 * @package local_archiving
 * @copyright 2025 Niels Gandraß <niels@gandrass.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_managecomponents extends \admin_setting {

    /**
     * Constructor
     * @param string $name unique ascii name, either 'mysetting' for settings that in config,
     *                     or 'myplugin/mysetting' for ones in config_plugins.
     */
    #[\Override]
    public function __construct($name) {
        parent::__construct($name, '', '', true);
    }

    /**
     * Returns current value of this setting
     * @return mixed array or string depending on instance, NULL means not set yet
     */
    #[\Override]
    public function get_setting() {
        return true;
    }

    /**
     * Returns default setting if exists
     * @return mixed array or string depending on instance; NULL means no default, user must supply
     */
    #[\Override]
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Store new setting
     *
     * @param mixed $data string or array, must not be NULL
     * @return string empty string if ok, string error message otherwise
     */
    #[\Override]
    public function write_setting($data) {
        return '';
    }

    /**
     * Is setting related to query text - used when searching
     *
     * @param string $query
     * @return bool
     */
    #[\Override]
    public function is_related($query) {
        // TODO: Do we need to implement more logic here?
        return parent::is_related($query);
    }

    /**
     * Return part of form with setting
     * This function should always be overwritten
     *
     * @param mixed $data array or string depending on setting
     * @param string $query
     * @return string
     */
    #[\Override]
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $html = '';

        $html .= $OUTPUT->box_start('generalbox');
        $html .= $OUTPUT->heading(get_string('subplugintype_archivingmod_plural', 'local_archiving'), 3, 'border-0');
        $html .= $this->define_activity_archiving_drivers_table();
        $html .= $OUTPUT->box_end();

        $html .= $OUTPUT->box_start('generalbox');
        $html .= $OUTPUT->heading(get_string('subplugintype_archivingstore_plural', 'local_archiving'), 3, 'border-0');
        $html .= $this->define_storage_drivers_table();
        $html .= $OUTPUT->box_end();

        $html .= $OUTPUT->box_start('generalbox');
        $html .= $OUTPUT->heading(get_string('subplugintype_archivingevent_plural', 'local_archiving'), 3, 'border-0');
        $html .= $this->define_event_connectors_table();
        $html .= $OUTPUT->box_end();

        return $html;
    }

    /**
     * Defines the activity archiving drivers table
     *
     * @return string HTML for the activity archiving drivers table
     * @throws \coding_exception
     */
    protected function define_activity_archiving_drivers_table() {
        global $OUTPUT, $PAGE;

        $activityarchivingdrivers = plugin_util::get_activity_archiving_drivers();

        // Prepare table structure.
        $table = new \html_table();
        $table->id = "{$this->name}table";
        $table->attributes['class'] = 'admintable generaltable';
        $table->head = [
            get_string('name'),
            get_string('version'),
            get_string('enable'),
            get_string('activities'),
            get_string('settings'),
        ];
        $table->colclasses = ['leftalign', 'leftalign', 'centeralign', 'leftalign', 'centeralign'];
        $table->data = [];

        // Add rows to the table.
        foreach ($activityarchivingdrivers as $archivingdriver) {
            // Enable / disable column.
            $enableurl = new \moodle_url('/local/archiving/admin/manage.php', [
                'action' => $archivingdriver['enabled'] ? 'plugindisable' : 'pluginenable',
                'plugin' => $archivingdriver['component'],
                'wantsurl' => $PAGE->url,
            ]);
            if ($archivingdriver['enabled']) {
                $enableicon = $OUTPUT->pix_icon('t/hide', get_string('disable'));
            } else {
                $enableicon = $OUTPUT->pix_icon('t/show', get_string('enable'));
            }

            // Activity pills.
            $badgecolor = $archivingdriver['enabled'] ? 'primary' : 'secondary';
            $activitieshtml = array_reduce($archivingdriver['activities'],
                fn ($res, $activity) => $res.'<span class="badge badge-'.$badgecolor.'">'.$activity.'</span>'
            , '');

            // Settings link.
            $settingsurl = new \moodle_url('/admin/settings.php', ['section' => $archivingdriver['component']]);

            // Build row.
            $row = new \html_table_row([
                $archivingdriver['displayname'],
                "{$archivingdriver['release']} <span class=\"text-muted\">({$archivingdriver['version']})</span>",
                \html_writer::link($enableurl, $enableicon),
                $activitieshtml,
                \html_writer::link($settingsurl, get_string('settings')),
            ]);
            if (!$archivingdriver['enabled']) {
                $row->attributes['class'] = 'dimmed_text';
            }
            $table->data[] = $row;
        }

        return \html_writer::table($table);
    }

    /**
     * Defines the storage drivers table
     *
     * @return string HTML for the storage drivers table
     */
    protected function define_storage_drivers_table() {
        global $OUTPUT, $PAGE;

        $storagedrivers = plugin_util::get_storage_drivers();

        // Prepare table structure.
        $table = new \html_table();
        $table->id = "{$this->name}table";
        $table->attributes['class'] = 'admintable generaltable';
        $table->head = [
            get_string('name'),
            get_string('version'),
            get_string('enable'),
            get_string('storage_tier', 'local_archiving'),
            get_string('settings'),
        ];
        $table->colclasses = ['leftalign', 'leftalign', 'centeralign', 'leftalign'];
        $table->data = [];

        // Add rows to the table.
        foreach ($storagedrivers as $storagedriver) {
            // Enable / disable column.
            $enableurl = new \moodle_url('/local/archiving/admin/manage.php', [
                'action' => $storagedriver['enabled'] ? 'plugindisable' : 'pluginenable',
                'plugin' => $storagedriver['component'],
                'wantsurl' => $PAGE->url,
            ]);
            if ($storagedriver['enabled']) {
                $enableicon = $OUTPUT->pix_icon('t/hide', get_string('disable'));
            } else {
                $enableicon = $OUTPUT->pix_icon('t/show', get_string('enable'));
            }

            // Storage tier.
            $badgecolor = $storagedriver['enabled'] ? $storagedriver['tier']->color() : 'secondary';
            $tierhtml = "
                <span class=\"badge badge-{$badgecolor}\"
                      data-toggle=\"tooltip\" data-placement=\"top\"
                      title=\"{$storagedriver['tier']->help()}\"
                >{$storagedriver['tier']->name()}</span>
            ";

            // Settings link.
            $settingsurl = new \moodle_url('/admin/settings.php', ['section' => $storagedriver['component']]);

            // Build row.
            $row = new \html_table_row([
                $storagedriver['displayname'],
                "{$storagedriver['release']} <span class=\"text-muted\">({$storagedriver['version']})</span>",
                \html_writer::link($enableurl, $enableicon),
                $tierhtml,
                \html_writer::link($settingsurl, get_string('settings')),
            ]);
            if (!$storagedriver['enabled']) {
                $row->attributes['class'] = 'dimmed_text';
            }
            $table->data[] = $row;
        }

        return \html_writer::table($table);
    }

    /**
     * Defines the event connectors table
     *
     * @return string HTML for the event connectors table
     */
    protected function define_event_connectors_table() {
        global $OUTPUT, $PAGE;

        $eventconnectors = plugin_util::get_event_connectors();

        // Prepare table structure.
        $table = new \html_table();
        $table->id = "{$this->name}table";
        $table->attributes['class'] = 'admintable generaltable';
        $table->head = [
            get_string('name'),
            get_string('version'),
            get_string('enable'),
            get_string('settings'),
        ];
        $table->colclasses = ['leftalign', 'leftalign', 'centeralign', 'leftalign'];
        $table->data = [];

        // Add rows to the table.
        foreach ($eventconnectors as $eventconnector) {
            // Enable / disable column.
            $enableurl = new \moodle_url('/local/archiving/admin/manage.php', [
                'action' => $eventconnector['enabled'] ? 'plugindisable' : 'pluginenable',
                'plugin' => $eventconnector['component'],
                'wantsurl' => $PAGE->url,
            ]);
            if ($eventconnector['enabled']) {
                $enableicon = $OUTPUT->pix_icon('t/hide', get_string('disable'));
            } else {
                $enableicon = $OUTPUT->pix_icon('t/show', get_string('enable'));
            }

            // Settings link.
            $settingsurl = new \moodle_url('/admin/settings.php', ['section' => $eventconnector['component']]);

            // Build row.
            $row = new \html_table_row([
                $eventconnector['displayname'],
                "{$eventconnector['release']} <span class=\"text-muted\">({$eventconnector['version']})</span>",
                \html_writer::link($enableurl, $enableicon),
                \html_writer::link($settingsurl, get_string('settings')),
            ]);
            if (!$eventconnector['enabled']) {
                $row->attributes['class'] = 'dimmed_text';
            }
            $table->data[] = $row;
        }

        return \html_writer::table($table);
    }

}
