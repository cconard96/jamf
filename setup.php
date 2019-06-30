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

define('PLUGIN_JAMF_VERSION', '1.0.0');
define('PLUGIN_JAMF_MIN_GLPI', '9.4.0');
define('PLUGIN_JAMF_MAX_GLPI', '9.5.0');

function plugin_init_jamf() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['jamf'] = true;
   Plugin::registerClass('PluginJamfConfig', ['addtabon' => 'Config']);
   $PLUGIN_HOOKS['post_item_form']['jamf'] = ['PluginJamfMobileDevice',
                                                   'showForComputerOrPhoneMain'];
   $PLUGIN_HOOKS['undiscloseConfigValue']['jamf'] = [PluginJamfConfig::class, 'undiscloseConfigValue'];
   Plugin::registerClass('PluginJamfShimPhoneOS', ['addtabon' => 'Phone']);
   Plugin::registerClass('PluginJamfRuleImportCollection', ['rulecollections_types' => true]);
   Plugin::registerClass('PluginJamfProfile', ['addtabon' => ['Profile']]);
   if (Session::haveRight('plugin_jamf_mobiledevice', READ)) {
      $PLUGIN_HOOKS['menu_toadd']['jamf'] = ['tools' => 'PluginJamfMenu'];
   }
}

function plugin_version_jamf() {
   
   return [
      'name' => __("JAMF Plugin for GLPI", 'jamf'),
      'version' => PLUGIN_JAMF_VERSION,
      'author'  => 'Curtis Conard',
      'license' => 'GPLv2',
      'homepage'=>'https://github.com/cconard96/jamf',
      'requirements'   => [
         'glpi'   => [
            'min' => PLUGIN_JAMF_MIN_GLPI,
            'max' => PLUGIN_JAMF_MAX_GLPI
         ]
      ]
   ];
}

function plugin_jamf_check_prerequisites() {
   if (!method_exists('Plugin', 'checkGlpiVersion')) {
      $version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
      $matchMinGlpiReq = version_compare($version, PLUGIN_JAMF_MIN_GLPI, '>=');
      $matchMaxGlpiReq = version_compare($version, PLUGIN_JAMF_MAX_GLPI, '<');
      if (!$matchMinGlpiReq || !$matchMaxGlpiReq) {
         echo vsprintf(
            'This plugin requires GLPI >= %1$s and < %2$s.',
            [
               PLUGIN_JAMF_MIN_GLPI,
               PLUGIN_JAMF_MAX_GLPI,
            ]
         );
         return false;
      }
   }
   return true;
}

function plugin_jamf_check_config()
{
   return true;
}
