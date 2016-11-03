<?php
/*
 * Copyright (C) Vulcan Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */
/**
 * @file
 * @ingroup FacetedSearchStorage
 * 
 * This file provides the access to the SQL database tables that are
 * used by the Faceted Search.
 *
 * @author Thomas Schweitzer
 * Date: 28.11.2011
 * 
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}


/**
 * This class encapsulates all methods that care about the database tables of
 * the Faceted Search extension. This is the implementation for the SQL database.
 *
 */
class FSStorageSQL {
	
	const NAMESPACE_TABLE = 'fs_namespace';
	/**
	 * Initializes the database tables of the FacetedSearch extensions.
	 * These are:
	 * - fs_namespace:
	 * 		a map of all namespace IDs with their names
	 *
	 */
	public function initDatabaseTables($verbose = true) {

		$db =& wfGetDB( DB_MASTER );

		FSDBHelper::reportProgress("Setting up FacetedSearch ...\n",$verbose);

		// fs_namespace:
		//		map from namespace ids to namespace names
		$table = $db->tableName(self::NAMESPACE_TABLE);

		FSDBHelper::setupTable($table, array(
            'namespace_id' 	=> 'INT(8) UNSIGNED NOT NULL PRIMARY KEY',
            'name' 			=> 'Text CHARACTER SET utf8 COLLATE utf8_bin'),
		$db, $verbose);
		FSDBHelper::reportProgress("   ... done!\n",$verbose);
	}
	
	public function dropDatabaseTables($verbose = true) {
		global $wgDBtype;
		
		FSDBHelper::reportProgress("Deleting all database content and tables generated by Faceted Search ...\n\n",$verbose);
		$db =& wfGetDB( DB_MASTER );
		$tables = array(self::NAMESPACE_TABLE);
		
		foreach ($tables as $table) {
			$name = $db->tableName($table);
			$db->query('DROP TABLE' . ($wgDBtype=='postgres'?'':' IF EXISTS'). $name, 'FSStorageSQL::dropDatabaseTables');
			FSDBHelper::reportProgress(" ... dropped table $name.\n", $verbose);
		}
		FSDBHelper::reportProgress("All data removed successfully.\n",$verbose);
	}
	
	
	/**
	 * Creates the map of namespace IDs and their name in the current language.
	 */
	public function updateNamespaceTable() {
		global $wgContLang;
		$namespaces = $wgContLang->getNamespaceIds();
		
		$db =& wfGetDB( DB_MASTER );
		
		foreach ($namespaces as $name => $id) {
			$db->replace($db->tableName(self::NAMESPACE_TABLE), null, array(
	            'name'      	=> $name ,
	            'namespace_id'	=> $id));
		}
		
	}
	
}

class FSDBHelper {

   /**
     * Make sure the table of the given name has the given fields, provided
     * as an array with entries fieldname => typeparams. typeparams should be
     * in a normalised form and order to match to existing values.
     *
     * The function returns an array that includes all columns that have been
     * changed. For each such column, the array contains an entry
     * columnname => action, where action is one of 'up', 'new', or 'del'
     * If the table was already fine or was created completely anew, an empty
     * array is returned (assuming that both cases require no action).
     *
     * NOTE: the function partly ignores the order in which fields are set up.
     * Only if the type of some field changes will its order be adjusted explicitly.
     *
     * @param string $primaryKeys
     *      This optional string specifies the primary keys if there is more
     *      than one. This is a comma separated list of column names. The primary
     *      keys are not altered, if the table already exists.
     */
    public static function setupTable($table, $fields, $db, $verbose, $primaryKeys = "") {
        global $wgDBname;
        FSDBHelper::reportProgress("Setting up table $table ...\n",$verbose);
        if ($db->tableExists($table) === false) { // create new table
            $sql = 'CREATE TABLE ' . $wgDBname . '.' . $table . ' (';
            $first = true;
            foreach ($fields as $name => $type) {
                if ($first) {
                    $first = false;
                } else {
                    $sql .= ',';
                }
                $sql .= $name . '  ' . $type;
            }
            if (!empty($primaryKeys)) {
                $sql .= ", PRIMARY KEY(".$primaryKeys.")";
            }
            $sql .= ') ENGINE=MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin';
            $db->query( $sql, 'FSDBHelper::setupTable' );
            FSDBHelper::reportProgress("   ... new table created\n",$verbose);
            return array();
        } else { // check table signature
            FSDBHelper::reportProgress("   ... table exists already, checking structure ...\n",$verbose);
            $res = $db->query( 'DESCRIBE ' . $table, 'FSDBHelper::setupTable' );
            $curfields = array();
            $result = array();
            while ($row = $db->fetchObject($res)) {
                $type = strtoupper($row->Type);
                if ($row->Null != 'YES') {
                    $type .= ' NOT NULL';
                }
                $curfields[$row->Field] = $type;
            }
            $position = 'FIRST';
            foreach ($fields as $name => $type) {
                if ( !array_key_exists($name,$curfields) ) {
                    FSDBHelper::reportProgress("   ... creating column $name ... ",$verbose);
                    $db->query("ALTER TABLE $table ADD `$name` $type $position", 'FSDBHelper::setupTable');
                    $result[$name] = 'new';
                    FSDBHelper::reportProgress("done \n",$verbose);
                } elseif ($curfields[$name] != $type && stripos($type, "primary key") === false) {
                // Changing primary keys throws an error
                    FSDBHelper::reportProgress("   ... changing type of column $name from '$curfields[$name]' to '$type' ... ",$verbose);
                    $db->query("ALTER TABLE $table CHANGE `$name` `$name` $type $position", 'FSDBHelper::setupTable');
                    $result[$name] = 'up';
                    $curfields[$name] = false;
                    FSDBHelper::reportProgress("done.\n",$verbose);
                } else {
                    FSDBHelper::reportProgress("   ... column $name is fine\n",$verbose);
                    $curfields[$name] = false;
                }
                $position = "AFTER $name";
            }
            foreach ($curfields as $name => $value) {
                if ($value !== false) { // not encountered yet --> delete
                    FSDBHelper::reportProgress("   ... deleting obsolete column $name ... ",$verbose);
                    $db->query("ALTER TABLE $table DROP COLUMN `$name`", 'FSDBHelper::setupTable');
                    $result[$name] = 'del';
                    FSDBHelper::reportProgress("done.\n",$verbose);
                }
            }
            FSDBHelper::reportProgress("   ... table $table set up successfully.\n",$verbose);
            return $result;
        }
    }
    
   /**
     * Print some output to indicate progress. The output message is given by
     * $msg, while $verbose indicates whether or not output is desired at all.
     */
    public static function reportProgress($msg, $verbose) {
        if (!$verbose) {
            return;
        }
        if (ob_get_level() == 0) { // be sure to have some buffer, otherwise some PHPs complain
            ob_start();
        }
        print $msg;
        ob_flush();
        flush();
    }
    
}

