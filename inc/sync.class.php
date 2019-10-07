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
 * PluginJamfSync class.
 * This class handles actively syncing data from JAMF to GLPI.
 */
class PluginJamfSync extends CommonGLPI
{

   /**
    * The sync task completed successfully.
    */
   const STATUS_OK = 0;
   /**
    * The sync task was skipped because the required data was not supplied (rights error on JSS), the config denies the sync, or another reason.
    */
   const STATUS_SKIPPED = 1;
   /**
    * An error occurred during the sync task.
    */
   const STATUS_ERROR = 2;
   /**
    * An attempt was made to run async task without the necessary resources being ready.
    * For example, adding an extension attribute to a mobile device on the first sync before it is created.
    * In this case, the task will get deferred until the sync is finalized. At that stage, the task is retired a final time.
    */
   const STATUS_DEFERRED = 3;

   /**
    * @var bool If true, it indicates an instance of the sync engine was created without the intention of using it for syncing.
    *              Any task that attempts to run, will be set to an error state.
    */
   private $dummySync = false;
   private $config = [];
   private $item_changes = [];
   private $extitem_changes = [];
   private $mobiledevice_changes = [];
   private $data = [];
   /** @var CommonDBTM */
   private $item = null;
   private $jamfitemtype = 'PluginJamfMobileDevice';
   /** @var PluginJamfMobileDevice */
   private $jamfdevice = null;
   private $status = [];

   /**
    * Helper function to convert the UTC timestamps from JSS to a local DateTime.
    * @param DateTime $utc The UTC DateTime from JSS.
    * @return DateTime The local DateTime.
    */
   private static function utcToLocal(DateTime $utc)
   {
      //TODO Use GLPI timezone when GLPI supports them.
      $tz = new DateTimeZone(date_default_timezone_get());
      $utc->setTimezone($tz);
      return $utc;
   }

   /**
    * Import a mobile device from Jamf into GLPI
    * @param string $itemtype GLPI type of device (Computer or Phone)
    * @param array $jamf_items_id Jamf item IDs to import
    * @return bool True if the import(s) were successful
    * @since 1.0.0
    */
   public static function importMobileDevice(string $itemtype, int $jamf_items_id): bool
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

      $rules = new PluginJamfRuleImportCollection();
      $ruleinput = [
         'name' => $jamf_item['general']['name'],
         'itemtype' => $itemtype,
         'last_inventory' => $jamf_item['general']['last_inventory_update_utc'],
         'managed' => $jamf_item['general']['managed'],
         'supervised' => $jamf_item['general']['supervised'],
      ];
      $ruleinput = $rules->processAllRules($ruleinput, $ruleinput, ['recursive' => true]);
      $import = isset($ruleinput['_import']) ? $ruleinput['_import'] : 'NS';

      if (isset($ruleinput['_import']) && !$ruleinput['_import']) {
         // Dropped by rules
         return false;
      }

      if ($DB->fieldExists($itemtype::getTable(), 'uuid')) {
         $iterator = $DB->request([
            'SELECT' => [$itemtype::getTable().'.id'],
            'FROM' => $itemtype::getTable(),
            'WHERE' => [
               'uuid' => $jamf_item['general']['udid']
            ],
            'LIMIT' => 1
         ]);
      } else {
         $iterator = $DB->request([
            'SELECT' => [$itemtype::getTable().'.id'],
            'FROM' => $itemtype::getTable(),
            'LEFT JOIN' => [
               'glpi_plugin_jamf_extfields' => [
                  'FKEY' => [
                     'glpi_plugin_jamf_extfields' => 'items_id',
                     $itemtype::getTable() => 'id', [
                        'AND' => [
                           'glpi_plugin_jamf_extfields.itemtype' => $itemtype
                        ]
                     ]
                  ]
               ]
            ],
            'WHERE' => [
               'glpi_plugin_jamf_extfields.name' => 'uuid',
               'glpi_plugin_jamf_extfields.value' => $jamf_item['general']['udid']
            ],
            'LIMIT' => 1
         ]);
      }
      if ($iterator->count()) {
         // Already imported
         \Glpi\Event::log(-1, $itemtype, 4, 'Jamf plugin', "Jamf mobile device $jamf_items_id not imported. A {$itemtype::getTypeName(1)} exists with the same uuid.");
         return false;
      }

      $DB->beginTransaction();
      // Import new device
      $items_id = $item->add([
         'name' => $DB->escape($jamf_item['general']['name']),
         'entities_id' => '0',
         'is_recursive' => '1'
      ]);
      if ($items_id) {
         if (self::updateComputerOrPhoneFromArray($itemtype, $items_id, $jamf_item, false)) {
            $DB->update('glpi_plugin_jamf_mobiledevices', [
               'import_date' => $_SESSION['glpi_currenttime']
            ], [
               'itemtype' => $itemtype,
               'items_id' => $items_id
            ]);
            $DB->delete(PluginJamfImport::getTable(), ['jamf_items_id' => $jamf_items_id]);
            $DB->commit();
         } else {
            $DB->rollBack();
         }
      } else {
         $DB->rollBack();
         return false;
      }
      return true;
   }

   public function __construct(CommonDBTM $item = null, string $jamf_itemtype = null, array $data = [])
   {
      if ($item === null) {
         $this->dummySync = true;
         return;
      }
      $this->config = PluginJamfConfig::getConfig();
      $this->item = $item;
      $this->data = $data;
      $jamfitem = new $jamf_itemtype();
      $this->jamfitemtype = $jamf_itemtype;
      $jamf_match = $jamfitem->find([
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()], [], 1);
      if (count($jamf_match)) {
         $jamf_id = reset($jamf_match)['id'];
         $jamfitem->getFromDB($jamf_id);
         $this->jamfdevice = $jamfitem;
      }
   }

   /**
    * Sync general information such as name, serial number, etc.
    * All synced fields here are on the main GLPI item and not a plugin item type.
    * @return PluginJamfSync
    */
   private function syncGeneral()
   {
      global $DB;

      if ($this->dummySync) {
         $this->status['syncGeneral'] = self::STATUS_ERROR;
         return $this;
      }
      if (!$this->config['sync_general'] || $this->item === null || !isset($this->data['general'])) {
         $this->status['syncGeneral'] = self::STATUS_SKIPPED;
         return $this;
      }
      try {
         $general = $this->data['general'];
         $itemtype = $this->item::getType();
         $config = PluginJamfConfig::getConfig();

         if (($general['name'] != $this->item->fields['name'])) {
            $this->item_changes['name'] = $general['name'];
         }
         $othergeneral_item = [
            'asset_tag' => 'otherserial',
            'serial_number' => 'serial'
         ];
         if ($itemtype == 'Computer') {
            $othergeneral_item['udid'] = 'uuid';
         } else {
            $this->extitem_changes['uuid'] = $general['udid'];
         }
         foreach ($othergeneral_item as $jamf_field => $item_field) {
            if ($general[$jamf_field] != $this->item->fields[$item_field]) {
               $this->item_changes[$item_field] = $DB->escape($general[$jamf_field]);
            }
         }

         // Create or match model
         if ($itemtype == 'Phone') {
            $model = new PhoneModel();
         } else {
            $model = new ComputerModel();
         }
         $model_matches = $model->find(['name' => $general['model'], 'product_number' => $general['model_number']]);
         if (!count($model_matches)) {
            $model_id = $model->add([
               'name' => $DB->escape($general['model']),
               'product_number' => $DB->escape($general['model_number']),
               'comment' => 'Created by Jamf Plugin for GLPI',
            ]);
         } else {
            $model_id = array_keys($model_matches)[0];
         }

         // Set model
         if ($itemtype == 'Computer') {
            $this->item_changes['computermodels_id'] = $model_id;
         } else {
            $this->item_changes['phonemodels_id'] = $model_id;
         }

         // Set default type
         if ($itemtype == 'Phone') {
            $preferred_type = $config['iphone_type'];
            if ($preferred_type) {
               $this->item_changes['phonetypes_id'] = $preferred_type;
            }
         } else {
            if (strpos($general['model'], 'Apple TV') === false) {
               $preferred_type = $config['ipad_type'];
            } else {
               $preferred_type = $config['appletv_type'];
            }
            if ($preferred_type) {
               $this->item_changes['computertypes_id'] = $preferred_type;
            }
         }

         // Set default manufacturer
         $preferred_manufacturer = $config['default_manufacturer'];
         if ($preferred_manufacturer) {
            $this->item_changes['manufacturers_id'] = $preferred_manufacturer;
         }
      } catch (Exception $e) {
         $this->status['syncGeneral'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncGeneral'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Sync operating system information.
    * @return PluginJamfSync
    */
   private function syncOS()
   {
      global $DB;

      if ($this->dummySync) {
         $this->status['syncOS'] = self::STATUS_ERROR;
         return $this;
      }
      if (!$this->config['sync_os'] || $this->item === null || !isset($this->data['general'])) {
         $this->status['syncOS'] = self::STATUS_SKIPPED;
         return $this;
      }
      try {
         $general = $this->data['general'];
         $os = new OperatingSystem();
         $os_version = new OperatingSystemVersion();
         $os_matches = $os->find(['name' => $general['os_type']]);
         if (!count($os_matches)) {
            $os_id = $os->add([
               'name' => $general['os_type'],
               'comment' => 'Created by Jamf Plugin for GLPI'
            ]);
         } else {
            $os_id = array_keys($os_matches)[0];
         }

         $osversion_matches = $os_version->find(['name' => $general['os_version']]);
         if (!count($osversion_matches)) {
            $osversion_id = $os_version->add([
               'name' => $general['os_version'],
               'comment' => 'Created by Jamf Plugin for GLPI'
            ]);
         } else {
            $osversion_id = array_keys($osversion_matches)[0];
         }
         $DB->updateOrInsert(Item_OperatingSystem::getTable(), [
            'operatingsystems_id' => $os_id,
            'operatingsystemversions_id' => $osversion_id,
            'date_creation' => $_SESSION['glpi_currenttime'],
         ], [
            'itemtype' => $this->item::getType(),
            'items_id' => $this->item->getID()
         ]);
      } catch (Exception $e) {
         $this->status['syncOS'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncOS'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Sync software information.
    * @return PluginJamfSync
    */
   private function syncSoftware()
   {
      global $DB;

      if ($this->dummySync) {
         $this->status['syncSoftware'] = self::STATUS_ERROR;
         return $this;
      }
      if ($this->item::getType() !== 'Computer') {
         $this->status['syncSoftware'] = self::STATUS_SKIPPED;
         return $this;
      }
      if (!$this->config['sync_software'] || $this->item === null || isset($this->data['applications'])) {
         $this->status['syncSoftware'] = self::STATUS_SKIPPED;
         return $this;
      }
      try {
         $applications = $this->data['applications'];
         $software = new Software();
         $softwareversion = new SoftwareVersion();
         $jamf_software = new PluginJamfSoftware();
         foreach ($applications as $application) {
            $jamfsoftware_matches = $jamf_software->find(['bundle_id' => $application['identifier']]);
            if (!count($jamfsoftware_matches)) {
               $software_data = PluginJamfAPIClassic::getItems('mobiledeviceapplications', [
                  'bundleid' => $application['identifier'],
                  'version' => $application['application_version']
               ]);
               if (is_null($software_data) || !isset($software_data['general'])) {
                  continue;
               }
               $software_id = $software->add([
                  'name' => $DB->escape($software_data['general']['name']),
                  'comment' => $DB->escape($software_data['general']['description']),
                  'entities_id' => $this->item->fields['entities_id'],
                  'is_recursive' => $this->item->fields['is_recursive']
               ]);
               $jamf_software->add([
                  'softwares_id' => $software_id,
                  'bundle_id' => $application['identifier'],
                  'itunes_store_url' => $DB->escape($software_data['general']['itunes_store_url'])
               ]);
            } else {
               $software_id = array_values($jamfsoftware_matches)[0]['softwares_id'];
            }
            $softwareversion_matches = $softwareversion->find([
               'softwares_id' => $software_id,
               'name' => $application['application_version']
            ]);
            if (!count($softwareversion_matches)) {
               $version_input = [
                  'softwares_id' => $software_id,
                  'name' => $application['application_version'],
                  'entities_id' => $this->item->fields['entities_id'],
                  'is_recursive' => $this->item->fields['is_recursive']
               ];
               if (isset($os_id)) {
                  $version_input['operatingsystems_id'] = $os_id;
               }
               $softwareversion_id = $softwareversion->add($version_input);
            } else {
               $softwareversion_id = array_keys($softwareversion_matches)[0];
            }
            $computer_softwareversion = new Computer_SoftwareVersion();
            $computer_softwareversion_matches = $computer_softwareversion->find([
               'computers_id' => $this->item->getID(),
               'softwareversions_id' => $softwareversion_id
            ]);
            if (!count($computer_softwareversion_matches)) {
               $computer_softwareversion->add([
                  'computers_id' => $this->item->getID(),
                  'softwareversions_id' => $softwareversion_id,
                  'entities_id' => $this->item->fields['entities_id'],
                  'is_recursive' => $this->item->fields['is_recursive']
               ]);
            }
         }
      } catch (Exception $e) {
         $this->status['syncSoftware'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncSoftware'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Sync user information.
    * @return PluginJamfSync
    */
   private function syncUser()
   {

      if ($this->dummySync) {
         $this->status['syncUser'] = self::STATUS_ERROR;
         return $this;
      }
      if (!$this->config['sync_user'] || $this->item === null || !isset($this->data['location'])) {
         $this->status['syncUser'] = self::STATUS_SKIPPED;
         return $this;
      }
      try {
         $location = $this->data['location'];
         $user = new User();
         $users_id = $user->find(['name' => $location['username']]);
         if ($users_id) {
            $this->item_changes['users_id'] = $users_id;
         }
      } catch (Exception $e) {
         $this->status['syncUser'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncUser'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Sync purchasing information.
    * @return PluginJamfSync
    */
   private function syncPurchasing()
   {
      global $DB;

      if ($this->dummySync) {
         $this->status['syncPurchasing'] = self::STATUS_ERROR;
         return $this;
      }
      if (!$this->config['sync_financial'] || $this->item === null || !isset($this->data['purchasing'])) {
         $this->status['syncPurchasing'] = self::STATUS_SKIPPED;
         return $this;
      }
      try {
         $purchasing = $this->data['purchasing'];
         $infocom_changes = [];
         if (!empty($purchasing['po_date_utc'])) {
            $purchase_date = self::utcToLocal(new DateTime($purchasing['po_date_utc']));
            $purchase_date_str = $purchase_date->format("Y-m-d H:i:s");
            $infocom_changes['buy_date'] = $purchase_date_str;
            if (!empty($purchasing['warranty_expires_utc'])) {
               $infocom_changes['warranty_date'] = $purchase_date_str;
               $warranty_expiration = self::utcToLocal(new DateTime($purchasing['warranty_expires_utc']));
               $diff = date_diff($warranty_expiration, $purchase_date);
               $warranty_length = $diff->m + ($diff->y * 12);
               $infocom_changes['warranty_duration'] = $warranty_length;
            }
         }
         if (!empty($purchasing['applecare_id'])) {
            $infocom_changes['warranty_info'] = "AppleCare ID: {$purchasing['applecare_id']}";
         }
         if (!empty($purchasing['po_number'])) {
            $infocom_changes['order_number'] = $purchasing['po_number'];
         }

         $DB->updateOrInsert(Infocom::getTable(), $infocom_changes, [
            'itemtype' => $this->item::getType(),
            'items_id' => $this->item->getID()
         ]);
      } catch (Exception $e) {
         $this->status['syncPurchasing'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncPurchasing'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Sync extension attributes. This task will be deferred if run for a device that was not previously imported.
    * @return PluginJamfSync
    */
   private function syncExtensionAttributes()
   {
      global $DB;

      if ($this->dummySync) {
         $this->status['syncExtensionAttributes'] = self::STATUS_ERROR;
         return $this;
      }
      if (!$this->config['sync_general'] || !isset($this->data['extension_attributes'])) {
         $this->status['syncExtensionAttributes'] = self::STATUS_SKIPPED;
         return $this;
      } else if ($this->config['sync_general'] && $this->jamfdevice === null) {
         error_log("Deferred extension attribute sync");
         $this->status['syncExtensionAttributes'] = self::STATUS_DEFERRED;
         return $this;
      }
      try {
         $extension_attributes = $this->data['extension_attributes'];
         $ext_attribute = new PluginJamfExtensionAttribute();
         error_log("Running extension attribute sync");

         foreach ($extension_attributes as $attr) {
            $attr_match = $ext_attribute->find([
               'jamf_id' => $attr['id'],
               'itemtype' => $this->jamfdevice::getType()
            ], [], 1);

            if ($attr_match !== null && count($attr_match)) {
               $attr_match = reset($attr_match);
               $DB->updateOrInsert(PluginJamfItem_ExtensionAttribute::getTable(), ['value' => $attr['value']], [
                  'glpi_plugin_jamf_extensionattributes_id' => $attr_match['id'],
                  'items_id' => $this->jamfdevice->getID(),
                  'itemtype' => $this->jamfdevice::getType()
               ]);
            }
         }
      } catch (Exception $e) {
         $this->status['syncExtensionAttributes'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncExtensionAttributes'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Sync security information.
    * @return PluginJamfSync
    */
   private function syncSecurity()
   {
      if ($this->dummySync) {
         $this->status['syncSecurity'] = self::STATUS_ERROR;
         return $this;
      }
      if ($this->item === null || !isset($this->data['security'])) {
         $this->status['syncSecurity'] = self::STATUS_SKIPPED;
         return $this;
      }
      try {
         $security = $this->data['security'];
         if (!empty($security['lost_mode_enable_issued_utc'])) {
            $lost_mode_enable_date = self::utcToLocal(new DateTime($security['lost_mode_enable_issued_utc']));
            $this->mobiledevice_changes['lost_mode_enable_date'] = $lost_mode_enable_date->format("Y-m-d H:i:s");
         }
         if (!empty($security['lost_location_utc'])) {
            $lost_location_date = self::utcToLocal(new DateTime($security['lost_location_utc']));
            $this->mobiledevice_changes['lost_location_date'] = $lost_location_date->format("Y-m-d H:i:s");
         }
         $this->mobiledevice_changes['activation_lock_enabled'] = $security['activation_lock_enabled'];
         $this->mobiledevice_changes['lost_mode_enabled'] = $security['lost_mode_enabled'];
         $this->mobiledevice_changes['lost_mode_enforced'] = $security['lost_mode_enforced'];
         $this->mobiledevice_changes['lost_mode_message'] = $security['lost_mode_message'];
         $this->mobiledevice_changes['lost_mode_phone'] = $security['lost_mode_phone'];
         $this->mobiledevice_changes['lost_location_latitude'] = $security['lost_location_latitude'];
         $this->mobiledevice_changes['lost_location_longitude'] = $security['lost_location_longitude'];
         $this->mobiledevice_changes['lost_location_altitude'] = $security['lost_location_altitude'];
         $this->mobiledevice_changes['lost_location_speed'] = $security['lost_location_speed'];
      } catch (Exception $e) {
         $this->status['syncSecurity'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncSecurity'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Sync general Jamf device information. All changes are made for the Jamf plugin item only. No GLPI item changes are made here.
    * @return PluginJamfSync
    */
   private function syncGeneralJamf()
   {

      if ($this->dummySync) {
         $this->status['syncGeneralJamf'] = self::STATUS_ERROR;
         return $this;
      }
      if (!$this->config['sync_general'] || $this->item === null || !isset($this->data['general'])) {
         $this->status['syncGeneralJamf'] = self::STATUS_SKIPPED;
         return $this;
      }
      try {
         $general = $this->data['general'];
         if (!empty($general['last_inventory_update_utc'])) {
            $last_inventory = self::utcToLocal(new DateTime($general['last_inventory_update_utc']));
            $this->mobiledevice_changes['last_inventory'] = $last_inventory->format("Y-m-d H:i:s");
         }
         if (!empty($general['initial_entry_date_utc'])) {
            $entry_date = self::utcToLocal(new DateTime($general['initial_entry_date_utc']));
            $this->mobiledevice_changes['entry_date'] = $entry_date->format("Y-m-d H:i:s");
         }
         if (!empty($general['last_enrollment_utc'])) {
            $enroll_date = self::utcToLocal(new DateTime($general['last_enrollment_utc']));
            $this->mobiledevice_changes['enroll_date'] = $enroll_date->format("Y-m-d H:i:s");
         }

         $this->mobiledevice_changes['jamf_items_id'] = $general['id'];
         $this->mobiledevice_changes['udid'] = $general['udid'];
         $this->mobiledevice_changes['managed'] = $general['managed'];
         $this->mobiledevice_changes['supervised'] = $general['supervised'];
         $this->mobiledevice_changes['shared'] = $general['shared'];
         $this->mobiledevice_changes['cloud_backup_enabled'] = $general['cloud_backup_enabled'];
      } catch (Exception $e) {
         $this->status['syncGeneralJamf'] = self::STATUS_ERROR;
         return $this;
      }
      $this->status['syncGeneralJamf'] = self::STATUS_OK;
      return $this;
   }

   /**
    * Apply all pending changes and retry deferred tasks.
    * @return array STATUS_OK if the sync was successful, STATUS_ERROR otherwise.
    */
   private function finalizeSync()
   {
      global $DB;

      if ($this->dummySync) {
         return $this->status;
      }
      $this->mobiledevice_changes['sync_date'] = $_SESSION['glpi_currenttime'];
      $this->item->update([
            'id' => $this->item->getID()
         ] + $this->item_changes);
      foreach ($this->extitem_changes as $key => $value) {
         PluginJamfExtField::setValue($this->item::getType(), $this->item->getID(), $key, $value);
      }
      $DB->updateOrInsert('glpi_plugin_jamf_mobiledevices', $this->mobiledevice_changes, [
         'itemtype' => $this->item::getType(),
         'items_id' => $this->item->getID()
      ]);

      if ($this->jamfdevice === null) {
         $jamf_item = new $this->jamfitemtype();
         $jamf_match = $jamf_item->find([
            'itemtype' => $this->item::getType(),
            'items_id' => $this->item->getID()], [], 1);
         if (count($jamf_match)) {
            $jamf_item->getFromDB(reset($jamf_match)['id']);
            $this->jamfdevice = $jamf_item;
         }
      }

      // Re-run all deferred tasks
      $deferred = array_keys($this->status, self::STATUS_DEFERRED);
      foreach ($deferred as $task) {
         if (method_exists($this, $task)) {
            $this->$task();
         } else {
            $this->status[$task] = self::STATUS_ERROR;
         }
      }
      return $this->status;
   }

   /**
    * Updates a computer or phone from data received from the Jamf API. The item must already exist in GLPI even if it isn't linked yet.
    * @param string $itemtype The GLPI itemtype.
    * @param int $items_id The GLPI item id.
    * @param array $data Array of data received from Jamf API.
    * @param bool $use_transaction True if a DB transaction should be used.
    * @return bool True if the update was successful.
    * @throws Exception Any exception that occurs during the update process.
    */
   public static function updateComputerOrPhoneFromArray($itemtype, $items_id, $data, $use_transaction = true)
   {
      global $DB;

      try {
         if ($use_transaction) {
            $DB->beginTransaction();
         }
         /** @var CommonDBTM $item */
         $item = new $itemtype();
         if (!$item->getFromDB($items_id)) {
            return false;
         }

         $sync = new self($item, 'PluginJamfMobileDevice', $data);
         $sync_result = $sync->syncGeneral()
            ->syncOS()
            ->syncSoftware()
            ->syncUser()
            ->syncPurchasing()
            ->syncExtensionAttributes()
            ->syncSecurity()
            ->syncGeneralJamf()
            ->finalizeSync();
         // Evaluate final sync result. If any errors exist, count as failure.
         // Any tasks that are still deferred are also counted as failures.
         $failed = array_keys($sync_result, [self::STATUS_ERROR, self::STATUS_DEFERRED]);
         if (count($failed) !== 0) {
            throw new RuntimeException('One or more sync actions failed [' + implode(', ', $failed) . ']');
         }

         if ($use_transaction) {
            $DB->commit();
         }
         return true;
      } catch (Exception $e) {
         Toolbox::logError($e->getMessage());
         if ($use_transaction) {
            $DB->rollBack();
         }
         throw $e;
      }
   }

   public static function syncMobileDevice(PluginJamfMobileDevice $mobiledevice): bool
   {
      $itemtype = $mobiledevice->fields['itemtype'];
      $item = new $itemtype();
      if (!$item->getFromDB($mobiledevice->fields['items_id'])) {
         Toolbox::logError("Attempted to sync non-existent $itemtype with ID {$mobiledevice->fields['items_id']}");
         return false;
      }
      $data = PluginJamfAPIClassic::getItems('mobiledevices', ['udid' => $mobiledevice->fields['udid']]);
      if (is_null($data)) {
         // API error or device no longer exists in Jamf
         return false;
      }
      try {
         return self::updateComputerOrPhoneFromArray($itemtype, $item->getID(), $data);
      } catch (Exception $e) {
         Toolbox::logError($e->getMessage());
         return false;
      }
   }

   public static function syncExtensionAttributeDefinitions()
   {
      $ext_attr = new PluginJamfExtensionAttribute();
      $all_attributes = PluginJamfAPIClassic::getItems('mobiledeviceextensionattributes');
      if (is_array($all_attributes)) {
         foreach ($all_attributes as $attribute) {
            $attr = PluginJamfAPIClassic::getItems('mobiledeviceextensionattributes', ['id' => $attribute['id']]);
            $input = [
               'jamf_id' => $attr['id'],
               'itemtype' => PluginJamfMobileDevice::getType(),
               'name' => $attr['name'],
               'description' => $attr['description'],
               'data_type' => $attr['data_type']
            ];
            $ext_attr->addOrUpdate($input);
         }
      }
   }

   public static function cronSyncJamf(CronTask $task)
   {
      global $DB;

      $config = PluginJamfConfig::getConfig();
      $mobiledevice = new PluginJamfMobileDevice();

      self::syncExtensionAttributeDefinitions();
      $iterator = $DB->request([
         'SELECT' => ['id'],
         'FROM' => PluginJamfMobileDevice::getTable(),
         'WHERE' => [
            new QueryExpression("sync_date < NOW() - INTERVAL {$config['sync_interval']} MINUTE")
         ]
      ]);
      if (!$iterator->count()) {
         return 0;
      }
      while ($data = $iterator->next()) {
         try {
            $mobiledevice->getFromDB($data['id']);
            $result = self::syncMobileDevice($mobiledevice);
            if ($result) {
               $task->addVolume(1);
            }
         } catch (PluginJamfRateLimitException $e1) {
            // We are making API calls too fast. Sleep for a bit, and we will re-sync this item on the next round.
            sleep(5);
         } catch (Exception $e2) {
            // Some other error
         }
      }
      return 1;
   }

   public static function cronImportJamf(CronTask $task)
   {
      global $DB;

      $jamf_devices = PluginJamfAPIClassic::getItems('mobiledevices');
      if (is_null($jamf_devices) || !count($jamf_devices)) {
         // API error or device no longer exists in Jamf
         return 0;
      }
      $imported = [];
      $iterator = $DB->request([
         'SELECT' => ['udid'],
         'FROM' => PluginJamfMobileDevice::getTable()
      ]);
      while ($data = $iterator->next()) {
         array_push($imported, $data['udid']);
      }
      $pending_iterator = $DB->request([
         'FROM' => 'glpi_plugin_jamf_imports'
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
            $itemtype = strpos($jamf_device['model_identifier'], 'iPhone') !== false ? ($config['itemtype_iphone'] ?? 'Phone') :
               (strpos($jamf_device['model_identifier'], 'AppleTV') !== false ? ($config['itemtype_appletv'] ?? 'Computer') :
               ($config['itemtype_ipad'] ?? 'Computer'));
            if (isset($config['autoimport']) && $config['autoimport']) {
               try {
                  $result = self::importMobileDevice($itemtype, $jamf_device['id']);
                  if ($result) {
                     $task->addVolume(1);
                  }
               } catch (PluginJamfRateLimitException $e1) {
                  // We are making API calls too fast. Sleep for a bit, and we will import this item on the next round.
                  sleep(5);
               } catch (Exception $e2) {
                  // Some other error
               }
            } else {
               if (array_key_exists($jamf_device['udid'], $pending_import)) {
                  // Already pending
               } else {
                  $DB->insert('glpi_plugin_jamf_imports', [
                     'jamf_items_id' => $jamf_device['id'],
                     'name' => $DB->escape($jamf_device['name']),
                     'type' => $itemtype,
                     'udid' => $jamf_device['udid'],
                     'date_discover' => $_SESSION['glpi_currenttime']
                  ]);
               }
            }
         }
      }
      return 1;
   }
}
