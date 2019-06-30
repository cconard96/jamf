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

 class PluginJamfAPIPro {
    private static $connection;

    private static function get(string $endpoint, $raw = false)
    {
        if (!self::$connection) {
            self::$connection = new PluginJamfConnection();
        }
        $url = (self::$connection)->getAPIUrl($endpoint, true);
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
        $start = microtime(true);
        $response = curl_exec($curl);
        $api_time = microtime(true) - $start;
        if ($api_time > 1) {
            Toolbox::logDebug("Jamf Pro API call took > 1 second. Expect slowdowns.");
        }
        curl_close($curl);
        if (!$response) {
           return null;
        }
        return ($raw ? $response : json_decode($response, true));
    }

    public static function getLobby()
    {
       return self::get('/');
    }

    public static function getAllMobileDevices()
    {
       if (!self::$connection) {
          self::$connection = new PluginJamfConnection();
       }
       $connection = self::$connection;
       if (version_compare($connection->getServerVersion(), '10.14.0', '>=')) {
          return self::get('/v1/mobile-devices');
       } else {
          return self::get('/inventory/obj/mobileDevice');
       } 
    }

    public static function getMobileDevice(int $id, bool $detailed = false)
    {
       if (!self::$connection) {
          self::$connection = new PluginJamfConnection();
       }
       $connection = self::$connection;
       if (version_compare($connection->getServerVersion(), '10.14.0', '>=')) {
          $endpoint = $endpoint = "/v1/mobile-devices/{$id}".($detailed ? '/detail' : '');
       } else {
          $endpoint = "/inventory/obj/mobileDevice/{$id}".($detailed ? '/detail' : '');
       }
       return self::get($endpoint);
    }
 }