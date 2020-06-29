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

abstract class PluginJamfDeviceSync extends PluginJamfSync {

   /**
    * Sync general information such as name, serial number, etc.
    * All synced fields here are on the main GLPI item and not a plugin item type.
    * @return PluginJamfDeviceSync
    * @since 2.0.0
    */
   protected function syncGeneral(): PluginJamfDeviceSync
   {
      $this->status['syncGeneral'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync operating system information.
    * @return PluginJamfDeviceSync
    * @since 2.0.0
    */
   protected function syncOS(): PluginJamfDeviceSync
   {
      $this->status['syncOS'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync software information.
    * @return PluginJamfDeviceSync
    * @since 2.0.0
    */
   protected function syncSoftware(): PluginJamfDeviceSync
   {
      $this->status['syncSoftware'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync user information.
    * @return PluginJamfDeviceSync
    * @since 2.0.0
    */
   protected function syncUser(): PluginJamfDeviceSync
   {
      $this->status['syncUser'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync purchasing information.
    * @return PluginJamfDeviceSync
    * @since 2.0.0
    */
   protected function syncPurchasing(): PluginJamfDeviceSync
   {
      $this->status['syncPurchasing'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync extension attributes. This task will be deferred if run for a device that was not previously imported.
    * @return PluginJamfDeviceSync
    * @since 1.1.0
    */
   protected function syncExtensionAttributes(): PluginJamfDeviceSync
   {
      $this->status['syncExtensionAttributes'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync security information.
    * @return PluginJamfDeviceSync
    * @since 2.0.0
    */
   protected function syncSecurity(): PluginJamfDeviceSync
   {
      $this->status['syncSecurity'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync network information.
    * @return PluginJamfDeviceSync
    * @since 2.0.0
    */
   protected function syncNetwork(): PluginJamfDeviceSync
   {
      $this->status['syncNetwork'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync general Jamf device information. All changes are made for the Jamf plugin item only. No GLPI item changes are made here.
    * @since 1.1.0
    * @return PluginJamfDeviceSync
    */
   protected function syncGeneralJamfPluginItem(): PluginJamfDeviceSync
   {
      $this->status['syncGeneralJamfPluginItem'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   /**
    * Sync component information such as volumes.
    * @since 2.0.0
    * @return PluginJamfDeviceSync
    */
   protected function syncComponents(): PluginJamfDeviceSync
   {
      $this->status['syncComponents'] = self::STATUS_NOT_APPLICABLE;
      return $this;
   }

   public static function syncExtensionAttributeDefinitions(): void
   {
      switch (static::$jamf_itemtype) {
         case 'Computer':
            $api_itemtype = 'computerextensionattributes';
            break;
         case 'MobileDevice':
            $api_itemtype = 'mobiledeviceextensionattributes';
            break;
         default:
            $api_itemtype = null;
      }
      if ($api_itemtype === null) {
         return;
      }
      $ext_attr = new PluginJamfExtensionAttribute();
      $all_attributes = PluginJamfAPIClassic::getItems($api_itemtype);
      if (is_array($all_attributes)) {
         foreach ($all_attributes as $attribute) {
            $attr = PluginJamfAPIClassic::getItems($api_itemtype, ['id' => $attribute['id']]);
            $input = [
               'jamf_id'      => $attr['id'],
               'itemtype'     => static::$jamfplugin_itemtype::getType(),
               'jamf_type'    => static::$jamf_itemtype,
               'name'         => $attr['name'],
               'description'  => $attr['description'],
               'data_type'    => $attr['data_type']
            ];
            $ext_attr->addOrUpdate($input);
         }
      }
   }

   public static function syncAll(): int
   {
      global $DB;

      $volume = 0;

      $config = PluginJamfConfig::getConfig();

      static::syncExtensionAttributeDefinitions();
      $iterator = $DB->request([
         'SELECT' => ['itemtype', 'items_id'],
         'FROM' => (static::$jamfplugin_itemtype)::getTable(),
         'WHERE' => [
            new QueryExpression("sync_date < NOW() - INTERVAL {$config['sync_interval']} MINUTE")
         ]
      ]);
      if (!$iterator->count()) {
         return -1;
      }
      while ($data = $iterator->next()) {
         try {
            $result = self::sync($data['itemtype'], $data['items_id']);
            if ($result) {
               $volume++;
            }
         } catch (Exception $e2) {
            // Some other error
         }
      }
      return $volume;
   }

   /**
    * Updates a device from data received from the Jamf API. The item must already exist in GLPI and be linked.
    * @param string $itemtype
    * @param int $items_id
    * @param bool $use_transaction True if a DB transaction should be used.
    * @return bool True if the update was successful.
    * @throws Exception Any exception that occurs during the update process.
    */
   final public static function sync(string $itemtype, int $items_id, bool $use_transaction = true): bool
   {
      global $DB;

      $item = new $itemtype();

      if (!$item->getFromDB($items_id)) {
         Toolbox::logError("Attempted to sync non-existent $itemtype with ID {$items_id}");
         return false;
      }

      $data = static::getJamfDataForSyncingByGlpiItem($itemtype, $items_id);
      if ($data === null) {
         // API error or device no longer exists in Jamf
         return false;
      }

      try {
         if ($use_transaction) {
            $DB->beginTransaction();
         }

         $sync = new static($item, $data);
         $sync_result = $sync->syncGeneral()
            ->syncOS()
            ->syncSoftware()
            ->syncUser()
            ->syncPurchasing()
            ->syncExtensionAttributes()
            ->syncSecurity()
            ->syncNetwork()
            ->syncComponents()
            ->syncOther()
            ->syncGeneralJamfPluginItem()
            ->finalizeSync();
         // Evaluate final sync result. If any errors exist, count as failure.
         // Any tasks that are still deferred are also counted as failures.
         $failed = array_keys($sync_result, [self::STATUS_ERROR, self::STATUS_DEFERRED]);
         if (count($failed) !== 0) {
            throw new RuntimeException('One or more sync actions failed [' . implode(', ', $failed) . ']');
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
         return false;
      }
   }

   /**
    * Sync all other data not handled by the built-in {@link \PluginJamfDeviceSync} sync methods.
    *
    * @return PluginJamfDeviceSync
    * @since 1.0.0
    */
   protected function syncOther(): PluginJamfDeviceSync
   {
      return $this;
   }
}