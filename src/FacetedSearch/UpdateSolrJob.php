<?php

namespace DIQA\FacetedSearch;

use Job;
use WikiPage;
use Title;

/**
 * Asynchronous updates for SOLR
 * 
 * @author Kai
 *
 */
class UpdateSolrJob extends Job {

	/**
	 * @param Title $title
	 * @param array $params job parameters (timestamp)
	 */
	function __construct( $title, $params ) {
		parent::__construct( 'UpdateSolrJob', $title, $params );
	}

	/**
	 * implementation of the actual job
	 *
	 * {@inheritDoc}
	 * @see Job::run()
	 */
	public function run() {
	    
	    $consoleMode = PHP_SAPI === 'cli' && !defined('UNITTEST_MODE');
		$title = $this->params['title'];
		$wp = new WikiPage(Title::newFromText($title));
		
        // when indexing with jobs, dependent pages do not need special treatment, because jobs already represent the dependent pages
   		global $fsUpdateOnlyCurrentArticle;
		$fsUpdateOnlyCurrentArticle = true;
		
		$indexer = FSIndexerFactory::create();
		try {
		    $messages = [];
			$indexer->updateIndexForArticle($wp, null, null, $messages );
			if ($consoleMode && count($messages) > 0) {
			    print implode("\t\n", $messages);
			}
		} catch(\Exception $e) {
			if ( $consoleMode ) {
				print sprintf("\tnot indexed, reason: %s \n", $e->getMessage());
			}
		}
		if ( $consoleMode ) {
			echo "Updated (SOLR): $title";
		}
		
	}
}
