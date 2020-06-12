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
 * PluginJamfRuleImport class. Represents a rule for importing devices into GLPI.
 * Determines if the import happens or if it is dropped.
 * @since 1.0.0
 */
 class PluginJamfRuleImport extends Rule {

   static public $rightname = 'plugin_jamf_ruleimport';
   public $can_sort = true;

   function getTitle()
   {
      return __('Device import rules', 'jamf');
   }

   function maxActionsCount()
   {
      return 1;
   }

   function getCriterias() 
   {
      $criterias = [];
      $criterias['name']['field'] = 'name';
      $criterias['name']['name']  = __('Name');
      $criterias['name']['table'] = '';

      $criterias['itemtype']['field'] = 'itemtype';
      $criterias['itemtype']['name']  = __('Item type');
      $criterias['itemtype']['table'] = '';
      $criterias['itemtype']['allow_condition'] = [Rule::PATTERN_IS, Rule::PATTERN_IS_NOT];

      $criterias['last_inventory']['field'] = 'last_inventory';
      $criterias['last_inventory']['name']  = __('Last inventory');
      $criterias['last_inventory']['table'] = '';

      $criterias['managed']['field'] = 'managed';
      $criterias['managed']['name']  = __('Managed');
      $criterias['managed']['type']  = 'yesno';
      $criterias['managed']['table'] = '';

      $criterias['supervised']['field'] = 'supervised';
      $criterias['supervised']['name']  = __('Supervised');
      $criterias['supervised']['type']  = 'yesno';
      $criterias['supervised']['table'] = '';
      return $criterias;
   }

   function getActions()
   {
      $actions = [];
      $actions['_import']['name']  = __('Import');
      $actions['_import']['type']  = 'yesno';
      return $actions;
   }

   function displayAdditionalRuleCondition($condition, $crit, $name, $value, $test = false)
   {
      if (isset($crit['field'])) {
         switch ($crit['field']) {
            case 'itemtype':
               Dropdown::showFromArray($name, [
                  Computer::getType()  => Computer::getTypeName(1),
                  Phone::getType()     => Phone::getTypeName(1),
               ]);
               return true;
         }
      }
   }
 }