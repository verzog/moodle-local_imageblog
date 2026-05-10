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
 * Bulk-import legacy blog entries from a CSV + image directory.
 *
 * CSV columns (header row required):
 *   title, summary, body, status, timepublished, author_email,
 *   category, subcategory, tags, levels, featured_image, panorama_image
 *
 * - status:        draft|published|archived (default: published)
 * - timepublished: ISO-8601 or unix timestamp (optional)
 * - tags / levels: pipe-separated names (e.g. "Melanoma|Dermoscopy")
 * - featured_image / panorama_image: filename relative to --imagedir
 *
 * Usage:
 *   php local/imageblog/cli/import.php \
 *       --csv=/path/to/posts.csv \
 *       --imagedir=/path/to/images \
 *       --fallback-author=admin \
 *       [--batch=200] [--dry-run] [--continue-on-error]
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');

[$options, $unrecognised] = cli_get_params([
    'csv'                => null,
    'imagedir'           => null,
    'fallback-author'    => 'admin',
    'batch'              => 200,
    'dry-run'            => false,
    'continue-on-error'  => false,
    'help'               => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help'] || empty($options['csv'])) {
    echo "Bulk-import legacy blog entries.\n\n";
    echo "Required:\n";
    echo "  --csv=PATH                CSV file with header row.\n";
    echo "Optional:\n";
    echo "  --imagedir=PATH           Directory containing image files referenced in the CSV.\n";
    echo "  --fallback-author=USER    Username to attribute posts to when author_email is missing\n";
    echo "                            or unknown (default: admin).\n";
    echo "  --batch=N                 Posts per transaction (default: 200).\n";
    echo "  --dry-run                 Parse and validate without writing.\n";
    echo "  --continue-on-error       Skip failed rows instead of aborting.\n";
    exit(0);
}

$csvpath = $options['csv'];
if (!is_readable($csvpath)) {
    cli_error("CSV not readable: $csvpath");
}
$imagedir = $options['imagedir'] ? rtrim($options['imagedir'], '/') : null;
if ($imagedir && !is_dir($imagedir)) {
    cli_error("Image directory not found: $imagedir");
}
$batchsize = max(1, (int)$options['batch']);
$dryrun    = (bool)$options['dry-run'];
$continue  = (bool)$options['continue-on-error'];

$fallback = $DB->get_record('user', ['username' => $options['fallback-author'], 'deleted' => 0], '*', MUST_EXIST);
$context  = context_system::instance();
$fs       = get_file_storage();

$handle = fopen($csvpath, 'r');
if (!$handle) {
    cli_error("Failed to open CSV: $csvpath");
}
$header = fgetcsv($handle);
if (!$header) {
    cli_error("Empty CSV.");
}
$header = array_map(fn($c) => strtolower(trim($c)), $header);
$required = ['title'];
foreach ($required as $col) {
    if (!in_array($col, $header, true)) {
        cli_error("Missing required CSV column: $col");
    }
}

// Cache user/taxonomy lookups so we don't re-query for every row.
$usercache  = [];
$tagcache   = [];
$levelcache = [];
$catcache   = [];
$subcatcache = [];

$resolve_user = function (string $email) use (&$usercache, $fallback): int {
    $email = strtolower(trim($email));
    if ($email === '') {
        return (int)$fallback->id;
    }
    if (isset($usercache[$email])) {
        return $usercache[$email];
    }
    global $DB;
    $u = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id');
    return $usercache[$email] = (int)($u ? $u->id : $fallback->id);
};

$resolve_category = function (string $name) use (&$catcache): ?int {
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    if (isset($catcache[$name])) {
        return $catcache[$name];
    }
    global $DB;
    $row = $DB->get_record('local_imageblog_categories', ['name' => $name], 'id');
    if ($row) {
        return $catcache[$name] = (int)$row->id;
    }
    return $catcache[$name] = (int)$DB->insert_record('local_imageblog_categories', (object)[
        'name'        => $name,
        'sortorder'   => 0,
        'timecreated' => time(),
    ]);
};

$resolve_subcategory = function (string $name, ?int $categoryid) use (&$subcatcache): ?int {
    $name = trim($name);
    if ($name === '' || !$categoryid) {
        return null;
    }
    $key = $categoryid . '|' . $name;
    if (isset($subcatcache[$key])) {
        return $subcatcache[$key];
    }
    global $DB;
    $row = $DB->get_record('local_imageblog_subcategories', ['name' => $name, 'categoryid' => $categoryid], 'id');
    if ($row) {
        return $subcatcache[$key] = (int)$row->id;
    }
    return $subcatcache[$key] = (int)$DB->insert_record('local_imageblog_subcategories', (object)[
        'name'       => $name,
        'categoryid' => $categoryid,
        'sortorder'  => 0,
    ]);
};

$resolve_tag = function (string $name) use (&$tagcache): int {
    $name = trim($name);
    if (isset($tagcache[$name])) {
        return $tagcache[$name];
    }
    return $tagcache[$name] = (int)\local_imageblog\taxonomy::save(
        \local_imageblog\taxonomy::TYPE_TAG,
        (object)['name' => $name]
    );
};

$resolve_level = function (string $name) use (&$levelcache): ?int {
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    if (isset($levelcache[$name])) {
        return $levelcache[$name];
    }
    global $DB;
    $row = $DB->get_record('local_imageblog_levels', ['name' => $name], 'id');
    return $levelcache[$name] = (int)($row
        ? $row->id
        : $DB->insert_record('local_imageblog_levels', (object)[
            'name' => $name, 'colourkey' => 'amber', 'sortorder' => 0,
        ]));
};

$parse_time = function (string $value): ?int {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (ctype_digit($value)) {
        return (int)$value;
    }
    $ts = strtotime($value);
    return $ts ?: null;
};

$store_image = function (string $relpath, string $filearea, int $postid)
        use ($imagedir, $context, $fs): bool {
    if (!$imagedir || trim($relpath) === '') {
        return false;
    }
    $abs = $imagedir . '/' . ltrim($relpath, '/');
    if (!is_readable($abs)) {
        cli_problem("  ! image not found: $abs");
        return false;
    }
    $fs->create_file_from_pathname([
        'contextid' => $context->id,
        'component' => 'local_imageblog',
        'filearea'  => $filearea,
        'itemid'    => $postid,
        'filepath'  => '/',
        'filename'  => basename($abs),
    ], $abs);
    return true;
};

$row     = 1;
$ok      = 0;
$failed  = 0;
$skipped = 0;
$batch   = [];
$now     = time();

$flush = function () use (&$batch, $dryrun, $store_image, $continue, &$ok, &$failed) {
    if (!$batch) {
        return;
    }
    global $DB;
    if ($dryrun) {
        $ok += count($batch);
        $batch = [];
        return;
    }
    $tx = $DB->start_delegated_transaction();
    try {
        foreach ($batch as $item) {
            $postid = (int)$DB->insert_record('local_imageblog_posts', $item['record']);

            if (!empty($item['featured'])) {
                $store_image($item['featured'], \local_imageblog\post::FILEAREA_FEATURED, $postid);
            }
            if (!empty($item['panorama'])) {
                $store_image($item['panorama'], \local_imageblog\post::FILEAREA_PANORAMA, $postid);
            }

            \local_imageblog\post::set_taxonomy(
                $postid,
                $item['categoryid'],
                $item['subcategoryid'],
                $item['tagids'],
                $item['levelids']
            );
            $ok++;
        }
        $tx->allow_commit();
    } catch (\Throwable $e) {
        $tx->rollback($e);
        $failed += count($batch);
        if (!$continue) {
            throw $e;
        }
        cli_problem("  ! batch failed: " . $e->getMessage());
    }
    $batch = [];
};

while (($cols = fgetcsv($handle)) !== false) {
    $row++;
    if (count($cols) === 1 && trim((string)$cols[0]) === '') {
        continue;
    }
    $data = array_combine($header, array_pad($cols, count($header), ''));
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '') {
        $skipped++;
        cli_problem("row $row: skipped (empty title)");
        continue;
    }

    $status = strtolower(trim((string)($data['status'] ?? 'published')));
    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $status = 'published';
    }
    $timepublished = $parse_time((string)($data['timepublished'] ?? ''));
    if ($status === 'published' && !$timepublished) {
        $timepublished = $now;
    }

    $categoryid    = $resolve_category((string)($data['category'] ?? ''));
    $subcategoryid = $resolve_subcategory((string)($data['subcategory'] ?? ''), $categoryid);

    $tagids = [];
    foreach (preg_split('/\|/', (string)($data['tags'] ?? '')) as $t) {
        if (trim($t) !== '') {
            $tagids[] = $resolve_tag($t);
        }
    }
    $levelids = [];
    foreach (preg_split('/\|/', (string)($data['levels'] ?? '')) as $l) {
        $id = $resolve_level($l);
        if ($id) {
            $levelids[] = $id;
        }
    }

    $batch[] = [
        'record' => (object)[
            'authorid'      => $resolve_user((string)($data['author_email'] ?? '')),
            'title'         => $title,
            'summary'       => (string)($data['summary'] ?? ''),
            'body'          => (string)($data['body'] ?? ''),
            'bodyformat'    => FORMAT_HTML,
            'status'        => $status,
            'timepublished' => $timepublished,
            'timecreated'   => $now,
            'timemodified'  => $now,
            'lazyimages'    => 1,
        ],
        'categoryid'    => $categoryid,
        'subcategoryid' => $subcategoryid,
        'tagids'        => $tagids,
        'levelids'      => $levelids,
        'featured'      => (string)($data['featured_image'] ?? ''),
        'panorama'      => (string)($data['panorama_image'] ?? ''),
    ];

    if (count($batch) >= $batchsize) {
        $flush();
        cli_writeln("  committed up to row $row (ok=$ok, failed=$failed, skipped=$skipped)");
    }
}

$flush();
fclose($handle);

cli_writeln("");
cli_writeln("Import complete: ok=$ok failed=$failed skipped=$skipped" . ($dryrun ? ' (dry-run)' : ''));
exit($failed > 0 ? 1 : 0);
