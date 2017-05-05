
(function($) {

	var xfs = window.XFS || {};

	/**
	 * Returns the page title and optionally an appendix displayed directly after the title.
	 * 
	 * @param doc
	 * @returns {___anonymous339_340}
	 */
	xfs.getPageTitle = function(doc) {
		var cats  = doc['smwh_categories'];
		
		var vals = [];
		if (typeof cats !== 'undefined') {
			
			vals = $(cats).filter(window.XFS.CATEGORIES_TO_SHOW_IN_TITLE).toArray();
			
		}
		
		var res = {};
		if (doc.smwh_Titel_xsdvalue_t) {
			res.title = doc[XFS.titlePropertyField][0];
			res.appendix = vals.join(", ");
		} else {
			res.title = doc.smwh_title;
			res.appendix = '';
		}
		return res;
	};
	

})(jQuery);