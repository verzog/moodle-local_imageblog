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

        // Build a categoryid -> [{id, name}] JSON map for the dependent
        // subcategory dropdown.
        $subcatmap = [];
        foreach (($taxonomy['subcategories'] ?? []) as $sub) {
            $catid = (int)$sub['categoryid'];
            $subcatmap[$catid] ??= [];
            $subcatmap[$catid][] = ['id' => (int)$sub['id'], 'name' => $sub['name']];
        }

        $context = [
            'cards'      => $cards,
            'hascards'   => !empty($cards),
            'total'      => $total,
            'filterbar'  => $this->build_filter_context($filters, $taxonomy),
            'pagination' => $this->build_pagination($total, $page, $perpage, $filters),
            'cancreate'  => has_capability('local/imageblog:createpost', \context_system::instance()),
            'newposturl' => (new moodle_url('/local/imageblog/edit.php'))->out(false),
            'subcatdata' => s(json_encode($subcatmap)),
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

        $author = $DB->get_record(
            'user',
            ['id' => $post->authorid],
            'id, firstname, lastname',
            IGNORE_MISSING
        );

        $imgurl = $post->get_featured_image_url();
        $syscontext = \context_system::instance();
        $canmanage = has_capability('local/imageblog:editanypost', $syscontext);
        $ispublished = ($post->status === post::STATUS_PUBLISHED);

        $context = [
            'id'            => $post->id,
            'title'         => format_string($post->title),
            'body'          => format_text($post->body, $post->bodyformat, [
                'context' => $syscontext,
            ]),
            'authorname'    => $author ? fullname($author) : '',
            'datepublished' => $post->timepublished
                ? userdate($post->timepublished, get_string('strftimedate', 'langconfig'))
                : '',
            'hasimage'      => !empty($imgurl),
            'imageurl'      => $imgurl ? $imgurl->out(false) : '',
            'listingurl'    => (new moodle_url('/local/imageblog/index.php'))->out(false),
            'lazyimages'    => !empty($post->lazyimages),
            'isdraft'       => !$ispublished,
            'statuslabel'   => get_string('status_' . $post->status, 'local_imageblog'),
            'canunpublish'  => $canmanage && $ispublished,
            'unpublishurl'  => (new moodle_url(
                '/local/imageblog/view.php',
                ['id' => $post->id, 'action' => 'unpublish', 'sesskey' => sesskey()]
            ))->out(false),
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

        $author = $DB->get_record(
            'user',
            ['id' => $post->authorid],
            'id, firstname, lastname',
            IGNORE_MISSING
        );
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

        $currentcategory = (int)($filters['categoryid'] ?? 0);
        $subcategories = [];
        foreach (($taxonomy['subcategories'] ?? []) as $sub) {
            if ($currentcategory && (int)$sub['categoryid'] !== $currentcategory) {
                continue;
            }
            $subcategories[] = [
                'id'       => $sub['id'],
                'name'     => $sub['name'],
                'selected' => ((int)$sub['id'] === (int)($filters['subcategoryid'] ?? 0)),
            ];
        }

        return [
            'authors'        => $mark($taxonomy['authors'] ?? [], (int)($filters['authorid'] ?? 0)),
            'categories'     => $mark($taxonomy['categories'] ?? [], $currentcategory),
            'subcategories'  => $subcategories,
            'hassubcats'     => !empty($subcategories),
            'tags'           => $mark($taxonomy['tags'] ?? [], (int)($filters['tagid'] ?? 0)),
            'levels'         => $mark($taxonomy['levels'] ?? [], (int)($filters['levelid'] ?? 0)),
            'haslevels'      => !empty($taxonomy['levels'] ?? []),
            'keyword'        => $filters['keyword'] ?? '',
            'datefrom'       => $filters['datefromraw'] ?? '',
            'dateto'         => $filters['datetoraw'] ?? '',
            'formaction'     => (new moodle_url('/local/imageblog/index.php'))->out(false),
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

        // Map our internal filter shape back to the URL param shape: drop
        // page + datetimes, swap raw date strings into datefrom/dateto.
        $base = $filters;
        unset($base['page'], $base['datefrom'], $base['dateto']);
        if (!empty($base['datefromraw'])) {
            $base['datefrom'] = $base['datefromraw'];
        }
        if (!empty($base['datetoraw'])) {
            $base['dateto'] = $base['datetoraw'];
        }
        unset($base['datefromraw'], $base['datetoraw']);

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

    /**
     * Render the taxonomy admin index for the given type.
     *
     * @param string $type One of \local_imageblog\taxonomy::TYPE_*
     * @param \stdClass[] $items
     * @return string
     */
    public function render_taxonomy_index(string $type, array $items): string {
        global $DB;

        $rows = [];
        foreach ($items as $item) {
            $row = [
                'id'   => (int)$item->id,
                'name' => format_string($item->name),
            ];
            if (property_exists($item, 'sortorder')) {
                $row['sortorder'] = (int)$item->sortorder;
            }
            if (property_exists($item, 'slug')) {
                $row['slug'] = $item->slug;
            }
            if (property_exists($item, 'colourkey')) {
                $row['colourkey'] = $item->colourkey;
            }
            if (property_exists($item, 'categoryid')) {
                $parent = $DB->get_field('local_imageblog_categories', 'name', ['id' => $item->categoryid]);
                $row['parentname'] = $parent ? format_string($parent) : '';
            }
            $row['editurl'] = (new moodle_url(
                '/local/imageblog/manage.php',
                ['type' => $type, 'action' => 'edit', 'id' => (int)$item->id]
            ))->out(false);
            $row['deleteurl'] = (new moodle_url(
                '/local/imageblog/manage.php',
                ['type' => $type, 'action' => 'delete', 'id' => (int)$item->id, 'sesskey' => sesskey()]
            ))->out(false);
            $rows[] = $row;
        }

        $context = [
            'type'      => $type,
            'rows'      => $rows,
            'hasrows'   => !empty($rows),
            'addurl'    => (new moodle_url(
                '/local/imageblog/manage.php',
                ['type' => $type, 'action' => 'add']
            ))->out(false),
            'showslug'  => $type === \local_imageblog\taxonomy::TYPE_TAG,
            'showcolour' => $type === \local_imageblog\taxonomy::TYPE_LEVEL,
            'showparent' => $type === \local_imageblog\taxonomy::TYPE_SUBCATEGORY,
            'showorder'  => in_array($type, [
                \local_imageblog\taxonomy::TYPE_CATEGORY,
                \local_imageblog\taxonomy::TYPE_SUBCATEGORY,
                \local_imageblog\taxonomy::TYPE_LEVEL,
            ], true),
            'tabs'      => $this->build_taxonomy_tabs($type),
        ];

        return $this->render_from_template('local_imageblog/taxonomy_index', $context);
    }

    /**
     * Build the tab strip for the taxonomy admin pages.
     *
     * @param string $current
     * @return array[]
     */
    private function build_taxonomy_tabs(string $current): array {
        $types = [
            \local_imageblog\taxonomy::TYPE_CATEGORY,
            \local_imageblog\taxonomy::TYPE_SUBCATEGORY,
            \local_imageblog\taxonomy::TYPE_TAG,
            \local_imageblog\taxonomy::TYPE_LEVEL,
        ];
        $tabs = [];
        foreach ($types as $type) {
            $tabs[] = [
                'label'  => get_string('manage_' . $type, 'local_imageblog'),
                'url'    => (new moodle_url('/local/imageblog/manage.php', ['type' => $type]))->out(false),
                'active' => $type === $current,
            ];
        }
        return $tabs;
    }
}
