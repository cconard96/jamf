<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2021 by Curtis Conard
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

use Glpi\Application\View\TemplateRenderer;

/**
 * PluginJamfConfig class
 */
class PluginJamfConfig extends CommonDBTM
{

    static protected $notable = true;

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate && $item->getType() === 'Config') {
            return _x('plugin_info', 'Jamf plugin', 'jamf');
        }
        return '';
    }

    public function showForm($ID = -1, array $options = [])
    {
        global $CFG_GLPI;
        if (!Session::haveRight('config', UPDATE)) {
            return false;
        }
        $config = self::getConfig(true);

        TemplateRenderer::getInstance()->display('@jamf/config.html.twig', [
            'config' => $config,
            'url' => Toolbox::getItemTypeFormURL('Config'),
        ]);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() === 'Config') {
            $config = new self();
            $config->showForm();
        }
    }

    public static function undiscloseConfigValue($fields)
    {
        $to_hide = ['jsspassword'];
        foreach ($to_hide as $f) {
            if (in_array($f, $fields, true)) {
                unset($fields[$f]);
            }
        }
        return $fields;
    }

    public static function getConfig(bool $force_all = false): array
    {
        static $config = null;
        if ($config === null) {
            $config = Config::getConfigurationValues('plugin:Jamf');
        }
        if (!$force_all) {
            return self::undiscloseConfigValue($config);
        }

        return $config;
    }
}
