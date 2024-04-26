<?php
namespace DIQA\FacetedSearch;

use MediaWiki\User\UserIdentity;
use WikiPage;

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
 * This file contains the interface for all faceted search indexers.
 * 
 * @author Thomas Schweitzer
 * Date: 22.02.2011
 * 
 */
if ( !defined( 'MEDIAWIKI' ) ) {
    die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}

/**
 * Interface of indexers for faceted search. The indexer indexes the semantic
 * data of the wiki and processes it for faceted search. Queries for facets are
 * answered by the indexer.
 * 
 * @author thsc
 *
 */
interface FSIndexerInterface {
    
    /**
     * Pings the server of the indexer and checks if it is responding.
     * @return bool
     * 	<true>, if the server is responding
     * 	<false> otherwise
     */
    public function ping();
    
    /**
     * Creates a full index of all available semantic data.
     * 
     * @param bool $clean
     * 		If <true> (default), the existing index is cleaned before the new
     * 		index is created.
     */
    public function createFullIndex($clean = true);
    
    /**
     * Deletes the complete index.
     */
    public function deleteIndex();
    
    /**
     * Updates the index for the given $wikiPage.
     * It retrieves all semantic data of the new version and adds it to the index.
     *
     * @param WikiPage $wikiPage
     *      The article that changed.
     * @param string $rawText
     *      Optional content of the article. If it is null, the content of $wikiPage is
     *      retrieved in this method.
     * @param array $messages
     *      User readible messages (out)
     * @param bool $debugMode
     *      Prints verbose output
     */
    public function updateIndexForArticle(WikiPage $wikiPage, $rawText = null,
                                          &$messages = [], bool $debugMode = false) : bool;

    /**
     * Updates the index for a moved article.
     *
     * @param int $oldid
     *         Old page ID of the article
     * @param int $newid
     *         New page ID of the article
     * @return bool
     *         <true> if the document in the index for the article was moved successfully
     *         <false> otherwise
     */
    public function updateIndexForMovedArticle($oldid, $newid);

    /**
     * Deletes the document with the ID $id from the index.
     *
     * @param string/int $id  ID of the document to delete.
     * @return bool
     *         <true> if the document was deleted successfully
     *         <false> otherwise
     *
     */
    public function deleteDocument($id);
}
