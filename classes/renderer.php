<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Output renderer.
 *
 * @package   local_scca_blog
 * @copyright 2026 Skin Cancer College of Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scca_blog\output;

defined('MOODLE_INTERNAL') || die();

use local_scca_blog\post;
use moodle_url;
use plugin_renderer_base;

/**
 * Renderer for local_scca_blog.
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the full listing page (filter bar + card grid + pagination).
     *
     * @param array  $posts    Array of post objects
     * @param int    $total    Total matching posts (for pagination)
     * @param int    $page     Current page (0-indexed)
     * @param int    $perpage
     * @param array  $filters  Current filter values
     * @param array  $taxonomy Authors, categories, tags for filter dropdowns
     * @return string HTML
     */
    public function render_listing(
        array $posts,
        int $total,
        int $page,
        int $perpage,
        array $filters,
        array $taxonomy
    ): string {

        $cards = [];
        foreach ($posts as $p) {
            $cards[] = $this->build_card_context($p);
        }

        $context = [
            'cards'        => $cards,
            'total'        => $total,
            'filterbar'    => $this->build_filter_context($filters, $taxonomy),
            'pagination'   => $this->build_pagination($total, $page, $perpage),
            'newposturl'   => has_capability('local/scca_blog:createpost', \context_system::instance())
                                ? (new moodle_url('/local/scca_blog/edit.php'))->out(false)
                                : null,
        ];

        return $this->render_from_template('local_scca_blog/listing', $context);
    }

    /**
     * Build the Mustache context array for a single card.
     *
     * @param post $post
     * @return array
     */
    private function build_card_context(post $post): array {
        global $DB;

        $author  = $DB->get_record('user', ['id' => $post->authorid], 'id, firstname, lastname', IGNORE_MISSING);
        $imgurl  = $post->get_featured_image_url();
        $tags    = $post->get_tags();
        $levels  = $post->get_levels();

        return [
            'id'           => $post->id,
            'viewurl'      => (new moodle_url('/local/scca_blog/view.php', ['id' => $post->id]))->out(false),
            'title'        => format_string($post->title),
            'summary'      => format_string($post->summary),
            'authorname'   => $author ? fullname($author) : '',
            'datepublished'=> $post->timepublished ? userdate($post->timepublished, get_string('strftimedate', 'langconfig')) : '',
            'hasimage'     => !empty($imgurl),
            'imageurl'     => $imgurl ? $imgurl->out(false) : '',
            'tags'         => array_map(fn($t) => ['name' => format_string($t->name)], $tags),
            'levels'       => array_map(fn($l) => [
                                'name'      => format_string($l->name),
                                'colourkey' => $l->colourkey,
                              ], $levels),
            'hastags'      => !empty($tags),
            'haslevels'    => !empty($levels),
        ];
    }

    /**
     * Build Mustache context for the filter bar.
     */
    private function build_filter_context(array $filters, array $taxonomy): array {
        return [
            'authors'    => $taxonomy['authors'] ?? [],
            'categories' => $taxonomy['categories'] ?? [],
            'tags'       => $taxonomy['tags'] ?? [],
            'current'    => $filters,
            'formaction' => (new moodle_url('/local/scca_blog/index.php'))->out(false),
        ];
    }

    /**
     * Build simple pagination context.
     */
    private function build_pagination(int $total, int $page, int $perpage): array {
        $totalpages = (int)ceil($total / $perpage);
        if ($totalpages <= 1) {
            return ['show' => false];
        }

        $pages = [];
        for ($i = 0; $i < $totalpages; $i++) {
            $pages[] = [
                'num'    => $i + 1,
                'active' => ($i === $page),
                'url'    => (new moodle_url('/local/scca_blog/index.php', ['page' => $i]))->out(false),
            ];
        }

        return [
            'show'     => true,
            'pages'    => $pages,
            'hasprev'  => $page > 0,
            'hasnext'  => $page < $totalpages - 1,
            'prevurl'  => (new moodle_url('/local/scca_blog/index.php', ['page' => $page - 1]))->out(false),
            'nexturl'  => (new moodle_url('/local/scca_blog/index.php', ['page' => $page + 1]))->out(false),
        ];
    }
}
