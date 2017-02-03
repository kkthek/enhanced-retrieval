<?php
namespace DIQA\FacetedSearch;

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
			$wgHooks['ArticleSaveComplete'][] = 'DIQA\FacetedSearch\FSIncrementalUpdater::onArticleSaveComplete';
			$wgHooks['UploadComplete'][] = 'DIQA\FacetedSearch\FSIncrementalUpdater::onUploadComplete';
			$wgHooks['AfterImportPage'][]     = 'DIQA\FacetedSearch\FSIncrementalUpdater::onAfterImportPage';
			$wgHooks['TitleMoveComplete'][]   = 'DIQA\FacetedSearch\FSIncrementalUpdater::onTitleMoveComplete';
			$wgHooks['ArticleDelete'][]       = 'DIQA\FacetedSearch\FSIncrementalUpdater::onArticleDelete';
		}
		
		///// Register specials pages
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
						//			"scripts/ajax-solr/lib/managers/Manager.jquery.js",
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
						"scripts/FacetedSearch/FS_DateFacetClusterer.js",
						"scripts/FacetedSearch/FS_ClusterWidget.js",
						"scripts/FacetedSearch/FS_FacetClustererFactory.js",
				),
				'styles' => array(
				'/skin/faceted_search.css',
				),
				'dependencies' => array(
				'ext.facetedSearch.Language',
				'ext.facetedSearch.ajaxSolr',
				)
	
		);
		self::addJSLanguageScripts();
	
	}
	
	
	/**
	 * Add appropriate JS language script
	 */
	private static function addJSLanguageScripts() {
		global $fsgIP, $wgUser, $wgResourceModules;
		// user language file
		$ulngScript = '/scripts/FacetedSearch/Language/FS_Language.js';
		$lngBase = '/scripts/FacetedSearch/Language/FS_Language';
		if (isset($wgUser)) {
			$lng = $lngBase . ucfirst($wgUser->getOption('language')).'.js';
			if (file_exists($fsgIP. $lng)) {
				$ulngScript = $lng;
			} else {
				// No language file => Fall back to english
				$ulngScript =  $lngBase . 'En.js';
			}
		}
		$wgResourceModules['ext.facetedSearch.Language'] = array(
				'scripts' => array(
						"scripts/FacetedSearch/Language/FS_Language.js",
						$ulngScript
				),
				'localBasePath' => $fsgIP,
				'remoteExtPath' => 'EnhancedRetrieval'
		);
	
	}
	
}



