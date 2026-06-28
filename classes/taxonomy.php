<?php
// Copyright (c) Vernon Apain / Educheckout.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Vernon Apain /
// Educheckout for use with Moodle. It is NOT free software and is NOT
// released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Educheckout. The software is provided "as
// is", without warranty of any kind, express or implied.

/**
 * Taxonomy CRUD helpers.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

namespace local_imageblog;

/**
 * Static CRUD operations for the four taxonomy tables used by the blog:
 * categories, subcategories, tags and difficulty levels.
 */
class taxonomy {
    /** @var string Categories taxonomy. */
    const TYPE_CATEGORY = 'category';
    /** @var string Subcategories taxonomy. */
    const TYPE_SUBCATEGORY = 'subcategory';
    /** @var string Tags taxonomy. */
    const TYPE_TAG = 'tag';
    /** @var string Difficulty levels taxonomy. */
    const TYPE_LEVEL = 'level';

    /** @var string[] Allowed colour keys on a level row. */
    const LEVEL_COLOURS = ['amber', 'teal', 'coral', 'purple'];

    /**
     * Map a type string to its database table name.
     *
     * @param string $type
     * @return string
     */
    public static function table(string $type): string {
        return match ($type) {
            self::TYPE_CATEGORY    => 'local_imageblog_categories',
            self::TYPE_SUBCATEGORY => 'local_imageblog_subcategories',
            self::TYPE_TAG         => 'local_imageblog_tags',
            self::TYPE_LEVEL       => 'local_imageblog_levels',
            default                => throw new \coding_exception("Unknown taxonomy type: {$type}"),
        };
    }

    /**
     * Fetch all rows of a taxonomy in display order.
     *
     * @param string $type
     * @return \stdClass[]
     */
    public static function all(string $type): array {
        global $DB;
        $sort = match ($type) {
            self::TYPE_TAG => 'name ASC',
            default        => 'sortorder ASC, name ASC',
        };
        return array_values($DB->get_records(self::table($type), null, $sort));
    }

    /**
     * Fetch a single row.
     *
     * @param string $type
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(string $type, int $id): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::table($type), ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Insert or update a taxonomy row.
     *
     * Accepts an object with at least a `name`, plus type-specific fields.
     * Returns the id.
     *
     * @param string $type
     * @param object $data
     * @return int
     */
    public static function save(string $type, object $data): int {
        global $DB;
        $table = self::table($type);

        $record = new \stdClass();
        $record->name = trim((string)($data->name ?? ''));
        if ($record->name === '') {
            throw new \moodle_exception('error_nameempty', 'local_imageblog');
        }

        switch ($type) {
            case self::TYPE_CATEGORY:
                $record->sortorder = (int)($data->sortorder ?? 0);
                if (empty($data->id)) {
                    $record->timecreated = time();
                }
                break;
            case self::TYPE_SUBCATEGORY:
                $record->categoryid = (int)($data->categoryid ?? 0);
                if (!$record->categoryid) {
                    throw new \moodle_exception('error_subcategoryparent', 'local_imageblog');
                }
                $record->sortorder = (int)($data->sortorder ?? 0);
                break;
            case self::TYPE_TAG:
                $record->slug = self::slugify($data->slug ?? $record->name);
                return self::save_tag_with_unique_slug($record, (int)($data->id ?? 0));
            case self::TYPE_LEVEL:
                $colour = (string)($data->colourkey ?? 'amber');
                if (!in_array($colour, self::LEVEL_COLOURS, true)) {
                    $colour = 'amber';
                }
                $record->colourkey = $colour;
                $record->sortorder = (int)($data->sortorder ?? 0);
                break;
        }

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record($table, $record);
            return $record->id;
        }

        return (int)$DB->insert_record($table, $record);
    }

    /**
     * Delete a taxonomy row plus its association rows.
     *
     * @param string $type
     * @param int $id
     */
    public static function delete(string $type, int $id): void {
        global $DB;
        switch ($type) {
            case self::TYPE_CATEGORY:
                $subs = $DB->get_fieldset_select(
                    'local_imageblog_subcategories',
                    'id',
                    'categoryid = :cid',
                    ['cid' => $id]
                );
                foreach ($subs as $subid) {
                    self::delete(self::TYPE_SUBCATEGORY, (int)$subid);
                }
                $DB->delete_records('local_imageblog_post_cats', ['categoryid' => $id]);
                break;
            case self::TYPE_SUBCATEGORY:
                $DB->set_field('local_imageblog_post_cats', 'subcategoryid', null, ['subcategoryid' => $id]);
                break;
            case self::TYPE_TAG:
                $DB->delete_records('local_imageblog_post_tags', ['tagid' => $id]);
                break;
            case self::TYPE_LEVEL:
                $DB->delete_records('local_imageblog_post_levels', ['levelid' => $id]);
                break;
        }
        $DB->delete_records(self::table($type), ['id' => $id]);
    }

    /**
     * Convert an arbitrary string to a URL-safe slug.
     *
     * @param string $value
     * @return string
     */
    public static function slugify(string $value): string {
        $value = trim($value);
        // Transliterate to ASCII when the intl extension is available so
        // non-ASCII titles (accents, CJK, etc.) don't all collapse to "tag".
        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate(
                'Any-Latin; Latin-ASCII; Lower()',
                $value
            );
            if ($transliterated !== false) {
                $value = $transliterated;
            }
        } else if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string)$value, '-');
        return $value !== '' ? $value : 'tag';
    }

    /**
     * Insert or update a tag, ensuring slug uniqueness against the database's
     * UNIQUE index even under concurrent writers.
     *
     * Computes a candidate slug (-2, -3, …) then retries on insert conflict
     * because a second writer can claim the same slug between the existence
     * check and the insert.
     *
     * @param \stdClass $record   Pre-populated tag record (without id on insert).
     * @param int       $existingid Existing tag id (0 on insert).
     * @return int Tag id.
     */
    private static function save_tag_with_unique_slug(\stdClass $record, int $existingid): int {
        global $DB;
        $base = $record->slug;
        $maxattempts = 8;
        for ($attempt = 0; $attempt < $maxattempts; $attempt++) {
            $record->slug = self::pick_slug_candidate($base, $existingid, $attempt);
            try {
                if ($existingid) {
                    $record->id = $existingid;
                    $DB->update_record('local_imageblog_tags', $record);
                    return $existingid;
                }
                return (int)$DB->insert_record('local_imageblog_tags', $record);
            } catch (\dml_write_exception $e) {
                // Unique-index conflict from a racing writer — try the next suffix.
                continue;
            }
        }
        throw new \moodle_exception('error_nameempty', 'local_imageblog');
    }

    /**
     * Pick the next free slug candidate by appending -2, -3, … as needed.
     *
     * @param string $base
     * @param int    $excludeid
     * @param int    $attempt   0 on the first try, then 1, 2, …
     * @return string
     */
    private static function pick_slug_candidate(string $base, int $excludeid, int $attempt): string {
        global $DB;
        $candidate = $base;
        $i = 2 + $attempt;
        $params = ['slug' => $candidate];
        $sql = 'slug = :slug';
        if ($excludeid) {
            $sql .= ' AND id <> :excludeid';
            $params['excludeid'] = $excludeid;
        }
        while ($DB->record_exists_select('local_imageblog_tags', $sql, $params)) {
            $candidate = $base . '-' . $i++;
            $params['slug'] = $candidate;
        }
        return $candidate;
    }
}
