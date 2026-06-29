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
 * Installation hook: seed default taxonomy rows so the plugin is usable
 * out of the box.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seed default categories, subcategories, tags and difficulty levels.
 *
 * @return bool
 */
function xmldb_local_imageblog_install(): bool {
    global $DB;

    $now = time();

    $categories = [
        'News'       => ['Announcements', 'Events'],
        'Tutorial'   => ['Dermoscopy', 'Histopathology', 'Procedures'],
        'Case study' => ['Diagnostic', 'Therapeutic'],
        'Reference'  => ['Guidelines', 'Research'],
    ];
    $sortorder = 0;
    foreach ($categories as $name => $subs) {
        $catid = $DB->insert_record('local_imageblog_categories', (object)[
            'name'        => $name,
            'sortorder'   => $sortorder++,
            'timecreated' => $now,
        ]);
        $subsort = 0;
        foreach ($subs as $sub) {
            $DB->insert_record('local_imageblog_subcategories', (object)[
                'name'       => $sub,
                'categoryid' => $catid,
                'sortorder'  => $subsort++,
            ]);
        }
    }

    $tags = ['feature', 'update', 'dermoscopy', 'biopsy', 'prevention', 'screening'];
    foreach ($tags as $tag) {
        $DB->insert_record('local_imageblog_tags', (object)[
            'name' => ucfirst($tag),
            'slug' => $tag,
        ]);
    }

    $levels = [
        ['name' => 'Beginner', 'colourkey' => 'teal', 'sortorder' => 0],
        ['name' => 'Intermediate', 'colourkey' => 'amber', 'sortorder' => 1],
        ['name' => 'Advanced', 'colourkey' => 'coral', 'sortorder' => 2],
        ['name' => 'All levels', 'colourkey' => 'purple', 'sortorder' => 3],
    ];
    foreach ($levels as $level) {
        $DB->insert_record('local_imageblog_levels', (object)$level);
    }

    // The "Blog author" role can't be created here — the capabilities
    // declared in db/access.php are registered after this install hook
    // returns, so calling assign_capability() now would fail. The role is
    // created lazily on first visit to the Manage blog authors admin page
    // (see authors.php), which is also idempotent for re-installs.

    return true;
}
