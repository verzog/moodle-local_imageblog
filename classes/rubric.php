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
 * Admin-defined evaluation rubric templates and their criteria.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog;

/**
 * CRUD helpers for rubric templates (the evaluate rubric an author picks) and
 * the criteria that belong to them.
 */
class rubric {
    /** @var int Smallest allowed top-of-scale value. */
    const MIN_MAXSCORE = 2;
    /** @var int Largest allowed top-of-scale value. */
    const MAX_MAXSCORE = 10;

    /**
     * All rubric templates in display order.
     *
     * @return \stdClass[]
     */
    public static function all_templates(): array {
        global $DB;
        return array_values($DB->get_records('local_imageblog_rubrics', null, 'sortorder ASC, name ASC'));
    }

    /**
     * Fetch one rubric template, or null.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_template(int $id): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_imageblog_rubrics', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Whether a rubric template exists.
     *
     * @param int $id
     * @return bool
     */
    public static function template_exists(int $id): bool {
        global $DB;
        return $id > 0 && $DB->record_exists('local_imageblog_rubrics', ['id' => $id]);
    }

    /**
     * Rubric templates as an id => name select menu.
     *
     * @return array
     */
    public static function template_menu(): array {
        $out = [];
        foreach (self::all_templates() as $r) {
            $out[(int)$r->id] = $r->name;
        }
        return $out;
    }

    /**
     * Criteria belonging to a template, in display order.
     *
     * @param int $rubricid
     * @return \stdClass[]
     */
    public static function get_criteria(int $rubricid): array {
        global $DB;
        return array_values($DB->get_records(
            'local_imageblog_rubric_crit',
            ['rubricid' => $rubricid],
            'sortorder ASC, id ASC'
        ));
    }

    /**
     * Normalise a template's top-of-scale into the allowed range.
     *
     * @param int $maxscore
     * @return int
     */
    public static function clamp_maxscore(int $maxscore): int {
        return max(self::MIN_MAXSCORE, min(self::MAX_MAXSCORE, $maxscore));
    }

    /**
     * Insert or update a rubric template (the template row only; criteria are
     * managed separately).
     *
     * @param object $data Object with name, optional description, maxscore, sortorder, id.
     * @return int Template id.
     */
    public static function save_template(object $data): int {
        global $DB;
        $now = time();
        $record = new \stdClass();
        $record->name = trim((string)($data->name ?? ''));
        if ($record->name === '') {
            throw new \moodle_exception('error_nameempty', 'local_imageblog');
        }
        $record->description = (string)($data->description ?? '');
        $record->maxscore    = self::clamp_maxscore((int)($data->maxscore ?? 5));
        $record->sortorder   = (int)($data->sortorder ?? 0);
        $record->timemodified = $now;

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_imageblog_rubrics', $record);
            return $record->id;
        }
        $record->timecreated = $now;
        return (int)$DB->insert_record('local_imageblog_rubrics', $record);
    }

    /**
     * Delete a rubric template and its criteria. Posts that referenced it keep
     * their stored rubricid but it no longer resolves, so callers must treat a
     * missing template defensively.
     *
     * @param int $id
     */
    public static function delete_template(int $id): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $critids = $DB->get_fieldset_select('local_imageblog_rubric_crit', 'id', 'rubricid = :r', ['r' => $id]);
        if ($critids) {
            [$insql, $params] = $DB->get_in_or_equal($critids, SQL_PARAMS_NAMED, 'c');
            $DB->delete_records_select('local_imageblog_eval_refs', "criterionid $insql", $params);
            $DB->delete_records_select('local_imageblog_eval_scores', "criterionid $insql", $params);
        }
        $DB->delete_records('local_imageblog_rubric_crit', ['rubricid' => $id]);
        $DB->delete_records('local_imageblog_rubrics', ['id' => $id]);
        $transaction->allow_commit();
    }

    /**
     * Insert or update a single criterion within a template.
     *
     * @param object $data Object with rubricid, name, optional description, sortorder, id.
     * @return int Criterion id.
     */
    public static function save_criterion(object $data): int {
        global $DB;
        $rubricid = (int)($data->rubricid ?? 0);
        if (!self::template_exists($rubricid)) {
            throw new \moodle_exception('error_notfound', 'local_imageblog');
        }
        $record = new \stdClass();
        $record->rubricid = $rubricid;
        $record->name = trim((string)($data->name ?? ''));
        if ($record->name === '') {
            throw new \moodle_exception('error_nameempty', 'local_imageblog');
        }
        $record->description = (string)($data->description ?? '');
        $record->sortorder   = (int)($data->sortorder ?? 0);

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $existing = $DB->get_record('local_imageblog_rubric_crit', ['id' => $record->id], 'id, rubricid', MUST_EXIST);
            // A criterion cannot be moved to a different template.
            if ((int)$existing->rubricid !== $rubricid) {
                throw new \moodle_exception('error_notfound', 'local_imageblog');
            }
            $DB->update_record('local_imageblog_rubric_crit', $record);
            return $record->id;
        }
        return (int)$DB->insert_record('local_imageblog_rubric_crit', $record);
    }

    /**
     * Delete a single criterion and any author references / reader scores for it.
     *
     * @param int $id
     */
    public static function delete_criterion(int $id): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_imageblog_eval_refs', ['criterionid' => $id]);
        $DB->delete_records('local_imageblog_eval_scores', ['criterionid' => $id]);
        $DB->delete_records('local_imageblog_rubric_crit', ['id' => $id]);
        $transaction->allow_commit();
    }
}
