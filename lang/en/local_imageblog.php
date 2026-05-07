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
$string['status']                = 'Status';
$string['status_draft']          = 'Draft';
$string['status_published']      = 'Published';
$string['status_archived']       = 'Archived';
$string['timepublished']         = 'Publish date';
$string['lazyimages']            = 'Optimise for slow connections';
$string['lazyimages_help']       = 'Images in the post body will load progressively as the reader scrolls. Recommended for posts with three or more images.';

// Taxonomy.
$string['categories']            = 'Categories';
$string['category']              = 'Category';
$string['subcategories']         = 'Subcategories';
$string['subcategory']           = 'Subcategory';
$string['tags']                  = 'Tags';
$string['tag']                   = 'Tag';
$string['levels']                = 'Levels';
$string['level']                 = 'Level';
$string['selectcategory']        = 'Select category';
$string['selectsubcategory']     = 'Select a category first';
$string['selecttags']            = 'Select tags';
$string['selectauthors']         = 'Select author';
$string['keyword']               = 'Keyword';
$string['daterange']             = 'Date range';
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
