<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019 by Curtis Conard
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

Html::header('Jamf Plugin', '', 'tools', 'PluginJamfMenu', 'import');

$links = [];
if (Session::haveRight('plugin_jamf_mobiledevice', CREATE)) {
   $links[] = Html::link(__('Import devices', 'jamf'), PluginJamfImport::getSearchURL());
}
if (Session::haveRight('config', UPDATE)) {
   $links[] = Html::link(__('Configure plugin', 'jamf'), Config::getFormURL()."?forcetab=PluginJamfConfig$1");
}

if (count($links)) {
   echo "<div class='center'><table class='tab_cadre'>";
   echo "<thead><th>".__('Jamf plugin', 'jamf')."</th></thead>";
   echo "<tbody>";
   foreach ($links as $link) {
      echo "<tr><td>{$link}</td></tr>";
   }
   echo "</tbody></table></div>";
} else {
   echo "<div class='center warning' style='width: 40%; margin: auto;'>";
   echo "<i class='fa fa-exclamation-triangle fa-3x'></i>";
   echo "<p>".__('You do not have access to any Jamf plugin items', 'jamf')."</p>";
   echo "</div>";
}
Html::footer();