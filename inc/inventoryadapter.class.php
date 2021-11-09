<?php
/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019-2021 by Curtis Conard
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

use Glpi\Agent\Communication\AbstractRequest;

/**
 * Adapter for converting data from the JSS API to the GLPI Inventory Format
 */
class PluginJamfInventoryAdapter {
   private $source_data;

   public function __construct($source_data) {
      $this->source_data = $source_data;
   }

   private function getDeviceID(): string {
      return $this->source_data['_metadata']['jamf_type'].'_'.$this->source_data['general']['udid'];
   }

   private function getVersionClient(): string {
      $plugin_version = PLUGIN_JAMF_VERSION;
      $version = "Jamf-Plugin_v{$plugin_version}";
      if (isset($this->source_data['_metadata']['jss_version_name'])) {
         $version .= ';'.$this->source_data['_metadata']['jss_version_name'];
      }
      return $version;
   }

   public function getGlpiInventoryData(): array {
      global $DB;

      $source = $this->source_data;
      $config = \Config::getConfigurationValues('plugin:jamf');

      $manufacturer = 'Apple Inc.';
      if (!empty($config['default_manufacturer'])) {
         $it = $DB->request([
            'SELECT' => ['name'],
            'FROM'   => Manufacturer::getTable(),
            'WHERE'  => ['id' => $config['default_manufacturer']]
         ]);
         if (count($it)) {
            $manufacturer = $it->current()['name'];
         }
      }

      $result = [
         'itemtype'  => $source['_metadata']['itemtype'],
         'action'    => AbstractRequest::INVENT_ACTION,
         'deviceid'  => $this->getDeviceID(),
         'content'   => [
            'accesslog' => [
               'logdate'   => PluginJamfToolbox::utcToLocal($source['general']['last_inventory_update_utc'])
            ],
            'versionclient'   => $this->getVersionClient(),
         ]
      ];

      $is_mobile = $source['_metadata']['jamf_type'] === 'MobileDevice';

      // Antivirus
      // There are more layers of security than a traditional antivirus software on MacOS.

      // Batteries
      // Nothing has specific battery hardware info. All we have is a battery level.

      // BIOS
      $bios = [
         'mmanufacturer'   => $manufacturer,
         'msn'             => $source['general']['serial_number'],
         'smanufacturer'   => $manufacturer,
         'ssn'             => $source['general']['serial_number']
      ];
      if (!$is_mobile) {
         $bios['bmanufacturer'] = $manufacturer;
         $bios['bversion'] = $source['hardware']['boot_rom'];
         $bios['biosserial'] = $source['general']['serial_number'];
         $bios['mmodel'] = $source['hardware']['model'];
      } else {
         $bios['mmodel'] = $source['general']['model'];
      }

      // Controllers
      // Not supported

      // CPUs
      $cpus = [];
      if (!$is_mobile) {
         $cpus = [
            'items'  => [
               [
                  'arch'         => $source['hardware']['processor_architecture'],
                  'corecount'    => $source['hardware']['number_cores'],
                  'name'         => $source['hardware']['processor_type'],
                  'manufacturer' => str_contains($source['hardware']['processor_type'], 'Intel') ? 'Intel' : $manufacturer,
                  'speed'        => $source['hardware']['processor_speed_mhz'],
                  'cache'        => $source['hardware']['cache_size_kb'],
               ]
            ]
         ];
      }

      // Drives
      //TODO

      // Envs
      // Not supported

      // Firewalls
      // Not supported

      // Hardware
      $hardware = [
         'name'   => $source['general']['name'],
         'uuid'   => $source['general']['udid'],
      ];
      if (!$is_mobile) {
         // Mobile devices don't report RAM information
         $hardware['memory'] = $source['hardware']['total_ram_mb'];
      }

      // Inputs
      //TODO Maybe not supported

      // License Info
      // Not supported

      // Logical Volumes

      // Memory

      // Monitors/Displays

      // Networks

      // Operating System

      // Physical Volumes

      // Ports

      // Printers

      // Processes

      // Remote Management

      // Slots

      // Softwares

      // Sounds

      // Storage

      // USB Devices

      // Users
      $users = [];
      if (!$is_mobile && isset($source['group_accounts']['local_accounts'])) {
         $hardware['users'] = [];
         foreach ($source['group_accounts']['local_accounts'] as $account) {
            if (isset($account['user'])) {
               $users[] = [
                  'login'  => $account['user']['name'],
                  'name'   => $account['user']['real_name'],
                  'id'     => $account['user']['uid'],
                  'home'   => $account['user']['home'],
               ];
            }
         }
      }

      // Videos (Graphical adapters including virtual ones)

      // Volume Groups

      $result['content']['bios']          = $bios;
      $result['content']['cpus']          = $cpus;
      $result['content']['hardware']      = $hardware;
      $result['content']['local_users']   = $users;
      return $result;
   }
}
