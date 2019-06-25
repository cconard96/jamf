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
 * JamfSync class.
 * This class handles actively syncing data from JAMF to GLPI.
 */
class PluginJamfSync extends CommonGLPI {

   private static function utcToLocal(DateTime $utc)
   {
      $tz = new DateTimeZone(date_default_timezone_get());
      $utc->setTimezone($tz);
      return $utc;
   }

   /**
    * Import a mobile device from Jamf into GLPI
    * @since 1.0.0
    * @param string $itemtype GLPI type of device (Computer or Phone)
    * @param array $jamf_items_id Jamf item IDs to import
    * @return bool True if the import(s) were successful
    */
   public static function importMobileDevice(string $itemtype, int $jamf_items_id) : bool
   {
      global $DB;

      if (($itemtype != 'Computer') && ($itemtype != 'Phone')) {
         // Invalid itemtype for a mobile device
         return false;
      }
      $item = new $itemtype();
      $mobiledevice = new PluginJamfMobileDevice();

      $jamf_item = PluginJamfAPIClassic::getItems('mobiledevices', ['id' => $jamf_items_id]);
      if (is_null($jamf_item)) {
         // API error or device no longer exists in Jamf
         return false;
      }

      $iterator = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => $itemtype::getTable(),
         'WHERE'  => [
            'uuid'   => $jamf_item['general']['udid']
         ],
         'LIMIT'  => 1
      ]);
      if ($iterator->count()) {
         // Already imported
         //TODO Support merging when a mobiledevice entry doesn't exist, but the GLPI item does
         \Glpi\Event::log(-1, $itemtype, 4, 'Jamf plugin', "Jamf mobile device $jamf_items_id not imported. A {$itemtype::getTypeName(1)} exists with the same uuid.");
         return false;
      }

      // Import new device
      $items_id = $item->add([
         'name' => $jamf_item['general']['name'],
         'entities_id'  => '0',
         'is_recursive' => '1'
      ]);
      if ($items_id) {
         self::updateComputerOrPhoneFromArray($itemtype, $items_id, $jamf_item);
      } else {
        return false;
      }
      return true;
   }

   private static function updateComputerOrPhoneFromArray($itemtype, $items_id, $data) {
      global $DB;

      try {
         $DB->beginTransaction();
         $item = new $itemtype();
         if (!$item->getFromDB($items_id)) {
            return false;
         }

         $config = PluginJamfConfig::getConfig();
         $general = $data['general'];
         $purchasing = $data['purchasing'];
         $security = $data['security'];
         $changes = [
            $itemtype  => []
         ];

         if ($config['sync_general']) {
            // Name has changed and it is not the default name (May be in the process of being set up)
            if (($general['name'] != $item->fields['name'])) {
               $changes[$itemtype]['name'] = $general['name'];
            }
            $othergeneral_item = [
               'asset_tag'       => 'otherserial',
               'serial_number'   => 'serial'
            ];
            if ($itemtype == 'Computer') {
               $othergeneral_item['udid'] = 'uuid';
            }
            foreach ($othergeneral_item as $jamf_field => $item_field) {
               if ($general[$jamf_field] != $item->fields[$item_field]) {
                  $changes[$itemtype][$item_field] = $general[$jamf_field];
               }
            }
            if ($itemtype == 'Phone') {
               $model = new PhoneModel();
            } else {
               $model = new ComputerModel();
            }
            $model_matches = $model->find(['name' => $general['model'], 'product_number' => $general['model_number']]);
            if (!count($model_matches)) {
               $model_id = $model->add([
                  'name'            => $general['model'],
                  'product_number'  => $general['model_number'],
                  'comment'         => 'Created by Jamf Plugin for GLPI',
               ]);
            } else {
               $model_id = array_keys($model_matches)[0];
            }
            if ($itemtype == 'Computer') {
               $changes[$itemtype]['computermodels_id'] = $model_id;
            } else {
               $changes[$itemtype]['phonemodels_id'] = $model_id;
            }

            if ($itemtype == 'Phone') {
               $preferred_type = $config['iphone_type'];
               if ($preferred_type) {
                  $changes['phonetypes_id'] = $preferred_type;
               }
            } else {
               $preferred_type = $config['ipad_type'];
               if ($preferred_type) {
                  $changes['computertypes_id'] = $preferred_type;
               }
            }
            
         }

         if ($config['sync_software']) {
            //TODO Not supported yet
         }

         if ($config['sync_os']) {
            $os = new OperatingSystem();
            $os_version = new OperatingSystemVersion();
            $os_matches = $os->find(['name' => $general['os_type']]);
            if (!count($os_matches)) {
               $os_id = $os->add([
                  'name'      => $general['os_type'],
                  'comment'   => 'Created by Jamf Plugin for GLPI'
               ]);
            } else {
               $os_id = array_keys($os_matches)[0];
            }

            $osversion_matches = $os_version->find(['name' => $general['os_version']]);
            if (!count($osversion_matches)) {
               $osversion_id = $os_version->add([
                  'name'      => $general['os_version'],
                  'comment'   => 'Created by Jamf Plugin for GLPI'
               ]);
            } else {
               $osversion_id = array_keys($osversion_matches)[0];
            }
            $DB->updateOrInsert(Item_OperatingSystem::getTable(), [
               'operatingsystems_id'         => $os_id,
               'operatingsystemversions_id'  => $osversion_id,
               'date_creation'               => $_SESSION['glpi_currenttime'],
            ], [
               'itemtype' => $itemtype,
               'items_id' => $items_id
            ]);
         }

         if ($config['sync_financial']) {
            $warranty_expiration = self::utcToLocal(new DateTime($purchasing['warranty_expires_utc']));
            $purchase_date = self::utcToLocal(new DateTime($purchasing['po_date_utc']));
            $diff = date_diff($warranty_expiration, $purchase_date);
            $warranty_length = $diff->m + ($diff->y * 12);
            $DB->updateOrInsert(Infocom::getTable(), [
               'buy_date'           => $purchase_date->format("Y-m-d H:i:s"),
               'warranty_date'      => $purchase_date->format("Y-m-d H:i:s"),
               'warranty_duration'  => $warranty_length,
               'warranty_info'      => "AppleCare ID: {$purchasing['applecare_id']}",
               'order_number'       => $purchasing['po_number'],
            ], [
               'itemtype' => $itemtype,
               'items_id' => $items_id
            ]);
         }

         if ($config['sync_components']) {
            //TODO Not implemented yet
         }

         if ($config['sync_user']) {
            //TODO Not implemented yet
         }

         // Update extra item data
         $mobiledevice = new PluginJamfMobileDevice();
         $md_match = $mobiledevice->find([
            'itemtype' => $itemtype,
            'items_id' => $items_id]);
         if (count($md_match)) {
            $md_id = array_keys($md_match)[0];
         } else {
            $md_id = -1;
         }
         $last_inventory = self::utcToLocal(new DateTime($general['last_inventory_update_utc']));
         $entry_date = self::utcToLocal(new DateTime($general['initial_entry_date_utc']));
         $enroll_date = self::utcToLocal(new DateTime($general['last_enrollment_utc']));
         $lost_mode_enable_date = self::utcToLocal(new DateTime($security['lost_mode_enable_issued_utc']));
         $lost_location_date = self::utcToLocal(new DateTime($security['lost_location_utc']));
         $DB->updateOrInsert('glpi_plugin_jamf_mobiledevices', [
            'udid'                     => $general['udid'],
            'last_inventory'           => $last_inventory->format("Y-m-d H:i:s"),
            'entry_date'               => $entry_date->format("Y-m-d H:i:s"),
            'enroll_date'              => $enroll_date->format("Y-m-d H:i:s"),
            'sync_date'                => $_SESSION['glpi_currenttime'],
            'managed'                  => $general['managed'],
            'supervised'               => $general['supervised'],
            'shared'                   => $general['shared'],
            'cloud_backup_enabled'     => $general['cloud_backup_enabled'],
            'activation_lock_enabled'  => $security['activation_lock_enabled'],
            'lost_mode_enabled'        => $security['lost_mode_enabled'],
            'lost_mode_enforced'       => $security['lost_mode_enforced'],
            'lost_mode_enable_date'    => $lost_mode_enable_date->format("Y-m-d H:i:s"),
            'lost_mode_message'        => $security['lost_mode_message'],
            'lost_mode_phone'          => $security['lost_mode_phone'],
            'lost_location_latitude'   => $security['lost_location_latitude'],
            'lost_location_longitude'  => $security['lost_location_longitude'],
            'lost_location_altitude'   => $security['lost_location_altitude'],
            'lost_location_speed'      => $security['lost_location_speed'],
            'lost_location_date'       => $lost_location_date->format("Y-m-d H:i:s"),
         ], ['itemtype' => $itemtype, 'items_id' => $items_id]);

         // Make main item updates last in case a sub-section of syncing has other changes to make to the item.
         $item->update([
            'id' => $items_id
         ] + $changes[$itemtype]);

         $DB->commit();
         return true;
      } catch (Exception $e) {
         Toolbox::logError($e->getMessage());
         $DB->rollBack();
         return false;
      }
   }

   public static function syncMobileDevice(string $itemtype, int $device_id) : bool
   {
      $mobiledevice = new PluginJamfMobileDevice();
      if (!$mobiledevice->getFromDB($device_id)) {
         return false;
      }

      $item = new $itemtype();
      if (!$item->getFromDB($mobiledevice->fields['items_id'])) {
         return false;
      }
      $data = PluginJamfAPIClassic::getItems('mobiledevices', ['udid' => $mobiledevice->fields['udid']]);
      if (is_null($data)) {
         // API error or device no longer exists in Jamf
         return false;
      }
      return self::updateComputerFromArray($itemtype, $item->getID(), $data);
   }

   public static function cronSyncJamf(CronTask $task) {
      global $DB;

      $mobiledevice = new PluginJamfMobileDevice();
      $all_mobiledevices = [];
      $iterator = $DB->request([
         'SELECT' => ['id', 'itemtype'],
         'FROM'   => PluginJamfMobileDevice::getTable()
      ]);
      if (!$iterator->count()) {
         return 0;
      }
      while ($data = $iterator->next()) {
         $result = self::syncMobileDevice($data['itemtype'], $data['id']);
         if ($result) {
            $task->addVolume(1);
         }
      }
      return 1;
   }

   public static function cronImportJamf(CronTask $task) {
      global $DB;

      $jamf_devices = PluginJamfAPIClassic::getItems('mobiledevices');
      if (is_null($jamf_devices) || !count($jamf_devices)) {
         // API error or device no longer exists in Jamf
         return 0;
      }
      $imported = [];
      $iterator = $DB->request([
         'SELECT' => ['udid'],
         'FROM'   => PluginJamfMobileDevice::getTable()
      ]);
      while ($data = $iterator->next()) {
         array_push($imported, $data['udid']);
      }
      $pending_iterator = $DB->request('glpi_plugin_jamf_imports', [
         'FROM'   => 'glpi_plugin_jamf_imports'
      ]);
      $pending_import = [];
      while ($data = $pending_iterator->next()) {
         $pending_import[$data['udid']] = $data;
      }

      $config = Config::getConfigurationValues('plugin:Jamf');
      foreach ($jamf_devices as $jamf_device) {
         if (in_array($jamf_device['udid'], $imported)) {
            // Already imported
         } else {
            $phone = strpos($jamf_device['model_identifier'], 'iPhone') !== false;
            if (isset($config['autoimport']) && $config['autoimport']) {
               $result = self::importMobileDevice($phone ? 'Phone' : 'Computer', $jamf_device['id']);
               if ($result) {
                  $task->addVolume(1);
               }
            } else {
               if (array_key_exists($jamf_device['udid'], $pending_import)) {
                  // Already pending
               } else {
                  $DB->insert('glpi_plugin_jamf_imports', [
                     'jamf_items_id'   => $jamf_device['id'],
                     'type'            => $phone ? 'Phone' : 'Computer',
                     'udid'            => $jamf_device['udid'],
                     'date_discover'   => $_SESSION['glpi_currenttime']
                  ]);
               }
            }
         }
      }
      return 1;
   }
}
