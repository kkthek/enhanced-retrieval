<?php

namespace DIQA\FacetedSearch\Util;

/**
 * Provides user data about the requesting user.
 *
 * Currently contains user_groups
 * Called via /api.php?action=fs_userdataapi&format=json
 *
 * Note: This is essentially a copy of DIQA\Util\Api\UserDataAPI.php
 */
class UserDataAPI extends \ApiBase {
    public function __construct($query, $moduleName) {
        parent::__construct ( $query, $moduleName );
    }

    public function isReadMode() {
        return true;
    }

    public function execute() {
        $params = $this->extractRequestParams ();
        global $wgUser;

		$o = new \stdClass();
		$o->user_groups = $wgUser->getGroups();

        // Set top-level elements.
        $result = $this->getResult ();
        $result->addValue ( null, 'result', $o );
    }

    protected function getAllowedParams() {
        return array ();
    }

    protected function getParamDescription() {
        return array ();
    }

    protected function getDescription() {
        return 'UserData (DIQA-PM.COM) from EnhancedRetrieval';
    }

    protected function getExamples() {
        return array (
                'api.php?action=odb_userdataapi&format=json'
        );
    }

    public function getVersion() {
        return __CLASS__ . ': $Id$';
    }

}
