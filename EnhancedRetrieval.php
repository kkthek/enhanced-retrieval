<?php
use DIQA\FacetedSearch\FSGlobalFunctions;
/*
 * Copyright (C) Vulcan Inc., DIQA-Projektmanagement GmbH
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
 * @ingroup EnhancedRetrieval
 * @defgroup EnhancedRetrieval Enhanced retrieval
 * @author: Kai Kühn
 *
 * Created on: 27.01.2009
 */
if( !defined( 'MEDIAWIKI' ) ) {
	echo("This file is an extension to the MediaWiki software and cannot be used standalone.\n");
	die(1);
}


define('US_SEARCH_EXTENSION_VERSION', '1.9.3');

define('US_HIGH_TOLERANCE', 0);
define('US_LOWTOLERANCE', 1);
define('US_EXACTMATCH', 2);

#require_once 'src/FacetedSearch/FS_GlobalFunctions.php';

global $wgExtensionCredits;
$wgExtensionCredits['other'][] = array(
        'name' => 'Enhanced Retrieval extension',
        'version' => US_SEARCH_EXTENSION_VERSION,
		'license-name' => 'GPL-2.0+',
        'author'=>"Vulcan Inc. Maintained by [http://www.diqa-pm.com DIQA].", 
        'url' => 'https://www.semantic-mediawiki.org/wiki/Enhanced_Retrieval',
        'description' => 'Enhanced retrieval provides a faceted search for Mediawiki and SMW. 
It requires a SOLR server as backend.',
);

global $wgJobClasses;
$wgJobClasses['UpdateSolrJob'] = 'DIQA\FacetedSearch\UpdateSolrJob';

global $wgExtensionFunctions, $wgExtensionMessagesFiles;
$dir = dirname(__FILE__).'/';
$wgExtensionFunctions[] = 'wfUSSetupExtension';
$wgExtensionMessagesFiles['FacetedSearch'] = $dir . '/src/FacetedSearch/Languages/FS_Messages.php'; // register messages (requires MW=>1.11)

/**
 * Initializes PermissionACL extension
 *
 * @return unknown
 */
function wfUSSetupExtension() {
	
	require_once 'src/FacetedSearch/FS_Settings.php';
	
	global $IP, $wgScriptPath;
	global $fsgScriptPath, $fsgIP;
	global $ergIP, $ergScriptPath;
	
	$ergIP = $IP . '/extensions/EnhancedRetrieval';
	$ergScriptPath = $wgScriptPath . '/extensions/EnhancedRetrieval';
	
	###
	# This is the path to your installation of the Faceted Search as seen from the
	# web. Change it if required ($wgScriptPath is the path to the base directory
	# of your wiki). No final slash.
	##
	$fsgScriptPath = $wgScriptPath . '/extensions/EnhancedRetrieval';
	
	###
	# This is the installation path of the extension
	$fsgIP = $IP.'/extensions/EnhancedRetrieval';
	
	global $wgSpecialPages;
	$wgSpecialPages['Search'] = array('DIQA\FacetedSearch\Specials\FSFacetedSearchSpecial');
	
	// Set up Faceted Search
	FSGlobalFunctions::setupFacetedSearch();
	
	
	return true;
}





