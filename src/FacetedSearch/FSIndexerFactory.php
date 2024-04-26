<?php
namespace DIQA\FacetedSearch;

use DIQA\FacetedSearch\Exceptions\FSException;

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
 * @ingroup FacetedSearch
 *
 * This file contains the factory class for the Faceted Search Indexer.
 *
 * @author Thomas Schweitzer
 * Date: 22.02.2011
 *
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}

/**
 * This factory creates indexer objects that encapsulate access to index servers
 * for faceted search.
 *
 * @author Thomas Schweitzer
 *
 */
class FSIndexerFactory {

    /**
     * Creates an indexer object which is described by the given configuration.
     *
     * @param array|null $indexerConfig
     *      This array has the following key value pairs:
     *      'indexer' => 'SOLR'
     *      'source'  => 'SMWDB'
     *      'proxyHost' => hostname of the proxy
     *      'proxyServlet' => the part of the URL after the port
     *      'indexerHost'  => hostname of the indexer as seen from the wiki server
     *      'indexerPort'  => port number of the indexer as seen from the wiki server
     *      If <null> (default), the global configuration which is stored in the
     *      variable $fsgFacetedSearchConfig is used.
     *
     * @return FSIndexerInterface
     *      An instance of the interface IFSIndexer
     * @throws FSException
     *      INCOMPLETE_CONFIG: If the configuration is incomplete
     *      UNSUPPORTED_VALUE: If a value for a field in the configuration is not supported
     */
	public static function create(array $indexerConfig = null) {
		if (is_null($indexerConfig)) {
			global $fsgFacetedSearchConfig;
			$indexerConfig = $fsgFacetedSearchConfig ?? [];
		}
		// Check if the configuration is complete
		$expKeys = array('indexer' => 0, 'source' => 0, 'proxyHost' => 0,
		                 'proxyServlet' => 0, 'indexerHost' => 0,
		                 'indexerPort' => 0);
		$missingKeys = array_diff_key($expKeys, $indexerConfig);
		if (count($missingKeys) > 0) {
			$missingKeys = "The following keys are missing: " . json_encode(array_keys($missingKeys));
			throw new FSException(FSException::INCOMPLETE_CONFIG, $missingKeys);
		}

		// Check if the configuration is supported
		$unsupported = [];
		if ($indexerConfig['indexer'] != 'SOLR') {
			$unsupported["indexer"] = $indexerConfig['indexer'];
		}
		if ($indexerConfig['source'] != 'SMWDB') {
			$unsupported["source"] = $indexerConfig['source'];
		}
		if (count($unsupported) > 0) {
			$unsupported = "The following values are not supported: " . json_encode($unsupported);
			throw new FSException(FSException::UNSUPPORTED_VALUE, $unsupported);
		}

		// Create the indexer object
        return new FSSolrSMWDB($indexerConfig['indexerHost'],
                               $indexerConfig['indexerPort'],
                               $indexerConfig['indexerUser'],
                               $indexerConfig['indexerPass'],
                               $indexerConfig['indexerCore']);
	}
}
