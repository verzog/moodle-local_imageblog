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
 * Unit tests for the evaluate interaction.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Unit tests for evaluations, reference ratings, scoring and CPD.
 *
 * @covers \local_imageblog\evaluation
 */
final class evaluation_test extends \advanced_testcase {
    /** @var int[] criterion ids [c1, c2] */
    private array $critids = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $syscontext = \context_system::instance();
        $userrole = $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('local/imageblog:createpost', CAP_ALLOW, $userrole, $syscontext->id, true);
        assign_capability('local/imageblog:publishpost', CAP_ALLOW, $userrole, $syscontext->id, true);
        set_config('cpd_basehours', 1, 'local_imageblog');
        set_config('cpd_submit_factor', 0.75, 'local_imageblog');
        set_config('cpd_difficulty_scale', '0.5,0.75,1.0,1.25,1.5', 'local_imageblog');
        set_config('case_cpd_enabled', 1, 'local_imageblog');
    }

    /**
     * Create a published evaluate post with a two-criterion rubric (scale 1-5),
     * authored by the given user. Returns the post id.
     *
     * @param \stdClass $author
     * @param string    $mode
     * @return int
     */
    private function create_eval_post(\stdClass $author, string $mode = post_type::MODE_ASSESSABLE): int {
        $rid = rubric::save_template((object)['name' => 'Rubric', 'maxscore' => 5]);
        $this->critids = [
            rubric::save_criterion((object)['rubricid' => $rid, 'name' => 'Clarity', 'sortorder' => 1]),
            rubric::save_criterion((object)['rubricid' => $rid, 'name' => 'Depth', 'sortorder' => 2]),
        ];
        $this->setUser($author);
        return post::save((object)[
            'title'          => 'A tutorial',
            'status'         => post::STATUS_PUBLISHED,
            'posttype'       => post_type::TUTORIAL,
            'assessmentmode' => $mode,
            'casedifficulty' => 3,
            'rubricid'       => $rid,
        ], \context_system::instance());
    }

    public function test_score_is_completeness_times_agreement(): void {
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_eval_post($author);
        $post = post::get($postid);
        [$c1, $c2] = $this->critids;

        evaluation::set_reference($post, [$c1 => 5, $c2 => 3]);
        // Reader: c1 exact (agree 1.0), c2 off by 2 of 4 (agree 0.5), with comment.
        evaluation::submit_evaluation($post, (int)$reader->id, [$c1 => 5, $c2 => 1], 'Good tutorial');

        $score = evaluation::score_for($post, (int)$reader->id);
        $this->assertNotNull($score);
        $this->assertEqualsWithDelta(1.0, $score['completeness'], 0.001);
        $this->assertEqualsWithDelta(0.75, $score['agreement'], 0.001);
        $this->assertSame(75, $score['percent']);
    }

    public function test_incomplete_evaluation_scores_lower(): void {
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_eval_post($author);
        $post = post::get($postid);
        [$c1, $c2] = $this->critids;

        evaluation::set_reference($post, [$c1 => 5, $c2 => 3]);
        // Only one criterion scored, no comment: completeness = 1/3, agreement = 1.0.
        evaluation::submit_evaluation($post, (int)$reader->id, [$c1 => 5], '');

        $score = evaluation::score_for($post, (int)$reader->id);
        $this->assertEqualsWithDelta(1 / 3, $score['completeness'], 0.001);
        $this->assertEqualsWithDelta(1.0, $score['agreement'], 0.001);
        $this->assertSame(33, $score['percent']);
    }

    public function test_score_null_without_reference(): void {
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_eval_post($author);
        $post = post::get($postid);
        [$c1, $c2] = $this->critids;

        evaluation::submit_evaluation($post, (int)$reader->id, [$c1 => 4, $c2 => 4], 'x');
        $this->assertNull(evaluation::score_for($post, (int)$reader->id));
        $this->assertFalse(evaluation::has_full_reference($post));

        evaluation::set_reference($post, [$c1 => 4, $c2 => 4]);
        $this->assertTrue(evaluation::has_full_reference($post));
    }

    public function test_reveal_awards_cpd_when_assessable(): void {
        global $DB;
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_eval_post($author, post_type::MODE_ASSESSABLE);
        $post = post::get($postid);
        [$c1, $c2] = $this->critids;
        evaluation::set_reference($post, [$c1 => 5, $c2 => 5]);
        evaluation::submit_evaluation($post, (int)$reader->id, [$c1 => 5, $c2 => 5], 'great');

        evaluation::reveal($postid);
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }

        // base 1 x difficulty-3 multiplier 1.0 x submit factor 0.75 = 0.75.
        $this->assertEqualsWithDelta(0.75, evaluation::get_user_total_hours($postid, (int)$reader->id), 0.001);
    }

    public function test_reveal_awards_no_cpd_when_practice(): void {
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_eval_post($author, post_type::MODE_PRACTICE);
        $post = post::get($postid);
        [$c1, $c2] = $this->critids;
        evaluation::set_reference($post, [$c1 => 5, $c2 => 5]);
        evaluation::submit_evaluation($post, (int)$reader->id, [$c1 => 5, $c2 => 5], 'great');

        evaluation::reveal($postid);
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }

        $this->assertEqualsWithDelta(0.0, evaluation::get_user_total_hours($postid, (int)$reader->id), 0.001);
    }

    public function test_aggregate_counts_and_means(): void {
        $author = $this->getDataGenerator()->create_user();
        $r1 = $this->getDataGenerator()->create_user();
        $r2 = $this->getDataGenerator()->create_user();
        $postid = $this->create_eval_post($author);
        $post = post::get($postid);
        [$c1, $c2] = $this->critids;

        evaluation::submit_evaluation($post, (int)$r1->id, [$c1 => 4, $c2 => 2], 'a');
        evaluation::submit_evaluation($post, (int)$r2->id, [$c1 => 2, $c2 => 4], 'b');

        $agg = evaluation::aggregate($postid);
        $this->assertSame(2, $agg['count']);
        $this->assertEqualsWithDelta(3.0, $agg['means'][$c1], 0.001);
        $this->assertEqualsWithDelta(3.0, $agg['means'][$c2], 0.001);
    }

    public function test_one_evaluation_per_user(): void {
        global $DB;
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_eval_post($author);
        $post = post::get($postid);
        [$c1, $c2] = $this->critids;

        evaluation::submit_evaluation($post, (int)$reader->id, [$c1 => 1, $c2 => 1], 'first');
        evaluation::submit_evaluation($post, (int)$reader->id, [$c1 => 5, $c2 => 5], 'second');

        $this->assertSame(1, $DB->count_records('local_imageblog_evals', ['postid' => $postid]));
        $eval = evaluation::get_user_evaluation($postid, (int)$reader->id);
        $this->assertSame('second', $eval->commenttext);
        $this->assertSame(5, $eval->scores[$c1]);
    }
}
