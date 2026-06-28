<?php
// Copyright (c) Vernon Apain / Educheckout.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Vernon Apain /
// Educheckout for use with Moodle. It is NOT free software and is NOT
// released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Educheckout. The software is provided "as
// is", without warranty of any kind, express or implied.

/**
 * Lets the current user opt in or out of the blog digest email.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
