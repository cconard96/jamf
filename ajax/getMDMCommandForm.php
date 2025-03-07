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

include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('jamf')) {
    Html::displayNotFoundError();
}

Session::checkLoginUser();

// An action must be specified
if (!isset($_GET['command'])) {
    throw new RuntimeException('Required argument missing!');
}

if (isset($_GET['itemtype'], $_GET['items_id'])) {
    $device = new ('PluginJamf' . $_GET['itemtype'])();
    if (!$device->getFromDB($_GET['items_id'])) {
        throw new RuntimeException('Invalid itemtype/items_id!');
    }
} else {
    $device = null;
}
$form = PluginJamfMDMCommand::getFormForCommand($_GET['command'], $device);
echo $form;
