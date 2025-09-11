<?php
namespace DIQA\FacetedSearch\Maintenance;

use DIQA\FacetedSearch\FSIndexerFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Throwable;
use WikiPage;

/**
 * Updates the solr index.
 *
 * @ingroup EnhancedRetrieval
 */
require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class UpdateSolr extends Maintenance
{

    private $linkCache;
    private $writeToStartidfile;
    private $num_files = 0;

    public function __construct()
    {
        parent::__construct();
        $this->addDescription( "Updates SOLR index" );
        $this->addOption('v', 'Verbose mode', false, false);
        $this->addOption('g', 'Get the maximum ID of pages that would be updated (all other parameters are ignored if this is present)', false, false);
        $this->addOption('d', 'Delay every 100 pages (milliseconds)', false, true);
        $this->addOption('x', 'Debug mode', false, false);
        $this->addOption('p', 'Page title(s), separated by ","', false, true);
        $this->addOption('s', 'Start-ID', false, true);
        $this->addOption('e', 'End-ID', false, true);
        $this->addOption('n', 'Number of IDs from Start-ID', false, true);
        $this->addOption('f', 'End-ID by page name', false, true);
        $this->addOption('startidfile', 'File containing ID to start processing and saves last processed ID to this file', false, true);
    }

    public function execute()
    {
        if( !defined( 'ER_EXTENSION_VERSION' ) ) {
            echo("ERROR: The enhanced retrieval extension is not properly installed or configured.\n");
            die(1);
        }

        if( $this->hasOption('g') ) {
            $max = $this->getMaxId();
            print "$max\n";
            return;
        }

        // when indexing everything, we don't create any updating job for SOLR
        global $fsCreateUpdateJob;
        $fsCreateUpdateJob = false;

        $this->linkCache = MediaWikiServices::getInstance()->getLinkCache();
        $this->num_files = 0;
        $this->printDocHeader();

        if (!$this->hasOption('p')) {
            $startId = $this->getStartId();
            $endId = $this->getEndId($startId);
            $this->refreshPagesByIds($startId, $endId);
        } else {
            $pages = explode(',', $this->getOption('p'));
            $this->refreshPages($pages);
        }

        print "{$this->num_files} IDs refreshed.\n";
    }

    /**
     * Print Documentation header
     */
    private function printDocHeader() {
        print "Refreshing all semantic data in the SOLR server!\n---\n" .
            " Some versions of PHP suffer from memory leaks in long-running scripts.\n" .
            " If your machine gets very slow after many pages (typically more than\n" .
            " 1000) were refreshed, please abort with CTRL-C and resume this script\n" .
            " at the last processed page id using the parameter -s (use -v to display\n" .
            " page ids during refresh). \n\n[Use -x for debugging information]\n\n" .
            "Continue this until all pages were refreshed.\n---\n";

    }

    /**
     * Refresh all pages from ID start to ID end
     * Writes last processed ID to a file if option 'startidfile' is set.
     *
     * @param int $start
     * @param int $end
     */
    private function refreshPagesByIds($start, $end)
    {
        print "Processing all IDs from $start to " . ($end ? "$end" : 'last ID') . " ...\n";

        $id = $start;
        while (((! $end) || ($id <= $end)) && ($id > 0)) {
            $title = Title::newFromID($id);
            if ($this->hasOption('v')) {
                print sprintf("(%s) Processing ID %s ... [%s]\n",
                    $this->num_files, $id, ! is_null($title) ? $title->getPrefixedText() : "-");
            }
            $id ++;
            if (is_null($title)) {
                continue;
            }

            $this->updateIndex($title);

            if (($this->hasOption('d')) && (($this->num_files + 1) % 100 === 0)) {
                usleep($this->getOption('d'));
            }
            $this->num_files ++;
            $this->linkCache->clear(); // avoid memory leaks

            if ($this->writeToStartidfile) {
                file_put_contents($this->getOption('startidfile'), "$id");
            }
        }

    }

    /**
     * Refresh given pages.
     *
     * @param array $pages of string Page titles
     */
    private function refreshPages($pages)
    {
        print "Refreshing specified pages!\n\n";

        foreach ($pages as $page) {

            $page = trim($page);
            if ($this->getOption('v')) {
                print sprintf("(%s) Processing page %s ... \n", $this->num_files, $page);
            }

            $title = Title::newFromText($page);

            if (! is_null($title)) {
                $this->updateIndex($title);
            }

            $this->num_files ++;
        }

    }

    /**
     * Update SOLR index of $title.
     *
     * @param Title $title
     */
    private function updateIndex($title) {
        $indexer = FSIndexerFactory::create();
        try {
            $messages = [];
            $indexer->updateIndexForArticle(new WikiPage($title), null, $messages, $this->hasOption('x'));
            if (count($messages) > 0) {
                print implode("\t\n", $messages);
            }
        } catch (Throwable $e) {
            print sprintf("\t[NOT INDEXED] [HTTP code %s]\n", $e->getCode());
            if ($this->hasOption('x')) {
                print sprintf("\t[NOT INDEXED] %s\n", $e->getMessage());
                print sprintf("%s\n", $e->getTraceAsString());
                print "---------------------------------------------------------\n";
            }
        }
    }

    /**
     * Calculates startID of MW-page
     * Reads ID from a file if option 'startidfile' is specified.
     *
     * @return int
     */
    private function getStartId()
    {
        $this->writeToStartidfile = false;
        if ($this->hasOption('s')) {
            $start = max(1, intval($this->getOption('s')));
        } elseif ($this->hasOption('f')) {
            $title = Title::newFromText($this->getOption('f'));
            $start = $title->getArticleID();
        } elseif ($this->hasOption('startidfile')) {
            if (! is_writable(file_exists($this->getOption('startidfile')) ? $this->getOption('startidfile') : dirname($this->getOption('startidfile')))) {
                die("Cannot use a startidfile that we can't write to.\n");
            }
            $this->writeToStartidfile = true;
            if (is_readable($this->getOption('startidfile'))) {
                $start = max(1, intval(file_get_contents($this->getOption('startidfile'))));
            } else {
                $start = 1;
            }
        } else {
            $start = 1;
        }
        return $start;
    }

    /**
     * Calculates endID of MW-page
     *
     * @param int $start Start-ID
     *
     * @return int
     */
    private function getEndId($start)
    {
        if ($this->hasOption('e')) { 
            // Note: this might reasonably be larger than the page count
            $end = intval($this->getOption('e'));

        } elseif ($this->hasOption('n')) {
            $end = $start + intval($this->getOption('n'));

        } elseif ($this->hasOption('f')) {
            $title = Title::newFromText($this->getOption('f'));
            $end = $title->getArticleID();

        } else {
            $end = $this->getMaxId();
        }
        return $end;
    }

    private function getMaxId() {
        $db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
        $row = $db->newSelectQueryBuilder()
                ->fields( ['maxId' => 'MAX(page_id)'] )
                ->table( 'page' )
                ->caller( __METHOD__ )
                ->fetchRow();
        if( $row ) {
            return $row->maxId;
        }
        return 0;
    }
}

$maintClass = UpdateSolr::class;
require_once RUN_MAINTENANCE_IF_MAIN;
