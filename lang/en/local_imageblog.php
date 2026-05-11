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
 * Language strings.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Image blog';

// Navigation.
$string['blogposts']        = 'Blog posts';
$string['newpost']          = 'New post';
$string['editpost']         = 'Edit post';
$string['viewpost']         = 'View post';
$string['backtoposts']      = 'Back to posts';
$string['reverttodraft']    = 'Revert to draft';
$string['reverted_to_draft'] = 'Post reverted to draft.';
$string['readmore']         = 'Read more';
$string['searchposts']      = 'Search';
$string['paginationlabel']  = 'Blog pagination';

// Post fields.
$string['title']                 = 'Title';
$string['summary']               = 'Summary';
$string['summary_help']          = 'A short excerpt shown on the listing card. Plain text only, no formatting.';
$string['body']                  = 'Post content';
$string['featuredimage']         = 'Featured image';
$string['featuredimage_help']    = 'Decorative image shown on the listing card. Any common image format is accepted; it will be resized in the browser before upload.';
$string['panorama']              = '360° panorama image';
$string['panorama_help']         = 'Optional equirectangular (2:1) image rendered as an interactive 360° viewer at the top of the post. Drag to look around; pinch or scroll to zoom. JPEG or PNG up to 20 MB.';
$string['status']                = 'Status';
$string['status_draft']          = 'Draft';
$string['status_published']      = 'Published';
$string['status_archived']       = 'Archived';
$string['status_mine']           = 'My posts (any status)';
$string['timepublished']         = 'Publish date';
$string['lazyimages']            = 'Optimise for slow connections';
$string['lazyimages_help']       = 'Images in the post body will load progressively as the reader scrolls. Recommended for posts with three or more images.';

// Taxonomy.
$string['categories']            = 'Categories';
$string['category']              = 'Category';
$string['subcategories']         = 'Subcategories';
$string['subcategory']           = 'Subcategory';
$string['tags']                  = 'Tags';
$string['tags_help']             = 'Pick from existing tags or type a new one and press Enter to create it.';
$string['tag']                   = 'Tag';
$string['addtagplaceholder']     = 'Type to find or add a tag…';
$string['levels']                = 'Levels';
$string['level']                 = 'Level';
$string['selectcategory']        = 'Select category';
$string['selectsubcategory']     = 'Select a category first';
$string['selecttags']            = 'Select tags';
$string['selectauthors']         = 'Select author';
$string['keyword']               = 'Keyword';
$string['daterange']             = 'Date range';
$string['datefrom']              = 'From';
$string['dateto']                = 'To';
$string['alllevels']             = 'All levels';
$string['datepublished']         = 'Date published';
$string['resetfilters']          = 'Reset';

// Meta.
$string['by']                    = 'By';
$string['likes']                 = '{$a} likes';
$string['comments']              = '{$a} comments';
$string['minread']               = '{$a} min read';
$string['nopostsfound']          = 'No posts found matching your filters.';
$string['close']                 = 'Close';
$string['previous']              = 'Previous';
$string['next']                  = 'Next';

// Image processing feedback.
$string['imageoptimised']        = 'Optimised: {$a->from} → {$a->to}';
$string['imageprocessing']       = 'Processing image…';
$string['imageprocesserror']     = 'Could not process image — uploading the original.';

// Capabilities.
$string['imageblog:view']            = 'View blog posts';
$string['imageblog:createpost']      = 'Create blog posts';
$string['imageblog:editanypost']     = 'Edit any blog post';
$string['imageblog:deleteanypost']   = 'Delete any blog post';
$string['imageblog:publishpost']     = 'Publish blog posts';
$string['imageblog:managetaxonomy']  = 'Manage categories, tags and levels';

// Errors.
$string['error_notfound']            = 'Post not found.';
$string['error_nopermission']        = 'You do not have permission to perform this action.';
$string['error_invalidstatus']       = 'Invalid post status.';
$string['error_nameempty']           = 'Name cannot be empty.';
$string['error_subcategoryparent']   = 'A subcategory must have a parent category.';

// Taxonomy management.
$string['manage_category']           = 'Manage categories';
$string['manage_subcategory']        = 'Manage subcategories';
$string['manage_tag']                = 'Manage tags';
$string['manage_level']              = 'Manage difficulty levels';
$string['notaxonomyrows']            = 'No items defined yet.';
$string['slug']                      = 'Slug';
$string['slug_help']                 = 'URL-friendly identifier. Leave blank to auto-generate from the name.';
$string['sortorder']                 = 'Sort order';
$string['colour']                    = 'Colour';
$string['colour_amber']              = 'Amber';
$string['colour_teal']               = 'Teal';
$string['colour_coral']              = 'Coral';
$string['colour_purple']             = 'Purple';

// Privacy.
$string['privacy:metadata:posts']                = 'Information about blog posts authored by users.';
$string['privacy:metadata:posts:authorid']       = 'The ID of the user who authored the post.';
$string['privacy:metadata:posts:title']          = 'The post title.';
$string['privacy:metadata:posts:summary']        = 'The short summary shown on listing cards.';
$string['privacy:metadata:posts:body']           = 'The full post body.';
$string['privacy:metadata:posts:status']         = 'The publication status (draft, published, archived).';
$string['privacy:metadata:posts:timecreated']    = 'When the post was created.';
$string['privacy:metadata:posts:timemodified']   = 'When the post was last modified.';
$string['privacy:metadata:posts:timepublished']  = 'When the post was published.';
$string['privacy:metadata:files']                = 'Featured images and body images attached to posts.';

// Cases / CPD.
$string['settings']                  = 'Settings';
$string['posttype']                  = 'Post type';
$string['posttype_help']             = 'A "Case" allows readers to submit a diagnosis, ask questions, and earn CPD hours once the author reveals the outcome.';
$string['posttype_blog']             = 'Blog post';
$string['posttype_case']             = 'Clinical case';
$string['caseoutcome']               = 'Outcome / final diagnosis';
$string['caseoutcome_help']          = 'Shown to all readers once you reveal the case. You can write it now and reveal later.';
$string['casedifficulty']            = 'Case difficulty (1 easy – 5 expert)';
$string['casedifficulty_help']       = 'Used to scale awarded CPD hours. Mapping is configured in the site settings.';
$string['case_submitdiagnosis']      = 'Submit your diagnosis';
$string['case_yourdiagnosis']        = 'Your diagnosis';
$string['case_yourreasoning']        = 'Reasoning (optional)';
$string['case_submit']               = 'Submit diagnosis';
$string['case_update']               = 'Update diagnosis';
$string['case_diagnosis_saved']      = 'Your diagnosis has been recorded.';
$string['case_already_diagnosed']    = 'You have already submitted a diagnosis.';
$string['case_not_revealed']         = 'The author has not yet revealed the outcome.';
$string['case_outcome_heading']      = 'Author\'s outcome';
$string['case_reveal']               = 'Reveal outcome to readers';
$string['case_reveal_confirm']       = 'Reveal the outcome to all readers? This locks further diagnosis submissions.';
$string['case_revealed']             = 'Outcome revealed.';
$string['case_locked']               = 'Submissions are closed — the outcome has been revealed.';
$string['case_questions_heading']    = 'Questions';
$string['case_ask_question']         = 'Ask a question';
$string['case_question_placeholder'] = 'Ask the author for clarification…';
$string['case_question_submitted']   = 'Question sent to the author.';
$string['case_answer_placeholder']   = 'Type your answer…';
$string['case_answer_submitted']     = 'Answer posted.';
$string['case_diagnoses_heading']    = 'Submitted diagnoses';
$string['case_diagnoses_hidden']     = 'Other readers\' diagnoses are hidden until the outcome is revealed.';
$string['case_no_diagnoses']         = 'No diagnoses submitted yet.';
$string['case_no_questions']         = 'No questions yet.';
$string['case_markbest']             = 'Mark as best diagnosis';
$string['case_isbest']               = 'Best diagnosis';
$string['case_cpd_awarded']          = 'You earned {$a} CPD hours from this case.';
$string['case_cpd_pending']          = 'CPD hours will be awarded when the outcome is revealed.';
$string['imageblog:submitdiagnosis']     = 'Submit a diagnosis on a clinical case';
$string['imageblog:askcasequestion']     = 'Ask a question on a clinical case';

// CPD admin settings.
$string['cpd_heading']               = 'CPD hours for clinical cases';
$string['cpd_heading_desc']          = 'How many CPD hours readers earn for participating in a case.';
$string['cpd_basehours']             = 'Base CPD hours per case';
$string['cpd_basehours_desc']        = 'Decimal number — e.g. 1.0 means one full CPD hour at difficulty level 3.';
$string['cpd_difficulty_scale']      = 'Difficulty multipliers';
$string['cpd_difficulty_scale_desc'] = 'Comma-separated multipliers for difficulty 1–5. Base hours are multiplied by the value matching the case\'s difficulty.';
$string['cpd_view_factor']           = 'Factor: viewed reveal';
$string['cpd_view_factor_desc']      = 'Share of base hours awarded to readers who view the revealed outcome without submitting a diagnosis.';
$string['cpd_submit_factor']         = 'Factor: submitted a diagnosis';
$string['cpd_submit_factor_desc']    = 'Share of base hours awarded to readers who submitted a diagnosis before the reveal.';
$string['cpd_best_bonus']            = 'Bonus: best diagnosis';
$string['cpd_best_bonus_desc']       = 'Additional share of base hours awarded to the reader whose diagnosis the author marks as best.';

$string['error_casenotrevealed']     = 'Outcome cannot be acted on — it has not been revealed yet.';
$string['error_caseclosed']          = 'Submissions are closed for this case.';
$string['error_notacase']            = 'This post is not a clinical case.';
