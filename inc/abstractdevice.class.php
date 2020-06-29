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
   static public $jamftype_name = null;

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
    * @global DBmysql $DB
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

   public static function getJamfItemClassForGLPIItem(string $itemtype, $items_id): ?string
   {
      global $DB;

      $computer_query = [
         'SELECT'    => [
            new QueryExpression('"Computer" AS jamf_type')
         ],
         'FROM'      => PluginJamfComputer::getTable(),
         'WHERE'     => [
            'itemtype'  => $itemtype,
            'items_id'  => $items_id
         ]
      ];
      $mobiledevice_query = [
         'SELECT'    => [
            new QueryExpression('"MobileDevice" AS jamf_type')
         ],
         'FROM'      => PluginJamfMobileDevice::getTable(),
         'WHERE'     => [
            'itemtype'  => $itemtype,
            'items_id'  => $items_id
         ]
      ];
      $iterator = $DB->request(new QueryUnion([
         $computer_query,
         $mobiledevice_query
      ]));
      if (count($iterator)) {
         $jamf_type = $iterator->next()['jamf_type'];
         if ($jamf_type === 'Computer') {
            return PluginJamfComputer::class;
         }

         if ($jamf_type === 'MobileDevice') {
            return PluginJamfMobileDevice::class;
         }
      }

      return null;
   }

   public static function getJamfItemForGLPIItem(CommonDBTM $item, $limit_to_type = false): ?PluginJamfAbstractDevice
   {
      global $DB;

      $found_type = static::class;
      $found_id = null;

      if (!$limit_to_type) {
         $computer_query = [
            'SELECT'    => [
               new QueryExpression('"Computer" AS jamf_type'),
               'id',
               'itemtype',
               'items_id',
               'jamf_items_id'
            ],
            'FROM'      => PluginJamfComputer::getTable(),
            'WHERE'     => [
               'itemtype'  => $item::getType(),
               'items_id'  => $item->getID()
            ]
         ];
         $mobiledevice_query = [
            'SELECT'    => [
               new QueryExpression('"MobileDevice" AS jamf_type'),
               'id',
               'itemtype',
               'items_id',
               'jamf_items_id'
            ],
            'FROM'      => PluginJamfMobileDevice::getTable(),
            'WHERE'     => [
               'itemtype'  => $item::getType(),
               'items_id'  => $item->getID()
            ]
         ];
         $iterator = $DB->request(new QueryUnion([
            $computer_query,
            $mobiledevice_query
         ]));
         if (count($iterator)) {
            $jamf_data = $iterator->next();
            if ($jamf_data['jamf_type'] === 'Computer') {
               $found_type = PluginJamfComputer::class;
               $found_id = $jamf_data['id'];
            } else if ($jamf_data['jamf_type'] === 'MobileDevice') {
               $found_type = PluginJamfMobileDevice::class;
               $found_id = $jamf_data['id'];
            }
         }
      }

      if ($found_id !== null) {
         $device = new $found_type();
         $device->getFromDB($found_id);
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