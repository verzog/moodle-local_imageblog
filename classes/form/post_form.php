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
 * Post create/edit form.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\form;

use moodleform;
use local_imageblog\post;
use local_imageblog\post_type;
use local_imageblog\rubric;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing blog posts.
 */
class post_form extends moodleform {
    /**
     * Define form elements.
     */
    public function definition(): void {
        $mform   = $this->_form;
        $post    = $this->_customdata['post'] ?? null;
        $context = $this->_customdata['context'];

        $mform->addElement('hidden', 'id', $post?->id ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'posttype', get_string('posttype', 'local_imageblog'), post_type::menu());
        $mform->setType('posttype', PARAM_ALPHA);
        $mform->addHelpButton('posttype', 'posttype', 'local_imageblog');

        // Assessment mode applies to the interactive types only; it is hidden
        // for read-only types (Blog Post, News, Reference Article). An
        // interactive post is always assessable (CPD) or practice — never
        // "not assessed" — so only those options are offered here.
        $mform->addElement(
            'select',
            'assessmentmode',
            get_string('assessmentmode', 'local_imageblog'),
            post_type::interactive_mode_menu()
        );
        $mform->setType('assessmentmode', PARAM_ALPHA);
        $mform->setDefault('assessmentmode', post_type::default_interactive_mode());
        $mform->addHelpButton('assessmentmode', 'assessmentmode', 'local_imageblog');
        // Moodle ORs repeated hideIf() rules on one element: the element is
        // hidden when ANY rule matches. So to show the selector only for the
        // interactive types, hide it on an exact match of each read-only type
        // (Blog Post, News, Reference Article).
        $interactivetypes = array_merge(post_type::diagnose_types(), post_type::evaluate_types());
        $readonlytypes = array_diff(post_type::all(), $interactivetypes);
        foreach ($readonlytypes as $rtype) {
            $mform->hideIf('assessmentmode', 'posttype', 'eq', $rtype);
        }

        $mform->addElement('text', 'title', get_string('title', 'local_imageblog'), ['size' => 80]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'summary',
            get_string('summary', 'local_imageblog'),
            ['rows' => 3, 'cols' => 80]
        );
        $mform->setType('summary', PARAM_TEXT);
        $mform->addHelpButton('summary', 'summary', 'local_imageblog');

        $mform->addElement(
            'editor',
            'body_editor',
            get_string('body', 'local_imageblog'),
            null,
            post::editor_options($context)
        );
        $mform->setType('body_editor', PARAM_RAW);
        $mform->addRule('body_editor', null, 'required', null, 'client');

        $mform->addElement(
            'filemanager',
            'featured_image',
            get_string('featuredimage', 'local_imageblog'),
            null,
            post::featured_options()
        );
        $mform->addHelpButton('featured_image', 'featuredimage', 'local_imageblog');

        $mform->addElement(
            'advcheckbox',
            'haspanorama',
            get_string('haspanorama', 'local_imageblog')
        );
        $mform->setType('haspanorama', PARAM_BOOL);
        $mform->addHelpButton('haspanorama', 'haspanorama', 'local_imageblog');

        $mform->addElement(
            'filemanager',
            'panorama_image',
            get_string('panorama', 'local_imageblog'),
            null,
            post::panorama_options()
        );
        $mform->addHelpButton('panorama_image', 'panorama', 'local_imageblog');
        $mform->hideIf('panorama_image', 'haspanorama', 'notchecked');

        $mform->addElement(
            'advcheckbox',
            'lazyimages',
            get_string('lazyimages', 'local_imageblog')
        );
        $mform->setDefault('lazyimages', 1);
        $mform->addHelpButton('lazyimages', 'lazyimages', 'local_imageblog');

        global $CFG;
        require_once($CFG->dirroot . '/local/imageblog/lib.php');
        $taxonomy = local_imageblog_get_taxonomy();

        $tagoptions = [];
        foreach ($taxonomy['tags'] as $tag) {
            $tagoptions[$tag['id']] = $tag['name'];
        }
        $mform->addElement(
            'autocomplete',
            'tagids',
            get_string('tags', 'local_imageblog'),
            $tagoptions,
            [
                'multiple' => true,
                'tags'     => true,
                'noselectionstring' => get_string('selecttags', 'local_imageblog'),
                'placeholder'       => get_string('addtagplaceholder', 'local_imageblog'),
            ]
        );
        $mform->setType('tagids', PARAM_RAW);
        $mform->addHelpButton('tagids', 'tags', 'local_imageblog');

        $leveloptions = [];
        foreach ($taxonomy['levels'] as $level) {
            $leveloptions[$level['id']] = $level['name'];
        }
        $mform->addElement(
            'select',
            'levelids',
            get_string('levels', 'local_imageblog'),
            $leveloptions,
            ['multiple' => 'multiple', 'size' => 4]
        );
        $mform->setType('levelids', PARAM_INT);

        $diffoptions = [];
        for ($i = 1; $i <= 5; $i++) {
            $diffoptions[$i] = (string)$i;
        }
        $mform->addElement(
            'select',
            'casedifficulty',
            get_string('casedifficulty', 'local_imageblog'),
            $diffoptions
        );
        $mform->setType('casedifficulty', PARAM_INT);
        $mform->setDefault('casedifficulty', 3);
        $mform->addHelpButton('casedifficulty', 'casedifficulty', 'local_imageblog');

        $mform->addElement(
            'editor',
            'caseoutcome_editor',
            get_string('caseoutcome', 'local_imageblog'),
            null,
            post::editor_options($context)
        );
        $mform->setType('caseoutcome_editor', PARAM_RAW);
        $mform->addHelpButton('caseoutcome_editor', 'caseoutcome', 'local_imageblog');

        // Optional author-set auto-reveal time. Left empty, the site default
        // reveal window applies from the publish date.
        $mform->addElement(
            'date_time_selector',
            'caserevealat',
            get_string('caserevealat', 'local_imageblog'),
            ['optional' => true]
        );
        $mform->addHelpButton('caserevealat', 'caserevealat', 'local_imageblog');

        // Rubric template chooser for the evaluate types. The author sets the
        // reference ("model") ratings for its criteria on the post page after
        // saving. Hidden when no templates exist (the element is still added so
        // hideIf has a target, but with only the placeholder option).
        $rubricoptions = [0 => get_string('rubric_choose', 'local_imageblog')] + rubric::template_menu();
        $mform->addElement(
            'select',
            'rubricid',
            get_string('rubric', 'local_imageblog'),
            $rubricoptions
        );
        $mform->setType('rubricid', PARAM_INT);
        $mform->setDefault('rubricid', 0);
        $mform->addHelpButton('rubricid', 'rubric', 'local_imageblog');

        // Moodle ORs repeated hideIf() rules (an element is hidden when ANY
        // rule matches), so each field is hidden on an exact match of every
        // type it does NOT belong to. Difficulty and reveal time belong to
        // every interactive type (diagnose + evaluate); the outcome editor to
        // the diagnose types only; the rubric chooser to the evaluate types only.
        $all = post_type::all();
        $noninteractive = array_diff($all, post_type::diagnose_types(), post_type::evaluate_types());
        $nondiagnose = array_diff($all, post_type::diagnose_types());
        $nonevaluate = array_diff($all, post_type::evaluate_types());
        $hide = function (string $element, array $types) use ($mform) {
            foreach ($types as $type) {
                $mform->hideIf($element, 'posttype', 'eq', $type);
            }
        };
        $hide('casedifficulty', $noninteractive);
        $hide('caserevealat', $noninteractive);
        $hide('caseoutcome_editor', $nondiagnose);
        $hide('rubricid', $nonevaluate);

        $statusoptions = [
            post::STATUS_DRAFT     => get_string('status_draft', 'local_imageblog'),
            post::STATUS_SCHEDULED => get_string('status_scheduled', 'local_imageblog'),
            post::STATUS_PUBLISHED => get_string('status_published', 'local_imageblog'),
            post::STATUS_ARCHIVED  => get_string('status_archived', 'local_imageblog'),
        ];
        $mform->addElement(
            'select',
            'status',
            get_string('status', 'local_imageblog'),
            $statusoptions
        );
        $mform->setDefault('status', post::STATUS_DRAFT);

        $mform->addElement(
            'date_time_selector',
            'timescheduled',
            get_string('timescheduled', 'local_imageblog'),
            ['optional' => false]
        );
        $mform->addHelpButton('timescheduled', 'timescheduled', 'local_imageblog');
        $mform->hideIf('timescheduled', 'status', 'neq', post::STATUS_SCHEDULED);

        $this->add_action_buttons();

        // Populate existing data, including draft areas for editor + filemanager.
        $defaults = new \stdClass();
        if ($post) {
            $defaults->id         = $post->id;
            $defaults->title      = $post->title;
            $defaults->summary    = $post->summary;
            $defaults->lazyimages = (int)$post->lazyimages;
            $defaults->status     = $post->status;
            $defaults->body       = $post->body;
            $defaults->bodyformat = $post->bodyformat;
            $defaults->tagids        = $post->get_tag_ids();
            $defaults->levelids      = $post->get_level_ids();
            $defaults->posttype          = $post->posttype;
            $defaults->assessmentmode    = $post->assessmentmode;
            $defaults->casedifficulty    = $post->casedifficulty;
            $defaults->caseoutcome       = $post->caseoutcome;
            $defaults->caseoutcomeformat = $post->caseoutcomeformat;
            $defaults->caserevealat      = $post->caserevealat ?? 0;
            $defaults->rubricid          = $post->rubricid ?? 0;
            $defaults->haspanorama       = $post->get_panorama_url() ? 1 : 0;
            $defaults->timescheduled     = $post->timescheduled ?? 0;
        } else {
            $defaults->id         = 0;
            $defaults->body       = '';
            $defaults->bodyformat = FORMAT_HTML;
            $defaults->posttype          = post_type::BLOG;
            $defaults->assessmentmode    = post_type::default_interactive_mode();
            $defaults->casedifficulty    = 3;
            $defaults->caseoutcome       = '';
            $defaults->caseoutcomeformat = FORMAT_HTML;
            $defaults->caserevealat      = 0;
            $defaults->rubricid          = 0;
            $defaults->haspanorama       = 0;
            // Default the schedule picker to 09:00 tomorrow using calendar
            // arithmetic in the user's timezone — adding DAYSECS would land an
            // hour off across a Sydney DST changeover (CLAUDE.md §6.5).
            $today = usergetdate(time());
            $defaults->timescheduled = make_timestamp(
                $today['year'],
                $today['mon'],
                $today['mday'] + 1,
                9,
                0,
                0
            );
        }

        $defaults = file_prepare_standard_editor(
            $defaults,
            'body',
            post::editor_options($context),
            $context,
            'local_imageblog',
            post::FILEAREA_BODY,
            $defaults->id ?: null
        );

        $defaults = file_prepare_standard_editor(
            $defaults,
            'caseoutcome',
            post::editor_options($context),
            $context,
            'local_imageblog',
            post::FILEAREA_CASEOUTCOME,
            $defaults->id ?: null
        );

        $draftitemid = file_get_submitted_draft_itemid('featured_image');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_imageblog',
            post::FILEAREA_FEATURED,
            $defaults->id ?: null,
            post::featured_options()
        );
        $defaults->featured_image = $draftitemid;

        $panodraftid = file_get_submitted_draft_itemid('panorama_image');
        file_prepare_draft_area(
            $panodraftid,
            $context->id,
            'local_imageblog',
            post::FILEAREA_PANORAMA,
            $defaults->id ?: null,
            post::panorama_options()
        );
        $defaults->panorama_image = $panodraftid;

        $this->set_data($defaults);
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['title']))) {
            $errors['title'] = get_string('required');
        }

        $validstatuses = [
            post::STATUS_DRAFT,
            post::STATUS_SCHEDULED,
            post::STATUS_PUBLISHED,
            post::STATUS_ARCHIVED,
        ];
        if (!in_array($data['status'], $validstatuses, true)) {
            $errors['status'] = get_string('error_invalidstatus', 'local_imageblog');
        }

        if (
            $data['status'] === post::STATUS_SCHEDULED
            && (empty($data['timescheduled']) || (int)$data['timescheduled'] <= time())
        ) {
            $errors['timescheduled'] = get_string('error_schedulepast', 'local_imageblog');
        }

        // An evaluate post needs a rubric to be evaluated against.
        if (
            !empty($data['posttype'])
            && post_type::is_evaluate((string)$data['posttype'])
            && empty($data['rubricid'])
        ) {
            $errors['rubricid'] = get_string('error_rubricrequired', 'local_imageblog');
        }

        return $errors;
    }
}
