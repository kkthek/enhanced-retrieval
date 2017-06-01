<?php

use DIQA\FacetedSearch\FSIndexerFactory;
/*
 * Copyright (C) DIQA-Projektmanagement GmbH 2013
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
 * Recreates all the semantic data in the SOLR server.
 * Note: Does NOT remove the semantic data before.
 *
 * @author Kai Kuehn
 * @ingroup EnhancedRetrieval Maintenance
 */

$optionsWithArgs = array( 'd', 's', 'e', 'n', 'b', 'f', 'startidfile', 'server', 'page' ); // -d <delay>, -s <startid>, -e <endid>, -n <numids>, --startidfile <startidfile> -b <backend>

require_once ( getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . "/maintenance/commandLine.inc"
	: dirname( __FILE__ ) . '/../../../maintenance/commandLine.inc' );

global $smwgEnableUpdateJobs, $wgServer, $wgTitle;
$wgTitle = Title::newFromText( 'SMW_refreshData.php' );
$smwgEnableUpdateJobs = false; // do not fork additional update jobs while running this script

// when indexing everything, dependent pages do not need special treatment
global $fsUpdateOnlyCurrentArticle;
$fsUpdateOnlyCurrentArticle = true;

if ( isset( $options['server'] ) ) {
	$wgServer = $options['server'];
}

if ( array_key_exists( 'd', $options ) ) {
	$delay = intval( $options['d'] ) * 100000; // sleep 100 times the given time, but do so only each 100 pages
} else {
	$delay = false;
}

if ( isset( $options['page'] ) ) {
	$pages = explode( '|', $options['page'] );
} else {
	$pages = false;
}

$writeToStartidfile = false;
if ( array_key_exists( 's', $options ) ) {
	$start = max( 1, intval( $options['s'] ) );
} elseif ( array_key_exists( 'startidfile', $options ) ) {
	if ( !is_writable( file_exists( $options['startidfile'] ) ? $options['startidfile'] : dirname( $options['startidfile'] ) ) ) {
		die("Cannot use a startidfile that we can't write to.\n");
	}
	$writeToStartidfile = true;
	if ( is_readable( $options['startidfile'] ) ) {
		$start = max( 1, intval( file_get_contents( $options['startidfile'] ) ) );
	} else {
		$start = 1;
	}
} else {
	$start = 1;
}

if ( array_key_exists( 'e', $options ) ) { // Note: this might reasonably be larger than the page count
	$end = intval( $options['e'] );
} elseif ( array_key_exists( 'n', $options ) ) {
	$end = $start + intval( $options['n'] );
} elseif ( array_key_exists( 'f', $options ) ) {
	$title = Title::newFromText($options['f']);
	$start = $title->getArticleID();
	$end = $title->getArticleID();
} else {
	$query = "SELECT MAX(page_id) as maxid FROM page";
	$db =& wfGetDB( DB_SLAVE );
	$res = $db->query( $query );
	if($db->numRows( $res ) > 0) {
		while($row = $db->fetchObject($res)) {
			$end = $row->maxid;
		}
	}
}


$verbose = array_key_exists( 'v', $options );
$debug = array_key_exists( 'x', $options );

$filterarray = array();
if (  array_key_exists( 'c', $options ) ) {
	$filterarray[] = NS_CATEGORY;
}
if (  array_key_exists( 'p', $options ) ) {
	$filterarray[] = SMW_NS_PROPERTY;
}
if (  array_key_exists( 't', $options ) ) {
	$filterarray[] = SMW_NS_TYPE;
}
$filter = count( $filterarray ) > 0 ? $filterarray : false;


$linkCache = LinkCache::singleton();
$num_files = 0;
if ( $pages == false ) {
	print "Refreshing all semantic data in the SOLR server!\n---\n" .
	" Some versions of PHP suffer from memory leaks in long-running scripts.\n" .
	" If your machine gets very slow after many pages (typically more than\n" .
	" 1000) were refreshed, please abort with CTRL-C and resume this script\n" .
	" at the last processed page id using the parameter -s (use -v to display\n" .
	" page ids during refresh). Continue this until all pages were refreshed.\n---\n";
	print "Processing all IDs from $start to " . ( $end ? "$end" : 'last ID' ) . " ...\n";
	new SMWDIProperty("_wpg");
	$id = $start;
	while ( ( ( !$end ) || ( $id <= $end ) ) && ( $id > 0 ) ) {
		$title = Title::newFromID($id);
		if ( $verbose ) {
			print sprintf("(%s) Processing ID %s ... [%s]\n", $num_files, $id, !is_null($title) ? $title->getPrefixedText() : "-");
		}
		$id++;
		if (is_null($title)) continue;
		$indexer = FSIndexerFactory::create(null, $debug);
		$indexer->updateIndexForArticle(new WikiPage($title));
		if ( ( $delay !== false ) && ( ( $num_files + 1 ) % 100 === 0 ) ) {
			usleep( $delay );
		}
		$num_files++;
		$linkCache->clear(); // avoid memory leaks
	}
	if ( $writeToStartidfile ) {
		file_put_contents( $options['startidfile'], "$id" );
	}
	print "$num_files IDs refreshed.\n";
} else {
	print "Refreshing specified pages!\n\n";
	
	foreach ( $pages as $page ) {
		
		if ( $verbose ) {
			print sprintf("(%s) Processing page %s ... \n", $num_files, $page);
		}
		
		$title = Title::newFromText( $page );
		
		if ( !is_null( $title ) ) {
			$indexer = FSIndexerFactory::create();
			$indexer->updateIndexForArticle(new WikiPage($title));
		}
		
		$num_files++;
	}
	
	print "$num_files pages refreshed.\n";
}
