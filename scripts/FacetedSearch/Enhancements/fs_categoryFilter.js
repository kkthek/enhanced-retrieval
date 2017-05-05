(function($) {
	
	var CategoryFilter = function() {
			
			var that = {};
			
			that.init = function() {
				$('select#fs_category_filter').change(that.onCategoryChange);
				
				var searchstring = window.location.href.substring(window.location.href.indexOf('?') + 1);
				searchstring = decodeURIComponent(searchstring);
				var regex = new RegExp('smwh_categories:([^&]*)');
				var result = regex.exec(searchstring);
				if (result == null) {
					return;
				}
				// there should be only one category at a time in ODB
				$('select#fs_category_filter option[value="'+result[1]+'"]').prop('selected', true);
				
			};
			
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
			
			
			return that;
	};
	
	$(function() { 
		var cf = new CategoryFilter();
		cf.init();
	});
	
})(jQuery);