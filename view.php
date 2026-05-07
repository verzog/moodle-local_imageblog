<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Single post view page.
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

$id = required_param('id', PARAM_INT);

$post = \local_scca_blog\post::get($id);
if (!$post || $post->status !== \local_scca_blog\post::STATUS_PUBLISHED) {
    // Admins can preview drafts.
    if (!$post || !has_capability('local/scca_blog:editanypost', context_system::instance())) {
        throw new moodle_exception('error_notfound', 'local_scca_blog');
    }
}

// ── Page setup ───────────────────────────────────────────────────────────────
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/scca_blog/view.php', ['id' => $id]));
$PAGE->set_title(format_string($post->title));
$PAGE->set_heading(format_string($post->title));
$PAGE->set_pagelayout('standard');

// Load GLightbox for clinical image zoom.
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css'));
$PAGE->requires->js_call_amd('local_scca_blog/lightbox', 'init');

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();

// Back link.
echo html_writer::link(
    new moodle_url('/local/scca_blog/index.php'),
    '← ' . get_string('backtoposts', 'local_scca_blog'),
    ['class' => 'btn btn-sm btn-outline-secondary mb-3']
);

// TODO: render full post template (post.mustache) — next iteration.
// For now, output a minimal readable view so the scaffold is functional.
echo html_writer::tag('h1', format_string($post->title), ['class' => 'mb-3']);
echo html_writer::tag('div', format_text($post->body, $post->bodyformat), ['class' => 'scca-blog-post-body']);

// Embedded forum comments will be wired here once the forum-map table is populated.

echo $OUTPUT->footer();
