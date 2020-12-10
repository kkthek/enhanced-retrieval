<?php
namespace DIQA\SolrProxy;

use DIQA\Util\Configuration\ConfigLoader as UtilConfigLoader;

/**
 * Loads the SOLR/EnhancedRetrieval configuration files.
 */
class ConfigLoader {

    public static function loadConfig() {

        $configLoaderClass = __DIR__ . '/../../../../Util/src/Util/Configuration/ConfigLoader.php';
        if (!file_exists($configLoaderClass)) {
            return;
        }

        require_once $configLoaderClass;

        $mwPath = __DIR__ . '/../../../../..';
        $ds = __DIR__ . '/../../../DefaultSettings.php';
        $configVariables = [
                'wgDBname',
                'wgDBpassword',
                'wgDBuser',
                'wgScriptPath',
                'wgServer',
                'SOLRcore',
                'SOLRhost',
                'SOLRpass',
                'SOLRport',
                'SOLRuser',
                'fsgCategoriesToShowInTitle',
                'fsgCategoryFilter',
                'fsgCustomConstraint',
                'fsgDemotionProperty',
                'fsgExtraPropertiesToRequest',
                'fsgFacetsWithOR',
                'fsgNamespaceConstraint',
                'fsgNumericPropertyClusters',
                'fsgPromotionProperty',
                'fsgShowCategories',
                'fsgShowSortOrder',
                'fsgShownFacets',
                'fsgTitleProperty',
                'fsgUseStatistics'
        ];

        $loader = new UtilConfigLoader($mwPath, $ds, $configVariables);
        $loader->loadConfig();
    }

}
