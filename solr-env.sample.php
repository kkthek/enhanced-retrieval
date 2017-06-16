<?php
/**
 * RENAME this file to solr-env.php to use it.
 * 
 * Make sure you adjust the server properties below.
 * 
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
 */
$fsgNamespaceConstraint = [ ];

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