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
 * Admin UI for managing the "Blog author" role assignments.
 *
 * Assigning a user to this role at system context grants them publish and
 * edit-any-post rights without making them a full site manager.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
