<?php

/*
 * -------------------------------------------------------------------------
 * JAMF plugin for GLPI
 * Copyright (C) 2019-2021 by Curtis Conard
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

use Glpi\Application\View\TemplateRenderer;

/**
 * JSS Item_MDMCommand class
 *
 * @since 1.1.0
 */
class PluginJamfItem_MDMCommand extends CommonDBTM
{

    static public $rightname = 'plugin_jamf_mdmcommand';

    public static function getTypeName($nb = 0)
    {
        return _nx('itemtype', 'MDM command', 'MDM commands', $nb, 'jamf');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $jamf_class = PluginJamfAbstractDevice::getJamfItemClassForGLPIItem($item::getType(), $item->getID());
        if ($jamf_class !== PluginJamfMobileDevice::class || !PluginJamfMobileDevice::canView()) {
            return false;
        }
        return self::getTypeName(2);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        return self::showForItem($item);
    }

    /**
     * @param PluginJamfMobileDevice $mobiledevice
     * @return array
     * @since 1.0.0
     */
    public static function getApplicableCommands(PluginJamfMobileDevice $mobiledevice)
    {
        if (PluginJamfUser_JSSAccount::hasLink()) {
            $allcommands = PluginJamfMDMCommand::getAvailableCommands();
            $device_data = $mobiledevice->getJamfDeviceData();

            foreach ($allcommands as $command => &$params) {
                if (isset($params['requirements'])) {
                    // Note: Costs are based on the number of DB or API calls. Checks should always be done least to most expensive.
                    // DB call: 1 cost, API call: 2 cost
                    // Check supervised - Cost: 0
                    if (isset($params['requirements']['supervised']) &&
                        $params['requirements']['supervised'] != $device_data['supervised']) {
                        unset($allcommands[$command]);
                        continue;
                    }

                    // Check managed - Cost: 0
                    if (isset($params['requirements']['managed']) &&
                        $params['requirements']['managed'] != $device_data['managed']) {
                        unset($allcommands[$command]);
                        continue;
                    }

                    // Check lost status - Cost: 0
                    if (isset($params['requirements']['lostmode'])) {
                        $req_value = $params['requirements']['lostmode'];
                        $value = $mobiledevice->fields['lost_mode_enabled'];

                        if ($value !== 'true' && $value !== 'false') {
                            unset($allcommands[$command]);
                            continue;
                        }

                        if ($req_value && $value !== 'true') {
                            unset($allcommands[$command]);
                            continue;
                        }

                        if (!$req_value && $value !== 'false') {
                            unset($allcommands[$command]);
                            continue;
                        }
                    }

                    // Test device type requirements - Cost: 2
                    if (isset($params['requirements']['devicetypes']) && !empty($params['requirements']['devicetypes']) &&
                        !array_key_exists('mobiledevice', $params['requirements']['devicetypes']) &&
                        !in_array('mobiledevice', $params['requirements']['devicetypes'], true)) {
                        $specifictype = $mobiledevice->getSpecificType();
                        if (!array_key_exists($specifictype, $params['requirements']['devicetypes']) &&
                            !in_array($specifictype, $params['requirements']['devicetypes'], true)) {
                            unset($allcommands[$command]);
                            continue;
                        }
                    }

                    // Test user JSS account rights - Cost: 2
                    if (isset($params['jss_right']) && !empty($params['jss_right']) && !PluginJamfMDMCommand::canSend($command)) {
                        unset($allcommands[$command]);
                        continue;
                    }
                }
            }
            self::applySpecificParams($allcommands, $mobiledevice);
            return $allcommands;
        }
        return [];
    }

    private static function getPMVData(): array
    {
        static $data = null;

        if ($data === null) {
            $pmv_file = GLPI_PLUGIN_DOC_DIR.'/jamf/pmv.json';
            if (file_exists($pmv_file)) {
                $data = json_decode(file_get_contents($pmv_file), true)['AssetSets'];
            } else {
                $data = [];
            }
        }

        return $data;
    }

    private static function getAvailableUpdates($model_identifier): array
    {
        $data = self::getPMVData();
        if (empty($data)) {
            return [];
        }
        $data['iOS'] = array_filter($data['iOS'], static function ($item) use ($model_identifier) {
            return $item['ExpirationDate'] > date('Y-m-d') &&
                in_array($model_identifier, $item['SupportedDevices'], true);
        });
        $data['macOS'] = array_filter($data['macOS'], static function ($item) use ($model_identifier) {
            return $item['ExpirationDate'] > date('Y-m-d') &&
                in_array($model_identifier, $item['SupportedDevices'], true);
        });

        // Sort so newest updates are first
        usort($data['iOS'], static function ($a, $b) {
            return version_compare($b['ProductVersion'], $a['ProductVersion']);
        });
        usort($data['macOS'], static function ($a, $b) {
            return version_compare($b['ProductVersion'], $a['ProductVersion']);
        });

        if (count($data['iOS']) > 0) {
            return array_column($data['iOS'], 'ProductVersion');
        } else if (count($data['macOS']) > 0) {
            return array_column($data['macOS'], 'ProductVersion');
        } else {
            return [];
        }
    }

    public static function applySpecificParams(&$commands, PluginJamfAbstractDevice $mobiledevice): void
    {
        if (isset($commands['ScheduleOSUpdate'])) {
            $applicable_updates = self::getAvailableUpdates($mobiledevice->getJamfDeviceData()['model_identifier']);
            if (count($applicable_updates) > 0) {
                // Replace product_version plain-text field with a dropdown of versions
                $commands['ScheduleOSUpdate']['params']['product_version']['type'] = 'dropdown';
                $commands['ScheduleOSUpdate']['params']['product_version']['values'] = $applicable_updates;
            }
        }
    }

    public static function showForItem(CommonDBTM $item)
    {
        if (!PluginJamfMobileDevice::canView() || !static::canView()) {
            return false;
        }

        $mobiledevice = PluginJamfMobileDevice::getJamfItemForGLPIItem($item);
        if ($mobiledevice === null) {
            return false;
        }

        $commands = self::getApplicableCommands($mobiledevice);
        $item_commands = $mobiledevice->getMDMCommands();
        $device_data = $mobiledevice->getJamfDeviceData();

        TemplateRenderer::getInstance()->display('@jamf/mdm_commands.html.twig', [
            'commands' => $commands,
            'pending_commands' => $item_commands['pending'],
            'failed_commands' => $item_commands['failed'],
            'itemtype' => $item->getType(),
            'items_id' => $item->getID(),
            'jamf_itemtype' => 'MobileDevice',
            'jamf_items_id' => $mobiledevice->getID(),
            'jamf_id' => $device_data['jamf_items_id'],
        ]);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getRights($interface = 'central')
    {

        return [READ => __('Read')];
    }
}
