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

Session::checkRight("plugin_jamf_mobiledevice", CREATE);
Html::header('Jamf Plugin', '', 'tools', 'PluginJamfMenu', 'import');

global $DB, $CFG_GLPI;

$start = $_GET['start'] ?? 0;

$import = new PluginJamfImport();
$importcount = countElementsInTable(PluginJamfImport::getTable());
$pending = iterator_to_array($DB->request([
    'FROM' => PluginJamfImport::getTable(),
    'START' => $start,
    'LIMIT' => $_SESSION['glpilist_limit']
]));

$linked_devices = $DB->request([
    'SELECT' => ['jamf_type', 'itemtype', 'items_id'],
    'FROM' => 'glpi_plugin_jamf_devices',
]);

$linked = [];
foreach ($linked_devices as $data) {
    $linked[$data['itemtype']][] = $data;
}

foreach ($pending as &$data) {
    $itemtype = $data['type'];
    /** @var CommonDBTM $item */
    $item = new $itemtype();
    $jamftype = ('PluginJamf' . $data['jamf_type']);
    $guesses = $DB->request([
        'SELECT' => ['id'],
        'FROM' => $itemtype::getTable(),
        'WHERE' => [
            'OR' => [
                'uuid' => $data['udid'],
                'name' => $data['name']
            ]
        ],
        'ORDER' => new QueryExpression("CASE WHEN uuid='" . $data['udid'] . "' THEN 0 ELSE 1 END"),
        'LIMIT' => 1
    ]);
    if (count($guesses)) {
        $data['guessed_item'] = $guesses->current()['id'];
    } else {
        $data['guessed_item'] = 0;
    }
}

TemplateRenderer::getInstance()->display('@jamf/merge.html.twig', [
    'pending' => $pending,
    'total_count' => $importcount,
    'linked' => $linked
]);
Html::footer();
