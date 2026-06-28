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
 * Plugin version information.
 *
 * @package   local_imageblog
 * @copyright © Vernon Apain / Educheckout
 * @license   Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_imageblog';
$plugin->version   = 2026062800;
$plugin->requires  = 2025041100; // Moodle 5.0.
$plugin->supported = [500, 502]; // Moodle 5.0 to 5.2 inclusive.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.4.0';
