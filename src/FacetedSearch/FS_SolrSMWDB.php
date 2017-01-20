<?php
namespace DIQA\FacetedSearch;

use SMW\ApplicationFactory;
use WikiPage;
use Article;
use ParserOptions;
use Sanitizer;
use Title;
use SMW\DIProperty as SMWDIProperty;
use SMW\DIWikiPage as SMWDIWikiPage;
use SMWDataItem;
use JobQueueGroup;

/*
 * Copyright (C) Vulcan Inc., DIQA-Projektmanagement GmbH
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * @file
 * @ingroup FacetedSearch
 *
 * This file contains the class FSSolrSMWDB. It creates the index from the database
 * tables of SMW.
 *
 * @author Thomas Schweitzer
 * @author Kai Kühn 
 * Date: 22.02.2011
 *
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}

/**
 * This class is the indexer for the SMW database tables.
 *
 * @author thsc
 *
 */
class FSSolrSMWDB extends FSSolrIndexer {

	// --- Constants ---

	/**
	 * Maximal number of synchronous updates during a request
	 * @var int
	 */
	const MAX_SYNC_UPDATES = 10;

 	//--- Private fields ---

	/**
	 * Dependant articles which must be updated too
	 * @var array
	 */
	private $dependant = [];
	
	/**
	 * Creates a new FSSolrSMWDB indexer object.
	 * @param string $host
	 * 		Name or IP address of the host of the server
	 * @param int $port
	 * 		Server port of the Solr server
	 */
	public function __construct($host, $port) {
		parent::__construct($host, $port);
	}

	/**
	 * Updates the index for the given $article.
	 * It retrieves all semantic data of the new version and adds it to the index.
	 *
	 * @param Article $article
	 * 		The article that changed.
	 * @param User $user
	 * 		Optional user object
	 * @param string $text
	 *		Optional content of the article. If NULL, the content of $article is
	 *		retrieved in this method.
	 */
	public function updateIndexForArticle(WikiPage $wikiPage, $user = NULL, $text = NULL) {
		$doc = array();
		$this->dependant = [];
		
		$db = wfGetDB( DB_SLAVE );

		$article = Article::newFromID($wikiPage->getId());

		// Get the page ID of the article
		$t = $article->getTitle();
		$pid = $t->getArticleID();
		$pns = $t->getNamespace();
		$pt  = $t->getDBkey();

		//if ($text === NULL) {
		//$text = $article->getContent();
		$parserOptions = new ParserOptions();
		$parserOut = $wikiPage->getParserOutput($parserOptions);
		$text = Sanitizer::stripAllTags($parserOut->getText());
		//}

		$doc['id'] = $pid;
		$doc['smwh_namespace_id'] = $pns;
		$doc['smwh_title'] = $pt;
		$doc['smwh_full_text'] = $text;

		$options = array();
		global $fsgDefaultBoost;
		$options['*']['boost'] = $fsgDefaultBoost;
		$options['smwh_title']['boost'] = $fsgDefaultBoost;
		
		global $fsgBlacklistPages;
		if (in_array($t->getPrefixedText(), $fsgBlacklistPages)) {
			return;
		}
		
		global $fsgNamespaceBoosts;
		if (array_key_exists($pns, $fsgNamespaceBoosts)) {
			$this->calculateBoostFactors($options, $fsgNamespaceBoosts[$pns]);
		}
		
		// retrieve templates (currently only needed for boosts)
		$this->retrieveTemplates($db, $pid, $doc, $options);
		
		// Get the categories of the article
		$this->retrieveCategories($db, $pid, $doc, $options);
		if ($this->retrieveSMWID($db, $pns, $pt, $doc)) {
			$smwID = $doc['smwh_smw_id'];
			$this->retrievePropertyValues($db, $pns, $pt, $doc);
		}

		// extract document if a file was uploaded
		if ($pns == NS_FILE) {
			$doc['smwh_full_text'] .= " " . $this->extractDocument($t);
		}
		
		// Let the super class update the index
		$this->updateIndex($doc, $options);
		
	    if($this->updateOnlyCurrentArticle()) {
			return;
		} 
		
    	// update dependant articles
    	if (count($this->dependant) > self::MAX_SYNC_UPDATES) {
    			// if more than MAX_SYNC_UPDATES updates are required, create jobs for it
    			foreach($this->dependant as $ttu) {
    				$params = [];
    				$params['title'] = $ttu->getPrefixedText();
    				$title = Title::makeTitle(NS_SPECIAL, 'Search');
    				$job = new UpdateSolrJob($title, $params);
    				JobQueueGroup::singleton()->push( $job );
    			}
    	} else {
    			// if less than MAX_SYNC_UPDATES, do it synchronously
    			global $fsUpdateOnlyCurrentArticle;
    			$fsUpdateOnlyCurrentArticle = true;
    			foreach($this->dependant as $ttu) {
    				$this->updateIndexForArticle(new WikiPage($ttu));
    			}
    	}
		
	}
	/**
	 * Updates the index for a moved article.
	 *
	 * @param int $oldid
	 * 		Old page ID of the article
	 * @param $newid
	 * 		New page ID of the article
	 * @return bool
	 * 		<true> if the document in the index for the article was moved
	 * 				successfully
	 * 		<false> otherwise
	 */
	public function updateIndexForMovedArticle($oldid, $newid) {
		if ($this->deleteDocument($oldid)) {
			global $wgUser;
			// The article with the new name has the same page id as before
			$article = Article::newFromID($oldid);
			$text = $article->getContent();
			return $this->updateIndexForArticle($article->getPage(), $wgUser, $text);
		}
		return false;
	}

	//--- Private methods ---

	/**
	 * Add general boost factor if it is greater than the old.
	 * 
	 * @param array $options
	 * @param float $value
	 */
	private function calculateBoostFactors(array &$options, $value) {
		$options['*']['boost'] += $value;
		$options['smwh_title']['boost'] *= 3;
	}
	/**
	 * Retrieves the templates of the article with the page ID $pid and calculate
	 * boosting factors for it
	 *
	 * @param Database $db
	 * 		The database object
	 * @param int $pid
	 * 		The page ID.
	 * @param array $doc
	 * 		
	 * @param $options
	 */
	private function retrieveTemplates($db, $pid, array &$doc, array &$options) {
		$templatelinks = $db->tableName('templatelinks');

		$sql = <<<SQL
			SELECT CAST(t.tl_title AS CHAR) template
			FROM $templatelinks t
			WHERE tl_from=$pid
SQL;
		$smwhTemplates = array();
		$res = $db->query($sql);
		if ($db->numRows($res) > 0) {
				
				
			while ($row = $db->fetchObject($res)) {
				$template = $row->template;
				$smwhTemplates[] = str_replace("_", " ", $template);
			}


		}
		$db->freeResult($res);
			

		// add boost according to templates
		global $fsgTemplateBoosts, $fsgDefaultBoost;
		if (count(array_intersect(array_keys($fsgTemplateBoosts), $smwhTemplates)) > 0) {
			// boost factor defined by category
				
			$templates = array_intersect(array_keys($fsgTemplateBoosts), $smwhTemplates);
			$max = 0;
				
				
			foreach($templates as $t) {
				if ($fsgTemplateBoosts[$t] > $max) {
					$max = $fsgTemplateBoosts[$t];
				}
			}
			$this->calculateBoostFactors($options, $max);
		} 
	}
	/**
	 * Retrieves the categories of the article with the page ID $pid and adds
	 * them to the document description $doc.
	 *
	 * @param Database $db
	 * 		The database object
	 * @param int $pid
	 * 		The page ID.
	 * @param array $doc
	 * 		The document description. If the page belongs to categories, an array
	 * 		of names is added with the key 'smwh_categories'.
	 * @param array $options Options for the document fields. 
	 */
	private function retrieveCategories($db, $pid, array &$doc, array &$options) {
		$categorylinks = $db->tableName('categorylinks');

		$sql = <<<SQL
			SELECT CAST(c.cl_to AS CHAR) cat
			FROM $categorylinks c
			WHERE cl_from=$pid
SQL;
		$prop_ignoreasfacet = wfMessage('fs_prop_ignoreasfacet')->text();
		$ignoreAsFacetProp = SMWDIProperty::newFromUserLabel($prop_ignoreasfacet);
		$store = smwfGetStore();
		$res = $db->query($sql);
		$categories = array();
		if ($db->numRows($res) > 0) {
			$doc['smwh_categories'] = array();
			while ($row = $db->fetchObject($res)) {
				$cat = $row->cat;
				
				$cTitle = Title::newFromText($cat, NS_CATEGORY);
				$iafValues = $store->getPropertyValues(SMWDIWikiPage::newFromTitle($cTitle), $ignoreAsFacetProp);
				if (count($iafValues) > 0) {
					continue;
				}
				
				$doc['smwh_categories'][] = $cat;
				$categories[] = str_replace("_", " ", $cat);
				
				//TODO: should be recursive
				$parentCategories = $cTitle->getParentCategories();
				foreach($parentCategories as $parentCat => $childCat) {
					$parentCatTitle = \Title::newFromText($parentCat);
					$doc['smwh_categories'][] = $parentCatTitle->getText();
					$categories[] = str_replace("_", " ", $parentCatTitle->getText());
				}
			}


		}
		$db->freeResult($res);

		// add boost according to categories
		global $fsgCategoryBoosts, $fsgDefaultBoost;
		if (count(array_intersect(array_keys($fsgCategoryBoosts), $categories)) > 0) {
			// boost factor defined by category
			$categories = array_intersect(array_keys($fsgCategoryBoosts), $categories);
			$max = 0;
			foreach($categories as $c) {
				if ($fsgCategoryBoosts[$c] > $max) {
					$max = $fsgCategoryBoosts[$c];
				}
			}
			$this->calculateBoostFactors($options, $max);
		} 
	}

	/**
	 * Encodes special characters in title
	 *
	 * all non-alphanumeric characters below 128 are encoded.
	 * @param {String} $str
	 * @return string
	 */
	public static function encodeTitle($str) {
		if ($str == '') { 
			return '';
		}
		$hex = "";
		$i = 0;
		$str = str_replace("_", "__", $str);
		do {
			$ord = ord($str{$i});
			if (($ord >= 65 && $ord <= 90) || ($ord >= 97 && $ord <= 122) || ($ord >= 48 && $ord <= 57) || $ord == 95) {
				// do not encode alphnumeric chars or underscore
				$hex .= $str{$i};
			} else if (ord($str{$i}) > 127 ) {
				// do not encode all chars above 127
				// NOTE: this is not compliant to the SOLR spec but it is neither harmful (SOLR4.4)
				$hex .= $str{$i};
			} else {
				// encode all others
				$hex .= "_0x".dechex(ord($str{$i}));
			}
			$i++;
		} while ($i < strlen($str));
		return $hex;
	}

	/**
	 * Retrieves the SMW-ID of the article with the $namespaceID and the $title
	 * and adds them to the document description $doc.
	 *
	 * @param Database $db
	 * 		The database object
	 * @param int $namespaceID
	 * 		Namespace ID of the article
	 * @param string $title
	 * 		The DB key of the title of the article
	 * @param array $doc
	 * 		The document description. If there is a SMW ID for the article, it is
	 * 		added with the key 'smwh_smw_id'.
	 * @return bool
	 * 		<true> if an SMW-ID was found
	 * 		<false> otherwise
	 */
	private function retrieveSMWID($db, $namespaceID, $title, array &$doc) {
		// Get the SMW ID for the page
		//        $title = str_replace("'", "\'", $title);
		$db = wfGetDB( DB_SLAVE );
		$title = $db->strencode($title);
		$smw_ids = $db->tableName('smw_object_ids');
		$sql = <<<SQL
			SELECT s.smw_id as smwID
			FROM $smw_ids s
			WHERE s.smw_namespace=$namespaceID AND 
			      s.smw_title='$title'
SQL;
		$found = false;
		$res = $db->query($sql);
		if ($db->numRows($res) > 0) {
			$row = $db->fetchObject($res);
			$smwID = $row->smwID;
			$doc['smwh_smw_id'] = $smwID;
			$found = true;
		}
		$db->freeResult($res);

		return $found;

	}

	/**
	 * Retrieves the relations of the article with the SMW ID $smwID and adds
	 * them to the document description $doc.
	 *
	 * @param Database $db
	 * 		The database object
	 * @param int $smwID
	 * 		The SMW ID.
	 * @param array $doc
	 * 		The document description. If the page has relations, all relations
	 * 		and their values are added to $doc. The key 'smwh_properties' will
	 * 		be an array of relation names and a key will be added for each
	 * 		relation with the value of the relation.
	 */
	private function retrievePropertyValues($db, $namespace, $title, array &$doc) {
		global $fsgIndexPredefinedProperties;

		$store = smwfGetStore();
        
		$subject = SMWDIWikiPage::newFromTitle(Title::newFromText($title, $namespace));
		
		$properties = $store->getProperties($subject);
		
		$attributes = array();
		$relations = array();
		
		global $wgContLang;
		foreach($properties as $property) {
		   
            if ($property->getKey() == "_INST") continue;
		    
		    $predefPropType = SMWDIProperty::getPredefinedPropertyTypeId($property->getKey());
		    $p = $property; //SMWDIProperty::newFromUserLabel($prop);
		    if (!empty($predefPropType)) {
		        // This is a predefined property
		        if (isset($fsgIndexPredefinedProperties) && $fsgIndexPredefinedProperties === false) {
		            continue;
		        }
		        $prop = str_replace(' ', '_', $p->getLabel());
		    
		    }
		    $prop_ignoreasfacet = wfMessage('fs_prop_ignoreasfacet')->text();
		    
		    $iafValues = $store->getPropertyValues($p->getDiWikiPage(), SMWDIProperty::newFromUserLabel($prop_ignoreasfacet));
		    if (count($iafValues) > 0) {
		        continue;
		    }
		    	
		    $values = $store->getPropertyValues($subject, $property);
		    
		    foreach($values as $value) {
		        if ($value->getDIType() == SMWDataItem::TYPE_WIKIPAGE) {
		            if ($value->getSubobjectName() != "") {
		                // handle record properties
		                if ($value->getSubobjectName() != "") {
		                    $subData = smwfGetStore()->getSemanticData($value);
		                    $recordProperties = $subData->getProperties();
		                    foreach($recordProperties as $rp) {
		                        if (strpos($rp->getKey(), "_") === 0) continue;
		                        $propertyValues = $subData->getPropertyValues($rp);
		                        $record_value = reset($propertyValues);
		                        if ($record_value->getDIType() == SMWDataItem::TYPE_WIKIPAGE) {
		                            $enc_prop = $this->serializeWikiPageDataItem($subject, $rp, $record_value, $doc);
		                            $relations[] = $enc_prop;
		                        } else {
    		                        $enc_prop = $this->serializeDataItem($rp, $record_value, $doc);
    		                        $attributes[] = $enc_prop;
		                        }
		                    }
		                }
		            } else {
		                // handle relation properties
    		            $enc_prop = $this->serializeWikiPageDataItem($subject, $property, $value, $doc);
    		            $relations[] = $enc_prop;
		            }
		        } else {
		            // handle attribute properties
		            $enc_prop = $this->serializeDataItem($property, $value, $doc);
		            $attributes[] = $enc_prop;
		        }
		    }
		}
		
		$doc['smwh_properties'] = array_filter(array_unique($relations), function($e) { return !empty($e); });
		$doc['smwh_attributes'] = array_filter(array_unique($attributes), function($e) { return !empty($e); });
        
	}
	
	/**
	 * Serialize SMWDIWikiPage into $doc array.
	 * 
	 * @param SMWDIProperty $property
	 * @param SMWDataItem $dataItem
	 * @param array $doc
	 * 
	 * @return encoded property name 
	 */
	private function serializeWikiPageDataItem($subject, $property, $dataItem, array &$doc) {
	    $obj = $dataItem->getTitle()->getPrefixedText();
	   
	    global $fsgTitleProperty;
	    if (isset($fsgTitleProperty) && $fsgTitleProperty != '') {
	    	$this->addTitleAndUpdateDependent($subject, $dataItem, $obj);
	    }
	    
	    // The values of all properties are stored as string.
	    $prop = str_replace(' ', '_', $property->getLabel());
	    $prop = self::encodeTitle($prop);
	    $prop = "smwh_{$prop}_t";
	    if (!array_key_exists($prop, $doc)) {
	        $doc[$prop] = array();
	    }
	    $doc[$prop][] = $obj;
	    return $prop;		 
	}
	
 	private function addTitleAndUpdateDependent($subject, $dataItem, & $obj) {
		global $fsgTitleProperty;
		$titleProperty = SMWDIProperty::newFromUserLabel($fsgTitleProperty);
		$store = ApplicationFactory::getInstance()->getStore();
		$titleValue = $store->getPropertyValues($dataItem, $titleProperty);
		if (count($titleValue) > 0) {
			$titleValue = reset($titleValue);
			$obj .= '|'.$titleValue->getString();

		}
		
		if($this->updateOnlyCurrentArticle()) {
			return;
		}
		
		$inProperties = $store->getInProperties($subject);
		foreach($inProperties as $inProperty) {
			$subjects = $store->getPropertySubjects($inProperty, $subject);

			foreach($subjects as $subj) {
				$this->dependant[] = $subj->getTitle();
			}
		}
			
		$this->dependant = array_unique($this->dependant);
	}
	
	/**
	 * Serialize all other SMWDataItems into $doc array (non-SMWDIWikiPage).
	 *
	 * @param SMWDIProperty $property
	 * @param SMWDataItem $dataItem
	 * @param array $doc
	 * 
	 * @return encoded property name 
	 */
	private function serializeDataItem($property, $dataItem, array &$doc) {
	  	    
	    $valueXSD = $dataItem->getSerialization();
	    $prop = str_replace(' ', '_', $property->getLabel());
	    $prop = self::encodeTitle($prop);
	    $type = $dataItem->getDIType();

	    // The values of all attributes are stored according to their type.
	    if ($type == SMWDataItem::TYPE_TIME) {
	        $typeSuffix = 'dt';

	        $year = $dataItem->getYear();
	        $month = $dataItem->getMonth();
	        $day = $dataItem->getDay();

	        $hour = $dataItem->getHour();
	        $min = $dataItem->getMinute();
	        $sec = $dataItem->getSecond();

	        $month = strlen($month) === 1 ? "0$month" : $month;
	        $day = strlen($day) === 1 ? "0$day" : $day;
	        $hour = strlen($hour) === 1 ? "0$hour" : $hour;
	        $min = strlen($min) === 1 ? "0$min" : $min;
	        $sec = strlen($sec) === 1 ? "0$sec" : $sec;

	        // Required format: 1995-12-31T23:59:59Z
	        $valueXSD = "{$year}-{$month}-{$day}T{$hour}:{$min}:{$sec}Z";

	        // Store a date/time also as long e.g. 19951231235959
	        // This is needed for querying statistics for dates
	        $year = strlen($year) === 1 ? "0$year" : $year;
	        $year = strlen($year) === 2 ? "0$year" : $year;
	        $year = strlen($year) === 3 ? "0$year" : $year;
	        $dateTime = "{$year}{$month}{$day}{$hour}{$min}{$sec}";

	        $propDate = 'smwh_' . $prop . '_datevalue_l';
	        if (!array_key_exists($propDate, $doc)) {
	            $doc[$propDate] = array();
	        }
	        $doc[$propDate][] = $dateTime;

	    } else if ($type == SMWDataItem::TYPE_NUMBER) {
	        $typeSuffix = 'd';

	        $propNum = "smwh_{$prop}_numvalue_d";
	        if (!array_key_exists($propNum, $doc)) {
	            $doc[$propNum] = array();
	        }
	        $doc[$propNum][] = $valueXSD;

	    } else if ($type == SMWDataItem::TYPE_BOOLEAN) {
	        $typeSuffix = 'b';

	    } else {
	        $typeSuffix = 't';
	    }

	    $propXSD = "smwh_{$prop}_xsdvalue_$typeSuffix";
	    if (!array_key_exists($propXSD, $doc)) {
	        $doc[$propXSD] = array();
	    }
	    $doc[$propXSD][] = $valueXSD;

	    return $propXSD;
	}
	
	/**
	 * @return boolean true iff the global variable $fsUpdateOnlyCurrentArticle is set to true
	 */
	private function updateOnlyCurrentArticle() {
		global $fsUpdateOnlyCurrentArticle;
		if (isset($fsUpdateOnlyCurrentArticle) && $fsUpdateOnlyCurrentArticle === true) {
			return true;
		} else {
			return false;
		}
	}
}

