<?php


/*
 * Copyright (C) Vulcan Inc., DIQA-Projektmanagement GmbH
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
 * @ingroup FS_Language
 *
 * Internationalization file for the Faceted Search module.
 *
 */

$messages = array();

/**
 * English
 */
$messages['en'] = array(
	/* general/maintenance messages */
	'facetedsearch'           => 'FacetedSearch',
	'facetedsearch-desc'      => 'EnhancedRetrieval provides faceted search for MediaWiki and SMW. It requires [https://www.diqa.de/de/Power-Search_for_MediaWiki DIQA powersearch] as backend.',
	'fs_facetedsearchspecial' => 'FacetedSearch',  // Name of the special page for Faceted Search
	'fs_specialpage_group'    => 'Faceted Search',

	//--- Messages for the special page ---
	'fs_title'            => 'Faceted Search',
	'fs_search'           => 'Find',
	'fs_categories'       => 'Categories',
	'fs_properties'       => 'Properties',
	'fs_namespaces'       => 'Namespaces',
	'fs_search_results'   => 'Search results',
	'fs_selected'         => 'Selected facets',
	'fs_available_facets' => 'Available facets',
	'fs_relevance'        => 'Relevance',
	'fs_newest_date_first'=> 'Latest article first',
	'fs_oldest_date_first'=> 'Oldest article first',
	'fs_title_ascending'  => 'Title ascending',
	'fs_title_descending' => 'Title descending',
	'fs_sort_by'          => 'Sort by',

	'fs_prop_ignoreasfacet' => 'Ignore as facet',

	/* Javascript messages */
	'solrConnectionError' => 'CONNECTION ERROR. Please check browser log.',
	'solrNotFound'		=> 'Could not connect to SOLR Server yet. Faceted Search will not work yet. Expecting to find SOLR Server at $1. '.
							'Possibly your firewall is blocking the SOLR port.'.
							'Please make sure that proxy/env.php is configured. You\'ll find an example at proxy/env-sample.php',
	'tryConnectSOLR'	=> 'Trying to connect to the search engine...',
	'more' 				=> 'more',
	'less' 				=> 'less',
	'noFacetFilter'		=> '(no facets selected)',
	'underspecifiedSearch' => 'Your current search may match too many results. Please refine it!',
	'session_lost'      => 'Mediawiki session got lost. Please re-login.',
	'removeFilter'		=> 'Remove this facet',
	'removeRestriction'	=> 'Remove restriction',
	'removeAllFilters'	=> 'Remove all facets',
	'pagerPrevious'		=> '&lt; Previous',
	'pagerNext'			=> 'Next &gt;',
	'results'			=> 'Results',
	'to'				=> 'to',
	'of'				=> 'of',
	'ofapprox'			=> 'of approx.',
	'inCategory'		=> 'is in category',
	'show'				=> 'Show properties',
	'hide'				=> 'Hide properties',
	'showDetails'		=> 'Show details',
	'hideDetails'		=> 'Hide details',
	'lastChange'		=> 'Last change',
	'addFacetOrQuery'	=> 'Please enter a search term or select a facet!',
	'mainNamespace'		=> 'Main',
	'namespaceTooltip'	=> '$1 article(s) in this namespace match the selection.',
	'allNamespaces'		=> 'All namespaces',
	'nonexArticle'		=> 'The article does not exist. Click here to create it=>',
	'searchLink' 		=> 'Link to this search',
	'searchLinkTT'		=> 'Right click to copy or bookmark this search',

	'_TYPE' => 'Has type',
	'_URI'  => 'Equivalent URI',
	'_SUBP' => 'Subproperty of',
	'_SUBC' => 'Subcategory of',
	'_UNIT' => 'Display units',
	'_IMPO' => 'Imported from',
	'_CONV' => 'Corresponds to',
	'_SERV' => 'Provides service',
	'_PVAL' => 'Allows value',
	'_MDAT' => 'Modification date',
	'_CDAT' => 'Creation date',
	'_NEWP' => 'Is a new page',
	'_LEDT' => 'Last editor is',
	'_ERRP' => 'Has improper value for',
	'_LIST' => 'Has fields',
	'_SOBJ' => 'Has subobject',
	'_ASK'  => 'Has query',
	'_ASKST'=> 'Query string',
	'_ASKFO'=> 'Query format',
	'_ASKSI'=> 'Query size',
	'_ASKDE'=> 'Query depth'
);

/**
 * German
 */
$messages['de'] = array(
	/* general/maintenance messages */
	'facetedsearch'           => 'Facettierte Suche',
	'facetedsearch-desc'      => 'EnhancedRetrieval ermöglicht facettierte Suche in Mediawiki. Es benötigt die [https://www.diqa.de/de/Power-Search_for_MediaWiki DIQA-Powersuche] als Backend.',
	'fs_facetedsearchspecial' => 'Facettierte Suche',  // Name of the special page for Faceted Search
	'fs_specialpage_group'    => 'Facettierte Suche',

	//--- Messages for the special page ---
	'fs_title'            => 'Facettensuche',
	'fs_search'           => 'Finde',
	'fs_categories'       => 'Kategorien',
	'fs_properties'       => 'Eigenschaften',
	'fs_namespaces'       => 'Namensräume',
	'fs_search_results'   => 'Suchresultate',
	'fs_selected'         => 'ausgewählte Facetten',
	'fs_available_facets' => 'Verfügbare Facetten',
	'fs_relevance'        => 'Relevanz',
	'fs_newest_date_first'=> 'Neuester Artikel zuerst',
	'fs_oldest_date_first'=> 'Ältester Artikel zuerst',
	'fs_title_ascending'  => 'Artikelname aufsteigend',
	'fs_title_descending' => 'Artikelname absteigend',
	'fs_sort_by'          => 'Sortierung:',

	'fs_prop_ignoreasfacet' => 'Ignoriere als Facette',

	/* Javascript messages */
	'solrConnectionError' => 'VERBINDUNGSFEHLER. Bitte Browser-Log untersuchen.',
	'solrNotFound'		=> 'Es konnte noch keine Verbindung zum SOLR Server hergestellt werden. Die facettierte Suche wird noch nicht funktionieren.'.
							'Der SOLR Server wird hier gesucht: "$1". Möglicherweise blockiert die lokale Firewall den Port des SOLR Servers. '.
							'Bitte prüfen Sie ob proxy/env.php korrekt konfiguriert ist. Sie finden ein Beispiel unter proxy/env-sample.php',
	'tryConnectSOLR'	=> 'Verbindung mit der Suchmaschine wird aufgebaut ...',
	'more' 				=> 'mehr',
	'less' 				=> 'weniger',
	'noFacetFilter'		=> '(Keine Facetten ausgewählt.)',
	'underspecifiedSearch' => 'Ihre aktuelle Suche hat zu viele Treffer. Bitte verfeinern Sie sie!',
	'session_lost'      => 'Browser-Session ist abgelaufen. Bitte loggen Sie sich neu ein.',
	'removeFilter'		=> 'Diese Facette enfernen',
	'removeRestriction'	=> 'Einschränkung entfernen',
	'removeAllFilters'	=> 'Alle Facetten entfernen',
	'pagerPrevious'		=> '&lt; Vorherige',
	'pagerNext'			=> 'Nächste &gt;',
	'results'			=> 'Resultate',
	'to'				=> 'bis',
	'of'				=> 'von',
	'ofapprox'			=> 'von ungefähr',
	'inCategory'		=> 'ist in Kategorie',
	'show'				=> 'Eigenschaften zeigen',
	'hide'				=> 'Eigenschaften ausblenden',
	'showDetails'		=> 'Zeige Details',
	'hideDetails'		=> 'Details ausblenden',
	'lastChange'		=> 'Letzte Änderung',
	'addFacetOrQuery'	=> 'Bitte geben Sie einen Suchbegriff ein oder wählen Sie eine Facette aus!',
	'mainNamespace'		=> 'Main',
	'namespaceTooltip'  => '$1 Artikel in diesem Namensraum passen zur Auswahl',
	'allNamespaces'		=> 'Alle Namensräume',
	'nonexArticle'		=> 'Der Artikel existiert nicht. Klicken Sie hier, um ihn zu erstellen=>',
	'searchLink' 		=> 'Link zur Suche',
	'searchLinkTT'		=> 'Rechts klicken zum Kopieren oder Lesezeichen setzen.',

	'_TYPE' => 'Datentyp',
	'_URI'  => 'Gleichwertige URI',
	'_SUBP' => 'Unterattribut von',
	'_SUBC' => 'Unterkategorie von',
	'_UNIT' => 'Einheiten',
	'_IMPO' => 'Importiert aus',
	'_CONV' => 'Entspricht',
	'_SERV' => 'Bietet Service',
	'_PVAL' => 'Erlaubt Wert',
	'_MDAT' => 'Zuletzt geändert',
	'_CDAT' => 'Erstellt',
	'_NEWP' => 'Ist eine neue Seite',
	'_LEDT' => 'Letzter Bearbeiter ist',
	'_ERRP' => 'Hat unpassenden Wert für',
	'_LIST' => 'Hat Komponenten',
	'_SOBJ' => 'Hat Unterobjekt',
	'_ASK'  => 'Hat Abfrage',
	'_ASKST'=> 'Abfragetext',
	'_ASKFO'=> 'Abfrageformat',
	'_ASKSI'=> 'Abfragegröße',
	'_ASKDE'=> 'Abfragetiefe'
);

$messages['de-ch'] = $messages['de'];