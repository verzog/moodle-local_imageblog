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
 * "Blog author" custom role bootstrapping.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\local;

/**
 * Helpers for the custom "Blog author" role this plugin uses to delegate
 * publish + edit-any rights to non-admin users from a dedicated admin UI.
 */
class author_role {
    /** @var string Shortname for the custom role. */
    const SHORTNAME = 'local_imageblog_author';

    /**
     * Capabilities granted at system context to the blog-author role.
     *
     * NOTE: editanypost is intentionally absent — blog authors edit and
     * publish only their own posts. Site managers (or anyone else with
     * editanypost via a custom role) override that scope.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return [
            'local/imageblog:view',
            'local/imageblog:createpost',
            'local/imageblog:publishpost',
            // File picker repository caps. The role is created with no
            // archetype, so it inherits none of the repository/*:view caps
            // that standard archetypes pick up from each subplugin's
            // access.php. Without these the file picker throws
            // 'nopermissiontoaccess' the moment it opens, because its
            // default tab (typically Recent or Upload) fails its capability
            // check before the user can click anything.
            'repository/upload:view',
            'repository/user:view',
            'repository/recent:view',
            'repository/url:view',
            'repository/local:view',
            'repository/areafiles:view',
        ];
    }

    /**
     * Capabilities the role used to grant but should no longer carry. Used by
     * the upgrade step to scrub the role on existing installs.
     *
     * @return string[]
     */
    public static function revoked_capabilities(): array {
        return [
            'local/imageblog:editanypost',
        ];
    }

    /**
     * Role id of the blog-author role, or null if it doesn't exist yet.
     *
     * @return int|null
     */
    public static function get_roleid(): ?int {
        global $DB;
        $id = $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
        return $id ? (int)$id : null;
    }

    /**
     * Create the role if it doesn't already exist and grant its capabilities
     * at system context. Safe to call on every upgrade.
     *
     * @return int Role id.
     */
    public static function ensure(): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/accesslib.php');

        $roleid = self::get_roleid();
        if (!$roleid) {
            $roleid = create_role(
                get_string('author_role_name', 'local_imageblog'),
                self::SHORTNAME,
                get_string('author_role_desc', 'local_imageblog'),
                ''
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        } else {
            // Keep the existing role's name and description in step with the
            // current lang strings, so corrected copy (e.g. the scope of the
            // role) reaches sites where the role was created by an older
            // version. Safe to run on every upgrade.
            $DB->update_record('role', (object)[
                'id'          => $roleid,
                'name'        => get_string('author_role_name', 'local_imageblog'),
                'description' => get_string('author_role_desc', 'local_imageblog'),
            ]);
        }

        $syscontext = \context_system::instance();
        foreach (self::capabilities() as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
        foreach (self::revoked_capabilities() as $capability) {
            unassign_capability($capability, $roleid, $syscontext->id);
        }

        return $roleid;
    }
}
