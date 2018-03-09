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
use SMW\DataTypeRegistry;

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
	 * @param string $user
	 * @param string $pass
	 * @param string $indexCore SOLR core
	 *
	 */
	public function __construct($host, $port, $user = '', $pass = '', $indexCore = '') {
		parent::__construct($host, $port, $user, $pass, $indexCore);
	}

	/**
	 * Updates the index for the given $article.
	 * It retrieves all semantic data of the new version and adds it to the index.
	 *
	 * @param WikiPage $wikiPage
	 * 		The article that changed.
	 * @param User $user
	 * 		Optional user object
	 * @param string $text
	 *		Optional content of the article. If NULL, the content of $wikiPage is
	 *		retrieved in this method.
	 * @param array $messages User readible messages
	 */
	public function updateIndexForArticle(WikiPage $wikiPage, $user = NULL, $rawText = NULL, & $messages = [] ) {
	    
		$doc = array();
		$this->dependant = [];
		
		// Get the page ID of the article
		$t = $wikiPage->getTitle();
		$pid = $wikiPage->getId();
		if($pid == 0) {
		    throw new \Exception("invalid page ID for " . $t->getPrefixedText());
		}
		
		global $fsgBlacklistPages;
		if (in_array($t->getPrefixedText(), $fsgBlacklistPages)) {
			throw new \Exception("blacklisted page: " . $t->getPrefixedText());
		}
		
		$pns = $t->getNamespace();
		$pt  = $t->getDBkey();
		
		$parserOptions = new ParserOptions();
		
		global $egApprovedRevsBlankIfUnapproved, $egApprovedRevsNamespaces;
		if (defined('APPROVED_REVS_VERSION') 
			&& $egApprovedRevsBlankIfUnapproved 
			&& in_array($wikiPage->getTitle()->getNamespace(), $egApprovedRevsNamespaces)) {
			
			// indexed approved revision
			$revision = $this->getApprovedRevision($wikiPage);
			if ($revision === false) {
				throw new \Exception("unapproved " . $t->getPrefixedText());
			}
			$content = $revision->getContent();
			$text = $content->getParserOutput($wikiPage->getTitle(),
					$revision->getId(), $parserOptions)->getText();
			$text = Sanitizer::stripAllTags($text);
			
		} else {
			// index latest revision
			$parserOut = $wikiPage->getParserOutput($parserOptions);
			if(!$parserOut) {
			    $text = '';
			} else {
		    	$text = Sanitizer::stripAllTags($parserOut->getText());
			}
		}
		

		$doc['id'] = $pid;
		$doc['smwh_namespace_id'] = $pns;
		$doc['smwh_title'] = $pt;
		$doc['smwh_full_text'] = $text;

		$options = array();
		global $fsgDefaultBoost;
		$options['smwh_boost_dummy']['boost'] = $fsgDefaultBoost;
		
		global $fsgNamespaceBoosts;
		if (array_key_exists($pns, $fsgNamespaceBoosts)) {
			$this->calculateBoostFactors($options, $fsgNamespaceBoosts[$pns]);
		}
		
		$db = wfGetDB( DB_SLAVE );
		
		// retrieve templates (currently only needed for boosts)
		$this->retrieveTemplates($db, $pid, $doc, $options);
		
		if ($this->retrieveSMWID($db, $pns, $pt, $doc)) {
			$smwID = $doc['smwh_smw_id'];
			$this->retrievePropertyValues($db, $pns, $pt, $doc, $options);
		}
		
		if ($t->getNamespace() == NS_FILE) {
			$this->retrieveFileSystemPath($db, $pns, $pt, $doc);
		}

		// extract document if a file was uploaded
		if ($pns == NS_FILE) {
		    try {
			   $docData = $this->extractDocument($t);
		    } catch(\Exception $e) {
		        $messages[] = $e->getMessage();
			   $doc['smwh_full_text'] .= " " . $e->getMessage();
		    }
		}
		
		// call fs_saveArticle hook
		\Hooks::run('fs_saveArticle', array( &$rawText, &$doc ));
		
		// Let the super class update the index
		$this->updateIndex($doc, $options);
		
	    if($this->updateOnlyCurrentArticle()) {
			return true;
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
				$this->updateIndexForArticle(new WikiPage($ttu), $user, $rawText, $messages);
			}
		}
		
		return true;
	}
	
	private function getApprovedRevision(WikiPage $wikiPage) {
		
		// get approved rev_id
		$db = wfGetDB( DB_MASTER );
		
		$queryString = sprintf(
				'SELECT rev_id' .
				' FROM approved_revs' .
				' WHERE page_id = %s',
				$wikiPage->getTitle()->getArticleID());
		$res = $db->query($queryString);
		$rev_id = null;
		if ($db->numRows( $res ) > 0) {
			
			if($row = $db->fetchRow( $res )) {
				$rev_id = $row['rev_id'];
			}	
		}
		
		if (is_null($rev_id)) {
			return false;
		}
		
		$revision = \Revision::newFromId($rev_id);
		return $revision;
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
			try {
				$this->updateIndexForArticle($article->getPage(), $wgUser, $text);	
			} catch(\Exception $e) { }
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
		$options['smwh_boost_dummy']['boost'] += $value;
		
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
	 * Returns the SOLR field name for a property
	 * @param SMWDIProperty $property
	 * 
	 * @return string
	 */
	public static function encodeSOLRFieldName($property) {
		
		$prop = str_replace(' ', '_', $property->getLabel());
	
		$prop = self::encodeTitle($prop);
		
		$typeId = $property->findPropertyTypeID();
		$type = DataTypeRegistry::getInstance()->getDataItemByType($typeId);
		
		// The property names of all attributes are according to their type.
		switch($type) {
			case SMWDataItem::TYPE_BOOLEAN:
				return "smwh_{$prop}_xsdvalue_b";
			case SMWDataItem::TYPE_NUMBER:
				return "smwh_{$prop}_numvalue_d";
			case SMWDataItem::TYPE_STRING:
				return "smwh_{$prop}_xsdvalue_t";
			case SMWDataItem::TYPE_WIKIPAGE:
				return "smwh_{$prop}_t";
			case SMWDataItem::TYPE_TIME:
				return "smwh_{$prop}_xsdvalue_dt";
		}
		
		// all others are regarded as string/text
		return "smwh_{$prop}_xsdvalue_t";
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
	 * Retrieves full URL of the file resource attached to this title.
	 * 
	 * @param Database $db
	 * @param int $namespace namespace-id
	 * @param string $title dbkey
	 * @param array $doc (out)
	 */
	private function retrieveFileSystemPath($db, $namespace, $title, array &$doc) {
		$title = Title::newFromText($title, $namespace);
		$file = \RepoGroup::singleton()->getLocalRepo()->newFile($title);
		$filepath = $file->getFullUrl();
		
		$propXSD = "smwh_diqa_import_fullpath_xsdvalue_t";
		$doc[$propXSD] = $filepath;
		$doc['smwh_attributes'][] = $propXSD;
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
	private function retrievePropertyValues($db, $namespace, $title, array &$doc, array &$options) {
		global $fsgIndexPredefinedProperties;

		$store = smwfGetStore();
        
		$subject = SMWDIWikiPage::newFromTitle(Title::newFromText($title, $namespace));
		
		$properties = $store->getProperties($subject);
		
		$attributes = array();
		$relations = array();
		
		global $wgContLang;
		foreach($properties as $property) {
		   
			// handle member categories
            if ($property->getKey() == "_INST") { 
            	$categories = $store->getPropertyValues($subject, $property);
            	$this->indexCategories($categories, $doc, $options);
            	continue;
            }
            
            // handle super-categories
            if ($property->getKey() == "_SUBC") {
            	$categories = $store->getPropertyValues($subject, $property);
            	$this->indexCategories($categories, $doc, $options);
            	continue;
            }
		    
            // check if particular pre-defined property should be indexed
		    $predefPropType = SMWDIProperty::getPredefinedPropertyTypeId($property->getKey());
		    $p = $property; //SMWDIProperty::newFromUserLabel($prop);
		    if (!empty($predefPropType)) {
		        // This is a predefined property
		        if (isset($fsgIndexPredefinedProperties) && $fsgIndexPredefinedProperties === false) {
		            continue;
		        }
		        $prop = str_replace(' ', '_', $p->getLabel());
		    
		    }
		    
		    // check if property should be indexed
		    $prop_ignoreasfacet = wfMessage('fs_prop_ignoreasfacet')->text();
		    
		    $iafValues = $store->getPropertyValues($p->getDiWikiPage(), SMWDIProperty::newFromUserLabel($prop_ignoreasfacet));
		    if (count($iafValues) > 0) {
		        continue;
		    }

		    // retrieve all annotations and index them 
		    $values = $store->getPropertyValues($subject, $property);
		    
		    foreach($values as $value) {
		        if ($value->getDIType() == SMWDataItem::TYPE_WIKIPAGE) {
		        	
		            if ($value->getSubobjectName() != "") {
                        
		                global $fsgIndexSubobjects;
		                if ($fsgIndexSubobjects !== true) {
		                    continue;
		                }
		                
		                // handle record properties
		                if ($value->getSubobjectName() != "") {
		                    $subData = smwfGetStore()->getSemanticData($value);
		                    $recordProperties = $subData->getProperties();
		                    foreach($recordProperties as $rp) {
		                        if (strpos($rp->getKey(), "_") === 0) continue;
		                        $propertyValues = $subData->getPropertyValues($rp);
		                        $record_value = reset($propertyValues);
		                        if ($record_value === false) continue;
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
	 * Indexes categories. Either as member categories or super-categories
	 * 
	 * @param array $categories
	 * @param array $doc
	 * @param array $options
	 */
	private function indexCategories($categories, array &$doc, array &$options) {
		
		$prop_ignoreasfacet = wfMessage('fs_prop_ignoreasfacet')->text();
		$ignoreAsFacetProp = SMWDIProperty::newFromUserLabel($prop_ignoreasfacet);
		$store = smwfGetStore();
		
		$doc['smwh_categories'] = [];
		$doc['smwh_directcategories'] = [];
		$allParentCategories = [];
		foreach($categories as $category) {
			 
			// do not index if ignored
			$iafValues = $store->getPropertyValues(SMWDIWikiPage::newFromTitle($category->getTitle()), $ignoreAsFacetProp);
			if (count($iafValues) > 0) {
				continue;
			}
			 
			// index this category
			$categoryAsText = $category->getTitle()->getText();
			$doc['smwh_categories'][] = $categoryAsText;
			$doc['smwh_directcategories'][] = $categoryAsText;
			$categories[] = str_replace("_", " ", $categoryAsText);
			 
			$this->_getAllSupercategories($category->getTitle(), $allParentCategories);
			
		}
		
		// index all parent categories
		$allParentCategories = array_unique($allParentCategories);
		foreach($allParentCategories as $pc) {
			$doc['smwh_categories'][] = $pc;
			$categories[] = str_replace("_", " ", $pc);
		}
		
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
	 * Returns all parent categories.
	 * 
	 * @param Title $cTitle
	 * @param array $categories Category names as text
	 */
	private function _getAllSupercategories($cTitle, & $categories) {
		$parentCategories = $cTitle->getParentCategories();
		foreach($parentCategories as $parentCat => $childCat) {
			$parentCatTitle = \Title::newFromText($parentCat);
			$categories[] = $parentCatTitle->getText();
			$this->_getAllSupercategories($parentCatTitle, $categories);
		}
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
			if ($titleValue instanceof \SMWDIString || $titleValue instanceof \SMWDIBlob) {
				$obj .= '|'.$titleValue->getString();
			}

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
			
		// remove duplicates
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
	    
	    $this->handleSpecialWikiProperties($property, $dataItem, $doc);

	    return $propXSD;
	}
	
	/**
	 * Special handling for special SMW properties.
	 * 
	 * @param SMWDIProperty $property
	 * @param SMWDataItem $dataItem
	 * @param array $doc
	 */
	private function handleSpecialWikiProperties($property, $dataItem, array &$doc) {
		
		if ($property->isUserDefined()) {
			return; // not special
		}
		
		switch ($property->getKey()) {
			case '_MDAT':
				// used for sorting
				$doc['smwh__MDAT_datevalue_l'] = $dataItem->getMwTimestamp();
				break;
				
		}
		
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

