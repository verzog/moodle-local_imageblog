<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Plugin library functions.
 *
 * @package   local_scca_blog
 * @copyright 2026 Skin Cancer College of Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve plugin files (featured images and body images).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context  $context
 * @param string   $filearea   featured_image | post_images
 * @param array    $args
 * @param bool     $forcedownload
 * @param array    $options
 * @return bool
 */
function local_scca_blog_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    require_login();
    require_capability('local/scca_blog:view', context_system::instance());

    $allowedareas = ['featured_image', 'post_images'];
    if (!in_array($filearea, $allowedareas)) {
        return false;
    }

    $itemid   = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'local_scca_blog', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    // Featured images are public-ish — no forced download, long cache.
    if ($filearea === 'featured_image') {
        send_file($file, $filename, 0, 0, false, false, '', false, $options);
    } else {
        // Clinical images — no download forced but shorter cache window.
        send_file($file, $filename, 86400, 0, false, $forcedownload, '', false, $options);
    }

    return true;
}

/**
 * Return taxonomy arrays (authors, categories, tags) for filter dropdowns.
 *
 * @return array{authors: array, categories: array, tags: array}
 */
function local_scca_blog_get_taxonomy(): array {
    global $DB;

    // Authors — users who have at least one published post.
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {local_scca_blog_posts} p ON p.authorid = u.id
             WHERE p.status = 'published'
          ORDER BY u.lastname, u.firstname";
    $authorrecords = $DB->get_records_sql($sql);
    $authors = array_map(function($u) {
        return ['id' => $u->id, 'name' => fullname($u)];
    }, $authorrecords);

    // Categories.
    $catrecords = $DB->get_records('local_scca_blog_categories', null, 'sortorder ASC');
    $categories = array_map(function($c) {
        return ['id' => $c->id, 'name' => $c->name];
    }, $catrecords);

    // Tags.
    $tagrecords = $DB->get_records('local_scca_blog_tags', null, 'name ASC');
    $tags = array_map(function($t) {
        return ['id' => $t->id, 'name' => $t->name];
    }, $tagrecords);

    return [
        'authors'    => array_values($authors),
        'categories' => array_values($categories),
        'tags'       => array_values($tags),
    ];
}
