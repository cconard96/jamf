<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2024 by Curtis Conard
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

include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('jamf')) {
    Html::displayNotFoundError();
}

Html::header('Jamf Plugin', '', 'tools', 'PluginJamfMenu', 'import');

global $CFG_GLPI;

$plugin_dir = Plugin::getWebDir('jamf');
$links = [];
if (Session::haveRight('plugin_jamf_mobiledevice', CREATE)) {
    $links[] = [
        'name' => _x('menu', 'Import devices', 'jamf'),
        'url' => PluginJamfImport::getSearchURL()
    ];
    $links[] = [
        'name' => _x('menu', 'Merge existing devices', 'jamf'),
        'url' => "{$plugin_dir}/front/merge.php"
    ];
}
if (Session::haveRight('config', UPDATE)) {
    $links[] = [
        'name' => _x('menu', 'Configuration', 'jamf'),
        'url' => Config::getFormURL() . "?forcetab=PluginJamfConfig"
    ];
}

TemplateRenderer::getInstance()->display('@jamf/menu.html.twig', [
    'links' => $links
]);
Html::footer();
