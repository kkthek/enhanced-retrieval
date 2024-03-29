/*
 * Copyright (C) Vulcan Inc.
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
console.log("ER: Loading scripts/FacetedSearch/FS_CreateArticleWidget.js");

if (typeof window.FacetedSearch == "undefined") {
//	Define the FacetedSearch module	
	window.FacetedSearch = { 
			classes : {}
	};
}

/**
 * @class CreateArticleWidget
 * This widget displays a link for creating an article if the search term is not
 * the name of an existing article.
 * 
 */
(function ($) {
	
FacetedSearch.classes.CreateArticleWidget = AjaxSolr.AbstractWidget.extend({

	/**
	 * This function is called when a request to the SOLR manager returns data.
	 * The data contains the article names that may match the search term. This
	 * function check is one of the article names matches the search term according
	 * to the MediaWIki rule i.e. case-insensitive first letter, spaces etc.
	 * If there is no matching name, a link for creating the article is generated.
	 * 
	 */
	afterRequest: function () {
		var fsi = FacetedSearch.singleton.FacetedSearchInstance;
		var docs = this.manager.response.response.docs;
		var tcd = this.manager.titleCheckData;
		var articleExists = false;
		var title = tcd.title;
		for (var i = 0, l = docs.length; i < l; ++i) {
			var doc = docs[i];
			var docNS = doc[fsi.NAMESPACE_FIELD].toString();
			if ((tcd.namespace === false && docNS === '0')
			    || (tcd.namespace === docNS)) {
				articleExists = this.checkArticleNameMatches(title, doc[fsi.TITLE_FIELD]);
				if (articleExists) {
					break;
				}
			}
		}
		$(this.target).empty();
        if (!articleExists) {
            // Check if the name starts with a known namespace
            var ns = mw.config.get('wgFormattedNamespaces')[tcd.namespace] || '';
            var colon = ns ? ':' : '';
            var articleName = ns+colon+title;
            var wgFSCreateNewPageLink = mw.config.get('wgFSCreateNewPageLink');
            if (wgFSCreateNewPageLink == false) {
            	return;
            }
            var cnpLink = wgFSCreateNewPageLink.replace(/\{article\}/g, articleName);
            var link = mw.config.get('wgServer') + mw.config.get('wgScript') + cnpLink;
            if (wgFSCreateNewPageLink != '' && articleName != '(*)') {
            	$(this.target)
                	.append(AjaxSolr.theme('createArticle', articleName, link));
            }
        }
	},
	
	/**
	 * This function checks if a searched title matches the existing title.
	 * @param {String} searchedTitle
	 * 		Name of an article that was searched for
	 * @param {Object} existingTitle
	 * 		Name of an existing article
	 * @return {bool} 
	 * 		true, if the titles match
	 * 		false otherwise
	 */
	checkArticleNameMatches: function (searchedTitle, existingTitle) {
		// Check for identity
		if (searchedTitle === existingTitle) {
			return true;
		}
		
		// replace spaces by underscores
		searchedTitle = searchedTitle.replace(/ /g, '_');
		existingTitle = existingTitle.replace(/ /g, '_');
		
		// make the first letter uppercase
    	searchedTitle = searchedTitle.charAt(0).toUpperCase()+searchedTitle.substr(1);
    	existingTitle = existingTitle.charAt(0).toUpperCase()+existingTitle.substr(1);
		
		return searchedTitle === existingTitle;

	}
	
});

})(jQuery);
