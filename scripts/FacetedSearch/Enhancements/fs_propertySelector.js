console.log("ER: Loading scripts/FacetedSearch/Enhancements/fs_propertySelector.js");

(function($) {
	
	var PropertySelector = function() {
			
			var that = {};
			
			that.init = function() {
				var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
				var propertyWidget = fsm.getWidget('fsfsmwh_properties');
				propertyWidget.addPropertyFacetListener(that.propertyFacetListener);
				
			};
			
			that.propertyFacetListener = function(facetItem) {
				
				var selectedCategory = $('select#fs_category_filter option:selected').val();
				
				if (mw.config.get('ext.er.SHOWNFACETS')[selectedCategory]) {
					var propertiesToShow = mw.config.get('ext.er.SHOWNFACETS')[selectedCategory];
					for (var i = 0; i < propertiesToShow.length; i++) {
						var property = propertiesToShow[i];
						property.replace(/\s/g, '_');
						if (facetItem.facet.indexOf('smwh_'+property+'_') === 0) {
							return true;
						}
					}
					return false;
				}
				
				// if category is not configured, show all properties
				return true;
			};
			
			return that;
	};
	
	$(function() { 
		var ps = new PropertySelector();
		ps.init();
		
		$('#xfsAvailableFacets').click(function() { 
			$('#xfsPropertyFacetContainer').toggle();
		});
	});
	
})(jQuery);