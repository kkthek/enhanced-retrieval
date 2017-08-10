<?php
/**
 * RENAME this file to solr-env.php to use it.
 * 
 * Make sure you adjust the server properties below.
 * 
 */

/**
 * SOLR connection data
 */
$SOLRhost = 'localhost';
$SOLRport = 8080;
$SOLRuser = '';
$SOLRpass = '';
$SOLRcore = '';

/**
 * Wiki connection data
 */
$wgServerHTTP = "http://wimawiki.local";
$wgScriptPath = "/mediawiki";
$wgDBname = 'wima_wiki';

/**
 * Specifies the visible namespace for groups.
 * 
 * group => array of namespaces-IDs, e.g.
 * 
 * $fsgNamespaceConstraint = [
 * 	'sysop' => [ 0, 10, 14 ] // sysop users may only see Main, Template and Category pages 
 * ];
 * 
 * Please note: You CANNOT use Mediawiki constants like NS_MAIN here.
 * 'user' is default group if a user is in no other group.
 * 
 */
$fsgNamespaceConstraint = [
    'user'          => [ 0, 14, 6, 2, 12 ],             /* Main, Kategorie, File, User, Help */
    'riskmanager'   => [ 0, 14, 6, 2, 12, 3302 ],       /* Main, Kategorie, File, User, Help, NS_RM */
    'wimaadmin'     => [ 0, 14, 6, 2, 12, 10, 102, 106 ]/* Main, Kategorie, File, User, Help, Property, Template, Formular */
];

$fsgCustomConstraint = [

	 /**
	  * Returns re-written query.
	  * 
	  * @param string $query The SOLR query URL.
	  * @param array $userGroups All groups a user is member of
	  * @param string $userName The username (login)
	  */
	 function($query, $userGroups, $userName) {
		
		return $query;
	}
	
];

/**
 * Use statistics logging
 * 
 */
$fsgUseStatistics = false;	