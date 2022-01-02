<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2021 by Curtis Conard
 https://github.com/cconard96/jamf
 -------------------------------------------------------------------------
 LICENSE
 This file is part of JAMF plugin for GLPI.
 JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 JAMF plugin for GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('jamf')) {
    Html::displayNotFoundError();
}

Html::header_nocache();

Session::checkLoginUser();

global $DB;

// Get AJAX input and load it into $_REQUEST
$input = file_get_contents('php://input');
parse_str($input, $_REQUEST);

// An action must be specified
if (!isset($_REQUEST['itemtype'], $_REQUEST['items_id'])) {
    throw new RuntimeException('Required argument missing!');
}

try {
    $jamf_class = PluginJamfAbstractDevice::getJamfItemClassForGLPIItem($_REQUEST['itemtype'], $_REQUEST['items_id']);
    if ($jamf_class !== null) {
        $sync_class = PluginJamfSync::getDeviceSyncEngines()[$jamf_class] ?? null;
        if ($sync_class !== null) {
            $sync_class::sync($_REQUEST['itemtype'], $_REQUEST['items_id']);
        }
    }
} catch (Exception $e) {
    Toolbox::logError($e->getMessage());
    return;
}
