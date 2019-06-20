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
 * JamfJamfConnection class
 */
class JamfJamfConnection {
   private $config;

   public function __construct()
   {
      global $DB;

      $iterator = $DB->request([
         'SELECT'    => [
            'jssserver',
            'jssuser',
            'jsspassword'
         ],
         'FROM'      => 'glpi_plugin_jamf_configs',
         'WHERE'     => ['id' => 1]
      ]);
      if (count($iterator)) {
         $this->config = $iterator->next();
         $this->config['jsspassword'] = Toolboox::decrypt($this->config['jsspassword', GLPI_KEY]);
      }
   }

   public function setConnectionConfig($jssserver, $jssuser, $jsspassword)
   {
      global $DB;

      $enc = Toolbox::encrypt($jsspassword, GLPI_KEY);
      $DB->update('glpi_plugin_jamf_configs', [
         'jssserver' => $jssserver,
         'jssuser' => $jssuser,
         'jsspassword' => $enc
      ], ['id' => 1]);
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

   public function setCurlAuth()
   {
      curl_setopt($ch, CURLOPT_USERPWD, $this->config['jssuser'] . ":" . $this->config['jsspassword']);
   }
}
