/*
 * Copyright (C) Vulcan Inc., DIQA Projektmanagement GmbH
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * @file
 * @ingroup FacetedSearchScripts
 * @author: Thomas Schweitzer
 * @author: Kai Kühn
 */
console.log("ER: Loading scripts/FacetedSearch/FS_Theme.js");

/**
 * This file defines the theme i.e. how certain elements are represented as HTML.
 */
(function ($) {

    // Global FS extension object
    window.XFS = window.XFS || {};

    var FS_CATEGORIES = 'smwh_categories';
    var FS_DIRECTCATEGORIES = 'smwh_directcategories';
    var FS_ATTRIBUTES = 'smwh_attributes';
    var FS_PROPERTIES = 'smwh_properties'; // relations
    var MOD_ATT = 'smwh__MDAT_xsdvalue_dt';
    var CAT_MAX = 4;
    var CAT_SEP = ' | ';
    var RELATION_REGEX = /^smwh_(.*)_(.*)$/;
    var ATTRIBUTE_REGEX = /smwh_(.*)_xsdvalue_(.*)/;
    var DATETIME_REGEX = /smwh_(.*)_xsdvalue_dt/;

    var IMAGE_PATH = '/extensions/EnhancedRetrieval/skin/images/';
    var NS_ICON = {
        // TODO add missing mappings
        0 : ['Wiki', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_instances_icon_16x16.png'],
        6 : ['File', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_image_icon_16x16.png'],
        10 : ['Template', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_template_icon_16x16.png'],
        14: ['Kategorie', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_category_icon_16x16.png'],
        102 : ['Property', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_property_icon_16x16.png'],
        120 : ['Document', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_document_icon_16x16.png'],
        122 : ['Audio', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_music_icon_16x16.png'],
        124 : ['Video', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_video_icon_16x16.png'],
        126 : ['Pdf', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_pdf_icon_16x16.png'],
        128 : ['ICalendar', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_ical_icon_16x16.gif'],
        130 : ['VCard', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_vcard_icon_16x16.gif'],
        700 : ['Comment', mw.config.get('wgScriptPath') + IMAGE_PATH + 'datawiki_comment_icon_16x16.png']
    };

    var REMOVE_ICON = mw.config.get('wgScriptPath') + IMAGE_PATH + 'delete.png';

    var NS_CAT_ID = 14;
    var NS_PROP_ID = 102;

    /**
     * Removes all underscores.
     */
    function noUnderscore(string) {
        return string.replace(/_/g, ' ');
    }

    /**
     * Gets icon-URL for a specific namespace ID.
     */
    function getIconForNSID(id) {
        var iconData = NS_ICON[id];
        if (iconData === undefined) {
            return '<!-- unknown namespace ID: ' + id + ' -->';
        }
        return '<img src="' + iconData[1] + '" title="' + iconData[0] + '"/>';
    }

    /**
     * Constructs a relative URL from namespace and page name.
     */
    function getLink(namespaceId, page) {
        var ns = mw.config.get('wgFormattedNamespaces')[String(namespaceId)];
        if (!ns) {
            ns = "";
        }
        if (ns.length > 0) {
            ns = noUnderscore(ns) + ':';
        }
        return mw.config.get('wgArticlePath').replace('$1', ns + page);
    }

    /**
     * Attributes and relations that are delivered as facets always have a prefix
     * and a suffix that indicates the type. This function retrieves the original
     * name of an attribute or relation.
     * @param {String} property
     *         The decorated name of an attribute or property.
     * @return {String}
     *         The plain name of the property.
     */
    function extractPlainName(property) {
        // Try attribute
        var plainName = property.match(ATTRIBUTE_REGEX);
        if (plainName) {
            return noUnderscore(plainName[1]);
        }
        // Try relation
        plainName = property.match(RELATION_REGEX);
        if (plainName) {
            return noUnderscore(plainName[1]);
        }
        // Neither attribute nor relation => return the given name
        return noUnderscore(property);
    }

    /**
     * A text that is displayed in the UI may contain HTML or script code which
     * may enable cross-site-scripting. This function escapes special HTML
     * characters.
     * Find further information at:
     * https://www.owasp.org/index.php/XSS_%28Cross_Site_Scripting%29_Prevention_Cheat_Sheet
     *
     * @param {string} text
     *         The string to be escaped.
     * @return {string}
     *         The escaped string
     */
    function escapeHTML(text) {
        var escText = text.replace(/&/g, '&amp;')
                          .replace(/</g, '&lt;')
                          .replace(/>/g, '&gt;')
                          .replace(/"/g, '&quot;')
                          .replace(/'/g, '&#x27;')
                          .replace(/\//g, '&#x2F;');
        return escText;
    }

    /**
     * Generates an HTML-ID for a facet. The HTML-IDs are used as IDs for HTML
     * elements and in jQuery selectors. Some characters like / or % are not valid
     * for use in jQuery selectors. So all characters in the facet are converted
     * to a hexadecimal string.
     *
     * @param {String} facet
     *         Name of the facet
     * @return {String}
     *         ID for the given facet.
     */
    function facet2HTMLID(facet) {
        var f = "";
        for (var i = 0, l = facet.length; i < l; ++i) {
            f += facet.charCodeAt(i).toString(16);
        }
        return f;
    }

    /**
     * This is the counterpart of FS_SolrSMWDB.encodeTitle()
     * Turns a SOLR field name into a readable Wiki title.
     *
     * @param {String} title url-encoded and % replaced with _0x
     * @returns {String}     decoded, readable name
     */
    function decodeTitle(title) {
        var tmp = replaceAll(title, " ", "_");
        tmp = replaceAll(tmp, "_0x", "\%");
        tmp = replaceAll(tmp, "__", "_");
        tmp = decodeURIComponent(tmp);
        return replaceAll(tmp, "_", " ");
    }

    /**
     * Finds all occurrances of pattern in text and replaces each with replacement.
     *
     * This function is only needed for compatibility with InternetExplorer.
     * All other browsers can do text.replaceAll(pattern, replacement)
     *
     * @param {String} text inputString
     * @param {String} pattern the substrang that should be replaced
     * @param {String} replacement
     * @returns {String} the altered text
     */
    function replaceAll(text, pattern, replacement) {
        try {
            return text.replaceAll(pattern, replacement);
        } catch (e) {
            return replaceAllTheHardWay(text, pattern, replacement);
        }
    }

    /**
     * Manual implementation of replaceAll.
     * This function is only needed for compatibility with InternetExplorer.
     * All other browsers can do text.replaceAll(pattern, replacement)
     *
     * @param {String} text inputString
     * @param {String} pattern the substrang that should be replaced
     * @param {String} replacement
     * @returns {String} the altered text
     */
    function replaceAllTheHardWay(text, pattern, replacement) {
        var j = text.indexOf(pattern);
        if (j < 0) {
            return text;
        }

        var patternLength = pattern.length;
        var patternLength1 = Math.max(patternLength, 1);
        var textLength = text.length;

        var newLengthHint = textLength - patternLength + replacement.length;
        if (newLengthHint < 0) {
            return text;
        }
        var result = '';
        var i = 0;
        do {
            result = result + text.substring(i, j) + replacement;
            i = j + patternLength;
        } while (j < textLength && (j = text.indexOf(pattern, j + patternLength1)) > 0);

        result = result + text.substring(i, textLength);
        // console.log("ER: FS_Theme: replaceAllTheHardWay ( " + text + " , " + pattern + " , " + replacement + " ) = '" + result + "'");
        return result;
    }

    /**
     * Some strings are too long for displaying them in the UI. This function
     * shortens them and appends an ellipsis (...) .
     * @param {String} longName
     *         The name that is shortened
     * @return {String/bool} short name
     *         The short name. If the longName is already short enough the boolean
     *         "false" is returned.
     */
    function makeShortName(longName, width, className) {
        // Fast version that does not consider the actual rendered width of the text
        //        var maxLength = 25;
        //        if (longName.length > maxLength) {
        //            return longName.substr(0, maxLength-3) + '&hellip;';
        //        }
        //        return false;

        var className = className ? ' class="' + className + '" ' : "";
        var tempItem = '<span id="textWidthTempItem" ' + className + 'style="display:none;">'+ longName +'</span>';
        $(tempItem).appendTo('body');
        tempItem = $('#textWidthTempItem');
        var itemWidth = tempItem.width();
        var shortName = longName;

        if (itemWidth < width){
            tempItem.remove();
            return false;
        }

        var minLen = 0;
        var maxLen = shortName.length;
        while (maxLen - minLen > 1 && Math.abs(itemWidth - width) > 5) {
            var currLen = (maxLen - minLen) / 2 + minLen;
            shortName = longName.substr(0, currLen);
            tempItem[0].innerHTML = shortName + '&hellip;';
            itemWidth = tempItem.width();
//            itemWidth = tempItem.html(shortName + '&hellip;').width();
            if (itemWidth > width) {
                // Reduce the upper bound
                maxLen = currLen;
            } else {
                // Increase the lower bound
                minLen = currLen;
            }
        }
        tempItem.remove();
        return shortName + '&hellip;';
    }

    /**
     * Checks if the given name is a name for an attribute or relation.
     *
     * @param {string} name
     *         The name to examine
     * @return {bool}
     *         true, if name is a property name
     */
    function isProperty(name) {
        return name.match(ATTRIBUTE_REGEX)|| name.match(RELATION_REGEX);
    }

    /**
     * Checks if the given name is a name for a relation.
     *
     * @param {string} name
     *         The name to examine
     * @return {bool}
     *         true, if name is a relation name
     */
    function isRelation(name) {
        return name.match(RELATION_REGEX) && !name.match(ATTRIBUTE_REGEX);
    }

    /**
     * Checks if the given name is a name for a Datetime property.
     *
     * @param {string} name
     *         The name to examine
     * @return {bool}
     *         true, if name is a Datetime property name
     */
    function isDateTime(name) {
        return name.match(DATETIME_REGEX);
    }

    /**
     * Retrieves display name by formatting plainNames according to property type.
     *
     * @param array/string plainNames
     * @param string property SOLR property
     * @param boolean returnLinks true if HTML links should be returned, otherwise plain text
     *
     * @return array Display names (can contain HTML)
     */
    function retrieveDisplayName(plainNames, property, returnLinks) {
        var vals = [];

        // make sure plainNames is always an array
        if (!(plainNames instanceof Array)) {
            plainNames = [plainNames];
        }

        if (property == null) {
            // if no property given, just extract the nicename
            $.each(plainNames, function() {
                var nicename = window.XFS.translateName ? window.XFS.translateName(this) : this;
                nicename = nicename.split('|').length > 1 ? nicename.split('|')[1] : nicename;
                vals.push(noUnderscore(nicename));
            });

        } else if (isRelation(property)) {
            // Relation values are rendered as link
            $.each(plainNames, function() {
                var nicename = window.XFS.translateName ? window.XFS.translateName(this) : this;
                nicename = nicename.split('|').length > 1 ? nicename.split('|')[1] : nicename;
                var link = this.split('|').length > 1 ? this.split('|')[0] : this;

                if (returnLinks) {
                    vals.push('<a href="' + getLink(0, link) + '">' + noUnderscore(nicename) + '</a>');
                } else {
                    vals.push(noUnderscore(nicename));
                }
            });

        } else if (isDateTime(property)) {
            // DateTime properties are rendered with proper locale
            $.each(plainNames, function() {
               var date = new Date(this);
               vals.push(date.toLocaleString());
            });

        } else {
            // Attribute values are rendered as normal text
            $.each(plainNames, function() {
                var nicename = window.XFS.translateName ? window.XFS.translateName(this.toString()) : this.toString();
                var valueStr = this.toString();
                nicename = valueStr.split('|').length > 1 ? valueStr.split('|')[1] : nicename;
                vals.push(noUnderscore(nicename));
            });
        }

        return vals;
    }

    /**
     * Checks if one of the categories appear in doc
     */
    function isInCategory(doc, categories) {
        if (!doc.smwh_categories) return false;

        for(var i = 0; i < categories.length; i++) {
            if ($.inArray(categories[i], doc.smwh_categories) != -1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns CSS class for files that have an extension defined
     * in $fsgShowFileInOverlay resp. mw.config.get('ext.er.SHOW_FILE_IN_OVERLAY')
     */
    function cssClassForFileType(doc) {
        if (doc.smwh_namespace_id != 6) {
            return '';
        }
        var fileParts = doc.smwh_title.split('.');
        var fileExtension = fileParts[fileParts.length-1].toLowerCase();
        if (mw.config.get('ext.er.SHOW_FILE_IN_OVERLAY').indexOf(fileExtension) > -1) {
            return ' imageOverlay';
        }
        return '';
    }

    /**
     * Returns CSS class result pages that should be promoted or demoted in the search UI
     */
    function cssClassForPromotionOrDemotion(doc) {
        css = '';
        var promotion = mw.config.get('ext.er.PROMOTION_PROPERTY');
        if(promotion && doc[promotion] == 'true') {
            css = ' xfsPromotedResult';
        }

        var demotion = mw.config.get('ext.er.DEMOTION_PROPERTY');
        if(demotion && doc[demotion] == 'true') {
            css = css + ' xfsDemotedResult';
        }
        return css;
    }

    /**
     * Adds annotations directly displayed in the snippets without
     * opening the 'properties'-view
     */
    AjaxSolr.theme.prototype.annotationsInSnippet = function (doc, snippets) {
          var output = '';
          output += '<div><div class="xfs_additional_property_table">';

          var atLeastOne = false;
          for (var s in snippets) {
              if (doc[s]) {
                  var category = snippets[s]['category'];
                  if (!isInCategory(doc, category)) {
                      continue;
                  }
                  output += '<div class="xfs_additional_property_table_row">';
                  output += '<span class="xfs_additional_property_label">' + snippets[s]['label'] +':&nbsp;</span>';
                  output += '<span class="xfs_additional_property_value">' + retrieveDisplayName(doc[s], s, true) + '</span>';
                  output += '<span class="xfs_additional_property_separator">&nbsp;|&nbsp;</span>';
                  output += '</div>';
                  atLeastOne = true;
              }
          }

          output += '</div></div>';
          return atLeastOne ? output : '';
    };

    /**
     * Generates an HTML ID for a property value facet with the name {facet}.
     *
     * @param {String} facet
     *         Name of the facet
     * @return {String}
     *         HTML ID for the given facet.
     */
    AjaxSolr.theme.prototype.getPropertyValueHTMLID = function (facet) {
        return 'property_' + facet2HTMLID(facet) + '_value';
    };

    /**
     * Theme for article titles and their semantic data with highlighting.
     *
     * @param doc
     *         The article given as SOLR document
     * @param data
     *         HTML representation of the semantic data
     * @param highlight
     *         HTML representation of the highlighed text
     */
    AjaxSolr.theme.prototype.article = function (doc, data, highlight, showDataHandler) {
        var link = getLink(doc.smwh_namespace_id, doc.smwh_title);
        if (doc.smwh_diqa_import_fullpath_xsdvalue_t) {
            link = doc.smwh_diqa_import_fullpath_xsdvalue_t;
        }

        var promoteOrDemoteResult = cssClassForPromotionOrDemotion(doc);
        var output = '<div class="xfsResult' + promoteOrDemoteResult +'">';

        // page title with link
        var cssClass = cssClassForFileType(doc);
        output += '<a class="xfsResultTitle' + promoteOrDemoteResult + cssClass + '" href="' + link + '">';
        if (window.XFS.getPageTitle) {
           var titleObj = window.XFS.getPageTitle(doc);
           output += noUnderscore(titleObj.title) + '</a> -- ' + titleObj.appendix;
        } else {
              output += noUnderscore(doc.smwh_displaytitle) + '</a>';
        }

        output += getIconForNSID(doc.smwh_namespace_id);
        // output += '<p id="links_' + doc.id + '" class="links"></p>';

        output += '<div class="xfsHighlightSearch">' + highlight + '</div>';

        output += '<div>' + data + '</div>';

        // Add the modification date
        if (doc[MOD_ATT]) {
            output +=
                '<div class="xfsResultModified">' +
                    '<p>'+ mw.msg('fs_lastChange') +': ' +
                        String(doc[MOD_ATT])
                            .replace('T', ' ')
                            .substring(0, 16) +
                    '</p>' +
                '</div>';
        }

        // close xfsResult-DIV
        output += '</div>';

        output = $(output);
        output.find('.xfsShow').data('documentData', doc).click(showDataHandler);
        if (window.XFS.registerAdditionalActions) {
              window.XFS.registerAdditionalActions(output, doc);
        }
        return output;
    };


    /**
     * Theme for rendering a highlighted text.
     *
     * @param highlight
     *         The highlighted text
     */
    AjaxSolr.theme.prototype.highlight = function (highlight) {
        return '&hellip;' + highlight + '&hellip;';
    };

    /**
     * Theme for the semantic data of an article. Only categories are displayed
     * to the full extent. If there are properties for the article, a link to
     * show them is displayed.
     *
     * @param doc
     *         The article given as SOLR document
     */
    AjaxSolr.theme.prototype.data = function (doc) {
        var output = '';
        var attr  = doc[FS_ATTRIBUTES] || [];
        var props = doc[FS_PROPERTIES] || [];
        var cats  = doc[FS_DIRECTCATEGORIES] || [];

        if (typeof cats !== 'undefined' && cats.length > 0) {
            // Show CAT_MAX categories
            output +=
                '<div class="xfsResultCategory">' +
                '<p>'+ mw.msg('fs_inCategory') +': ';
            var count = Math.min(cats.length, CAT_MAX);
            var vals = [];
            for ( var i = 0; i < count; i++) {
                vals.push('<a href="' + getLink(NS_CAT_ID, cats[i]) + '">'
                          + noUnderscore(cats[i])
                          + '</a>');
            }
            output += vals.join(CAT_SEP);
            if (count < cats.length) {
                vals = [];
                for (var i=count; i<cats.length; i++) {
                    vals.push('<a href="' + getLink(NS_CAT_ID, cats[i]) + '">'
                              + noUnderscore(cats[i])
                              + '</a>');
                }
                output += CAT_SEP;
                output += '<span class="xfsToggle" style="display: none">' + vals.join(CAT_SEP) + '</span>';
                output += ' (<a class="xfsMore">'+mw.msg('fs_more')+'</a>)';
            }
            output += '</p></div>';
        }

        if (props.length + attr.length > 0) {

          if (window.XFS.addAddAdditionalData) {
                output += window.XFS.addAddAdditionalData(doc);
          }

          if (mw.config.get('ext.er.annotationsInSnippet')) {
              output += AjaxSolr.theme.prototype.annotationsInSnippet(doc, mw.config.get('ext.er.annotationsInSnippet'));
          }

          if (mw.config.get('ext.er.showArticleProperties')) {

            if(mw.config.get('ext.er.showSolrScore') && doc['score']) {
                var scoreAttribute = 'title="SOLR score=' + doc['score'] + '"';
            } else {
                var scoreAttribute = '';
            }

            // Properties or attributes are present
            // => add a table header
            output +=
                    '<div>' +
                        '(<a class="xfsShow"' + scoreAttribute + '>' +
                            mw.msg('fs_show') +
                        '</a>)' +
                        '<div class="xfsResultTable"></div>' +
                    '</div>';
          }

        }

        if (window.XFS.addAdditionalActions) {
            var additionalActionsHTML = window.XFS.addAdditionalActions(output, doc);
            output += additionalActionsHTML;
        }
        return output;
    };

    /**
     * Theme for the semantic properties of an article.
     *
     * @param doc
     *         The article given as SOLR document
     */
    AjaxSolr.theme.prototype.articleProperties = function(doc) {
        var output = '<table>';
        var row = 0;

        // Show all relations and attributes in a table
        for (var property in doc) {
            // Get the property name without prefix, suffix and type
            var plainName = extractPlainName(property);
            output += '<tr class="s' + (row % 2) + '">';
            row += 1;

            // check if it is a pre-defined property
            var key = plainName.replace(/\s/, '_');
            if (mw.messages[key]) {
                output += '<td>' + mw.messages[key] + '</td>';
            } else {
                plainName = decodeTitle(plainName);
                output += '<td>' + plainName + '</td>';
            }

            var vals = retrieveDisplayName(doc[property], property, true);
            output += '<td>' + vals.join(', ') + '</td>';
            output += '</tr>';
        }

        output += '</table>';
        return output;
    };

    /**
     * This function generates the HTML for a namespace facet. The namespace is
     * given as the namespace number. The namespace name is retrieved and returned
     * as HTML.
     *
     * @param {string} facet
     *         Name of the facet
     * @param {int} count
     *         Number of documents that match the facet
     * @param {Function} handler
     *         Click handler for the facet.
     * @param {Function} showPropertyDetailsHandler
     *         This function is called when the details of a property are to be
     *         shown.
     *
     */
    AjaxSolr.theme.prototype.namespaceFacet = function(facet, count, handler, showPropertyDetailsHandler, isRemove){
        var name = facet === 'all' ?
                    mw.msg('fs_allNamespaces') :
                    mw.config.get('wgFormattedNamespaces')[facet];
        if (name === '') {
            // Main namespace
            name = mw.msg('fs_mainNamespace');
        }

        if (typeof name === 'undefined') {
            // The namespace may be undefined if the extension that defines it
            // was disabled after the creation of the index.
            return '';
        }
        var tooltip = 'title="' + mw.msg('fs_namespaceTooltip', count) + '" ';
        name = name.replace(/ /g, '&nbsp;');
        var emptyNamespace = count === 0 ? " xfsEmptyNamespace" : '';
        html = $('<span namespace="' + facet + '" class="xfsNamespace' + emptyNamespace + '"/>')
                .append('&nbsp;')
                .append($('<span ' + tooltip + '>' + name + '</span>'))
                .append(' ');
        html.find('span').click(handler);
        return html;
    };

    /**
     * This function generates the HTML for a facet which may be a category or
     * a property. Properties have details e.g. clusters of values or lists of
     * values.
     *
     * @param {string} facet
     *         Name of the facet
     * @param {int} count
     *         Number of documents that match the facet
     * @param {Function} handler
     *         Click handler for the facet.
     * @param {Object} handlerData
     *         This object is passed to the handler function when it is called.
     * @param {Function} showPropertyDetailsHandler
     *         This function is called when the details of a property are to be
     *         shown.
     * @param {bool} isRemove
     *         If {true}, this facet can only be removed. The icon for removing the
     *         facet is added. Otherwise the facet is rendered as link.
     *
     */
    AjaxSolr.theme.prototype.facet = function(facet, count, handler, handlerData,
                                                showPropertyDetailsHandler, isRemove) {
        var html;
        var plainName = extractPlainName(facet);

        // check if it is a pre-defined property
        var key = plainName.replace(/\s{2}/g, '_');

        if (mw.messages[key]) {
            plainName = mw.messages[key];
        } else {
            plainName = decodeTitle(plainName);
        }

        var maxWidth = $('.facets').width() * 0.7;
        var shortName = makeShortName(plainName, maxWidth);
        var tooltip = shortName === false ? '' : ' title="' + plainName + '" ';

        var cssClass = isProperty(facet) ? 'fs_propertyFacet' : 'fs_categoryFacet';
        var nicename = retrieveDisplayName(plainName, handlerData ? handlerData.field : null, false);

        if (isRemove) {
            html = '<span' + tooltip + ' class="'+cssClass+'">' + nicename + '</span>' +
                   '<img class="xfsRemoveFacet ' + cssClass + '" src="' + REMOVE_ICON +'" title="' + mw.msg('fs_removeFilter') + '"/>' +
                   (isProperty(facet) && window.XFS.addAdditionalFacets ? window.XFS.addAdditionalFacets(facet) : '');

        } else {
            html = '<a href="#"' + tooltip + ' class="addFacet">' + nicename + '</a> ' +
                   '<span class="xfsMinor">(' + count + ')</span>' +
                   (isProperty(facet) && window.XFS.addAdditionalFacets ? window.XFS.addAdditionalFacets(facet) : '');
        }

        var path = mw.config.get('wgScriptPath') + IMAGE_PATH;
        if (isProperty(facet)) {
            var facetsExpanded = FacetedSearch.singleton.FacetedSearchInstance.isExpandedFacet(facet);
            var img1Visible = facetsExpanded ? ' style="display:none" ' : '';
            var img2Visible = facetsExpanded ? '' : ' style="display:none" ';
            var divID = AjaxSolr.theme.prototype.getPropertyValueHTMLID(facet);
            var img1ID = 'show_details' + divID;
            var img2ID = 'hide_details' + divID;

            var toggleFunc = function () {
                if ($('#' + divID).is(':visible')) {
                    $('#' + divID).hide();
                    FacetedSearch.singleton.FacetedSearchInstance
                        .removeExpandedFacet(facet);
                } else {
                    $('#' + divID).show();
                    FacetedSearch.singleton.FacetedSearchInstance
                        .addExpandedFacet(facet);
                    showPropertyDetailsHandler(facet);
                }
                $('#' + img1ID).toggle();
                $('#' + img2ID).toggle();
            };

            var img1 =
                '<img src="'+ path + 'right.png" ' +
                    'title="'+ mw.msg('fs_showDetails') +
                    '" id="'+img1ID+'"'+img1Visible+' class="detailsImage fs_propertyFacet"/>';
            var img2 =
                '<img src="'+ path + 'down.png" ' +
                    'title="'+ mw.msg('fs_hideDetails') +
                    '" id="'+img2ID+'"'+img2Visible+'" class="detailsImage fs_propertyFacet"/>';
            html = img1 + img2 + html;
            html += '<div id="' + divID +'"'+ img2Visible + '></div>';
        } else {
            var img = '<img src="' + path + 'item.png" class="fs_categoryFacet">';
            html = img + html;
        }

        html = $('<div class="' + cssClass + '">' + html + '</div>' + '<div style="clear: both;"></div>');

        // Attach the event handlers
        html.find('.addFacet').bind('click', handlerData, handler);
        html.find('.xfsRemoveFacet').bind('click', handlerData, handler);

        if (isProperty(facet) && window.XFS.registerAdditionalFacets) {
            window.XFS.registerAdditionalFacets(html, handlerData, facet, key);
            html.find('.detailsImage').click(toggleFunc);
        }
        return html;
    };

    AjaxSolr.theme.prototype.propertyValueFacet = function(facet, count, handler, handlerData, showPropertyDetailsHandler, isRemove){
        var html = AjaxSolr.theme('facet', facet, count, handler, handlerData, showPropertyDetailsHandler, isRemove);
        html = $('<div class="xfsClusterEntry" />').append(html);
        return html;
    };

    AjaxSolr.theme.prototype.facet_link = function(value, handler) {
        return $('<a href="#"/>'+ value + '</a>').click(handler);
    };

    AjaxSolr.theme.prototype.moreLessLink = function(moreHandler, lessHandler) {
        var $ = jQuery;

        var html =
            '<div>' +
                '<a class="xfsFMore">' +
                    mw.msg('fs_more') +
                '</a>' +
                '<span style="display: none">' +
                ' &#124; ' +
                '</span>' +
                '<a class="xfsFLess" style="display: none">' +
                    mw.msg('fs_less') +
                '</a>' +
            '</div>';
        html = $(html);
        html.find('.xfsFMore').click(moreHandler);
        html.find('.xfsFLess').click(lessHandler);
        return html;
    };

    AjaxSolr.theme.prototype.no_items_found = function() {
        return 'no items found in current selection';
    };

    AjaxSolr.theme.prototype.no_items_found_with_facetvalue = function(facet) {
        return $('<div>').addClass('xfsClusterEntry').html(mw.msg('fs_noFacetsFound'));
    };

    AjaxSolr.theme.prototype.no_facet_filter_set = function() {
        return $('<div class="xfsMinor">').text(mw.msg('fs_noFacetFilter'));
    };

    AjaxSolr.theme.prototype.underspecified_search = function() {
        return $('<div class="xfsErrorMessage">')
                .text(mw.msg('fs_underspecifiedSearch'));
    };

    AjaxSolr.theme.prototype.session_lost = function() {
        return $('<div class="xfsErrorMessage">')
            .text(mw.msg('fs_sessionLost'));
    };

    AjaxSolr.theme.prototype.remove_all_filters = function(handler) {
        return $('<a href="#"/>')
                .text(mw.msg('fs_removeAllFilters'))
                .click(handler);
    };

    AjaxSolr.theme.prototype.emptyQuery = function(handler) {
        return mw.msg('fs_addFacetOrQuery');
    };

    AjaxSolr.theme.prototype.createArticle = function(articleName, link) {
        link = escapeHTML(link);
        articleName = escapeHTML(articleName);
        var html = mw.msg('fs_nonexArticle', '<em>'+articleName+'</em>') +
                    ' <a href="' + link + '" class="xfsRedLink">' +
                        articleName +
                    '</a>';
        return html;
    };

    AjaxSolr.theme.prototype.filter_debug = function(filters) {
        var list = $('<ul id="xfsFilterDebug">');
        $.each(filters, function(index, value) {
            $(list).append($('<li>').text(value));
        });
        return list;
    };

    AjaxSolr.theme.prototype.currentSearch = function(link) {
        link = escapeHTML(link);
        var html = ' <a href="' + link + '" title="' +
                        mw.msg('fs_searchLinkTT') + '">' +
                        mw.msg('fs_searchLink') +
                    '</a>';
        return html;
    };

    /**
     * Creates the HTML for a cluster of values of an attribute. A cluster is
     * a range of values and the number of elements within this range e.g.
     * 10 - 30 (5).
     *
     * @param {double} from
     *         Start value of the range
     * @param {double} to
     *         End value of the range
     * @param {int} count
     *         Number of elements in this range
     * @param {function} handler
     *         This function is called when the cluster is clicked.
     * @param {bool} isClusterTitle
     *         If true, this range is displayed as the cluster title. It shows the
     *         absolute borders of all clusters it contains.
     * @param {bool} isRangeRestricted
     *         If true, there is a range restriction on the facet. The icon for
     *         deleting the range is displayed.
     * @param {bool} isLastRange
     *         If true, this is the last range. It will not be displayed as link.
     *
     */
    AjaxSolr.theme.prototype.cluster = function(from, to, count, handler,
                                            isClusterTitle, isRangeRestricted,
                                            isLastRange) {
        var html;

        var range = from === to
                        ? from
                        : from + ' - ' + to;

        if (isClusterTitle) {
            var removeIcon = isRangeRestricted
                ? '<img class="xfsRemoveRange" src="' + REMOVE_ICON +'" ' +
                        'title="'+ mw.msg('fs_removeRestriction') +'"/>'
                : '';
            html =
                $('<div>' +
                        '<span class="xfsClusterTitle">' +
                            range + ' (' + count + ')' +
                            removeIcon +
                        '</span>' +
                    '</div>');
            if (removeIcon) {
                html.find('img').click(handler);
            }
        } else {
             if (isLastRange) {
                 html =
                    '<div>' +
                        '<span class="xfsClusterEntry">' +
                            range + ' (' + count + ')' +
                        '</span>' +
                    '</div>';
             } else {
                html =
                    $('<div>' +
                        '<a href="#" class="xfsClusterEntry">' +
                            range + ' (' + count + ')' +
                          '</a>' +
                      '</div>')
                    .click(handler);
             }
        }
        return html;
    };

})(jQuery);

