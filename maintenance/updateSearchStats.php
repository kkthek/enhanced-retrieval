<?php

/**
 * UpdateSearchStats DIQAimport
 *
 * @ingroup DIQA Import
 */
require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class UpdateSearchStats extends Maintenance {
	
	public function __construct() {
		parent::__construct ();
		$this->mDescription = "UpdateSearchStats DIQAimport";
		$this->addOption( 'searches', 'Searches', false, false );
		$this->addOption( 'searchHits', 'Search hits', false, false );
	}
	
	public function execute() {
		$cache = \ObjectCache::getInstance(CACHE_DB);
		
		if ($this->hasOption('searches')) {
			$num_searches = $cache->get('DIQA.EnhancedRetrieval.num_searches');
			$num_searches = $num_searches === false ? 0 : $num_searches;
			$cache->set('DIQA.EnhancedRetrieval.num_searches', ++$num_searches);
		}
		
		if ($this->hasOption('searchHits')) {
			$num_search_hits = $cache->get('DIQA.EnhancedRetrieval.num_search_hits');
			$num_search_hits = $num_search_hits === false ? 0 : $num_search_hits;
			$cache->set('DIQA.EnhancedRetrieval.num_search_hits', ++$num_search_hits);
		}
	}
}

$maintClass = "UpdateSearchStats";
require_once RUN_MAINTENANCE_IF_MAIN;
