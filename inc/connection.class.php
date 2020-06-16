<?php

/*
 -------------------------------------------------------------------------
 JAMF plugin for GLPI
 Copyright (C) 2019-2020 by Curtis Conard
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

/**
 * JamfConnection class
 * @since 1.0.0
 */
class PluginJamfConnection {
   private $config;

   /**
    * Load connection details from the DB and store them in the $config array.
    * @since 1.0.0
    */
   public function __construct()
   {
      global $DB;

      $jamf_config = Config::getConfigurationValues('plugin:Jamf', [
         'jssserver', 'jssuser', 'jsspassword']);
      $this->config = $jamf_config;
      $this->config['jsspassword'] = Toolbox::decrypt($this->config['jsspassword'], GLPIKEY);
   }

   /**
    * Set or change the connection details in the DB.
    * @since 1.0.0
    * @param string $jssserver The URL (and port) or the JSS server.
    * @param string $jssuser The user to connect to the JSS with.
    * @param string $jsspassword The password for $jssuser.
    */
   public function setConnectionConfig($jssserver, $jssuser, $jsspassword)
   {
      global $DB;

      $enc = Toolbox::encrypt($jsspassword, GLPIKEY);
      Config::setConfigurationValues('plugin:Jamf', [
         'jssserver' => $jssserver,
         'jssuser' => $jssuser,
         'jsspassword' => $enc
      ]);
   }

   /**
    * Get the version of the JSS server and caches it for future function calls.
    * @since 1.0.0
    * @return string The JSS version.
    */
   public function getServerVersion()
   {
      static $version = null;
      if (is_null($version)) {
         $version = PluginJamfAPIPro::getLobby()['version'];
      }
      return $version;
   }

   /**
    * Gets the API endpoint URL based on if it is using the classic or pro API.
    * @param string $endpoint The API endpoint.
    * @param bool $pro_api True if using the pro API.
    * @return string The full API endpoint URL.
    */
   public function getAPIUrl($endpoint, $pro_api = false)
   {
      if ($pro_api) {
         return "{$this->config['jssserver']}/uapi/{$endpoint}";
      }

      return "{$this->config['jssserver']}/JSSResource/{$endpoint}";
   }

   /**
    * Set the username and password for the specified curl connection.
    * @param resource $curl The curl handle.
    */
   public function setCurlAuth(&$curl)
   {
      if (isset($this->config['jssuser']) && !empty($this->config['jssuser'])) {
         curl_setopt($curl, CURLOPT_USERPWD, $this->config['jssuser'] . ':' . $this->config['jsspassword']);
      }
   }
}
