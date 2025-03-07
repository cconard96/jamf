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

class PluginJamfToolbox
{
    public static function getHumanReadableTimeDiff($start, $end = null)
    {
        if ($start === null || $start == 'NULL') {
            return null;
        }
        if ($end === null) {
            $end = $_SESSION['glpi_currenttime'];
        }
        $diff     = date_diff(date_create($start), date_create($end));
        $text_arr = [];
        if ($diff->y > 0) {
            $text_arr[] = sprintf('%d Y', $diff->y);
        }
        if ($diff->m > 0) {
            $text_arr[] = sprintf('%d M', $diff->m);
        }
        if ($diff->d > 0) {
            $text_arr[] = sprintf('%d D', $diff->d);
        }
        if ($diff->h > 0) {
            $text_arr[] = sprintf('%d H', $diff->h);
        }
        if ($diff->i > 0) {
            $text_arr[] = sprintf('%d m', $diff->i);
        }
        if ($diff->s > 0) {
            $text_arr[] = sprintf('%d s', $diff->s);
        }

        return implode(' ', $text_arr);
    }

    /**
     * Helper function to convert the UTC timestamps from JSS to a local DateTime.
     * @param DateTime|string|null $utc The UTC DateTime from JSS.
     * @param ?int $format
     * @return string The local date and time.
     * @throws Exception
     */
    public static function utcToLocal($utc, ?int $format = null): string
    {
        if ($utc === null) {
            return '';
        }
        if (!is_a($utc, DateTime::class)) {
            $utc = new DateTime($utc, new DateTimeZone('UTC'));
        }
        $mask = 'Y-m-d H:i:s';

        if ($format === null) {
            $format = $_SESSION['glpidate_format'];
        }
        switch ($format) {
            case 1: // DD-MM-YYYY
                $mask = 'd-m-Y H:i:s';
                break;
            case 2: // MM-DD-YYYY
                $mask = 'm-d-Y H:i:s';
                break;
        }
        $tz = new DateTimeZone($_SESSION['glpi_tz'] ?? date_default_timezone_get());
        $utc->setTimezone($tz);

        return $utc->format($mask);
    }
}
