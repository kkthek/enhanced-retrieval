console.log("ER: Loading scripts/FacetedSearch/Enhancements/fs_facetValueDialog.js");

(function($) {

	window.XFS = window.XFS || {};
	XFS.Dialogs = XFS.Dialogs || {};
	
	var xfs = window.XFS;
	xfs.registerAdditionalFacets = function(html, handlerData, facet, property) { 
		html.find('.xfsAddFacetOperation').bind('click', handlerData, function(event) {
			
			event.stopPropagation();
			event.preventDefault();
			
			var dialog = new XFS.Dialogs.SelectFacetValueDialog();
			dialog.openDialog(property, facet, function(selectedValues) { 
				
				// create ORed facets
				var facetPropertyName = XFS.Util.getFacetName(facet);
				var facetType = XFS.Util.getFacetType(facet);
				var q = '';
				var selectedValuesArray = selectedValues.map(function(i, e) { 
					return facetPropertyName+':"'+$(e).val()+'"';
				});
				q += selectedValuesArray.toArray().join(' OR ');
				
				// create and perform SOLR request
				var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
				fsm.store.addByValue('facet', true);
				var regex = new RegExp(facetPropertyName+':.*');
				fsm.store.removeByValue('fq', regex);
				regex = new RegExp(facetType+':'+facet);
				fsm.store.removeByValue('fq', regex);
				
				if (q != '') {
					fsm.store.addByValue('fq', facetType+':'+facet);
					fsm.store.addByValue('fq', q);
				}
				FacetedSearch.singleton.FacetedSearchInstance.addExpandedFacet(facet);
				fsm.doRequest(0);
			});
		});
	};
	
	xfs.addAdditionalFacets = function(facet) {
		// check if facet should have an OR-dialog link
		var ATTRIBUTE_REGEX = /smwh_(.*)_xsdvalue_(.*)/;
		var result = ATTRIBUTE_REGEX.exec(facet);
		if (result == null || $.inArray(result[1], mw.config.get('ext.er.OREDFACETS')) == -1) {
			var PROPERTY_REGEX = /smwh_(.*)_t/;
			var result = PROPERTY_REGEX.exec(facet);
			if (result == null || $.inArray(result[1], mw.config.get('ext.er.OREDFACETS')) == -1) {
				return '';
			}
		}
		
		return '<span style="float:right"><small><a class="xfsAddFacetOperation">(Facetten)</a></small></span>';
	};
	
	var Ajax = function() {
		var that = {};

		/**
		 * Returns the dialog HTML
		 */
		that.getDialog = function(property, callback, callbackError) {

			var data = {
				action : 'fs_dialogapi',
				method : 'getSelectFacetValueDialog',
				property : property,
				format : 'json'
			};

			$.ajax({
				type : "GET",
				url : mw.util.wikiScript('api'),
				data : data,
				dataType : 'json',
				success : function(jsondata) {
					callback(jsondata);

				},
				error : function(jsondata) {
					callbackError(jsondata);
				}
			});

		};

		return that;
	};

	XFS.Dialogs.SelectFacetValueDialog = function() {

		var that = {};
		
		that.property = undefined;
		that.facet = undefined;
		that.onCloseCallback = undefined; 
			
		that.initializeDialog = function() {
			
			/**
			 * react to buttons
			 */
			$("#fs-facet-value-dialog button.btn").on("click", function(event) {
				var action = $(event.target).attr("action");
				if (action == "ok") {
					if (that.onCloseCallback) {
						that.onCloseCallback($('#fs-facet-value-dialog input:checked'));
					}
					that.dialog.modal('hide');
				} else if (action == "select-all") {
					$('#fs-facet-value-dialog input[type=checkbox]').prop("checked", true);
				} else if (action == "select-none") {
					$('#fs-facet-value-dialog input[type=checkbox]').prop("checked", false);
				}
			});
			
			// check already selected facets
			var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
			var facetPropertyName = XFS.Util.getFacetName(that.facet);
			var value_param = fsm.store.values('fq');
			var facetregexp = new RegExp(facetPropertyName+':"?([^"]+)"?', 'g');
			var values = [];
			while(result = facetregexp.exec(value_param)){
				values.push(result[1]);
			}
			for(var i = 0; i < values.length;i++) {
				$('#fs-facet-value-dialog input[value="'+values[i]+'"]').prop('checked', true);
			}
		};
		
		that.openDialog = function(property, facet, onCloseCallback) {
			that.onCloseCallback = onCloseCallback;
			that.property = property;
			that.facet = facet;
			
			var ajaxIndicator = new XFS.Util.AjaxIndicator();
			ajaxIndicator.setGlobalLoading(true);
			
			new Ajax().getDialog(property, function(jsondata) {
				
				ajaxIndicator.setGlobalLoading(false);
				var html = jsondata.fs_dialogapi.html;
				$('div#fs-facet-value-dialog').remove();
				$('body').append($(html));

				that.dialog = $('#fs-facet-value-dialog').modal({
					"backdrop" : "static",
					"keyboard" : true,
					"show" : true
				}).on('shown.bs.modal', function(e) {
					// ???
				});
				
				that.initializeDialog();

			}, function() { 
				// callback on ajax-error
				ajaxIndicator.setGlobalLoading(false);
			});
		};
		return that;
	};

})(jQuery);