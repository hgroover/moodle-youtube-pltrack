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
 * Defines the version and other meta-info about the plugin
 *
 * Setting the $plugin->version to 0 prevents the plugin from being installed.
 * See https://docs.moodle.org/dev/version.php for more info.
 *
 * @package   mod_ytpltrack
 * @copyright 2017 Henry Groover <henry.groover@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */
 
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2017090803;
$plugin->requires  = 2014111000; // Moodle 2.8 - tested with 3.1 (2016052300)
$plugin->cron      = 0;
$plugin->component = 'mod_ytpltrack';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '3.0';
 
$plugin->dependencies = array(
    //'mod_forum' => ANY_VERSION,
    //'mod_data'  => TODO
);
