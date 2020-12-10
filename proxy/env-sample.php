<?php
/**
 * To use this configuration file copy or rename it to:
 *     env.php
 * 
 * For a complete list of configuration settings, check the DefaultSetting.php file.
 *
 * Make sure to set the following properties to the same values
 * as in LocalSettings.php. 
 */

/**
 * SOLR connection data (REQUIRED!)
 */
global $SOLRhost, $SOLRport, $SOLRuser, $SOLRpass, $SOLRcore;
$SOLRhost = 'localhost';
$SOLRport = 8983;
$SOLRuser = '';
$SOLRpass = '';
$SOLRcore = 'mw';

/**
 * Wiki connection data (REQUIRED!)
 */
global $wgServer, $wgScriptPath, $wgDBname;
$wgServer = "http://localhost";
$wgScriptPath = "/mediawiki";
$wgDBname = 'wikidb';


/**
 * If something does not work, set this to true
 */
global $SOLRProxyDebug;
$SOLRProxyDebug=false;


/**
 * The following configurations are optional.
 */

/**
 * Specifies the namespaces visible for groups.
 * 
 * group => array of namespaces-IDs
 * 
 * group => array of namespaces-IDs, e.g.
 *
 * $fsgNamespaceConstraint = [
 * 	'sysop' => [ 0, 10, 14 ] // sysop users may only see Main, Template and Category pages
 * ];
 *
 * Please note: You CANNOT use Mediawiki constants like NS_MAIN here.
 * 'user' is default group if a user is in no other group.
 */
# global $fsgNamespaceConstraint;
# $fsgNamespaceConstraint = [
#     'user'          => [ 0, 14, 6, 2, 12 ],             /* Main, Category, File, User, Help */
#     'wikisysop'     => [ 0, 14, 6, 2, 12, 10, 102, 106 ]/* Main, Category, File, User, Help, Property, Template, Formular */
# ];


/**
 * List of functions that can rewrite SOLR queries based on usernames and their groups,
 * e.g. to hide pages with certain properties.
 */
# global $fsgCustomConstraint;
# $fsgCustomConstraint = [
# 
# 	 /**
# 	  * Returns re-written query.
# 	  * 
# 	  * @param string $query The SOLR query URL.
# 	  * @param array $userGroups All groups a user is member of
# 	  * @param string $userName The username (login)
# 	  */
# 	 function($query, $userGroups, $userName) {
# 		return $query;
# 	}
# 	
# ];
