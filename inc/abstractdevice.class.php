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
 * Abstraction for all Jamf device types (Mobile device and Computer for now)
 * @since 2.0.0
 */
abstract class PluginJamfAbstractDevice extends CommonDBChild
{
   static public $itemtype = 'itemtype';
   static public $items_id = 'items_id';

   /**
    * Display the extra information for Jamf devices on the main Computer or Phone tab.
    * @param array $params
    * @return void|bool Displays HTML only if a supported item is in the params parameter. If there is any issue, false is returned.
    * @since 1.0.0
    * @since 2.0.0 Renamed from showForComputerOrPhoneMain to showForItem
    */
   abstract public static function showForItem(array $params);

   /**
    * Get a direct link to the device on the Jamf server.
    * @param int $jamf_id The Jamf ID of the device.
    * @return string Jamf URL for the mobile device.
    */
   abstract public static function getJamfDeviceUrl(int $jamf_id): string;

   /**
    * Cleanup relations when an item is purged.
    * @global type $DB
    * @param CommonDBTM $item
    */
   private static function purgeItemCommon(CommonDBTM $item)
   {
      global $DB;

      $DB->delete(static::getTable(), [
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()
      ]);
   }

   /**
    * Cleanup relations when a Computer is purged.
    * @param Computer $item
    * @global DBmysql $DB
    */
   public static function plugin_jamf_purgeComputer(Computer $item)
   {
      static::purgeItemCommon($item);
   }

   /**
    * Cleanup relations when a Phone is purged.
    * @param Phone $item
    * @global DBmysql $DB
    */
   public static function plugin_jamf_purgePhone(Phone $item)
   {
      global $DB;

      static::purgeItemCommon($item);
      $DB->delete(Item_OperatingSystem::getTable(), [
         'itemtype' => $item::getType(),
         'items_id' => $item->getID()
      ]);
   }

   static function preUpdatePhone($item) {
      if (isset($item->input['_plugin_jamf_uuid'])) {
         PluginJamfExtField::setValue($item::getType(), $item->getID(), 'uuid', $item->input['_plugin_jamf_uuid']);
      }
   }

   public static function getJamfItemForGLPIItem(CommonDBTM $item): ?PluginJamfAbstractDevice
   {
      $device = new static();
      $matches = $device->find([
         'itemtype'   => $item::getType(),
         'items_id'   => $item->getID()
      ], [], 1);
      if (count($matches)) {
         $id = reset($matches)['id'];
         $device->getFromDB($id);
         return $device;
      }
      return null;
   }

   public function getGLPIItem() {
      $itemtype = $this->fields['itemtype'];
      $item = new $itemtype();
      $item->getFromDB($this->fields['items_id']);
      return $item;
   }

   abstract public function getMDMCommands();
}