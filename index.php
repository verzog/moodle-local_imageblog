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
 * Blog listing page.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/imageblog/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/imageblog:view', $context);

$filters = [
    'authorid'   => optional_param('authorid',   0,  PARAM_INT),
    'categoryid' => optional_param('categoryid', 0,  PARAM_INT),
    'tagid'      => optional_param('tagid',      0,  PARAM_INT),
    'keyword'    => optional_param('keyword',    '', PARAM_TEXT),
    'page'       => optional_param('page',       0,  PARAM_INT),
];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/index.php'));
$PAGE->set_title(get_string('blogposts', 'local_imageblog'));
$PAGE->set_heading(get_string('blogposts', 'local_imageblog'));
$PAGE->set_pagelayout('standard');

$perpage = 12;
$result = \local_imageblog\post::get_published($filters, $perpage);
$taxonomy = local_imageblog_get_taxonomy();

/** @var \local_imageblog\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_imageblog');

echo $OUTPUT->header();
echo $renderer->render_listing(
    $result['posts'],
    $result['total'],
    (int)$filters['page'],
    $perpage,
    $filters,
    $taxonomy
);
echo $OUTPUT->footer();
