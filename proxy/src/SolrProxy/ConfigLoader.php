<?php
namespace DIQA\SolrProxy;

use DIQA\Util\Configuration\ConfigLoader as UtilConfigLoader;

/**
 * Loads the SOLR/EnhancedRetrieval configuration files.
 */
class ConfigLoader {
        
    public static function loadConfig() {
        require_once __DIR__ . '/../../../../Util/src/Util/Configuration/ConfigLoader.php';
        
        $mwPath = __DIR__ . '/../../../../..';
        $ds = __DIR__ . '/../../../DefaultSettings.php';
        $configVariables = [
            'wgDBname', 'wgDBuser', 'wgDBpassword',
            'wgServerHTTP', 'wgScriptPath',
            'SOLRhost', 'SOLRport', 'SOLRuser', 'SOLRpass', 'SOLRcore',
            'fsgNamespaceConstraint', 'fsgCustomConstraint', 'fsgUseStatistics'
        ];

		$loader = new UtilConfigLoader($mwPath, $ds, $configVariables);
        $loader->loadConfig();
    }
    
}