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
class JamfSync extends CommonGLPI {

   public static function cronJamfSync() {
      
   }

   public static function importDevice(string $devicetype, int $jamf_items_id) : bool
   {
      $computer = new Computer();
      if ($devicetype == 'ios' || $devicetype == 'mobile') {
         $jamf_items = JamfAPIClassic::getItems('mobiledevices', ['id' => $jamf_items_id]);
         $jamf_item = $jamf_items[0];
         $matches = $computer->find(['uuid' => $jamf_item['udid']]);
      }
   }

   public static function syncDevice(string $devicetype, int $device_id) : bool
   {
      global $DB;

      $iterator = $DB->request([
         'SELECT'    => [
            'sync_*',
            'audit_mode',
            'user_sync_mode',
            'enroll_status',
            'lost_status',
            'mobiledevice_type',
            'autoimport'
         ],
         'FROM'      => 'glpi_plugin_jamf_configs',
         'WHERE'     => ['id' => 1]
      ]);

      if (!count($iterator)) {
         return false;
      }
      $config = $iterator->next();

      if ($devicetype == 'ios' || $devicetype == 'mobile') {
         $mobiledevice = new JamfMobileDevice();
         if ($config['sync_general']) {

         }

         if ($config['sync_software']) {
            
         }

         if ($config['sync_os']) {
            
         }

         if ($config['sync_financial']) {
            
         }

         if ($config['sync_components']) {
            
         }

         if ($config['sync_user']) {
            
         }
      }
   }
}
