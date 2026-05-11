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
 * Adds an admin menu link to the blog index.
 *
 * @package    local_imageblog
 * @copyright  2026 Skin Cancer College of Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_imageblog_cat',
        get_string('pluginname', 'local_imageblog')
    ));

    $settings = new admin_settingpage(
        'local_imageblog_settings',
        get_string('settings', 'local_imageblog')
    );

    $settings->add(new admin_setting_heading(
        'local_imageblog/cpd_heading',
        get_string('cpd_heading', 'local_imageblog'),
        get_string('cpd_heading_desc', 'local_imageblog')
    ));
    $settings->add(new admin_setting_configtext(
        'local_imageblog/cpd_basehours',
        get_string('cpd_basehours', 'local_imageblog'),
        get_string('cpd_basehours_desc', 'local_imageblog'),
        '1.0',
        PARAM_FLOAT
    ));
    $settings->add(new admin_setting_configtext(
        'local_imageblog/cpd_difficulty_scale',
        get_string('cpd_difficulty_scale', 'local_imageblog'),
        get_string('cpd_difficulty_scale_desc', 'local_imageblog'),
        '0.5,0.75,1.0,1.25,1.5',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configtext(
        'local_imageblog/cpd_view_factor',
        get_string('cpd_view_factor', 'local_imageblog'),
        get_string('cpd_view_factor_desc', 'local_imageblog'),
        '0.25',
        PARAM_FLOAT
    ));
    $settings->add(new admin_setting_configtext(
        'local_imageblog/cpd_submit_factor',
        get_string('cpd_submit_factor', 'local_imageblog'),
        get_string('cpd_submit_factor_desc', 'local_imageblog'),
        '0.75',
        PARAM_FLOAT
    ));
    $settings->add(new admin_setting_configtext(
        'local_imageblog/cpd_best_bonus',
        get_string('cpd_best_bonus', 'local_imageblog'),
        get_string('cpd_best_bonus_desc', 'local_imageblog'),
        '0.25',
        PARAM_FLOAT
    ));

    $ADMIN->add('local_imageblog_cat', $settings);

    $ADMIN->add('local_imageblog_cat', new admin_externalpage(
        'local_imageblog',
        get_string('blogposts', 'local_imageblog'),
        new moodle_url('/local/imageblog/index.php'),
        'local/imageblog:view'
    ));

    foreach (['category', 'subcategory', 'tag', 'level'] as $tax) {
        $ADMIN->add('local_imageblog_cat', new admin_externalpage(
            'local_imageblog_manage_' . $tax,
            get_string('manage_' . $tax, 'local_imageblog'),
            new moodle_url('/local/imageblog/manage.php', ['type' => $tax]),
            'local/imageblog:managetaxonomy'
        ));
    }
}
