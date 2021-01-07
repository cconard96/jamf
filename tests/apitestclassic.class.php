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

class PluginJamfApiTestClassic extends PluginJamfAPIClassic {

   /**
    * Convert a standard Classic API endpoint to filename which points to an appropriate sample response file.
    *
    * This does not guarantee that the file actually exists.
    * @param string $endpoint The Classic API endpoint
    * @param string $response_type The content type expected for the response
    * @return string The name of the expected sample response file
    * @since 2.0.0
    */
   private static function endpointToFilename(string $endpoint, $response_type): string
   {
      $response_ext = $response_type === 'application/xml' ? 'xml' : 'json';
      return GLPI_ROOT . '/plugins/jamf/tools/samples/classic_api/' . $endpoint . '.' . $response_ext;
   }

   /**
    * {@inheritDoc}
    */
   protected static function get(string $endpoint, $raw = false, $response_type = 'application/json')
   {
      $file = self::endpointToFilename($endpoint, $response_type);
      if (!file_exists($file)) {
         return null;
      }
      $handle = fopen($file, 'rb');
      $response = fread($handle, filesize($file));
      fclose($handle);

      if ($response_type === 'application/xml') {
         return simplexml_load_string($response);
      }
      return json_decode($response, true);
   }

   /**
    * {@inheritDoc}
    */
   protected static function add(string $endpoint, string $payload)
   {
      // No-Op
      return true;
   }

   /**
    * {@inheritDoc}
    */
   protected static function update(string $endpoint, array $data)
   {
      // No-Op
      return true;
   }

   /**
    * {@inheritDoc}
    */
   protected static function delete(string $endpoint)
   {
      // No-Op
      return true;
   }
}