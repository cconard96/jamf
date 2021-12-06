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

use CJDevStudios\ArrayMapper\Mapper;
use CJDevStudios\ArrayMapper\MappingRule;
use CJDevStudios\ArrayMapper\MappingSchema;
use Glpi\Agent\Communication\AbstractRequest;

/**
 * Adapter for converting data from the JSS API to the GLPI Inventory Format
 */
class PluginJamfInventoryAdapter implements PluginJamfInventoryMutatorInterface {

   private array $source_data;

   private bool $is_mobile;

   private string $manufacturer;

   private array $config;

   public function __construct($source_data) {
      global $DB;

      $this->source_data = $source_data;
      $this->is_mobile = $this->source_data['_metadata']['jamf_type'] === 'MobileDevice';

      $this->config = \Config::getConfigurationValues('plugin:jamf');

      $this->manufacturer = 'Apple Inc.';
      if (!empty($this->config['default_manufacturer'])) {
         $it = $DB->request([
            'SELECT' => ['name'],
            'FROM'   => Manufacturer::getTable(),
            'WHERE'  => ['id' => $this->config['default_manufacturer']]
         ]);
         if (count($it)) {
            $this->manufacturer = $it->current()['name'];
         }
      }
   }

   public function getManufacturer(): string {
      return $this->manufacturer;
   }

   public function getDeviceID(): string {
      return $this->source_data['_metadata']['jamf_type'].'_'.$this->source_data['general']['udid'];
   }

   public function getGlpiItemtype(): string {
      return $this->source_data['_metadata']['itemtype'];
   }

   public function getIsPartial(): bool {
      return false;
   }

   public function getVersionClient(): string {
      $plugin_version = PLUGIN_JAMF_VERSION;
      $version = "Jamf-Plugin_v{$plugin_version}";
      if (isset($this->source_data['_metadata']['jss_version_name'])) {
         $version .= ';'.$this->source_data['_metadata']['jss_version_name'];
      }
      return $version;
   }

   public function getVersionProvider(): array {
      return [];
   }

   public function getAntivirusData(): ?array {
      return null;
   }

   public function getBatteriesData(): ?array {
      return null;
   }

   public function getBiosData(): ?array {

      $bios = [
         'mmanufacturer' => $this->manufacturer,
         'smanufacturer' => $this->manufacturer,
      ];
      if (!$this->is_mobile) {
         $bios['bmanufacturer'] = $this->manufacturer;
      }

      $schema = new MappingSchema();
      $schema->addMappingRule(new MappingRule('general.serial_number', 'msn'));
      $schema->addMappingRule(new MappingRule('general.serial_number', 'ssn'));
      if (!$this->is_mobile) {
         $schema->addMappingRule(new MappingRule('hardware.boot_rom', 'bversion'));
         $schema->addMappingRule(new MappingRule('general.serial_number', 'biosserial'));
         $schema->addMappingRule(new MappingRule('hardware.model', 'mmodel'));
      } else {
         $schema->addMappingRule(new MappingRule('general.model', 'mmodel'));
      }

      $mapper = new Mapper($schema);
      $bios = array_merge($bios, $mapper->map($this->source_data));

      return $bios;
   }

   public function getControllersData(): ?array {
      return null;
   }

   public function getCpusData(): ?array {
      if ($this->is_mobile) {
         return null;
      }
      $schema = new MappingSchema();
      $schema->addMappingRule(new MappingRule('hardware.processor_architecture', 'items.0.arch'));
      $schema->addMappingRule(new MappingRule('hardware.number_cores', 'items.0.corecount'));
      $schema->addMappingRule(new MappingRule('hardware.processor_type', 'items.0.name'));
      $schema->addMappingRule(new MappingRule('hardware.processor_speed_mhz', 'items.0.speed'));
      $schema->addMappingRule(new MappingRule('hardware.cache_size_kb', 'items.0.cache'));
      $schema->addMappingRule(
         (new MappingRule('hardware.processor_type', 'items.0.manufacturer'))
            ->setTransform(function ($value) {
               return str_contains($value, 'Intel') ? 'Intel' : $this->manufacturer;
      }));

      $mapper = new Mapper($schema);
      return $mapper->map($this->source_data);
   }

   public function getDrivesData(): ?array {
      return null;
   }

   public function getEnvsData(): ?array {
      return null;
   }

   public function getFirewallsData(): ?array {
      return null;
   }

   public function getHardwareData(): ?array {
      $hardware = [
         'name'   => $this->source_data['general']['name'],
         'uuid'   => $this->source_data['general']['udid'],
      ];
      if (!$this->is_mobile) {
         // Mobile devices don't report RAM information
         $hardware['memory'] = $this->source_data['hardware']['total_ram_mb'];
      }

      return $hardware;
   }

   public function getInputsData(): ?array {
      return null;
   }

   public function getLocalGroupsData(): ?array {
      return null;
   }

   public function getLocalUsersData(): ?array {
      $users = [];
      if (!$this->is_mobile && isset($this->source_data['group_accounts']['local_accounts'])) {
         foreach ($this->source_data['group_accounts']['local_accounts'] as $account) {
            if (isset($account['user'])) {
               $users[] = [
                  'login'  => $account['user']['name'],
                  'name'   => $account['user']['real_name'],
                  'id'     => $account['user']['uid'],
                  'home'   => $account['user']['home'],
               ];
            }
         }
         return $users;
      }
      return null;
   }

   public function getPhysicalVolumesData(): ?array {
      return null;
   }

   public function getMemoriesData(): ?array {
      return null;
   }

   public function getMonitorsData(): ?array {
      return null;
   }

   public function getNetworksData(): ?array {
      return null;
   }

   public function getOperatingSystemData(): ?array {
      if ($this->is_mobile) {
         $os = [
            'name'      => $this->source_data['general']['os_type'],
            'version'   => $this->source_data['general']['os_version'],
         ];
      } else {
         $os = [
            'name'      => $this->source_data['hardware']['os_name'],
            'version'   => $this->source_data['hardware']['os_version'],
         ];
         if ($this->source_data['hardware']['active_directory_status']) {
            $os['dns_domain'] = $this->source_data['hardware']['active_directory_status'];
            $os['fqdn'] = $os['dns_domain'];
         }
      }
      $os['full_name'] = $os['name'] . ' ' . $os['version'];

      return $os;
   }

   public function getPortsData(): ?array {
      return null;
   }

   public function getPrintersData(): ?array {
      $printers = [];
      if (!$this->is_mobile && $this->source_data['hardware']['mapped_printers']) {
         foreach ($this->source_data['hardware']['mapped_printers'] as $printer) {
            $printers[] = [
               'name'         => $printer['name'],
               'network'      => strtolower($printer['location']) !== strtolower($this->source_data['general']['name']),
               'description'  => $printer['type']
            ];
         }
         return $printers;
      }
      return null;
   }

   public function getProcessesData(): ?array {
      return null;
   }

   public function getRemoteManagementData(): ?array {
      return null;
   }

   public function getSlotsData(): ?array {
      return null;
   }

   public function getSoftwaresData(): ?array {
      return null;
   }

   public function getSoundsData(): ?array {
      return null;
   }

   public function getStoragesData(): ?array {
      return null;
   }

   public function getUsbDevicesData(): ?array {
      return null;
   }

   public function getUsersData(): ?array {
      return null;
   }

   public function getVideosData(): ?array {
      return null;
   }

   public function getVirtualMachinesData(): ?array {
      return null;
   }

   public function getVolumeGroupsData(): ?array {
      return null;
   }

   public function getLicenseInfosData(): ?array {
      return null;
   }

   public function getModemsData(): ?array {
      return null;
   }

   public function getFirmwaresData(): ?array {
      return null;
   }

   public function getSimcardsData(): ?array {
      return null;
   }

   public function getSensorsData(): ?array {
      return null;
   }

   public function getPowerSuppliesData(): ?array {
      return null;
   }

   public function getCamerasData(): ?array {
      return null;
   }

   public function getNetworkPortsData(): ?array {
      return null;
   }

   public function getNetworkComponentsData(): ?array {
      return null;
   }

   public function getPageCountersData(): ?array {
      return null;
   }

   public function getCartridgesData(): ?array {
      return null;
   }

   public function getConsumablesData(): ?array {
      return null;
   }

   public function getNetworkDevicesData(): ?array {
      return null;
   }

   public function getDatabaseServicesData(): ?array {
      return null;
   }

   public function getInventoryDate(): string {
      return PluginJamfToolbox::utcToLocal($this->source_data['general']['last_inventory_update_utc']);
   }

   public function getGlpiInventoryData(): array {

      $result = [
         'itemtype'  => $this->getGlpiItemtype(),
         'action'    => AbstractRequest::INVENT_ACTION,
         'deviceid'  => $this->getDeviceID(),
         'partial'   => $this->getIsPartial(),
         'content'   => [
            'accesslog' => [
               'logdate'   => $this->getInventoryDate()
            ],
            'antivirus'          => $this->getAntivirusData(),
            'batteries'          => $this->getBatteriesData(),
            'bios'               => $this->getBiosData(),
            'cameras'            => $this->getCamerasData(),
            'cartridges'         => $this->getCartridgesData(),
            'controllers'        => $this->getControllersData(),
            'consumables'        => $this->getConsumablesData(),
            'cpus'               => $this->getCpusData(),
            'databases_services' => $this->getDatabaseServicesData(),
            'drives'             => $this->getDrivesData(),
            'envs'               => $this->getEnvsData(),
            'firewalls'          => $this->getFirewallsData(),
            'firmwares'          => $this->getFirmwaresData(),
            'hardware'           => $this->getHardwareData(),
            'inputs'             => $this->getInputsData(),
            'licenseinfos'       => $this->getLicenseInfosData(),
            'local_groups'       => $this->getLocalGroupsData(),
            'local_users'        => $this->getLocalUsersData(),
            'modems'             => $this->getModemsData(),
            'monitors'           => $this->getMonitorsData(),
            'network_components' => $this->getNetworkComponentsData(),
            'network_device'     => $this->getNetworkDevicesData(),
            'network_ports'      => $this->getNetworkPortsData(),
            'networks'           => $this->getNetworksData(),
            'operatingsystem'    => $this->getOperatingSystemData(),
            'pagecounters'       => $this->getPageCountersData(),
            'physical_volumes'   => $this->getPhysicalVolumesData(),
            'ports'              => $this->getPortsData(),
            'powersupplies'      => $this->getPowerSuppliesData(),
            'printers'           => $this->getPrintersData(),
            'processes'          => $this->getProcessesData(),
            'remote_mgmt'        => $this->getRemoteManagementData(),
            'sensors'            => $this->getSensorsData(),
            'simcards'           => $this->getSimcardsData(),
            'slots'              => $this->getSlotsData(),
            'softwares'          => $this->getSoftwaresData(),
            'sounds'             => $this->getSoundsData(),
            'storages'           => $this->getStoragesData(),
            'usbdevices'         => $this->getUsbDevicesData(),
            'users'              => $this->getUsersData(),
            'videos'             => $this->getVideosData(),
            'virtualmachines'    => $this->getVirtualMachinesData(),
            'versionclient'      => $this->getVersionClient(),
            'versionprovider'    => $this->getVersionProvider(),
            'volume_groups'      => $this->getVolumeGroupsData(),
         ]
      ];

      $original_content_count = count($result['content']);
      $result['content'] = array_filter($result['content'], static function($v) {
         return $v !== null;
      });
      if (count($result['content']) !== $original_content_count) {
         $result['partial'] = true;
      }

      return $result;
   }
}
