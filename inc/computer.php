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

/**
 * PluginJamfComputer class. This represents a computer from Jamf.
 * This is mainly used to store extra fields that are not already in the GLPI Computer class.
 */
class PluginJamfComputer extends PluginJamfAbstractDevice
{
   static $rightname = 'plugin_jamf_computer';

   public static function getTypeName($nb = 1)
   {
      return _n('Jamf computer', 'Jamf computers', $nb, 'jamf');
   }

   /**
    * Display the extra information for Jamf devices on the main Computer or Phone tab.
    * @param array $params
    * @return void|bool Displays HTML only if a supported item is in the params parameter. If there is any issue, false is returned.
    * @since 1.0.0
    * @since 2.0.0 Renamed from showForComputerOrPhoneMain to showForItem
    */
   public static function showForItem(array $params)
   {
      global $CFG_GLPI;

      $item = $params['item'];

      if (!self::canView() || (!($item::getType() === 'Computer') && !($item::getType() === 'Phone'))) {
         return false;
      }

      $getYesNo = static function($value) {
         return $value ? __('Yes') : __('No');
      };

      $out = '';
      if ($item::getType() === 'Phone') {
         $uuid = PluginJamfExtField::getValue('Phone', $item->getID(), 'uuid');
         $out .= '<tr><td>' . __('UUID', 'jamf') . '</td><td>';
         $out .= Html::input('_plugin_jamf_uuid', [
            'value' => $uuid
         ]);
         $out .= '</td></tr>';
      }
      $mobiledevice = new self();
      $match = $mobiledevice->find([
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()]);

      if (!count($match)) {
         echo $out;
         return false;
      }
      $match = reset($match);

      $out .= "<tr><th colspan='4'>".__('Jamf General Information', 'jamf'). '</th></tr>';
      $out .= '<tr><td>' .__('Import date', 'jamf'). '</td>';
      $out .= '<td>' .Html::convDateTime($match['import_date']). '</td>';
      $out .= '<td>' .__('Last sync', 'jamf'). '</td>';
      $out .= '<td>' .Html::convDateTime($match['sync_date']). '</td></tr>';

      $out .= '<tr><td>' .__('Jamf last inventory', 'jamf'). '</td>';
      $out .= '<td>'.Html::convDateTime($match['last_inventory']). '</td>';
      $out .= '<td>'.__('Jamf import date', 'jamf'). '</td>';
      $out .= '<td>' .Html::convDateTime($match['entry_date']). '</td></tr>';

      $out .= '<tr><td>'.__('Enrollment date', 'jamf').'</td>';
      $out .= '<td>'.Html::convDateTime($match['enroll_date']).'</td>';
      $out .= '<td>'.__('Shared device', 'jamf').'</td>';
      $out .= '<td>'.$match['shared']. '</td></tr>';

      $out .= '<tr><td>'.__('Supervised', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['supervised']).'</td>';
      $out .= '<td>'.__('Managed', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['managed']).'</td></tr>';

      $out .= '<td>'.__('Cloud backup enabled', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['cloud_backup_enabled']).'</td>';
      $out .= '<td>'.__('Activation locked', 'jamf').'</td>';
      $out .= '<td>'.$getYesNo($match['activation_lock_enabled']).'</td></tr>';

      $link = self::getJamfDeviceURL($match['jamf_items_id']);
      $view_msg = __('View in Jamf', 'jamf');
      $out .= "<tr><td colspan='4' class='center'>";
      $out .= "<a class='vsubmit' href='{$link}' target='_blank'>{$view_msg}</a>";

      if ($item->canUpdate()) {
         $onclick = "syncDevice(\"{$item::getType()}\", {$item->getID()}); return false;";
         $out .= "&nbsp;&nbsp;<a class='vsubmit' onclick='{$onclick}'>".__('Sync now', 'jamf'). '</a>';
         $ajax_url = $CFG_GLPI['root_doc']. '/plugins/jamf/ajax/sync.php';
         $js = <<<JAVASCRIPT
               function syncDevice(itemtype, items_id) {
                  $.ajax({
                     type: "POST",
                     url: "{$ajax_url}",
                     data: {"itemtype": itemtype, "items_id": items_id},
                     contentType: 'application/json',
                     success: function() {
                        location.reload();
                     }
                  });
               }
JAVASCRIPT;
         $out .= Html::scriptBlock($js);
      }
      $out .= '</td></tr>';
      echo $out;
   }

   /**
    * Get a direct link to the device on the Jamf server.
    * @param int $jamf_id The Jamf ID of the device.
    * @return string Jamf URL for the mobile device.
    */
   public static function getJamfDeviceUrl(int $jamf_id): string
   {
      $config = PluginJamfConfig::getConfig();
      return "{$config['jssserver']}/computers.html?id={$jamf_id}";
   }

   public function getMDMCommands()
   {
      return [
         'completed' => [],
         'pending'   => [],
         'failed'    => []
      ];
   }
}