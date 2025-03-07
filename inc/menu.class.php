<?php

/**
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of JAMF plugin for GLPI.
 *
 * JAMF plugin for GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * JAMF plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JAMF plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024-2024 by Teclib'
 * @copyright Copyright (C) 2019-2024 by Curtis Conard
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/jamf
 * -------------------------------------------------------------------------
 */

/**
 * PluginJamfMenu class. This class adds a menu which contains links to pages related to this plugin.
 * See /front/menu.php file for the menu content.
 */
class PluginJamfMenu extends CommonGLPI
{
    /**
     * Get name of this type by language of the user connected
     *
     * @param integer $nb number of elements
     * @return string name of this type
     */
    public static function getTypeName($nb = 0)
    {
        return _x('plugin_info', 'Jamf plugin', 'jamf');
    }

    public static function getMenuName()
    {
        return _x('plugin_info', 'Jamf plugin', 'jamf');
    }

    public static function getIcon()
    {
        return 'fas fa-tablet-alt';
    }

    /**
     * Check if can view item
     *
     * @return boolean
     */
    public static function canView()
    {
        return Config::canView();
    }
}
