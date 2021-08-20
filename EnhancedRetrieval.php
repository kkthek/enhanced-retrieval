<?php
use DIQA\FacetedSearch\FSGlobalFunctions;
use DIQA\FacetedSearch\Proxy\SolrProxy\ConfigLoader;
use DIQA\FacetedSearch\Specials\FSFacetedSearchSpecial;

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

define('ER_EXTENSION_VERSION', '2.3');
wfLoadExtension( 'EnhancedRetrieval', dirname( __FILE__ ) . '/extension.json' );

global $wgJobClasses;
$wgJobClasses['UpdateSolrJob'] = 'DIQA\FacetedSearch\UpdateSolrJob';

global $wgExtensionFunctions, $wgExtensionMessagesFiles;
$dir = dirname(__FILE__).'/';
$wgExtensionFunctions[] = 'wfERSetupExtension';
$wgExtensionMessagesFiles['FacetedSearch'] = $dir . '/src/FacetedSearch/Languages/FSMessages.php';

global $wgHooks;
$wgHooks['ParserFirstCallInit'][] = 'DIQA\FacetedSearch\FSGlobalFunctions::initializeBeforeParserInit';
$wgHooks['fs_extendedFilters'][] = 'DIQA\FacetedSearch\FacetedCategoryFilter::addFilter';

global $wgAPIModules;
$wgAPIModules['fs_dialogapi'] = 'DIQA\FacetedSearch\Util\DialogAjaxAPI';

require_once 'DefaultSettings.php';

/**
 * Initializes the extension
 */
function wfERSetupExtension() {

	###
	# This array configures the indexer that is used for faceted search. It has the
	# following key-value pairs:
	# indexer: Type of the indexer. Currently only 'SOLR' is supported.
	# source:  The source for indexing semantic data. Currently only the database
	#          of SMW is supported: 'SMWDB'
	# proxyHost: Protocol and name or IP address of the proxy to the indexer server
	#          as seen from the client e.g. 'http://www.mywiki.com' or $wgServer
	# proxyPort: The port number of the indexer server e.g. 8983 as seen from the
	#          client.
	#          If the solrproxy is used this can be omitted.
	# proxyServlet: Servlet of the indexer proxy as seen from the client. If the
	#          solrproxy is used it should be
	#          "$wgScriptPath/rest.php/EnhancedRetrieval/v1/proxy"
	#          If the indexer is addressed directly it should be '/solr/select' (for SOLR)
	# indexerHost: Name or IP address of the indexer server as seen from the wiki server
	#          e.g. 'localhost'
	#          If the solrproxy is used and the indexer host (SOLR) is different from
	#          'localhost', i.e. SOLR is running on another machine than the wiki server,
	#          the variable $SOLRhost must be set in LocalSettings.php
	# indexerPort: The port number of the indexer server e.g. 8983 as seen from the
	#          wiki server.
	#          If the solrproxy is used and the port of the indexer host (SOLR) is
	#          different from 8983, the variable $SOLRport must be set in LocalSettings.php.
	##
    ConfigLoader::loadConfig();

    if (file_exists(__DIR__ . '/custom.php')) {
        require_once(__DIR__ . '/custom.php');
    }

    global $SOLRProxyDebug;
    if (isset($SOLRProxyDebug) && $SOLRProxyDebug === true) {
        error_reporting( E_ALL );
        ini_set( 'display_startup_errors', 1 );
        ini_set( 'display_errors', 1 );
    }

	global $SOLRhost, $SOLRport, $SOLRuser, $SOLRpass, $SOLRcore;

	global $fsgFacetedSearchConfig, $wgServer, $wgScriptPath;
	if (!isset($fsgFacetedSearchConfig)) {
		$fsgFacetedSearchConfig = array(
		    'indexer' => 'SOLR',
		    'source'  => 'SMWDB',
		    'proxyHost'    => $wgServer,
		//	'proxyPort'    => 8983,
			'proxyServlet' => "$wgScriptPath/rest.php/EnhancedRetrieval/v1/proxy",
		    'indexerHost' => $SOLRhost,
			'indexerPort' => $SOLRport,
			'indexerUser' => $SOLRuser,
			'indexerPass' => $SOLRpass,
			'indexerCore' => $SOLRcore
		);
	}

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
	$wgSpecialPages['Search'] = function() {
	    return new FSFacetedSearchSpecial();
	};

	// Set up Faceted Search
	FSGlobalFunctions::setupFacetedSearch();
	
	
	return true;
}