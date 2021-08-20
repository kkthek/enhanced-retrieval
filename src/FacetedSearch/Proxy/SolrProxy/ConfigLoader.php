<?php
namespace DIQA\FacetedSearch\Proxy\SolrProxy;

/**
 * Loads the extended configuration files env.json / env-default.json and LocalVariables.php
 */
class ConfigLoader {

    public static function loadConfig() {

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
            'fsgAnnotationsInSnippet',
            'fsgBlacklistPages',
            'fsgCategoriesToShowInTitle',
            'fsgCategoryBoosts',
            'fsgCategoryFilter',
            'fsgCreateNewPageLink',
            'fsgCustomConstraint',
            'fsgDateTimePropertyClusters',
            'fsgDefaultBoost',
            'fsgDefaultSortOrder',
            'fsgDemotionProperty',
            'fsgEnableIncrementalIndexer',
            'fsgExtraPropertiesToRequest',
            'fsgFacetedSearchForMW',
            'fsgFacetsWithOR',
            'fsgHitsPerPage',
            'fsgIndexPredefinedProperties',
            'fsgIndexSubobjects',
            'fsgNamespaceBoosts',
            'fsgNamespaceConstraint',
            'fsgNumericPropertyClusters',
            'fsgPromotionProperty',
            'fsgShowArticleProperties',
            'fsgShowCategories',
            'fsgShowFileInOverlay',
            'fsgShowNamespaces',
            'fsgShowSortOrder',
            'fsgShownCategoryFacets',
            'fsgShownFacets',
            'fsgActivateBoosting',
            'fsgTemplateBoosts',
            'fsgUseStatistics'
        ];

        $loader = new ConfigLoader($configVariables);
        $loader->loadConfiguration();
    }

    private $localVariablesFile = '';
    private $envJsonFile = '';
    private $envDefaultJsonFile = '';

    private $configVariables = [];

    /**
     * @param array $configVariables
     *                      list of config-variables that must be defined in (one of) the config files
     *                      used to verify a ConfigLoader properly fetched expected variables
     */
    public function __construct(
        array $configVariables = []) {
            global $IP;
            $this->envDefaultJsonFile = "$IP/env-default.json";
            $this->envJsonFile = "$IP/env.json";
            $this->localVariablesFile = "$IP/LocalVariables.php";

            $this->configVariables = $configVariables;
    }

    public function loadConfiguration() {
        //$this->logger->debug("Starting loading configuration files.");

        $ed = $this->loadEnvDefault();
        $ej = $this->loadEnvJson();
        $lv = $this->loadLocalVariables();

        $this->checkConfig();
    }

    /**
     * loads env-default.json and then env.json
     */
    public function loadEnv() {
        $ed = $this->loadEnvDefault();
        $ej = $this->loadEnvJson();

        if(!$ed && !$ej ) {
            $msg = "No configuration files found.";
            $this->error($msg);
            die($msg);
        }
    }

    private function loadEnvDefault() {
        return $this->loadJsonFile($this->envDefaultJsonFile);
    }

    private function loadEnvJson() {

        $mw = $this->loadJsonFile($this->envJsonFile);
        $apps = $this->loadJsonFile($this->envJsonFile);
        return $mw && $apps;
    }

    private function loadJsonFile($fileName = '', $appId = '') {
        if ($fileName) {
            if(file_exists($fileName) && is_readable($fileName)) {
                //$this->logger->debug("Loading $fileName");
                try {
                    $jsonString = file_get_contents($fileName);
                    $json = json_decode($jsonString, true);

                    if($json) {
                        $this->processApps($json, $appId);
                    } else {
                        return false;
                    }
                } catch (\Exception $e) {
                    $this->error("Cannot read configuration file '$fileName': " . $e->getMessage());
                    return false;
                }
            } else {
                $this->error("Failed to load $fileName");
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $json maps an appId (e,g, MW) to a config-array
     */
    private function processApps($json = [], $appFilter = '') {
        foreach ($json as $app => $value) {

            if( !$appFilter || $appFilter && $app == $appFilter ) {
                $this->makeGlobals($value);
            }
        }
    }

    /**
     * @param array $json maps config variables to their values
     */
    private function makeGlobals($json = []) {
        foreach ($json as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }

    private function loadLocalVariables() {
        return $this->loadPhpFile($this->localVariablesFile);
    }

    private function loadPhpFile($fileName = '') {
        if ($fileName) {
            if(file_exists($fileName) && is_readable($fileName)) {
                //$this->logger->debug("Loading $fileName");
                try {
                    @require $fileName;
                    return true;
                } catch (\Exception $e) {
                    $this->error("Cannot read configuration file '$fileName': " . $e->getMessage());
                    return false;
                }
            } else {
                $this->error("Failed to load $fileName");
                return false;
            }
        } else {
            return true;
        }
    }

    private function checkConfig() {
        foreach ($this->configVariables as $var ) {
            $this->checkIfConfigured($var);
        }
    }

    private function checkIfConfigured($var) {
        if (!isset($GLOBALS[$var])) {
            $msg = "'$var' is not configured in any of the configuration files.";
            $this->error($msg);
            die($msg);
        }
    }

    private function error($msg) {
        //echo "$msg\n";
        //trigger_error($msg);
        //$this->logger->error($msg);
    }

    public static function test() {
        $mwPath = __DIR__ . '/../../../../..';
        $ds = "$mwPath/LocalVariables.php";
        $configVariables = [
            'wgServerHTTP',
            'wgScriptPath'
        ];

        $loader = new ConfigLoader($mwPath, $ds, $configVariables);
        $loader->loadConfig();
    }
}

// ConfigLoader::test();
// echo "--------------------------------------\n";
// var_dump($GLOBALS);
