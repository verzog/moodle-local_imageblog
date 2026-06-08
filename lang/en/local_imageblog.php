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

$string['addtagplaceholder']     = 'Type to find or add a tag…';
$string['alllevels']             = 'All levels';
$string['appearance_heading'] = 'Appearance';
$string['appearance_heading_desc'] = 'Customise how the blog looks to readers. These settings apply only to the blog listing and post pages.';
$string['author_add']                = 'Add blog author';
$string['author_add_placeholder']    = 'Search for a user…';
$string['author_added']              = 'Blog author added.';
$string['author_removed']            = 'Blog author removed.';
$string['author_role_desc']          = 'Grants permission to create, publish and edit any blog post on the Image blog. Assigned from the admin "Manage blog authors" screen.';
$string['author_role_name']          = 'Blog author';
$string['backtoposts']      = 'Back to posts';
$string['blogposts']        = 'Blog posts';
$string['body']                  = 'Post content';
$string['by']                    = 'By';
$string['case_already_diagnosed']    = 'You have already submitted a diagnosis.';
$string['case_answer_placeholder']   = 'Type your answer…';
$string['case_answer_submitted']     = 'Answer posted.';
$string['case_ask_question']         = 'Ask a question';
$string['case_cpd_awarded']          = 'You earned {$a} CPD hours from this case.';
$string['case_cpd_pending']          = 'CPD hours will be awarded when the outcome is revealed.';
$string['case_diagnoses_heading']    = 'Submitted diagnoses';
$string['case_diagnoses_hidden']     = 'Other readers\' diagnoses are hidden until the outcome is revealed.';
$string['case_diagnosis_saved']      = 'Your diagnosis has been recorded.';
$string['case_isbest']               = 'Best diagnosis';
$string['case_locked']               = 'Submissions are closed — the outcome has been revealed.';
$string['case_markbest']             = 'Mark as best diagnosis';
$string['case_no_diagnoses']         = 'No diagnoses submitted yet.';
$string['case_no_questions']         = 'No questions yet.';
$string['case_not_revealed']         = 'The author has not yet revealed the outcome.';
$string['case_outcome_heading']      = 'Author\'s outcome';
$string['case_question_placeholder'] = 'Ask the author for clarification…';
$string['case_question_submitted']   = 'Question sent to the author.';
$string['case_questions_heading']    = 'Questions';
$string['case_reveal']               = 'Reveal outcome to readers';
$string['case_reveal_confirm']       = 'Reveal the outcome to all readers? This locks further diagnosis submissions.';
$string['case_revealed']             = 'Outcome revealed.';
$string['case_submit']               = 'Submit diagnosis';
$string['case_submitdiagnosis']      = 'Submit your diagnosis';
$string['case_update']               = 'Update diagnosis';
$string['case_yourdiagnosis']        = 'Your diagnosis';
$string['case_yourreasoning']        = 'Reasoning (optional)';
$string['casedifficulty']            = 'Case difficulty (1 easy – 5 expert)';
$string['casedifficulty_help']       = 'Used to scale awarded CPD hours. Mapping is configured in the site settings.';
$string['caseoutcome']               = 'Outcome / final diagnosis';
$string['caseoutcome_help']          = 'Shown to all readers once you reveal the case. You can write it now and reveal later.';
$string['categories']            = 'Categories';
$string['category']              = 'Category';
$string['close']                 = 'Close';
$string['colour']                    = 'Colour';
$string['colour_amber']              = 'Amber';
$string['colour_coral']              = 'Coral';
$string['colour_purple']             = 'Purple';
$string['colour_teal']               = 'Teal';
$string['comments']              = '{$a} comments';
$string['cpd_basehours']             = 'Base CPD hours per case';
$string['cpd_basehours_desc']        = 'Decimal number — e.g. 1.0 means one full CPD hour at difficulty level 3.';
$string['cpd_best_bonus']            = 'Bonus: best diagnosis';
$string['cpd_best_bonus_desc']       = 'Additional share of base hours awarded to the reader whose diagnosis the author marks as best.';
$string['cpd_difficulty_scale']      = 'Difficulty multipliers';
$string['cpd_difficulty_scale_desc'] = 'Comma-separated multipliers for difficulty 1–5. Base hours are multiplied by the value matching the case\'s difficulty.';
$string['cpd_heading']               = 'CPD hours for clinical cases';
$string['cpd_heading_desc']          = 'How many CPD hours readers earn for participating in a case.';
$string['cpd_submit_factor']         = 'Factor: submitted a diagnosis';
$string['cpd_submit_factor_desc']    = 'Share of base hours awarded to readers who submitted a diagnosis before the reveal.';
$string['cpd_view_factor']           = 'Factor: viewed reveal';
$string['cpd_view_factor_desc']      = 'Share of base hours awarded to readers who view the revealed outcome without submitting a diagnosis.';
$string['customcss'] = 'Custom CSS';
$string['customcss_desc'] = 'CSS injected into the blog listing and post pages only. Use this to restyle the blog without affecting the rest of the Moodle site.';
$string['datefrom']              = 'From';
$string['datepublished']         = 'Date published';
$string['daterange']             = 'Date range';
$string['dateto']                = 'To';
$string['digest_footer']            = 'Manage your subscription: {$a}';
$string['digest_greeting']          = 'Hi {$a}, here\'s what\'s new on the blog:';
$string['digest_intro']             = 'Latest posts from {$a}:';
$string['digest_subject']           = 'New on {$a->site}: {$a->count} recent post(s)';
$string['digest_unsubscribe']       = 'Unsubscribe or change frequency';
$string['digest_viewall']           = 'View all posts on the site';
$string['editpost']         = 'Edit post';
$string['error_caseclosed']          = 'Submissions are closed for this case.';
$string['error_casenotrevealed']     = 'Outcome cannot be acted on — it has not been revealed yet.';
$string['error_emptyfield']          = 'This field cannot be empty.';
$string['error_invalidstatus']       = 'Invalid post status.';
$string['error_nameempty']           = 'Name cannot be empty.';
$string['error_nopermission']        = 'You do not have permission to perform this action.';
$string['error_notacase']            = 'This post is not a clinical case.';
$string['error_notfound']            = 'Post not found.';
$string['error_subcategoryparent']   = 'A subcategory must have a parent category.';
$string['featuredimage']         = 'Featured image';
$string['featuredimage_help']    = 'Decorative image shown on the listing card. Any common image format is accepted; it will be resized in the browser before upload.';
$string['frequency']                = 'Frequency';
$string['frequency_daily']          = 'Daily';
$string['frequency_monthly']        = 'Monthly';
$string['frequency_weekly']         = 'Weekly';
$string['haspanorama'] = 'Include a 360° panorama';
$string['haspanorama_help'] = 'Tick to add an interactive 360° panorama to the top of the post. Leave it unticked to keep the panorama uploader hidden.';
$string['imageblog:askcasequestion']     = 'Ask a question on a clinical case';
$string['imageblog:createpost']      = 'Create blog posts';
$string['imageblog:deleteanypost']   = 'Delete any blog post';
$string['imageblog:editanypost']     = 'Edit any blog post';
$string['imageblog:managetaxonomy']  = 'Manage categories, tags and levels';
$string['imageblog:publishpost']     = 'Publish blog posts';
$string['imageblog:submitdiagnosis']     = 'Submit a diagnosis on a clinical case';
$string['imageblog:view']            = 'View blog posts';
$string['imageoptimised']        = 'Optimised: {$a->from} → {$a->to}';
$string['imageprocesserror']     = 'Could not process image — uploading the original.';
$string['imageprocessing']       = 'Processing image…';
$string['keyword']               = 'Keyword';
$string['lazyimages']            = 'Optimise for slow connections';
$string['lazyimages_help']       = 'Images in the post body will load progressively as the reader scrolls. Recommended for posts with three or more images.';
$string['level']                 = 'Level';
$string['levels']                = 'Levels';
$string['likes']                 = '{$a} likes';
$string['manage_authors']            = 'Manage blog authors';
$string['manage_authors_intro']      = 'Users assigned the Blog author role can create, publish and edit any blog post. The role is granted at site level.';
$string['manage_category']           = 'Manage categories';
$string['manage_level']              = 'Manage difficulty levels';
$string['manage_subcategory']        = 'Manage subcategories';
$string['manage_tag']                = 'Manage tags';
$string['minread']               = '{$a} min read';
$string['newpost']          = 'New post';
$string['next']                  = 'Next';
$string['no_authors_yet']            = 'No blog authors have been added yet.';
$string['nopostsfound']          = 'No posts found matching your filters.';
$string['notaxonomyrows']            = 'No items defined yet.';
$string['paginationlabel']  = 'Blog pagination';
$string['panorama']              = '360° panorama image';
$string['panorama_help']         = 'Optional equirectangular (2:1) image rendered as an interactive 360° viewer at the top of the post. Drag to look around; pinch or scroll to zoom. JPEG or PNG up to 20 MB.';
$string['pluginname'] = 'Image blog';
$string['posttype']                  = 'Post type';
$string['posttype_blog']             = 'Blog post';
$string['posttype_case']             = 'Clinical case';
$string['posttype_help']             = 'A "Case" allows readers to submit a diagnosis, ask questions, and earn CPD hours once the author reveals the outcome.';
$string['previous']              = 'Previous';
$string['privacy:metadata:cpd']                  = 'CPD hours awarded to users for case participation.';
$string['privacy:metadata:cpd:hours']            = 'The number of CPD hours awarded.';
$string['privacy:metadata:cpd:reason']           = 'Why the hours were awarded (participation, best answer, view).';
$string['privacy:metadata:cpd:timeawarded']      = 'When the hours were awarded.';
$string['privacy:metadata:cpd:userid']           = 'The user receiving the CPD hours.';
$string['privacy:metadata:diags']                = 'Diagnoses submitted by users on clinical cases.';
$string['privacy:metadata:diags:diagnosis']      = 'The diagnosis text.';
$string['privacy:metadata:diags:reasoning']      = 'Optional reasoning provided with the diagnosis.';
$string['privacy:metadata:diags:timecreated']    = 'When the diagnosis was first submitted.';
$string['privacy:metadata:diags:timemodified']   = 'When the diagnosis was last updated.';
$string['privacy:metadata:diags:userid']         = 'The user who submitted the diagnosis.';
$string['privacy:metadata:files']                = 'Featured images and body images attached to posts.';
$string['privacy:metadata:posts']                = 'Information about blog posts authored by users.';
$string['privacy:metadata:posts:authorid']       = 'The ID of the user who authored the post.';
$string['privacy:metadata:posts:body']           = 'The full post body.';
$string['privacy:metadata:posts:status']         = 'The publication status (draft, published, archived).';
$string['privacy:metadata:posts:summary']        = 'The short summary shown on listing cards.';
$string['privacy:metadata:posts:timecreated']    = 'When the post was created.';
$string['privacy:metadata:posts:timemodified']   = 'When the post was last modified.';
$string['privacy:metadata:posts:timepublished']  = 'When the post was published.';
$string['privacy:metadata:posts:title']          = 'The post title.';
$string['privacy:metadata:qs']                   = 'Questions and answers posted on a clinical case.';
$string['privacy:metadata:qs:answer']            = 'The author\'s answer text.';
$string['privacy:metadata:qs:answeredby']        = 'The user who answered the question.';
$string['privacy:metadata:qs:question']          = 'The question text.';
$string['privacy:metadata:qs:timeanswered']      = 'When the question was answered.';
$string['privacy:metadata:qs:timeasked']         = 'When the question was asked.';
$string['privacy:metadata:qs:userid']            = 'The user who asked the question.';
$string['privacy:metadata:subs']                 = 'User subscription preferences for blog digest emails.';
$string['privacy:metadata:subs:frequency']       = 'The chosen digest frequency.';
$string['privacy:metadata:subs:lastsent']        = 'When the most recent digest was sent.';
$string['privacy:metadata:subs:userid']          = 'The subscribed user.';
$string['readmore']         = 'Read more';
$string['resetfilters']          = 'Reset';
$string['reverted_to_draft'] = 'Post reverted to draft.';
$string['reverttodraft']    = 'Revert to draft';
$string['searchposts']      = 'Search';
$string['selectauthors']         = 'Select author';
$string['selectcategory']        = 'Select category';
$string['selectsubcategory']     = 'Select a category first';
$string['selecttags']            = 'Select tags';
$string['settings']                  = 'Settings';
$string['slug']                      = 'Slug';
$string['slug_help']                 = 'URL-friendly identifier. Leave blank to auto-generate from the name.';
$string['sortorder']                 = 'Sort order';
$string['status']                = 'Status';
$string['status_archived']       = 'Archived';
$string['status_draft']          = 'Draft';
$string['status_mine']           = 'My posts (any status)';
$string['status_published']      = 'Published';
$string['subcategories']         = 'Subcategories';
$string['subcategory']           = 'Subcategory';
$string['subs_heading']             = 'Subscription emails';
$string['subs_heading_desc']        = 'Periodic digest of recently published posts. Users opt in from the blog index.';
$string['subscribe_disabled']       = 'Email subscriptions are not enabled on this site.';
$string['subscribe_link']           = 'Email me new posts';
$string['subscribe_optin']          = 'Email me when new posts are published';
$string['subscribe_optin_help']     = 'Each digest contains the title, summary and featured image of every post published since your last email.';
$string['subscribe_removed']        = 'You will no longer receive blog digest emails.';
$string['subscribe_saved']          = 'Your subscription preferences have been saved.';
$string['subscribe_title']          = 'Blog email subscription';
$string['subscriptions_enabled']    = 'Enable digest emails';
$string['subscriptions_enabled_desc'] = 'When enabled, a scheduled task sends each subscriber a digest of new posts at their chosen frequency.';
$string['summary']               = 'Summary';
$string['summary_help']          = 'A short excerpt shown on the listing card. Plain text only, no formatting.';
$string['tag']                   = 'Tag';
$string['tags']                  = 'Tags';
$string['tags_help']             = 'Pick from existing tags or type a new one and press Enter to create it.';
$string['task_send_digest']         = 'Send blog subscription digest emails';
$string['timepublished']         = 'Publish date';
$string['title']                 = 'Title';
$string['viewpost']         = 'View post';
