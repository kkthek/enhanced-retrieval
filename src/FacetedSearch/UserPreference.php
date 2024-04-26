<?php

namespace DIQA\FacetedSearch;

use MediaWiki\MediaWikiServices;

class UserPreference
{
    public static $defaultSortOrder = "sort-by-count";

    static public function setupPreferences($user, &$preferences)
    {
        $options = [];

        $options[wfMessage('sort-by-count')->text()] = "sort-by-count";
        $options[wfMessage('sort-alphabetically')->text()] = "sort-alphabetically";

        $preferences ['er-sort-order-preferences'] = array(
            'type' => 'radio',
            'label' => '&#160;',
            'label-message' => 'prefs-Standard-Sortierung-Facetten', // a system message
            'section' => 'enhanced-retrieval',
            'options' => $options,
            'help-message' => 'Suchoptionen'  // a system message (optional)
        );

        $option = MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption(
            $user, 'er-sort-order-preferences', null);
        if (is_null($option)) {
            $preferences ['er-sort-order-preferences'] ['default'] = self::$defaultSortOrder;
        }

        return true;
    }
}