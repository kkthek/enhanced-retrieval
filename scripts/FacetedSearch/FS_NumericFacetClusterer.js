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
console.log("ER: Loading scripts/FacetedSearch/FS_NumericFacetClusterer.js");

if (typeof window.FacetedSearch == "undefined") {
// Define the FacetedSearch module	
	window.FacetedSearch = { 
		classes : {}
	};
}

/**
 * @class NumericFacetClusterer
 * This class clusters the values of a facet with type "double".
 * 
 */
FacetedSearch.classes.NumericFacetClusterer = function (facetName, plainName) {

	//--- Constants ---
	
	//--- Private members ---

	
	// Call the constructor of the super class
	var that = FacetedSearch.classes.FacetClusterer(facetName, plainName);
	

	/**
	 * Constructor for the NumericFacetClusterer class.
	 * 
	 * @param string facetName
	 * 		The full name of the facet whose values are clustered. 
	 */
	function construct(facetName, plainName) {
	};
	that.construct = construct;
	
	
	/**
	 * This function generates clusters for number values between min and max.
	 * 
	 * @param {int} min
	 * 		The minimal number value of the value range.
	 * @param {int} max
	 * 		The maximal number value of the value range.
	 */
	that.makeClusters = function makeClusters(min, max) {
		if (mw.config.get('ext.er.numericPropertyClusters')[facetName]) {
			return that.makeCustomClusters(min, max);
		}
		var diff = max - min;
		var values = [];
		var currVal = min;
		var incr = diff / that.NUM_CLUSTERS;
		
		for (var i = 0; i < that.NUM_CLUSTERS; ++i) {
			values[i] = Math.round(currVal);
			currVal += incr;
		}
		values[i] = max+1;
		for (i = 0; i < values.length-1; ++i) {
			values[i] = [values[i], values[i+1]-1];
		}
		values.splice(values.length-1,1);
		return values;
	};
	
	that.makeCustomClusters = function(min, max) {
		var clusters = mw.config.get('ext.er.numericPropertyClusters')[facetName];
		var values = [];
		if (clusters['min']) {
			values.push([clusters['min'], clusters['min']]);
		}
		if (clusters['lowerBound'] && clusters['upperBound'] && clusters['interval']) {
			var currVal = clusters['lowerBound'];
			var numClusters = (clusters['upperBound'] - clusters['lowerBound']) / clusters['interval'];
			
			for (var i = 0; i < numClusters; ++i) {
				values.push([ Math.round(currVal), Math.round(currVal + clusters['interval']) - 1 ]);
				currVal += clusters['interval'];
			}
			
		}
		if (clusters['max']) {
			values.push([clusters['max'], clusters['max']]);
		}
		return values;
	};
		
//	that.makeCustomClusters_ALTERNATIVE = function(min, max) {
//		var clusters = mw.config.get('ext.er.numericPropertyClusters')[facetName];
//		var interval = clusters['interval'];
//		var start = Math.floor(min / interval) * interval;
//			
//		var values = [];
//		for (var i = start; i < max; i=i+interval) {
//			values.push( [ i, i + interval ] );
//		}
//		console.log("makeCustomCluster");
//		console.log(values);
//		
//		return values;
//	};
		
	construct(facetName, plainName);
	return that;
	
}
