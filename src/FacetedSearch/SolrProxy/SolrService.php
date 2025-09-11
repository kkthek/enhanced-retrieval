<?php
namespace DIQA\FacetedSearch\SolrProxy;

use Apache\Solr\HttpTransportException;
use Apache\Solr\InvalidArgumentException;
use Apache\Solr\Response;
use Apache\Solr\Service;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;

/**
 * This is a subclass of the Apache\Solr\Service.
 * It adds an additional method for sending raw queries to SOLR.
 *
 * @author thsc
 *
 */
class SolrService extends Service {

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
    public function __construct($host = 'localhost', $port = 8983, $path = '/solr/', $httpTransport = false, $userpass = ':') {
        parent::__construct($host, $port, $path, $httpTransport, $userpass);
    }

    /**
     * Does a raw search on the SOLR server.
     * The $queryString should have the Lucene query syntax.
     *
     * @param string $queryString
     *            The raw query string
     * @param string $method
     *            The HTTP method (Apache\Solr\Service::METHOD_GET or Apache\Solr\Service::METHOD::POST)
     * @return Response
     *
     * @throws HttpTransportException If an error occurs during the service call
     * @throws InvalidArgumentException If an invalid HTTP method is used
     */
    public function rawsearch($queryString, $method = self::METHOD_GET) {
        $queryString = $this->extendQuery($queryString);

        if ($method == self::METHOD_GET) {
            return $this->_sendRawGet($this->_searchUrl . $this->_queryDelimiter . $queryString);
        } else if ($method == self::METHOD_POST) {
            return $this->_sendRawPost($this->_searchUrl, $queryString, FALSE, 'application/x-www-form-urlencoded; charset=UTF-8');
        } else {
            throw new InvalidArgumentException("Unsupported method '$method', please use the Apache\Solr\Service::METHOD_* constants");
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

        $cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getInstance(CACHE_DB);
        $num_searches = $cache->get('DIQA.EnhancedRetrieval.num_searches');
        $num_searches = $num_searches === false ? 0 : $num_searches;
        $cache->set('DIQA.EnhancedRetrieval.num_searches', ++$num_searches);

        if ($numFound > 0) {
            $num_search_hits = $cache->get('DIQA.EnhancedRetrieval.num_search_hits');
            $num_search_hits = $num_search_hits === false ? 0 : $num_search_hits;
            $cache->set('DIQA.EnhancedRetrieval.num_search_hits', ++$num_search_hits);
        }
    }

    private function getUserGroups() {
        $user = RequestContext::getMain()->getUser();
        $userGroups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserGroups( $user );
        // every user is treated as being a member of "user"
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
        return str_replace(' ', '%20', $modifiedQuery);
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
}
