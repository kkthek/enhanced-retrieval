<?php
namespace DIQA\FacetedSearch;

use Exception;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use SMW\DataTypeRegistry;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\PropertyRegistry;
use SMWDataItem;
use SMWDITime;
use Wikimedia\Rdbms\IDatabase;
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
 * This file contains the class FSSolrSMWDB. It creates the index from the database
 * tables of SMW.
 *
 * @author Thomas Schweitzer
 * @author Kai Kühn
 * Date: 22.02.2011
 *
 */
if ( !defined( 'MEDIAWIKI' ) ) {
    die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}

/**
 * This class is the indexer for the SMW database tables.
 *
 * @author thsc
 *
 */
class FSSolrSMWDB extends FSSolrIndexer {

    // --- Constants ---


     //--- Private fields ---


    /**
     * Creates a new FSSolrSMWDB indexer object.
     * @param string $host
     *         Name or IP address of the host of the server
     * @param int $port
     *         Server port of the Solr server
     * @param string $user
     * @param string $pass
     * @param string $indexCore SOLR core
     *
     */
    public function __construct($host, $port, $user = '', $pass = '', $indexCore = '') {
        parent::__construct($host, $port, $user, $pass, $indexCore);
    }

    /**
     * Updates the index for the given $wikiPage.
     * It retrieves all semantic data of the new version and adds it to the index.
     *
     * @param WikiPage $wikiPage
     *         The article that changed.
     * @param string $rawText
     *        Optional content of the article. If it is null, the content of $wikiPage is
     *        retrieved in this method.
     * @param array $messages
     *      User readable messages (out)
     * @param bool $debugMode
     *      Prints verbose output
     */
    public function updateIndexForArticle(WikiPage $wikiPage, $rawText = null,
                                          &$messages = [], bool $debugMode = false
                                          ) : bool {

        $doc = [];

        $pageTitle = $wikiPage->getTitle();
        $pagePrefixedTitle = $pageTitle->getPrefixedText();
        $pageID = $wikiPage->getId();
        if( $pageID == 0 ) {
            throw new Exception("invalid page ID for $pagePrefixedTitle");
        }

        global $fsgBlacklistPages;
        if (in_array($pagePrefixedTitle, $fsgBlacklistPages)) {
            throw new Exception("blacklisted page: $pagePrefixedTitle");
        }

        $pageNamespace = $pageTitle->getNamespace();
        $pageDbKey  = $pageTitle->getDBkey();
        $text = $rawText ?? $this->getText( $wikiPage, $doc, $messages );

        $doc['id'] = $pageID;
        $doc['smwh_namespace_id'] = $pageNamespace;
        $doc['smwh_title'] = $pageDbKey;
        $doc['smwh_full_text'] = $text;
        $doc['smwh_displaytitle'] = FacetedSearchUtil::findDisplayTitle( $pageTitle, $wikiPage );

        if ($this->retrieveSMWID($pageNamespace, $pageDbKey, $doc)) {
            $this->retrievePropertyValues($pageTitle, $doc);
            $this->indexCategories($pageTitle, $doc);
        }

        $options = [];
        $this->calculateBoosting( $wikiPage, $options, $doc );

        // call fs_saveArticle hook
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookContainer->run( 'fs_saveArticle', [ $text, &$doc ] );

        // Let the super class update the index
        $this->updateIndex( $doc, $options, $debugMode );

        return true;
    }

    /**
     * @throws Exception
     */
    private function getText(WikiPage $wikiPage, array &$doc, array &$messages ) : string {
        $pageTitle = $wikiPage->getTitle();
        $pageNamespace = $pageTitle->getNamespace();

        # DisplayTitle seems to fetch info from the request's page title ;( so we set it here ):
        RequestContext::getMain()->setTitle( $pageTitle );

        if ($pageNamespace == NS_FILE) {
            $text = $this->getTextFromFile( $wikiPage, $doc, $messages );
            if( $text ) {
                return $text;
            }
        }

        global $egApprovedRevsBlankIfUnapproved, $egApprovedRevsNamespaces;
        if (defined('APPROVED_REVS_VERSION')
                && $egApprovedRevsBlankIfUnapproved
                && in_array( $pageNamespace, $egApprovedRevsNamespaces )) {

            // index the approved revision
            $revision = $this->getApprovedRevision( $wikiPage );
            if ($revision === false) {
                throw new Exception( "unapproved $pageTitle" );
            }
            $content = $revision->getContent( SlotRecord::MAIN, RevisionRecord::RAW );
            if ( !$content ) {
                throw new Exception("Cannot find content for latest revision of $pageTitle");
            }
            $parserOut = MediaWikiServices::getInstance()->getContentRenderer()->getParserOutput($content, $wikiPage, $revision);
        } else {
            // index latest revision
            $content = $wikiPage->getContent();
            if ( !$content ) {
                throw new Exception("Cannot find content for $pageTitle");
            }
            $parserOut = MediaWikiServices::getInstance()->getContentRenderer()->getParserOutput($content, $wikiPage);
        }

        if ( $parserOut == null ) {
            throw new Exception("Cannot find parser output for $pageTitle");
        }
        return Sanitizer::stripAllTags($parserOut->getText() ?? '');
    }

    /**
     * extract document if a file was uploaded
     */
    private function getTextFromFile( WikiPage $wikiPage, array &$doc, array &$messages ) : string {
        $pageTitle = $wikiPage->getTitle();
        $pageNamespace = $pageTitle->getNamespace();
        if ($pageNamespace !== NS_FILE) {
            return '';
        }

        $text = '';
        $pageDbKey  = $pageTitle->getDBkey();
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

        global $fsgIndexImageURL;

        try {
            if (isset($fsgIndexImageURL) && $fsgIndexImageURL === true) {
                $this->retrieveFileSystemPath($db, $pageNamespace, $pageDbKey, $doc);
            }
            $docData = $this->extractDocument( $pageTitle );
            if( $docData ) {
                $text = $docData['text'] ?? '';
            }
        } catch( Exception $e ) {
            $messages[] = $e->getMessage();
            $text = $e->getMessage();
        }

        return $text;
    }

    /**
     * Will update the $options['smwh_boost_dummy']['boost'] field with the accumulated boost value
     * from namespaces, templates and categories of the wiki page.
     */
    private function calculateBoosting(WikiPage $wikiPage, array &$options, array $doc) {
        global $fsgActivateBoosting;
        if (! isset($fsgActivateBoosting) || $fsgActivateBoosting === false) {
            return;
        }

        global $fsgDefaultBoost;
        if($fsgDefaultBoost) {
            $options['smwh_boost_dummy']['boost'] = $fsgDefaultBoost;
        } else {
            $options['smwh_boost_dummy']['boost'] = 1.0;
        }

        $title = $wikiPage->getTitle();
        $namespace = $title->getNamespace();
        $pid = $wikiPage->getId();

        // add boost according to namespace
        global $fsgNamespaceBoosts;
        if( array_key_exists($namespace, $fsgNamespaceBoosts) ) {
            $this->updateBoostFactor($options, $fsgNamespaceBoosts[$namespace]);
        }

        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

        // add boost according to templates
        global $fsgTemplateBoosts;
        $templates = $this->retrieveTemplates($db, $pid, $doc, $options);
        $templates = array_intersect(array_keys($fsgTemplateBoosts), $templates);
        foreach($templates as $template) {
            $this->updateBoostFactor($options, $fsgTemplateBoosts[$template]);
        }

        // add boost according to categories
        global $fsgCategoryBoosts;
        $categoriesIterator = $wikiPage->getCategories();
        $categories = array();
        foreach ($categoriesIterator as $categoryTitle) {
            $categories[] = $categoryTitle;
        }
        $categories = array_intersect(array_keys($fsgCategoryBoosts), $categories);
        foreach($categories as $category) {
            $this->updateBoostFactor($options, $fsgCategoryBoosts[$category]);
        }
    }

    /**
     * @param WikiPage
     * @return RevisionRecord|bool
     */
    private function getApprovedRevision(WikiPage $wikiPage) {
        // get approved rev_id
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

        $res = $db->newSelectQueryBuilder()
                ->select( 'rev_id' )
                ->from( 'approved_revs' )
                ->where( ['page_id' => $wikiPage->getTitle()->getArticleID()] )
                ->fetchResultSet();

        $rev_id = null;
        if ( $res->numRows() > 0 ) {
            if( $row = $res->fetchRow() ) {
                $rev_id = $row['rev_id'];
            }
        }

        if (is_null($rev_id)) {
            return false;
        }

        $store = MediaWikiServices::getInstance()->getRevisionStore();
        $revision = $store->getRevisionById( $rev_id );
        return $revision;
    }

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
    public function updateIndexForMovedArticle($oldid, $newid) {
        if( $this->deleteDocument($oldid) ) {
            // The article with the new name has the same page id as before
            $wp = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $oldid );

            $content = $wp->getContent(RevisionRecord::RAW);
            if($content == null) {
                $text = '';
            } else  {
                $parserOut = MediaWikiServices::getInstance()->getContentRenderer()->getParserOutput( $content, $wp );
                $text = $parserOut->getText() ?? '';
                $text = Sanitizer::stripAllTags( $text );
            }

            try {
                $this->updateIndexForArticle($wp, $text);
            } catch( Exception $e) {
                // TODO error logging
             }
        }
        return false;
    }

    //--- Private methods ---

    /**
     * Add general boost factor if it is greater than the old.
     *
     * @param array $options
     * @param float $value
     */
    private function updateBoostFactor(array &$options, $value) {
        $options['smwh_boost_dummy']['boost'] *= $value;
    }

    /**
     * Retrieves the templates of the article with the page ID $pid and calculate
     * boosting factors for it
     *
     * @param IDatabase $db
     *         The database object
     * @param int $pid
     *         The page ID.
     * @param array $doc
     * @param array $options
     * @return array
     */
    private function retrieveTemplates($db, $pid, array &$doc, array &$options) {
        // TODO for MW 1.43 use an API: ParserOutput::getLinkList(ParserOutputLinkTypes::TEMPLATE)
        // MW >= 1.38
        $res = $db->newSelectQueryBuilder()
                ->select( ['template' => 'CAST(lt_title AS CHAR)'] )
                ->from( 'templatelinks' )
                ->join( 'page', null, [ 'page_id = tl_from' ] )
                ->join( 'linktarget', null, [ 'lt_id = tl_target_id' ] )
                ->where( ['tl_from' => $pid] )
                ->caller( __METHOD__ )
                ->fetchResultSet();

        $smwhTemplates = [];
        if ( $res->numRows() > 0 ) {
            while( $row = $res->fetchObject() ) {
                $template = $row->template;
                $smwhTemplates[] = str_replace("_", " ", $template);
            }
        }
        $res->free();

        return $smwhTemplates;
    }

    /**
     * Encodes special characters in a given SMW property name to make it compliant with SOLR field names
     *
     * @param string  $propertyName
     * @return string
     */
    public static function encodeTitle($propertyName) {
        // turns non-acii and some special characters into percent encoding, e.g. %3A
        $tmp = rawurlencode($propertyName);

        $tmp = str_replace("_", "__", $tmp);

        // replaces % with _0x
        $tmp = str_replace("%", "_0x", $tmp);
        return $tmp;
    }

    /**
     * Returns the SOLR field name for a property
     * @param DIProperty $property
     *
     * @return string
     */
    public static function encodeSOLRFieldName($property) {
        $prop = str_replace(' ', '_', $property->getLabel());

        $prop = self::encodeTitle($prop);

        $typeId = $property->findPropertyValueType();
        $type = DataTypeRegistry::getInstance()->getDataItemByType($typeId);

        // The property names of all attributes are built based on their type.
        switch($type) {
            case SMWDataItem::TYPE_BOOLEAN:
                return "smwh_{$prop}_xsdvalue_b";
            case SMWDataItem::TYPE_NUMBER:
                return "smwh_{$prop}_numvalue_d";
            case SMWDataItem::TYPE_BLOB:
                return "smwh_{$prop}_xsdvalue_t";
            case SMWDataItem::TYPE_WIKIPAGE:
                return "smwh_{$prop}_t";
            case SMWDataItem::TYPE_TIME:
                return "smwh_{$prop}_xsdvalue_dt";
        }

        // all others are regarded as string/text
        return "smwh_{$prop}_xsdvalue_t";
    }

    /**
     * Returns the SOLR field name for a property value constraint
     * @param DIProperty $property
     *
     * @return string
     */
    public static function encodeSOLRFieldNameForValue($property) {
        $prop = str_replace(' ', '_', $property->getLabel());

        $prop = self::encodeTitle($prop);

        $typeId = $property->findPropertyValueType();
        $type = DataTypeRegistry::getInstance()->getDataItemByType($typeId);

        // The property names of all attributes are built based on their type.
        switch($type) {
            case SMWDataItem::TYPE_BOOLEAN:
                return "smwh_{$prop}_xsdvalue_b";
            case SMWDataItem::TYPE_NUMBER:
                return "smwh_{$prop}_numvalue_d";
            case SMWDataItem::TYPE_BLOB:
                return "smwh_{$prop}_xsdvalue_s";
            case SMWDataItem::TYPE_WIKIPAGE:
                return "smwh_{$prop}_s";
            case SMWDataItem::TYPE_TIME:
                return "smwh_{$prop}_xsdvalue_dt";
        }

        // all others are regarded as wikipage
        return "smwh_{$prop}_s";
    }


    /**
     * Retrieves the SMW-ID of the article with the $namespaceID and the $title
     * and adds them to the document description $doc.
     *
     * @param int $namespaceID
     *         Namespace ID of the article
     * @param string $title
     *         The DB key of the title of the article
     * @param array $doc
     *         The document description. If there is a SMW ID for the article, it is
     *         added with the key 'smwh_smw_id'.
     * @return bool
     *         <true> if an SMW-ID was found
     *         <false> otherwise
     */
    private function retrieveSMWID( $namespaceID, $title, array &$doc ) {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
        $res = $db->newSelectQueryBuilder()
                ->select( 'smw_id' )
                ->from( 'smw_object_ids' )
                ->where( ['smw_namespace' => $namespaceID, 'smw_title' => $title] )
                ->caller( __METHOD__ )
                ->fetchResultSet();

        $found = false;
        if ( $res->numRows() > 0 ) {
            $row = $res->fetchObject();
            $smwID = $row->smw_id;
            $doc['smwh_smw_id'] = $smwID;
            $found = true;
        }
        $res->free();

        return $found;
    }

    /**
     * Retrieves full URL of the file resource attached to this title.
     *
     * @param IDatabase $db
     * @param int $namespace namespace-id
     * @param string $title dbkey
     * @param array $doc (out)
     */
    private function retrieveFileSystemPath($db, $namespace, $title, array &$doc) {
        $title = Title::newFromText($title, $namespace);
        $file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile($title);
        $filepath = $file->getFullUrl();

        $propXSD = "smwh_diqa_import_fullpath_xsdvalue_t";
        $doc[$propXSD] = $filepath;
        $doc['smwh_attributes'][] = $propXSD;
    }

    /**
     * Retrieves the relations of the article with the SMW ID $smwID and adds
     * them to the document description $doc.
     *
     * @param Title $title
     * @param array $doc
     *         The document description. If the page has relations, all relations
     *         and their values are added to $doc. The key 'smwh_properties' will
     *         be an array of relation names and a key will be added for each
     *         relation with the value of the relation.
     */
    private function retrievePropertyValues( $title, array &$doc ) {
        global $fsgIndexPredefinedProperties;

        $store = smwfGetStore();
        $attributes = array();
        $relations = array();

        $subject = DIWikiPage::newFromTitle($title);
        $properties = $store->getProperties($subject);

        foreach($properties as $property) {
            // skip instance-of and subclass properties
            if ($property->getKey() == "_INST" || $property->getKey() == "_SUBC") {
                continue;
            }

            // check if particular pre-defined property should be indexed
            $predefPropType = PropertyRegistry::getInstance()->getPropertyValueTypeById($property->getKey());
            $p = $property; //DIProperty::newFromUserLabel($prop);
            if (!empty($predefPropType)) {
                // This is a predefined property
                if (isset($fsgIndexPredefinedProperties) && $fsgIndexPredefinedProperties === false) {
                    continue;
                }
            }

            // check if property should be indexed
            $prop_ignoreasfacet = wfMessage('fs_prop_ignoreasfacet')->text();

            $iafValues = $store->getPropertyValues($p->getDiWikiPage(), DIProperty::newFromUserLabel($prop_ignoreasfacet));
            if (count($iafValues) > 0) {
                continue;
            }

            // retrieve all annotations and index them
            $values = $store->getPropertyValues($subject, $property);

            foreach($values as $value) {
                if ($value->getDIType() == SMWDataItem::TYPE_WIKIPAGE) {

                    if ($value->getSubobjectName() != "") {

                        global $fsgIndexSubobjects;
                        if ($fsgIndexSubobjects !== true) {
                            continue;
                        }

                        // handle record properties
                        if ($value->getSubobjectName() != "") {
                            $subData = smwfGetStore()->getSemanticData($value);
                            $recordProperties = $subData->getProperties();
                            foreach($recordProperties as $rp) {
                                if (strpos($rp->getKey(), "_") === 0) continue;
                                $propertyValues = $subData->getPropertyValues($rp);
                                $record_value = reset($propertyValues);
                                if ($record_value === false) continue;
                                if ($record_value->getDIType() == SMWDataItem::TYPE_WIKIPAGE) {
                                    $enc_prop = $this->serializeWikiPageDataItem($subject, $rp, $record_value, $doc);
                                    $relations[] = $enc_prop;
                                } else {
                                    $enc_prop = $this->serializeDataItem($rp, $record_value, $doc);
                                    if (is_null($enc_prop)) {
                                        continue;
                                    }
                                    $attributes[] = $enc_prop;
                                }
                            }
                        }
                    } else {
                        // handle relation properties
                        $enc_prop = $this->serializeWikiPageDataItem($subject, $property, $value, $doc);
                        $relations[] = $enc_prop;
                    }

                } else {
                    // handle attribute properties
                    $enc_prop = $this->serializeDataItem($property, $value, $doc);
                    if (is_null($enc_prop)) {
                        continue;
                    }
                    $attributes[] = $enc_prop;
                }
            }
        }

        $doc['smwh_properties'] = array_filter(array_unique($relations), function($e) { return !empty($e); });
        $doc['smwh_attributes'] = array_filter(array_unique($attributes), function($e) { return !empty($e); });
    }

    /**
     * Indexes categories. Either as member categories or super-categories
     *
     * @param Title $title
     * @param array $doc
     */
    private function indexCategories(Title $title, array &$doc) {
        $store = smwfGetStore();
        $subject = DIWikiPage::newFromTitle($title);

        $categories = [];
        $properties = $store->getProperties($subject);
        foreach ($properties as $property) {
            if ($property->getKey() == "_INST" || $property->getKey() == "_SUBC") {
                $categories = array_merge($categories, $store->getPropertyValues($subject, $property));
            }
        }

        $prop_ignoreasfacet = wfMessage('fs_prop_ignoreasfacet')->text();
        $ignoreAsFacetProp = DIProperty::newFromUserLabel($prop_ignoreasfacet);

        $doc['smwh_directcategories'] = [];
        $allParentCategories = [];
        foreach ($categories as $category) {
            // do not index if ignored
            $iafValues = $store->getPropertyValues(DIWikiPage::newFromTitle($category->getTitle()), $ignoreAsFacetProp);
            if (count($iafValues) > 0) {
                continue;
            }

            // index this category
            $doc['smwh_directcategories'][] = $category->getTitle()->getDBkey();
            $allParentCategories[] = $category->getTitle();
        }

        // index all categories recursively
        $allCategories = $this->getAllSuperCategories($allParentCategories);
        $allCategories = array_unique($allCategories);
        foreach ($allCategories as $pc) {
            $doc['smwh_categories'][] = $pc;
        }
    }

    /**
     * Returns all parent categories, recursively.
     *
     * @param array of Title objects $categories for starting the recursion
     * @return array transitive superclass closure of categories
     */
    private function getAllSuperCategories($categories) {
        $y = [];
        foreach($categories as $category) {
            $y = $this->getAllSuperCategoriesInternal($category, $y);
        }
        return $y;
    }

    /**
     * Returns all parent categories.
     *
     * @param Title $root the current root category
     * @param array $categories temporary list of already found cateagories, for endless-loop protection
     * @return array transitive superclass closure of categories
     */
    private function getAllSuperCategoriesInternal($root, $temp) {
        $y = $temp;
        $y[] = $root->getDBkey();
        $parentCategories = $root->getParentCategories();
        foreach($parentCategories as $parentCategoryName => $childCat) {
            $parentCatTitle = Title::newFromText($parentCategoryName);
            if( ! in_array($parentCatTitle->getDBkey(), $y) ) {
                $y = $this->getAllSuperCategoriesInternal($parentCatTitle, $y);
            }
        }
        return $y;
    }

    /**
     * Serialize DIWikiPage into $doc array.
     *
     * @param DIWikiPage $subject
     * @param DIProperty $property
     * @param SMWDataItem $dataItem
     * @param array $doc
     *
     * @return string representing the encoded property name
     */
    private function serializeWikiPageDataItem($subject, $property, $dataItem, array &$doc) {
        $obj = $this->createPropertyValueWithLabel( $dataItem );

        // The values of all properties are stored as string.
        $prop = str_replace(' ', '_', $property->getLabel());
        $prop = self::encodeTitle($prop);
        $prop = "smwh_{$prop}_t";
        if (!array_key_exists($prop, $doc)) {
            $doc[$prop] = array();
        }
        $doc[$prop][] = $obj;
        return $prop;
    }

    private function createPropertyValueWithLabel(SMWDataItem $dataItem) {
        /** @var DIWikiPage $dataItem */
        $title = $dataItem->getTitle();
        $valueId = $title->getPrefixedText();
        $valueLabel = FacetedSearchUtil::findDisplayTitle($title);
        return "$valueId|$valueLabel";
    }

    /**
     * Serialize all other SMWDataItems into $doc array (non-DIWikiPage).
     *
     * @param DIProperty $property
     * @param SMWDataItem $dataItem
     * @param array $doc
     *
     * @return string|null representing the encoded property name
     */
    private function serializeDataItem($property, $dataItem, array &$doc) {

        $valueXSD = $dataItem->getSerialization();

        // TODO use encodeSOLRFieldName() here
        $prop = str_replace(' ', '_', $property->getLabel());
        $prop = self::encodeTitle($prop);
        $type = $dataItem->getDIType();

        // The values of all attributes are stored according to their type.
        if ($type == SMWDataItem::TYPE_TIME) {
            $typeSuffix = 'dt';

            /** @var SMWDITime $dataItem */
            $year = $dataItem->getYear();
            $month = $dataItem->getMonth();
            $day = $dataItem->getDay();

            $hour = $dataItem->getHour();
            $min = $dataItem->getMinute();
            $sec = $dataItem->getSecond();

            $month = strlen($month) === 1 ? "0$month" : $month;
            $day = strlen($day) === 1 ? "0$day" : $day;
            $hour = strlen($hour) === 1 ? "0$hour" : $hour;
            $min = strlen($min) === 1 ? "0$min" : $min;
            $sec = strlen($sec) === 1 ? "0$sec" : $sec;

            // Required format: 1995-12-31T23:59:59Z
            $valueXSD = "{$year}-{$month}-{$day}T{$hour}:{$min}:{$sec}Z";

            // Store a date/time also as long e.g. 19951231235959
            // This is needed for querying statistics for dates
            $year = strlen($year) === 1 ? "0$year" : $year;
            $year = strlen($year) === 2 ? "0$year" : $year;
            $year = strlen($year) === 3 ? "0$year" : $year;
            $dateTime = "{$year}{$month}{$day}{$hour}{$min}{$sec}";

            $propDate = 'smwh_' . $prop . '_datevalue_l';
            if (!array_key_exists($propDate, $doc)) {
                $doc[$propDate] = array();
            }
            $doc[$propDate][] = $dateTime;

        } else if ($type == SMWDataItem::TYPE_NUMBER) {
            $typeSuffix = 'd';

            $propNum = "smwh_{$prop}_numvalue_d";
            if (!array_key_exists($propNum, $doc)) {
                $doc[$propNum] = array();
            }
            $doc[$propNum][] = $valueXSD;

        } else if ($type == SMWDataItem::TYPE_BOOLEAN) {
            $typeSuffix = 'b';

        } else if ($type == SMWDataItem::TYPE_CONCEPT) {
            return null;

        } else {
            $typeSuffix = 't';
        }

        $propXSD = "smwh_{$prop}_xsdvalue_$typeSuffix";
        if (!array_key_exists($propXSD, $doc)) {
            $doc[$propXSD] = array();
        }
        $doc[$propXSD][] = $valueXSD;

        $this->handleSpecialWikiProperties($property, $dataItem, $doc);

        return $propXSD;
    }

    /**
     * Special handling for special SMW properties.
     *
     * @param DIProperty $property
     * @param SMWDataItem $dataItem
     * @param array $doc
     */
    private function handleSpecialWikiProperties($property, $dataItem, array &$doc) {
        if ($property->isUserDefined()) {
            return; // not special
        }

        switch ($property->getKey()) {
            case '_MDAT':
                // used for sorting
                /** @var SMWDITime $dataItem */
                $doc['smwh__MDAT_datevalue_l'] = $dataItem->getMwTimestamp();
                break;
        }
    }

}

