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
 * Post create/edit page.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
