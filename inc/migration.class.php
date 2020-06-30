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
 * Handles migrating between plugin versions.
 *
 * @since 2.0.0
 */
final class PluginJamfMigration {

   private const BASE_VERSION = '1.0.0';
   /**
    * @var Migration
    */
   private $glpiMigration;

   /**
    * @var DBmysql
    */
   private $db;

   /**
    * PluginJamfMigration constructor.
    * @param string $version
    * @global DBmysql $DB
    */
   public function __construct($version)
   {
      global $DB;

      $this->glpiMigration = new Migration($version);
      $this->db = $DB;
   }

   public function applyMigrations()
   {
      $rc = new ReflectionClass($this);
      $otherMigrationFunctions = array_map(static function ($rm) use ($rc) {
         return $rm->getShortName();
      }, array_filter($rc->getMethods(), static function ($m) {
         return preg_match('/(?<=^apply_)(.*)(?=_migration$)/', $m->getShortName());
      }));

      if (count($otherMigrationFunctions)) {
         // Map versions to functions
         $versionMap = [];
         foreach ($otherMigrationFunctions as $function) {
            $ver = str_replace('_', '.', $function);
            $versionMap[$ver] = $function;
         }

         // Sort semantically
         uksort($versionMap, 'version_compare');

         // Get last known recorded version. If none exists, assume this is 1.0.0 since versions weren't recorded until 2.0.0.
         // Migrations should be replayable so nothing should be lost on multiple runs.
         $lastKnownVersion = Config::getConfigurationValues('plugin:Jamf')['plugin_version'] ?? self::BASE_VERSION;

         // Call each migration in order starting from the last known version
         foreach ($versionMap as $version => $func) {
            // Last known version is the same or greater than release version
            if (version_compare($lastKnownVersion, $version) !== -1) {
               continue;
            }
            $this->$func();
            if ($version !== self::BASE_VERSION) {
               $this->setPluginVersionInDB($version);
            }
         }

      }
   }

   private function setPluginVersionInDB($version)
   {
      $this->db->updateOrDie(Config::getTable(), [
         'value'     => $version
      ], [
         'context'   => 'plugin:Jamf',
         'name'      => 'plugin_version'
      ]);
   }

   /**
    * Apply the migrations for the base plugin version (1.0.0).
    *
    * @since 2.0.0
    */
   public function apply_1_0_0_migration(): void
   {

      // Check imports table (Used to store newly discovered devices that haven't been imported yet)
      if (!$this->db->tableExists('glpi_plugin_jamf_imports')) {
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
         $this->db->queryOrDie($query, 'Error creating JAMF plugin imports table' . $this->db->error());
      }

      // Check mobile devices table (Extra data for mobile devices)
      if (!$this->db->tableExists('glpi_plugin_jamf_mobiledevices')) {
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
         $this->db->queryOrDie($query, 'Error creating JAMF plugin imports table' . $this->db->error());
      }

      // Check software table (Extra data for software). Also check the later name just to avoid useless SQL actions.
      if (!$this->db->tableExists('glpi_plugin_jamf_softwares') && !$this->db->tableExists('glpi_plugin_jamf_mobiledevicesoftwares')) {
         $query = "CREATE TABLE `glpi_plugin_jamf_softwares` (
                  `id` int(11) NOT NULL auto_increment,
                  `softwares_id` int(11) NOT NULL,
                  `bundle_id` varchar(255) NOT NULL,
                  `itunes_store_url` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->queryOrDie($query, 'Error creating JAMF plugin software table' . $this->db->error());
      }

      $jamfconfig = Config::getConfigurationValues('plugin:Jamf');
      if (!count($jamfconfig)) {
         $this->glpiMigration->addConfig([
            'jssserver' => '',
            'jssuser' => '',
            'jsspassword' => '',
            'sync_interval' => '0',
            'sync_general' => '0',
            'sync_os' => '0',
            'sync_software' => '0',
            'sync_financial' => '0',
            'sync_user' => '0',
            'user_sync_mode' => 'email',
            'autoimport' => '0',
         ], 'plugin:Jamf');
      }

      // Not originally a part of version 1.0.0 but useful for the migration system added in 2.0.0
      if (!isset($jamfconfig['plugin_version'])) {
         $this->glpiMigration->addConfig([
            'plugin_version' => '1.0.0'
         ]);
      }

      // CronTask already makes sure we don't register duplicates
      CronTask::register('PluginJamfSync', 'syncJamf', 300, [
         'state'        => 1,
         'allowmode'    => 2,
         'logslifetime' => 30,
         'comment'      => "Sync devices with Jamf that are already imported"
      ]);
      CronTask::register('PluginJamfSync', 'importJamf', 900, [
         'state'        => 1,
         'allowmode'    => 2,
         'logslifetime' => 30,
         'comment'      => "Import or discover devices in Jamf that are not already imported"
      ]);
   }

   private function apply_1_1_0_migration()
   {
      // Check extension attribute tables
      if (!$this->db->tableExists('glpi_plugin_jamf_extensionattributes')) {
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
         $this->db->queryOrDie($query, 'Error creating JAMF plugin extension attribute table' . $this->db->error());
      }
      if (!$this->db->tableExists('glpi_plugin_jamf_items_extensionattributes')) {
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
         $DB->queryOrDie($query, 'Error creating JAMF plugin item extension attribute table' . $this->db->error());
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
         $this->db->queryOrDie($query, 'Error creating JAMF plugin item extension field table' . $DB->error());
      }
      if (!$this->db->tableExists('glpi_plugin_jamf_users_jssaccounts')) {
         $query = "CREATE TABLE `glpi_plugin_jamf_users_jssaccounts` (
                  `id` int(11) NOT NULL auto_increment,
                  `users_id` int(11) NOT NULL,
                  `jssaccounts_id` int(11) NOT NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $this->db->queryOrDie($query, 'Error creating JAMF plugin jss account link table' . $DB->error());
      }

      $this->glpiMigration->addConfig([
         'itemtype_iphone' => 'Phone',
         'itemtype_ipad' => 'Computer',
         'itemtype_appletv' => 'Computer',
      ], 'plugin:Jamf');

      // Fix missing Jamf ID field
      if (!$this->db->fieldExists('glpi_plugin_jamf_mobiledevices', 'jamf_items_id', false)) {
         $this->glpiMigration->addField('glpi_plugin_jamf_mobiledevices', 'jamf_items_id', 'integer', ['default' => -1]);
         $this->glpiMigration->migrationOneTable('glpi_plugin_jamf_mobiledevices');
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
   }

   private function apply_1_1_1_migration()
   {
      $this->glpiMigration->addRight(PluginJamfMobileDevice::$rightname, ALLSTANDARDRIGHT);
      $this->glpiMigration->addRight(PluginJamfRuleImport::$rightname, ALLSTANDARDRIGHT);
      $this->glpiMigration->addRight(PluginJamfUser_JSSAccount::$rightname, ALLSTANDARDRIGHT);
      $this->glpiMigration->addRight(PluginJamfItem_MDMCommand::$rightname, ALLSTANDARDRIGHT);
   }

   private function apply_1_1_2_migration()
   {
      $this->db->updateOrDie('glpi_crontasks', [
         'allowmode' => 3
      ], [
         'itemtype'  => 'PluginJamfSync'
      ]);
   }

   private function apply_2_0_0_migration()
   {
      $config = Config::getConfigurationValues('plugin:Jamf');
      $coreConfig = Config::getConfigurationValues('core');

      // Drop configs for selecting itemtypes. This is now statically enforced since improvements to the GLPI phone type.
      if (isset($coreConfig['itemtype_iphone'])) {
         $this->db->delete(Config::class, [
            'context'   => 'core',
            'name'      => ['itemtype_iphone', 'itemtype_ipad', 'itemtype_appletv']
         ]);
      }
      if (isset($config['itemtype_iphone'])) {
         $this->db->delete(Config::class, [
            'context'   => 'plugin:Jamf',
            'name'      => ['itemtype_iphone', 'itemtype_ipad', 'itemtype_appletv']
         ]);
      }

      // Add default status config option
      if (!isset($config['default_status'])) {
         $this->glpiMigration->addConfig(['default_status', null], 'plugin:Jamf');
      }
      if (!isset($config['sync_components'])) {
         $this->glpiMigration->addConfig(['sync_components', 0], 'plugin:Jamf');
      }
      if (!isset($config['jssignorecert'])) {
         $this->glpiMigration->addConfig(['jssignorecert', 0], 'plugin:Jamf');
      }

      if (!$this->db->fieldExists('glpi_plugin_jamf_extensionattributes', 'jamf_type', false)) {
         $this->glpiMigration->addField('glpi_plugin_jamf_extensionattributes', 'jamf_type', 'string', [
            'default'   => 'MobileDevice',
            'after'     => 'itemtype'
         ]);
         $this->glpiMigration->dropKey('glpi_plugin_jamf_extensionattributes', 'jamf_id');
         $this->glpiMigration->dropField('glpi_plugin_jamf_extensionattributes', 'itemtype');
         $this->glpiMigration->addKey('glpi_plugin_jamf_extensionattributes', ['jamf_type', 'jamf_id'], 'unicity', 'UNIQUE');
         $this->glpiMigration->migrationOneTable('glpi_plugin_jamf_extensionattributes');
      }

      if (!$this->db->fieldExists('glpi_plugin_jamf_imports', 'jamf_type', false)) {
         $this->glpiMigration->addField('glpi_plugin_jamf_imports', 'jamf_type', 'string', [
            'default'   => 'MobileDevice',
            'after'     => 'id'
         ]);
         $this->glpiMigration->migrationOneTable('glpi_plugin_jamf_imports');
      }

      // Check computers table (Extra data for computers)
      if (!$this->db->tableExists('glpi_plugin_jamf_computers')) {
         $query = "CREATE TABLE `glpi_plugin_jamf_computers` (
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
                  `activation_lock_enabled` tinyint(1) DEFAULT '0',
                  `jamf_items_id` int(11) NOT NULL DEFAULT -1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`itemtype`, `items_id`),
                KEY `udid` (`udid`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $this->db->queryOrDie($query, 'Error creating JAMF plugin computers table' . $this->db->error());
      }

      // Convert old software table to mobile device software table
      if ($this->db->tableExists('glpi_plugin_jamf_softwares')) {
         $this->glpiMigration->renameTable('glpi_plugin_jamf_softwares', 'glpi_plugin_jamf_mobiledevicesoftwares');
      }

      if (!$this->db->tableExists('glpi_plugin_jamf_computersoftwares')) {
         $query = "CREATE TABLE `glpi_plugin_jamf_computersoftwares` (
                  `id` int(11) NOT NULL auto_increment,
                  `softwares_id` int(11) NOT NULL,
                  `bundle_id` varchar(255) NOT NULL,
                  `itunes_store_url` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $this->db->queryOrDie($query, 'Error creating JAMF plugin computer software table' . $this->db->error());
      }

      $this->db->updateOrDie(CronTask::getTable(), [
         'itemtype'  => 'PluginJamfCron'
      ], [
         'itemtype'  => 'PluginJamfSync'
      ]);
   }
}