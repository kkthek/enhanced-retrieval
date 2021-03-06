<?php
use DIQA\FacetedSearch\FSGlobalFunctions;
use DIQA\SolrProxy\ConfigLoader;
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

define('ER_EXTENSION_VERSION', '2.2');

global $wgExtensionCredits;
$wgExtensionCredits['other'][] = array(
    'name' => 'Enhanced Retrieval extension',
    'version' => ER_EXTENSION_VERSION,
    'license-name' => 'GPL-2.0+',
    'author'=>"Vulcan Inc. Maintained by [http://www.diqa-pm.com DIQA].",
    'url' => 'https://www.semantic-mediawiki.org/wiki/Enhanced_Retrieval',
    'description' => 'Enhanced retrieval provides faceted search for MediaWiki and SMW. It requires a SOLR server as backend.',
);

global $wgJobClasses;
$wgJobClasses['UpdateSolrJob'] = 'DIQA\FacetedSearch\UpdateSolrJob';

global $wgExtensionFunctions, $wgExtensionMessagesFiles;
$dir = dirname(__FILE__).'/';
$wgExtensionFunctions[] = 'wfERSetupExtension';
$wgExtensionMessagesFiles['FacetedSearch'] = $dir . '/src/FacetedSearch/Languages/FS_Messages.php'; // register messages (requires MW=>1.11)

global $wgHooks;
$wgHooks['ParserFirstCallInit'][] = 'DIQA\FacetedSearch\FSGlobalFunctions::initializeBeforeParserInit';
$wgHooks['fs_extendedFilters'][] = 'DIQA\FacetedSearch\FacetedCategoryFilter::addFilter';
$wgHooks['UserLogout'][] = 'wfERLogout';

global $wgAPIModules;
$wgAPIModules['fs_dialogapi'] = 'DIQA\FacetedSearch\Util\DialogAjaxAPI';
$wgAPIModules['fs_userdataapi'] = 'DIQA\FacetedSearch\Util\UserDataAPI';

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
	#          "$wgScriptPath/extensions/EnhancedRetrieval/src/FacetedSearch/solrproxy.php"
	#          If the indexer is addressed directly it should be '/solr/select' (for SOLR)
	# indexerHost: Name or IP address of the indexer server as seen from the wiki server
	#          e.g. 'localhost'
	#          If the solrproxy is used and the indexer host (SOLR) is different from
	#          'localhost', i.e. SOLR is running on another machine than the wiki server,
	#          the variable $SOLRhost must be set in solrproxy.php.
	# indexerPort: The port number of the indexer server e.g. 8983 as seen from the
	#          wiki server.
	#          If the solrproxy is used and the port of the indexer host (SOLR) is
	#          different from 8983, the variable $SOLRport must be set in solrproxy.php.
	##
	require_once __DIR__ . '/proxy/src/SolrProxy/ConfigLoader.php';
    if (!file_exists(__DIR__ . '/proxy/env.php')
        && !file_exists(__DIR__ . '/../../env.php')) {
        ConfigLoader::loadConfig();
    }

	if (!isset($SOLRhost)) {
	    $SOLRhost = 'localhost';
	}
	if (!isset($SOLRport)) {
	    $SOLRport = 8983;
	}
	if (!isset($SOLRuser)) {
	    $SOLRuser = '';
	}
	if (!isset($SOLRpass)) {
	    $SOLRpass = '';
	}
	if (!isset($SOLRcore)) {
	    $SOLRcore = '';
	}

	global $SOLRhost, $SOLRport, $SOLRuser, $SOLRpass, $SOLRcore;
	global $wgServerHTTP, $wgScriptPath, $wgDBname;

	global $fsgFacetedSearchConfig, $wgServer, $wgScriptPath;
	if (!isset($fsgFacetedSearchConfig)) {
		$fsgFacetedSearchConfig = array(
		    'indexer' => 'SOLR',
		    'source'  => 'SMWDB',
		    'proxyHost'    => $wgServer,
		//	'proxyPort'    => 8983,
			'proxyServlet' => "$wgScriptPath/extensions/EnhancedRetrieval/src/FacetedSearch/solrproxy.php",
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

function wfERLogout() {
    global $wgUser;
    $proxyUrl = "/extensions/EnhancedRetrieval/src/FacetedSearch/solrproxy.php?logout=" . $wgUser->getId();
    global $wgServer, $wgScriptPath;
    header("Location: $wgServer$wgScriptPath$proxyUrl");
}
