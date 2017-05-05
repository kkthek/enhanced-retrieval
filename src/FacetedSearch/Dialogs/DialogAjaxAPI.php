<?php
namespace DIQA\FacetedSearch\Dialogs;

use Philo\Blade\Blade;
use DIQA\FacetedSearch\FacetedSearchUtil;


class DialogAjaxAPI extends \ApiBase {
	
	private $blade;
	
	public function __construct($query, $moduleName) {
		parent::__construct ( $query, $moduleName );
		$views = __DIR__ . '/../../../views';
		$cache = __DIR__ . '/../../../cache';
		
		$this->blade = new Blade ( $views, $cache );
	}
	
	public function execute() {
		$params = $this->extractRequestParams ();
		
		switch ($params ['method']) {
			
			case 'getSelectFacetValueDialog' :
				$this->getSelectFacetValueDialog ( $params );
				break;
		}
	}
	
	
	private function getSelectFacetValueDialog($params) {
		global $wgServer, $wgScriptPath;
		
		$distinctPropertyValues = FacetedSearchUtil::getDistinctPropertyValues($params ['property']);
		usort($distinctPropertyValues, function($e1, $e2) { 
			return strcmp(strtolower($e1['label']), strtolower($e2['label']));
		});
		
		$html = $this->blade->view ()->make ( "dialogs.facet-value-dialog", 
				array ('values' => $distinctPropertyValues,
					   'facetName' => $params ['property'])
		 )->render ();
		
		$htmlResult = ['html'=>$html];
		$result = $this->getResult ();
		$result->setIndexedTagName ( $htmlResult, 'p' );
		$result->addValue ( null, $this->getModuleName (), $htmlResult );
		
	}
	
	protected function getAllowedParams() {
		return array (
				'method' => null, 
				'property' => null,
				
		);
	}
	protected function getParamDescription() {
		return array (
				'method' => 'Method name', 
				
		);
	}
	protected function getDescription() {
		return 'DialogAjaxAPI';
	}
	protected function getExamples() {
		return array (
				'api.php?action=odbgeojson&method=getSelectFacetValueDialog' 
		);
	}
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}