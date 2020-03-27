<?php

use DIQA\SolrProxy\SolrService;
use DIQA\SolrProxy\ConfigLoader;

if ( !defined('SOLRPROXY')) {
	die('Not an valid entry point.');
}

global $SOLRcore;
global $SOLRhost;
global $SOLRport;
global $SOLRuser;
global $SOLRpass;
global $fsgUseStatistics;

if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
} else if (file_exists(__DIR__ . '/../../../env.php')) {
    require_once __DIR__ . '/../../../env.php';
} else {
    ConfigLoader::loadConfig();
}

if (file_exists(__DIR__ . '/custom.php')) {
    require_once(__DIR__ . '/custom.php');
}

global $SOLRProxyDebug;

if (isset($SOLRProxyDebug) && $SOLRProxyDebug === true) {
    error_reporting( E_ALL );
    ini_set( 'display_startup_errors', 1 );
    ini_set( 'display_errors', 1 );
}

if (!isset($SOLRhost)) {
   $SOLRhost = 'localhost';
}
if (!isset($SOLRport)) {
   $SOLRport = 8983;
}
if (!isset($SOLRuser)) {
   $SOLRuser = '';
}
if (!isset($SOLRpass)) {
   $SOLRpass = '';
}
if (!isset($SOLRcore)) {
   $SOLRcore = '';
}

// required for JSONP by IE
header('Content-Type: application/javascript');

// Get the query string from the URL
$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : false;

// create a new solr service instance with the configured settings
$core = $SOLRcore == '' ? '/solr/' : '/solr/' . $SOLRcore . '/';
try {
    
    $solr = new SolrService($SOLRhost, $SOLRport, $core, false, "$SOLRuser:$SOLRpass");
   
    // if magic quotes is enabled then stripslashes will be needed
    if (get_magic_quotes_gpc() == 1) {
    	$query = stripslashes($query);
    }
    
 
	$query = $solr->applyConstraints($query);
	$query = $solr->putFilterParamsToMainParams($query);

	$results = $solr->rawsearch($query, SolrService::METHOD_POST);
	$response = $results->getRawResponse();
	if (isset($fsgUseStatistics) && $fsgUseStatistics === true) {
		$solr->updateSearchStats($response);
	}
} catch (Exception $e) {
    $res = new stdClass();
    $res->error = true;
    $res->msg = $e->getMessage();
    header("HTTP/1.0 500 Internal error");
    header('Content-Type: text/html');
    echo '<h1 style="color:red;">ERROR</h1>';
    echo "<br>Error message from SOLR-proxy: " . $e->getMessage();
    echo "<br>Please make sure that proxy/env.php is configured. You'll find an example at proxy/env.sample.php";
	die();
}

echo $response;