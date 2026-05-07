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
 * Output renderer.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\output;

use local_imageblog\post;
use moodle_url;
use plugin_renderer_base;

/**
 * Renderer for local_imageblog.
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the listing page.
     *
     * @param post[] $posts
     * @param int    $total
     * @param int    $page    Current page (0-indexed)
     * @param int    $perpage
     * @param array  $filters
     * @param array  $taxonomy
     * @return string
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
            'cards'      => $cards,
            'hascards'   => !empty($cards),
            'total'      => $total,
            'filterbar'  => $this->build_filter_context($filters, $taxonomy),
            'pagination' => $this->build_pagination($total, $page, $perpage, $filters),
            'cancreate'  => has_capability('local/imageblog:createpost', \context_system::instance()),
            'newposturl' => (new moodle_url('/local/imageblog/edit.php'))->out(false),
        ];

        return $this->render_from_template('local_imageblog/listing', $context);
    }

    /**
     * Render a single post page.
     *
     * @param post $post
     * @return string
     */
    public function render_post(post $post): string {
        global $DB;

        $author = $DB->get_record('user', ['id' => $post->authorid],
            'id, firstname, lastname', IGNORE_MISSING);

        $imgurl = $post->get_featured_image_url();

        $context = [
            'id'            => $post->id,
            'title'         => format_string($post->title),
            'body'          => format_text($post->body, $post->bodyformat, [
                'context' => \context_system::instance(),
            ]),
            'authorname'    => $author ? fullname($author) : '',
            'datepublished' => $post->timepublished
                ? userdate($post->timepublished, get_string('strftimedate', 'langconfig'))
                : '',
            'hasimage'      => !empty($imgurl),
            'imageurl'      => $imgurl ? $imgurl->out(false) : '',
            'listingurl'    => (new moodle_url('/local/imageblog/index.php'))->out(false),
            'lazyimages'    => !empty($post->lazyimages),
        ];

        return $this->render_from_template('local_imageblog/post', $context);
    }

    /**
     * Build the Mustache context for a single card.
     *
     * @param post $post
     * @return array
     */
    private function build_card_context(post $post): array {
        global $DB;

        $author = $DB->get_record('user', ['id' => $post->authorid],
            'id, firstname, lastname', IGNORE_MISSING);
        $imgurl = $post->get_featured_image_url();
        $tags   = $post->get_tags();
        $levels = $post->get_levels();

        return [
            'id'            => $post->id,
            'viewurl'       => (new moodle_url('/local/imageblog/view.php', ['id' => $post->id]))->out(false),
            'title'         => format_string($post->title),
            'summary'       => format_string($post->summary),
            'authorname'    => $author ? fullname($author) : '',
            'datepublished' => $post->timepublished
                ? userdate($post->timepublished, get_string('strftimedate', 'langconfig'))
                : '',
            'hasimage'      => !empty($imgurl),
            'imageurl'      => $imgurl ? $imgurl->out(false) : '',
            'tags'          => array_map(fn($t) => ['name' => format_string($t->name)], $tags),
            'levels'        => array_map(fn($l) => [
                'name'      => format_string($l->name),
                'colourkey' => $l->colourkey,
            ], $levels),
            'hastags'       => !empty($tags),
            'haslevels'     => !empty($levels),
        ];
    }

    /**
     * Build the filter-bar context, marking the currently-selected option
     * in each dropdown so the form can reflect state across requests.
     *
     * @param array $filters
     * @param array $taxonomy
     * @return array
     */
    private function build_filter_context(array $filters, array $taxonomy): array {
        $mark = function (array $items, int $current): array {
            return array_map(function ($item) use ($current) {
                return [
                    'id'       => $item['id'],
                    'name'     => $item['name'],
                    'selected' => ((int)$item['id'] === $current),
                ];
            }, $items);
        };

        return [
            'authors'    => $mark($taxonomy['authors']    ?? [], (int)($filters['authorid']   ?? 0)),
            'categories' => $mark($taxonomy['categories'] ?? [], (int)($filters['categoryid'] ?? 0)),
            'tags'       => $mark($taxonomy['tags']       ?? [], (int)($filters['tagid']      ?? 0)),
            'keyword'    => $filters['keyword'] ?? '',
            'formaction' => (new moodle_url('/local/imageblog/index.php'))->out(false),
        ];
    }

    /**
     * Build pagination context, preserving current filters in URLs.
     *
     * @param int   $total
     * @param int   $page
     * @param int   $perpage
     * @param array $filters
     * @return array
     */
    private function build_pagination(int $total, int $page, int $perpage, array $filters): array {
        $totalpages = (int)ceil($total / max(1, $perpage));
        if ($totalpages <= 1) {
            return ['show' => false];
        }

        $base = $filters;
        unset($base['page']);

        $url = function (int $p) use ($base): string {
            return (new moodle_url('/local/imageblog/index.php', $base + ['page' => $p]))->out(false);
        };

        $pages = [];
        for ($i = 0; $i < $totalpages; $i++) {
            $pages[] = [
                'num'    => $i + 1,
                'active' => ($i === $page),
                'url'    => $url($i),
            ];
        }

        return [
            'show'    => true,
            'pages'   => $pages,
            'hasprev' => $page > 0,
            'hasnext' => $page < $totalpages - 1,
            'prevurl' => $url(max(0, $page - 1)),
            'nexturl' => $url(min($totalpages - 1, $page + 1)),
        ];
    }
}
