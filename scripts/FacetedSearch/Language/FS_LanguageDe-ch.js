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
 */

if (typeof window.FacetedSearch == "undefined") {
// Define the FacetedSearch  module	
	window.FacetedSearch  = { 
		classes : {}
	};
}

/**
 * @class FSLanguageDe
 * This class contains the german language string for the faceted search UI
 * 
 */
FacetedSearch.classes.FSLanguageDe = function () {
	
	// The instance of this object
	var that = FacetedSearch.classes.FSLanguage();
	
	that.mMessages = {
'solrNotFound'		: 'Es konnte noch keine Verbindung zum SOLR Server hergestellt werden. ' +
					  'Die facettierte Suche wird noch nicht funktionieren. '+
					  'Der SOLR Server wird hier gesucht: ' + mw.config.get('wgFSSolrURL')  + mw.config.get('wgFSSolrServlet') + '. ' +
					  'Möglicherweise blockiert die lokale Firewall den Port des SOLR Servers.',
'tryConnectSOLR'	: 'Verbindung mit der Suchmaschine wird aufgebaut ...',
'more' 				: 'mehr',
'less' 				: 'weniger',
'noFacetFilter'		: '(Keine Facetten ausgewählt.)',
'underspecifiedSearch' : 'Ihre aktuelle Suche hat zu viele Treffer. Bitte verfeinern Sie sie!',
'removeFilter'		: 'Diese Facette enfernen',
'removeRestriction'	: 'Einschränkung entfernen',
'removeAllFilters'	: 'Alle Facetten entfernen',
'pagerPrevious'		: '&lt; Vorherige',
'pagerNext'			: 'Nächste &gt;',
'results'			: 'Zeige Ergebnisse',
'to'				: 'bis',
'of'				: 'von',
'ofapprox'			: 'von ungefähr',
'inCategory'		: 'ist in Kategorie',
'show'				: 'Eigenschaften zeigen',
'hide'				: 'Eigenschaften ausblenden',
'showDetails'		: 'Zeige Details',
'hideDetails'		: 'Details ausblenden',
'lastChange'		: 'Letzte Änderung',
'addFacetOrQuery'	: 'Bitte geben Sie einen Suchbegriff ein oder wählen Sie eine Facette aus!',
'mainNamespace'		: 'Main',
'namespaceTooltip'  : '$1 Artikel in diesem Namensraum passen zur Auswahl',
'allNamespaces'		: 'Alle Namensräume',
'nonexArticle'		: 'Der Artikel existiert nicht. Klicken Sie hier, um ihn zu erstellen:',
'searchLink' 		: 'Link zur Suche',
'searchLinkTT'		: 'Rechts klicken zum Kopieren oder Lesezeichen setzen.',

                '_TYPE' : 'Datentyp',
		'_URI'  : 'Gleichwertige URI',
		'_SUBP' : 'Unterattribut von',
		'_SUBC' : 'Unterkategorie von',
		'_UNIT' : 'Einheiten',
		'_IMPO' : 'Importiert aus',
		'_CONV' : 'Entspricht',
		'_SERV' : 'Bietet Service',
		'_PVAL' : 'Erlaubt Wert',
		'_MDAT' : 'Zuletzt geändert',
		'_CDAT' : 'Erstellt',
		'_NEWP' : 'Ist eine neue Seite',
		'_LEDT' : 'Letzter Bearbeiter ist',
		'_ERRP' : 'Hat unpassenden Wert für',
		'_LIST' : 'Hat Komponenten',
		'_SOBJ' : 'Hat Unterobjekt',
		'_ASK'  : 'Hat Abfrage',
		'_ASKST': 'Abfragetext',
		'_ASKFO': 'Abfrageformat',
		'_ASKSI': 'Abfragegröße',
		'_ASKDE': 'Abfragetiefe'
 	};
	
	return that;
	
}

jQuery(document).ready(function() {
	if (!FacetedSearch.singleton) {
		FacetedSearch.singleton = {};
	}
	FacetedSearch.singleton.Language = FacetedSearch.classes.FSLanguageDe();
});
