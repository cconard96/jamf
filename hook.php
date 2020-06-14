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

function plugin_jamf_install()
{
   global $DB;

   $migration = new Migration(PLUGIN_JAMF_VERSION);
   // Check imports table (Used to store newly discovered devices that haven't been imported yet)
   if (!$DB->tableExists('glpi_plugin_jamf_imports')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_imports` (
                  `id` int(11) NOT NULL auto_increment,
                  `jamf_items_id` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
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
                UNIQUE KEY `unicity` (`itemtype`, `items_id`),
                KEY `udid` (`udid`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin imports table' . $DB->error());
   }

   // Check software table (Extra data for software)
   if (!$DB->tableExists('glpi_plugin_jamf_softwares')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_softwares` (
                  `id` int(11) NOT NULL auto_increment,
                  `softwares_id` int(11) NOT NULL,
                  `bundle_id` varchar(255) NOT NULL,
                  `itunes_store_url` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin software table' . $DB->error());
   }

    // Check extension attribute tables
    if (!$DB->tableExists('glpi_plugin_jamf_extensionattributes')) {
        $query = "CREATE TABLE `glpi_plugin_jamf_extensionattributes` (
                  `id` int(11) NOT NULL auto_increment,
                  `itemtype` varchar(100) NOT NULL,
                  `jamf_id` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `description` varchar(255) NOT NULL,
                  `data_type` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                UNIQUE KEY `jamf_id` (`jamf_id`, `itemtype`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $DB->queryOrDie($query, 'Error creating JAMF plugin extension attribute table' . $DB->error());
    }
    if (!$DB->tableExists('glpi_plugin_jamf_items_extensionattributes')) {
        $query = "CREATE TABLE `glpi_plugin_jamf_items_extensionattributes` (
                  `id` int(11) NOT NULL auto_increment,
                  `itemtype` varchar(100) NOT NULL,
                  `items_id` int(11) NOT NULL,
                  `glpi_plugin_jamf_extensionattributes_id` int(11) NOT NULL,
                  `value` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `item` (`itemtype`, `items_id`),
                UNIQUE `unicity` (`itemtype`, `items_id`, `glpi_plugin_jamf_extensionattributes_id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $DB->queryOrDie($query, 'Error creating JAMF plugin item extension attribute table' . $DB->error());
    }
    if (!$DB->tableExists('glpi_plugin_jamf_extfields')) {
        $query = "CREATE TABLE `glpi_plugin_jamf_extfields` (
                  `id` int(11) NOT NULL auto_increment,
                  `itemtype` varchar(100) NOT NULL,
                  `items_id` int(11) NOT NULL,
                  `name` varchar(100) NOT NULL,
                  `value` varchar(255) DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `item` (`itemtype`, `items_id`),
                UNIQUE `unicity` (`itemtype`, `items_id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $DB->queryOrDie($query, 'Error creating JAMF plugin item extension field table' . $DB->error());
    }
    if (!$DB->tableExists('glpi_plugin_jamf_users_jssaccounts')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_users_jssaccounts` (
                  `id` int(11) NOT NULL auto_increment,
                  `users_id` int(11) NOT NULL,
                  `jssaccounts_id` int(11) NOT NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin jss account link table' . $DB->error());
   }

   $jamfconfig = Config::getConfigurationValues('plugin:Jamf');
   if (!count($jamfconfig)) {
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'jssserver',
         'value'     => ''
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'jssuser',
         'value'     => ''
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'jsspassword',
         'value'     => ''
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'sync_interval',
         'value'     => '120' // Sync devices every two hours
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'sync_general',
         'value'     => '0'
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'sync_os',
         'value'     => '0'
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'sync_software',
         'value'     => '0'
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'sync_financial',
         'value'     => '0'
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'sync_user',
         'value'     => '0'
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'user_sync_mode',
         'value'     => 'email'
      ]);
      $DB->insert('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'autoimport',
         'value'     => '0'
      ]);
   }

   // New Configs (post 1.0.0)
   // Allow choosing item types
   $migration->addConfig([
      'itemtype_iphone' => 'Phone',
      'itemtype_ipad' => 'Computer',
      'itemtype_appletv' => 'Computer'
   ]);

   if (!count(Config::getConfigurationValues('plugin:Jamf', ['default_status']))) {
      $DB->insertOrDie('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'default_status',
         'value'     => null
      ]);
   }

   if (!count(Config::getConfigurationValues('plugin:Jamf', ['sync_components']))) {
      $DB->insertOrDie('glpi_configs', [
         'context'   => 'plugin:Jamf',
         'name'      => 'sync_components',
         'value'     => 0
      ]);
   }
   // End of Post-release configs

   CronTask::register('PluginJamfSync', 'syncJamf', 300, [
      'state'        => 1,
      'allowmode'    => 3,
      'logslifetime' => 30,
      'comment'      => "Sync devices with Jamf that are already imported"
   ]);
   CronTask::register('PluginJamfSync', 'importJamf', 900, [
      'state'        => 1,
      'allowmode'    => 3,
      'logslifetime' => 30,
      'comment'      => "Import or discover devices in Jamf that are not already imported"
   ]);

   if (!$DB->fieldExists('glpi_plugin_jamf_mobiledevices', 'jamf_items_id', false)) {
      $migration->addField('glpi_plugin_jamf_mobiledevices', 'jamf_items_id', 'integer', ['default' => -1]);
      $migration->migrationOneTable('glpi_plugin_jamf_mobiledevices');
      $mobiledevice = new PluginJamfMobileDevice();
      // Find all devices that don't have the jamf id recorded, and retrieve it.
      $unassigned = $mobiledevice->find(['jamf_items_id' => -1]);
      foreach ($unassigned as $item) {
         $jamf_item = PluginJamfAPIClassic::getItems('mobiledevices', ['udid' => $item['udid'], 'subset' => 'General']);
         if ($jamf_item !== null && count($jamf_item) === 1) {
            $mobiledevice->update([
               'id'              => $item['id'],
               'jamf_items_id'   => $jamf_item['general']['id']
            ]);
         }
      }
   }

   $migration->addRight(PluginJamfMobileDevice::$rightname, ALLSTANDARDRIGHT);
   $migration->addRight(PluginJamfRuleImport::$rightname, ALLSTANDARDRIGHT);
   $migration->addRight(PluginJamfUser_JSSAccount::$rightname, ALLSTANDARDRIGHT);
   $migration->addRight(PluginJamfItem_MDMCommand::$rightname, ALLSTANDARDRIGHT);

   // Update 1.1.2
   // Make all plugin cron tasks CLI or GLPI mode instead of CLI-only. This makes it easier for debugging.
   $DB->updateOrDie('glpi_crontasks', [
      'allowmode' => 3
   ], [
      'itemtype'  => 'PluginJamfSync'
   ]);

   // Finish update/install
   $migration->executeMigration();
   return true;
}

function plugin_jamf_uninstall()
{
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_imports');
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_mobiledevices');
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_softwares');
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_extensionattributes');
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_items_extensionattributes');
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_extfields');
   PluginJamfDBUtil::dropTableOrDie('glpi_plugin_jamf_users_jssaccounts');
   Config::deleteConfigurationValues('plugin:Jamf');
   CronTask::unregister('jamf');
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

function plugin_jamf_getAddSearchOptions($itemtype)
{
   $opt = [];
   $plugin = new Plugin();
   if ($plugin->isActivated('jamf')) {
      if ($itemtype == 'Computer' || $itemtype == 'Phone') {
         $opt = [
            '22002' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'last_inventory',
               'name'            => 'Jamf - ' .__('Last inventory', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22003' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'entry_date',
               'name'            => 'Jamf - ' .__('Import date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22004' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'enroll_date',
               'name'            => 'Jamf - ' .__('Enrollment date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22005' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'managed',
               'name'            => 'Jamf - ' .__('Managed', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22006' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'supervised',
               'name'            => 'Jamf - ' .__('Supervised', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22007' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'cloud_backup_enabled',
               'name'            => 'Jamf - ' .__('Cloud backup enabled', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22008' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'activation_lock_enabled',
               'name'            => 'Jamf - ' .__('Activation lock enabled', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22009' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_enabled',
               'name'            => 'Jamf - ' .__('Lost mode enabled', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22010' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_enforced',
               'name'            => 'Jamf - ' .__('Lost mode enforced', 'jamf'),
               'datatype'        => 'bool',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22011' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_enable_date',
               'name'            => 'Jamf - ' .__('Lost mode enable date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22012' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_message',
               'name'            => 'Jamf - ' .__('Lost mode message', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22013' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_mode_phone',
               'name'            => 'Jamf - ' .__('Lost mode phone', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22014' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_latitude',
               'name'            => 'Jamf - ' .__('Lost mode latitude', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22015' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_longitude',
               'name'            => 'Jamf - ' .__('Lost mode longitude', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22016' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_altitude',
               'name'            => 'Jamf - ' .__('Lost mode altitude', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22017' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_speed',
               'name'            => 'Jamf - ' .__('Lost mode speed', 'jamf'),
               'datatype'        => 'text',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22018' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'lost_location_date',
               'name'            => 'Jamf - ' .__('Lost mode location date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22019' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'import_date',
               'name'            => 'Jamf - ' .__('Import date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ],
            '22020' => [
               'table'           => 'glpi_plugin_jamf_mobiledevices',
               'field'           => 'sync_date',
               'name'            => 'Jamf - ' .__('Sync date', 'jamf'),
               'datatype'        => 'datetime',
               'massiveaction'   => false,
               'joinparams'      => ['jointype' => 'itemtype_item']
            ]
         ];
      }
   }
   return $opt;
}
