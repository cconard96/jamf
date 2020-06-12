<?php

/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019-2020 by Curtis Conard
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
 * JSS Extension Attribute class
 *
 * @since 1.1.0
 */
class PluginJamfExtensionAttribute extends CommonDBTM {

    public static function getTypeName($nb = 1)
    {
        return __('Extension attribute', 'Extension attributes', $nb, 'jamf');
    }

    public function addOrUpdate($input)
    {
       global $DB;

       if (!isset($input['jamf_id'])) {
          return false;
       }
       $jamf_id = $input['jamf_id'];
       unset($input['jamf_id']);
       return $DB->updateOrInsert(PluginJamfExtensionAttribute::getTable(), $input, ['jamf_id' => $jamf_id]);
    }

    public function showForm() {

    }
}