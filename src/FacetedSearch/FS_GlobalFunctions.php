<?php
namespace DIQA\FacetedSearch;

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
        $wgExtensionAliasesFiles['FacetedSearch'] = $dir . '/Languages/FS_Aliases.php';

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
        $wgSpecialPages['FacetedSearch']      = array('DIQA\FacetedSearch\Specials\FSFacetedSearchSpecial');
        $wgSpecialPageGroups['FacetedSearch'] = 'facetedsearch_group';
        $wgSpecialPageGroups['FacetedSearch'] = 'smwplus_group';

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
                        'jquery.ui.autocomplete',
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
                $fsgTitleProperty, $fsgAnnotationsInSnippet, $fsgShowArticleProperties,
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

        if ($fsgTitleProperty != '') {
            $titlePropertyField = FSSolrSMWDB::encodeSOLRFieldName(\SMWDIProperty::newFromUserLabel($fsgTitleProperty));
            $extraPropertiesToRequest[] = $titlePropertyField;
        } else {
            $titlePropertyField = '';
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

        $script = "\n<script type='text/javascript'>";
        $script .= "var XFS = XFS || {};";
        $script .= "XFS.titlePropertyField = '$titlePropertyField';";
        $script .= "XFS.numericPropertyClusters = "     . json_encode($fsgNumericPropertyClusters) . ";";
        $script .= "XFS.dateTimePropertyClusters = "    . json_encode($fsgDateTimePropertyClusters) . ";";
        self::addAnnotationSnippets($script);
        $script .= "XFS.extraPropertiesToRequest = "    . json_encode($extraPropertiesToRequest) . ";";
        $script .= "XFS.fsgShowArticleProperties = "    . ($fsgShowArticleProperties?'true':'false') . ";";

        $script .= "XFS.SHOWN_CATEGORY_FACETS = "       . json_encode($fsgShownCategoryFacets).";";
        $script .= "XFS.SHOWNFACETS = "                 . json_encode($fsgShownFacets) . ';';
        $script .= "XFS.OREDFACETS = "                  . json_encode($fsgFacetsWithOR) . ';';
        $script .= "XFS.CATEGORIES_TO_SHOW_IN_TITLE = " . json_encode($fsgCategoriesToShowInTitle) . ';';
        $script .= "XFS.SHOW_FILE_IN_OVERLAY = "        . json_encode($fsgShowFileInOverlay) . ";";
        $script .= "XFS.PROMOTION_PROPERTY = '$promotionProperty';";
        $script .= "XFS.DEMOTION_PROPERTY = '$demotionProperty';";
        $script .= "XFS.HITS_PER_PAGE = "               . json_encode($fsgHitsPerPage).";";
        $script .= "XFS.DEFAULT_SORT_ORDER = "          . json_encode($fsgDefaultSortOrder).";";
        $script .= "</script>";

        $wgOut->addScript( $script );
        return true;
    }

    /**
     * Serializes metadata about properties for displaying them later in snippets.
     *
     *  1. adds every property in $fsgExtraPropertiesToRequest
     *  2. retrieves metadata like DisplayTitle
     *
     * @param string $script (out)
     */
    private static function addAnnotationSnippets(& $script) {
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

                global $fsgTitleProperty;
                $value = $store->getPropertyValues( $smwProperty->getDiWikiPage(),
                        \SMWDIProperty::newFromUserLabel ( $fsgTitleProperty ) );
                $value = reset($value);
                $displayTitle = $value !== false ? $value->getString() : $smwProperty->getDiWikiPage()->getTitle()->getText();

                if (!array_key_exists($solrFieldName, $result)) {
                    $result[$solrFieldName] = ['label' => $displayTitle, 'category' => [ $category ] ];
                } else {
                    $result[$solrFieldName]['category'][] = $category;
                }
            }
        }

        $script .= "XFS.annotationsInSnippet = ".json_encode($result).";";
    }
}