<?php
// Copyright (c) Vernon Apain / Educheckout.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Vernon Apain /
// Educheckout for use with Moodle. It is NOT free software and is NOT
// released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Educheckout. The software is provided "as
// is", without warranty of any kind, express or implied.

/**
 * Uninstallation hook.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
