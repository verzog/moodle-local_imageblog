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
 * Admin UI for managing the "Blog author" role assignments.
 *
 * Assigning a user to this role at system context grants them publish and
 * edit-any-post rights without making them a full site manager.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_imageblog_authors');

$context = context_system::instance();
require_capability('moodle/role:assign', $context);

$roleid = \local_imageblog\local\author_role::ensure();
$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'add') {
    require_sesskey();
    $userid = required_param('userid', PARAM_INT);
    role_assign($roleid, $userid, $context->id, 'local_imageblog', 0, 0);
    redirect(
        new moodle_url('/local/imageblog/authors.php'),
        get_string('author_added', 'local_imageblog'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'remove') {
    require_sesskey();
    $userid = required_param('userid', PARAM_INT);
    role_unassign($roleid, $userid, $context->id, 'local_imageblog');
    redirect(
        new moodle_url('/local/imageblog/authors.php'),
        get_string('author_removed', 'local_imageblog'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_url(new moodle_url('/local/imageblog/authors.php'));
$PAGE->set_title(get_string('manage_authors', 'local_imageblog'));
$PAGE->set_heading(get_string('manage_authors', 'local_imageblog'));

$form = new \local_imageblog\form\author_add_form(
    new moodle_url('/local/imageblog/authors.php'),
    ['roleid' => $roleid]
);

if ($data = $form->get_data()) {
    require_sesskey();
    $userid = (int)$data->userid;
    if ($userid) {
        role_assign($roleid, $userid, $context->id, 'local_imageblog', 0, 0);
        redirect(
            new moodle_url('/local/imageblog/authors.php'),
            get_string('author_added', 'local_imageblog'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$namefields = implode(', ', \core_user\fields::get_name_fields());
$current = $DB->get_records_sql(
    "SELECT u.id, u.email, $namefields
       FROM {role_assignments} ra
       JOIN {user} u ON u.id = ra.userid
      WHERE ra.roleid = :roleid
        AND ra.contextid = :contextid
   ORDER BY u.lastname, u.firstname",
    ['roleid' => $roleid, 'contextid' => $context->id]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_authors', 'local_imageblog'));
echo html_writer::tag('p', get_string('manage_authors_intro', 'local_imageblog'));

$form->display();

if ($current) {
    $table = new html_table();
    $table->head = [
        get_string('fullname'),
        get_string('email'),
        '',
    ];
    foreach ($current as $u) {
        $removeurl = new moodle_url('/local/imageblog/authors.php', [
            'action'  => 'remove',
            'userid'  => $u->id,
            'sesskey' => sesskey(),
        ]);
        $removebtn = $OUTPUT->single_button(
            $removeurl,
            get_string('remove'),
            'post',
            ['class' => 'btn btn-sm btn-outline-danger']
        );
        $table->data[] = [
            fullname($u),
            s($u->email),
            $removebtn,
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(
        get_string('no_authors_yet', 'local_imageblog'),
        \core\output\notification::NOTIFY_INFO
    );
}

echo $OUTPUT->footer();
