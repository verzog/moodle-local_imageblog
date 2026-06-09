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
 * Clinical case operations and CPD calculation.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Static helpers for case-type posts: diagnoses, Q&A, reveal, CPD award.
 */
class case_post {
    /** @var string Regular blog post type. */
    const TYPE_BLOG = 'blog';
    /** @var string Clinical case post type. */
    const TYPE_CASE = 'case';

    /** @var string CPD reason: submitted a diagnosis. */
    const REASON_PARTICIPATION = 'participation';
    /** @var string CPD reason: author selected the diagnosis as best. */
    const REASON_BEST          = 'bestanswer';
    /** @var string CPD reason: viewed the revealed outcome only. */
    const REASON_VIEW          = 'view';

    /**
     * Submit or update a reader's diagnosis. The unique (postid,userid)
     * index guarantees one diagnosis per user per case.
     *
     * @param int    $postid
     * @param int    $userid
     * @param string $diagnosis
     * @param string $reasoning
     * @return int Diagnosis row id.
     */
    public static function submit_diagnosis(int $postid, int $userid, string $diagnosis, string $reasoning = ''): int {
        global $DB;
        $now = time();
        $existing = $DB->get_record('local_imageblog_case_diags', ['postid' => $postid, 'userid' => $userid]);
        if ($existing) {
            $existing->diagnosis    = $diagnosis;
            $existing->reasoning    = $reasoning;
            $existing->timemodified = $now;
            $DB->update_record('local_imageblog_case_diags', $existing);
            return (int)$existing->id;
        }
        $record = (object)[
            'postid'       => $postid,
            'userid'       => $userid,
            'diagnosis'    => $diagnosis,
            'reasoning'    => $reasoning,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        return (int)$DB->insert_record('local_imageblog_case_diags', $record);
    }

    /**
     * Diagnoses for a case. After the reveal everyone sees all; before the
     * reveal only the author and the user themselves see anything.
     *
     * @param int $postid
     * @return \stdClass[]
     */
    public static function get_diagnoses(int $postid): array {
        global $DB;
        $namefields = implode(', u.', \core_user\fields::get_name_fields());
        $sql = "SELECT d.*, u.{$namefields}
                  FROM {local_imageblog_case_diags} d
                  JOIN {user} u ON u.id = d.userid
                 WHERE d.postid = :postid
              ORDER BY d.timecreated ASC";
        return array_values($DB->get_records_sql($sql, ['postid' => $postid]));
    }

    /**
     * Fetch the diagnosis submitted by a single user for this case.
     *
     * @param int $postid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_user_diagnosis(int $postid, int $userid): ?\stdClass {
        global $DB;
        $r = $DB->get_record('local_imageblog_case_diags', ['postid' => $postid, 'userid' => $userid]);
        return $r ?: null;
    }

    /**
     * Record a question on a case from the given user.
     *
     * @param int    $postid
     * @param int    $userid
     * @param string $question
     * @return int Question row id.
     */
    public static function ask_question(int $postid, int $userid, string $question): int {
        global $DB;
        $record = (object)[
            'postid'    => $postid,
            'userid'    => $userid,
            'question'  => $question,
            'timeasked' => time(),
        ];
        return (int)$DB->insert_record('local_imageblog_case_qs', $record);
    }

    /**
     * Store the author's answer to a question.
     *
     * @param int    $questionid
     * @param int    $authorid
     * @param string $answer
     */
    public static function answer_question(int $questionid, int $authorid, string $answer): void {
        global $DB;
        $record = (object)[
            'id'           => $questionid,
            'answer'       => $answer,
            'answeredby'   => $authorid,
            'timeanswered' => time(),
        ];
        $DB->update_record('local_imageblog_case_qs', $record);
    }

    /**
     * Fetch the question thread for a case, oldest first, with the asker's name.
     *
     * @param int $postid
     * @return \stdClass[]
     */
    public static function get_questions(int $postid): array {
        global $DB;
        $namefields = implode(', u.', \core_user\fields::get_name_fields());
        $sql = "SELECT q.*, u.{$namefields}
                  FROM {local_imageblog_case_qs} q
                  JOIN {user} u ON u.id = q.userid
                 WHERE q.postid = :postid
              ORDER BY q.timeasked ASC";
        return array_values($DB->get_records_sql($sql, ['postid' => $postid]));
    }

    /**
     * Mark the case as revealed and award CPD hours to participants.
     *
     * @param int $postid
     */
    public static function reveal(int $postid): void {
        global $DB;
        $now = time();
        $record = (object)[
            'id'               => $postid,
            'caserevealed'     => 1,
            'caserevealedtime' => $now,
            'timemodified'     => $now,
        ];
        $DB->update_record('local_imageblog_posts', $record);
        self::award_cpd_for_case($postid);
    }

    /**
     * Choose (or clear) the "best diagnosis". Only meaningful after reveal.
     * Pass 0 to clear. Awards a one-time bonus to the chosen submitter.
     *
     * @param int $postid
     * @param int $diagnosisid
     */
    public static function set_best_diagnosis(int $postid, int $diagnosisid): void {
        global $DB;
        $record = (object)[
            'id'                  => $postid,
            'casebestdiagnosisid' => $diagnosisid ?: null,
            'timemodified'        => time(),
        ];
        $DB->update_record('local_imageblog_posts', $record);
        if ($diagnosisid) {
            $diag = $DB->get_record('local_imageblog_case_diags', ['id' => $diagnosisid], '*', MUST_EXIST);
            // Clear any previous best bonuses for this case so re-selection is correct.
            $DB->delete_records('local_imageblog_case_cpd', ['postid' => $postid, 'reason' => self::REASON_BEST]);
            self::record_cpd((int)$diag->postid, (int)$diag->userid, self::REASON_BEST);
        }
    }

    /**
     * Award (or refresh) CPD rows for everyone who has participated in this case.
     *
     * @param int $postid
     */
    public static function award_cpd_for_case(int $postid): void {
        global $DB;
        $post = $DB->get_record('local_imageblog_posts', ['id' => $postid], '*', MUST_EXIST);
        if ($post->posttype !== self::TYPE_CASE || empty($post->caserevealed)) {
            return;
        }
        // Compute hours once — config and difficulty don't change inside the loop.
        $hours = self::compute_hours((int)$post->casedifficulty, self::REASON_PARTICIPATION);
        if ($hours <= 0) {
            return;
        }
        $diags = $DB->get_records('local_imageblog_case_diags', ['postid' => $postid], '', 'id, userid');
        foreach ($diags as $d) {
            self::upsert_cpd_row($postid, (int)$d->userid, self::REASON_PARTICIPATION, $hours);
        }
    }

    /**
     * Insert or refresh a CPD row with a pre-computed hours value.
     *
     * @param int    $postid
     * @param int    $userid
     * @param string $reason
     * @param float  $hours
     */
    private static function upsert_cpd_row(int $postid, int $userid, string $reason, float $hours): void {
        global $DB;
        $existing = $DB->get_record(
            'local_imageblog_case_cpd',
            ['postid' => $postid, 'userid' => $userid, 'reason' => $reason]
        );
        if ($existing) {
            $existing->hours       = $hours;
            $existing->timeawarded = time();
            $DB->update_record('local_imageblog_case_cpd', $existing);
            return;
        }
        $DB->insert_record('local_imageblog_case_cpd', (object)[
            'postid'      => $postid,
            'userid'      => $userid,
            'hours'       => $hours,
            'reason'      => $reason,
            'timeawarded' => time(),
        ]);
    }

    /**
     * Record CPD hours for a single (user, reason) on a case. Idempotent —
     * relies on the unique (postid,userid,reason) index, refreshes the value
     * if the admin rates have changed.
     *
     * @param int    $postid
     * @param int    $userid
     * @param string $reason
     */
    public static function record_cpd(int $postid, int $userid, string $reason): void {
        global $DB;
        $post = $DB->get_record('local_imageblog_posts', ['id' => $postid], '*', MUST_EXIST);
        if ($post->posttype !== self::TYPE_CASE || empty($post->caserevealed)) {
            return;
        }
        $hours = self::compute_hours((int)$post->casedifficulty, $reason);
        if ($hours <= 0) {
            return;
        }
        self::upsert_cpd_row($postid, $userid, $reason, $hours);
    }

    /**
     * Award the view-only CPD when a non-participant reads a revealed case.
     * Skipped if the user already has a participation row.
     *
     * @param int $postid
     * @param int $userid
     */
    public static function award_view_if_eligible(int $postid, int $userid): void {
        global $DB;
        $hasdiag = $DB->record_exists('local_imageblog_case_diags', ['postid' => $postid, 'userid' => $userid]);
        if ($hasdiag) {
            return;
        }
        self::record_cpd($postid, $userid, self::REASON_VIEW);
    }

    /**
     * Compute hours for a (difficulty, reason) pair using admin config.
     *
     * @param int    $difficulty
     * @param string $reason
     * @return float
     */
    public static function compute_hours(int $difficulty, string $reason): float {
        $base = (float)get_config('local_imageblog', 'cpd_basehours');
        if ($base <= 0) {
            return 0.0;
        }
        $scaleraw = (string)get_config('local_imageblog', 'cpd_difficulty_scale');
        $scale = array_map('floatval', array_map('trim', explode(',', $scaleraw)));
        $idx = max(0, min(count($scale) - 1, $difficulty - 1));
        $mult = $scale[$idx] ?? 1.0;

        $factor = 0.0;
        switch ($reason) {
            case self::REASON_PARTICIPATION:
                $factor = (float)get_config('local_imageblog', 'cpd_submit_factor');
                break;
            case self::REASON_VIEW:
                $factor = (float)get_config('local_imageblog', 'cpd_view_factor');
                break;
            case self::REASON_BEST:
                $factor = (float)get_config('local_imageblog', 'cpd_best_bonus');
                break;
        }
        return round($base * $mult * $factor, 2);
    }

    /**
     * Total CPD hours awarded to a user on this case (across all reasons).
     *
     * @param int $postid
     * @param int $userid
     * @return float
     */
    public static function get_user_total_hours(int $postid, int $userid): float {
        global $DB;
        $sum = $DB->get_field_sql(
            'SELECT COALESCE(SUM(hours), 0) FROM {local_imageblog_case_cpd} WHERE postid = :postid AND userid = :userid',
            ['postid' => $postid, 'userid' => $userid]
        );
        return (float)$sum;
    }
}
