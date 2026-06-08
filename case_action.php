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
 * Handles case-post actions: submit diagnosis, ask/answer question,
 * reveal outcome, mark best diagnosis.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
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
if ($post->posttype !== \local_imageblog\case_post::TYPE_CASE) {
    throw new moodle_exception('error_notacase', 'local_imageblog');
}

// Can manage this case — author with createpost still held, or any-post editor.
$canmanagecase = has_capability('local/imageblog:editanypost', $context)
    || ((int)$post->authorid === (int)$USER->id
        && has_capability('local/imageblog:createpost', $context));

$returnurl = new moodle_url('/local/imageblog/view.php', ['id' => $postid]);

switch ($action) {
    case 'diagnose':
        require_capability('local/imageblog:submitdiagnosis', $context);
        if (!empty($post->caserevealed)) {
            throw new moodle_exception('error_caseclosed', 'local_imageblog');
        }
        $diagnosis = trim(required_param('diagnosis', PARAM_TEXT));
        $reasoning = trim(optional_param('reasoning', '', PARAM_TEXT));
        if ($diagnosis === '') {
            redirect(
                $returnurl,
                get_string('error_emptyfield', 'local_imageblog'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        \local_imageblog\case_post::submit_diagnosis($postid, (int)$USER->id, $diagnosis, $reasoning);
        redirect(
            $returnurl,
            get_string('case_diagnosis_saved', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'ask':
        require_capability('local/imageblog:askcasequestion', $context);
        $question = trim(required_param('question', PARAM_TEXT));
        if ($question === '') {
            redirect(
                $returnurl,
                get_string('error_emptyfield', 'local_imageblog'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        \local_imageblog\case_post::ask_question($postid, (int)$USER->id, $question);
        redirect(
            $returnurl,
            get_string('case_question_submitted', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'answer':
        if (!$canmanagecase) {
            throw new moodle_exception('error_nopermission', 'local_imageblog');
        }
        $qid = required_param('questionid', PARAM_INT);
        $answer = trim(required_param('answer', PARAM_TEXT));
        if ($answer === '') {
            redirect(
                $returnurl,
                get_string('error_emptyfield', 'local_imageblog'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        \local_imageblog\case_post::answer_question($qid, (int)$USER->id, $answer);
        redirect(
            $returnurl,
            get_string('case_answer_submitted', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'reveal':
        if (!$canmanagecase) {
            throw new moodle_exception('error_nopermission', 'local_imageblog');
        }
        \local_imageblog\case_post::reveal($postid);
        redirect(
            $returnurl,
            get_string('case_revealed', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'markbest':
        if (!$canmanagecase) {
            throw new moodle_exception('error_nopermission', 'local_imageblog');
        }
        if (empty($post->caserevealed)) {
            throw new moodle_exception('error_casenotrevealed', 'local_imageblog');
        }
        $diagid = required_param('diagnosisid', PARAM_INT);
        \local_imageblog\case_post::set_best_diagnosis($postid, $diagid);
        redirect(
            $returnurl,
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    default:
        throw new moodle_exception('error_invalidstatus', 'local_imageblog');
}
