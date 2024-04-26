<?php

namespace DIQA\FacetedSearch;

use Exception;
use Job;
use WikiPage;
use Title;

/**
 * Asynchronous updates for SOLR
 * @deprecated remains for now to keep pending jobs in queue working
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
		$this->removeDuplicates = true;
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

        // when indexing with jobs, we must assure not to create new updating jobs
		global $fsCreateUpdateJob;
		$fsCreateUpdateJob = false;

		try {
			$indexer = FSIndexerFactory::create();
			$messages = [];
			$indexer->updateIndexForArticle( $wp, null, $messages );
			if ($consoleMode && count($messages) > 0) {
				print implode("\t\n", $messages);
			}
		} catch(Exception $e) {
			if ( $consoleMode ) {
				print sprintf("\tnot indexed, reason: %s \n", $e->getMessage());
			}
		}
		if ( $consoleMode ) {
			echo "Updated (SOLR): $title\n";
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see Job::getDeduplicationInfo()
	 */
	public function getDeduplicationInfo() {
		$info = parent::getDeduplicationInfo();
		if ( isset( $info['params']) ) {
			// timestamp not relevant for duplicate detection
			unset( $info['params']['timestamp'] );
		}
		return $info;
	}
}
