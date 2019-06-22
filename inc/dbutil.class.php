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
 * DB utilities for Jamf plugin.
 * Contains several methods not yet available in the core for interacting with the DB and tables.
 */
 class PluginJamfDBUtil {

    //TODO Remove these methods as they become available in the core.

    /**
     * Builds a bulk insert statement
     *
     * @since 1.0.0
     * @param \QueryExpression|string $table  Table name
     * @param array                   $keys Array of column names
     * @param array                   $params Array of arrays of values.
     *                                    This will be replaced with an associative array for a PDO statement
     * @return string
     */
    public static function buildInsertBulk($table, array $columns, array &$params): string
    {
        global $DB;

        $query = "INSERT INTO " . $DB->quoteName($table) . " (";
        $fields = [];
        $keys   = [];
        foreach ($columns as $column) {
            $fields[] = $DB->quoteName($column);
        }
        $newparams = [];
        foreach ($params as $rowkey => $row) {
            $row_keys = [];
            foreach ($row as $arrkey => $value) {
                if ($value instanceof \QueryExpression) {
                    $row_keys[] = $value->getValue();
                } else {
                    $pdo_placeholder = ":{$columns[$arrkey]}_$rowkey";
                    $row_keys[] = $pdo_placeholder;
                    $newparams[$pdo_placeholder] = $value;
                }
            }
            $keys[] = $row_keys;
        }
        $params = $newparams;
        
        $query .= implode(', ', $fields) . ") VALUES ";
        foreach ($keys as $rowkey => $rowvalues) {
            $query .= '('.implode(',', $rowvalues).'),';
        }
        $query = rtrim($query, ',');
        return $query;
    }
    /**
     * Insert a row in the database
     *
     * @since 1.0.0
     * @param string $table  Table name
     * @param array  $params Query parameters ([field name => field value)
     * @return PDOStatement|boolean
     */
    public static function insertBulk(string $table, array $columns, array $values)
    {
        global $DB;

        $result = $DB->rawQuery(
            $this->buildInsertBulk($table, $columns, $values),
            $values
        );
        return $result;
    }

    /**
     * Insert a row in the database and die
     * (optionally with a message) if it fails
     *
     * @since 1.0.0
     * @param string      $table   Table name
     * @param array       $params  Query parameters ([field name => field value)
     * @param string|null $message Explanation of query
     * @return PDOStatement
     */
    public static function insertBulkOrDie(string $table, array $columns, array $values, $message = null): PDOStatement
    {
        global $DB;

        $insert = $this->buildInsertBulk($table, $columns, $values);
        $res = $DB->rawQuery($insert, $values);
        if (!$res) {
           //TRANS: %1$s is the description, %2$s is the query, %3$s is the error message
            $message = sprintf(
                __('%1$s - Error during the database query: %2$s - Error is %3$s'),
                $message,
                $insert,
                $DB->error()
            );
            if (isCommandLine()) {
                throw new \RuntimeException($message);
            } else {
                echo $message . "\n";
                die(1);
            }
        }
        return $res;
    }

    public static function dropTable(string $table)
    {
       global $DB;

       return $DB->rawQuery('DROP TABLE'.$DB->quoteName($table));
    }

    public static function dropTableOrDie(string $table, string $message = '')
    {
        global $DB;

        $res = $DB->rawQuery('DROP TABLE'.$DB->quoteName($table));
        if (!$res) {
           //TRANS: %1$s is the description, %2$s is the query, %3$s is the error message
            $message = sprintf(
                __('%1$s - Error during the database query: %2$s - Error is %3$s'),
                $message,
                $insert,
                $DB->error()
            );
            if (isCommandLine()) {
                throw new \RuntimeException($message);
            } else {
                echo $message . "\n";
                die(1);
            }
        }
        return $res;
    }
 }