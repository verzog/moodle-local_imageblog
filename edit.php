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
 * Post create/edit page.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/imageblog/lib.php');

require_login();

$id = optional_param('id', 0, PARAM_INT);
$context = context_system::instance();

if ($id) {
    $post = \local_imageblog\post::get($id);
    if (!$post) {
        throw new moodle_exception('error_notfound', 'local_imageblog');
    }
    $canedit = ($post->authorid === (int)$USER->id
            && has_capability('local/imageblog:createpost', $context))
        || has_capability('local/imageblog:editanypost', $context);
    if (!$canedit) {
        throw new moodle_exception('error_nopermission', 'local_imageblog');
    }
} else {
    require_capability('local/imageblog:createpost', $context);
    $post = null;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/edit.php', ['id' => $id]));
$PAGE->set_title($id ? get_string('editpost', 'local_imageblog') : get_string('newpost', 'local_imageblog'));
$PAGE->set_heading($PAGE->title);
$PAGE->set_pagelayout('standard');

$PAGE->requires->js_call_amd('local_imageblog/image_processor', 'init', [[
    'featuredSelector' => '#id_featured_image',
    'maxWidth'         => 800,
    'maxHeight'        => 530,
    'quality'          => 0.85,
    'mode'             => 'featured',
]]);

$mform = new \local_imageblog\form\post_form(
    new moodle_url('/local/imageblog/edit.php', ['id' => $id]),
    ['post' => $post, 'context' => $context]
);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/imageblog/index.php'));
}

if ($data = $mform->get_data()) {
    $postid = \local_imageblog\post::save($data, $context);
    redirect(
        new moodle_url('/local/imageblog/view.php', ['id' => $postid]),
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
