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
 * Upgrade script.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run upgrade steps from older versions of the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_imageblog_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026051100) {
        $table = new xmldb_table('local_imageblog_posts');

        $fields = [
            new xmldb_field('posttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'blog', 'featuredimage'),
            new xmldb_field('caseoutcome', XMLDB_TYPE_TEXT, null, null, null, null, null, 'posttype'),
            new xmldb_field('caseoutcomeformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'caseoutcome'),
            new xmldb_field('caserevealed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'caseoutcomeformat'),
            new xmldb_field('caserevealedtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'caserevealed'),
            new xmldb_field('casebestdiagnosisid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'caserevealedtime'),
            new xmldb_field('casedifficulty', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'casebestdiagnosisid'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $diagstable = new xmldb_table('local_imageblog_case_diags');
        if (!$dbman->table_exists($diagstable)) {
            $diagstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $diagstable->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $diagstable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $diagstable->add_field('diagnosis', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $diagstable->add_field('reasoning', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $diagstable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $diagstable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $diagstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $diagstable->add_index('idx_post_user', XMLDB_INDEX_UNIQUE, ['postid', 'userid']);
            $dbman->create_table($diagstable);
        }

        $qstable = new xmldb_table('local_imageblog_case_qs');
        if (!$dbman->table_exists($qstable)) {
            $qstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $qstable->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $qstable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $qstable->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $qstable->add_field('answer', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $qstable->add_field('answeredby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $qstable->add_field('timeasked', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $qstable->add_field('timeanswered', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $qstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $qstable->add_index('idx_postid', XMLDB_INDEX_NOTUNIQUE, ['postid']);
            $dbman->create_table($qstable);
        }

        $cpdtable = new xmldb_table('local_imageblog_case_cpd');
        if (!$dbman->table_exists($cpdtable)) {
            $cpdtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $cpdtable->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cpdtable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cpdtable->add_field('hours', XMLDB_TYPE_NUMBER, '6, 2', null, XMLDB_NOTNULL, null, '0');
            $cpdtable->add_field('reason', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $cpdtable->add_field('timeawarded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cpdtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $cpdtable->add_index('idx_post_user_reason', XMLDB_INDEX_UNIQUE, ['postid', 'userid', 'reason']);
            $dbman->create_table($cpdtable);
        }

        upgrade_plugin_savepoint(true, 2026051100, 'local', 'imageblog');
    }

    if ($oldversion < 2026051200) {
        $subs = new xmldb_table('local_imageblog_subs');
        if (!$dbman->table_exists($subs)) {
            $subs->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $subs->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $subs->add_field('frequency', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'weekly');
            $subs->add_field('lastsent', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $subs->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $subs->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $subs->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $subs->add_index('idx_userid', XMLDB_INDEX_UNIQUE, ['userid']);
            $dbman->create_table($subs);
        }
        upgrade_plugin_savepoint(true, 2026051200, 'local', 'imageblog');
    }

    if ($oldversion < 2026060800) {
        \local_imageblog\local\author_role::ensure();
        upgrade_plugin_savepoint(true, 2026060800, 'local', 'imageblog');
    }

    if ($oldversion < 2026060803) {
        // Add the scheduling timestamp column.
        $table = new xmldb_table('local_imageblog_posts');
        $field = new xmldb_field(
            'timescheduled',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null,
            'status'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Re-run role bootstrap so existing Blog author roles lose editanypost.
        \local_imageblog\local\author_role::ensure();

        upgrade_plugin_savepoint(true, 2026060803, 'local', 'imageblog');
    }

    if ($oldversion < 2026060805) {
        // Migrate retired 'monthly' frequency to 'weekly'.
        $DB->set_field(
            'local_imageblog_subs',
            'frequency',
            \local_imageblog\subscription::FREQ_WEEKLY,
            ['frequency' => \local_imageblog\subscription::FREQ_MONTHLY]
        );
        upgrade_plugin_savepoint(true, 2026060805, 'local', 'imageblog');
    }

    if ($oldversion < 2026060900) {
        \local_imageblog\local\author_role::ensure();
        upgrade_plugin_savepoint(true, 2026060900, 'local', 'imageblog');
    }

    if ($oldversion < 2026062802) {
        // Refresh the Blog author role's name/description so the corrected
        // "own posts only" wording reaches sites where the role already exists.
        \local_imageblog\local\author_role::ensure();
        upgrade_plugin_savepoint(true, 2026062802, 'local', 'imageblog');
    }

    if ($oldversion < 2026101400) {
        $table = new xmldb_table('local_imageblog_posts');

        // Add the assessment mode (none|practice|assessable) and the optional
        // scheduled auto-reveal timestamp.
        $field = new xmldb_field('assessmentmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none', 'posttype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('caserevealat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'caserevealedtime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Map the two legacy post types onto the new set: a clinical case
        // becomes a Patient Case graded for CPD; everything else is a Blog Post.
        // Set the mode while the row still reads 'case', then rename the type.
        $DB->set_field('local_imageblog_posts', 'assessmentmode', 'assessable', ['posttype' => 'case']);
        $DB->set_field('local_imageblog_posts', 'posttype', 'patientcase', ['posttype' => 'case']);

        // The free category taxonomy is replaced by post type + assessment mode;
        // drop its tables (link table first to respect the foreign keys).
        foreach (['local_imageblog_post_cats', 'local_imageblog_subcategories', 'local_imageblog_categories'] as $name) {
            $drop = new xmldb_table($name);
            if ($dbman->table_exists($drop)) {
                $dbman->drop_table($drop);
            }
        }

        upgrade_plugin_savepoint(true, 2026101400, 'local', 'imageblog');
    }

    if ($oldversion < 2026101500) {
        // Phase 2: the evaluate interaction. Add the chosen-rubric pointer to
        // posts and create the rubric-template and evaluation tables.
        $table = new xmldb_table('local_imageblog_posts');
        $field = new xmldb_field('rubricid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'casedifficulty');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_imageblog_rubrics');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('maxscore', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '5');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_imageblog_rubric_crit');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('rubricid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_rubric', XMLDB_KEY_FOREIGN, ['rubricid'], 'local_imageblog_rubrics', ['id']);
        $table->add_index('idx_rubricid', XMLDB_INDEX_NOTUNIQUE, ['rubricid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_imageblog_eval_refs');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('criterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('refscore', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_post', XMLDB_KEY_FOREIGN, ['postid'], 'local_imageblog_posts', ['id']);
        $table->add_key('fk_crit', XMLDB_KEY_FOREIGN, ['criterionid'], 'local_imageblog_rubric_crit', ['id']);
        $table->add_index('idx_post_crit', XMLDB_INDEX_UNIQUE, ['postid', 'criterionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_imageblog_evals');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('commenttext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_post', XMLDB_KEY_FOREIGN, ['postid'], 'local_imageblog_posts', ['id']);
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('idx_post_user', XMLDB_INDEX_UNIQUE, ['postid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_imageblog_eval_scores');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('evalid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('criterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('score', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_eval', XMLDB_KEY_FOREIGN, ['evalid'], 'local_imageblog_evals', ['id']);
        $table->add_key('fk_crit', XMLDB_KEY_FOREIGN, ['criterionid'], 'local_imageblog_rubric_crit', ['id']);
        $table->add_index('idx_eval_crit', XMLDB_INDEX_UNIQUE, ['evalid', 'criterionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101500, 'local', 'imageblog');
    }

    return true;
}
