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
 * Single post view page.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/imageblog/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/imageblog:view', $context);

$id = required_param('id', PARAM_INT);

$post = \local_imageblog\post::get($id);
if (!$post || $post->status !== \local_imageblog\post::STATUS_PUBLISHED) {
    if (!$post || !has_capability('local/imageblog:editanypost', $context)) {
        throw new moodle_exception('error_notfound', 'local_imageblog');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/view.php', ['id' => $id]));
$PAGE->set_title(format_string($post->title));
$PAGE->set_heading(format_string($post->title));
$PAGE->set_pagelayout('standard');
$PAGE->requires->js_call_amd('local_imageblog/lightbox', 'init');

/** @var \local_imageblog\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_imageblog');

echo $OUTPUT->header();
echo $renderer->render_post($post);
echo $OUTPUT->footer();
