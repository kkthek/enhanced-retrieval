console.log("ER: Loading scripts/FacetedSearch/FS_Utils.js");

(function($) {

	window.XFS = window.XFS || {};
	window.XFS.Util = window.XFS.Util || {};

	window.XFS.Util.getFacetName = function(property) {
		if (property.match(/_t$/)) {
			return property.replace(/_t$/, '_s');
		}
		return property;
	};

	window.XFS.Util.getFacetType = function(facet) {
		var ATTRIBUTE_REGEX = /smwh_(.*)_xsdvalue_(.*)/;
		if (facet.match(ATTRIBUTE_REGEX)) {
			// Attribute field
			field = 'smwh_attributes';
		} else {
			// Relation field
			field = 'smwh_properties';
		}
		return field;
	};

	/**
	 * Checks if one of the categories appear in doc
	 */
	window.XFS.Util.isInCategory = function(doc, categories) {
		if (!doc.smwh_categories) return false;
		
		for(var i = 0; i < categories.length; i++) {
			if ($.inArray(categories[i], doc.smwh_categories) != -1) {
				return true;
			}
		}
		
		return false;
	};
	
	window.XFS.Util.AjaxIndicator = function() {
		var that = {};
		
		/**
		 * Ajax indicator for the whole page
		 */
		that.setGlobalLoading = function(state) {			
			if ($('.globalSpinner').length == 0) {
				$('body').append($('<div class="globalSpinner" style="display: none;"></div>'))
			}
			
			var wgScriptPath = mw.config.get('wgScriptPath');
			css = {
				'background-image' : 'url(' + wgScriptPath + '/extensions/EnhancedRetrieval/skin/images/ajax-preview-loader.gif)',
				'background-repeat' : 'no-repeat',
				'background-position' : 'center'
			};

			if (state) {
				$('.globalSpinner').css(css).show();
			} else {
				$('.globalSpinner').css(css).hide();
			}
		};

		/**
		 * Returns current state of the Ajax indicator 
		 */
		that.getGlobalLoading = function() {
			if ($('.globalSpinner').length == 0) {
				return false;
			}
			return $('.globalSpinner').is(':visible') ;
		};

		return that;
	}

})(jQuery);