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
 * Plugin library functions.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve plugin files (featured images and post body images).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context  $context
 * @param string   $filearea
 * @param array    $args
 * @param bool     $forcedownload
 * @param array    $options
 * @return bool
 */
function local_imageblog_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    require_login();
    require_capability('local/imageblog:view', $context);

    $allowedareas = ['featured_image', 'post_images', 'panorama', 'case_outcome'];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    if (count($args) < 2) {
        return false;
    }
    $itemid   = (int)array_shift($args);
    $filename = array_pop($args);
    if ($filename === null || $filename === '') {
        return false;
    }
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    // The itemid is the post id. Enforce the same visibility as the post view
    // so a view-capable user can't fetch images from a draft/archived post — or
    // the hidden case "answer" — just by guessing the (sequential) id.
    $post = \local_imageblog\post::get($itemid);
    if (!$post || !\local_imageblog\post::can_view($post, $context)) {
        return false;
    }
    // Case-outcome images are the answer: keep them private until the case is
    // revealed, except to the author or a manager.
    if (
        $filearea === \local_imageblog\post::FILEAREA_CASEOUTCOME
        && empty($post->caserevealed)
        && !\local_imageblog\post::can_manage($post, $context)
    ) {
        return false;
    }

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'local_imageblog', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    $cachelifetime = in_array($filearea, ['featured_image', 'panorama'], true) ? DAYSECS : HOURSECS;
    send_stored_file($file, $cachelifetime, 0, $forcedownload, $options);
}

/**
 * Build an inline <style> block for the admin-configured custom CSS.
 *
 * Emitted only by this plugin's own pages, so a mistake in the setting
 * cannot affect the rest of the Moodle site.
 *
 * @return string HTML, or '' when no custom CSS is configured.
 */
function local_imageblog_get_custom_css_html(): string {
    $css = trim((string)get_config('local_imageblog', 'customcss'));
    if ($css === '') {
        return '';
    }
    // Defensively prevent breaking out of the style element.
    $css = str_ireplace('</style', '<\/style', $css);
    return \html_writer::tag(
        'style',
        $css,
        ['type' => 'text/css', 'data-region' => 'local-imageblog-customcss']
    );
}

/**
 * Return taxonomy arrays (authors, categories, tags) for filter dropdowns.
 *
 * @return array Keyed by 'authors', 'categories', 'subcategories', 'tags' and 'levels'.
 */
function local_imageblog_get_taxonomy(): array {
    global $DB;

    $namefields = \core_user\fields::for_name()->get_sql('u', true)->selects;
    $sql = "SELECT DISTINCT u.id{$namefields}
              FROM {user} u
              JOIN {local_imageblog_posts} p ON p.authorid = u.id
             WHERE p.status = :status
          ORDER BY u.lastname, u.firstname";
    $authorrecords = $DB->get_records_sql($sql, ['status' => \local_imageblog\post::STATUS_PUBLISHED]);
    $authors = array_map(function ($u) {
        return ['id' => (int)$u->id, 'name' => fullname($u)];
    }, $authorrecords);

    $catrecords = $DB->get_records('local_imageblog_categories', null, 'sortorder ASC');
    $categories = array_map(function ($c) {
        return ['id' => (int)$c->id, 'name' => $c->name];
    }, $catrecords);

    $subcatrecords = $DB->get_records('local_imageblog_subcategories', null, 'sortorder ASC');
    $subcategories = array_map(function ($s) {
        return [
            'id'         => (int)$s->id,
            'name'       => $s->name,
            'categoryid' => (int)$s->categoryid,
        ];
    }, $subcatrecords);

    $tagrecords = $DB->get_records('local_imageblog_tags', null, 'name ASC');
    $tags = array_map(function ($t) {
        return ['id' => (int)$t->id, 'name' => $t->name];
    }, $tagrecords);

    $levelrecords = $DB->get_records('local_imageblog_levels', null, 'sortorder ASC');
    $levels = array_map(function ($l) {
        return ['id' => (int)$l->id, 'name' => $l->name];
    }, $levelrecords);

    return [
        'authors'       => array_values($authors),
        'categories'    => array_values($categories),
        'subcategories' => array_values($subcategories),
        'tags'          => array_values($tags),
        'levels'        => array_values($levels),
    ];
}

/**
 * Inject a link to the blog into the global navigation.
 *
 * @param global_navigation $navigation
 * @return void
 */
function local_imageblog_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $context = context_system::instance();
    if (!has_capability('local/imageblog:view', $context)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'local_imageblog'),
        new moodle_url('/local/imageblog/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_imageblog'
    );
}

/**
 * Surface the blog email subscription page on the user's preferences page.
 *
 * Called by Moodle when building the navigation tree for user/preferences.php,
 * so the new node appears under "Miscellaneous" for any logged-in user who
 * can view the blog.
 *
 * @param navigation_node $navigation
 * @param stdClass        $user
 * @param context         $usercontext
 * @param stdClass        $course
 * @param context         $coursecontext
 */
function local_imageblog_extend_navigation_user_settings(
    navigation_node $navigation,
    $user,
    $usercontext,
    $course,
    $coursecontext
): void {
    global $USER;

    if (!get_config('local_imageblog', 'subscriptions_enabled')) {
        return;
    }
    // Only the user themselves (or a manager managing their account) should
    // see the link — editing someone else's subscription is out of scope.
    if ((int)$user->id !== (int)$USER->id) {
        return;
    }
    if (!has_capability('local/imageblog:view', context_system::instance())) {
        return;
    }
    $navigation->add(
        get_string('subscribe_title', 'local_imageblog'),
        new moodle_url('/local/imageblog/subscribe.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_imageblog_subscribe'
    );
}
