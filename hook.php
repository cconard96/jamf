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

   // Check configuration table
   if (!$DB->tableExists('glpi_plugin_jamf_configs')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_configs` (
                  `id` int(11) NOT NULL auto_increment,
                  `jssserver` varchar(255) NULL,
                  `jssuser` varchar(255) NULL,
                  `jsspassword` varchar(255) NULL,
                  `audit_mode` tinyint(1) NOT NULL DEFAULT '1',
                  `sync_interval` int(11) NOT NULL DEFAULT '10',
                  `sync_general` tinyint(1) NOT NULL DEFAULT '0',
                  `sync_software` tinyint(1) NOT NULL DEFAULT '0',
                  `sync_os` tinyint(1) NOT NULL DEFAULT '0',
                  `sync_financial` tinyint(1) NOT NULL DEFAULT '0',
                  `sync_components` tinyint(1) NOT NULL DEFAULT '0',
                  `sync_user` tinyint(1) NOT NULL DEFAULT '0',
                  `user_sync_mode` varchar(100) NOT NULL DEFAULT 'email',
                  `sync_status` tinyint(1) NOT NULL DEFAULT '0',
                  `enroll_status` tinyint(1) NOT NULL DEFAULT '0',
                  `lost_status` tinyint(1) NOT NULL DEFAULT '0',
                  `mobiledevice_type` int(11) DEFAULT NULL,
                  `autoimport` tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin config table' . $DB->error());
      $DB->queryOrDie("INSERT INTO `glpi_plugin_jamf_configs` () VALUES ()");
   }

   // Check actions table (Used to audit actions made by the plugin)
   if (!$DB->tableExists('glpi_plugin_jamf_actions')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_actions` (
                  `id` int(11) NOT NULL auto_increment,
                  `computers_id` int(11) NOT NULL,
                  `field` varchar(255) NOT NULL,
                  `old_value` text NULL,
                  `new_value` text NULL,
                  `is_commited` tinyint(1) NOT NULL DEFAULT '0',
                  `date` datetime NOT NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin actions table' . $DB->error());
   }

   // Check imports table (Used to store newly discovered devices that haven't been imported yet)
   if (!$DB->tableExists('glpi_plugin_jamf_imports')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_imports` (
                  `id` int(11) NOT NULL auto_increment,
                  `jamf_items_id` int(11) NOT NULL,
                  `type` varchar(100) NOT NULL,
                  `udid` varchar(255) NOT NULL,
                  `date_discover` datetime NOT NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin imports table' . $DB->error());
   }

   // Check mobile devices table (Extra data for mobile devices)
   if (!$DB->tableExists('glpi_plugin_jamf_mobiledevices')) {
      $query = "CREATE TABLE `glpi_plugin_jamf_mobiledevices` (
                  `id` int(11) NOT NULL auto_increment,
                  `computers_id` int(11) NOT NULL,
                  `udid` varchar(100) NOT NULL,
                  `last_inventory` datetime NULL,
                  `entry_date` datetime NULL,
                  `enroll_date` datetime NULL,
                  `managed` tinyint(1) NOT NULL DEFAULT '0',
                  `supervised` tinyint(1) NOT NULL DEFAULT '0',
                  `shared` tinyint(1) NOT NULL DEFAULT '0',
                  `cloud_backup_enabled` tinyint(1) NOT NULL DEFAULT '0',
                  `activation_lock_enabled` tinyint(1) NOT NULL DEFAULT '0',
                  `lost_mode_enabled` tinyint(1) NOT NULL DEFAULT '0',
                  `lost_mode_enforced` tinyint(1) NOT NULL DEFAULT '0',
                  `lost_mode_enable_date` datetime NULL,
                  `lost_mode_message` varchar(255) DEFAULT NULL,
                  `lost_mode_phone` varchar(255) DEFAULT NULL,
                  `lost_location_latitude` decimal(5,10) NOT NULL DEFAULT '0.0',
                  `lost_location_longitude` decimal(5,10) NOT NULL DEFAULT '0.0',
                  `lost_location_altitude` decimal(5,10) NOT NULL DEFAULT '0.0',
                  `lost_location_date` datetime NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
      $DB->queryOrDie($query, 'Error creating JAMF plugin imports table' . $DB->error());
   }
   return true;
}

function plugin_jamf_uninstall()
{
   global $DB;

   $DB->queryOrDie('DROP `glpi_plugin_jamf_configs`', $DB->error());
   $DB->queryOrDie('DROP `glpi_plugin_jamf_actions`', $DB->error());
   $DB->queryOrDie('DROP `glpi_plugin_jamf_imports`', $DB->error());
   $DB->queryOrDie('DROP `glpi_plugin_jamf_mobiledevices`', $DB->error());
   return true;
}