console.log("ER: Loading scripts/FacetedSearch/Enhancements/fs_categoryFilter.js");

(function($) {
	
	var CategoryFilter = function() {
			
			var that = {};

			that.onCategoryChange = function(event) {
				var category = $('select#fs_category_filter option:selected').val();
				that.selectCategory(category);
			};
			
			that.selectCategory = function(category) {
				var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
				var regex = new RegExp('smwh_categories:.*');
				fsm.store.removeByValue('fq', regex);
				
				if (category != '') {
					fsm.store.addByValue('fq', 'smwh_categories:'+category);
				} 
				
				fsm.doRequest(0);
			};

			that.init = function() {
				$('select#fs_category_filter').change(that.onCategoryChange);
				
				var searchstring = window.location.href.substring(window.location.href.indexOf('?') + 1);
				searchstring = decodeURIComponent(searchstring);
				var regex = new RegExp('smwh_categories:([^&]*)');
				var result = regex.exec(searchstring);

				if (result == null) {
					$('select#fs_category_filter option').first().prop('selected', true);
				} else {
					// mark the category from the URI as selected in the drop down menu
					$('select#fs_category_filter option[value="'+result[1]+'"]').prop('selected', true);
				}

			};

			return that;
	};
	
	$(function() { 
		var cf = new CategoryFilter();
		cf.init();
	});
	
})(jQuery);