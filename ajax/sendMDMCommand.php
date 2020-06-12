<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2020 by Curtis Conard
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

include ('../../../inc/includes.php');
Html::header_nocache();

Session::checkLoginUser();

// An action must be specified
if (!isset($_POST['command']) || !isset($_POST['jamf_id'])) {
   throw new \RuntimeException('Required argument missing!');
}

if (!is_array($_POST['jamf_id'])) {
   $_POST['jamf_id'] = [$_POST['jamf_id']];
}


$fields = [];
if (isset($_POST['fields'])) {
   parse_str($_POST['fields'], $fields);
}

$payload = new SimpleXMLElement("<mobile_device_command/>");
$general = $payload->addChild('general');
$general->addChild('command', $_POST['command']);
$fields = array_flip($fields);
array_walk_recursive($fields, [$general, 'addChild']);
$mobile_devices = $payload->addChild('mobile_devices');
foreach ($_POST['jamf_id'] as $device_id) {
   $m = $mobile_devices->addChild('mobile_device');
   $m->addChild('id', $device_id);
}

echo PluginJamfAPIClassic::addItem('mobiledevicecommands', $payload->asXML(), true);