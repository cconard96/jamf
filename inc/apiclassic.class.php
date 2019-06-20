<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019 by Curtis Conard
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

 class JamfAPIClassic {
    private static $connection;

    private static function get(string $endpoint, $raw = false)
    {
        if (!$connection) {
            $connection = new JamfJamfConnection();
        }
        $connection->setCurlAuth();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    }

    private static function getParamString(array $params = [])
    {
        $param_str = "";
        foreach ($params as $key => $value) {
            $param_str = "{$param_str}/{$key}/{$value}";
        }
        return $param_str;
    }

    public static function getItems(string $itemtype, array $params = [])
    {
        $param_str = self::getParamString($params);
        $endpoint = "$itemtype/$param_str";
    }
 }