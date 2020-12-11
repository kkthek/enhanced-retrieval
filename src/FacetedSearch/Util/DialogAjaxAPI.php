<?php
namespace DIQA\FacetedSearch\Util;

use ApiBase;
use DIQA\FacetedSearch\FacetedSearchUtil;
use Philo\Blade\Blade;
use Title;

class DialogAjaxAPI extends ApiBase {

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

		$propertyTitle = Title::newFromText($params ['property'], SMW_NS_PROPERTY);

		$html = $this->blade->view ()->make ( "dialogs.facet-value-dialog",
				array ('values' => $distinctPropertyValues,
					   'facetName' => $propertyTitle->getText())
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
				'method' => 'Method name, currently only "getSelectFacetValueDialog" exists',
				'property' => 'name of the semantic property to show in the dialog'
		);
	}

	protected function getDescription() {
		return 'DialogAjaxAPI for faceted search';
	}

	protected function getExamples() {
		return array (
                'api.php?action=fs_dialogapi&method=getSelectFacetValueDialog&property=Gemeinde&format=json'
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}