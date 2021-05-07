<?php
namespace DIQA\SolrProxy;

require_once (__DIR__ . '/../SolrPhpClient/Apache/Solr/Service.php');

/**
 * This is a sub class of the Apache_Solr_Service.
 * It adds an additional method for sending raw queries to SOLR.
 *
 * @author thsc
 *
 */
class SolrService extends \Apache_Solr_Service {

    private $groups;

    /**
     * Constructor.
     * All parameters are optional and will take on default values
     * if not specified.
     *
     * @param string $host
     * @param string $port
     * @param string $path
     * @param boolean $httpTransport
     * @param string $userpass of the form "user:pass"
     */
    public function __construct($host = 'localhost', $port = 8983, $path = '/solr/', $httpTransport = false, $userpass) {
        parent::__construct($host, $port, $path, $httpTransport, $userpass);

        try {
            $this->groups = Auth::session();
        } catch (\Exception $e) {
            throw new \Apache_Solr_InvalidArgumentException($e->getMessage());
        }
    }

    /**
     * Does a raw search on the SOLR server.
     * The $queryString should have the Lucene query syntax.
     *
     * @param string $queryString
     *            The raw query string
     * @param string $method
     *            The HTTP method (Apache_Solr_Service::METHOD_GET or Apache_Solr_Service::METHOD::POST)
     * @return Apache_Solr_Response
     *
     * @throws Apache_Solr_HttpTransportException If an error occurs during the service call
     * @throws Apache_Solr_InvalidArgumentException If an invalid HTTP method is used
     */
    public function rawsearch($queryString, $method = self::METHOD_GET) {
        $queryString = $this->extendQuery($queryString);

        if ($method == self::METHOD_GET) {
            return $this->_sendRawGet($this->_searchUrl . $this->_queryDelimiter . $queryString);
        } else if ($method == self::METHOD_POST) {
            return $this->_sendRawPost($this->_searchUrl, $queryString, FALSE, 'application/x-www-form-urlencoded; charset=UTF-8');
        } else {
            throw new \Apache_Solr_InvalidArgumentException("Unsupported method '$method', please use the Apache_Solr_Service::METHOD_* constants");
        }
    }

    /**
     * Updates the search statistics in MW object cache.
     *
     * @param string $response
     *            SOLR response (JSONp)
     */
    public function updateSearchStats($response) {
        $response = substr($response, strlen('_jqjsp('), - 2);
        $jsonResponse = json_decode($response);

        $numFound = $jsonResponse->response->numFound;
        $params = '--searches';
        if ($numFound > 0) {
            $params .= ' --searchHits';
        }

        BackgroundProcess::open("php " . __DIR__ . "/../../maintenance/updateSearchStats.php $params");
    }

    private function getUserGroups() {
        $userGroups = $this->groups;
        // every users is treated as being a member of "user"
        if (! in_array('user', $userGroups)) {
            $userGroups[] = 'user';
        }
        return $userGroups;
    }

    /**
     * Applies constraints depending on user groups.
     *
     * @param string $query
     * @return string
     */
    private function extendQuery($queryString) {
        $modifiedQuery = $this->applyNamespaceConstraints($queryString);
        $modifiedQuery = $this->applyCustomConstraints($modifiedQuery);
        $modifiedQuery = $this->putFilterParamsToMainParams($modifiedQuery);
        $modifiedQuery = str_replace(' ', '%20', $modifiedQuery);
        return $modifiedQuery;
    }

    private function applyNamespaceConstraints($query) {
        global $fsgNamespaceConstraint;
        if (! isset($fsgNamespaceConstraint)) {
            return $query;
        }

        $userGroups = $this->getUserGroups();

        $constraints = [];
        foreach ($fsgNamespaceConstraint as $group => $namespaces) {
            if (in_array($group, $userGroups)) {
                foreach ($namespaces as $namespace) {
                    $constraints[] = "smwh_namespace_id%3A$namespace";
                }
            }
        }
        $constraints = array_unique($constraints);
        if (count($constraints) > 0) {
            return $query . "&fq=" . urlencode(implode(' OR ', $constraints));
        }

        return $query;
    }

    private function applyCustomConstraints($query) {
        global $fsgCustomConstraint;
        if (! isset($fsgCustomConstraint)) {
            return $query;
        }

        $userGroups = $this->getUserGroups();

        global $wgDBname;
        $userid = self::getCookie($wgDBname . 'UserID');
        $userName = self::getCookie($wgDBname . 'UserName');

        $modifiedQuery = $query;
        foreach ($fsgCustomConstraint as $operation) {
            $modifiedQuery = $operation($modifiedQuery, $userGroups, $userName, $userid);
        }

        return $modifiedQuery;
    }

    private static function getCookie($var) {
        if (isset($_COOKIE[$var])) {
            return $_COOKIE[$var];
        }
        return '';
    }

    /**
     * Adds filter query parameters to main query parameters.
     * This is required for boosting.
     *
     * @param string $query
     * @return string
     */
    private function putFilterParamsToMainParams($query) {
        // parse query string
        $parsedResults = [];
        $params = explode("&", $query);
        foreach ($params as $p) {
            $keyValue = explode("=", $p, 2);
            $parsedResults[$keyValue[0]][] = $keyValue[1];
        }

        // add fq-params to q-params
        if (isset($parsedResults['fq'])) {
            foreach ($parsedResults['fq'] as $fq) {
                $parsedResults['q'][] = $fq;
            }
        }

        // add boost dummy
        global $fsgSwitchOfBoost;
        if ( isset($fsgSwitchOfBoost) && !$fsgSwitchOfBoost === true ) {
            $parsedResults['q'][] = 'smwh_boost_dummy%3A1';
        }

        // serialize query string
        $url = '';
        $first = true;
        foreach ($parsedResults as $key => $values) {
            if ($key == 'q') {
                if (! $first) {
                    $url .= '&';
                }
                $url .= "q=";
                $url .= '(' . implode(' ) AND ( ', $values) . ')';
                $first = false;
            } else {
                foreach ($values as $val) {
                    if (! $first) {
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
}
