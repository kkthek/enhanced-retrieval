console.log("ER: Loading scripts/FacetedSearch/Enhancements/fs_enhancements.js");

(function($) {

    window.XFS = window.XFS || {};
    var xfs = window.XFS;

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
            vals = $(cats).filter(mw.config.get('ext.er.CATEGORIES_TO_SHOW_IN_TITLE')).toArray();
        }
        
        var res = {
            title : doc.smwh_displaytitle,
            appendix : vals.join(", "),
        }
        console.log("ER: fs_enhancements/getPageTitle() " + doc.smwh_displaytitle);
        return res;
    };

})(jQuery);