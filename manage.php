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
 * Taxonomy management dispatcher (categories, subcategories, tags, levels).
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_imageblog\taxonomy;
use local_imageblog\form\taxonomy_form;

require_login();
$context = context_system::instance();
require_capability('local/imageblog:managetaxonomy', $context);

$validtypes = [
    taxonomy::TYPE_CATEGORY,
    taxonomy::TYPE_SUBCATEGORY,
    taxonomy::TYPE_TAG,
    taxonomy::TYPE_LEVEL,
];
$type = optional_param('type', taxonomy::TYPE_CATEGORY, PARAM_ALPHA);
if (!in_array($type, $validtypes, true)) {
    $type = taxonomy::TYPE_CATEGORY;
}
$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$baseurl = new moodle_url('/local/imageblog/manage.php', ['type' => $type]);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/manage.php', ['type' => $type, 'action' => $action]));
$PAGE->set_title(get_string('manage_' . $type, 'local_imageblog'));
$PAGE->set_heading(get_string('manage_' . $type, 'local_imageblog'));
$PAGE->set_pagelayout('admin');

if ($action === 'delete' && $id) {
    require_sesskey();
    taxonomy::delete($type, $id);
    redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'edit' || $action === 'add') {
    $record = $id ? taxonomy::get($type, $id) : null;
    $mform = new taxonomy_form(
        new moodle_url('/local/imageblog/manage.php', ['type' => $type, 'action' => $action, 'id' => $id]),
        ['type' => $type, 'record' => $record]
    );

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    }

    if ($data = $mform->get_data()) {
        taxonomy::save($type, $data);
        redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($id ? 'edit' : 'add'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

/** @var \local_imageblog\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_imageblog');

echo $OUTPUT->header();
echo $renderer->render_taxonomy_index($type, taxonomy::all($type));
echo $OUTPUT->footer();
