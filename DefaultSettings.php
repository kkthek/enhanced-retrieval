<?php
/**
 * SOLR connection data
 */
global $SOLRhost, $SOLRport, $SOLRuser, $SOLRpass, $SOLRcore;
$SOLRhost = 'localhost';
$SOLRport = 8983;
$SOLRuser = '';
$SOLRpass = '';
$SOLRcore = 'mw';

/**
 * Specifies the visible namespace for groups.
 *
 * group => array of namespaces-IDs, e.g.
 *
 * $fsgNamespaceConstraint = [
 * 	'sysop' => [ 0, 10, 14 ] // sysop users may only see Main, Template and Category pages
 * ];
 *
 * Please note: You CANNOT use Mediawiki constants like NS_MAIN here.
 * 'user' is default group if a user is in no other group.
 */
global $fsgNamespaceConstraint;
$fsgNamespaceConstraint = [];

global $fsgCustomConstraint;
$fsgCustomConstraint = [
    /**
     * Returns re-written query.
     *
     * @param string $query
     *            The SOLR query URL.
     * @param array $userGroups
     *            All groups a user is member of
     * @param string $userName
     *            The username (login)
     */
    function ($query, $userGroups, $userName) {
        return $query;
    }
];

/**
 * Use statistics logging
 */
global $fsgUseStatistics;
$fsgUseStatistics = false;

/**
 * If this variable is <true>, a search in the MediaWiki search field is redirected
 * to the faceted search special page.
 * If <false>, Enhanced Retrieval is installed.
 */
global $fsgFacetedSearchForMW;
$fsgFacetedSearchForMW = true;

/**
 * This is the pattern for the link that leads to the creation of new pages.
 * Faceted Search checks if the entered search term is the name of an existing
 * article. If this is not the case it offers a link for creating this article.
 * The variable {article} will be replace by the actual article name.
 * The link will be appended to the base URL like "http://localhost/mediawiki/index.php"
 */
global $fsgCreateNewPageLink;
$fsgCreateNewPageLink = "/{article}?action=edit";

/**
 * If this variable is <true>, changed pages will be indexed incrementally i.e.
 * when they are saved, moved or deleted.
 * Setting it to <false> can make sense for example during the installation when
 * SOLR is not yet running.
 */
global $fsgEnableIncrementalIndexer;
$fsgEnableIncrementalIndexer = true;

/**
 * If this variable is <true>, SMW's pre-defined properties will be indexed too.
 *
 * Single properties can be excluded from the facets via [[Ignore as facet::true]]
 */
global $fsgIndexPredefinedProperties;
$fsgIndexPredefinedProperties = true;

/**
 * Activates boosting
 */
global $fsgActivateBoosting;
$fsgActivateBoosting=false;

/**
 * The default boost index for all pages which are not boosted otherwise
 */
global $fsgDefaultBoost;
$fsgDefaultBoost = 1.0;

/**
 * All pages belonging to the categories are boosted by the given value.
 *
 * DO NOT add category prefix before the page title.
 */
global $fsgCategoryBoosts;
$fsgCategoryBoosts = array(
    // 'People' => 2.0
);

/**
 * All pages using one of the templates are boosted by the given value.
 *
 * DO NOT add template prefix before the page title.
 */
global $fsgTemplateBoosts;
$fsgTemplateBoosts = array(
    //'MyTemplate' => 5.5
);

/**
 * All pages in the namespaces are boosted by the given value
 * Use the namespace numbers here, not their names.
 */
global $fsgNamespaceBoosts;
$fsgNamespaceBoosts = array(
    // SMW_NS_PROPERTY => 3.0
);

/* End of BOOSTING properties ****************************************/


/**
 * All pages listed here are ignored by EnhancedRetrieval
 *
 * Please specify prefixed page title, e.g. Property:Name or Category:People
 */
global $fsgBlacklistPages;
$fsgBlacklistPages = array(
    // 'Category:People'
);

/**
 * Set of properties which are requested on search hits. Change this only
 * if you want to extend the search
 */
global $fsgExtraPropertiesToRequest;
$fsgExtraPropertiesToRequest = [];

/**
 * Show/hide UI parts
 */
global $fsgShowSortOrder, $fsgShowCategories, $fsgShowNamespaces;
$fsgShowSortOrder = true;
$fsgShowCategories = true;
$fsgShowNamespaces = true;

/**
 * Numeric property clusters
 *
 * Example:
 * $fsgNumericPropertyClusters['smwh_BaujahrMin_xsdvalue_d'] = [
 *     'min' => -9999,
 *     'max' => 9999,
 *     'lowerBound' => 1700,
 *     'upperBound' => 2030,
 *     'interval' => 10 ];
 * means that the given property gets a minimum cluster value of -9999, a maximum of 9999
 * and 33 x 10year-clusters from 1700 - 2030. min and max is optional.
 */
global $fsgNumericPropertyClusters;
$fsgNumericPropertyClusters = [];

/**
 * DateTime property clusters
 *
 * Example:
 *	$fsgDateTimePropertyClusters['smwh_Freigegeben__am_xsdvalue_dt'] =
 * 			[ 'min' => '1990-01-01-00:00:00', 'max' => '2030-12-31-23:59:59' ];
 */
global $fsgDateTimePropertyClusters;
$fsgDateTimePropertyClusters = [];

/**
 * User configurable category drop-down
 */
global $fsgCategoryFilter;
$fsgCategoryFilter = [];

/**
 * Annotations already shown in snippet
 *
 * Maps category names to a list of property names.
 * Articles from these categories show the values
 * of the given properties in the snippets (if values exist)
 *
 * Example:
 *	$fsgAnnotationsInSnippet['Dokument' => [ 'Abteilung', 'Dokumentart' ] ];
 */
global $fsgAnnotationsInSnippet;
$fsgAnnotationsInSnippet = [];

/**
 * Show the article properties button under each search result.
 */
global $fsgShowArticleProperties;
$fsgShowArticleProperties = true;

/**
 * Show the SOLR search score as a tooltip for the SHOW DETAILS link of each search result.
 */
global $fsgShowSolrScore;
$fsgShowSolrScore = false;

/**
 * Show in overlay
 */
global $fsgShowFileInOverlay;
$fsgShowFileInOverlay = ['pdf'];

/**
 * Index subobjects
 */
global $fsgIndexSubobjects;
$fsgIndexSubobjects = true;

/**
 * Facets shown per category
 */
global $fsgShownFacets;
$fsgShownFacets = [
    //'Person' => ['Name', 'Country', 'Gender', 'Age'],
    //'Company' => ['CEO', 'Country', 'BusinessArea']
];

/**
 * List of properties for which ER will offer a selection dialog for OR
 */
global $fsgFacetsWithOR;
$fsgFacetsWithOR = [];

/**
 * Categories which should be shown in title of search hit
 */
global $fsgCategoriesToShowInTitle;
$fsgCategoriesToShowInTitle = [];

/**
 * List of categories to show in the category facet (if empty all categories are shown)
 */
global $fsgShownCategoryFacets;
$fsgShownCategoryFacets = [];

/**
 * boolean property indicating that a search result should be highlighted (promoted)
 * use the SOLR field name here, e.g. 'smwh_HatInventarbeschrieb_xsdvalue_b' or false to turn it off
 */
global $fsgPromotionProperty;
$fsgPromotionProperty = false;

/**
 * boolean property indicating that a search result should be grayed out (demoted)
 * use the SOLR field name here, e.g. 'smwh_HatInventarbeschrieb_xsdvalue_b' or false to turn it off
 */
global $fsgDemotionProperty;
$fsgDemotionProperty = false;

/**
 * max. number of hits per page
 */
global $fsgHitsPerPage;
$fsgHitsPerPage = 10;

/**
 * Sort order default. Possible values: score, newest, oldest, ascending, descending
 */
global $fsgDefaultSortOrder;
$fsgDefaultSortOrder = "score";

/**
 * Indexes the image URL as separate attribute
 */
global $fsgIndexImageURL;
$fsgIndexImageURL = false;

/**
 * Pages of these namespaces are shown in the global search field (requires a patch)
 */
global $fsgNamespacesForSearchField;
$fsgNamespacesForSearchField = [ NS_MAIN ];
