<?php
namespace DIQA\FacetedSearch\Util;

use eftec\bladeone\BladeOne;
use MediaWiki\Api\ApiBase;
use MediaWiki\MediaWikiServices;

class DialogAjaxAPI extends ApiBase {

	private BladeOne $blade;

	public function __construct($query, $moduleName) {
		parent::__construct ( $query, $moduleName );
		$views = __DIR__ . '/../../../views';
		$cache = __DIR__ . '/../../../cache';

		$this->blade = new BladeOne( $views, $cache );
	}

	public function execute() {
		$params = $this->extractRequestParams ();

        global $fsgFacetsDialogWithCustomContent;
        $customContent = in_array($params['property'], $fsgFacetsDialogWithCustomContent) ?? false;

		switch ($params ['method']) {
			case 'getSelectFacetValueDialog' :
			    if ($customContent !== false) {
			        $this->getSelectFacetValueFromCustomContent($params);
                } else {
                    $this->getSelectFacetValueDialog($params);
                }
				break;
		}
	}

	private function getSelectFacetValueDialog($params) {

		$facetValues = new FacetValueGenerator($params ['property']);

		$html = $this->blade->run ( "dialogs.facet-value-dialog",
				array ('values' => $facetValues->getFacetData(),
					   'toRemove' => json_encode($facetValues->getFacetsToRemove()),
					   'facetName' => $params ['property'])
		 );

		$htmlResult = ['html'=>$html];
		$result = $this->getResult ();
		$result->setIndexedTagName ( $htmlResult, 'p' );
		$result->addValue ( null, $this->getModuleName (), $htmlResult );
	}

    private function getSelectFacetValueFromCustomContent($params) {

        $content = [];
        $hookContainer = MediaWikiServices::getInstance()->getHookContainer();
        $hookContainer->run('fsgCustomFacetDialogContent', [ $params ['property'], & $content ]);

        $html = $this->blade->run( "dialogs.facet-custom-dialog",
            [
                'content' => $content[$params ['property']] ?? '',
                'facetName' => $params ['property']
            ]
        );

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