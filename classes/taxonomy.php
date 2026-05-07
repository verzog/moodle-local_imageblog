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
 * Taxonomy CRUD helpers.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
                self::ensure_unique_slug($record->slug, (int)($data->id ?? 0));
                break;
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
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string)$value, '-');
        return $value !== '' ? $value : 'tag';
    }

    /**
     * Ensure the given tag slug is unique by appending -2, -3, etc. when needed.
     *
     * @param string $slug Mutated by reference to a unique value.
     * @param int $excludeid Existing tag id to ignore (for updates).
     */
    private static function ensure_unique_slug(string &$slug, int $excludeid = 0): void {
        global $DB;
        $candidate = $slug;
        $i = 2;
        $params = ['slug' => $candidate];
        $sql = 'slug = :slug';
        if ($excludeid) {
            $sql .= ' AND id <> :excludeid';
            $params['excludeid'] = $excludeid;
        }
        while ($DB->record_exists_select('local_imageblog_tags', $sql, $params)) {
            $candidate = $slug . '-' . $i++;
            $params['slug'] = $candidate;
        }
        $slug = $candidate;
    }
}
