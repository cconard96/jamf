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
 * PluginJamfMobileDeviceSoftware class.
 * @since 1.0.0
 */
class PluginJamfMobileDeviceSoftware extends CommonDBTM
{

   public static function getTypeName($nb = 0)
   {
      return Software::getTypeName($nb);
   }

   /**
    * Cleanup relations when an item is purged.
    * @global type $DB
    * @param type $item
    */
   public static function plugin_jamf_purgeSoftware($item)
   {
      global $DB;

      $DB->delete(self::getTable(), [
         'softwares_id' => $item->getID(),
      ]);
   }
}
