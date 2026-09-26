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
 * Endpoint for editing admin settings directly via a link from the website
 * administration section.
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

global $OUTPUT, $PAGE;

// Parse expected params.
$action = required_param('action', PARAM_ALPHA);
$wantsurl = required_param('wantsurl', PARAM_LOCALURL);

// Check login and capabilities.
require_login();
$ctx = context_system::instance();
require_capability('moodle/site:config', $ctx);
require_sesskey();

// Setup page.
$PAGE->set_context($ctx);
$PAGE->set_url(new \moodle_url(
    '/local/archiving/admin/manage.php',
    [
        'action' => $action,
        'wantsurl' => $wantsurl,
    ]
));

// Handle actions.
if ($action === 'pluginenable' || $action === 'plugindisable') {
    $plugincomponent = required_param('plugin', PARAM_COMPONENT);

    // Only allow enabling/disabling of archiving plugins, not arbitrary plugins.
    if (!str_starts_with($plugincomponent, 'archiving')) {
        throw new \moodle_exception('nopermissions', 'error', $wantsurl, $plugincomponent);
    }

    $plugin = \core_plugin_manager::instance()->get_plugin_info($plugincomponent);
    if (!$plugin) {
        throw new \moodle_exception('nopermissions', 'error', $wantsurl, $plugincomponent);
    }

    $plugin->enable_plugin($plugin->name, $action === 'pluginenable' ? 1 : 0);
    \core_plugin_manager::reset_caches();
    redirect($wantsurl);
}

// Catch invalid requests / actions.
echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('invalidrequest', 'error'), \core\output\notification::NOTIFY_ERROR);
echo $OUTPUT->continue_button($wantsurl);
echo $OUTPUT->footer();
