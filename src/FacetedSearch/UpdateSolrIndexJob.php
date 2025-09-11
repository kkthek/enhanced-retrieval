<?php

namespace DIQA\FacetedSearch;

use Exception;
use Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\Services\ServicesFactory;
use Throwable;
use WikiPage;

/**
 * Asynchronous update for SOLR index.
 * Updates a page in SOLR and/or also (indirectly) updates pages referring to this page
 * (via semantic properties)
 */
class UpdateSolrIndexJob extends Job {

    // number of referring pages needed for creating additional update jobs (and batch size)
    const referrerThreshold = 100;

    // LoggerUtils, but only if it exists
    private $logger;

    /**
     * @param Title $title
     * @param array $params incl. keys: 'children' (array of Title) and 'recursive' (bool)
     */
    function __construct( Title $title, array $params = []) {
        parent::__construct( 'UpdateSolrIndexJob',
            [
                'title' => $title->getDBkey(),
                'namespace' => $title->getNamespace(),
                'children' => $params['children'] ?? [],
                'recursive' => $params['recursive'] ?? true
            ] );
        $this->removeDuplicates = true;

        if (class_exists('ODB\Core\Utils\LoggerUtils')) {
            $this->logger = new \ODB\Core\Utils\LoggerUtils( 'UpdateSolrIndexJob', 'EnhancedRetrieval' );
        }
    }

    /**
     * {@inheritDoc}
     * @see Job::getDeduplicationInfo()
     */
    public function getDeduplicationInfo() {
        $info = parent::getDeduplicationInfo();
        if (isset($info['params'])) {
            // timestamp not relevant for duplicate detection
            unset($info['params']['timestamp']);
        }
        return $info;
    }

    /**
     * {@inheritDoc}
     * @see Job::run()
     */
    public function run() {
        $title = $this->getTitle();

        // when indexing with jobs, we must ensure not to create new updating jobs
        // TODO do we really still need this, the design is fishy
        global $fsCreateUpdateJob;
        $fsCreateUpdateJob = false;

        $recursive = $this->getParams()['recursive'];
        $children = $this->getParams()['children'];
        if( $children  ) {
            $num = count($children);
            $this->logMessage( "Indexing $num pages referring to '$title'." );;

            $referringPages = [];

            foreach ($children as $child) {
                $this->updateSinglePage( $child );
                $referringPages = $recursive ? ($referringPages + $this->findReferringPages( $child )) : [];
            }
            $referringPages = array_unique($referringPages);

        } else {
            $this->logMessage( "Indexing 1 page: $title" );
            $this->updateSinglePage( $title );
            $referringPages = $recursive ? $this->findReferringPages( $title ) : [];
        }

        $this->updatePages( $referringPages );
    }

    private function findReferringPages($title): array {
        $referringPages = [];
        $targetPage = DIWikiPage::newFromTitle($title);
        $store = ServicesFactory::getInstance()->getStore();
        $inProperties = $store->getInProperties($targetPage);

        foreach ($inProperties as $inProperty) {
            /** @var DIProperty $inProperty */
            $subjects = $store->getPropertySubjects($inProperty, $targetPage);
            foreach ($subjects as $subj) {
                $referringPages[] = $subj->getTitle();
            }
        }

        // TODO: we could also add pages that point to $title via normal Wiki links to the list of $referringPages

        // Remove duplicate titles. This works because of Title::__toString()
        return array_unique( $referringPages );
    }

    private function updateSinglePage(Title $title ) {
        try {
            $indexer = FSIndexerFactory::create();
        } catch (Exception $e) {
            $this->logError("Could not create indexer for indexing '$title'.", $e);
            return;
        }

        try {
            $wp = new WikiPage($title);
            $messages = [];
            $indexer->updateIndexForArticle($wp, null, $messages);
            if ( $messages ) {
                $e = new Exception( implode(", ", $messages ) );
                $this->logError( "Errors while updating SOLR index for '$title'.", $e );
            }
        } catch (Throwable $e) {
            $this->logError("Could not index '$title'.", $e);
        }
    }

    private function updatePages( array $pages ) {
        $numberOfPages = count( $pages );
        if( $numberOfPages === 0 ) {
            return;
        }

        $title = $this->getTitle();

        if( $numberOfPages <= self::referrerThreshold ) {
            $this->logMessage( "Found $numberOfPages referring pages for '$title'. Updating them now." );
            foreach ($pages as $page) {
                $this->updateSinglePage( $page );
            }
        } else {
            $this->logMessage( "Found $numberOfPages referring pages for '$title'. Creating UpdateSolrIndexJob batches for them." );
            $batches = array_chunk( $pages, self::referrerThreshold );
            $jobs = [];
            foreach ($batches as $batch) {
                $jobs[] = new UpdateSolrIndexJob( $title, ['children'=>$batch, 'recursive'=>false] );
            }
            MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup()->lazyPush( $jobs );
        }
    }

    private function logError(String $message, Throwable $e) {
        if ( $this->logger ) {
            $this->logger->error($message, $e);
            return;
        }

        $this->logLocalMessage( "ERROR\t$message\t" . $e->getMessage() );
    }

    private function logMessage(String $message) {
        if ( $this->logger ) {
            $this->logger->log($message);
            return;
        }

        $this->logLocalMessage( "     \t$message" );
    }

    private function logLocalMessage(String $message) {
        $consoleMode = PHP_SAPI === 'cli' && !defined('UNITTEST_MODE');
        if ($consoleMode) {
            $fullMessage = date('H:i:s') . "\tUpdateSolrIndexJob - $message\n";
            echo( $fullMessage );
        }
    }
}
