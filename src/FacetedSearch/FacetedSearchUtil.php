<?php
namespace DIQA\FacetedSearch;

use PageProps;
use Title;
use WikiPage;

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

        if ($db->numRows ( $res ) > 0) {
            while ( $row = $db->fetchObject ( $res ) ) {
                if ($row->ns_value == -1) {
                    $results [] = [ 'id' => $row->p_value, 'label' => is_null($row->blob_value) ? $row->p_value : $row->blob_value ];
                } else {
                    $title = Title::newFromText($row->p_value, $row->ns_value);
                    if (is_null($title)) {
                        continue;
                    }
                    $displayTitle = FacetedSearchUtil::findDisplayTitle($title);
                    $results[] = ['id' => $title->getPrefixedText(), 'label' => $displayTitle ];
                }
            }
        }
        $db->freeResult ( $res );

        return $results;
    }

    /**
     * Retrieves the display title from the properties tabel for the given page.
     * It will probably only properly work if the DisplayTitles extension is installed and used.
     * The default value is the pagename.
     * 
     * This code is inspired by getDisplayTitle() from DisplayTitle\includes\DisplayTitleHooks.php
     * 
     * @param Title $title
     * @param WikiPage $wikipage (optional) if present redirects will be followed
     * @return string smwh_displaytitle
     */
    public static function findDisplayTitle(Title $title, WikiPage $wikipage = null) {
        $title = $title->createFragmentTarget( '' );
        $originalPageName = $title->getPrefixedText();
        
        $redirect = false;
        if($wikipage) {
            $redirectTarget = $wikipage->getRedirectTarget();
            if ( !is_null( $redirectTarget ) ) {
                $redirect = true;
                $title = $redirectTarget;
            }
        }
        
        $id = $title->getArticleID();
        $values = PageProps::getInstance()->getProperties( $title, 'displaytitle' );
        if ( array_key_exists( $id, $values ) ) {
            $value = $values[$id];
            if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' ) {
                return $value;
            }
        } elseif ( $redirect ) {
            return  $title->getPrefixedText();
        }
        return $originalPageName;
    }

}