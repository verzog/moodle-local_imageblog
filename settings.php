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
 * Adds an admin menu link to the blog index.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
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
        '1',
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
    $settings->add(new admin_setting_configcheckbox(
        'local_imageblog/case_cpd_enabled',
        get_string('case_cpd_enabled', 'local_imageblog'),
        get_string('case_cpd_enabled_desc', 'local_imageblog'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'local_imageblog/subs_heading',
        get_string('subs_heading', 'local_imageblog'),
        get_string('subs_heading_desc', 'local_imageblog')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_imageblog/subscriptions_enabled',
        get_string('subscriptions_enabled', 'local_imageblog'),
        get_string('subscriptions_enabled_desc', 'local_imageblog'),
        0
    ));

    $hours = [];
    for ($h = 0; $h <= 23; $h++) {
        $hours[$h] = sprintf('%02d:00', $h);
    }
    $settings->add(new admin_setting_configselect(
        'local_imageblog/digest_hour',
        get_string('digest_hour', 'local_imageblog'),
        get_string('digest_hour_desc', 'local_imageblog'),
        8,
        $hours
    ));

    $weekdays = [
        1 => get_string('monday', 'calendar'),
        2 => get_string('tuesday', 'calendar'),
        3 => get_string('wednesday', 'calendar'),
        4 => get_string('thursday', 'calendar'),
        5 => get_string('friday', 'calendar'),
        6 => get_string('saturday', 'calendar'),
        7 => get_string('sunday', 'calendar'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_imageblog/digest_weekday',
        get_string('digest_weekday', 'local_imageblog'),
        get_string('digest_weekday_desc', 'local_imageblog'),
        1,
        $weekdays
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_imageblog/rss_enabled',
        get_string('rss_enabled', 'local_imageblog'),
        get_string('rss_enabled_desc', 'local_imageblog'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'local_imageblog/appearance_heading',
        get_string('appearance_heading', 'local_imageblog'),
        get_string('appearance_heading_desc', 'local_imageblog')
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_imageblog/customcss',
        get_string('customcss', 'local_imageblog'),
        get_string('customcss_desc', 'local_imageblog'),
        '',
        PARAM_RAW
    ));

    $ADMIN->add('local_imageblog_cat', $settings);

    $ADMIN->add('local_imageblog_cat', new admin_externalpage(
        'local_imageblog',
        get_string('blogposts', 'local_imageblog'),
        new moodle_url('/local/imageblog/index.php'),
        'local/imageblog:view'
    ));

    $ADMIN->add('local_imageblog_cat', new admin_externalpage(
        'local_imageblog_authors',
        get_string('manage_authors', 'local_imageblog'),
        new moodle_url('/local/imageblog/authors.php'),
        'moodle/role:assign'
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
