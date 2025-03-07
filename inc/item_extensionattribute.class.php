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

use Glpi\Application\View\TemplateRenderer;

/**
 * JSS Extension Attribute Item Link class
 *
 * @since 1.1.0
 */
class PluginJamfItem_ExtensionAttribute extends CommonDBChild
{
    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';

    public static function getTypeName($nb = 1)
    {
        return _nx('itemtype', 'Extension attribute', 'Extension attributes', $nb, 'jamf');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        /** @var PluginJamfAbstractDevice $jamf_class */
        $jamf_class = PluginJamfAbstractDevice::getJamfItemClassForGLPIItem($item::getType(), $item->getID());
        if ($jamf_class === null) {
            return false;
        }
        $jamf_item = $jamf_class::getJamfItemForGLPIItem($item);
        if ($jamf_class === null || !$jamf_class::canView()) {
            return false;
        }

        return self::createTabEntry(self::getTypeName(2), self::countForJamfItem($jamf_item));
    }

    public static function countForJamfItem($jamf_item)
    {
        return countElementsInTable(self::getTable(), [
            'itemtype' => $jamf_item::getType(),
            'items_id' => $jamf_item->getID(),
        ]);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        return self::showForItem($item);
    }

    public static function showForItem(CommonDBTM $item)
    {
        /** @var PluginJamfAbstractDevice $jamf_class */
        $jamf_class = PluginJamfAbstractDevice::getJamfItemClassForGLPIItem($item::getType(), $item->getID());
        if ($jamf_class === null || !$jamf_class::canView()) {
            return false;
        }

        $mobiledevice = $jamf_class::getJamfItemForGLPIItem($item);
        if ($mobiledevice === null) {
            return false;
        }

        $attributes = $mobiledevice->getExtensionAttributes();
        TemplateRenderer::getInstance()->display('@jamf/ext_attributes.html.twig', [
            'attributes' => $attributes,
        ]);

        return true;
    }
}
