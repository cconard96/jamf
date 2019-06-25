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

function plugin_jamf_install()
{
   global $DB;

   // Check imports table (Used to store newly discovered devices that haven't been imported yet)
   if (!$DB->tableExists('glpi_plugin_jamf_imports')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_imports` (
                  `id` int(11) NOT NULL auto_increment,
                  `jamf_items_id` int(11) NOT NULL,
                  `type` varchar(100) NOT NULL,
                  `udid` varchar(100) NOT NULL,
                  `date_discover` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`jamf_items_id`,`type`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin imports table' . $DB->error());
   }

   // Check mobile devices table (Extra data for mobile devices)
   if (!$DB->tableExists('glpi_plugin_jamf_mobiledevices')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_mobiledevices` (
                  `id` int(11) NOT NULL auto_increment,
                  `items_id` int(11) NOT NULL,
                  `itemtype` varchar(100) NOT NULL,
                  `udid` varchar(100) NOT NULL,
                  `last_inventory` datetime NULL,
                  `entry_date` datetime NULL,
                  `enroll_date` datetime NULL,
                  `import_date` datetime NULL,
                  `sync_date` datetime NULL,
                  `managed` tinyint(1) NOT NULL DEFAULT '0',
                  `supervised` tinyint(1) NOT NULL DEFAULT '0',
                  `shared` varchar(100) NOT NULL DEFAULT '',
                  `cloud_backup_enabled` tinyint(1) DEFAULT '0',
                  `activation_lock_enabled` tinyint(1) DEFAULT '0',
                  `lost_mode_enabled` varchar(255) DEFAULT 'Unknown',
                  `lost_mode_enforced` tinyint(1) DEFAULT '0',
                  `lost_mode_enable_date` datetime NULL,
                  `lost_mode_message` varchar(255) DEFAULT NULL,
                  `lost_mode_phone` varchar(100) DEFAULT NULL,
                  `lost_location_latitude` varchar(100) DEFAULT '',
                  `lost_location_longitude` varchar(100) DEFAULT '',
                  `lost_location_altitude` varchar(100) DEFAULT '',
                  `lost_location_speed` varchar(100) DEFAULT '',
                  `lost_location_date` datetime NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`computers_id`),
                KEY `udid` (`udid`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin imports table' . $DB->error());
   }

   CronTask::register('PluginJamfSync', 'syncJamf', 900, [
      'state'        => 0,
      'allowmode'    => 3,
      'logslifetime' => 30,
      'comment'      => "Sync devices with Jamf that are already imported"
   ]);
   CronTask::register('PluginJamfSync', 'importJamf', 900, [
      'state'        => 0,
      'allowmode'    => 3,
      'logslifetime' => 30,
      'comment'      => "Import or discover devices in Jamf that are not already imported"
   ]);
   return true;
}

function plugin_jamf_uninstall()
{
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_imports');
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_mobiledevices');
   return true;
}

function plugin_jamf_getDatabaseRelations()
{
   $plugin = new Plugin();
   if ($plugin->isActivated('jamf')) {
      return [
         'glpi_softwares' => [
            'glpi_plugin_jamf_softwares' => 'softwares_id'
         ],
         'glpi_computers' => [
            'glpi_plugin_jamf_mobiledevices' => 'computers_id',
         ]
      ];
   }
   return [];
}

function plugin_jamf_getAddSearchOptions($itemtype) {
   $opt = [];
   $plugin = new Plugin();
   if ($plugin->isActivated('jamf')) {
      if ($itemtype == 'Computer') {
         $opt = [
            '22002' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'last_inventory',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Last inventory', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22003' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'entry_date',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Import date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22004' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'enroll_date',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Enrollment date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22005' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'managed',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Managed', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22006' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'supervised',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Supervised', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22007' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'cloud_backup_enabled',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Cloud backup enabled', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22008' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'activation_lock_enabled',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Activation lock enabled', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22009' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_enabled',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode enabled', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22010' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_enforced',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode enforced', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22011' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_enable_date',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode enable date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22012' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_message',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode message', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22013' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_phone',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode phone', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22014' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_latitude',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode latitude', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22015' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_longitude',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode longitude', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22016' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_altitude',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode altitude', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22017' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_speed',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode speed', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ],
            '22018' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_date',
               'name'            => __('Jamf', 'jamf'). ' - ' .__('Lost mode location date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'child']
            ]
         ];
      }
   }
   return $opt;
}