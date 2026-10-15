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
 * Admin management of evaluation rubric templates and their criteria.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_imageblog\rubric;
use local_imageblog\form\rubric_form;
use local_imageblog\form\rubric_criterion_form;

require_login();
$context = context_system::instance();
require_capability('local/imageblog:managetaxonomy', $context);

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$rubricid = optional_param('rubricid', 0, PARAM_INT);

$listurl = new moodle_url('/local/imageblog/rubrics.php');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/rubrics.php', ['action' => $action]));
$PAGE->set_title(get_string('manage_rubrics', 'local_imageblog'));
$PAGE->set_heading(get_string('manage_rubrics', 'local_imageblog'));
$PAGE->set_pagelayout('admin');

/** @var \local_imageblog\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_imageblog');

// Template-level actions.
if ($action === 'delete' && $id) {
    require_sesskey();
    rubric::delete_template($id);
    redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'edit' || $action === 'add') {
    $record = $id ? rubric::get_template($id) : null;
    $mform = new rubric_form(
        new moodle_url('/local/imageblog/rubrics.php', ['action' => $action, 'id' => $id]),
        ['record' => $record]
    );
    if ($mform->is_cancelled()) {
        redirect($listurl);
    }
    if ($data = $mform->get_data()) {
        $savedid = rubric::save_template($data);
        // Straight to the criteria editor so a new template can be filled in.
        redirect(new moodle_url('/local/imageblog/rubrics.php', ['action' => 'criteria', 'rubricid' => $savedid]));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($id ? 'edit' : 'add'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Criterion-level actions operate within a template.
if ($action === 'deletecrit' && $id && $rubricid) {
    require_sesskey();
    rubric::delete_criterion($id);
    redirect(
        new moodle_url('/local/imageblog/rubrics.php', ['action' => 'criteria', 'rubricid' => $rubricid]),
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'addcrit' || $action === 'editcrit') {
    $template = rubric::get_template($rubricid);
    if (!$template) {
        throw new moodle_exception('error_notfound', 'local_imageblog');
    }
    $record = $id ? $DB->get_record('local_imageblog_rubric_crit', ['id' => $id, 'rubricid' => $rubricid]) : null;
    $mform = new rubric_criterion_form(
        new moodle_url('/local/imageblog/rubrics.php', ['action' => $action, 'id' => $id, 'rubricid' => $rubricid]),
        ['record' => $record ?: null, 'rubricid' => $rubricid]
    );
    $criteriaurl = new moodle_url('/local/imageblog/rubrics.php', ['action' => 'criteria', 'rubricid' => $rubricid]);
    if ($mform->is_cancelled()) {
        redirect($criteriaurl);
    }
    if ($data = $mform->get_data()) {
        rubric::save_criterion($data);
        redirect($criteriaurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($id ? 'edit' : 'add'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'criteria' && $rubricid) {
    $template = rubric::get_template($rubricid);
    if (!$template) {
        throw new moodle_exception('error_notfound', 'local_imageblog');
    }
    echo $OUTPUT->header();
    echo $renderer->render_rubric_criteria($template, rubric::get_criteria($rubricid));
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $renderer->render_rubric_index(rubric::all_templates());
echo $OUTPUT->footer();
