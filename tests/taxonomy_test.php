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
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Unit tests for taxonomy CRUD operations.
 *
 * @covers \local_imageblog\taxonomy
 */
final class taxonomy_test extends \advanced_testcase {
    public function test_save_and_fetch_category(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_CATEGORY, (object)['name' => 'News', 'sortorder' => 5]);
        $this->assertGreaterThan(0, $id);

        $row = taxonomy::get(taxonomy::TYPE_CATEGORY, $id);
        $this->assertSame('News', $row->name);
        $this->assertSame(5, (int)$row->sortorder);

        $all = taxonomy::all(taxonomy::TYPE_CATEGORY);
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

    public function test_save_subcategory_requires_parent(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        taxonomy::save(taxonomy::TYPE_SUBCATEGORY, (object)['name' => 'Orphan']);
    }

    public function test_save_rejects_empty_name(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        taxonomy::save(taxonomy::TYPE_TAG, (object)['name' => '   ']);
    }

    public function test_delete_category_cascades(): void {
        global $DB;
        $this->resetAfterTest();

        $catid = taxonomy::save(taxonomy::TYPE_CATEGORY, (object)['name' => 'Tutorial']);
        $subid = taxonomy::save(taxonomy::TYPE_SUBCATEGORY, (object)[
            'name'       => 'Dermoscopy',
            'categoryid' => $catid,
        ]);

        // Manually associate a fake post so we can verify the cascade.
        $postid = $DB->insert_record('local_imageblog_post_cats', (object)[
            'postid'        => 1,
            'categoryid'    => $catid,
            'subcategoryid' => $subid,
        ]);
        $this->assertTrue($DB->record_exists('local_imageblog_post_cats', ['id' => $postid]));

        taxonomy::delete(taxonomy::TYPE_CATEGORY, $catid);

        $this->assertNull(taxonomy::get(taxonomy::TYPE_CATEGORY, $catid));
        $this->assertNull(taxonomy::get(taxonomy::TYPE_SUBCATEGORY, $subid));
        $this->assertFalse($DB->record_exists('local_imageblog_post_cats', ['categoryid' => $catid]));
    }

    public function test_update_existing_row(): void {
        $this->resetAfterTest();
        $id = taxonomy::save(taxonomy::TYPE_CATEGORY, (object)['name' => 'Original']);
        taxonomy::save(taxonomy::TYPE_CATEGORY, (object)[
            'id'        => $id,
            'name'      => 'Renamed',
            'sortorder' => 9,
        ]);
        $row = taxonomy::get(taxonomy::TYPE_CATEGORY, $id);
        $this->assertSame('Renamed', $row->name);
        $this->assertSame(9, (int)$row->sortorder);
    }
}
