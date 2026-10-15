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
 * Unit tests for the taxonomy CRUD helpers.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Unit tests for taxonomy CRUD operations.
 *
 * @covers \local_imageblog\taxonomy
 */
final class taxonomy_test extends \advanced_testcase {
    /**
     * Wipe seeded taxonomy rows so each test starts from an empty slate.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('local_imageblog_post_tags');
        $DB->delete_records('local_imageblog_post_levels');
        $DB->delete_records('local_imageblog_tags');
        $DB->delete_records('local_imageblog_levels');
    }

    public function test_save_and_fetch_level(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_LEVEL, (object)['name' => 'Beginner', 'sortorder' => 5]);
        $this->assertGreaterThan(0, $id);

        $row = taxonomy::get(taxonomy::TYPE_LEVEL, $id);
        $this->assertSame('Beginner', $row->name);
        $this->assertSame(5, (int)$row->sortorder);

        $all = taxonomy::all(taxonomy::TYPE_LEVEL);
        $this->assertCount(1, $all);
    }

    public function test_save_tag_generates_unique_slug(): void {
        $this->resetAfterTest();
        $id1 = taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => 'Hello World']);
        $id2 = taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => 'Hello World']);

        $row1 = taxonomy::get(taxonomy::TYPE_TAG, $id1);
        $row2 = taxonomy::get(taxonomy::TYPE_TAG, $id2);
        $this->assertSame('hello-world', $row1->slug);
        $this->assertSame('hello-world-2', $row2->slug);
    }

    public function test_save_level_rejects_invalid_colour(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_LEVEL, (object)[
            'name'      => 'Beginner',
            'colourkey' => 'rainbow',
        ]);
        $row = taxonomy::get(taxonomy::TYPE_LEVEL, $id);
        $this->assertSame('amber', $row->colourkey);
    }

    public function test_table_rejects_unknown_type(): void {
        $this->resetAfterTest();
        $this->expectException(\coding_exception::class);
        taxonomy::table('category');
    }

    public function test_save_rejects_empty_name(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => '   ']);
    }

    public function test_delete_level_cascades(): void {
        global $DB;
        $this->resetAfterTest();

        $levelid = taxonomy::save(taxonomy::TYPE_LEVEL, (object)['name' => 'Advanced']);

        // Manually associate a fake post so we can verify the cascade.
        $rowid = $DB->insert_record('local_imageblog_post_levels', (object)[
            'postid'  => 1,
            'levelid' => $levelid,
        ]);
        $this->assertTrue($DB->record_exists('local_imageblog_post_levels', ['id' => $rowid]));

        taxonomy::delete(taxonomy::TYPE_LEVEL, $levelid);

        $this->assertNull(taxonomy::get(taxonomy::TYPE_LEVEL, $levelid));
        $this->assertFalse($DB->record_exists('local_imageblog_post_levels', ['levelid' => $levelid]));
    }

    public function test_update_existing_row(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_LEVEL, (object)['name' => 'Original']);
        taxonomy::save(taxonomy::TYPE_LEVEL, (object)[
            'id'        => $id,
            'name'      => 'Renamed',
            'sortorder' => 9,
        ]);
        $row = taxonomy::get(taxonomy::TYPE_LEVEL, $id);
        $this->assertSame('Renamed', $row->name);
        $this->assertSame(9, (int)$row->sortorder);
    }
}
