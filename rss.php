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
 * Public RSS 2.0 feed of the most recent published posts.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');

// The feed is read by user-agents that don't carry a Moodle session, so the
// site setting controls whether it's reachable at all.
if (!get_config('local_imageblog', 'rss_enabled')) {
    throw new moodle_exception('rss_disabled', 'local_imageblog');
}

$limit = max(1, min(50, (int)optional_param('limit', 20, PARAM_INT)));

$result = \local_imageblog\post::get_published([], $limit);
$posts = $result['posts'];

$sitename = format_string($SITE->fullname);
$selfurl = (new moodle_url('/local/imageblog/rss.php'))->out(false);
$blogurl = (new moodle_url('/local/imageblog/index.php'))->out(false);

header('Content-Type: application/rss+xml; charset=UTF-8');

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('rss');
$xml->writeAttribute('version', '2.0');
$xml->writeAttributeNs('xmlns', 'atom', null, 'http://www.w3.org/2005/Atom');
$xml->startElement('channel');
$xml->writeElement('title', $sitename . ' — ' . get_string('blogposts', 'local_imageblog'));
$xml->writeElement('link', $blogurl);
$xml->writeElement('description', get_string('rss_description', 'local_imageblog', $sitename));
$xml->writeElement('language', current_language());
$xml->writeElement('lastBuildDate', date('r'));
$xml->startElement('atom:link');
$xml->writeAttribute('href', $selfurl);
$xml->writeAttribute('rel', 'self');
$xml->writeAttribute('type', 'application/rss+xml');
$xml->endElement();

foreach ($posts as $post) {
    $viewurl = (new moodle_url('/local/imageblog/view.php', ['id' => $post->id]))->out(false);
    $xml->startElement('item');
    $xml->writeElement('title', format_string($post->title));
    $xml->writeElement('link', $viewurl);
    $xml->writeElement('guid', $viewurl);
    if (!empty($post->timepublished)) {
        $xml->writeElement('pubDate', date('r', (int)$post->timepublished));
    }
    if (trim((string)$post->summary) !== '') {
        $xml->writeElement('description', format_string($post->summary));
    }
    $xml->endElement();
}

$xml->endElement();
$xml->endElement();
echo $xml->outputMemory();
