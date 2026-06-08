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
 * Unit tests for lib.php helpers.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/imageblog/lib.php');

/**
 * Unit tests for plugin library helpers.
 *
 * @coversNothing
 */
final class lib_test extends \advanced_testcase {
    /**
     * Grant publish + create caps to the authenticated user role so test
     * users can save posts in any status via post::save().
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $syscontext = \context_system::instance();
        $userrole = $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('local/imageblog:createpost', CAP_ALLOW, $userrole, $syscontext->id, true);
        assign_capability('local/imageblog:publishpost', CAP_ALLOW, $userrole, $syscontext->id, true);
    }

    public function test_get_custom_css_html_returns_empty_when_unset(): void {
        $this->resetAfterTest();
        set_config('customcss', '', 'local_imageblog');
        $this->assertSame('', local_imageblog_get_custom_css_html());
    }

    public function test_get_custom_css_html_wraps_value_in_style_tag(): void {
        $this->resetAfterTest();
        set_config('customcss', 'body { color: red; }', 'local_imageblog');
        $html = local_imageblog_get_custom_css_html();
        $this->assertStringContainsString('<style', $html);
        $this->assertStringContainsString('color: red', $html);
    }

    public function test_get_custom_css_html_escapes_closing_style_tag(): void {
        $this->resetAfterTest();
        set_config('customcss', "body { color: red; }</style><script>alert(1)</script>", 'local_imageblog');
        $html = local_imageblog_get_custom_css_html();
        // The literal closing style must be neutralised so it can't break out.
        $this->assertStringNotContainsString('</style><script', $html);
    }

    public function test_get_taxonomy_returns_lists_keyed_by_section(): void {
        $this->resetAfterTest();
        $result = local_imageblog_get_taxonomy();
        $this->assertArrayHasKey('authors', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('subcategories', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('levels', $result);
        $this->assertIsArray($result['categories']);
    }

    public function test_get_taxonomy_authors_only_lists_published_post_owners(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user(['firstname' => 'Alice']);
        $bob   = $this->getDataGenerator()->create_user(['firstname' => 'Bob']);

        $this->setUser($alice);
        post::save(
            (object)['title' => 'Alice published', 'status' => post::STATUS_PUBLISHED],
            \context_system::instance()
        );
        $this->setUser($bob);
        post::save(
            (object)['title' => 'Bob draft', 'status' => post::STATUS_DRAFT],
            \context_system::instance()
        );

        $tax = local_imageblog_get_taxonomy();
        $authorids = array_map(fn($a) => (int)$a['id'], $tax['authors']);
        $this->assertContains((int)$alice->id, $authorids);
        $this->assertNotContains((int)$bob->id, $authorids);
    }
}
