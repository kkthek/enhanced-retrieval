
(function($) {

	var xfs = window.XFS || {};

	/**
	 * Returns the page title and optionally an appendix displayed directly after the title.
	 * 
	 * @param doc
	 * @returns JS object with title and appendix properties
	 */
	xfs.getPageTitle = function(doc) {
		var cats  = doc['smwh_categories'];
		
		var vals = [];
		if (typeof cats !== 'undefined') {
			vals = $(cats).filter(window.XFS.CATEGORIES_TO_SHOW_IN_TITLE).toArray();
		}
		
		var res = {};
		if (doc[XFS.titlePropertyField]) {
			res.title = doc[XFS.titlePropertyField][0];
			res.appendix = vals.join(", ");
		} else {
			res.title = doc.smwh_title;
			res.appendix = '';
		}
		return res;
	};

})(jQuery);