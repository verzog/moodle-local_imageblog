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
 * Uninstallation hook.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove artefacts core's uninstall_plugin() cannot know about.
 *
 * Core already drops the plugin tables (install.xml), removes the
 * capabilities, plugin settings, scheduled tasks and all files stored
 * under the local_imageblog component. The one thing it cannot clean up
 * is the custom "Blog author" role bootstrapped by
 * \local_imageblog\local\author_role::ensure(), so delete it here
 * (delete_role() also removes any remaining role assignments).
 *
 * @return bool
 */
function xmldb_local_imageblog_uninstall(): bool {
    global $DB;

    $roleid = $DB->get_field('role', 'id', [
        'shortname' => \local_imageblog\local\author_role::SHORTNAME,
    ]);
    if ($roleid) {
        delete_role((int)$roleid);
    }

    return true;
}
