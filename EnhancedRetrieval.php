<?php

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
class EnhancedRetrieval {

    public static function initExtension($credits = []) {
        if (!defined('ER_EXTENSION_VERSION') && isset($credits['version'])) {
            define('ER_EXTENSION_VERSION', $credits['version']);
        }
        
        // https://phabricator.wikimedia.org/T212738
        if ( !defined( 'MW_VERSION' ) ) {
            define( 'MW_VERSION', $GLOBALS['wgVersion'] );
        }
    }

    /**
     * Setup and initialization
     *
     * @note $wgExtensionFunctions variable is an array that stores
     * functions to be called after most of MediaWiki initialization
     * has finalized
     *
     * @see https://www.mediawiki.org/wiki/Manual:$wgExtensionFunctions
     */
    public static function onExtensionFunction() {
        global $fsgSolrProxyDebug;
        if (isset($fsgSolrProxyDebug) && $fsgSolrProxyDebug === true) {
            error_reporting( E_ALL );
            ini_set( 'display_startup_errors', 1 );
            ini_set( 'display_errors', 1 );
        }

        global $fsgFacetedSearchConfig;
        if (!isset($fsgFacetedSearchConfig)) {
            global $fsgSolrHost, $fsgSolrPort, $fsgSolrUser, $fsgSolrPass, $fsgSolrCore;
            global $wgServer, $wgScriptPath;
            $fsgFacetedSearchConfig = array(
                'indexer' => 'SOLR',
                'source'  => 'SMWDB',
                'proxyHost'    => $wgServer,
                //	'proxyPort'    => 8983,
                'proxyServlet' => "$wgScriptPath/rest.php/EnhancedRetrieval/v1/proxy",
                'indexerHost' => $fsgSolrHost,
                'indexerPort' => $fsgSolrPort,
                'indexerUser' => $fsgSolrUser,
                'indexerPass' => $fsgSolrPass,
                'indexerCore' => $fsgSolrCore
            );
        }

        return true;
    }
   
}