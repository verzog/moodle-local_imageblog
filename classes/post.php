<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Post model class.
 *
 * @package   local_scca_blog
 * @copyright 2026 Skin Cancer College of Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scca_blog;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a single blog post and provides data-access methods.
 */
class post {

    /** @var int */
    public int $id;
    /** @var int */
    public int $authorid;
    /** @var string */
    public string $title;
    /** @var string */
    public string $summary;
    /** @var string */
    public string $body;
    /** @var int */
    public int $bodyformat;
    /** @var string draft|published|archived */
    public string $status;
    /** @var int|null */
    public ?int $timepublished;
    /** @var int */
    public int $timecreated;
    /** @var int */
    public int $timemodified;
    /** @var bool */
    public bool $lazy_images;
    /** @var int|null */
    public ?int $forumpostid;
    /** @var int|null */
    public ?int $featuredimage;

    /** Status constants */
    const STATUS_DRAFT     = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED  = 'archived';

    /**
     * Fetch a single post by id.
     *
     * @param int $id
     * @return self|null
     */
    public static function get(int $id): ?self {
        global $DB;
        $record = $DB->get_record('local_scca_blog_posts', ['id' => $id]);
        if (!$record) {
            return null;
        }
        return self::from_record($record);
    }

    /**
     * Fetch published posts for the listing page.
     *
     * @param array $filters  Keys: authorid, categoryid, tagid, keyword, page
     * @param int   $perpage
     * @return array{posts: self[], total: int}
     */
    public static function get_published(array $filters = [], int $perpage = 12): array {
        global $DB;

        $params = ['status' => self::STATUS_PUBLISHED];
        $where  = ['p.status = :status'];

        if (!empty($filters['authorid'])) {
            $where[]            = 'p.authorid = :authorid';
            $params['authorid'] = (int)$filters['authorid'];
        }

        if (!empty($filters['keyword'])) {
            $where[]            = $DB->sql_like('p.title', ':keyword', false);
            $params['keyword']  = '%' . $DB->sql_like_escape($filters['keyword']) . '%';
        }

        $wheresql = implode(' AND ', $where);
        $page     = max(0, (int)($filters['page'] ?? 0));

        $sql = "SELECT p.*
                  FROM {local_scca_blog_posts} p
                 WHERE {$wheresql}
              ORDER BY p.timepublished DESC";

        $total = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_scca_blog_posts} p WHERE {$wheresql}",
            $params
        );

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $posts   = array_map([self::class, 'from_record'], $records);

        return ['posts' => $posts, 'total' => $total];
    }

    /**
     * Save a new or updated post.
     *
     * @param object $data Form data
     * @return int Post id
     */
    public static function save(object $data): int {
        global $DB, $USER;

        $now = time();

        if (!empty($data->id)) {
            // Update.
            $data->timemodified = $now;
            $DB->update_record('local_scca_blog_posts', $data);
            return (int)$data->id;
        }

        // Insert.
        $data->authorid    = $USER->id;
        $data->timecreated = $now;
        $data->timemodified = $now;
        $data->status      = $data->status ?? self::STATUS_DRAFT;

        return (int)$DB->insert_record('local_scca_blog_posts', $data);
    }

    /**
     * Return tags attached to this post.
     *
     * @return \stdClass[]
     */
    public function get_tags(): array {
        global $DB;
        $sql = "SELECT t.*
                  FROM {local_scca_blog_tags} t
                  JOIN {local_scca_blog_post_tags} pt ON pt.tagid = t.id
                 WHERE pt.postid = :postid";
        return array_values($DB->get_records_sql($sql, ['postid' => $this->id]));
    }

    /**
     * Return difficulty levels attached to this post.
     *
     * @return \stdClass[]
     */
    public function get_levels(): array {
        global $DB;
        $sql = "SELECT l.*
                  FROM {local_scca_blog_levels} l
                  JOIN {local_scca_blog_post_levels} pl ON pl.levelid = l.id
                 WHERE pl.postid = :postid";
        return array_values($DB->get_records_sql($sql, ['postid' => $this->id]));
    }

    /**
     * Return the URL to the featured image, or null.
     *
     * @return \moodle_url|null
     */
    public function get_featured_image_url(): ?\moodle_url {
        global $CFG;
        if (!$this->featuredimage) {
            return null;
        }
        $context = \context_system::instance();
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_scca_blog',
            'featured_image',
            $this->id,
            '/',
            'featured.jpg'
        );
    }

    /**
     * Build a post object from a DB record.
     *
     * @param \stdClass $record
     * @return self
     */
    private static function from_record(\stdClass $record): self {
        $post               = new self();
        $post->id           = (int)$record->id;
        $post->authorid     = (int)$record->authorid;
        $post->title        = $record->title;
        $post->summary      = $record->summary ?? '';
        $post->body         = $record->body ?? '';
        $post->bodyformat   = (int)($record->bodyformat ?? FORMAT_HTML);
        $post->status       = $record->status;
        $post->timepublished  = isset($record->timepublished) ? (int)$record->timepublished : null;
        $post->timecreated    = (int)$record->timecreated;
        $post->timemodified   = (int)$record->timemodified;
        $post->lazy_images    = (bool)$record->lazy_images;
        $post->forumpostid    = isset($record->forumpostid) ? (int)$record->forumpostid : null;
        $post->featuredimage  = isset($record->featuredimage) ? (int)$record->featuredimage : null;
        return $post;
    }
}
