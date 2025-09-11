<?php
namespace DIQA\FacetedSearch;

use MediaWiki\MediaWikiServices;

class FacetedCategoryFilter {

    /**
     * Adds an additional filter element to FacetedSearch
     *
     * @param string $extendedFilters HTML to add (this will carry the return value)
     * @return boolean
     */
    public static function addFilter(&$extendedFilters) {
        global $fsgCategoryFilter;

        if (!isset($fsgCategoryFilter) || count($fsgCategoryFilter) == 0) {
            $extendedFilters = '';
            return true;
        }

        $wgContLang = MediaWikiServices::getInstance()->getContentLanguage();
        $categoryLabel = $wgContLang->getNsText(NS_CATEGORY);
        $html = "<span id='fs_category_filter_label'>$categoryLabel: </span><br/>";

        $hasAllCategory = false;
        $selected = ' selected="true"'; // the first entry is selected

        $html .= "<select id='fs_category_filter' name='fs_category_filter'>";
        foreach ( $fsgCategoryFilter as $cat => $label ) {
            $html .= "<option value='$cat'$selected>$label</option>";
            $selected = ''; // only the first entry is selected
            if($cat == '') {
                $hasAllCategory = true;
            }
        }
        if( !$hasAllCategory ) {
            $allPages = wfMessage('fs_all_pages')->text();
            $html .= "<option value=''$selected>$allPages</option>";
        }
        $html .= "</select>";

        $extendedFilters = $html;

        return true;
    }
}