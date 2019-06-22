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

/**
 * JamfConnection class
 */
class PluginJamfConnection {
   private $config;

   public function __construct()
   {
      global $DB;

      $jamf_config = Config::getConfigurationValues('plugin:Jamf', [
         'jssserver', 'jssuser', 'jsspassword']);
      $this->config = $jamf_config;
      $this->config['jsspassword'] = Toolbox::decrypt($this->config['jsspassword'], GLPIKEY);
   }

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

   private function getServer()
   {
      if (isset($this->config['jssserver'])) {
         return $this->config['jssserver'];
      } else {
         return null;
      }
   }

   private function getUser()
   {
      if (isset($this->config['jssuser'])) {
         return $this->config['jssuser'];
      } else {
         return null;
      }
   }

   public function getAPIUrl($endpoint)
   {
      return "{$this->config['jssserver']}/JSSResource/{$endpoint}";
   }

   public function setCurlAuth(&$curl)
   {
      if (isset($this->config['jssuser']) && (strlen($this->config['jssuser']) > 0)) {
         curl_setopt($curl, CURLOPT_USERPWD, $this->config['jssuser'] . ":" . $this->config['jsspassword']);
      }
   }
}
