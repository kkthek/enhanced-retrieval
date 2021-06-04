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
console.log("ER: Loading scripts/FacetedSearch/FS_BooleanFacetClusterer.js");

if (typeof window.FacetedSearch == "undefined") {
// Define the FacetedSearch module	
	window.FacetedSearch = { 
		classes : {}
	};
}

/**
 * @class BooleanFacetClusterer
 * This class shows the values of a facet with type "string".
 * 
 */
FacetedSearch.classes.BooleanFacetClusterer = function (facetName, plainName) {

	//--- Constants ---
	
	//--- Private members ---

	
	// Call the constructor of the super class
	var that = FacetedSearch.classes.FacetClusterer(facetName, plainName);
	

	/**
	 * Constructor for the BooleanFacetClusterer class.
	 * 
	 * @param string facetName
	 * 		The full name of the facet whose values are clustered. 
	 */
	function construct(facetName, plainName) {
	};
	that.construct = construct;
	
	/**
	 * Retrieves the clusters for the facet of this instance
	 */
	that.retrieveClusters = function () {
		var asm = that.getAjaxSolrManager();
		var facet = that.getFacetName();
	
		var fpvw = new FacetedSearch.classes.FacetPropertyValueWidget({
			id : 'fsf' + facet,
			target : '#'+AjaxSolr.theme.prototype.getPropertyValueHTMLID(facet),
			field : facet
		});
		fpvw.initObject();

		asm.addWidget(fpvw);
		asm.store.addByValue('facet.field', facet);

		asm.doRequest(0);
	}
			
	construct(facetName, plainName);
	return that;
	
}
