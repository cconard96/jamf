<?php

/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019-2020 by Curtis Conard
 * https://github.com/cconard96/jamf
 * -------------------------------------------------------------------------
 * LICENSE
 * This file is part of JAMF plugin for GLPI.
 * JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * JAMF plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

use Glpi\Event;
include ('../../../inc/includes.php');
Session::checkRight(PluginJamfUser_JSSAccount::$rightname, UPDATE);

global $DB;
if ($_POST['jssaccounts_id'] == 0) {
   $DB->delete(PluginJamfUser_JSSAccount::getTable(), ['users_id' => $_POST['users_id']]);
   Event::log($_POST["users_id"], "user", 2, "security",
      sprintf(_x('event', '%s remove a link to a JSS account from a user', 'jamf'), $_SESSION["glpiname"]));
   Html::back();
} else {
   $result = $DB->updateOrInsert(PluginJamfUser_JSSAccount::getTable(), [
      'users_id' => $_POST['users_id'],
      'jssaccounts_id' => $_POST['jssaccounts_id']
   ], [
      'users_id' => $_POST['users_id']
   ]);
   if ($result) {
      Event::log($_POST["users_id"], "user", 2, "security",
         sprintf(_x('event', '%s links a JSS account to a user', 'jamf'), $_SESSION["glpiname"]));
      Html::back();
   }
}

Html::displayErrorAndDie("lost");
