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
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_imageblog\form;

use moodleform;
use local_imageblog\post;

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
            'filemanager',
            'panorama_image',
            get_string('panorama', 'local_imageblog'),
            null,
            post::panorama_options()
        );
        $mform->addHelpButton('panorama_image', 'panorama', 'local_imageblog');

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

        $catoptions = ['' => get_string('selectcategory', 'local_imageblog')];
        foreach ($taxonomy['categories'] as $cat) {
            $catoptions[$cat['id']] = $cat['name'];
        }
        $mform->addElement(
            'select',
            'categoryid',
            get_string('category', 'local_imageblog'),
            $catoptions
        );
        $mform->setType('categoryid', PARAM_INT);

        $subcatoptions = ['' => get_string('selectsubcategory', 'local_imageblog')];
        foreach ($taxonomy['subcategories'] as $sub) {
            $parent = $sub['categoryid'];
            $parentname = '';
            foreach ($taxonomy['categories'] as $cat) {
                if ($cat['id'] === $parent) {
                    $parentname = $cat['name'];
                    break;
                }
            }
            $label = $parentname !== '' ? "{$parentname} / {$sub['name']}" : $sub['name'];
            $subcatoptions[$sub['id']] = $label;
        }
        $mform->addElement(
            'select',
            'subcategoryid',
            get_string('subcategory', 'local_imageblog'),
            $subcatoptions
        );
        $mform->setType('subcategoryid', PARAM_INT);

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

        $statusoptions = [
            post::STATUS_DRAFT     => get_string('status_draft', 'local_imageblog'),
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
            [$catid, $subcatid] = $post->get_category_ids();
            $defaults->categoryid    = $catid;
            $defaults->subcategoryid = $subcatid;
            $defaults->tagids        = $post->get_tag_ids();
            $defaults->levelids      = $post->get_level_ids();
        } else {
            $defaults->id         = 0;
            $defaults->body       = '';
            $defaults->bodyformat = FORMAT_HTML;
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
            post::STATUS_PUBLISHED,
            post::STATUS_ARCHIVED,
        ];
        if (!in_array($data['status'], $validstatuses, true)) {
            $errors['status'] = get_string('error_invalidstatus', 'local_imageblog');
        }

        return $errors;
    }
}
