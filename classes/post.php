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
     * Fetch published posts for the listing page.
     *
     * @param array $filters Keys: authorid, categoryid, tagid, keyword, page
     * @param int   $perpage
     * @return array{posts: self[], total: int}
     */
    public static function get_published(array $filters = [], int $perpage = 12): array {
        global $DB;

        $params = ['status' => self::STATUS_PUBLISHED];
        $where  = ['p.status = :status'];
        $joins  = '';

        if (!empty($filters['authorid'])) {
            $where[]            = 'p.authorid = :authorid';
            $params['authorid'] = (int)$filters['authorid'];
        }

        if (!empty($filters['categoryid'])) {
            $joins .= ' JOIN {local_imageblog_post_cats} pc ON pc.postid = p.id ';
            $where[]              = 'pc.categoryid = :categoryid';
            $params['categoryid'] = (int)$filters['categoryid'];
        }

        if (!empty($filters['tagid'])) {
            $joins .= ' JOIN {local_imageblog_post_tags} pt ON pt.postid = p.id ';
            $where[]         = 'pt.tagid = :tagid';
            $params['tagid'] = (int)$filters['tagid'];
        }

        if (!empty($filters['keyword'])) {
            $where[]           = $DB->sql_like('p.title', ':keyword', false);
            $params['keyword'] = '%' . $DB->sql_like_escape($filters['keyword']) . '%';
        }

        $wheresql = implode(' AND ', $where);
        $page     = max(0, (int)($filters['page'] ?? 0));

        $sql = "SELECT DISTINCT p.*
                  FROM {local_imageblog_posts} p
                  $joins
                 WHERE $wheresql
              ORDER BY p.timepublished DESC, p.id DESC";

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

        $categoryid    = !empty($data->categoryid) ? (int)$data->categoryid : null;
        $subcategoryid = !empty($data->subcategoryid) ? (int)$data->subcategoryid : null;
        $tagids        = isset($data->tagids)   && is_array($data->tagids) ? $data->tagids : [];
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
        return $post;
    }
}
