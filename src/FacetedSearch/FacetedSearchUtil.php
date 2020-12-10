<?php
namespace DIQA\FacetedSearch;

use SMW\ApplicationFactory;
use SMWDIProperty;
use SMWDIWikiPage;
use Title;

class FacetedSearchUtil {

	/**
	 * Returns all *distinct* values of a given property.
	 * @param string $property
	 *
	 * @return array of string
	 */
	public static function getDistinctPropertyValues($property) {
		$db = wfGetDB ( DB_REPLICA  );

		$p_id = smwfGetStore ()->smwIds->getSMWPageID ( $property, SMW_NS_PROPERTY, "", "" );


		$smw_ids = $db->tableName ( 'smw_object_ids' );
		$smw_atts2 = $db->tableName ( 'smw_di_blob' );
		$smw_inst2 = $db->tableName ( 'smw_fpt_inst' );
		$smw_rels2 = $db->tableName ( 'smw_di_wikipage' );

		// get attribute and relations values
		$att_query = "SELECT DISTINCT a.o_hash AS p_value, a.o_blob AS blob_value, -1 AS ns_value
		FROM $smw_atts2 a
		JOIN $smw_ids s ON a.s_id = s.smw_id
		WHERE a.p_id = $p_id";

		$rel_query = "SELECT DISTINCT o.smw_title AS p_value, '' AS blob_value, o.smw_namespace AS ns_value
		FROM $smw_rels2 r
		JOIN $smw_ids s ON r.s_id = s.smw_id
		JOIN $smw_ids o ON r.o_id = o.smw_id

		WHERE r.p_id = $p_id";

		$res = $db->query ( "($att_query) UNION ($rel_query) LIMIT 500" );
		// rewrite result as array
		$results = array ();

		global $fsgTitleProperty;
		$titleProperty = SMWDIProperty::newFromUserLabel($fsgTitleProperty);
		if ($db->numRows ( $res ) > 0) {
			while ( $row = $db->fetchObject ( $res ) ) {
				if ($row->ns_value == -1) {
					$results [] = [ 'id' => $row->p_value, 'label' => is_null($row->blob_value) ? $row->p_value : $row->blob_value ];
				} else {
					$title = Title::newFromText($row->p_value, $row->ns_value);
					if (is_null($title)) {
						continue;
					}
					$subject = SMWDIWikiPage::newFromTitle($title);
					$displayTitleObject = ApplicationFactory::getInstance()->getStore()->getPropertyValues($subject, $titleProperty);
					if (count($displayTitleObject) > 0) {
						$displayTitleObject = reset($displayTitleObject);
						$displayTitle = $displayTitleObject->getString();
					} else {
						$displayTitle = $title->getPrefixedText();
					}
					$results[] = ['id' => $title->getPrefixedText(), 'label' => $displayTitle ];
				}
			}
		}
		$db->freeResult ( $res );

		return $results;
	}

}