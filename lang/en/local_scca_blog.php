<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 or later.

/**
 * Language strings — AU/UK English.
 *
 * @package   local_scca_blog
 * @copyright 2026 Skin Cancer College of Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'SCCA Blog';

// Navigation.
$string['blogposts']    = 'Blog posts';
$string['newpost']      = 'New post';
$string['editpost']     = 'Edit post';
$string['viewpost']     = 'View post';
$string['backtoposts']  = 'Back to posts';
$string['readmore']     = 'Read more';

// Post fields.
$string['title']            = 'Title';
$string['summary']          = 'Summary';
$string['summarydesc']      = 'A short excerpt shown on the listing card (plain text, no formatting).';
$string['body']             = 'Post content';
$string['featuredimage']    = 'Featured image';
$string['featuredimagedesc']= 'Decorative image shown on the listing card. Any format accepted — the system will automatically optimise it for display.';
$string['status']           = 'Status';
$string['status_draft']     = 'Draft';
$string['status_published'] = 'Published';
$string['status_archived']  = 'Archived';
$string['timepublished']    = 'Publish date';
$string['lazy_images']      = 'Optimise for slow connections';
$string['lazy_imagesdesc']  = 'Images in the post body will load progressively as the reader scrolls down. Recommended for posts with 3 or more images, or content aimed at regional audiences.';

// Taxonomy.
$string['categories']    = 'Categories';
$string['category']      = 'Category';
$string['subcategories'] = 'Subcategories';
$string['subcategory']   = 'Subcategory';
$string['tags']          = 'Tags';
$string['tag']           = 'Tag';
$string['levels']        = 'Levels';
$string['level']         = 'Level';
$string['selectcategory']    = 'Select category';
$string['selectsubcategory'] = 'Select a category first';
$string['selecttags']        = 'Select tags';
$string['selectauthors']     = 'Select authors';
$string['keyword']           = 'Keyword';
$string['daterange']         = 'Date range';
$string['datepublished']     = 'Date published';
$string['resetfilters']      = 'Reset';

// Meta.
$string['by']         = 'By';
$string['likes']      = '{$a} likes';
$string['comments']   = '{$a} comments';
$string['minread']    = '{$a} min read';
$string['nopostsfound'] = 'No posts found matching your filters.';

// Image processing feedback.
$string['imageoptimised']   = 'Optimised: {$a->from} → {$a->to}';
$string['imageprocessing']  = 'Processing image…';
$string['imagetiffdecode']  = 'Decoding TIFF…';

// Capabilities.
$string['scca_blog:view']           = 'View blog posts';
$string['scca_blog:createpost']     = 'Create blog posts';
$string['scca_blog:editanypost']    = 'Edit any blog post';
$string['scca_blog:deleteanypost']  = 'Delete any blog post';
$string['scca_blog:publishpost']    = 'Publish blog posts';
$string['scca_blog:managetaxonomy'] = 'Manage categories, tags and levels';

// Errors.
$string['error_notfound']      = 'Post not found.';
$string['error_nopermission']  = 'You do not have permission to perform this action.';
$string['error_invalidstatus'] = 'Invalid post status.';
