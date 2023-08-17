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
 * @ingroup FacetedSearchScripts
 * @author: Thomas Schweitzer
 */
console.log("ER: Loading scripts/FacetedSearch/FS_FacetedSearch.js");

if (typeof window.FacetedSearch == "undefined") {
	// Define the FacetedSearch module	
	window.FacetedSearch = { 
		classes : {}
	};
}

/**
 * @class FacetedSearch
 * This is the main class of the faceted search.
 * 
 */
FacetedSearch.classes.FacetedSearch = function () {
	var $ = jQuery;
	
	//--- Constants ---
	// The field with this name is used on SOLR queries
	var QUERY_FIELD = 'smwh_search_field';

	// The field on which highlighting is enabled
	var HIGHLIGHT_FIELD = 'smwh_search_field';
	
	// Name of the field with the document ID
	var DOCUMENT_ID = 'id';
	
	// Name of the SOLR field that stores relation values
	var RELATION_FIELD = 'smwh_properties';
	
	// Name of the SOLR field that stores attribute values
	var ATTRIBUTE_FIELD = 'smwh_attributes';

	// Name of the SOLR field that stores the modification date of an article
	var MODIFICATION_DATE_FIELD = 'smwh__MDAT_datevalue_l';

	// Name of the SOLR field that stores the title of an article with type
	// 'wiki'
	var TITLE_FIELD = 'smwh_title';

	// Name of the SOLR field that stores the namespace id of an article
	var NAMESPACE_FIELD = 'smwh_namespace_id';

	// Name of the SOLR field that stores the dispaly title of an article as string.
	var DISPLAY_TITLE_FIELD = 'smwh_displaytitle';
	
	// Names of the facet classes
	var FACET_FIELDS = ['smwh_categories', ATTRIBUTE_FIELD, RELATION_FIELD,
						NAMESPACE_FIELD];
						
	// Names of all fields that are returned in a query for documents
	var QUERY_FIELD_LIST = [MODIFICATION_DATE_FIELD,
							'smwh_categories',
							'smwh_directcategories',
							ATTRIBUTE_FIELD, 
							RELATION_FIELD,
							DOCUMENT_ID,
							TITLE_FIELD,
							NAMESPACE_FIELD,
							'score',
							DISPLAY_TITLE_FIELD];
						
	var RELATION_REGEX = /^smwh_(.*)_(.*)$/;
	var ATTRIBUTE_REGEX = /smwh_(.*)_xsdvalue_(.*)/;
	
	// Wait 500ms for new key presses before the search is executed
	var KEY_DELAY = 500;
	
	var solrPresent = false;
	
	//--- Private members ---

	// The instance of this object
	var that = {};
	
	// AjaxSolr.FSManager - The manager from the AjaxSolr library.
	var mAjaxSolrManager;
	
	// reference to the (dummy) relation widget
	var mRelationWidget;
	
	// {Array} Array of strings. It contains the names of all facets that are
	// currently expanded in the UI.
	var mExpandedFacets = [];
	 
	//--- Getters/Setters ---
	that.getAjaxSolrManager = function() {
		return mAjaxSolrManager;
	};
	
	that.getRelationWidget = function() {
		return mRelationWidget;
	};
	
	function getSearch() {
		var mSearch = $('#query').val();

		// trim the search term
		mSearch = mSearch.replace(/^\s*(.*?)\s*$/,'$1');
	
		if (mSearch == '*' || mSearch == '') {
			mSearch="(*)";
		}

		return mSearch;
	}
	that.getSearch = getSearch;
	
	/**
	 * Adds the given facet to the set of expanded facets in the UI, if it is a
	 * property facet.
	 * 
	 * @param {String} facet
	 * 		Name of the facet
	 */
	that.addExpandedFacet = function (facet) {
		if ($.inArray(facet, mExpandedFacets) === -1) {
			if (isProperty(facet)) {
				mExpandedFacets.push(facet);
			}
		}
	};
	
	/**
	 * Return true if the given facet is expanded in the User Interface.
	 * 
	 * @param {String} facet
	 * 		Name of the facet
	 * @return {bool}
	 * 		true, if the facet is expanded
	 */
	that.isExpandedFacet = function (facet) {
		return $.inArray(facet, mExpandedFacets) >= 0;
	};
	
	/**
	 * Removes the given facet from the set of expanded facets in the UI. If no
	 * facet name is given, all facets are removed.
	 * 
	 * @param {String} facet
	 * 		Name of the facet. If this parameter is missing, all facets are removed.
	 */
	that.removeExpandedFacet = function (facet) {
		if (typeof facet === 'undefined') {
			mExpandedFacets.length = 0;
			return;
		}
		var pos = $.inArray(facet, mExpandedFacets);
		if (pos === -1) {
			return;
		}
		// Replace the element to be removed by the last element of the array...
		var len = mExpandedFacets.length;
		mExpandedFacets[pos] = mExpandedFacets[len-1];
		// ... and reduce the array's length
		mExpandedFacets.length = len - 1;
	};
	
	/**
	 * Shows the property values of all expanded facets.
	 */
	that.showExpandedFacets = function () {
		for (var i = 0; i < mExpandedFacets.length; ++i) {
			var facet = mExpandedFacets[i];
			FacetedSearch.classes.ClusterWidget.showPropertyDetailsHandler(facet);
		}
	};
	
	/**
	 * Constructor for the FacetedSearch class.
	 */
	function construct(){
	}
	that.construct = construct;
		
	/**
	 * Constructor for the FacetedSearch class.
	 */
	function createUserInterface() {
		createSolrManager();
		createWidgets();
		addEventHandlers();
		initializeGUIElements();
		
		initNamespaces();
		
		// Show all results at start up
		updateSearchResults();
	}
	that.createUserInterface = createUserInterface;
	
	/**
	 * Keyup event handler for the search input field.
	 */
	that.onSearchKeyup = function () {
		var timestamp = new Date().getTime();
		that.timerstamp = timestamp;
		window.setTimeout(function () {
			if (that.timerstamp == timestamp) {
				
				updateSearchResults();
				
				if (!solrPresent) {
					checkSolrPresent();
				}
			}
		},KEY_DELAY);
		return false;
	};
		
	/**
	 * Event handler for the search order selection field. A new SOLR resquest is
	 * sent for the new search result order.
	 */
	that.onSearchOrderChanged = function() {
		var selected =  $("#fs_sort_order_drop_down option:selected");
		var order = selected[0].value;
		var sort = getSortOrderModifier(order);
		
		mAjaxSolrManager.store.addByValue('sort', sort);
		mAjaxSolrManager.doRequest(0);
		return false;
	};
	
	/**
	 * Event handler for clicking the search button. A new SOLR request is 
	 * triggered.
	 */
	that.onSearchButtonClicked = function () {
		updateSearchResults();

		if (!solrPresent) {
			checkSolrPresent();
		}
	};
	
	/**
	 * Adds the given widget to the SOLR manager.
	 * 
	 * @param {AbstractWidget} widget
	 */
	that.addWidget = function (widget) {
		mAjaxSolrManager.addWidget(widget);
	};
	
	/**
	 * Gets the search term from the input field and triggers a new SOLR request.
	 * All widgets will be updated.
	 */
	function updateSearchResults() {
		var searchText = getSearch(); 
		
		// If the query is enclosed in parentheses it is treated as an expert query.
		// Expert queries may contain logical operators. Text is not converted
		// to lowercase.
		var isExpertQuery = searchText.charAt(0) === '(' && searchText.charAt(searchText.length-1) === ')';
		
		if (isExpertQuery) {
			// A colon in the search term must be escaped otherwise SOLR will throw
			// a parser exception
			var qs = searchText.replace(/(:)/g,"\\$1");
		} else {
			var qs = prepareQueryString(searchText) + prepareTitleQuery(searchText);
		}
		
		mAjaxSolrManager.store.addByValue('q.alt', QUERY_FIELD + ':' + qs);
		mAjaxSolrManager.store.addByValue('searchText', searchText);
		readPrefixParameter();
		mAjaxSolrManager.doRequest(0);
	}
	
	/**
	 * Prepare query for exact title matches
	 */
	function prepareTitleQuery(searchText) {
		var exactMatchQuery = '';
		if (searchText != '') {
			var escapedSearchText = searchText.toLowerCase()
					.replace(/([\+\-!\(\)\{\}\[\]\^"~\*\?\\:])/g, '\\$1')
					.replace(/(&&|\|\|)/g,'\\$1')
					.replace(/\s\s*/g, ' ');
			var parts = escapedSearchText.split(" ");
			escapedSearchText = parts.join(" AND ");
			escapedSearchText = '(' + escapedSearchText + ')';
			exactMatchQuery = ' OR ' + QUERY_FIELD + ':'+ escapedSearchText;
			exactMatchQuery += ' OR ' + TITLE_FIELD + ':' + escapedSearchText;
			exactMatchQuery += ' OR ' + DISPLAY_TITLE_FIELD + ':' + escapedSearchText;

		}
		return exactMatchQuery;
	}
  
	/**
	 * Translates a query string that is not an expert query (i.e. not enclosed in
	 * parentheses) to a SOLR query string:
	 * - A * is appended to the last word. 
	 *   Example: foo -> (+foo*) 
	 *            Searches for all documents containing words starting with foo
	 * - Single words are converted to lowercase as the index is also lowercase
	 *   Example: FOO -> (+foo*)
	 *            Searches for lowercase words starting with foo
	 * - Single words are concatenated with the + operator (AND)
	 *   Example: foo bar -> (+foo +bar*)
	 *            Searches for documents containing the word foo and words 
	 *            starting with bar
	 * - Preserve phrase expressions:
	 *   Example: foo "This is bar" "This is foobar" -> (+foo +"This is bar" +"This is foobar")
	 *            Searches for documents containing the word foo and the phrases 
	 *            'This is bar' and 'This is foobar'.
	 * - Escapes all special characters that belong to the SOLR query syntax
	 *   Example: (foo+bar) "(foo) in a (bar)" -> (\(foo\+bar\) "\(foo\) in a \(bar\)") 
	 *            Searches for documents containing words starting with (foo+bar)
	 *            and the phrase '(foo) in a (bar)'
	 * @return {String}
	 * 		The prepared query string
	 */
	function prepareQueryString(searchText) {
		// Extract all phrases
		var phrases = searchText.match(/".*?"/g);
		var endWithPhrase = searchText.charAt(searchText.length-1) === '"';
		
		// Remove phrases from the query string and trim it
		searchText = searchText.replace(/(".*?")/g, '')
								 .replace(/^\s*(.*?)\s*$/,'$1')
								 .replace(/\s\s*/g, ' ');
		
		// Split the query string at spaces in words
		var words = searchText.split(' ');
		
		var queryString = "";
		
		// Convert words to lower case and escape the special characters:
		// + - && || ! ( ) { } [ ] ^ " ~ * ? : \			
		for (var i = 0, numWords = words.length; i < numWords; ++i) {
			var w = words[i].toLowerCase()
			                   .replace(/([\+\-!\(\)\{\}\[\]\^"~\*\?\\:])/g, '\\$1')
					           .replace(/(&&|\|\|)/g,'\\$1');
			// Add a * to the last word if the query string does not end with a phrase
			if (!endWithPhrase && i == numWords-1) {
				w += '*';
				queryString += "+" + w + " ";
			} else {
				queryString += "+" + w + " AND ";

			}
		}
		
		// Escape special characters in phrases
		if (phrases) {
			for (i = 0; i < phrases.length; ++i) {
				var p = phrases[i].substring(1, phrases[i].length-1);
				p = '+"' + p.replace(/([\+\-!\(\)\{\}\[\]\^"~\*\?\\:])/g, '\\$1')
								.replace(/(&&|\|\|)/g,'\\$1') +
						'" ';
				queryString += p;
			}
		}
		
		if (queryString.length > 0) {
			queryString = '(' + queryString + ')';
		}
		
		return queryString;		
	}
	
	/**
	 * Initializes the event handlers for the User Interface.
	 */
	function addEventHandlers() {
		// Keyup handler for the search input field
		$('#query').keyup(that.onSearchKeyup);
		$('#fs_sort_order_drop_down').change(that.onSearchOrderChanged);
		$('#search_button').click(that.onSearchButtonClicked);
	}
	
	/**
	 * Initialize GUI elements after initial load.
	 * 
	 */
	function initializeGUIElements() {
		var sort = mAjaxSolrManager.store.values('sort');
		if (!sort || sort.length == 0 || sort[0].length == 0) {
			return;
		}
		
		switch(sort[0]) {
			case MODIFICATION_DATE_FIELD + ' desc, score desc':
				var val = 'newest';
				break;
			case MODIFICATION_DATE_FIELD + ' asc, score desc':
				var val = 'oldest';
				break;
			case DISPLAY_TITLE_FIELD + ' asc, score desc':
				var val = 'ascending';
				break;
			case DISPLAY_TITLE_FIELD + ' desc, score desc':
				var val = 'descending';
				break;
			default:
				var val = 'relevance';
		}
		$("#fs_sort_order_drop_down option[value="+val+"]").prop('selected', true);
	}
	
	/**
	 * Prefix parameter (optional)
	 */
	function readPrefixParameter() {
		var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
		
		// parse prefix param
		var prefix = $('input#fs-prefix-param').val();
		prefix = decodeURIComponent(prefix);
		var parts = prefix.split('&');
		var params = {};
		for (var i = 0; i < parts.length;i++) {
			var keyValue = parts[i].split("=");
			params[keyValue[0]] = keyValue[1];
		}
		
		// apply prefix param
		for(var param in params) {
			
			if (param == '') {
				continue;
			}
			
			switch(param.toLowerCase()) {
				case 'category':
					$('select#fs_category_filter option[value="'+params[param]+'"]').prop('selected', true);
					var regex = new RegExp('smwh_categories:.*');
					fsm.store.removeByValue('fq', regex);
					
					var category = params[param];
					if (category != '') {
						fsm.store.addByValue('fq', 'smwh_categories:'+category);
					} 
					break;
					
				case 'sort':	
					$('select#fs_sort_order_drop_down option[value="'+params[param]+'"]').prop('selected', true); 
					that.onSearchOrderChanged();
					break;
					
				default:
					var property = params[param];
					if (property != '') {
						fsm.store.addByValue('fq', param+':'+property);
					} 
			}
		}
		
		// clear init parameters
		$('input#fs-prefix-param').val('');
	}
	
	/**
	 * Checks if the SOLR server is responding
	 */
	function checkSolrPresent() {
		var sm = new AjaxSolr.FSManager({
			solrUrl : mw.config.get('wgFSSolrURL'),
			servlet : mw.config.get('wgFSSolrServlet'),
			handleResponse : function (data) {
				solrPresent = true;
			},
			handleErrorResponse: function() {
				if (numTries >= 15) {
					
					$("#waiting_for_solr div").remove();
					var errorHint = $('<div>')
						.text(mw.msg('solrConnectionError'));
					errorHint.css({'color' : 'red', 'font-weight' : 'bold'});
					$("#waiting_for_solr").append(errorHint);
					
				}
			}
		});
		var dots = '';
		var numTries = 0;
		sm.init();
		sm.store.addByValue('q.alt', '*:*');
		sm.doRequest(0);
		
		var lang = FacetedSearch.singleton.Language;
		$("#waiting_for_solr").text(mw.msg('tryConnectSOLR'));
		$("#results").hide();
		$("#waiting_for_solr").show();
		
		var interval = setInterval(function () {
			if (!solrPresent) {
				var msg = (numTries < 3)
					? mw.msg('tryConnectSOLR')
					: mw.msg('solrNotFound', mw.config.get('wgFSSolrURL') + mw.config.get('wgFSSolrServlet')) + dots
				$("#waiting_for_solr").text(msg);
				dots += '.';
				++numTries;
				if (numTries >= 15) {
					// stop after 15 retries
					clearInterval(interval);
					return;
				}
				sm.doRequest(0);
			} else {
				clearInterval(interval);
				$("#waiting_for_solr").hide();
				$("#results").show();
			}
		}, 1000);
	}
	
	/**
	 * This function retrieves all namespaces that are currently populated in the
	 * wiki. The namespace widget is initialized with these namespaces.
	 */
	function initNamespaces() {
		var sm = new AjaxSolr.FSManager({
			solrUrl : mw.config.get('wgFSSolrURL'),
			servlet: mw.config.get('wgFSSolrServlet'),
			handleResponse : function (data) {
				var namespaces = data.facet_counts.facet_fields[NAMESPACE_FIELD];
				var ns = [];
				for (var nsid in namespaces) {
					ns.push(nsid);
				}
				mAjaxSolrManager.addWidget(new FacetedSearch.classes.NamespaceFacetWidget({
					id : 'fsf' + NAMESPACE_FIELD,
					target : '#field_namespaces',
					field : NAMESPACE_FIELD,
					mNamespaces: ns
				}));
				
			}
		});
		sm.init();
		sm.store.addByValue('q.alt', '*:*');
		sm.store.addByValue('fl', NAMESPACE_FIELD);		
		sm.store.addByValue('facet', true);		
		sm.store.addByValue('facet.field', NAMESPACE_FIELD);		
		sm.store.addByValue('json.nl', 'map');	
		sm.doRequest(0);
	}
	
	/**
	 * Checks if the given name is a name for an attribute or relation.
	 * 
	 * @param {string} name
	 * 		The name to examine
	 * @return {bool}
	 * 		true, if name is a property name
	 */
	function isProperty(name) {
		return name.match(ATTRIBUTE_REGEX)|| name.match(RELATION_REGEX);
	}
		
	/**
	 * Initializes the parameter store of the main ajax solr manager. If SOLR
	 * parameters are given in the URL, these values are used. Otherwise the 
	 * default values are set.
	 */
	function initParameterStore() {
		initParameterStoreDefault();
		// overwrite defaults with values from URL
		initParameterStoreFromURL();
	}
	
	/**
	 * Tries to initialize the parameter store of the main ajax solr manager with
	 * parameters given in the URL. These parameters are given in the value 
	 * "fssearch". 
	 * @return {bool}
	 * 		true: Parameters are given in the URL. Store was initialized.
	 * 		false: No parameters given. Store was not initialized.
	 */
	function initParameterStoreFromURL() {
		var url = document.URL;
		var params = url.match(/^.*[?&]fssearch=(.*)$/);
		if (params) {
			mAjaxSolrManager.store.parseString(decodeURIComponent(params[1]));
			// Is a query string given?
			var searchText = mAjaxSolrManager.store.get('searchText').val();
			if (searchText) {
				if (searchText == '(*)') {
					return true;
				}
				$('#query').val(searchText);
			}
			return true;
		}
		return false;
	}
	
	function getSortOrderModifier(order) {
		var sort;
		switch (order) {
		case "relevance":
			sort = 'score desc, ' + DISPLAY_TITLE_FIELD + ' asc';
			break;
		case "newest":
			sort = MODIFICATION_DATE_FIELD + ' desc, score desc';
			break;
		case "oldest":
			sort = MODIFICATION_DATE_FIELD + ' asc, score desc';
			break;
		case "ascending":
			sort = DISPLAY_TITLE_FIELD + ' asc, score desc';
			break;
		case "descending":
			sort = DISPLAY_TITLE_FIELD + ' desc, score desc';
			break;
		default:
			sort = 'score desc';
		}
		return sort;
	}
	
	/**
	 * Initializes the parameter store of the main ajax solr manager with default
	 * values.
	 */
	function initParameterStoreDefault() {
		for (var i = 0; i < mw.config.get('ext.er.extraPropertiesToRequest').length; i++) {
			QUERY_FIELD_LIST.push(mw.config.get('ext.er.extraPropertiesToRequest')[i]);
		}
		
		var params = {
			'defType': 'edismax',
			'boost': 'max(smwh_boost_dummy)',
			'facet': true,
			'facet.field': FACET_FIELDS,
			'facet.mincount': 1,
			'json.nl': 'map',
			'fl': QUERY_FIELD_LIST,
			'hl': true,
			'hl.fl': HIGHLIGHT_FIELD,
			'hl.simple.pre': '<b>',
			'hl.simple.post': '</b>',
			'hl.fragsize': '250',
			'sort': getSortOrderModifier(mw.config.get('ext.er.DEFAULT_SORT_ORDER')),
			'q.alt': '*:*'
		};
		
		// initialize the parameter store
		for (var name in params) {
			mAjaxSolrManager.store.addByValue(name, params[name]);
		}
	}
	
	/**
	 * @private
	 * Creates the SOLR manager
	 */
	function createSolrManager(){
		mAjaxSolrManager = new AjaxSolr.FSManager({
			solrUrl: mw.config.get('wgFSSolrURL'),
			servlet: mw.config.get('wgFSSolrServlet')
			});
	}
	
	/**
	 * @private
	 * Creates and attaches all widgets to the SOLR manager
	 */
	function createWidgets() {
		mAjaxSolrManager.addWidget(new FacetedSearch.classes.LinkCurrentSearchWidget({
			id: 'currentSearchLink',
			target: '#current_search_link'
		}));

		mAjaxSolrManager.addWidget(new FacetedSearch.classes.ResultWidget({
		  id: 'article',
		  target: '#docs'
		}));
		
		// Add the widgets for the standard facets
		var categoryFacet = FACET_FIELDS[0];
		var relationFacet = FACET_FIELDS[1];
		var attributeFacet = FACET_FIELDS[2];
		mAjaxSolrManager.addWidget(new FacetedSearch.classes.FacetWidget({
			id : 'fsf' + categoryFacet,
			target : '#field_categories',
			field : categoryFacet
		}));
		mRelationWidget = new FacetedSearch.classes.FacetWidget({
			id : 'fsf' + relationFacet,
			target : '#field_dummy',
			field : relationFacet,
			noRender : true
		});
		mAjaxSolrManager.addWidget(mRelationWidget);
		mAjaxSolrManager.addWidget(new FacetedSearch.classes.FacetWidget({
			id : 'fsf' + attributeFacet,
			target : '#field_properties',
			field : attributeFacet,
			fields : [ relationFacet, attributeFacet ]
		}));

		// paging
		var lang = FacetedSearch.singleton.Language;

		mAjaxSolrManager.addWidget(new FacetedSearch.classes.PagerWidget({
			id : 'pager',
			target : '#pager',
			prevLabel : mw.msg('pagerPrevious'),
			nextLabel : mw.msg('pagerNext'),
			renderHeader : function(perPage, offset, total, approx) {
				$('#pager-header').html(
						$('<span/>').text(
								mw.msg('results') + ' ' 
								+ Math.min(total, offset + 1)
								+ ' ' + mw.msg('to') + ' '
								+ Math.min(total, offset + perPage)
								+ ' ' 
								+ mw.msg(approx ? 'ofapprox' : 'of') 
								+ ' ' + total));
			}
		}));
		
		// current search filters
		mAjaxSolrManager.addWidget(new FacetedSearch.classes.CurrentSearchWidget({
			id: 'currentsearch',
		  	target: '#selection'
		}));
		
		// Inform all extensions that widgets can be added now
		$(that).trigger('FSAddWidgets');

		// init
		mAjaxSolrManager.init();
		initParameterStore();
		checkSolrPresent();	
	}
	
	construct();
	
	// Public constants
	that.FACET_FIELDS		 = FACET_FIELDS;
	that.DOCUMENT_ID		 = DOCUMENT_ID;
	that.HIGHLIGHT_FIELD	 = HIGHLIGHT_FIELD;
	that.RELATION_FIELD		 = RELATION_FIELD;
	that.ATTRIBUTE_FIELD	 = ATTRIBUTE_FIELD;
	that.NAMESPACE_FIELD	 = NAMESPACE_FIELD;
	that.TITLE_FIELD		 = TITLE_FIELD;
	that.DISPLAY_TITLE_FIELD = DISPLAY_TITLE_FIELD;
	return that;
}

// Create the singleton instance of Faceted Search
if (!FacetedSearch.singleton) {
	FacetedSearch.singleton = {};
}
FacetedSearch.singleton.FacetedSearchInstance = FacetedSearch.classes.FacetedSearch();

jQuery(document).ready(function() {
	FacetedSearch.singleton.FacetedSearchInstance.createUserInterface();
});
