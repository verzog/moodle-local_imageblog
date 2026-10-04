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

namespace local_imageblog\local;

/**
 * Unit tests for the context-neutral scoring primitives.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_imageblog\local\scoring
 */
final class scoring_test extends \basic_testcase {

    /**
     * Parsing a scale string yields the expected list of floats.
     */
    public function test_parse_scale(): void {
        $this->assertEquals([1.0, 1.5, 2.0, 3.0], scoring::parse_scale('1, 1.5, 2, 3'));
    }

    /**
     * The multiplier is read from the matching 1-based position.
     */
    public function test_difficulty_multiplier_in_range(): void {
        $scale = [1.0, 1.5, 2.0];
        $this->assertEqualsWithDelta(1.0, scoring::difficulty_multiplier($scale, 1), 0.0001);
        $this->assertEqualsWithDelta(1.5, scoring::difficulty_multiplier($scale, 2), 0.0001);
        $this->assertEqualsWithDelta(2.0, scoring::difficulty_multiplier($scale, 3), 0.0001);
    }

    /**
     * Out-of-range difficulty levels clamp to the ends of the scale.
     */
    public function test_difficulty_multiplier_clamps(): void {
        $scale = [1.0, 1.5, 2.0];
        $this->assertEqualsWithDelta(2.0, scoring::difficulty_multiplier($scale, 9), 0.0001);
        $this->assertEqualsWithDelta(1.0, scoring::difficulty_multiplier($scale, 0), 0.0001);
    }

    /**
     * An empty scale yields a neutral multiplier.
     */
    public function test_difficulty_multiplier_empty_scale(): void {
        $this->assertEqualsWithDelta(1.0, scoring::difficulty_multiplier([], 2), 0.0001);
    }

    /**
     * Hours are the product of the inputs, rounded to two decimals.
     */
    public function test_hours_product_and_rounding(): void {
        $this->assertEqualsWithDelta(3.0, scoring::hours(2.0, 1.5, 1.0), 0.0001);
        $this->assertEqualsWithDelta(1.33, scoring::hours(1.0, 1.333, 1.0), 0.0001);
    }

    /**
     * A zero or negative product yields zero hours, never a negative award.
     */
    public function test_hours_never_negative(): void {
        $this->assertEquals(0.0, scoring::hours(2.0, 1.0, 0.0));
        $this->assertEquals(0.0, scoring::hours(2.0, -1.0, 1.0));
    }
}
