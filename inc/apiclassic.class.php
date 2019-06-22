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

 class PluginJamfAPIClassic {
    private static $connection;

    private static function get(string $endpoint, $raw = false)
    {
        if (!self::$connection) {
            self::$connection = new PluginJamfConnection();
        }
        $url = (self::$connection)->getAPIUrl($endpoint);
        $curl = curl_init($url);
        self::$connection->setCurlAuth($curl);
        curl_setopt($curl, CURLOPT_SSLVERSION, 6);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
           'Content-Type: application/json',
           'Accept: application/json'
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);
        if (!$response) {
           return null;
        }
        return ($raw ? $response : json_decode($response, true));
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
        $endpoint = "$itemtype$param_str";
        $response = self::get($endpoint);
        // Strip first key (usually like mobile_devices or mobile_device)
        // No other first level keys exist
        return reset($response);
    }
 }