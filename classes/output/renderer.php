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
use local_imageblog\case_post;
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
            'subsenabled' => (bool)get_config('local_imageblog', 'subscriptions_enabled'),
            'subscribeurl' => (new moodle_url('/local/imageblog/subscribe.php'))->out(false),
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

        global $USER;

        $imgurl = $post->get_featured_image_url();
        $panourl = $post->get_panorama_url();
        $syscontext = \context_system::instance();
        $canmanage = has_capability('local/imageblog:editanypost', $syscontext);
        $isowner = ($post->authorid === (int)$USER->id);
        $canauthor = has_capability('local/imageblog:createpost', $syscontext);
        $canedit = $canmanage || ($isowner && $canauthor);
        $ispublished = ($post->status === post::STATUS_PUBLISHED);

        $context = [
            'id'            => $post->id,
            'title'         => format_string($post->title),
            'body'          => format_text(
                file_rewrite_pluginfile_urls(
                    $post->body,
                    'pluginfile.php',
                    $syscontext->id,
                    'local_imageblog',
                    post::FILEAREA_BODY,
                    $post->id
                ),
                $post->bodyformat,
                ['context' => $syscontext]
            ),
            'authorname'    => $author ? fullname($author) : '',
            'datepublished' => $post->timepublished
                ? userdate($post->timepublished, get_string('strftimedate', 'langconfig'))
                : '',
            'hasimage'      => !empty($imgurl),
            'imageurl'      => $imgurl ? $imgurl->out(false) : '',
            'haspanorama'   => !empty($panourl),
            'panoramaurl'   => $panourl ? $panourl->out(false) : '',
            'listingurl'    => (new moodle_url('/local/imageblog/index.php'))->out(false),
            'lazyimages'    => !empty($post->lazyimages),
            'isdraft'       => !$ispublished,
            'statuslabel'   => get_string('status_' . $post->status, 'local_imageblog'),
            'canunpublish'  => $canmanage && $ispublished,
            'unpublishurl'  => (new moodle_url(
                '/local/imageblog/view.php',
                ['id' => $post->id, 'action' => 'unpublish', 'sesskey' => sesskey()]
            ))->out(false),
            'canedit'       => $canedit,
            'editurl'       => (new moodle_url(
                '/local/imageblog/edit.php',
                ['id' => $post->id]
            ))->out(false),
            'iscase'        => $post->posttype === case_post::TYPE_CASE,
            'casepanel'     => $post->posttype === case_post::TYPE_CASE
                ? $this->build_case_context($post, (int)$USER->id, $isowner || $canmanage)
                : null,
        ];

        return $this->render_from_template('local_imageblog/post', $context);
    }

    /**
     * Render the subscription digest email for one recipient.
     *
     * @param post[]    $posts
     * @param \stdClass $user      Recipient user record.
     * @param string    $frequency Subscription frequency.
     * @return array{0: string, 1: string, 2: string} [html, text, subject]
     */
    public function render_digest_email(array $posts, \stdClass $user, string $frequency): array {
        global $SITE;

        $items = [];
        foreach ($posts as $p) {
            $imgurl = $p->get_featured_image_url();
            $items[] = [
                'title'    => format_string($p->title),
                'summary'  => format_string($p->summary),
                'hasimage' => !empty($imgurl),
                'imageurl' => $imgurl ? $imgurl->out(false) : '',
                'viewurl'  => (new moodle_url('/local/imageblog/view.php', ['id' => $p->id]))->out(false),
            ];
        }

        $sitename = format_string($SITE->fullname);
        $context = [
            'sitename'        => $sitename,
            'recipientname'   => fullname($user),
            'frequencylabel'  => get_string('frequency_' . $frequency, 'local_imageblog'),
            'items'           => $items,
            'count'           => count($items),
            'listingurl'      => (new moodle_url('/local/imageblog/index.php'))->out(false),
            'subscribeurl'    => (new moodle_url('/local/imageblog/subscribe.php'))->out(false),
            'readmorelabel'   => get_string('readmore', 'local_imageblog'),
        ];

        $html = $this->render_from_template('local_imageblog/digest_email', $context);

        $textlines = [];
        $textlines[] = get_string('digest_intro', 'local_imageblog', $sitename);
        $textlines[] = '';
        foreach ($items as $item) {
            $textlines[] = '* ' . $item['title'];
            if ($item['summary'] !== '') {
                $textlines[] = '  ' . $item['summary'];
            }
            $textlines[] = '  ' . $item['viewurl'];
            $textlines[] = '';
        }
        $textlines[] = get_string('digest_footer', 'local_imageblog', $context['subscribeurl']);
        $text = implode("\n", $textlines);

        $subject = get_string('digest_subject', 'local_imageblog', (object)[
            'site'  => $sitename,
            'count' => count($items),
        ]);

        return [$html, $text, $subject];
    }

    /**
     * Build the Mustache context for the case panel rendered under a post.
     *
     * @param post $post
     * @param int  $userid    Viewer id
     * @param bool $isauthor  True when viewer can manage the case
     * @return array
     */
    private function build_case_context(post $post, int $userid, bool $isauthor): array {
        $syscontext = \context_system::instance();
        $revealed = !empty($post->caserevealed);
        $usersub = case_post::get_user_diagnosis($post->id, $userid);
        $allquestions = case_post::get_questions($post->id);

        // Once revealed, award the view-only CPD for non-participants.
        if ($revealed && !$usersub && has_capability('local/imageblog:submitdiagnosis', $syscontext, $userid)) {
            case_post::award_view_if_eligible($post->id, $userid);
        }

        $diagnoses = [];
        if ($revealed || $isauthor) {
            foreach (case_post::get_diagnoses($post->id) as $d) {
                $diagnoses[] = [
                    'id'         => (int)$d->id,
                    'userid'     => (int)$d->userid,
                    'username'   => fullname($d),
                    'diagnosis'  => format_text($d->diagnosis, FORMAT_PLAIN, ['context' => $syscontext]),
                    'reasoning'  => $d->reasoning
                        ? format_text($d->reasoning, FORMAT_PLAIN, ['context' => $syscontext])
                        : '',
                    'hasreasoning' => !empty($d->reasoning),
                    'isbest'     => $post->casebestdiagnosisid && (int)$d->id === (int)$post->casebestdiagnosisid,
                    'markbesturl' => (new moodle_url('/local/imageblog/case_action.php', [
                        'postid'       => $post->id,
                        'diagnosisid'  => (int)$d->id,
                        'action'       => 'markbest',
                        'sesskey'      => sesskey(),
                    ]))->out(false),
                    'time'       => userdate((int)$d->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                ];
            }
        }

        $questions = [];
        foreach ($allquestions as $q) {
            $questions[] = [
                'id'        => (int)$q->id,
                'username'  => fullname($q),
                'question'  => format_text($q->question, FORMAT_PLAIN, ['context' => $syscontext]),
                'hasanswer' => !empty($q->answer),
                'answer'    => $q->answer
                    ? format_text($q->answer, FORMAT_PLAIN, ['context' => $syscontext])
                    : '',
                'asktime'   => userdate((int)$q->timeasked, get_string('strftimedatetimeshort', 'langconfig')),
                'answertime' => $q->timeanswered
                    ? userdate((int)$q->timeanswered, get_string('strftimedatetimeshort', 'langconfig'))
                    : '',
            ];
        }

        $totalhours = case_post::get_user_total_hours($post->id, $userid);

        return [
            'postid'           => $post->id,
            'revealed'         => $revealed,
            'isauthor'         => $isauthor,
            'cansubmit'        => !$revealed && has_capability('local/imageblog:submitdiagnosis', $syscontext)
                && !$isauthor,
            'canask'           => has_capability('local/imageblog:askcasequestion', $syscontext) && !$isauthor,
            'hasusersub'       => !empty($usersub),
            'userdiagnosis'    => $usersub ? format_text($usersub->diagnosis, FORMAT_PLAIN, ['context' => $syscontext]) : '',
            'userreasoning'    => $usersub && $usersub->reasoning
                ? format_text($usersub->reasoning, FORMAT_PLAIN, ['context' => $syscontext]) : '',
            'outcome'          => $revealed
                ? format_text(
                    file_rewrite_pluginfile_urls(
                        $post->caseoutcome,
                        'pluginfile.php',
                        $syscontext->id,
                        'local_imageblog',
                        post::FILEAREA_CASEOUTCOME,
                        $post->id
                    ),
                    $post->caseoutcomeformat,
                    ['context' => $syscontext]
                )
                : '',
            'difficulty'       => (int)$post->casedifficulty,
            'diagnoses'        => $diagnoses,
            'hasdiagnoses'     => !empty($diagnoses),
            'showdiagnoses'    => $revealed || $isauthor,
            'questions'        => $questions,
            'hasquestions'     => !empty($questions),
            'sesskey'          => sesskey(),
            'actionurl'        => (new moodle_url('/local/imageblog/case_action.php'))->out(false),
            'revealurl'        => (new moodle_url('/local/imageblog/case_action.php', [
                'postid'  => $post->id,
                'action'  => 'reveal',
                'sesskey' => sesskey(),
            ]))->out(false),
            'cpdtotal'         => $totalhours > 0 ? number_format($totalhours, 2) : '',
            'hascpd'           => $totalhours > 0,
        ];
    }

    /**
     * Build the Mustache context for a single card.
     *
     * @param post $post
     * @return array
     */
    private function build_card_context(post $post): array {
        global $DB, $USER;

        $author = $DB->get_record(
            'user',
            ['id' => $post->authorid],
            'id, firstname, lastname',
            IGNORE_MISSING
        );
        $imgurl = $post->get_featured_image_url();
        $tags   = $post->get_tags();
        $levels = $post->get_levels();

        $syscontext = \context_system::instance();
        $isowner    = ($post->authorid === (int)$USER->id);
        $canedit    = has_capability('local/imageblog:editanypost', $syscontext)
            || ($isowner && has_capability('local/imageblog:createpost', $syscontext));

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
            'isdraft'       => $post->status === post::STATUS_DRAFT,
            'isarchived'    => $post->status === post::STATUS_ARCHIVED,
            'statuslabel'   => $post->status !== post::STATUS_PUBLISHED
                ? get_string('status_' . $post->status, 'local_imageblog')
                : '',
            'canedit'       => $canedit,
            'editurl'       => $canedit
                ? (new moodle_url('/local/imageblog/edit.php', ['id' => $post->id]))->out(false)
                : '',
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

        $currentstatus = (string)($filters['status'] ?? '');
        $statusoptions = [];
        if (!empty($filters['canseestatuses'])) {
            $choices = [
                ''          => 'status_published',
                'mine'      => 'status_mine',
                'draft'     => 'status_draft',
                'archived'  => 'status_archived',
            ];
            foreach ($choices as $value => $stringkey) {
                $statusoptions[] = [
                    'value'    => $value,
                    'label'    => get_string($stringkey, 'local_imageblog'),
                    'selected' => $value === $currentstatus,
                ];
            }
        }

        return [
            'authors'         => $mark($taxonomy['authors'] ?? [], (int)($filters['authorid'] ?? 0)),
            'categories'      => $mark($taxonomy['categories'] ?? [], $currentcategory),
            'subcategories'   => $subcategories,
            'hassubcats'      => !empty($subcategories),
            'tags'            => $mark($taxonomy['tags'] ?? [], (int)($filters['tagid'] ?? 0)),
            'levels'          => $mark($taxonomy['levels'] ?? [], (int)($filters['levelid'] ?? 0)),
            'haslevels'       => !empty($taxonomy['levels'] ?? []),
            'keyword'         => $filters['keyword'] ?? '',
            'datefrom'        => $filters['datefromraw'] ?? '',
            'dateto'          => $filters['datetoraw'] ?? '',
            'showstatus'      => !empty($filters['canseestatuses']),
            'statusoptions'   => $statusoptions,
            'formaction'      => (new moodle_url('/local/imageblog/index.php'))->out(false),
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
        // page + datetimes + internal flags, swap raw date strings back in.
        $base = $filters;
        unset(
            $base['page'],
            $base['datefrom'],
            $base['dateto'],
            $base['statuses'],
            $base['mineonly'],
            $base['viewerid'],
            $base['canseestatuses']
        );
        if (!empty($base['datefromraw'])) {
            $base['datefrom'] = $base['datefromraw'];
        }
        if (!empty($base['datetoraw'])) {
            $base['dateto'] = $base['datetoraw'];
        }
        unset($base['datefromraw'], $base['datetoraw']);
        if (empty($base['status'])) {
            unset($base['status']);
        }

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
