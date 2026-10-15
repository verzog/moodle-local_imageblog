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
 * Evaluate-post operations: reader evaluations, author reference ratings,
 * completeness x agreement scoring, reveal and CPD.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Static helpers for the evaluate interaction (Tutorial, Critical Review,
 * Design Evaluation). The reader scores the post against the author's chosen
 * rubric and leaves a comment; the author sets a reference score per criterion.
 * After reveal the reader's score is completeness x agreement with the
 * reference.
 */
class evaluation {
    /**
     * Criteria of the rubric template attached to a post, in display order.
     * Empty when the post has no (or a since-deleted) rubric.
     *
     * @param post $post
     * @return \stdClass[]
     */
    public static function get_criteria(post $post): array {
        if (empty($post->rubricid) || !rubric::template_exists((int)$post->rubricid)) {
            return [];
        }
        return rubric::get_criteria((int)$post->rubricid);
    }

    /**
     * Top of the rating scale for a post's rubric (criteria are scored
     * 1..maxscore). Defaults to 5 when the template is missing.
     *
     * @param post $post
     * @return int
     */
    public static function maxscore(post $post): int {
        $template = $post->rubricid ? rubric::get_template((int)$post->rubricid) : null;
        return $template ? rubric::clamp_maxscore((int)$template->maxscore) : 5;
    }

    /**
     * Author reference scores for a post, keyed by criterion id.
     *
     * @param int $postid
     * @return array<int,int>
     */
    public static function get_reference(int $postid): array {
        global $DB;
        $rows = $DB->get_records('local_imageblog_eval_refs', ['postid' => $postid], '', 'criterionid, refscore');
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->criterionid] = (int)$r->refscore;
        }
        return $out;
    }

    /**
     * Whether the author has set a reference score for every criterion of the
     * post's rubric — the precondition for a meaningful reveal.
     *
     * @param post $post
     * @return bool
     */
    public static function has_full_reference(post $post): bool {
        $criteria = self::get_criteria($post);
        if (!$criteria) {
            return false;
        }
        $refs = self::get_reference($post->id);
        foreach ($criteria as $c) {
            if (!array_key_exists((int)$c->id, $refs)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Replace the author's reference scores for a post. Scores outside the
     * criterion's rubric or the 1..maxscore range are dropped.
     *
     * @param post  $post
     * @param array $scores criterionid => score
     */
    public static function set_reference(post $post, array $scores): void {
        global $DB;
        $criteria = self::get_criteria($post);
        $validids = array_map(fn($c) => (int)$c->id, $criteria);
        $max = self::maxscore($post);

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_imageblog_eval_refs', ['postid' => $post->id]);
        foreach ($scores as $critid => $score) {
            $critid = (int)$critid;
            $score  = (int)$score;
            if (!in_array($critid, $validids, true) || $score < 1 || $score > $max) {
                continue;
            }
            $DB->insert_record('local_imageblog_eval_refs', (object)[
                'postid'      => $post->id,
                'criterionid' => $critid,
                'refscore'    => $score,
            ]);
        }
        $transaction->allow_commit();
    }

    /**
     * Submit or update a reader's evaluation: the per-criterion scores plus an
     * optional comment. The unique (postid,userid) index guarantees one
     * evaluation per user per post.
     *
     * @param post   $post
     * @param int    $userid
     * @param array  $scores criterionid => score
     * @param string $comment
     * @return int Evaluation row id.
     */
    public static function submit_evaluation(post $post, int $userid, array $scores, string $comment = ''): int {
        global $DB;
        $now = time();
        $criteria = self::get_criteria($post);
        $validids = array_map(fn($c) => (int)$c->id, $criteria);
        $max = self::maxscore($post);

        $transaction = $DB->start_delegated_transaction();

        $existing = $DB->get_record('local_imageblog_evals', ['postid' => $post->id, 'userid' => $userid]);
        if ($existing) {
            $existing->commenttext = $comment;
            $existing->timemodified = $now;
            $DB->update_record('local_imageblog_evals', $existing);
            $evalid = (int)$existing->id;
        } else {
            $evalid = (int)$DB->insert_record('local_imageblog_evals', (object)[
                'postid'       => $post->id,
                'userid'       => $userid,
                'commenttext'  => $comment,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        $DB->delete_records('local_imageblog_eval_scores', ['evalid' => $evalid]);
        foreach ($scores as $critid => $score) {
            $critid = (int)$critid;
            $score  = (int)$score;
            if (!in_array($critid, $validids, true) || $score < 1 || $score > $max) {
                continue;
            }
            $DB->insert_record('local_imageblog_eval_scores', (object)[
                'evalid'      => $evalid,
                'criterionid' => $critid,
                'score'       => $score,
            ]);
        }

        $transaction->allow_commit();
        return $evalid;
    }

    /**
     * A single reader's evaluation, or null. Returns the header plus a
     * criterionid => score map.
     *
     * @param int $postid
     * @param int $userid
     * @return \stdClass|null Object with id, comment, timemodified and scores (array).
     */
    public static function get_user_evaluation(int $postid, int $userid): ?\stdClass {
        global $DB;
        $eval = $DB->get_record('local_imageblog_evals', ['postid' => $postid, 'userid' => $userid]);
        if (!$eval) {
            return null;
        }
        $eval->scores = self::scores_for_eval((int)$eval->id);
        return $eval;
    }

    /**
     * All reader evaluations for a post, each with the submitter's name and a
     * criterionid => score map. Oldest first.
     *
     * @param int $postid
     * @return \stdClass[]
     */
    public static function get_evaluations(int $postid): array {
        global $DB;
        $namefields = implode(', u.', \core_user\fields::get_name_fields());
        $sql = "SELECT e.*, u.{$namefields}
                  FROM {local_imageblog_evals} e
                  JOIN {user} u ON u.id = e.userid
                 WHERE e.postid = :postid
              ORDER BY e.timecreated ASC";
        $rows = array_values($DB->get_records_sql($sql, ['postid' => $postid]));
        foreach ($rows as $row) {
            $row->scores = self::scores_for_eval((int)$row->id);
        }
        return $rows;
    }

    /**
     * criterionid => score map for one evaluation.
     *
     * @param int $evalid
     * @return array<int,int>
     */
    private static function scores_for_eval(int $evalid): array {
        global $DB;
        $rows = $DB->get_records('local_imageblog_eval_scores', ['evalid' => $evalid], '', 'criterionid, score');
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->criterionid] = (int)$r->score;
        }
        return $out;
    }

    /**
     * Score a reader's evaluation: completeness x agreement.
     *
     * Completeness counts the criteria scored plus the comment, over the number
     * of criteria plus one. Agreement averages, over the criteria the reader
     * scored and the author referenced, 1 - |score - reference| / (maxscore-1).
     * Returns null when the post has no criteria, no reader evaluation, or no
     * reference set.
     *
     * @param post $post
     * @param int  $userid
     * @return array|null ['completeness'=>float, 'agreement'=>float, 'percent'=>int]
     */
    public static function score_for(post $post, int $userid): ?array {
        $criteria = self::get_criteria($post);
        if (!$criteria) {
            return null;
        }
        $eval = self::get_user_evaluation($post->id, $userid);
        if (!$eval) {
            return null;
        }
        $refs = self::get_reference($post->id);
        if (!$refs) {
            return null;
        }

        $total = count($criteria);
        $scored = 0;
        foreach ($criteria as $c) {
            if (array_key_exists((int)$c->id, $eval->scores)) {
                $scored++;
            }
        }
        $hascomment = trim((string)$eval->commenttext) !== '';
        // Criteria + the comment are the completable items.
        $completeness = ($scored + ($hascomment ? 1 : 0)) / ($total + 1);

        $range = max(1, self::maxscore($post) - 1);
        $agreements = [];
        foreach ($criteria as $c) {
            $cid = (int)$c->id;
            if (array_key_exists($cid, $eval->scores) && array_key_exists($cid, $refs)) {
                $distance = abs($eval->scores[$cid] - $refs[$cid]);
                $agreements[] = 1 - ($distance / $range);
            }
        }
        $agreement = $agreements ? array_sum($agreements) / count($agreements) : 0.0;

        return [
            'completeness' => $completeness,
            'agreement'    => $agreement,
            'percent'      => (int)round($completeness * $agreement * 100),
        ];
    }

    /**
     * Mean reader score per criterion and the submission count, for the
     * post-reveal aggregate display.
     *
     * @param int $postid
     * @return array ['count'=>int, 'means'=>array<int,float>]
     */
    public static function aggregate(int $postid): array {
        global $DB;
        $sql = "SELECT es.criterionid, COUNT(es.id) AS n, AVG(es.score) AS meanscore
                  FROM {local_imageblog_eval_scores} es
                  JOIN {local_imageblog_evals} e ON e.id = es.evalid
                 WHERE e.postid = :postid
              GROUP BY es.criterionid";
        $means = [];
        foreach ($DB->get_records_sql($sql, ['postid' => $postid]) as $r) {
            $means[(int)$r->criterionid] = round((float)$r->meanscore, 2);
        }
        $count = (int)$DB->count_records('local_imageblog_evals', ['postid' => $postid]);
        return ['count' => $count, 'means' => $means];
    }

    /**
     * Mark an evaluate post revealed and queue the CPD award for participants.
     *
     * @param int $postid
     */
    public static function reveal(int $postid): void {
        global $DB;
        $now = time();
        $DB->update_record('local_imageblog_posts', (object)[
            'id'               => $postid,
            'caserevealed'     => 1,
            'caserevealedtime' => $now,
            'timemodified'     => $now,
        ]);
        case_post::queue_cpd_award($postid);
    }

    /**
     * Whether an evaluate post is eligible to accrue CPD: an evaluate-type post,
     * revealed, and set to the assessable mode. Practice-mode posts show a
     * sample score but never record CPD.
     *
     * @param \stdClass|post $post
     * @return bool
     */
    public static function is_cpd_eval($post): bool {
        return post_type::is_evaluate((string)$post->posttype)
            && !empty($post->caserevealed)
            && ($post->assessmentmode ?? post_type::MODE_NONE) === post_type::MODE_ASSESSABLE;
    }

    /**
     * Award (or refresh) participation CPD for everyone who evaluated this post.
     * Mirrors the diagnose award: base hours scaled by difficulty and the
     * submit factor. Idempotent.
     *
     * @param int $postid
     */
    public static function award_cpd_for_eval(int $postid): void {
        global $DB;
        $record = $DB->get_record('local_imageblog_posts', ['id' => $postid], '*', MUST_EXIST);
        if (!self::is_cpd_eval($record)) {
            return;
        }
        $hours = case_post::compute_hours((int)$record->casedifficulty, case_post::REASON_PARTICIPATION);
        if ($hours <= 0) {
            return;
        }
        $evals = $DB->get_records('local_imageblog_evals', ['postid' => $postid], '', 'id, userid');
        foreach ($evals as $e) {
            case_post::upsert_cpd_row($postid, (int)$e->userid, case_post::REASON_PARTICIPATION, $hours);
        }
    }

    /**
     * Total CPD hours a user has earned on this post (participation rows share
     * the case CPD table).
     *
     * @param int $postid
     * @param int $userid
     * @return float
     */
    public static function get_user_total_hours(int $postid, int $userid): float {
        return case_post::get_user_total_hours($postid, $userid);
    }
}
