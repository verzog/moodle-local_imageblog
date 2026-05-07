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
 * Plugin library functions.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve plugin files (featured images and post body images).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context  $context
 * @param string   $filearea
 * @param array    $args
 * @param bool     $forcedownload
 * @param array    $options
 * @return bool
 */
function local_imageblog_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    require_login();
    require_capability('local/imageblog:view', $context);

    $allowedareas = ['featured_image', 'post_images'];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    $itemid   = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'local_imageblog', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    $cachelifetime = ($filearea === 'featured_image') ? DAYSECS : HOURSECS;
    send_stored_file($file, $cachelifetime, 0, $forcedownload, $options);
}

/**
 * Return taxonomy arrays (authors, categories, tags) for filter dropdowns.
 *
 * @return array{authors: array, categories: array, tags: array}
 */
function local_imageblog_get_taxonomy(): array {
    global $DB;

    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {local_imageblog_posts} p ON p.authorid = u.id
             WHERE p.status = :status
          ORDER BY u.lastname, u.firstname";
    $authorrecords = $DB->get_records_sql($sql, ['status' => \local_imageblog\post::STATUS_PUBLISHED]);
    $authors = array_map(function ($u) {
        return ['id' => (int)$u->id, 'name' => fullname($u)];
    }, $authorrecords);

    $catrecords = $DB->get_records('local_imageblog_categories', null, 'sortorder ASC');
    $categories = array_map(function ($c) {
        return ['id' => (int)$c->id, 'name' => $c->name];
    }, $catrecords);

    $subcatrecords = $DB->get_records('local_imageblog_subcategories', null, 'sortorder ASC');
    $subcategories = array_map(function ($s) {
        return [
            'id'         => (int)$s->id,
            'name'       => $s->name,
            'categoryid' => (int)$s->categoryid,
        ];
    }, $subcatrecords);

    $tagrecords = $DB->get_records('local_imageblog_tags', null, 'name ASC');
    $tags = array_map(function ($t) {
        return ['id' => (int)$t->id, 'name' => $t->name];
    }, $tagrecords);

    $levelrecords = $DB->get_records('local_imageblog_levels', null, 'sortorder ASC');
    $levels = array_map(function ($l) {
        return ['id' => (int)$l->id, 'name' => $l->name];
    }, $levelrecords);

    return [
        'authors'       => array_values($authors),
        'categories'    => array_values($categories),
        'subcategories' => array_values($subcategories),
        'tags'          => array_values($tags),
        'levels'        => array_values($levels),
    ];
}

/**
 * Inject a link to the blog into the global navigation.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_imageblog_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $context = context_system::instance();
    if (!has_capability('local/imageblog:view', $context)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'local_imageblog'),
        new moodle_url('/local/imageblog/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_imageblog'
    );
}
