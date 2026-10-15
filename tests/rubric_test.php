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
 * Unit tests for rubric templates and criteria.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Unit tests for the rubric CRUD helpers.
 *
 * @covers \local_imageblog\rubric
 */
final class rubric_test extends \advanced_testcase {
    public function test_save_and_fetch_template(): void {
        $this->resetAfterTest();
        $id = rubric::save_template((object)['name' => 'Design review', 'maxscore' => 4, 'sortorder' => 2]);
        $this->assertGreaterThan(0, $id);
        $template = rubric::get_template($id);
        $this->assertSame('Design review', $template->name);
        $this->assertSame(4, (int)$template->maxscore);
        $this->assertTrue(rubric::template_exists($id));
        $this->assertArrayHasKey($id, rubric::template_menu());
    }

    public function test_maxscore_is_clamped(): void {
        $this->resetAfterTest();
        $low = rubric::save_template((object)['name' => 'Too low', 'maxscore' => 1]);
        $high = rubric::save_template((object)['name' => 'Too high', 'maxscore' => 99]);
        $this->assertSame(rubric::MIN_MAXSCORE, (int)rubric::get_template($low)->maxscore);
        $this->assertSame(rubric::MAX_MAXSCORE, (int)rubric::get_template($high)->maxscore);
    }

    public function test_save_template_rejects_empty_name(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        rubric::save_template((object)['name' => '   ']);
    }

    public function test_criteria_crud_and_ordering(): void {
        $this->resetAfterTest();
        $rid = rubric::save_template((object)['name' => 'R']);
        $c2 = rubric::save_criterion((object)['rubricid' => $rid, 'name' => 'Second', 'sortorder' => 2]);
        $c1 = rubric::save_criterion((object)['rubricid' => $rid, 'name' => 'First', 'sortorder' => 1]);

        $criteria = rubric::get_criteria($rid);
        $this->assertCount(2, $criteria);
        $this->assertSame('First', $criteria[0]->name);
        $this->assertSame('Second', $criteria[1]->name);

        rubric::delete_criterion($c1);
        $this->assertCount(1, rubric::get_criteria($rid));
        $this->assertSame($c2, (int)rubric::get_criteria($rid)[0]->id);
    }

    public function test_save_criterion_requires_valid_template(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        rubric::save_criterion((object)['rubricid' => 999999, 'name' => 'Orphan']);
    }

    public function test_delete_template_cascades_to_criteria(): void {
        global $DB;
        $this->resetAfterTest();
        $rid = rubric::save_template((object)['name' => 'R']);
        rubric::save_criterion((object)['rubricid' => $rid, 'name' => 'C']);
        $this->assertTrue($DB->record_exists('local_imageblog_rubric_crit', ['rubricid' => $rid]));

        rubric::delete_template($rid);
        $this->assertNull(rubric::get_template($rid));
        $this->assertFalse($DB->record_exists('local_imageblog_rubric_crit', ['rubricid' => $rid]));
    }
}
