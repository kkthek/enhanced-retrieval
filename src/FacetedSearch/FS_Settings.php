<?php
namespace DIQA\FacetedSearch;

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
 * @ingroup FacetedSearch
 * 
 * This file contains the settings for Faceted Search
 * 
 * @author Thomas Schweitzer
 * @author Kai Kühn
 * Date: 24.02.2011
 * 
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}




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
global $fsgFacetedSearchConfig, $wgServer, $wgScriptPath;
$fsgFacetedSearchConfig = array(
    'indexer' => 'SOLR',
    'source'  => 'SMWDB',
    'proxyHost'    => $wgServer,
//	'proxyPort'    => 8983,		
	'proxyServlet' => "$wgScriptPath/extensions/EnhancedRetrieval/src/FacetedSearch/solrproxy.php",
	'indexerHost' => 'localhost', // must be equal to $SOLRhost in solrproxy.php
	'indexerPort' => 8080         // must be equal to $SOLRport in solrproxy.php
);

###
# If this variable is <true>, a search in the MediaWiki search field is redirected
# to the faceted search special page. 
# If <false>, Enhanced Retrieval is installed. 
global $fsgFacetedSearchForMW;
$fsgFacetedSearchForMW = true;

###
# This is the pattern for the link that leads to the creation of new pages.
# Faceted Search checks if the entered search term is the name of an existing 
# article. If this is not the case it offers a link for creating this article. 
# The variable {article} will be replace by the actual article name.
# The link will be appended to the base URL like "http://localhost/mediawiki/index.php"
#
global $fsgCreateNewPageLink;
//$fsgCreateNewPageLink = "/Create_new_page?target={article}&redlink=1";
$fsgCreateNewPageLink = "/{article}?action=edit";
//$fsgCreateNewPageLink = "?todo=createnewarticle&newarticletitle={article}";

###
# If this variable is <true>, changed pages will be indexed incrementally i.e.
# when they are saved, moved or deleted.
# Setting it to <false> can make sense for example during the installation when
# SOLR is not yet running. 
global $fsgEnableIncrementalIndexer;
$fsgEnableIncrementalIndexer = true;

###
# If this variable is <true>, SMW's pre-defined properties will be indexed too. 
# 
# Single properties can be excluded from the facets via [[Ignore as facet::true]]
#
global $fsgIndexPredefinedProperties;
$fsgIndexPredefinedProperties = true;

#####
#
# BOOSTING
#
# Please note: If you want to use boosting, the field type "wiki" needs to be derived from 
# com.diqapm.solr.queryparser.DataWikiField (is default, see schema.xml of SOLR). However, this is more
# memory consuming and may lead to messages like "Your query yields too many results, please refine", especially
# on very short (or empty) search terms.
#
# You have two options:
#
#	1. Don't use boosting and replace "com.diqapm.solr.queryparser.DataWikiField" by "solr.TextField"
# 	2. Increase "maxBooleanClauses" in solrconfig.xml (requires more memory)
#
######

###
#
# The default boost index for all pages which are not boosted otherwise 
#
global $fsgDefaultBoost;
$fsgDefaultBoost = 1.0;

###
#
# All pages belonging to the categories are boosted by the given value.
#
# DO NOT add category prefix before the page title.
#
global $fsgCategoryBoosts;
$fsgCategoryBoosts = array(
	// e.g.
	// 'People' => 2.0
);

###
#
# All pages using one of the templates are boosted by the given value.
#
# DO NOT add template prefix before the page title.
#
global $fsgTemplateBoosts;
$fsgTemplateBoosts = array(
	//e.g. 
	//'MyTemplate' => 5.5
);

###
#
# All pages in the namespaces are boosted by the given value
#
global $fsgNamespaceBoosts;
$fsgNamespaceBoosts = array(
	// e.g.
	// SMW_NS_PROPERTY => 3.0
);

###
#
# All pages listed here are ignored by EnhancedRetrieval
#
# Please specify prefixed page title, e.g. Property:Name or Category:People
#
global $fsgBlacklistPages;
$fsgBlacklistPages = array(
	// e.g.
	// 'Category:People'
);

###
#
# Indicates if there's a Title-Property that should be used for relations
# Empty value means there is no.
#
global $fsgTitleProperty;
$fsgTitleProperty = "";

###
#
# Set of properties which are requested on search hits. Change this only
# if you want to extend the search
#
global $fsgExtraPropertiesToRequest;
$fsgExtraPropertiesToRequest = [];

###
#
# Show/hide UI parts
#
global $fsgShowSortOrder, $fsgShowCategories;
$fsgShowSortOrder = true;
$fsgShowCategories = true;

####
#
# Numeric property clusters
# 
# Example:
# $fsgNumericPropertyClusters['smwh_BaujahrMin_xsdvalue_d'] = 
# 			[ 'min' => -9999, 'max' => 9999, 'lowerBound' => 1700, 
#				'upperBound' => 2030, 'interval' => 10 ];
# means that the given property get a minimum cluster value of -9999, a maximum of 9999
# and 33 x 10year-clusters from 1700 - 2030. min and max is optional.
#
$fsgNumericPropertyClusters = [];