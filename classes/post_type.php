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
 * Post-type and assessment-mode registry.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * Defines the blog's post types (the "category") and assessment modes (the
 * "subcategory"), and which types carry a reader assessment.
 *
 * Two interaction patterns exist:
 *  - Diagnose: the reader submits a diagnosis and the author reveals the
 *    outcome (Patient Case, Case Study).
 *  - Evaluate: the reader appraises the post itself via a rubric and comment
 *    (Tutorial, Critical Review, Design Evaluation). The evaluate interaction
 *    is wired in a later phase; here these are valid types that may carry an
 *    assessment mode.
 * The remaining types (Blog Post, News, Reference Article) are read-only.
 */
class post_type {
    /** @var string General editorial post (read-only). */
    const BLOG = 'blog';
    /** @var string Announcement/news post (read-only). */
    const NEWS = 'news';
    /** @var string Reference article (read-only). */
    const REFERENCE = 'reference';
    /** @var string Patient case — a person; diagnose pattern. */
    const PATIENTCASE = 'patientcase';
    /** @var string Case study — any situation/field; diagnose pattern. */
    const CASESTUDY = 'casestudy';
    /** @var string Tutorial; evaluate pattern. */
    const TUTORIAL = 'tutorial';
    /** @var string Critical review; evaluate pattern. */
    const REVIEW = 'review';
    /** @var string Design evaluation; evaluate pattern. */
    const DESIGNEVAL = 'designeval';

    /** @var string Not assessed: no interactive block. */
    const MODE_NONE = 'none';
    /** @var string Practice: reader attempts and sees a sample score; no CPD recorded. */
    const MODE_PRACTICE = 'practice';
    /** @var string Assessable: participation earns CPD hours. */
    const MODE_ASSESSABLE = 'assessable';

    /**
     * All post-type slugs, in display order.
     *
     * @return string[]
     */
    public static function all(): array {
        return [
            self::BLOG,
            self::NEWS,
            self::REFERENCE,
            self::PATIENTCASE,
            self::CASESTUDY,
            self::TUTORIAL,
            self::REVIEW,
            self::DESIGNEVAL,
        ];
    }

    /**
     * Types that use the diagnose interaction (submit a diagnosis, reveal).
     *
     * @return string[]
     */
    public static function diagnose_types(): array {
        return [self::PATIENTCASE, self::CASESTUDY];
    }

    /**
     * Types that use the evaluate interaction (reader appraises the post).
     *
     * @return string[]
     */
    public static function evaluate_types(): array {
        return [self::TUTORIAL, self::REVIEW, self::DESIGNEVAL];
    }

    /**
     * Whether a slug is a known post type.
     *
     * @param string $type
     * @return bool
     */
    public static function is_valid(string $type): bool {
        return in_array($type, self::all(), true);
    }

    /**
     * Whether a type uses the diagnose interaction.
     *
     * @param string $type
     * @return bool
     */
    public static function is_diagnose(string $type): bool {
        return in_array($type, self::diagnose_types(), true);
    }

    /**
     * Whether a type uses the evaluate interaction.
     *
     * @param string $type
     * @return bool
     */
    public static function is_evaluate(string $type): bool {
        return in_array($type, self::evaluate_types(), true);
    }

    /**
     * Whether a type carries any reader assessment (diagnose or evaluate).
     *
     * @param string $type
     * @return bool
     */
    public static function is_interactive(string $type): bool {
        return self::is_diagnose($type) || self::is_evaluate($type);
    }

    /**
     * All assessment-mode slugs.
     *
     * @return string[]
     */
    public static function modes(): array {
        return [self::MODE_NONE, self::MODE_PRACTICE, self::MODE_ASSESSABLE];
    }

    /**
     * Whether a slug is a known assessment mode.
     *
     * @param string $mode
     * @return bool
     */
    public static function is_valid_mode(string $mode): bool {
        return in_array($mode, self::modes(), true);
    }

    /**
     * Post-type select menu: slug => localised label.
     *
     * @return array
     */
    public static function menu(): array {
        $out = [];
        foreach (self::all() as $type) {
            $out[$type] = get_string('posttype_' . $type, 'local_imageblog');
        }
        return $out;
    }

    /**
     * Assessment-mode select menu: slug => localised label.
     *
     * @return array
     */
    public static function mode_menu(): array {
        $out = [];
        foreach (self::modes() as $mode) {
            $out[$mode] = get_string('mode_' . $mode, 'local_imageblog');
        }
        return $out;
    }
}
