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
 * Context-neutral scoring primitives for clinical cases.
 *
 * These are pure functions with no dependency on Moodle configuration, the
 * database, or any particular plugin context: they take plain values and
 * return a result. The site-wide blog (local_imageblog) reads admin config and
 * feeds it here to compute CPD hours; the companion activity module
 * (mod_imageblog) can feed per-instance settings into the same primitives. This
 * keeps one definition of "base x difficulty multiplier x factor" instead of
 * the two plugins drifting apart.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scoring {

    /**
     * Parse a comma-separated difficulty-scale string into a list of floats.
     *
     * The input is admin-editable text such as "1, 1.5, 2, 3"; each entry is
     * the multiplier for the difficulty level at that position (level 1 first).
     *
     * @param string $raw the raw configured scale
     * @return float[] the parsed multipliers, in order
     */
    public static function parse_scale(string $raw): array {
        return array_map('floatval', array_map('trim', explode(',', $raw)));
    }

    /**
     * Resolve the multiplier for a 1-based difficulty level from a scale list.
     *
     * The level is clamped into range, so a difficulty beyond the end of the
     * scale uses the last entry and anything below 1 uses the first. An empty
     * scale yields a neutral multiplier of 1.0.
     *
     * @param float[] $scale the ordered multipliers
     * @param int $difficulty the 1-based difficulty level
     * @return float the multiplier to apply
     */
    public static function difficulty_multiplier(array $scale, int $difficulty): float {
        if (!$scale) {
            return 1.0;
        }
        $idx = max(0, min(count($scale) - 1, $difficulty - 1));
        return (float) ($scale[$idx] ?? 1.0);
    }

    /**
     * Compute awarded hours from pure inputs, rounded to two decimals.
     *
     * @param float $base the base hours for a case
     * @param float $multiplier the difficulty multiplier
     * @param float $factor the per-reason factor (participation, view, bonus)
     * @return float the awarded hours (never negative)
     */
    public static function hours(float $base, float $multiplier, float $factor): float {
        $hours = $base * $multiplier * $factor;
        return $hours > 0 ? round($hours, 2) : 0.0;
    }
}
