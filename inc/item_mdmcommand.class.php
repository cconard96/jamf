<?php

/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019 by Curtis Conard
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

/**
 * JSS Item_MDMCommand class
 *
 * @since 1.1.0
 */
class PluginJamfItem_MDMCommand extends CommonDBTM {

   static public $rightname = 'plugin_jamf_mdmcommand';

   public static function getTypeName($nb = 0)
   {
      return _n('MDM command', 'MDM commands', $nb, 'jamf');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
   {
      if (!PluginJamfMobileDevice::canView()) {
         return false;
      }
      return self::getTypeName(2);
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
   {
      return self::showForItem($item);
   }

   private static function getApplicableCommands(PluginJamfMobileDevice $mobiledevice) {
      if (Session::haveRight(self::$rightname, CREATE) && PluginJamfUser_JSSAccount::hasLink()) {
         $allcommands = PluginJamfMDMCommand::getAvailableCommands();

         foreach ($allcommands as $command => &$params) {
            if (isset($params['requirements'])) {
               // Note: Costs are based on the number of DB or API calls. Checks should always be done least to most expensive.
               // DB call: 1 cost, API call: 2 cost
               // Check supervised - Cost: 0
               if (isset($params['requirements']['supervised']) &&
                  $params['requirements']['supervised'] != $mobiledevice->fields['supervised']) {
                  unset($allcommands[$command]);
                  continue;
               }

               // Check managed - Cost: 0
               if (isset($params['requirements']['managed']) &&
                  $params['requirements']['managed'] != $mobiledevice->fields['managed']) {
                  unset($allcommands[$command]);
                  continue;
               }

               // Check lost status - Cost: 0
               if (isset($params['requirements']['lostmode']) &&
                  $mobiledevice->fields['lost_mode_enabled'] !== 'true' && $mobiledevice->fields['lost_mode_enabled'] !== 'false') {
                  unset($allcommands[$command]);
                  continue;
               } else if (isset($params['requirements']['lostmode']) &&
                  $params['requirements']['lostmode'] && $mobiledevice->fields['lost_mode_enabled'] !== 'true') {
                  unset($allcommands[$command]);
                  continue;
               } else if (isset($params['requirements']['lostmode']) &&
                  !$params['requirements']['lostmode'] && $mobiledevice->fields['lost_mode_enabled'] !== 'false') {
                  unset($allcommands[$command]);
                  continue;
               }

               // Test device type requirements - Cost: 2
               if (isset($params['requirements']['devicetypes'])) {
                  if (!empty($params['requirements']['devicetypes'])) {
                     if (!array_key_exists('mobiledevice', $params['requirements']['devicetypes']) &&
                        !in_array('mobiledevice', $params['requirements']['devicetypes'])) {
                        $specifictype = $mobiledevice->getSpecificType();
                        if (!array_key_exists($specifictype, $params['requirements']['devicetypes']) &&
                           !in_array($specifictype, $params['requirements']['devicetypes'])) {
                           unset($allcommands[$command]);
                           continue;
                        }
                     }
                  }
               }
            }
         }
         return $allcommands;
      }
      return [];
   }

   static function showForItem(CommonDBTM $item)
   {
      if (!PluginJamfMobileDevice::canView()) {
         return false;
      }

      $mobiledevice = PluginJamfMobileDevice::getJamfItemForGLPIItem($item);
      if ($mobiledevice === null) {
         return false;
      }

      $commands = self::getApplicableCommands($mobiledevice);

      echo Html::hidden('itemtype', ['value' => $item->getType()]);
      echo Html::hidden('items_id', ['value' => $item->getID()]);
      echo "<div class='mdm-button-group'>";
      foreach ($commands as $command => $params) {
         $title = $params['name'];
         $icon = $params['icon'] ?? '';
         $icon_color = $params['icon_color'] ?? 'black';
         $onclick = "jamfPlugin.onMDMCommandButtonClick('$command', event)";
         echo "<div class='mdm-button' onclick=\"$onclick\"><i class='$icon' style='color: $icon_color'/>$title</div>";
      }
      echo "</div>";

      $item_commands = $mobiledevice->getMDMCommands();

      echo "<h3>" . __('Pending Commands', 'jamf') . "</h3>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<thead>";
      echo "<th>".__('Command', 'jamf')."</th>";
      echo "<th>".__('Status', 'jamf')."</th>";
      echo "<th>".__('Date issued', 'jamf')."</th>";
      echo "<th>".__('Date of last push', 'jamf')."</th>";
      echo "<th>".__('Username', 'jamf')."</th>";
      echo "</thead>";
      echo "<tbody>";
      foreach ($item_commands['pending'] as $entry) {
         $last_push = $entry['date_time_failed'] ?? '';
         $username = $entry['username'] ?? '';
         $issued = $entry['date_time_issued'];
         echo "<tr><td>{$entry['name']}</td><td>{$entry['status']}</td><td>{$issued}</td><td>{$last_push}</td><td>{$username}</td></tr>";
      }
      echo "</tbody>";
      echo "</table>";

      echo "<h3>" . __('Failed Commands', 'jamf') . "</h3>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<thead>";
      echo "<th>".__('Command', 'jamf')."</th>";
      echo "<th>".__('Error', 'jamf')."</th>";
      echo "<th>".__('Date issued', 'jamf')."</th>";
      echo "<th>".__('Date of last push', 'jamf')."</th>";
      echo "<th>".__('Username', 'jamf')."</th>";
      echo "</thead>";
      echo "<tbody>";
      foreach ($item_commands['failed'] as $entry) {
         $last_push = $entry['date_time_failed'];
         $username = $entry['username'] ?? '';
         $issued = $entry['date_time_issued'];
         echo "<tr><td>{$entry['name']}</td><td>{$entry['error']}</td><td>{$issued}</td><td>{$last_push}</td><td>{$username}</td></tr>";
      }
      echo "</tbody>";
      echo "</table>";
      $commands_json = json_encode($commands, JSON_FORCE_OBJECT);
      $jamf_id = $mobiledevice->fields['jamf_items_id'];
      $js = <<<JAVASCRIPT
         $(function(){
            jamfPlugin = new JamfPlugin();
            jamfPlugin.init({
               commands: $commands_json,
               jamf_id: $jamf_id
            });
         });
JAVASCRIPT;

      echo Html::scriptBlock($js);
      return true;
   }
}