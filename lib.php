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

/**
 * Legacy lib definitions
 *
 * @package   local_archiving
 * @copyright 2026 Niels Gandraß <niels@gandrass.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_archiving\archive_job;
use local_archiving\file_handle;
use local_archiving\local\type\filearea;
use local_archiving\local\type\storage_tier;
use local_archiving\tsp_manager;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * Inject navigation nodes
 *
 * @param settings_navigation $settingsnav The root node of the settings navigation
 * @param context $ctx Current context
 * @return void
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 */
function local_archiving_extend_settings_navigation(settings_navigation $settingsnav, context $ctx) {
    // Inject archiving overview node into navigation.
    if ($ctx && $ctx instanceof context_course) {
        if (has_capability('local/archiving:view', $ctx)) {
            // Construct new navigation node.
            $url = new moodle_url('/local/archiving/index.php', [
                'courseid' => $ctx->get_course_context()->instanceid,
            ]);
            $node = navigation_node::create(
                get_string('pluginname', 'local_archiving'),
                $url,
                navigation_node::TYPE_SETTING,
                'local_archiving',
                'local_archiving',
                new pix_icon('i/settings', '')
            );

            // Append to course administration node.
            $parentnode = $settingsnav->find('courseadmin', null);
            if ($parentnode) {
                $parentnode->add_node($node);
            }
        }
    }

    // Inject activity archiving node into activity course menu.
    if ($ctx && $ctx instanceof context_module) {
        if (has_capability('local/archiving:view', $ctx)) {
            // Construct new navigation node.
            $url = new moodle_url('/local/archiving/archive.php', [
                'courseid' => $ctx->get_course_context()->instanceid,
                'cmid' => $ctx->instanceid,
            ]);
            $node = navigation_node::create(
                get_string('pluginname', 'local_archiving'),
                $url,
                navigation_node::TYPE_SETTING,
                'local_archiving',
                'local_archiving',
                new pix_icon('i/settings', '')
            );

            // Append to activity course menu.
            $parentnode = $settingsnav->find('modulesettings', null);
            if ($parentnode) {
                $parentnode->add_node($node);
            }
        }
    }
}

/**
 * Serve local_archiving files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 *
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 *
 * @throws coding_exception
 * @throws required_capability_exception
 * @throws moodle_exception
 */
function local_archiving_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Validate filearea.
    $filearea = filearea::tryFrom($filearea);
    switch ($filearea) {
        case filearea::FILESTORE_CACHE:
        case filearea::TSP:
            break;
        default:
            return false;
    }

    // Check permissions.
    require_login($course, false, $cm);
    if (!has_capability('local/archiving:view', $context)) {
        return false;
    }

    // Get remaining file information from $args.
    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';

    // Stop here if we are running unit tests because the following functions send output and stop execution.
    if (defined('PHPUNIT_TEST') && PHPUNIT_TEST === true) {
        return 'PHPUNIT_TEST';
    }

    // @codeCoverageIgnoreStart

    // Catch virtual files.
    if ($filearea == filearea::TSP) {
        try {
            tsp_manager::send_virtual_tsp_file($filepath, $filename);
        } catch (Exception $e) {
            send_header_404();
            throw $e;
        }
    }

    // Try to retrieve and serve file.
    $fs = get_file_storage();
    $file = $fs->get_file(
        contextid: $context->id,
        component: $filearea->get_component(),
        filearea: $filearea->value,
        itemid: $itemid,
        filepath: $filepath,
        filename: $filename
    );

    // Handle cache misses.
    if (!$file || $file->is_directory()) {
        if ($filearea === filearea::FILESTORE_CACHE) {
            // Ensure the file we are looking for still exists.
            try {
                $filehandle = file_handle::get_by_id($itemid);
            } catch (\dml_exception) {
                $filehandle = null;
            }

            if ($filehandle !== null && !$filehandle->deleted) {
                // Transparently copy LOCAL tier files to the cache and serve the copy synchronously.
                if ($filehandle->archivingstore()::get_storage_tier() === storage_tier::LOCAL) {
                    $filehandle->retrieve_file();
                    $file = $fs->get_file(
                        contextid: $context->id,
                        component: $filearea->get_component(),
                        filearea: $filearea->value,
                        itemid: $itemid,
                        filepath: $filepath,
                        filename: $filename
                    );
                } else {
                    // Redirect users for REMOTE tier files that are not yet available in the cache to the fecthing UI.
                    $job = archive_job::get_by_id($filehandle->jobid);
                    redirect(new moodle_url('/local/archiving/fetch.php', [
                        'filehandleid' => $filehandle->id,
                        'contextid' => $job->get_context()->id,
                    ]));
                }
            }
        }

        // The users want's something we can do nothing about ...
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
    }

    // File exists: serve it!
    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;

    // @codeCoverageIgnoreEnd
}
