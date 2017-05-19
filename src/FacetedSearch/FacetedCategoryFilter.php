<?php

namespace DIQA\FacetedSearch;

class FacetedCategoryFilter {
	
	/**
	 * Adds an additional filter element to FacetedSearch
	 * 
	 * @param string $extendedFilters HTML to add
	 * @return boolean
	 */
	public static function addFilter(& $extendedFilters) {
		
		global $fsgCategoryFilter;
		
		$extendedFilters = '';
		if (!isset($fsgCategoryFilter) || count($fsgCategoryFilter) == 0) {
			return true;
		}
		
		global $wgContLang;
		$categoryLabel = $wgContLang->getNsText(NS_CATEGORY);
		
		$html = '<span id="fs_category_filter_label">'.$categoryLabel.': </span><select id="fs_category_filter" name="fs_category_filter">';
		$html .= '<option value="" selected="true">Alle Wikiseiten</option>';
		foreach ( $fsgCategoryFilter as $cat => $label ) {
			$html .= sprintf ( '<option value="%s">%s</option>', $cat, $label );
		}
		$html .= "</select>";
				
		$extendedFilters = $html;
		
		return true;
	}
	
}