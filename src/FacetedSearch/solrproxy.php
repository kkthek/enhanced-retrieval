<?php
/*
 * Copyright (C) Vulcan Inc., DIQA-Projektmanagement GmbH 
 * 
 * This program is free software;
 * 
 * you can redistribute it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or 
 * (at your option) any later version. This program is distributed in the hope that it
 * will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program
 * If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @file
 * @ingroup FacetedSearch
 *
 * This is a proxy for SOLR requests that can be invoke via port 80. This is needed
 * in case the standard SOLR port is blocked by a firewall.
 *
 * The script is called instead of the SOLR server. The SOLR query is fetched and
 * sent to the SOLR server. The response is returned as result of this script.
 *
 * @author Thomas Schweitzer
 *         Date: 22.11.2011
 *        
 */

// Used to control a valid entry point for 
// some classes that are only used by the
// solrproxy.
define ( 'SOLRPROXY', true );

if (is_readable ( __DIR__ . '/../../proxy/vendor/autoload.php' )) {
	include_once __DIR__ . '/../../proxy/vendor/autoload.php';
	exit ();
}

