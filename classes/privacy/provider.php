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
 * @copyright  2026 Vernon Apain / Educheckout
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

        $collection->add_database_table(
            'local_imageblog_case_diags',
            [
                'userid'       => 'privacy:metadata:diags:userid',
                'diagnosis'    => 'privacy:metadata:diags:diagnosis',
                'reasoning'    => 'privacy:metadata:diags:reasoning',
                'timecreated'  => 'privacy:metadata:diags:timecreated',
                'timemodified' => 'privacy:metadata:diags:timemodified',
            ],
            'privacy:metadata:diags'
        );

        $collection->add_database_table(
            'local_imageblog_case_qs',
            [
                'userid'       => 'privacy:metadata:qs:userid',
                'question'     => 'privacy:metadata:qs:question',
                'answer'       => 'privacy:metadata:qs:answer',
                'answeredby'   => 'privacy:metadata:qs:answeredby',
                'timeasked'    => 'privacy:metadata:qs:timeasked',
                'timeanswered' => 'privacy:metadata:qs:timeanswered',
            ],
            'privacy:metadata:qs'
        );

        $collection->add_database_table(
            'local_imageblog_case_cpd',
            [
                'userid'      => 'privacy:metadata:cpd:userid',
                'hours'       => 'privacy:metadata:cpd:hours',
                'reason'      => 'privacy:metadata:cpd:reason',
                'timeawarded' => 'privacy:metadata:cpd:timeawarded',
            ],
            'privacy:metadata:cpd'
        );

        $collection->add_database_table(
            'local_imageblog_subs',
            [
                'userid'    => 'privacy:metadata:subs:userid',
                'frequency' => 'privacy:metadata:subs:frequency',
                'lastsent'  => 'privacy:metadata:subs:lastsent',
            ],
            'privacy:metadata:subs'
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

        // All user data lives at system context — return it if the user has
        // any row in any of our tables.
        $sql = "SELECT c.id
                  FROM {context} c
                 WHERE c.contextlevel = :contextlevel
                   AND (EXISTS (SELECT 1 FROM {local_imageblog_posts} WHERE authorid = :u1)
                        OR EXISTS (SELECT 1 FROM {local_imageblog_case_diags} WHERE userid = :u2)
                        OR EXISTS (SELECT 1 FROM {local_imageblog_case_qs}
                                    WHERE userid = :u3 OR answeredby = :u4)
                        OR EXISTS (SELECT 1 FROM {local_imageblog_case_cpd} WHERE userid = :u5)
                        OR EXISTS (SELECT 1 FROM {local_imageblog_subs} WHERE userid = :u6))";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'u1' => $userid, 'u2' => $userid, 'u3' => $userid,
            'u4' => $userid, 'u5' => $userid, 'u6' => $userid,
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
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_imageblog_case_diags}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_imageblog_case_qs}", []);
        $sql = "SELECT answeredby FROM {local_imageblog_case_qs} WHERE answeredby IS NOT NULL";
        $userlist->add_from_sql('answeredby', $sql, []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_imageblog_case_cpd}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_imageblog_subs}", []);
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
            $pluginname = get_string('pluginname', 'local_imageblog');
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
                $path = [$pluginname, 'post-' . $record->id];
                $w = writer::with_context($context);
                $w->export_data($path, $data);
                foreach (['featured_image', 'post_images', 'panorama', 'case_outcome'] as $area) {
                    $w->export_area_files($path, 'local_imageblog', $area, $record->id);
                }
            }

            // Case diagnoses submitted by the user.
            $diags = $DB->get_records('local_imageblog_case_diags', ['userid' => $user->id]);
            foreach ($diags as $d) {
                $data = (object)[
                    'diagnosis'    => $d->diagnosis,
                    'reasoning'    => $d->reasoning,
                    'timecreated'  => \core_privacy\local\request\transform::datetime($d->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($d->timemodified),
                ];
                writer::with_context($context)
                    ->export_data([$pluginname, 'case-diagnosis-' . $d->id], $data);
            }

            // Case questions asked or answered by the user.
            $qs = $DB->get_records_select(
                'local_imageblog_case_qs',
                'userid = :u1 OR answeredby = :u2',
                ['u1' => $user->id, 'u2' => $user->id]
            );
            foreach ($qs as $q) {
                $data = (object)[
                    'question'     => $q->question,
                    'answer'       => $q->answer,
                    'asked'        => (int)$q->userid === (int)$user->id,
                    'answered'     => (int)$q->answeredby === (int)$user->id,
                    'timeasked'    => \core_privacy\local\request\transform::datetime($q->timeasked),
                    'timeanswered' => $q->timeanswered
                        ? \core_privacy\local\request\transform::datetime($q->timeanswered)
                        : null,
                ];
                writer::with_context($context)
                    ->export_data([$pluginname, 'case-question-' . $q->id], $data);
            }

            // CPD awards for the user.
            $cpd = $DB->get_records('local_imageblog_case_cpd', ['userid' => $user->id]);
            foreach ($cpd as $row) {
                $data = (object)[
                    'hours'       => $row->hours,
                    'reason'      => $row->reason,
                    'timeawarded' => \core_privacy\local\request\transform::datetime($row->timeawarded),
                ];
                writer::with_context($context)
                    ->export_data([$pluginname, 'case-cpd-' . $row->id], $data);
            }

            // Subscription preferences.
            $sub = $DB->get_record('local_imageblog_subs', ['userid' => $user->id]);
            if ($sub) {
                $data = (object)[
                    'frequency' => $sub->frequency,
                    'lastsent'  => $sub->lastsent
                        ? \core_privacy\local\request\transform::datetime($sub->lastsent)
                        : null,
                ];
                writer::with_context($context)
                    ->export_data([$pluginname, 'subscription'], $data);
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

        // The case + subscription tables only contain user data — wipe them entirely.
        $DB->delete_records('local_imageblog_case_diags', null);
        $DB->delete_records('local_imageblog_case_qs', null);
        $DB->delete_records('local_imageblog_case_cpd', null);
        $DB->delete_records('local_imageblog_subs', null);
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
            self::delete_case_data_for_users([$user->id]);
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
        self::delete_case_data_for_users($userids);
    }

    /**
     * Remove case submissions, Q&A authorship, CPD rows and subscriptions
     * for the listed users. Posts authored by them are handled separately.
     *
     * @param int[] $userids
     */
    private static function delete_case_data_for_users(array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_imageblog_case_diags', "userid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_case_cpd', "userid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_subs', "userid $insql", $inparams);
        // Drop rows where the user asked the question; for rows they only
        // answered, keep the question intact and null out the answer fields.
        $DB->delete_records_select('local_imageblog_case_qs', "userid $insql", $inparams);
        $DB->execute(
            "UPDATE {local_imageblog_case_qs}
                SET answeredby = NULL, answer = NULL, timeanswered = NULL
              WHERE answeredby $insql",
            $inparams
        );
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
            $fs->delete_area_files($context->id, 'local_imageblog', 'case_outcome', $postid);
        }

        // Cascade-delete case rows belonging to the deleted posts.
        $DB->delete_records_select('local_imageblog_case_diags', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_case_qs', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_case_cpd', "postid $insql", $inparams);

        $DB->delete_records_select('local_imageblog_post_tags', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_post_cats', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_post_levels', "postid $insql", $inparams);
        $DB->delete_records_select('local_imageblog_posts', "id $insql", $inparams);
    }
}
