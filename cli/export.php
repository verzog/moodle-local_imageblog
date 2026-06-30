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
 * Bulk-export blog entries to a CSV + image directory.
 *
 * Produces the same column layout that cli/import.php consumes, so an
 * export can be re-imported into another site:
 *   title, summary, body, status, timepublished, author_email,
 *   category, subcategory, tags, levels, featured_image, panorama_image
 *
 * Notes:
 * - 'scheduled' posts are exported with status 'draft' because the import
 *   format has no scheduling column (the schedule timestamp would be stale
 *   by the time it is re-imported anyway).
 * - Images embedded in the post body (@@PLUGINFILE@@ URLs) are not
 *   exported; only the featured image and the panorama are.
 *
 * Usage:
 *   php local/imageblog/cli/export.php \
 *       --csv=/path/to/posts.csv \
 *       [--imagedir=/path/to/images] \
 *       [--status=published] [--overwrite]
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'csv'       => null,
    'imagedir'  => null,
    'status'    => 'all',
    'overwrite' => false,
    'help'      => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help'] || empty($options['csv'])) {
    echo "Bulk-export blog entries to CSV (re-importable via cli/import.php).\n\n";
    echo "Required:\n";
    echo "  --csv=PATH       Output CSV file.\n";
    echo "Optional:\n";
    echo "  --imagedir=PATH  Directory to copy featured/panorama images into.\n";
    echo "  --status=NAME    all|draft|scheduled|published|archived (default: all).\n";
    echo "  --overwrite      Replace the CSV file if it already exists.\n";
    exit(0);
}

$csvpath = $options['csv'];
if (file_exists($csvpath) && !$options['overwrite']) {
    cli_error("CSV already exists (use --overwrite to replace): $csvpath");
}
$imagedir = $options['imagedir'] ? rtrim($options['imagedir'], '/') : null;
if ($imagedir && !check_dir_exists($imagedir, true, true)) {
    cli_error("Cannot create image directory: $imagedir");
}

$validstatuses = [
    'all',
    \local_imageblog\post::STATUS_DRAFT,
    \local_imageblog\post::STATUS_SCHEDULED,
    \local_imageblog\post::STATUS_PUBLISHED,
    \local_imageblog\post::STATUS_ARCHIVED,
];
$statusfilter = strtolower(trim((string)$options['status']));
if (!in_array($statusfilter, $validstatuses, true)) {
    cli_error("Invalid --status (expected one of: " . implode('|', $validstatuses) . ")");
}

$context = context_system::instance();
$fs      = get_file_storage();

// Cache taxonomy names keyed by id so each post is a couple of lookups.
$categorynames    = $DB->get_records_menu('local_imageblog_categories', null, '', 'id, name');
$subcategorynames = $DB->get_records_menu('local_imageblog_subcategories', null, '', 'id, name');

$exportimage = function (int $postid, string $filearea, string $subdir) use ($fs, $context, $imagedir): string {
    if (!$imagedir) {
        return '';
    }
    $files = $fs->get_area_files(
        $context->id,
        'local_imageblog',
        $filearea,
        $postid,
        'itemid, filepath, filename',
        false
    );
    if (!$files) {
        return '';
    }
    $file    = reset($files);
    $relpath = $postid . '/' . $subdir . '/' . $file->get_filename();
    $abs     = $imagedir . '/' . $relpath;
    if (!check_dir_exists(dirname($abs), true, true)) {
        cli_problem("  ! cannot create directory: " . dirname($abs));
        return '';
    }
    $file->copy_content_to($abs);
    return $relpath;
};

$handle = fopen($csvpath, 'w');
if (!$handle) {
    cli_error("Failed to open CSV for writing: $csvpath");
}

$header = [
    'title', 'summary', 'body', 'status', 'timepublished', 'author_email',
    'category', 'subcategory', 'tags', 'levels', 'featured_image', 'panorama_image',
];
fputcsv($handle, $header, ',', '"', '\\');

$params = [];
$where  = '';
if ($statusfilter !== 'all') {
    $where = 'WHERE p.status = :status';
    $params['status'] = $statusfilter;
}
$sql = "SELECT p.*, u.email AS authoremail
          FROM {local_imageblog_posts} p
          JOIN {user} u ON u.id = p.authorid
        $where
      ORDER BY p.id ASC";

$count = 0;
$rs = $DB->get_recordset_sql($sql, $params);
foreach ($rs as $record) {
    $post = \local_imageblog\post::get((int)$record->id);
    if (!$post) {
        continue;
    }

    [$categoryid, $subcategoryid] = $post->get_category_ids();
    $tagnames   = array_map(fn($t) => $t->name, $post->get_tags());
    $levelnames = array_map(fn($l) => $l->name, $post->get_levels());

    // The import format has no scheduling column; demote to draft.
    $status = $post->status === \local_imageblog\post::STATUS_SCHEDULED
        ? \local_imageblog\post::STATUS_DRAFT
        : $post->status;

    $row = [
        $post->title,
        $post->summary,
        $post->body,
        $status,
        $post->timepublished ?? '',
        $record->authoremail,
        $categoryid ? ($categorynames[$categoryid] ?? '') : '',
        $subcategoryid ? ($subcategorynames[$subcategoryid] ?? '') : '',
        implode('|', $tagnames),
        implode('|', $levelnames),
        $exportimage($post->id, \local_imageblog\post::FILEAREA_FEATURED, 'featured'),
        $exportimage($post->id, \local_imageblog\post::FILEAREA_PANORAMA, 'panorama'),
    ];
    fputcsv($handle, $row, ',', '"', '\\');
    $count++;

    if ($count % 200 === 0) {
        cli_writeln("  exported $count posts...");
    }
}
$rs->close();
fclose($handle);

cli_writeln("");
cli_writeln("Export complete: $count posts written to $csvpath"
    . ($imagedir ? " (images in $imagedir)" : ''));
exit(0);
