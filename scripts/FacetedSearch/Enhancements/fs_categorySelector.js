console.log("ER: Loading scripts/FacetedSearch/Enhancements/fs_categorySelector.js");

(function($) {
	
	var CategorySelector = function() {
			
			var that = {};
			
			that.init = function() {
				var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
				var categoryWidget = fsm.getWidget('fsfsmwh_categories');
				categoryWidget.addPropertyFacetListener(that.categoryFacetListener);
				
			};
			
			that.categoryFacetListener = function(facetItem) {
				
				if (mw.config.get('ext.er.SHOWN_CATEGORY_FACETS').length == 0) {
					return true;
				}
				
				return mw.config.get('ext.er.SHOWN_CATEGORY_FACETS').indexOf(facetItem.facet) > -1;
					
			};
			
			return that;
	};
	
	$(function() { 
		var cs = new CategorySelector();
		cs.init();
		
	});
	
})(jQuery);