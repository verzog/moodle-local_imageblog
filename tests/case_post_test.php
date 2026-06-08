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
 * Unit tests for case_post.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Unit tests for clinical case operations.
 *
 * @covers \local_imageblog\case_post
 */
final class case_post_test extends \advanced_testcase {
    /**
     * Grant publish + create caps to the authenticated user role so test
     * users can save published cases via post::save().
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $syscontext = \context_system::instance();
        $userrole = $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('local/imageblog:createpost', CAP_ALLOW, $userrole, $syscontext->id, true);
        assign_capability('local/imageblog:publishpost', CAP_ALLOW, $userrole, $syscontext->id, true);
    }

    /**
     * Create a published case post owned by the given user.
     *
     * @param \stdClass $author
     * @param int       $difficulty
     * @return int Post id.
     */
    private function create_case(\stdClass $author, int $difficulty = 3): int {
        $this->setUser($author);
        return post::save((object)[
            'title'          => 'Pigmented lesion',
            'status'         => post::STATUS_PUBLISHED,
            'posttype'       => case_post::TYPE_CASE,
            'casedifficulty' => $difficulty,
        ], \context_system::instance());
    }

    /**
     * Configure deterministic CPD rates so the maths is easy to assert.
     */
    private function set_default_cpd_config(): void {
        set_config('cpd_basehours', '1.0', 'local_imageblog');
        set_config('cpd_difficulty_scale', '0.5,0.75,1.0,1.25,1.5', 'local_imageblog');
        set_config('cpd_submit_factor', '0.75', 'local_imageblog');
        set_config('cpd_view_factor', '0.25', 'local_imageblog');
        set_config('cpd_best_bonus', '0.25', 'local_imageblog');
    }

    public function test_submit_diagnosis_inserts_then_updates_same_row(): void {
        global $DB;
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_case($author);

        $first = case_post::submit_diagnosis($postid, (int)$reader->id, 'Melanoma', 'Asymmetric');
        $second = case_post::submit_diagnosis($postid, (int)$reader->id, 'Nevus', 'On reflection');

        $this->assertSame($first, $second);
        $this->assertSame(1, $DB->count_records('local_imageblog_case_diags', [
            'postid' => $postid, 'userid' => $reader->id,
        ]));
        $row = case_post::get_user_diagnosis($postid, (int)$reader->id);
        $this->assertSame('Nevus', $row->diagnosis);
        $this->assertSame('On reflection', $row->reasoning);
    }

    public function test_questions_are_listed_in_order_with_asker_name(): void {
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $alice  = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'A']);
        $bob    = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'B']);
        $postid = $this->create_case($author);

        case_post::ask_question($postid, (int)$alice->id, 'Border?');
        case_post::ask_question($postid, (int)$bob->id, 'Diameter?');

        $rows = case_post::get_questions($postid);
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]->firstname);
        $this->assertSame('Bob', $rows[1]->firstname);
    }

    public function test_answer_question_stores_author_and_timestamp(): void {
        global $DB;
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_case($author);

        $qid = case_post::ask_question($postid, (int)$reader->id, 'Edge?');
        case_post::answer_question($qid, (int)$author->id, 'Irregular.');

        $row = $DB->get_record('local_imageblog_case_qs', ['id' => $qid]);
        $this->assertSame('Irregular.', $row->answer);
        $this->assertSame((int)$author->id, (int)$row->answeredby);
        $this->assertNotNull($row->timeanswered);
    }

    public function test_reveal_marks_post_and_awards_participation_cpd(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_default_cpd_config();
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_case($author, 3);

        case_post::submit_diagnosis($postid, (int)$reader->id, 'Melanoma');
        case_post::reveal($postid);

        $post = $DB->get_record('local_imageblog_posts', ['id' => $postid]);
        $this->assertSame(1, (int)$post->caserevealed);
        $this->assertNotNull($post->caserevealedtime);

        $cpd = $DB->get_record('local_imageblog_case_cpd', [
            'postid' => $postid,
            'userid' => $reader->id,
            'reason' => case_post::REASON_PARTICIPATION,
        ]);
        // Difficulty 3 × base 1 × submit 0.75 = 0.75.
        $this->assertEqualsWithDelta(0.75, (float)$cpd->hours, 0.001);
    }

    public function test_view_only_cpd_only_awarded_when_no_diagnosis(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_default_cpd_config();
        $author       = $this->getDataGenerator()->create_user();
        $participant  = $this->getDataGenerator()->create_user();
        $viewer       = $this->getDataGenerator()->create_user();
        $postid       = $this->create_case($author, 3);

        case_post::submit_diagnosis($postid, (int)$participant->id, 'Melanoma');
        case_post::reveal($postid);

        case_post::award_view_if_eligible($postid, (int)$viewer->id);
        case_post::award_view_if_eligible($postid, (int)$participant->id);

        $this->assertTrue($DB->record_exists('local_imageblog_case_cpd', [
            'postid' => $postid,
            'userid' => $viewer->id,
            'reason' => case_post::REASON_VIEW,
        ]));
        $this->assertFalse($DB->record_exists('local_imageblog_case_cpd', [
            'postid' => $postid,
            'userid' => $participant->id,
            'reason' => case_post::REASON_VIEW,
        ]));
    }

    public function test_set_best_diagnosis_awards_bonus_and_clears_previous(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_default_cpd_config();
        $author = $this->getDataGenerator()->create_user();
        $alice  = $this->getDataGenerator()->create_user();
        $bob    = $this->getDataGenerator()->create_user();
        $postid = $this->create_case($author, 3);

        $aliceid = case_post::submit_diagnosis($postid, (int)$alice->id, 'Melanoma');
        $bobid   = case_post::submit_diagnosis($postid, (int)$bob->id, 'Nevus');
        case_post::reveal($postid);

        case_post::set_best_diagnosis($postid, $aliceid);
        $this->assertTrue($DB->record_exists('local_imageblog_case_cpd', [
            'postid' => $postid,
            'userid' => $alice->id,
            'reason' => case_post::REASON_BEST,
        ]));

        // Switch the choice — Alice's bonus should disappear, Bob's should appear.
        case_post::set_best_diagnosis($postid, $bobid);
        $this->assertFalse($DB->record_exists('local_imageblog_case_cpd', [
            'postid' => $postid,
            'userid' => $alice->id,
            'reason' => case_post::REASON_BEST,
        ]));
        $this->assertTrue($DB->record_exists('local_imageblog_case_cpd', [
            'postid' => $postid,
            'userid' => $bob->id,
            'reason' => case_post::REASON_BEST,
        ]));
    }

    public function test_record_cpd_noop_when_post_is_not_a_revealed_case(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_default_cpd_config();
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();

        // Non-case post.
        $this->setUser($author);
        $blogid = post::save((object)[
            'title'  => 'Blog',
            'status' => post::STATUS_PUBLISHED,
        ], \context_system::instance());
        case_post::record_cpd($blogid, (int)$reader->id, case_post::REASON_VIEW);
        $this->assertSame(0, $DB->count_records('local_imageblog_case_cpd', ['postid' => $blogid]));

        // Case but not revealed.
        $caseid = $this->create_case($author);
        case_post::record_cpd($caseid, (int)$reader->id, case_post::REASON_VIEW);
        $this->assertSame(0, $DB->count_records('local_imageblog_case_cpd', ['postid' => $caseid]));
    }

    public function test_compute_hours_scales_by_difficulty(): void {
        $this->resetAfterTest();
        $this->set_default_cpd_config();

        // Submit factor is 0.75; difficulty multipliers are 0.5,0.75,1,1.25,1.5.
        $this->assertEqualsWithDelta(0.38, case_post::compute_hours(1, case_post::REASON_PARTICIPATION), 0.01);
        $this->assertEqualsWithDelta(0.75, case_post::compute_hours(3, case_post::REASON_PARTICIPATION), 0.01);
        $this->assertEqualsWithDelta(1.13, case_post::compute_hours(5, case_post::REASON_PARTICIPATION), 0.01);

        // Difficulty out of range clamps to the boundary.
        $this->assertEqualsWithDelta(0.38, case_post::compute_hours(0, case_post::REASON_PARTICIPATION), 0.01);
        $this->assertEqualsWithDelta(1.13, case_post::compute_hours(99, case_post::REASON_PARTICIPATION), 0.01);
    }

    public function test_get_user_total_hours_sums_across_reasons(): void {
        $this->resetAfterTest();
        $this->set_default_cpd_config();
        $author = $this->getDataGenerator()->create_user();
        $reader = $this->getDataGenerator()->create_user();
        $postid = $this->create_case($author, 3);

        $diagid = case_post::submit_diagnosis($postid, (int)$reader->id, 'Melanoma');
        case_post::reveal($postid);
        case_post::set_best_diagnosis($postid, $diagid);

        $total = case_post::get_user_total_hours($postid, (int)$reader->id);
        // Participation 0.75 + best 0.25 = 1.0.
        $this->assertEqualsWithDelta(1.0, $total, 0.01);
    }
}
