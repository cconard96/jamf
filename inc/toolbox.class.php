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

class PluginJamfToolbox {

   public static function getHumanReadableTimeDiff($start, $end = null) {
      if (is_null($start) || $start == 'NULL') {
         return null;
      }
      if (is_null($end)) {
         $end = $_SESSION['glpi_currenttime'];
      }
      $diff = date_diff(date_create($start), date_create($end));
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
}