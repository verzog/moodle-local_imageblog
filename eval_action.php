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
 * Handles evaluate-post actions: submit a reader evaluation, set the author's
 * reference ratings, reveal the result.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/imageblog/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/imageblog:view', $context);

$postid = required_param('postid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$post = \local_imageblog\post::get($postid);
if (!$post) {
    throw new moodle_exception('error_notfound', 'local_imageblog');
}
if (!\local_imageblog\post_type::is_evaluate($post->posttype)) {
    throw new moodle_exception('error_notevaluate', 'local_imageblog');
}
// Only allow interaction with posts the user can actually see, so a
// submit-capable user cannot POST to a draft or archived post by id.
if (!\local_imageblog\post::can_view($post, $context)) {
    throw new moodle_exception('error_notfound', 'local_imageblog');
}

$canmanage = \local_imageblog\post::can_manage($post, $context);
$returnurl = new moodle_url('/local/imageblog/view.php', ['id' => $postid]);

switch ($action) {
    case 'evaluate':
        require_capability('local/imageblog:submitevaluation', $context);
        if (!empty($post->caserevealed)) {
            throw new moodle_exception('error_caseclosed', 'local_imageblog');
        }
        if ($canmanage) {
            // The author evaluating their own post would skew the aggregate.
            throw new moodle_exception('error_nopermission', 'local_imageblog');
        }
        $scores  = optional_param_array('score', [], PARAM_INT);
        $comment = trim(optional_param('comment', '', PARAM_TEXT));
        $evalid = \local_imageblog\evaluation::submit_evaluation($post, (int)$USER->id, $scores, $comment);
        if (!$evalid) {
            redirect(
                $returnurl,
                get_string('error_emptyfield', 'local_imageblog'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        redirect(
            $returnurl,
            get_string('eval_saved', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'setreference':
        if (!$canmanage) {
            throw new moodle_exception('error_nopermission', 'local_imageblog');
        }
        if (!empty($post->caserevealed)) {
            throw new moodle_exception('error_caseclosed', 'local_imageblog');
        }
        $refs = optional_param_array('ref', [], PARAM_INT);
        \local_imageblog\evaluation::set_reference($post, $refs);
        redirect(
            $returnurl,
            get_string('eval_reference_saved', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'reveal':
        if (!$canmanage) {
            throw new moodle_exception('error_nopermission', 'local_imageblog');
        }
        if (!\local_imageblog\evaluation::has_full_reference($post)) {
            redirect(
                $returnurl,
                get_string('error_norubricreference', 'local_imageblog'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        \local_imageblog\evaluation::reveal($postid);
        redirect(
            $returnurl,
            get_string('case_revealed', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    default:
        throw new moodle_exception('error_invalidstatus', 'local_imageblog');
}
