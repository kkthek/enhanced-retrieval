<?php
namespace DIQA\FacetedSearch;

use Bootstrap\BootstrapManager;
use SMW\StoreFactory;
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
        global $wgHooks, $wgExtensionMessagesFiles,
        $wgExtensionAliasesFiles, $fsgEnableIncrementalIndexer;
        $dir = dirname(__FILE__).'/';

        // Register special pages aliases file
        $wgExtensionAliasesFiles['FacetedSearch'] = $dir . '/Languages/FSAliases.php';

        // Register hooks
        if ($fsgEnableIncrementalIndexer) {
            $wgHooks['SMW::SQLStore::AfterDataUpdateComplete'][] =
                    'DIQA\FacetedSearch\FSIncrementalUpdater::onUpdateDataAfter';
            $wgHooks['UploadComplete'][] =
                    'DIQA\FacetedSearch\FSIncrementalUpdater::onUploadComplete';
            $wgHooks['AfterImportPage'][] =
                    'DIQA\FacetedSearch\FSIncrementalUpdater::onAfterImportPage';
            $wgHooks['TitleMoveComplete'][] =
                    'DIQA\FacetedSearch\FSIncrementalUpdater::onTitleMoveComplete';
            $wgHooks['ArticleDelete'][] =
                    'DIQA\FacetedSearch\FSIncrementalUpdater::onArticleDelete';
            $wgHooks['ApprovedRevsRevisionApproved'][] =
                    'DIQA\FacetedSearch\FSIncrementalUpdater::onRevisionApproved';
        }

        // Register specials pages
        global $wgSpecialPages, $wgSpecialPageGroups;
        $wgSpecialPages['FacetedSearch']      = array('class' => 'DIQA\FacetedSearch\Specials\FSFacetedSearchSpecial');
        $wgSpecialPageGroups['FacetedSearch'] = 'fs_specialpage_group';

        self::initResourceLoaderModules();
    }

    /**
     * Initializes all modules for the resource loader.
     */
    private static function initResourceLoaderModules() {
        global $wgResourceModules, $fsgIP, $fsgScriptPath;

        $moduleTemplate = array(
                'localBasePath' => $fsgIP,
                'remoteBasePath' => $fsgScriptPath,
                'group' => 'ext.facetedSearch'
        );

        // Scripts and styles for all actions
        $wgResourceModules['ext.facetedSearch.ajaxSolr'] = $moduleTemplate + array(
                'scripts' => array(
                        "scripts/ajax-solr/lib/core/Core.js",
                        "scripts/ajax-solr/lib/core/AbstractManager.js",
                        //            "scripts/ajax-solr/lib/managers/Manager.jquery.js",
                        "scripts/ajax-solr/lib/core/Parameter.js",
                        "scripts/ajax-solr/lib/core/ParameterStore.js",
                        "scripts/ajax-solr/lib/core/AbstractWidget.js",
                        "scripts/ajax-solr/lib/core/AbstractFacetWidget.js",
                        "scripts/ajax-solr/lib/core/ParameterStore.js",
                        "scripts/ajax-solr/lib/helpers/jquery/ajaxsolr.theme.js",
                        "scripts/ajax-solr/lib/widgets/jquery/PagerWidget.js",
                        "scripts/FacetedSearch/jquery.jsonp-2.1.4.js",
                        "scripts/FacetedSearch/FS_Manager.jquery.js",
                )
        );
        // Scripts and styles for all actions
        $wgResourceModules['ext.facetedSearch.special'] = $moduleTemplate + array(
                'scripts' => array(
                        "scripts/FacetedSearch/FS_Theme.js",
                        "scripts/FacetedSearch/FS_Utils.js",
                        "scripts/FacetedSearch/FS_ResultWidget.js",
                        "scripts/FacetedSearch/FS_PagerWidget.js",
                        "scripts/FacetedSearch/FS_FacetWidget.js",
                        "scripts/FacetedSearch/FS_ArticlePropertiesWidget.js",
                        "scripts/FacetedSearch/FS_CreateArticleWidget.js",
                        "scripts/FacetedSearch/FS_LinkCurrentSearchWidget.js",
                        "scripts/FacetedSearch/FS_NamespaceFacetWidget.js",
                        "scripts/FacetedSearch/FS_FacetPropertyValueWidget.js",
                        "scripts/FacetedSearch/FS_CurrentSearchWidget.js",
                        "scripts/FacetedSearch/FS_FacetedSearch.js",
                        "scripts/FacetedSearch/FS_FacetClusterer.js",
                        "scripts/FacetedSearch/FS_NumericFacetClusterer.js",
                        "scripts/FacetedSearch/FS_StringFacetClusterer.js",
                        "scripts/FacetedSearch/FS_BooleanFacetClusterer.js",
                        "scripts/FacetedSearch/FS_DateFacetClusterer.js",
                        "scripts/FacetedSearch/FS_ClusterWidget.js",
                        "scripts/FacetedSearch/FS_FacetClustererFactory.js",
                ),

                'styles' => array(
                        '/skin/faceted_search.css',
                ),
                'dependencies' => array(
                        'ext.facetedSearch.ajaxSolr',
                ),
                'messages' => array (
                        'solrNotFound',
                        'solrConnectionError',
                        'tryConnectSOLR'    ,
                        'more'                 ,
                        'less'                 ,
                        'noFacetFilter'        ,
                        'underspecifiedSearch' ,
                        'session_lost' ,
                        'removeFilter'        ,
                        'removeRestriction'    ,
                        'removeAllFilters'    ,
                        'pagerPrevious'        ,
                        'pagerNext'            ,
                        'results'            ,
                        'to'                ,
                        'of'                ,
                        'ofapprox'            ,
                        'inCategory'        ,
                        'show'                ,
                        'hide'                ,
                        'showDetails'        ,
                        'hideDetails'        ,
                        'lastChange'        ,
                        'addFacetOrQuery'    ,
                        'mainNamespace'        ,
                        'namespaceTooltip'  ,
                        'allNamespaces'        ,
                        'nonexArticle'        ,
                        'searchLink'         ,
                        'searchLinkTT'        ,
                        '_TYPE' ,
                        '_URI'  ,
                        '_SUBP' ,
                        '_SUBC' ,
                        '_UNIT' ,
                        '_IMPO' ,
                        '_CONV' ,
                        '_SERV' ,
                        '_PVAL' ,
                        '_MDAT' ,
                        '_CDAT' ,
                        '_NEWP' ,
                        '_LEDT' ,
                        '_ERRP' ,
                        '_LIST' ,
                        '_SOBJ' ,
                        '_ASK'  ,
                        '_ASKST',
                        '_ASKFO',
                        '_ASKSI',
                        '_ASKDE'
                )
        );
        BootstrapManager::getInstance()->addBootstrapModule("modals");

        $wgResourceModules['ext.facetedSearch.enhancements'] = $moduleTemplate + array(
                'localBasePath' => __DIR__,

                'scripts' => array(
                        'scripts/FacetedSearch/Enhancements/fs_categoryFilter.js',
                        'scripts/FacetedSearch/Enhancements/fs_propertySelector.js',
                        'scripts/FacetedSearch/Enhancements/fs_categorySelector.js',
                        'scripts/FacetedSearch/Enhancements/fs_facetValueDialog.js',
                        'scripts/FacetedSearch/Enhancements/fs_enhancements.js'
                ),
                'styles' => array('skin/dialogs.css'),
                'dependencies' => array(
                        'ext.facetedSearch.special',
                        'ext.bootstrap.styles',
                        'ext.bootstrap.scripts'
                )
        );
    }

    /**
     * Called before parser is initialized
     */
    public static function initializeBeforeParserInit() {
        $currentTitle = \RequestContext::getMain()->getTitle();
        if( is_null($currentTitle) || $currentTitle->getNamespace() != NS_SPECIAL ||
               ($currentTitle->getText() != 'Suche' && $currentTitle->getText() != 'Search')) {
            return;
        }

        global $wgOut,
                $fsgExtraPropertiesToRequest, $fsgNumericPropertyClusters, $fsgDateTimePropertyClusters,
                $fsgShowArticleProperties, $fsgShowSolrScore,
                $fsgShownFacets, $fsgFacetsWithOR, $fsgShownCategoryFacets,
                $fsgCategoriesToShowInTitle, $fsgShowFileInOverlay,
                $fsgPromotionProperty, $fsgDemotionProperty,
                $fsgHitsPerPage, $fsgDefaultSortOrder;

        if (isset($fsgExtraPropertiesToRequest)) {
            $extraPropertiesToRequest = [];
            foreach ($fsgExtraPropertiesToRequest as $prop) {
                $extraPropertiesToRequest[] = FSSolrSMWDB::encodeSOLRFieldName(\SMWDIProperty::newFromUserLabel($prop));
            }
        } else {
            $extraPropertiesToRequest = [];
        }

        if ($fsgPromotionProperty) {
            $promotionProperty = FSSolrSMWDB::encodeSOLRFieldName(\SMWDIProperty::newFromUserLabel($fsgPromotionProperty));
        } else {
            $promotionProperty = '';
        }

        if ($fsgDemotionProperty) {
            $demotionProperty = FSSolrSMWDB::encodeSOLRFieldName(\SMWDIProperty::newFromUserLabel($fsgDemotionProperty));
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
        $wgOut->addJsConfigVars($xfsVars);

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
    private static function addAnnotationSnippetVars() {
        global $fsgAnnotationsInSnippet, $fsgExtraPropertiesToRequest;

        $result = [];
        $store = StoreFactory::getStore ();
        foreach($fsgAnnotationsInSnippet as $category => $properties) {

            foreach($properties as $property) {
                $smwProperty = \SMWDIProperty::newFromUserLabel($property);
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