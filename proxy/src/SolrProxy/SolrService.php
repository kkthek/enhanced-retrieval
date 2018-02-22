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
            throw new \Apache_Solr_InvalidArgumentException("Not logged in: " .$e->getMessage());
        }
    }

    /**
     * Does a raw search on the SOLR server.
     * The $queryString should have the
     * Lucene query format
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
    function updateSearchStats($response) {
        $response = substr($response, strlen('_jqjsp('), - 2);
        $jsonResponse = json_decode($response);
        
        $numFound = $jsonResponse->response->numFound;
        $params = '--searches';
        if ($numFound > 0) {
            $params .= ' --searchHits';
        }
        
        BackgroundProcess::open("php " . __DIR__ . "/../../maintenance/updateSearchStats.php $params");
    }

    /**
     * Applies constraints depending on user groups.
     *
     * @param string $query
     * @return string
     */
    function applyConstraints($query) {
        global $userid, $userName;
        global $fsgNamespaceConstraint, $fsgCustomConstraint;
        
        $userGroups = $this->groups;
        
        // treat him always as member of "user"
        if (! in_array('user', $userGroups)) {
        	$userGroups[] = 'user';
        }
        
        // namespace constraints
        if (! isset($fsgNamespaceConstraint)) {
            $fsgNamespaceConstraint = [];
        }
        $constraints = [];
        foreach ($fsgNamespaceConstraint as $group => $namespaces) {
            if (in_array($group, $userGroups)) {
                foreach ($namespaces as $namespace) {
                    $constraints[] = "smwh_namespace_id:$namespace";
                }
            }
        }
        $constraints = array_unique($constraints);
        if (count($constraints) > 0) {
            $query = $query . "&fq=" . urlencode(implode(' OR ', $constraints));
        }
        
        // custom constraints
        if (! isset($fsgCustomConstraint)) {
            $fsgCustomConstraint = [];
        }
        foreach ($fsgCustomConstraint as $operation) {
            $query = $operation($query, $userGroups, $userName);
        }
        return $query;
    }

    /**
     * Adds filter query parameters to main query parameters.
     * This is required for boosting.
     *
     * @param string $query
     * @return string
     */
    function putFilterParamsToMainParams($query) {
        
        // parse query string
        $parsedResults = [];
        $params = explode("&", $query);
        foreach ($params as $p) {
            $keyValue = explode("=", $p);
            $parsedResults[$keyValue[0]][] = $keyValue[1];
        }
        
        // add fq-params to q-params
        if (isset($parsedResults['fq'])) {
            foreach ($parsedResults['fq'] as $fq) {
                $parsedResults['q'][] = $fq;
            }
        }
        
        // add boost dummy
        $parsedResults['q'][] = 'smwh_boost_dummy%3A1';
        
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
