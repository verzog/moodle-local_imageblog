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
 * Lets the current user opt in or out of the blog digest email.
 *
 * @package    local_imageblog
 * @copyright  2026 Vernon Apain / Educheckout
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/imageblog/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/imageblog:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/imageblog/subscribe.php'));
$PAGE->set_title(get_string('subscribe_title', 'local_imageblog'));
$PAGE->set_heading(get_string('subscribe_title', 'local_imageblog'));
$PAGE->set_pagelayout('standard');

if (!get_config('local_imageblog', 'subscriptions_enabled')) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('subscribe_disabled', 'local_imageblog'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo $OUTPUT->footer();
    exit;
}

$existing = \local_imageblog\subscription::get_for_user((int)$USER->id);

$form = new \local_imageblog\form\subscription_form(
    new moodle_url('/local/imageblog/subscribe.php')
);
$form->set_data((object)[
    'frequency' => $existing ? $existing->frequency : 'none',
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/imageblog/index.php'));
}

if ($data = $form->get_data()) {
    $frequency = (string)$data->frequency;
    if ($frequency === 'none') {
        \local_imageblog\subscription::unsubscribe((int)$USER->id);
        $msg = get_string('subscribe_removed', 'local_imageblog');
    } else {
        \local_imageblog\subscription::subscribe((int)$USER->id, $frequency);
        $msg = get_string('subscribe_saved', 'local_imageblog');
    }
    redirect(
        new moodle_url('/local/imageblog/subscribe.php'),
        $msg,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
