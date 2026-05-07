<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Blog listing page.
 *
 * @package   local_scca_blog
 * @copyright 2026 Skin Cancer College of Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/scca_blog/lib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
require_capability('local/scca_blog:view', context_system::instance());

// ── Page setup ──────────────────────────────────────────────────────────────
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/scca_blog/index.php'));
$PAGE->set_title(get_string('blogposts', 'local_scca_blog'));
$PAGE->set_heading(get_string('blogposts', 'local_scca_blog'));
$PAGE->set_pagelayout('standard');

// ── Filter parameters ────────────────────────────────────────────────────────
$filters = [
    'authorid'   => optional_param('authorid',   0,  PARAM_INT),
    'categoryid' => optional_param('categoryid', 0,  PARAM_INT),
    'tagid'      => optional_param('tagid',      0,  PARAM_INT),
    'keyword'    => optional_param('keyword',    '', PARAM_TEXT),
    'page'       => optional_param('page',       0,  PARAM_INT),
];

// ── Data ─────────────────────────────────────────────────────────────────────
$perpage = 12;
$result  = \local_scca_blog\post::get_published($filters, $perpage);

// Build taxonomy arrays for filter dropdowns.
$taxonomy = local_scca_blog_get_taxonomy();

// ── Render ───────────────────────────────────────────────────────────────────
/** @var \local_scca_blog\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_scca_blog');

echo $OUTPUT->header();
echo $renderer->render_listing(
    $result['posts'],
    $result['total'],
    $filters['page'],
    $perpage,
    $filters,
    $taxonomy
);
echo $OUTPUT->footer();
