<?php
namespace DIQA\FacetedSearch;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use Skin;
use SMW\DIProperty;

/*
 * Copyright (C) Vulcan Inc., DIQA Projektmanagement GmbH
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
 * This file contains the initialization and global functions for the faceted
 * search.
 *
 * @author Thomas Schweitzer
 * @author Kai Kühn
 * Date: 23.02.2011
 *
 */
if ( !defined( 'MEDIAWIKI' ) ) {
    die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}

class FSGlobalFunctions {
    public static function setupFacetedSearch() {
        global $wgExtensionAliasesFiles, $fsgEnableIncrementalIndexer;
        $dir = dirname(__FILE__);

        // Register special pages aliases file
        $wgExtensionAliasesFiles['FacetedSearch'] = "$dir/Languages/FSAliases.php";

        // Register hooks
        if ($fsgEnableIncrementalIndexer) {
            $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
            $hookContainer->register('SMW::SQLStore::AfterDataUpdateComplete', 'DIQA\FacetedSearch\FSIncrementalUpdater::onUpdateDataAfter');
            $hookContainer->register('UploadComplete',                         'DIQA\FacetedSearch\FSIncrementalUpdater::onUploadComplete');
            $hookContainer->register('AfterImportPage',                        'DIQA\FacetedSearch\FSIncrementalUpdater::onAfterImportPage');
            $hookContainer->register('PageMoveCompleting',                     'DIQA\FacetedSearch\FSIncrementalUpdater::onTitleMoveComplete');
            $hookContainer->register('PageDelete',                             'DIQA\FacetedSearch\FSIncrementalUpdater::onPageDelete');
            $hookContainer->register('ApprovedRevsRevisionApproved',           'DIQA\FacetedSearch\FSIncrementalUpdater::onRevisionApproved');
            $hookContainer->register('PageSaveComplete',                       'DIQA\FacetedSearch\FSIncrementalUpdater::onPageSaveComplete');
        }
    }

    public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
        global $fsgFacetsDialogWithCustomContent;
        $out->addJsConfigVars('fsgFacetsDialogWithCustomContent', $fsgFacetsDialogWithCustomContent );
    }

    /**
     * Called before parser is initialized
     */
    public static function initializeBeforeParserInit() {
        if( !RequestContext::getMain()->hasTitle() ) {
            return true;
        }

        $currentTitle = RequestContext::getMain()->getTitle();
        if( is_null($currentTitle) || 
                $currentTitle->getNamespace() != NS_SPECIAL ||
               ($currentTitle->getText() != 'Suche' && $currentTitle->getText() != 'Search') ) {
            return true;
        }

        global $fsgExtraPropertiesToRequest, $fsgNumericPropertyClusters, $fsgDateTimePropertyClusters,
                $fsgShowArticleProperties, $fsgShowSolrScore,
                $fsgShownFacets, $fsgFacetsWithOR, $fsgShownCategoryFacets,
                $fsgCategoriesToShowInTitle, $fsgShowFileInOverlay,
                $fsgPromotionProperty, $fsgDemotionProperty,
                $fsgHitsPerPage, $fsgDefaultSortOrder;

        $extraPropertiesToRequest = [];
        if(isset($fsgExtraPropertiesToRequest)) {
            foreach ($fsgExtraPropertiesToRequest as $prop) {
                $extraPropertiesToRequest[] = FSSolrSMWDB::encodeSOLRFieldName(DIProperty::newFromUserLabel($prop));
            }
        }

        if ($fsgPromotionProperty) {
            $promotionProperty = FSSolrSMWDB::encodeSOLRFieldName(DIProperty::newFromUserLabel($fsgPromotionProperty));
        } else {
            $promotionProperty = '';
        }

        if ($fsgDemotionProperty) {
            $demotionProperty = FSSolrSMWDB::encodeSOLRFieldName(DIProperty::newFromUserLabel($fsgDemotionProperty));
        } else {
            $demotionProperty = '';
        }

        $xfsVars = [];
        $xfsVars["ext.er.numericPropertyClusters"] = $fsgNumericPropertyClusters;
        $xfsVars["ext.er.dateTimePropertyClusters"] = $fsgDateTimePropertyClusters;
        $xfsVars["ext.er.annotationsInSnippet"] = self::addAnnotationSnippetVars();
        $xfsVars["ext.er.extraPropertiesToRequest"] = $extraPropertiesToRequest;
        $xfsVars["ext.er.showArticleProperties"] = $fsgShowArticleProperties ? 1 : 0;
        $xfsVars["ext.er.showSolrScore"] = $fsgShowSolrScore ? true : false;
        $xfsVars["ext.er.SHOWN_CATEGORY_FACETS"] = $fsgShownCategoryFacets;
        $xfsVars["ext.er.SHOWNFACETS"] = $fsgShownFacets;
        $xfsVars["ext.er.OREDFACETS"] = $fsgFacetsWithOR;
        $xfsVars["ext.er.CATEGORIES_TO_SHOW_IN_TITLE"] = $fsgCategoriesToShowInTitle;
        $xfsVars["ext.er.SHOW_FILE_IN_OVERLAY"] = $fsgShowFileInOverlay;
        $xfsVars["ext.er.PROMOTION_PROPERTY"] = $promotionProperty;
        $xfsVars["ext.er.DEMOTION_PROPERTY"] = $demotionProperty;
        $xfsVars["ext.er.HITS_PER_PAGE"] = $fsgHitsPerPage;
        $xfsVars["ext.er.DEFAULT_SORT_ORDER"] = $fsgDefaultSortOrder;

        RequestContext::getMain()->getOutput()->addJsConfigVars($xfsVars);

        return true;
    }

    /**
     * Serializes metadata about properties for displaying them later in snippets.
     *
     *  1. adds every property in $fsgExtraPropertiesToRequest
     *  2. retrieves metadata like DisplayTitle
     *
     * @return array with key/value pairs
     */
    private static function addAnnotationSnippetVars(): array {
        global $fsgAnnotationsInSnippet, $fsgExtraPropertiesToRequest;

        $result = [];
        foreach($fsgAnnotationsInSnippet ?? [] as $category => $properties) {

            foreach($properties as $property) {
                $smwProperty = DIProperty::newFromUserLabel($property);
                $solrFieldName = FSSolrSMWDB::encodeSOLRFieldName($smwProperty);

                if (!in_array($solrFieldName, $fsgExtraPropertiesToRequest)) {
                    $fsgExtraPropertiesToRequest[] = $solrFieldName;
                }

                if ( array_key_exists($solrFieldName, $result) ) {
                    $result[$solrFieldName]['category'][] = $category;
                } else {
                    $displayTitle = FacetedSearchUtil::findDisplayTitle($smwProperty->getDiWikiPage()->getTitle());
                    $result[$solrFieldName] = [ 'label' => $displayTitle, 'category' => [ $category ] ];
                }
            }
        }
        return $result;
    }
}