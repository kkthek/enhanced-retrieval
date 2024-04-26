console.log('ER: Loading scripts/FacetedSearch/Enhancements/fs_facetValueDialog.js');

(function ($) {
    'use strict';
    window.XFS = window.XFS || {};
    XFS.Dialogs = XFS.Dialogs || {};

    var xfs = window.XFS;
    xfs.registerAdditionalFacets = function (html, handlerData, facet, property) {
        html.find('.xfsAddFacetOperation').bind('click', handlerData, function (event) {
            event.stopPropagation();
            event.preventDefault();
            openDialog(property, facet);
        });
    };

    var openDialog = function(property, facet){
        var dialog = new XFS.Dialogs.SelectFacetValueDialog();
        dialog.openDialog(property, facet, function (selectedValues, metadata){

            // remove old facets
            var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
            fsm.store.addByValue('facet', true);
            var toRemove = JSON.parse(metadata.attr('toremove'));
            for (var i = 0; i < toRemove.length; i++) {
                fsm.store.removeByValue('fq', new RegExp(toRemove[i]));
            }

            // add selected facet values
            var queries = {};
            selectedValues.each(function (i, e) {
                var property = $(e).attr('property');
                var value = $(e).val();
                queries[property] = queries[property] || [];
                queries[property].push(value);
            });
            for (var p in queries) {
                var q = queries[p].join(' OR ');
                fsm.store.addByValue('fq', q);
            }

            // add selected properties
            var selectedPropetiesArray = selectedValues.map(function (i, e) {
                return $(e).attr('propertyFacet');
            });

            for (i = 0; i < selectedPropetiesArray.length; i++) {
                fsm.store.addByValue('fq', selectedPropetiesArray[i]);
            }

            FacetedSearch.singleton.FacetedSearchInstance.addExpandedFacet(facet);
            fsm.doRequest(0);
        });
    };

    xfs.addAdditionalFacets = function (facet) {
        // check if facet should have an OR-dialog link
        var ATTRIBUTE_REGEX = /smwh_(.*)_xsdvalue_(.*)/;
        var result = ATTRIBUTE_REGEX.exec(facet);
        if (result === null || $.inArray(result[1], mw.config.get('ext.er.OREDFACETS')) === -1) {
            var PROPERTY_REGEX = /smwh_(.*)_t/;
            result = PROPERTY_REGEX.exec(facet);
            if (result === null || $.inArray(result[1], mw.config.get('ext.er.OREDFACETS')) === -1) {
                return '';
            }
        }

        return '<span style="float:right"><small><a class="xfsAddFacetOperation">(Facetten)</a></small></span>';
    };

    var Ajax = function () {
        var that = {};

        /**
         * Returns the dialog HTML
         */
        that.getDialog = function (property, callback, callbackError) {

            var data = {
                action: 'fs_dialogapi',
                method: 'getSelectFacetValueDialog',
                property: property,
                format: 'json'
            };

            $.ajax({
                type: 'GET',
                url: mw.util.wikiScript('api'),
                data: data,
                dataType: 'json',
                success: function (jsondata) {
                    callback(jsondata);

                },
                error: function (jsondata) {
                    callbackError(jsondata);
                }
            });

        };

        return that;
    };

    XFS.Dialogs.SelectFacetValueDialog = function () {

        var that = {};

        that.property = undefined;
        that.facet = undefined;
        that.onCloseCallback = undefined;

        that.initializeDialog = function () {

            that.initializeSearchFilter();
            /**
             * react to buttons
             */
            $('#fs-facet-value-dialog button.btn').on('click', function (event) {
                var action = $(event.target).attr('action');
                if (action === 'ok') {
                    if (that.onCloseCallback) {
                        that.onCloseCallback($('#fs-facet-value-dialog input:checked'), $('#fs-facet-value-dialog div.fsgFacetDialogMetadata'));
                    }
                    that.dialog.modal('hide');
                } else if (action === 'select-all') {
                    $('#fs-facet-value-dialog input:visible[type=checkbox][isLeaf=true]').prop('checked', true);
                } else if (action === 'select-none') {
                    $('#fs-facet-value-dialog input[type=checkbox][isLeaf=true]').prop('checked', false);
                }
            });

            // check already selected facets
            var fsm = FacetedSearch.singleton.FacetedSearchInstance.getAjaxSolrManager();
            var facetsOrEd = fsm.store.values('fq');

            $('#fs-facet-value-dialog input[value]').each(function (i, e) {
                var facet = $(e).val();
                for(i = 0; i < facetsOrEd.length; i++) {
                    if (facetsOrEd[i].split(' OR ').includes(facet)) {
                        $(e).prop('checked', true);
                    }
                }
            });
        };

        that.openDialog = function (property, facet, onCloseCallback) {
            that.onCloseCallback = onCloseCallback;
            that.property = property;
            that.facet = facet;

            var ajaxIndicator = new XFS.Util.AjaxIndicator();
            ajaxIndicator.setGlobalLoading(true);

            new Ajax().getDialog(property, function (jsondata) {

                ajaxIndicator.setGlobalLoading(false);
                var html = jsondata.fs_dialogapi.html;
                $('div#fs-facet-value-dialog').remove();
                $('body').append($(html));

                that.dialog = $('#fs-facet-value-dialog').modal({
                    'backdrop': 'static',
                    'keyboard': true,
                    'show': true
                });

                that.initializeDialog();

            }, function () {
                // callback on ajax-error
                ajaxIndicator.setGlobalLoading(false);
            });
        };

        that.initializeSearchFilter = function () {
            var handle;

            var showParents = function(li) {
                var nextLi = li.parent().prev();
                while(nextLi.length > 0 && nextLi.get(0).tagName === 'LI') {
                    nextLi.show();
                    nextLi = nextLi.parent().prev();
                }
            };

            var containsAll = function(valueToMatch, searchValue) {
                var parts = searchValue.split(/\s+/);
                var found = true;
                for(var i = 0; i < parts.length; i++) {
                    found = found && (valueToMatch.includes(parts[i]));
                }
                return found;
            };

            var filter = function () {
                var filterValue = $('#search-field').val();
                filterValue = filterValue.toLocaleLowerCase().trim();
                $('#fs-facet-value-dialog ul, #fs-facet-value-dialog li').show();
                $('#fs-facet-value-dialog input[filtervalue]').each(function (i, e) {
                    var valueToMatch = $(e).attr('filtervalue');
                    var li = $(e).parent();
                    if (filterValue === '' || containsAll(valueToMatch, filterValue)) {
                        li.show();
                    } else {
                        li.hide();
                    }
                });
                $('#fs-facet-value-dialog input[filtervalue]').each(function (i, e) {
                    var valueToMatch = $(e).attr('filtervalue');
                    if (filterValue === '' || containsAll(valueToMatch, filterValue)) {
                        var li = $(e).parent();
                        showParents(li);
                    }
                });
            };

            $('#search-field').keydown(function () {
                if (handle) {
                    clearTimeout(handle);
                }
                handle = setTimeout(filter, 300);
            });
        };

        return that;
    };

})(jQuery);