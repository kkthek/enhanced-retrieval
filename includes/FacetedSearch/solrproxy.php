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
 * @ingroup FacetedSearch
 * 
 * This is a proxy for SOLR requests that can be invoke via port 80. This is needed
 * in case the standard SOLR port is blocked by a firewall.
 * 
 * The script is called instead of the SOLR server. The SOLR query is fetched and
 * sent to the SOLR server. The response is returned as result of this script.
 *
 * @author Thomas Schweitzer
 * Date: 22.11.2011
 *
 */


/**
 * Configuration of the SOLR server
 * 
 * $SOLRhost: Name or IP address of the SOLR server
 * $SOLRport: Port of the SOLR server
 */

$SOLRhost = 'localhost';
$SOLRport = 8080;

// Please comment this configuration if your are NOT using HaloACL
/*
$spgHaloACLConfig = array(
			'wikiDBprefix'	=> '',
			'wikiDBname'	=> 'testdb',
			'cookiePath'	=> '/',		 // must be equal to $wgCookiePath
			'cookieDomain'	=> '', 		 // must be equal to $wgCookieDomain = "";
			'cookieSecure'	=> false,	 // must be equal to $wgCookieSecure = false;
			'cookieHttpOnly'	=> true, // must be equal to $wgCookieHttpOnly = true;
			'memcacheconfig' => array(
				'servers' => array('localhost:11211'),
				'debug'   => false,
				'compress_threshold' => 10240,
				'persistant' => true),
			'mediawikiIndex' => 'http://localhost/mediawiki/index.php',
			'categoryNS'	=> 14,
			'propertyNS'	=> 102,
			'contentlanguage' => 'en'

);
*/

// Used to control a valid entry point for some classes that are only used by the
// solrproxy.
define('SOLRPROXY', true);

// Include the Apache Solr Client library
require_once('SolrPhpClient/Apache/Solr/Service.php');

if (isset($spgHaloACLConfig) && $spgHaloACLConfig) {
	require_once 'Solrproxy/FS_ResultFilter.php';
}
require_once 'Solrproxy/FS_QueryParser.php';

/**
 * This is a sub class of the Apache_Solr_Service. It adds an additional method
 * for sending raw queries to SOLR.
 * 
 * @author thsc
 *
 */
class SolrProxy extends Apache_Solr_Service {
	
	/**
	 * Constructor. All parameters are optional and will take on default values
	 * if not specified.
	 *
	 * @param string $host
	 * @param string $port
	 * @param string $path
	 * @param Apache_Solr_HttpTransport_Interface $httpTransport
	 */
	public function __construct($host = 'localhost', $port = 8983, $path = '/solr/', $httpTransport = false)
	{
		parent::__construct($host, $port, $path, $httpTransport);
	}
	
	/**
	 * Does a raw search on the SOLR server. The $queryString should have the
	 * Lucene query format
	 *
	 * @param string $queryString The raw query string
	 * @param string $method The HTTP method (Apache_Solr_Service::METHOD_GET or Apache_Solr_Service::METHOD::POST)
	 * @return Apache_Solr_Response
	 *
	 * @throws Apache_Solr_HttpTransportException If an error occurs during the service call
	 * @throws Apache_Solr_InvalidArgumentException If an invalid HTTP method is used
	 */
	public function rawsearch($queryString, $method = self::METHOD_GET)
	{

		if ($method == self::METHOD_GET)
		{
			return $this->_sendRawGet($this->_searchUrl . $this->_queryDelimiter . $queryString);
		}
		else if ($method == self::METHOD_POST)
		{
			return $this->_sendRawPost($this->_searchUrl, $queryString, FALSE, 'application/x-www-form-urlencoded; charset=UTF-8');
		}
		else
		{
			throw new Apache_Solr_InvalidArgumentException("Unsupported method '$method', please use the Apache_Solr_Service::METHOD_* constants");
		}
	}
	
}

header('Content-Type: application/javascript'); // required for JSONP by IE

// Get the query string from the URL
$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : false;

// create a new solr service instance with the configured settings
$solr = new SolrProxy($SOLRhost, $SOLRport, '/solr/');

// if magic quotes is enabled then stripslashes will be needed
if (get_magic_quotes_gpc() == 1)
{
	$query = stripslashes($query);
}

// Is access control enabled?
$acEnabled = isset($spgHaloACLConfig);

// Get start and number of expected rows from query. Access control might filter
// results but nevertheless we want to return the expected number of results.
$queryParser = new FSQueryParser($query);
$numExpectedResults = $queryParser->get('rows');
if (!$numExpectedResults) {
	// 10 results is the default
	$numExpectedResults = 10;
}
$start = $queryParser->get('start');

if ($acEnabled) {
	$resultFilter = FSResultFilter::getInstance();
}
$numPermittedResults = 0;
$tryNumResults = $numExpectedResults;
$furtherResultsAvailable = true;
while ($numPermittedResults < $numExpectedResults && $furtherResultsAvailable) {
	try
	{
		if ($acEnabled) {
			// Access control is enabled. Ask for more results than necessary
			// as some of them might be filtered.
			$tryNumResults += 10;
			$queryParser->set('rows', $tryNumResults);
			$query = $queryParser->serialize();
		}
		$results = $solr->rawsearch($query, SolrProxy::METHOD_POST);
		$response = $results->getRawResponse();
		if ($acEnabled) {
			list($numPermittedResults, $numNeededResults, $furtherResultsAvailable) =
				$resultFilter->countPermittedResults($user, 'read', $response, $numExpectedResults);
			if ($numNeededResults !== -1 && $numNeededResults !== $numPermittedResults) {
				// There are enough permitted results. Ask the query again to get 
				// exactly the number of needed results.
				$queryParser->set('rows', $numNeededResults);
				$query = $queryParser->serialize();
				$results = $solr->rawsearch($query);
				$response = $results->getRawResponse();
			}
		} else {
			// Without access control there is no need searching for further results
			$numPermittedResults = $numExpectedResults;
		}
		
	}
	catch (Exception $e)
	{
		die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
	}
}	


if ($acEnabled) {
	$response = $resultFilter->filterResult(NULL, 'read', $response);
}
echo $response;
