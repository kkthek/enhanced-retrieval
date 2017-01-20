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
		$title = $this->params['title'];
		$wp = new WikiPage(Title::newFromText($title));
		
        // when indexing with jobs, dependent pages do not need special treatment, because jobs already represent the dependent pages
   		global $fsUpdateOnlyCurrentArticle;
		$fsUpdateOnlyCurrentArticle = true;
		
		$indexer = FSIndexerFactory::create();
		$indexer->updateIndexForArticle($wp);
		
		if ( PHP_SAPI === 'cli' && !defined('UNITTEST_MODE')) {
			echo "Updated (SOLR): $title";
		}
		
	}
}
