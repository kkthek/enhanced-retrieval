<?php

namespace DIQA\FacetedSearch;

use DIQA\FacetedSearch\Proxy\SolrProxy\SolrService;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Response;


/**
 * solr proxy REST endpoint. This is where SOLR requests are processed
 */
class ProxyRestEndpoint extends Handler
{

    public function execute()
    {

        global $SOLRhost;
        global $SOLRport;
        global $SOLRcore;
        global $SOLRuser;
        global $SOLRpass;
        global $fsgUseStatistics;

        $query = $this->getRequest()->getUri()->getQuery();
        // create a new solr service instance with the configured settings
        $core = $SOLRcore == '' ? '/solr/' : "/solr/$SOLRcore/";
        try {
            $solr = new SolrService($SOLRhost, $SOLRport, $core, false, "$SOLRuser:$SOLRpass");

            // if magic quotes is enabled then stripslashes will be needed
            if (get_magic_quotes_gpc() == 1) {
                $query = stripslashes($query);
            }

            $results = $solr->rawsearch($query, SolrService::METHOD_POST);
            $response = $results->getRawResponse();

            if (isset($fsgUseStatistics) && $fsgUseStatistics === true) {
                $solr->updateSearchStats($response);
            }
        } catch (\Apache_Solr_HttpTransportException $e) {
            $httpStatus = $e->getResponse()->getHttpStatus() == 0 ? 500 : $e->getResponse()->getHttpStatus();
            return $this->getResponseFactory()->createHttpError($httpStatus,
                ["<h1 style='color:red;'>ERROR</h1>\n",
                    "<br>Accessing SOLR: $SOLRhost:$SOLRport{$core}select?$query\n",
                    "<br>Error message from SOLR-proxy: <b>" . $e->getMessage() . "</b>\n",
                    "<br>Please make sure that SOLR proxy is configured. ".
                    "<br>You'll find documentation at extensions/EnhancedRetrieval/INSTALL\n"]);
        }

        return new Response($response);
    }

}