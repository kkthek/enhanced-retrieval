<?php
use DIQA\FacetedSearch\Solrproxy\FSQueryParser;
use DIQA\FacetedSearch\Solrproxy\FSResultFilter;
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
$SOLRuser = '';
$SOLRpass = '';
$SOLRcore = '';

// if solr-env.php exists create a proxy-session
if (file_exists(__DIR__ . '/../../solr-env.php')) {
	
	session_start();
	require_once(__DIR__ . '/../../solr-env.php');
	$userid = $_COOKIE[$wgDBname.'UserID'];
	$userName = $_COOKIE[$wgDBname.'UserName'];
	
	// access Wiki once to retrieve user groups and store it in a proxy-session
	if (!isset($_SESSION['user_groups'.$userid])) {
		$_SESSION['user_groups'.$userid] = [];
		$res = HttpGet($wgServerHTTP . $wgScriptPath . "/api.php?action=fs_userdataapi&format=json");
		$o = json_decode($res[2]);
		$groups = isset($o->result->user_groups) ? $o->result->user_groups : [];
		if (count($groups) === 0) {
			$groups[] = 'user'; // add default group
		}
		$_SESSION['user_groups'.$userid] = $groups;
	}
}


// Used to control a valid entry point for some classes that are only used by the
// solrproxy.
define('SOLRPROXY', true);

// Include the Apache Solr Client library
require_once('SolrPhpClient/Apache/Solr/Service.php');

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
	 * @param Boolean
	 * @param string $userpass user:pass
	 * @param Apache_Solr_HttpTransport_Interface $httpTransport
	 */
	public function __construct($host = 'localhost', $port = 8983, $path = '/solr/', $httpTransport = false, $userpass)
	{
		parent::__construct($host, $port, $path, $httpTransport, $userpass);
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
$core = $SOLRcore == '' ? '/solr/' : '/solr/' . $SOLRcore . '/';
$solr = new SolrProxy($SOLRhost, $SOLRport, $core, false, "$SOLRuser:$SOLRpass");

// if magic quotes is enabled then stripslashes will be needed
if (get_magic_quotes_gpc() == 1)
{
	$query = stripslashes($query);
}

try {
		$query = applyConstraints($query);
		$query = putFilterParamsToMainParams($query);
		$results = $solr->rawsearch($query, SolrProxy::METHOD_POST);
		$response = $results->getRawResponse();
		if (isset($fsgUseStatistics) && $fsgUseStatistics === true) {
			updateSearchStats($response);
		}
		
} catch (Exception $e) {
	die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
}
	

echo $response;

/**
 * Updates the search statistics in MW object cache.
 * 
 * @param string $response SOLR response (JSONp)
 */
function updateSearchStats($response) {
	$response = substr($response, strlen('_jqjsp('), -2);
	$jsonResponse = json_decode($response);
	
	$numFound = $jsonResponse->response->numFound;
	$params = '--searches';
	if ($numFound > 0) {
		$params .= ' --searchHits';
	}
	
	BackgroundProcess::open("php " . __DIR__ . "/../../maintenance/updateSearchStats.php $params");
}
/**
 * Applies constraints depending on user groups.
 *
 * @param string $query
 * @return string
 */
function applyConstraints($query) {
	global $userid, $userName;
	global $fsgNamespaceConstraint, $fsgCustomConstraint;
	
	$userGroups = $_SESSION['user_groups'.$userid];
	
	// namespace constraints
	if (!isset($fsgNamespaceConstraint)) $fsgNamespaceConstraint = [];
	foreach($fsgNamespaceConstraint as $group => $namespaces) {
		if (in_array($group, $userGroups)) {
			$constraints = [];
			foreach($namespaces as $namespace) {
				$constraints[] = "smwh_namespace_id:$namespace";
			}
			
			$query = $query . "&fq=".urlencode(implode(' OR ', $constraints));
		}
	}
	
	// custom constraints
	if (!isset($fsgCustomConstraint)) $fsgCustomConstraint = [];
	foreach($fsgCustomConstraint as $operation) {
		$query = $operation($query, $userGroups, $userName);
	}
	return $query;
	
}
/**
 * Adds filter query parameters to main query parameters.
 * This is required for boosting.
 * 
 * @param string $query
 * @return string
 */
function putFilterParamsToMainParams($query) {
	
	// parse query string
	$parsedResults = [];
	$params = explode("&", $query);
	foreach($params as $p) {
		$keyValue = explode("=", $p);
		$parsedResults[$keyValue[0]][] = $keyValue[1];
	}
	
	// add fq-params to q-params
	if (isset($parsedResults['fq'])) {
		foreach($parsedResults['fq'] as $fq) {
			$parsedResults['q'][] = $fq;
		}
		
	}
	
	// add boost dummy
	$parsedResults['q'][] = 'smwh_boost_dummy%3A1';
	
	// serialize query string
	$url = '';
	$first = true;
	foreach($parsedResults as $key => $values) {
		
		if ($key == 'q') {
			if (!$first) {
				$url .= '&';
			}
			$url .= "q=";
			$url .= '(' . implode(' ) AND ( ', $values) . ')';
			$first = false;
		} else {
			foreach($values as $val) {
				if (!$first) {
					$url .= '&';
				}
				$val = (string) $val;
				$url .= "$key=$val";
				$first = false;
			}
		}
	}
	
	return $url;
}

/**
 * Does a HTTP GET request containing MW session data. 
 * (requires php-curl to be activated)
 * 
 * @param string $url
 * @return (header, HTTP status code, content)
 */
function HttpGet($url) {
	$res = "";
	$header = "";
	global $wgDBname;
	$sessionId = $_COOKIE[$wgDBname.'_session'];
	$username = $_COOKIE[$wgDBname.'UserName'];
	$userid = $_COOKIE[$wgDBname.'UserID'];

	// Create a curl handle to a non-existing location
	$ch = curl_init($url);

	$cookieprefix =
	// Execute
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_COOKIE, "{$wgDBname}_session=$sessionId; {$wgDBname}UserName=$username; {$wgDBname}UserID=$userid;");
	$res = curl_exec($ch);

	$status = curl_getinfo($ch,CURLINFO_HTTP_CODE);
	curl_close($ch);

	$bodyBegin = strpos($res, "\r\n\r\n");
	list($header, $res) = $bodyBegin !== false ? array(substr($res, 0, $bodyBegin), substr($res, $bodyBegin+4)) : array($res, "");
	return array($header, $status, str_replace("%0A%0D%0A%0D", "\r\n\r\n", $res));
}

class BackgroundProcess {

	static function open($exec, $cwd = null) {
		if (!is_string($cwd)) {
			$cwd = @getcwd();
		}

		@chdir($cwd);

		if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
			$WshShell = new COM("WScript.Shell");
			$WshShell->CurrentDirectory = str_replace('/', '\\', $cwd);
			$WshShell->Run($exec, 0, false);
		} else {
			exec($exec . " > /dev/null 2>&1 &");
		}
	}

	static function fork($phpScript, $phpExec = null) {
		$cwd = dirname($phpScript);

		@putenv("PHP_FORCECLI=true");

		if (!is_string($phpExec) || !file_exists($phpExec)) {
			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
				$phpExec = str_replace('/', '\\', dirname(ini_get('extension_dir'))) . '\php.exe';

				if (@file_exists($phpExec)) {
					BackgroundProcess::open(escapeshellarg($phpExec) . " " . escapeshellarg($phpScript), $cwd);
				}
			} else {
				$phpExec = exec("which php-cli");

				if ($phpExec[0] != '/') {
					$phpExec = exec("which php");
				}

				if ($phpExec[0] == '/') {
					BackgroundProcess::open(escapeshellarg($phpExec) . " " . escapeshellarg($phpScript), $cwd);
				}
			}
		} else {
			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
				$phpExec = str_replace('/', '\\', $phpExec);
			}

			BackgroundProcess::open(escapeshellarg($phpExec) . " " . escapeshellarg($phpScript), $cwd);
		}
	}
}
