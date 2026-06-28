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
 * Handles case-post actions: submit diagnosis, ask/answer question,
 * reveal outcome, mark best diagnosis.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
