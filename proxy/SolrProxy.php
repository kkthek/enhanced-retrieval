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
} else {
    ConfigLoader::loadConfig();
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
	die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
}

echo $response;