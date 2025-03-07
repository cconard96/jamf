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
 * Contains all cron functions for Jamf plugin
 * @since 1.0.0
 */
final class PluginJamfCron extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return _x('plugin_info', 'Jamf plugin', 'jamf');
    }

    public static function cronSyncJamf(CronTask $task)
    {
        $volume  = 0;
        $engines = PluginJamfSync::getDeviceSyncEngines();

        foreach ($engines as $jamf_class => $engine) {
            $v = $engine::syncAll();
            $volume += $v >= 0 ? $v : 0;
        }
        $task->addVolume($volume);

        return 1;
    }

    public static function cronImportJamf(CronTask $task)
    {
        $volume  = 0;
        $engines = PluginJamfSync::getDeviceSyncEngines();

        foreach ($engines as $jamf_class => $engine) {
            $v = $engine::discover();
            $volume += $v >= 0 ? $v : 0;
        }
        $task->addVolume($volume);

        return 1;
    }

    public static function cronUpdatePMV(CronTask $task): int
    {
        $url      = 'https://gdmf.apple.com/v2/pmv';
        $out_file = GLPI_PLUGIN_DOC_DIR . '/jamf/pmv.json';

        $json = file_get_contents($url);
        if ($json === false) {
            $task->log(__('Unable to fetch PMV JSON from Apple', 'jamf'));

            return 0;
        }
        try {
            $json = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $task->log(__('Retrieved malformed PMV JSON', 'jamf'));

            return 0;
        }
        unset($json['PublicAssetSets']);
        try {
            $json = json_encode($json, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $task->log(__('Unable to encode PMV JSON', 'jamf'));

            return 0;
        }
        if (file_put_contents($out_file, $json) === false) {
            $task->log(__('Unable to write PMV JSON to file', 'jamf'));

            return 0;
        }

        return 1;
    }
}
