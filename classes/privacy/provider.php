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
 * Privacy provider.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — describes and serves user data stored by local_imageblog.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe what user data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_imageblog_posts',
            [
                'authorid'      => 'privacy:metadata:posts:authorid',
                'title'         => 'privacy:metadata:posts:title',
                'summary'       => 'privacy:metadata:posts:summary',
                'body'          => 'privacy:metadata:posts:body',
                'status'        => 'privacy:metadata:posts:status',
                'timecreated'   => 'privacy:metadata:posts:timecreated',
                'timemodified'  => 'privacy:metadata:posts:timemodified',
                'timepublished' => 'privacy:metadata:posts:timepublished',
            ],
            'privacy:metadata:posts'
        );

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data for the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {local_imageblog_posts} p ON p.authorid = :userid
                 WHERE c.contextlevel = :contextlevel";
        $contextlist->add_from_sql($sql, [
            'userid'       => $userid,
            'contextlevel' => CONTEXT_SYSTEM,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users in a given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('authorid', "SELECT authorid FROM {local_imageblog_posts}", []);
    }

    /**
     * Export all user data for the contexts the user has data in.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $records = $DB->get_records('local_imageblog_posts', ['authorid' => $user->id]);
            foreach ($records as $record) {
                $data = (object)[
                    'title'         => $record->title,
                    'summary'       => $record->summary,
                    'body'          => $record->body,
                    'status'        => $record->status,
                    'timecreated'   => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified'  => \core_privacy\local\request\transform::datetime($record->timemodified),
                    'timepublished' => $record->timepublished
                        ? \core_privacy\local\request\transform::datetime($record->timepublished)
                        : null,
                ];
                writer::with_context($context)
                    ->export_data(
                        [get_string('pluginname', 'local_imageblog'), 'post-' . $record->id],
                        $data
                    )
                    ->export_area_files(
                        [get_string('pluginname', 'local_imageblog'), 'post-' . $record->id],
                        'local_imageblog',
                        'featured_image',
                        $record->id
                    )
                    ->export_area_files(
                        [get_string('pluginname', 'local_imageblog'), 'post-' . $record->id],
                        'local_imageblog',
                        'post_images',
                        $record->id
                    )
                    ->export_area_files(
                        [get_string('pluginname', 'local_imageblog'), 'post-' . $record->id],
                        'local_imageblog',
                        'panorama',
                        $record->id
                    );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $postids = $DB->get_fieldset_select('local_imageblog_posts', 'id', '');
        self::delete_posts($postids, $context);
    }

    /**
     * Delete all data for the user in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $postids = $DB->get_fieldset_select(
                'local_imageblog_posts',
                'id',
                'authorid = :userid',
                ['userid' => $user->id]
            );
            self::delete_posts($postids, $context);
        }
    }

    /**
     * Delete data for the listed users in the given context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $postids = $DB->get_fieldset_select(
            'local_imageblog_posts',
            'id',
            "authorid $insql",
            $inparams
        );
        self::delete_posts($postids, $context);
    }

    /**
     * Helper to delete posts and their attached files.
     *
     * @param array    $postids
     * @param \context $context
     */
    private static function delete_posts(array $postids, \context $context): void {
        global $DB;
        if (!$postids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);

        $fs = get_file_storage();
        foreach ($postids as $postid) {
            $fs->delete_area_files($context->id, 'local_imageblog', 'featured_image', $postid);
            $fs->delete_area_files($context->id, 'local_imageblog', 'post_images', $postid);
            $fs->delete_area_files($context->id, 'local_imageblog', 'panorama', $postid);
        }

        $DB->delete_records_select('local_imageblog_post_tags', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_post_cats', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_post_levels', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_posts', "id $insql", $inparams);
    }
}
