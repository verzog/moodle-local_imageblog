<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Post create/edit page.
 *
 * @package   local_scca_blog
 * @copyright 2026 Skin Cancer College of Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/scca_blog/lib.php');
require_once($CFG->dirroot . '/local/scca_blog/classes/form/post_form.php');

defined('MOODLE_INTERNAL') || die();

require_login();

$id = optional_param('id', 0, PARAM_INT);

// Check permission — own post or edit-any.
$context = context_system::instance();
if ($id) {
    $post = \local_scca_blog\post::get($id);
    if (!$post) {
        throw new moodle_exception('error_notfound', 'local_scca_blog');
    }
    $canedit = ($post->authorid === $USER->id && has_capability('local/scca_blog:createpost', $context))
                || has_capability('local/scca_blog:editanypost', $context);
    if (!$canedit) {
        throw new moodle_exception('error_nopermission', 'local_scca_blog');
    }
} else {
    require_capability('local/scca_blog:createpost', $context);
    $post = null;
}

// ── Page setup ───────────────────────────────────────────────────────────────
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/scca_blog/edit.php', ['id' => $id]));
$PAGE->set_title($id ? get_string('editpost', 'local_scca_blog') : get_string('newpost', 'local_scca_blog'));
$PAGE->set_pagelayout('standard');

// Load image processor AMD module.
$PAGE->requires->js_call_amd('local_scca_blog/image_processor', 'init', [
    [
        'featuredSelector' => '#id_featured_image',
        'maxWidth'         => 800,
        'maxHeight'        => 530,
        'quality'          => 0.85,
        'mode'             => 'featured',
    ]
]);

// ── Form ──────────────────────────────────────────────────────────────────────
$mform = new \local_scca_blog\form\post_form(null, ['post' => $post, 'context' => $context]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/scca_blog/index.php'));
}

if ($data = $mform->get_data()) {
    $postid = \local_scca_blog\post::save($data);
    redirect(
        new moodle_url('/local/scca_blog/view.php', ['id' => $postid]),
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Render ───────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
