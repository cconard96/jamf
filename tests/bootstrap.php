<?php

/**
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of JAMF plugin for GLPI.
 *
 * JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * JAMF plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024-2024 by Teclib'
 * @copyright Copyright (C) 2019-2024 by Curtis Conard
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/jamf
 * -------------------------------------------------------------------------
 */

global $CFG_GLPI;

define('GLPI_ROOT', dirname(dirname(dirname(__DIR__))));

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}
include GLPI_ROOT . '/inc/includes.php';
//include_once GLPI_ROOT . '/tests/GLPITestCase.php';
//include_once GLPI_ROOT . '/tests/DbTestCase.php';
include_once 'AbstractDBTest.php';

$plugin = new Plugin();
$plugin->checkPluginState('jamf');
$plugin->getFromDBbyDir('jamf');

if (!plugin_jamf_check_prerequisites()) {
    echo "\nPrerequisites are not met!";
    die(1);
}

if (!$plugin->isInstalled('jamf')) {
    $plugin->install($plugin->getID());
}
if (!$plugin->isActivated('jamf')) {
    $plugin->activate($plugin->getID());
}

include_once 'apitest.class.php';
include_once 'connectiontest.class.php';
include_once 'mobiletestsync.class.php';
include_once 'computertestsync.class.php';
