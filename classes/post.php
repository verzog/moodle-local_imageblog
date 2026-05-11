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
 * Post model class.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Represents a single blog post and provides data-access methods.
 */
class post {
    /** @var int */
    public int $id = 0;
    /** @var int */
    public int $authorid = 0;
    /** @var string */
    public string $title = '';
    /** @var string */
    public string $summary = '';
    /** @var string */
    public string $body = '';
    /** @var int */
    public int $bodyformat = 1;
    /** @var string draft|published|archived */
    public string $status = self::STATUS_DRAFT;
    /** @var int|null */
    public ?int $timepublished = null;
    /** @var int */
    public int $timecreated = 0;
    /** @var int */
    public int $timemodified = 0;
    /** @var bool */
    public bool $lazyimages = true;
    /** @var int|null */
    public ?int $forumpostid = null;
    /** @var int|null */
    public ?int $featuredimage = null;
    /** @var string blog|case */
    public string $posttype = 'blog';
    /** @var string */
    public string $caseoutcome = '';
    /** @var int */
    public int $caseoutcomeformat = 1;
    /** @var bool */
    public bool $caserevealed = false;
    /** @var int|null */
    public ?int $caserevealedtime = null;
    /** @var int|null */
    public ?int $casebestdiagnosisid = null;
    /** @var int */
    public int $casedifficulty = 1;

    /** @var string Draft post status. */
    const STATUS_DRAFT     = 'draft';
    /** @var string Published post status. */
    const STATUS_PUBLISHED = 'published';
    /** @var string Archived post status. */
    const STATUS_ARCHIVED  = 'archived';

    /** @var string Filearea for embedded body images. */
    const FILEAREA_BODY     = 'post_images';
    /** @var string Filearea for the featured (cover) image. */
    const FILEAREA_FEATURED = 'featured_image';
    /** @var string Filearea for an equirectangular 360° panorama. */
    const FILEAREA_PANORAMA = 'panorama';
    /** @var string Filearea for embedded images in the case outcome editor. */
    const FILEAREA_CASEOUTCOME = 'case_outcome';

    /**
     * Fetch a single post by id.
     *
     * @param int $id
     * @return self|null
     */
    public static function get(int $id): ?self {
        global $DB;
        $record = $DB->get_record('local_imageblog_posts', ['id' => $id]);
        return $record ? self::from_record($record) : null;
    }

    /**
     * Fetch posts for the listing page.
     *
     * By default returns only published posts. Pass `statuses` to widen the
     * window (e.g. include drafts), and pass `mineonly` + `viewerid` to scope
     * the result to a single author — used so non-managers can find their
     * own drafts without seeing other authors' unpublished work.
     *
     * @param array $filters Keys: authorid, categoryid, subcategoryid, tagid,
     *                       levelid, keyword, datefrom, dateto, page,
     *                       statuses (string[]), mineonly (bool),
     *                       viewerid (int)
     * @param int   $perpage
     * @return array{posts: self[], total: int}
     */
    public static function get_published(array $filters = [], int $perpage = 12): array {
        global $DB;

        $statuses = (array)($filters['statuses'] ?? [self::STATUS_PUBLISHED]);
        $statuses = array_values(array_intersect($statuses, [
            self::STATUS_PUBLISHED,
            self::STATUS_DRAFT,
            self::STATUS_ARCHIVED,
        ]));
        if (!$statuses) {
            $statuses = [self::STATUS_PUBLISHED];
        }
        [$statussql, $statusparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'st');
        $params = $statusparams;
        $where  = ["p.status $statussql"];
        $joins  = '';

        if (!empty($filters['mineonly']) && !empty($filters['viewerid'])) {
            $where[]            = 'p.authorid = :viewerid';
            $params['viewerid'] = (int)$filters['viewerid'];
        }

        if (!empty($filters['authorid'])) {
            $where[]            = 'p.authorid = :authorid';
            $params['authorid'] = (int)$filters['authorid'];
        }

        if (!empty($filters['categoryid']) || !empty($filters['subcategoryid'])) {
            $joins .= ' JOIN {local_imageblog_post_cats} pc ON pc.postid = p.id ';
            if (!empty($filters['categoryid'])) {
                $where[]              = 'pc.categoryid = :categoryid';
                $params['categoryid'] = (int)$filters['categoryid'];
            }
            if (!empty($filters['subcategoryid'])) {
                $where[]                 = 'pc.subcategoryid = :subcategoryid';
                $params['subcategoryid'] = (int)$filters['subcategoryid'];
            }
        }

        if (!empty($filters['tagid'])) {
            $joins .= ' JOIN {local_imageblog_post_tags} pt ON pt.postid = p.id ';
            $where[]         = 'pt.tagid = :tagid';
            $params['tagid'] = (int)$filters['tagid'];
        }

        if (!empty($filters['levelid'])) {
            $joins .= ' JOIN {local_imageblog_post_levels} pl ON pl.postid = p.id ';
            $where[]           = 'pl.levelid = :levelid';
            $params['levelid'] = (int)$filters['levelid'];
        }

        if (!empty($filters['datefrom'])) {
            $where[]            = 'p.timepublished >= :datefrom';
            $params['datefrom'] = (int)$filters['datefrom'];
        }
        if (!empty($filters['dateto'])) {
            $where[]          = 'p.timepublished <= :dateto';
            $params['dateto'] = (int)$filters['dateto'];
        }

        if (!empty($filters['keyword'])) {
            $titlelike = $DB->sql_like('p.title', ':keyword_title', false);
            $taglike   = $DB->sql_like('t.name', ':keyword_tag', false);
            $where[] = "($titlelike OR EXISTS (
                            SELECT 1
                              FROM {local_imageblog_post_tags} kt
                              JOIN {local_imageblog_tags} t ON t.id = kt.tagid
                             WHERE kt.postid = p.id AND $taglike
                        ))";
            $kw = '%' . $DB->sql_like_escape($filters['keyword']) . '%';
            $params['keyword_title'] = $kw;
            $params['keyword_tag']   = $kw;
        }

        $wheresql = implode(' AND ', $where);
        $page     = max(0, (int)($filters['page'] ?? 0));

        // Postgres requires DISTINCT ORDER BY columns to appear in the
        // SELECT list, so expose the sort key explicitly.
        $sql = "SELECT DISTINCT p.*,
                       COALESCE(p.timepublished, p.timemodified, p.timecreated) AS sortkey
                  FROM {local_imageblog_posts} p
                  $joins
                 WHERE $wheresql
              ORDER BY sortkey DESC, p.id DESC";

        $countsql = "SELECT COUNT(DISTINCT p.id)
                       FROM {local_imageblog_posts} p
                       $joins
                      WHERE $wheresql";

        $total   = (int)$DB->count_records_sql($countsql, $params);
        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $posts   = array_values(array_map([self::class, 'from_record'], $records));

        return ['posts' => $posts, 'total' => $total];
    }

    /**
     * Save a new or updated post, including editor and filemanager content.
     *
     * @param object   $data    Form data from post_form
     * @param \context $context System context
     * @return int Post id
     */
    public static function save(object $data, \context $context): int {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/lib/filelib.php');
        require_once($CFG->dirroot . '/lib/formslib.php');

        $now = time();
        $isnew = empty($data->id);

        $record = new \stdClass();
        $record->title       = $data->title;
        $record->summary     = $data->summary ?? '';
        $record->status      = $data->status ?? self::STATUS_DRAFT;
        $record->lazyimages  = !empty($data->lazyimages) ? 1 : 0;
        $record->bodyformat  = FORMAT_HTML;
        $record->body        = '';
        $record->timemodified = $now;
        $record->posttype    = !empty($data->posttype) && $data->posttype === case_post::TYPE_CASE
            ? case_post::TYPE_CASE
            : case_post::TYPE_BLOG;
        if ($record->posttype === case_post::TYPE_CASE) {
            $record->casedifficulty = max(1, min(5, (int)($data->casedifficulty ?? 1)));
        }

        if ($isnew) {
            $record->authorid    = (int)$USER->id;
            $record->timecreated = $now;
            $record->timepublished = ($record->status === self::STATUS_PUBLISHED) ? $now : null;
            $record->id = (int)$DB->insert_record('local_imageblog_posts', $record);
        } else {
            $record->id = (int)$data->id;
            $existing = $DB->get_record('local_imageblog_posts', ['id' => $record->id], '*', MUST_EXIST);
            if (
                $existing->status !== self::STATUS_PUBLISHED
                && $record->status === self::STATUS_PUBLISHED
            ) {
                $record->timepublished = $now;
            }
            $DB->update_record('local_imageblog_posts', $record);
        }

        // Process body editor (text + draft files for embedded images).
        if (!empty($data->body_editor)) {
            $data->id = $record->id;
            $data = file_postupdate_standard_editor(
                $data,
                'body',
                self::editor_options($context),
                $context,
                'local_imageblog',
                self::FILEAREA_BODY,
                $record->id
            );
            $DB->update_record('local_imageblog_posts', (object)[
                'id'         => $record->id,
                'body'       => $data->body,
                'bodyformat' => $data->bodyformat,
            ]);
        }

        // Process the case outcome editor when this is a case post.
        if ($record->posttype === case_post::TYPE_CASE && !empty($data->caseoutcome_editor)) {
            $data->id = $record->id;
            $data = file_postupdate_standard_editor(
                $data,
                'caseoutcome',
                self::editor_options($context),
                $context,
                'local_imageblog',
                self::FILEAREA_CASEOUTCOME,
                $record->id
            );
            $DB->update_record('local_imageblog_posts', (object)[
                'id'                => $record->id,
                'caseoutcome'       => $data->caseoutcome,
                'caseoutcomeformat' => $data->caseoutcomeformat,
            ]);
        }

        // Save featured image draft files into the permanent area.
        if (!empty($data->featured_image)) {
            file_save_draft_area_files(
                (int)$data->featured_image,
                $context->id,
                'local_imageblog',
                self::FILEAREA_FEATURED,
                $record->id,
                self::featured_options()
            );
        }

        // Save 360° panorama draft files into the permanent area.
        if (!empty($data->panorama_image)) {
            file_save_draft_area_files(
                (int)$data->panorama_image,
                $context->id,
                'local_imageblog',
                self::FILEAREA_PANORAMA,
                $record->id,
                self::panorama_options()
            );
        }

        $categoryid    = !empty($data->categoryid) ? (int)$data->categoryid : null;
        $subcategoryid = !empty($data->subcategoryid) ? (int)$data->subcategoryid : null;
        $tagids        = isset($data->tagids) && is_array($data->tagids)
            ? self::resolve_tagids($data->tagids)
            : [];
        $levelids      = isset($data->levelids) && is_array($data->levelids) ? $data->levelids : [];
        self::set_taxonomy($record->id, $categoryid, $subcategoryid, $tagids, $levelids);

        return $record->id;
    }

    /**
     * Standard editor options for the body field.
     *
     * @param \context $context
     * @return array
     */
    public static function editor_options(\context $context): array {
        global $CFG;
        return [
            'maxfiles'  => 20,
            'maxbytes'  => $CFG->maxbytes,
            'trusttext' => false,
            'context'   => $context,
            'subdirs'   => false,
        ];
    }

    /**
     * Filemanager options for the featured image.
     *
     * @return array
     */
    public static function featured_options(): array {
        return [
            'maxbytes'       => 2097152,
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp'],
            'maxfiles'       => 1,
            'subdirs'        => 0,
        ];
    }

    /**
     * Filemanager options for a 360° panorama image. Allows larger files
     * because equirectangular sources are typically high-resolution.
     *
     * @return array
     */
    public static function panorama_options(): array {
        return [
            'maxbytes'       => 20 * 1024 * 1024,
            'accepted_types' => ['.jpg', '.jpeg', '.png'],
            'maxfiles'       => 1,
            'subdirs'        => 0,
        ];
    }

    /**
     * Update the status of a single post in place.
     *
     * @param int $postid
     * @param string $status One of self::STATUS_*
     */
    public static function set_status(int $postid, string $status): void {
        global $DB;
        $allowed = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];
        if (!in_array($status, $allowed, true)) {
            throw new \moodle_exception('error_invalidstatus', 'local_imageblog');
        }
        $DB->update_record('local_imageblog_posts', (object)[
            'id'           => $postid,
            'status'       => $status,
            'timemodified' => time(),
        ]);
    }

    /**
     * Convert a mixed array of tag ids and free-text tag names (as produced by
     * the autocomplete element with tags=true) into a list of existing tag ids,
     * creating any new tags on the fly.
     *
     * @param array $values
     * @return int[]
     */
    private static function resolve_tagids(array $values): array {
        global $DB;
        $ids = [];
        foreach ($values as $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if (is_numeric($value)) {
                $ids[] = (int)$value;
                continue;
            }
            $name = trim((string)$value);
            if ($name === '') {
                continue;
            }
            $slug = taxonomy::slugify($name);
            $existing = $DB->get_record('local_imageblog_tags', ['slug' => $slug], 'id');
            if ($existing) {
                $ids[] = (int)$existing->id;
                continue;
            }
            $ids[] = taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => $name]);
        }
        return array_values(array_unique($ids));
    }

    /**
     * Replace the taxonomy associations for this post.
     *
     * @param int      $postid
     * @param int|null $categoryid
     * @param int|null $subcategoryid
     * @param int[]    $tagids
     * @param int[]    $levelids
     */
    public static function set_taxonomy(
        int $postid,
        ?int $categoryid,
        ?int $subcategoryid,
        array $tagids,
        array $levelids
    ): void {
        global $DB;

        $DB->delete_records('local_imageblog_post_cats', ['postid' => $postid]);
        $DB->delete_records('local_imageblog_post_tags', ['postid' => $postid]);
        $DB->delete_records('local_imageblog_post_levels', ['postid' => $postid]);

        if ($categoryid) {
            $DB->insert_record('local_imageblog_post_cats', (object)[
                'postid'        => $postid,
                'categoryid'    => $categoryid,
                'subcategoryid' => $subcategoryid ?: null,
            ]);
        }

        foreach (array_unique(array_filter(array_map('intval', $tagids))) as $tagid) {
            $DB->insert_record('local_imageblog_post_tags', (object)[
                'postid' => $postid,
                'tagid'  => $tagid,
            ]);
        }

        foreach (array_unique(array_filter(array_map('intval', $levelids))) as $levelid) {
            $DB->insert_record('local_imageblog_post_levels', (object)[
                'postid'  => $postid,
                'levelid' => $levelid,
            ]);
        }
    }

    /**
     * Currently-associated category id (or null), and subcategory id.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function get_category_ids(): array {
        global $DB;
        $row = $DB->get_record(
            'local_imageblog_post_cats',
            ['postid' => $this->id],
            'categoryid, subcategoryid',
            IGNORE_MISSING
        );
        if (!$row) {
            return [null, null];
        }
        return [(int)$row->categoryid, $row->subcategoryid ? (int)$row->subcategoryid : null];
    }

    /**
     * Tag ids attached to this post.
     *
     * @return int[]
     */
    public function get_tag_ids(): array {
        global $DB;
        return array_map('intval', array_values($DB->get_fieldset_select(
            'local_imageblog_post_tags',
            'tagid',
            'postid = :postid',
            ['postid' => $this->id]
        )));
    }

    /**
     * Level ids attached to this post.
     *
     * @return int[]
     */
    public function get_level_ids(): array {
        global $DB;
        return array_map('intval', array_values($DB->get_fieldset_select(
            'local_imageblog_post_levels',
            'levelid',
            'postid = :postid',
            ['postid' => $this->id]
        )));
    }

    /**
     * Tags attached to this post.
     *
     * @return \stdClass[]
     */
    public function get_tags(): array {
        global $DB;
        $sql = "SELECT t.*
                  FROM {local_imageblog_tags} t
                  JOIN {local_imageblog_post_tags} pt ON pt.tagid = t.id
                 WHERE pt.postid = :postid
              ORDER BY t.name";
        return array_values($DB->get_records_sql($sql, ['postid' => $this->id]));
    }

    /**
     * Difficulty levels attached to this post.
     *
     * @return \stdClass[]
     */
    public function get_levels(): array {
        global $DB;
        $sql = "SELECT l.*
                  FROM {local_imageblog_levels} l
                  JOIN {local_imageblog_post_levels} pl ON pl.levelid = l.id
                 WHERE pl.postid = :postid
              ORDER BY l.sortorder";
        return array_values($DB->get_records_sql($sql, ['postid' => $this->id]));
    }

    /**
     * URL to the featured image, or null if none.
     *
     * @return \moodle_url|null
     */
    public function get_featured_image_url(): ?\moodle_url {
        $context = \context_system::instance();
        $fs      = get_file_storage();
        $files   = $fs->get_area_files(
            $context->id,
            'local_imageblog',
            self::FILEAREA_FEATURED,
            $this->id,
            'itemid, filepath, filename',
            false
        );
        if (!$files) {
            return null;
        }
        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * URL to the 360° panorama image, or null if none.
     *
     * @return \moodle_url|null
     */
    public function get_panorama_url(): ?\moodle_url {
        $context = \context_system::instance();
        $fs      = get_file_storage();
        $files   = $fs->get_area_files(
            $context->id,
            'local_imageblog',
            self::FILEAREA_PANORAMA,
            $this->id,
            'itemid, filepath, filename',
            false
        );
        if (!$files) {
            return null;
        }
        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Build a post object from a DB record.
     *
     * @param \stdClass $record
     * @return self
     */
    private static function from_record(\stdClass $record): self {
        $post                = new self();
        $post->id            = (int)$record->id;
        $post->authorid      = (int)$record->authorid;
        $post->title         = $record->title;
        $post->summary       = $record->summary ?? '';
        $post->body          = $record->body ?? '';
        $post->bodyformat    = (int)($record->bodyformat ?? FORMAT_HTML);
        $post->status        = $record->status;
        $post->timepublished = isset($record->timepublished) ? (int)$record->timepublished : null;
        $post->timecreated   = (int)$record->timecreated;
        $post->timemodified  = (int)$record->timemodified;
        $post->lazyimages    = !empty($record->lazyimages);
        $post->forumpostid   = isset($record->forumpostid) ? (int)$record->forumpostid : null;
        $post->featuredimage = isset($record->featuredimage) ? (int)$record->featuredimage : null;
        $post->posttype          = $record->posttype ?? 'blog';
        $post->caseoutcome       = $record->caseoutcome ?? '';
        $post->caseoutcomeformat = (int)($record->caseoutcomeformat ?? FORMAT_HTML);
        $post->caserevealed      = !empty($record->caserevealed);
        $post->caserevealedtime  = isset($record->caserevealedtime) ? (int)$record->caserevealedtime : null;
        $post->casebestdiagnosisid = isset($record->casebestdiagnosisid) ? (int)$record->casebestdiagnosisid : null;
        $post->casedifficulty    = (int)($record->casedifficulty ?? 1);
        return $post;
    }
}
