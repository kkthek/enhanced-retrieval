<?php
namespace DIQA\FacetedSearch;

use Apache\Solr\HttpTransportException;
use Apache\Solr\Service;
use DIQA\FacetedSearch\SolrProxy\SolrService;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Response;

/**
 * SOLR proxy REST endpoint. This is where SOLR requests are processed
 */
class ProxyRestEndpoint extends Handler
{

    public function execute()
    {

        global $fsgSolrHost;
        global $fsgSolrPort;
        global $fsgSolrCore;
        global $fsgSolrUser;
        global $fsgSolrPass;
        global $fsgUseStatistics;

        $query = $this->getRequest()->getUri()->getQuery();
        // create a new solr service instance with the configured settings
        $core = $fsgSolrCore == '' ? '/solr/' : "/solr/$fsgSolrCore/";
        try {
            $solr = new SolrService($fsgSolrHost, $fsgSolrPort, $core, false, "$fsgSolrUser:$fsgSolrPass");

            $results = $solr->rawsearch($query, Service::METHOD_POST);
            $response = $results->getRawResponse();

            if (isset($fsgUseStatistics) && $fsgUseStatistics === true) {
                $solr->updateSearchStats($response);
            }
        } catch (HttpTransportException $e) {
            $httpStatus = $e->getResponse()->getHttpStatus() == 0 ? 500 : $e->getResponse()->getHttpStatus();
            return $this->getResponseFactory()->createHttpError($httpStatus,
                ["<h1 style='color:red;'>ERROR</h1>\n",
                    "<br>Accessing SOLR: $fsgSolrHost:$fsgSolrPort{$core}select?$query\n",
                    "<br>Error message from SOLR-proxy: <b>" . $e->getMessage() . "</b>\n",
                    "<br>Please make sure that SOLR proxy is configured. ".
                    "<br>You'll find documentation at extensions/EnhancedRetrieval/INSTALL\n"]);
        }

        return new Response($response);
    }

}