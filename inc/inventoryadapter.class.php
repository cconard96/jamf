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
      $result = [
         'itemtype'  => $this->source_data['_metadata']['itemtype'],
         'query'     => 'INVENTORY',
         'deviceid'  => $this->getDeviceID(),
         'content'   => [
            'accesslog' => [
               'logdate'   => PluginJamfToolbox::utcToLocal($this->source_data['general']['last_inventory_update_utc'])
            ]
         ]
      ];

      // Batteries

      // BIOS

      // Controllers

      // CPUs

      // Drives

      // Hardware

      // Inputs

      // Logical Volumes

      // Memory

      // Monitors/Displays

      // Networks

      // Operating System

      // Physical Volumes

      // Ports

      // Printers

      // Slots

      // Softwares

      // Sounds

      // Storage

      // USB Devices

      // Users

      // Version Client

      // Version Provider

      // Volume Groups

      return $result;
   }
}
