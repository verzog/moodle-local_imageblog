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
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/imageblog/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/imageblog:view', $context);

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$post = \local_imageblog\post::get($id);
if (!$post) {
    throw new moodle_exception('error_notfound', 'local_imageblog');
}
if (!\local_imageblog\post::can_view($post, $context)) {
    throw new moodle_exception('error_notfound', 'local_imageblog');
}

if ($action === 'unpublish') {
    require_sesskey();
    $isauthor = ((int)$post->authorid === (int)$USER->id);
    $canpublishown = $isauthor
        && has_capability('local/imageblog:createpost', $context)
        && has_capability('local/imageblog:publishpost', $context);
    if (!has_capability('local/imageblog:editanypost', $context) && !$canpublishown) {
        throw new moodle_exception('error_nopermission', 'local_imageblog');
    }
    \local_imageblog\post::set_status($post->id, \local_imageblog\post::STATUS_DRAFT);
    redirect(
        new moodle_url('/local/imageblog/view.php', ['id' => $post->id]),
        get_string('reverted_to_draft', 'local_imageblog'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Award the view-only CPD for a revealed case when the viewer is eligible.
// Done here (after access checks, before render) rather than in the renderer
// so reads stay side-effect-free.
if (
    $post->posttype === \local_imageblog\case_post::TYPE_CASE
    && !empty($post->caserevealed)
    && has_capability('local/imageblog:submitdiagnosis', $context)
) {
    \local_imageblog\case_post::award_view_if_eligible($post->id, (int)$USER->id);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/view.php', ['id' => $id]));
$PAGE->set_title(format_string($post->title));
$PAGE->set_heading(format_string($post->title));
$PAGE->set_pagelayout('standard');
$PAGE->requires->js_call_amd('local_imageblog/lightbox', 'init');

if ($post->get_panorama_url()) {
    $jsurl  = (new moodle_url('/local/imageblog/thirdparty/pannellum/pannellum.js'))->out(false);
    $cssurl = (new moodle_url('/local/imageblog/thirdparty/pannellum/pannellum.css'))->out(false);
    $PAGE->requires->js_call_amd('local_imageblog/panorama', 'init', [$jsurl, $cssurl]);
}

/** @var \local_imageblog\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_imageblog');

echo $OUTPUT->header();
echo local_imageblog_get_custom_css_html();
echo $renderer->render_post($post);
echo $OUTPUT->footer();
