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
 * Taxonomy management dispatcher (categories, subcategories, tags, levels).
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
