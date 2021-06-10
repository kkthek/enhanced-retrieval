<?php
namespace DIQA\FacetedSearch;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use WikiPage;
use Title;
use SMWStore;
use SMW\SemanticData;


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
 * This file contains the class FSIncrementalUpdater.
 * 
 * @author Thomas Schweitzer
 * Date: 23.02.2011
 * 
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}


/**
 * Listens to changes, deletes and moves of articles in MediaWiki and updates 
 * the index accordingly.
 * 
 * @author Thomas Schweitzer
 * 
 */
class FSIncrementalUpdater  {
	
	/**
	 * Constructor for  FSIncrementalUpdater
	 */		
	private function __construct() {
	}
	

	//--- getter/setter ---
	
	//--- Public methods ---
	
	/**
	 * Called when semantic data is refreshed.
	 * Note: This hook is only used on command-line!
	 * 
	 * @param SMWStore $store
	 * @param SemanticData $semanticData
	 * @return void|boolean
	 */
    public static function onUpdateDataAfter(SMWStore $store, SemanticData $semanticData) {
        $wikiTitle = $semanticData->getSubject()->getTitle();

        $store = MediaWikiServices::getInstance()->getRevisionStore();
        $revision = $store->getRevisionByTitle( $wikiTitle );
        if (is_null($revision)) {
            return;
        }

        $content = $revision->getContent(SlotRecord::MAIN, RevisionRecord::RAW)->serialize();
        $indexer = FSIndexerFactory::create();
        try {
            $indexer->updateIndexForArticle(new WikiPage($wikiTitle), null, $content);
        } catch(Exception $e) {
            // TODO error logging
        }
        return true;
    }
	
	public static function onUploadComplete( &$image ) {
		global $wgUser;
		$wikiPage = new WikiPage($image->getLocalFile()->getTitle());
		$indexer = FSIndexerFactory::create();
		try {
			$indexer->updateIndexForArticle($wikiPage, $wgUser, "");	
		} catch(\Exception $e) { }
		return true;
	}
	
	/**
	 * This function is called after an article was imported via
	 * Special:Import.
	 * It starts an update of the index for the given title.
	 * 
	 * @param Title $title
	 * 		Title under which the revisions were imported
	 * @param Title $origTitle
	 *		Title provided by the XML file
	 * @param int $revCount
	 *		Number of revisions in the XML file
	 * @param int $sRevCount
	 *		Number of sucessfully imported revisions
	 * @param array $pageInfo
	 *		associative array of page information
	 * 
	 */
	public static function onAfterImportPage($title, $origTitle, $revCount, $sRevCount, $pageInfo) {
		$indexer = FSIndexerFactory::create();
		$wikiPage = new WikiPage($title);
		try {
			$indexer->updateIndexForArticle($wikiPage);
		} catch(\Exception $e) { }
		return true;
	}
	
	/**
	 * This function is called after an article was moved.
	 * It starts an update of the index for the given article.
	 * 
	 * @param Title $title
	 * @param Title $newTitle
	 * @param unknown_type $user    not used
	 * @param unknown_type $oldid
	 * @param unknown_type $newid
	 * @return bool
	 * 		As a hook function it always returns <true>
	 */
	public static function onTitleMoveComplete(Title &$title, Title &$newTitle, $user, $oldid, $newid) {
		$indexer = FSIndexerFactory::create();
		$indexer->updateIndexForMovedArticle($oldid, $newid);
		return true;
	}
	
	/**
	 * This method is called, when an article is deleted. It is removed from
	 * the index.
	 *
	 * @param unknown_type $article
	 * @param unknown_type $user    not used
	 * @param unknown_type $reason  not used
	 * 
	 * @return bool
	 * 		As a hook function it always returns <true>
	 */
	public static function onArticleDelete(&$article, &$user, &$reason) {
		$indexer = FSIndexerFactory::create();
		$indexer->deleteDocument($article->getID());
		return true;
	}	
	
	/**
	 * This method called when a revision is approved.
	 * Only if ApprovedRev extension is installed.
	 * 
	 * @param Parser $parser
	 * @param Title $title
	 * @param int $rev_id
	 * @return void|boolean
	 */
    public static function onRevisionApproved($parser, $title, $rev_id) {
        $store = MediaWikiServices::getInstance()->getRevisionStore();
        $revision = $store->getRevisionByTitle( $title, $rev_id);
        if (is_null($revision)) {
            return;
        }

        $content = $revision->getContent(SlotRecord::MAIN, RevisionRecord::RAW)->serialize();
        $indexer = FSIndexerFactory::create();
        try {
            $indexer->updateIndexForArticle(new WikiPage($title), null, $content);
        } catch(Exception $e) {
            # TODO add error-logging
        }
        return true;
    }

	//--- Private methods ---
}
