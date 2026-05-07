<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Post create/edit form.
 *
 * @package   local_scca_blog
 * @copyright 2026 Skin Cancer College of Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scca_blog\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Form for creating and editing blog posts.
 */
class post_form extends moodleform {

    /**
     * Define form elements.
     */
    public function definition(): void {
        global $CFG;

        $mform  = $this->_form;
        $post   = $this->_customdata['post'] ?? null;
        $context = $this->_customdata['context'];

        // Hidden post id.
        $mform->addElement('hidden', 'id', $post?->id ?? 0);
        $mform->setType('id', PARAM_INT);

        // ── Title ──────────────────────────────────────────────────────────
        $mform->addElement('text', 'title', get_string('title', 'local_scca_blog'), ['size' => 80]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        // ── Summary (plain text for card) ──────────────────────────────────
        $mform->addElement('textarea', 'summary', get_string('summary', 'local_scca_blog'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('summary', PARAM_TEXT);
        $mform->addHelpButton('summary', 'summary', 'local_scca_blog');

        // ── Body (TinyMCE 6) ───────────────────────────────────────────────
        $editoroptions = [
            'maxfiles'       => 10,
            'maxbytes'       => 20971520, // 20MB
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.tiff', '.webp'],
            'context'        => $context,
            'noclean'        => false,
        ];
        $mform->addElement('editor', 'body_editor', get_string('body', 'local_scca_blog'), null, $editoroptions);
        $mform->setType('body_editor', PARAM_RAW);

        // ── Featured image ─────────────────────────────────────────────────
        $mform->addElement('filemanager', 'featured_image', get_string('featuredimage', 'local_scca_blog'), null, [
            'maxbytes'       => 2097152, // 2MB hard backstop — JS will deliver ~150kb
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp'],
            'maxfiles'       => 1,
            'subdirs'        => 0,
        ]);
        $mform->addHelpButton('featured_image', 'featuredimage', 'local_scca_blog');

        // ── Lazy images toggle ─────────────────────────────────────────────
        $mform->addElement('advcheckbox', 'lazy_images', get_string('lazy_images', 'local_scca_blog'));
        $mform->setDefault('lazy_images', 1);
        $mform->addHelpButton('lazy_images', 'lazy_images', 'local_scca_blog');

        // ── Status ─────────────────────────────────────────────────────────
        $statusoptions = [
            \local_scca_blog\post::STATUS_DRAFT     => get_string('status_draft',     'local_scca_blog'),
            \local_scca_blog\post::STATUS_PUBLISHED => get_string('status_published', 'local_scca_blog'),
            \local_scca_blog\post::STATUS_ARCHIVED  => get_string('status_archived',  'local_scca_blog'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'local_scca_blog'), $statusoptions);
        $mform->setDefault('status', \local_scca_blog\post::STATUS_DRAFT);

        // ── Buttons ────────────────────────────────────────────────────────
        $this->add_action_buttons();

        // ── Populate existing data ─────────────────────────────────────────
        if ($post) {
            $data = (object)[
                'id'          => $post->id,
                'title'       => $post->title,
                'summary'     => $post->summary,
                'body_editor' => ['text' => $post->body, 'format' => $post->bodyformat],
                'lazy_images' => (int)$post->lazy_images,
                'status'      => $post->status,
            ];
            $this->set_data($data);
        }
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['title']))) {
            $errors['title'] = get_string('required');
        }

        $validstatuses = [
            \local_scca_blog\post::STATUS_DRAFT,
            \local_scca_blog\post::STATUS_PUBLISHED,
            \local_scca_blog\post::STATUS_ARCHIVED,
        ];
        if (!in_array($data['status'], $validstatuses)) {
            $errors['status'] = get_string('error_invalidstatus', 'local_scca_blog');
        }

        return $errors;
    }
}
