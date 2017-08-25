<?php

use DIQA\SolrProxy\SolrService;
use DIQA\SolrProxy\Auth;

if ( !defined('SOLRPROXY')) {
	die('Not an valid entry point.');
}

$SOLRhost = 'localhost';
$SOLRport = 8080;
$SOLRuser = '';
$SOLRpass = '';
$SOLRcore = '';


if (file_exists(__DIR__ . '/../solr-env.php')) {
	require_once(__DIR__ . '/../solr-env.php');
}


header('Content-Type: application/javascript'); // required for JSONP by IE

// Get the query string from the URL
$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : false;

// create a new solr service instance with the configured settings
$core = $SOLRcore == '' ? '/solr/' : '/solr/' . $SOLRcore . '/';
$solr = new SolrService($SOLRhost, $SOLRport, $core, false, "$SOLRuser:$SOLRpass");

// if magic quotes is enabled then stripslashes will be needed
if (get_magic_quotes_gpc() == 1)
{
	$query = stripslashes($query);
}

try {
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