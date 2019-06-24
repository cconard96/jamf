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
    * @param string $devicetype Type of device. Only mobile is supported currently
    * @param array $jamf_items_ids Array of Jamf item IDs to import
    * @return bool True if the import(s) were successful
    */
   public static function importDevice(string $devicetype, array $jamf_items_ids = []) : bool
   {
      global $DB;

      $computer = new Computer();
      $mobiledevice = new PluginJamfMobileDevice();
      if ($devicetype == 'mobile') {
         $import_datas = [];

         foreach ($jamf_items_ids as $jamf_items_id) {
            $data = PluginJamfAPIPro::getMobileDevice($jamf_items_id);
            if (is_null($jamf_item)) {
               // API error or device no longer exists in Jamf
               continue;
            }
            $import_datas[$jamf_items_id] = [
               'data'   => $data
            ];

            $iterator = $DB->request([
               'SELECT' => ['id'],
               'FROM'   => Computer::getTable(),
               'WHERE'  => [
                  'uuid'   => $jamf_item['general']['udid']
               ],
               'LIMIT'  => 1
            ]);
            if ($iterator->count()) {
               // Already imported
               //TODO Support merging when a mobiledevice entry doesn't exist, but the computer does
               \Glpi\Event::log(-1, 'Computer', 4, 'Jamf plugin', "Jamf mobile device $jamf_items_id not imported. A computer exists with the same uuid.");
               return false;
            }
            $import_datas[$jamf_items_id]['computer'] = [
               'name' => $jamf_item['general']['name'],
               'entities_id'  => '0',
               'is_recursive' => '1'
            ];
         }

         $status = [];
         foreach ($import_datas as $jamf_id => $import_item) {
            // Import new device
            $computers_id = $computer->add($import_item['computer']);
            if ($computers_id) {
               $status[$jamf_id] = self::updateComputerFromArray($computers_id, $import_item['data']);
            } else {
               $status[$jamf_id] = false;
            }
         }
         return count(array_filter($status, function($s) {
            return $s === false;
         })) == 0;
      }
   }

   private static function updateComputerFromArray($computers_id, $data) {
      global $DB;

      try {
         $DB->beginTransaction();
         $computer = new Computer();
         if (!$computer->getFromDB($computers_id)) {
            return false;
         }

         $config = PluginJamfConfig::getConfig();
         $general = $data['general'];
         $purchasing = $data['purchasing'];
         $security = $data['security'];
         $changes = [
            'Computer'  => []
         ];

         if ($config['sync_general']) {
            // Name has changed and it is not the default name (May be in the process of being set up)
            if (($general['name'] != $computer->fields['name'])) {
               $changes['Computer']['name'] = $general['name'];
            }
            $othergeneral_computer = [
               'asset_tag'       => 'otherserial',
               'udid'            => 'uuid',
               'serial_number'   => 'serial'
            ];
            foreach ($othergeneral_computer as $jamf_field => $computer_field) {
               if ($general[$jamf_field] != $computer->fields[$computer_field]) {
                  $changes['Computer'][$computer_field] = $general[$jamf_field];
               }
            }
            $model = new ComputerModel();
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
               'itemtype' => 'Computer',
               'items_id' => $computers_id
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
               'itemtype' => 'Computer',
               'items_id' => $computers_id
            ]);
         }

         if ($config['sync_components']) {
            //TODO Not implemented yet
         }

         if ($config['sync_user']) {
            //TODO Not implemented yet
         }

         // Update extra computer data
         $mobiledevice = new PluginJamfMobileDevice();
         $md_match = $mobiledevice->find(['computers_id' => $computers_id]);
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
         ], ['computers_id' => $computers_id]);

         // Make computer updates last in case a sub-section of syncing has other changes to make to the Computer.
         $computer->update([
            'id' => $computers_id
         ] + $changes['Computer']);

         $DB->commit();
         return true;
      } catch (Exception $e) {
         Toolbox::logError($e->getMessage());
         $DB->rollBack();
         return false;
      }
   }

   public static function syncDevice(string $devicetype, int $device_id) : bool
   {
      if ($devicetype == 'mobile') {
         $computer = new Computer();
         $mobiledevice = new PluginJamfMobileDevice();
         if (!$mobiledevice->getFromDB($device_id)) {
            return false;
         }
         if (!$computer->getFromDB($mobiledevice->fields['computers_id'])) {
            return false;
         }
         $data = PluginJamfAPIClassic::getItems('mobiledevices', ['udid' => $mobiledevice->fields['udid']]);
         if (is_null($data)) {
            // API error or device no longer exists in Jamf
            return false;
         }
         return self::updateComputerFromArray($computer->getID(), $data);
      }
   }

   public static function cronSyncJamf(CronTask $task) {
      global $DB;

      $mobiledevice = new PluginJamfMobileDevice();
      $all_mobiledevices = [];
      $iterator = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => PluginJamfMobileDevice::getTable()
      ]);
      while ($data = $iterator->next()) {
         array_push($all_mobiledevices, $data['id']);
      }
      if (!count($all_mobiledevices)) {
         return 0;
      }
      foreach ($all_mobiledevices as $device_id) {
         $result = self::syncDevice('mobile', $device_id);
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

      foreach ($jamf_devices as $jamf_device) {
         if (in_array($jamf_device['udid'], $imported)) {
            // Already imported
         } else {
            $config = Config::getConfigurationValues('plugin:Jamf');
            if (isset($config['autoimport']) && $config['autoimport']) {
               $result = self::importDevice('mobile', $jamf_device['id']);
               if ($result) {
                  $task->addVolume(1);
               }
            } else {
               if (array_key_exists($jamf_device['udid'], $pending_import)) {
                  // Already pending
               } else {
                  $DB->insert('glpi_plugin_jamf_imports', [
                     'jamf_items_id'   => $jamf_device['id'],
                     'type'            => 'mobile',
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
