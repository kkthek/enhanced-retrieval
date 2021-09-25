<?php
namespace DIQA\FacetedSearch;

use ForeignTitle;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use User;
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

    /**
     * Called when semantic data is refreshed.
     *
     * @param SMWStore $store
     * @param SemanticData $semanticData
     * @return bool
     */
    public static function onUpdateDataAfter(SMWStore $store, SemanticData $semanticData) {
        $wikiTitle = $semanticData->getSubject()->getTitle();
        return self::updateArticle($wikiTitle);
    }

    /**
     * Called when article is saved. Not necessary if namespace of article may contain semantic links.
     *
     * @param WikiPage $wikiPage
     * @param UserIdentity $user
     * @param string $summary
     * @param int $flags
     * @param RevisionRecord $revisionRecord
     * @param EditResult $editResult
     * @return bool
     */
    public static function onPageSaveComplete( WikiPage $wikiPage, UserIdentity $user, string $summary,
                                               int $flags, RevisionRecord $revisionRecord, EditResult $editResult ) {
        $wikiTitle = $wikiPage->getTitle();

        global $smwgNamespacesWithSemanticLinks;
        if (isset($smwgNamespacesWithSemanticLinks[$wikiTitle->getNamespace()]) &&
            $smwgNamespacesWithSemanticLinks[$wikiTitle->getNamespace()] === true) {
            return; // already updated in onUpdateDataAfter
        }
        return self::updateArticle($wikiTitle);

    }

    /**
     * Called when image upload is complete.
     *
     * @param $image
     * @return bool
     */
    public static function onUploadComplete( &$image ) {
        global $wgUser;
        try {
            $wikiPage = new WikiPage($image->getLocalFile()->getTitle());
            $indexer = FSIndexerFactory::create();
            $indexer->updateIndexForArticle($wikiPage, $wgUser, "");
        } catch(\Exception $e) {
            wfDebugLog("EnhancedRetrieval", "Could not update article in SOLR. Reason: ".$e->getMessage());
        }
        return true;
    }

    /**
     * This function is called after an article was imported via
     * Special:Import.
     * It starts an update of the index for the given title.
     *
     * @param Title $title
     * 		Title under which the revisions were imported
     * @param ForeignTitle $origTitle
     *		Title provided by the XML file
     * @param int $revCount
     *		Number of revisions in the XML file
     * @param int $sRevCount
     *		Number of sucessfully imported revisions
     * @param array $pageInfo
     *		associative array of page information
     * @return bool
     */
    public static function onAfterImportPage(Title $title, ForeignTitle $origTitle, $revCount, $sRevCount, $pageInfo) {
        try {
            $indexer = FSIndexerFactory::create();
            $wikiPage = new WikiPage($title);
            $indexer->updateIndexForArticle($wikiPage);
        } catch(\Exception $e) {
            wfDebugLog("EnhancedRetrieval", "Could not update article on import operation in SOLR. Reason: ".$e->getMessage());
        }
        return true;
    }

    /**
     * This function is called after an article was moved.
     * It starts an update of the index for the given article.
     *
     * @param Title $title
     * @param Title $newTitle
     * @param User $user
     * @param numeric $oldid
     * @param numeric $newid
     * @return bool
     *
     */
    public static function onTitleMoveComplete(Title &$title, Title &$newTitle, $user, $oldid, $newid): bool
    {
        try {
            $indexer = FSIndexerFactory::create();
            $indexer->updateIndexForMovedArticle($oldid, $newid);
        } catch(\Exception $e) {
            wfDebugLog("EnhancedRetrieval", "Could not move article in SOLR. Reason: ".$e->getMessage());
        }
        return true;
    }

    /**
     * This method is called, when an article is deleted. It is removed from
     * the index.
     *
     * @param WikiPage $article
     * @param User $user
     * @param string $reason
     * @return bool
     *
     */
    public static function onArticleDelete(WikiPage &$article, User &$user, string &$reason): bool
    {
        try {
            $indexer = FSIndexerFactory::create();
            $indexer->deleteDocument($article->getID());
        } catch(\Exception $e) {
            wfDebugLog("EnhancedRetrieval", "Could not delete article in SOLR. Reason: ".$e->getMessage());
        }
        return true;
    }

    /**
     * This method called when a revision is approved.
     * Only if ApprovedRev extension is installed.
     *
     * @param Parser $parser
     * @param Title $title
     * @param int $rev_id
     * @return bool
     */
    public static function onRevisionApproved($parser, $title, $rev_id): bool
    {
        $store = MediaWikiServices::getInstance()->getRevisionStore();
        $revision = $store->getRevisionByTitle( $title, $rev_id);
        if (is_null($revision)) {
            return true;
        }

        $content = $revision->getContent(SlotRecord::MAIN, RevisionRecord::RAW)->serialize();
        try {
            $indexer = FSIndexerFactory::create();
            $indexer->updateIndexForArticle(new WikiPage($title), null, $content);
        } catch(\Exception $e) {
            wfDebugLog("EnhancedRetrieval", "Could not update article in SOLR. Reason: ".$e->getMessage());
        }
        return true;
    }

    //--- Private methods ---

    /**
     * Updates article in SOLR backend.
     *
     * @param Title $wikiTitle
     * @return bool
     */
    private static function updateArticle(Title $wikiTitle): bool
    {
        $store = MediaWikiServices::getInstance()->getRevisionStore();
        $revision = $store->getRevisionByTitle($wikiTitle);
        if (is_null($revision)) {
            return true;
        }

        $content = $revision->getContent(SlotRecord::MAIN, RevisionRecord::RAW)->serialize();
        try {
            $indexer = FSIndexerFactory::create();
            $indexer->updateIndexForArticle(new WikiPage($wikiTitle), null, $content);
        } catch (\Exception $e) {
            wfDebugLog("EnhancedRetrieval", "Could not update article in SOLR. Reason: ".$e->getMessage());
        }
        return true;
    }
}
