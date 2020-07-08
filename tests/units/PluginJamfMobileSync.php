<?php

/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019-2020 by Curtis Conard
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

namespace tests\units;

class PluginJamfMobileSync extends \DbTestCase {

   public function testDiscover()
   {
      global $DB;

      \PluginJamfMobileTestSync::discover();

      $iterator = $DB->request([
         'FROM'   => \PluginJamfImport::getTable()
      ]);

      $this->integer($iterator->count())->isEqualTo(5);
   }

   public function testSyncExtensionAttributeDefinitions()
   {
      global $DB;

      \PluginJamfMobileTestSync::syncExtensionAttributeDefinitions();

      $iterator = $DB->request([
         'FROM'   => \PluginJamfExtensionAttribute::getTable(),
         'WHERE'  => [
            'jamf_type' => 'MobileDevice'
         ]
      ]);
      $this->integer($iterator->count())->isEqualTo(2);

      // Make sure syncing again does not cause duplicates
      \PluginJamfMobileTestSync::syncExtensionAttributeDefinitions();

      $iterator = $DB->request([
         'FROM'   => \PluginJamfExtensionAttribute::getTable(),
         'WHERE'  => [
            'jamf_type' => 'MobileDevice'
         ]
      ]);
      $this->integer($iterator->count())->isEqualTo(2);
   }

   public function testImportAsComputer()
   {
      global $DB;

      // Force sync extension attribute definitions
      \PluginJamfMobileTestSync::syncExtensionAttributeDefinitions();

      \PluginJamfMobileTestSync::import('Computer', 28, false);

      // Make sure the computer was created
      $iterator = $DB->request([
         'FROM'   => \Computer::getTable(),
         'WHERE'  => [
            'name'   => 'Device 2'
         ]
      ]);
      $this->integer($iterator->count())->isEqualTo(1);
      $item = $iterator->next();

      // Make sure the new computer is linked properly
      $link_iterator = $DB->request([
         'FROM'   => \PluginJamfMobileDevice::getTable(),
         'WHERE'  => [
            'itemtype'  => 'Computer',
            'items_id'  => $item['id']
         ]
      ]);
      $this->integer($link_iterator->count())->isEqualTo(1);
      $link = $link_iterator->next();
      $this->string($link['udid'])->isEqualTo('ca44c88e60a311e490b812df261f2c7e');
      $this->integer($link['managed'])->isEqualTo(1);
      $this->integer($link['supervised'])->isEqualTo(0);
      $this->integer($link['activation_lock_enabled'])->isEqualTo(1);
      $this->string($link['lost_mode_enabled'])->isEqualTo('Unsupervised Device');

      $ext_attr_iterator = $DB->request([
         'FROM'   => \PluginJamfItem_ExtensionAttribute::getTable(),
         'WHERE'  => [
            'itemtype'  => \PluginJamfMobileDevice::class,
            'items_id'  => $link['id']
         ]
      ]);
      $this->integer($ext_attr_iterator->count())->isEqualTo(1);
   }

   public function testImportAsPhone()
   {
      global $DB;
      \PluginJamfMobileTestSync::import('Phone', 28, false);

      // Make sure the phone was created
      $iterator = $DB->request([
         'FROM'   => \Phone::getTable(),
         'WHERE'  => [
            'name'   => 'Device 2'
         ]
      ]);
      $this->integer($iterator->count())->isEqualTo(1);
      $item = $iterator->next();

      // Make sure the new phone is linked properly
      $link_iterator = $DB->request([
         'FROM'   => \PluginJamfMobileDevice::getTable(),
         'WHERE'  => [
            'itemtype'  => 'Phone',
            'items_id'  => $item['id']
         ]
      ]);
      $this->integer($link_iterator->count())->isEqualTo(1);
      $link = $link_iterator->next();
      $this->integer($link['jamf_items_id'])->isEqualTo(28);

      $ext_iterator = $DB->request([
         'FROM'   => \PluginJamfExtField::getTable(),
         'WHERE'  => [
            'itemtype'  => 'Phone',
            'items_id'  => $item['id'],
            'name'      => 'uuid'
         ]
      ]);
      $this->integer($ext_iterator->count())->isEqualTo(1);
      $ext_field = $ext_iterator->next();
      $this->string($ext_field['value'])->isEqualTo('ca44c88e60a311e490b812df261f2c7e');
   }

   public function deviceSyncEnginesProvider()
   {
      $engines = \PluginJamfSync::getDeviceSyncEngines();
      $result = [];
      foreach ($engines as $device_class => $sync_class) {
         $result[] = [
            $device_class,
            $sync_class
         ];
      }
      return $result;
   }

   /**
    * @dataProvider deviceSyncEnginesProvider
    */
   public function testGetDeviceSyncEngineItem($device_class, $sync_class)
   {
      $rdc = new \ReflectionClass($device_class);
      $this->boolean($rdc->getParentClass()->getName() === \PluginJamfAbstractDevice::class)->isTrue();
      $rsc = new \ReflectionClass($sync_class);
      $this->boolean($rsc->isSubclassOf(\PluginJamfSync::class))->isTrue();
   }
}