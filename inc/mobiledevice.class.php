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

/**
 * JamfMobileDevice class. This represents a mobile device from Jamf.
 * This is mainly used to store extra fields that are not already in Computer or Phone classes.
 */
class PluginJamfMobileDevice extends CommonDBChild
{

   static public $itemtype = 'itemtype';
   static public $items_id = 'items_id';
   static $rightname = 'plugin_jamf_mobiledevice';

   public static function getTypeName($nb = 1)
   {
      return __('Jamf mobile device', 'Jamf mobile devices', $nb, 'jamf');
   }

   /**
    * Display the extra information for mobile devices on the main Computer or Phone tab.
    * @param type $params
    * @return type
    */
   public static function showForComputerOrPhoneMain($params)
   {
      global $CFG_GLPI;

      $item = $params['item'];

      if (!self::canView() || (!($item::getType() == 'Computer') && !($item::getType() == 'Phone'))) {
         return false;
      }
      $mobiledevice = new PluginJamfMobileDevice();
      $match = $mobiledevice->find([
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()]);

      if (!count($match)) {
         return;
      }

      $getYesNo = function($value) {
         return $value ? __('Yes') : __('No');
      };

      $match = reset($match);
      $out = "<th colspan='4'>".__('Jamf General Information', 'jamf')."</th>";
      $out .= "<tr><td>".__('Import date', 'jamf')."</td>";
      $out .= "<td>".Html::convDateTime($match['import_date'])."</td>";
      $out .= "<td>".__('Last sync', 'jamf')."</td>";
      $out .= "<td>".Html::convDateTime($match['sync_date'])."</td></tr>";

      $out .= "<tr><td>".__('Jamf last inventory', 'jamf')."</td>";
      $out .= "<td>".Html::convDateTime($match['last_inventory'])."</td>";
      $out .= "<td>".__('Jamf import date', 'jamf')."</td>";
      $out .= "<td>".Html::convDateTime($match['entry_date'])."</td></tr>";

      $out .= "<tr><td>".__('Enrollment date', 'jamf')."</td>";
      $out .= "<td>".Html::convDateTime($match['enroll_date'])."</td>";
      $out .= "<td>".__('Shared device', 'jamf')."</td>";
      $out .= "<td>".$match['shared']."</td></tr>";

      $out .= "<tr><td>".__('Supervised', 'jamf')."</td>";
      $out .= "<<td>".$getYesNo($match['supervised'])."</td>";
      $out .= "<td>".__('Managed', 'jamf')."</td>";
      $out .= "<td>".$getYesNo($match['managed'])."</td></tr>";

      $out .= "<td>".__('Cloud backup enabled', 'jamf')."</td>";
      $out .= "<td>".$getYesNo($match['cloud_backup_enabled'])."</td>";
      $out .= "<td>".__('Activation locked', 'jamf')."</td>";
      $out .= "<td>".$getYesNo($match['activation_lock_enabled'])."</td></tr>";

      $link = self::getJamfDeviceURL($match['jamf_items_id']);
      $view_msg = __('View in Jamf', 'jamf');
      $out .= "<tr><td colspan='4' class='center'>";
      $out .= "<a class='vsubmit' href='{$link}' target='_blank'>{$view_msg}</a>";

      if ($item->canUpdate()) {
         $out .= "&nbsp;&nbsp;<a class='vsubmit' onclick='syncDevice(&quot;{$item::getType()}&quot;, {$item->getID()}); return false;'>".__('Sync now', 'jamf')."</a>";
         $ajax_url = $CFG_GLPI['root_doc']."/plugins/jamf/ajax/sync.php";
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
      $out .= "</td></tr>";

      $out .= "<th colspan='4'>".__('Jamf Lost Mode Information', 'jamf')."</th>";
      $enabled = $match['lost_mode_enabled'];
      if (!$enabled || ($enabled != 'true')) {
         $out .= "<tr class='center'><td colspan='4'>".__('Lost mode is not enabled')."</td></tr>";
      } else {
         $out .= "<tr><td>".__('Enabled', 'jamf')."</td>";
         $out .= "<td>".$enabled."</td>";
         $out .= "<td>".__('Enforced', 'jamf')."</td>";
         $out .= "<td>".$getYesNo($match['lost_mode_enforced'])."</td></tr>";

         $out .= "<tr><td>".__('Enable date', 'jamf')."</td>";
         $out .= "<td>".Html::convDateTime($match['lost_mode_enable_date'])."</td></tr>";

         $out .= "<tr><td>".__('Message', 'jamf')."</td>";
         $out .= "<td>".$match['lost_mode_message']."</td>";
         $out .= "<td>".__('Phone', 'jamf')."</td>";
         $out .= "<td>".$match['lost_mode_phone']."</td></tr>";

         $lat = $match['lost_location_latitude'];
         $long = $match['lost_location_longitude'];
         $out .= "<td>".__('GPS')."</td><td>";
         //TODO Use leaflet
         $out .= Html::link("$lat, $long", "https://www.google.com/maps/place/$lat,$long", [
            'display'   => false
         ]);
         $out .= "</td><td>".__('Altitude')."</td>";
         $out .= "<td>".$match['lost_location_altitude']."</td>";
         $out .= "<tr><td>".__('Speed', 'jamf')."</td>";
         $out .= "<td>".$match['lost_location_speed']."</td>";
         $out .= "<td>".__('Lost location date')."</td>";
         $out .= "<td>".Html::convDateTime($match['lost_location_date'])."</td></tr>";
      }

      echo $out;
   }

   /**
    * Get a direct link to the mobile device on the Jamf server.
    * @param string $jamf_id The Jamf ID of the device.
    * @return string Jamf URL for the mobile device.
    */
   public static function getJamfDeviceURL($jamf_id)
   {
      $config = PluginJamfConfig::getConfig();
      return "{$config['jssserver']}/mobileDevices.html?udid={$jamf_id}";
   }

   /**
    * Cleanup relations when an item is purged.
    * @global type $DB
    * @param CommonDBTM $item
    */
   private static function purgeItemCommon(CommonDBTM $item)
   {
      global $DB;

      $DB->delete(self::getTable(), [
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()
      ]);
   }

   /**
    * Cleanup relations when a Computer is purged.
    * @global type $DB
    * @param CommonDBTM $item
    */
   public static function plugin_jamf_purgeComputer(Computer $item)
   {
      self::purgeItemCommon($item);
   }

   /**
    * Cleanup relations when a Phone is purged.
    * @global type $DB
    * @param CommonDBTM $item
    */
   public static function plugin_jamf_purgePhone(Phone $item)
   {
      global $DB;

      self::purgeItemCommon($item);
      $DB->delete(Item_OperatingSystem::getTable(), [
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()
      ]);
   }

   /**
    * @param CommonDBTM $item
    * @return PluginJamfMobileDevice
    */
   public static function getJamfItemForGLPIItem(CommonDBTM $item)
   {
       $mobiledevice = new PluginJamfMobileDevice();
       $matches = $mobiledevice->find([
           'itemtype'   => $item::getType(),
           'items_id'   => $item->getID()
       ], [], 1);
       if (count($matches)) {
           $id = reset($matches)['id'];
           $mobiledevice->getFromDB($id);
           return $mobiledevice;
       }
       return null;
   }

   public function getExtensionAttributes()
   {
       global $DB;

       $ext_table = PluginJamfExtensionAttribute::getTable();
       $item_ext_table = PluginJamfItem_ExtensionAttribute::getTable();

       $iterator = $DB->request([
           'SELECT' => [
               'name', 'data_type', 'value'
           ],
           'FROM'   => $ext_table,
           'LEFT JOIN'  => [
               $item_ext_table => [
                   'FKEY'   => [
                       $ext_table       => 'id',
                       $item_ext_table  => 'glpi_plugin_jamf_extensionattributes_id'
                   ]
               ]
           ],
           'WHERE'  => [
               $item_ext_table.'.itemtype'   => self::getType(),
               'items_id'   => $this->getID()
           ]
       ]);

       $attributes = [];
       while ($data = $iterator->next()) {
           $attributes[] = $data;
       }
       return $attributes;
   }
}
