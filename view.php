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
 * Single post view page.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
if ($post->status !== \local_imageblog\post::STATUS_PUBLISHED) {
    $isauthor = ((int)$post->authorid === (int)$USER->id)
        && has_capability('local/imageblog:createpost', $context);
    if (!$isauthor && !has_capability('local/imageblog:editanypost', $context)) {
        throw new moodle_exception('error_notfound', 'local_imageblog');
    }
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
