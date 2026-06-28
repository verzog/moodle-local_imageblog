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
 * Blog listing page.
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

$datefromraw = optional_param('datefrom', '', PARAM_TEXT);
$datetoraw   = optional_param('dateto', '', PARAM_TEXT);
$datefrom    = $datefromraw !== '' ? strtotime($datefromraw . ' 00:00:00') : 0;
$dateto      = $datetoraw !== '' ? strtotime($datetoraw . ' 23:59:59') : 0;

// Status scope (visible only to authors/managers; defaults to published).
$canauthor = has_capability('local/imageblog:createpost', $context);
$canmanage = has_capability('local/imageblog:editanypost', $context);
$canseestatuses = $canauthor || $canmanage;

$status = optional_param('status', '', PARAM_ALPHA);
$validstatuses = [
    \local_imageblog\post::STATUS_PUBLISHED,
    \local_imageblog\post::STATUS_DRAFT,
    \local_imageblog\post::STATUS_ARCHIVED,
];
if (!in_array($status, array_merge([''], $validstatuses, ['mine']), true)) {
    $status = '';
}

$statuses = [\local_imageblog\post::STATUS_PUBLISHED];
$mineonly = false;
if ($canseestatuses) {
    if ($status === 'mine') {
        // Show only the current user's posts in any status.
        $statuses = $validstatuses;
        $mineonly = true;
    } else if (in_array($status, $validstatuses, true)) {
        $statuses = [$status];
        $mineonly = !$canmanage && $status !== \local_imageblog\post::STATUS_PUBLISHED;
    }
}

$filters = [
    'authorid'      => optional_param('authorid', 0, PARAM_INT),
    'categoryid'    => optional_param('categoryid', 0, PARAM_INT),
    'subcategoryid' => optional_param('subcategoryid', 0, PARAM_INT),
    'tagid'         => optional_param('tagid', 0, PARAM_INT),
    'levelid'       => optional_param('levelid', 0, PARAM_INT),
    'keyword'       => optional_param('keyword', '', PARAM_RAW_TRIMMED),
    'datefrom'      => $datefrom ?: 0,
    'dateto'        => $dateto ?: 0,
    'datefromraw'   => $datefromraw,
    'datetoraw'     => $datetoraw,
    'page'          => optional_param('page', 0, PARAM_INT),
    'status'        => $status,
    'statuses'      => $statuses,
    'mineonly'      => $mineonly,
    'viewerid'      => (int)$USER->id,
    'canseestatuses' => $canseestatuses,
];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/index.php'));
$PAGE->set_title(get_string('blogposts', 'local_imageblog'));
$PAGE->set_heading(get_string('blogposts', 'local_imageblog'));
$PAGE->set_pagelayout('standard');

if (get_config('local_imageblog', 'rss_enabled')) {
    $rssurl = (new moodle_url('/local/imageblog/rss.php'))->out(false);
    $PAGE->add_alternate_version(
        get_string('rss_link', 'local_imageblog'),
        $rssurl,
        'application/rss+xml'
    );
}

$perpage = 12;
$result = \local_imageblog\post::get_published($filters, $perpage);
$taxonomy = local_imageblog_get_taxonomy();

/** @var \local_imageblog\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_imageblog');

echo $OUTPUT->header();
echo local_imageblog_get_custom_css_html();
echo $renderer->render_listing(
    $result['posts'],
    $result['total'],
    (int)$filters['page'],
    $perpage,
    $filters,
    $taxonomy
);
echo $OUTPUT->footer();
