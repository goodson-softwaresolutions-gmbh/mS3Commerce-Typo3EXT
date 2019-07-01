<?php

/***************************************************************
* Part of mS3 Commerce
* Copyright (C) 2019 Goodson GmbH <http://www.goodson.at>
*  All rights reserved
* 
* Dieses Computerprogramm ist urheberrechtlich sowie durch internationale
* Abkommen geschützt. Die unerlaubte Reproduktion oder Weitergabe dieses
* Programms oder von Teilen dieses Programms kann eine zivil- oder
* strafrechtliche Ahndung nach sich ziehen und wird gemäß der geltenden
* Rechtsprechung mit größtmöglicher Härte verfolgt.
* 
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 *All methods and variables needed for resolving and handling SMZ markers
 *@see substituteSMZ
 * 
 */
class tx_ms3commerce_SMZ {

	/** @var tx_ms3commerce_template */
	var $template;
	var $useUseFieldsForEmpty;
	var $cache = array();
	var $pageTypeCache = array();
	var $languageId;
	var $marketId;
	/** @var tx_ms3commerce_db */
	var $db;
	
	public function __construct($template, $db = null, $marketId = null, $languageId = null) {
		if ($template) {
			$this->useUseFieldsForEmpty = $template->conf['use_SMZUse_forEmpty'] ? true : false;
			$this->template = $template;
			$this->db = $template->db;
			$this->languageId = $template->languageId;
			$this->marketId = $template->marketId;
		} else {
			$this->useUseFieldsForEmpty = false;
			$this->db = $db;
			$this->languageId = $languageId;
			$this->marketId = $marketId;
			$this->template = null;
		}
	}
	/**
	 * Builds a query string depending on the SMZ definition
	 * @param type $smzmarkerparts 
	 * @return string
	 */
	function buildSMZQuery($smzmarkerparts) {
		$query = array();
		$query['SELECT'] = 'sm.Id AS FeatureId, sm.`Name` AS `FeatureName`, UseUnit, UsePrefix, UseTitle, DisplayType, FilterType, IsComposedGroup AS IsComposed, SubGroup, GroupTitle ,IsNode,Level,HierarchyType,smzsm.`UserRights` AS UserRights';
		$query['FROM'] = 'featureComp_feature smzsm, featureCompilation smz, Feature sm ';
		$query['WHERE'] = ' smzsm.FeatureCompId = smz.Id' .
				' AND sm.Id = smzsm.FeatureId' .
				' AND smz.`Name` = \'' . $this->db->sql_escape($smzmarkerparts['name'], false) . '\'' .
				' AND sm.languageId = ' . $this->languageId .
				' AND sm.marketId = ' . $this->marketId;

		if ($smzmarkerparts['hierarchy']) {
			switch ($smzmarkerparts['hierarchy']) {
			case 'FGF': $ht = 1; break;
			case 'SF': $ht = 2; break;
			case 'RGF': $ht = 3; break;
			default: $ht = 0; // will result in nothing => Marker error
			}
			$query['WHERE'].= ' AND smzsm.HierarchyType = '.$ht;
		}
		
		if ($smzmarkerparts['subGroup']) {
			$query['WHERE'].= ' AND smzsm.SubGroup LIKE \'' . $this->db->sql_escape($smzmarkerparts['subGroup'], false) . '%\'';
		}

		$query['ORDER'] = ' smzsm.HierarchyType, smzsm.Sort';

		return $query;
	}

	/**
	 *
	 * @param type $tag
	 * @param type $smzsmrow
	 * @return string 
	 */
	function replaceSMZSMTag($tag, $smzsmrow) {
		$smName = $smzsmrow['FeatureName'];
		switch (strtolower($tag)) {
			case 'useunit':
				if ($smzsmrow['UseUnit']) {
					return '###SM_' . $smName . '_UNIT###';
				} else {
					return '';
				}
				break;
			case 'unitempty':
				if ($this->useUseFieldsForEmpty && !$smzsmrow['UseUnit']) {
					return 'mS3Commerce_unit_isempty';
				} else {
					return '###SM_' . $smName . '_UNITEMPTY###';
				}
				break;
			case 'useprefix':
				if ($smzsmrow['UsePrefix']) {
					return '###SM_' . $smName . '_PREFIX###';
				} else {
					return '';
				}
				break;
			case 'prefixempty':
				if ($this->useUseFieldsForEmpty && !$smzsmrow['UsePrefix']) {
					return 'mS3Commerce_prefix_isempty';
				} else {
					return '###SM_' . $smName . '_UNITEMPTY###';
				}
				break;
			case 'usetitle':
				if ($smzsmrow['UseTitle']) {
					return '###SM_' . $smName . '_TITLE###';
				} else {
					return '';
				}
				break;
			case 'titleempty':
				if ($this->useUseFieldsForEmpty && !$smzsmrow['UseTitle']) {
					return 'mS3Commerce_title_isempty';
				} else {
					return '###SM_' . $smName . '_TITLEEMPTY###';
				}
				break;
			case 'filter':
				// SPECIAL CASE: For 2-SM Filters, die actual SM is just a dummy-Node
				// The real SMs are in "FilterType", and start with ";".
				$filter = $smzsmrow['FilterType'];
				if ($filter[0] == ';') {
					return '###SM_' . substr($filter, 1) . '###';
				} else {
					return '###SM_' . $smName . '_' . $filter . '###';
				}
				break;
			case 'display':
				if (empty($smzsmrow['DisplayType'])) {
					return '###SM_' . $smName . '_VALUE###';
				} else {
					return '###SM_' . $smName . '_' . $smzsmrow['DisplayType'] . '###';
				}

			case 'iscomposed':
				if ($smzsmrow['IsComposed']) {
					return 'mS3Commerce_iscomposed';
				} else {
					return 'mS3Commerce_isnotcomposed';
				}

			case 'grouptitle':
				return $smzsmrow['GroupTitle'];

			case 'groupname':
				return $smzsmrow['SubGroup'];

			case 'groupid':
				return preg_replace('#[^\w\d]#u', '_', $smzsmrow['SubGroup']);

			default:
				return '###SM_' . $smName . '_' . $tag . '###';
		}
	}

	/**
	 * Get the SMZ marker contents
	 * @param string The SMZ marker (Format SMZ[T]_<SMZName>[@FGF|SF|RGF][:<SMZSubGroup>]*_SMZVIEW_<ViewName>)
	 * @return mixed Array containing parts "name", "view", "isPageType" and "subGroup" (without leading ':')
	 */
	function getSMZMarkerParts($marker) {
		$matches = array();
		if (preg_match("/^SMZ(T?)_([^:@]+)(@FGF|@SF|@RGF)?(:.+)?_SMZVIEW_(.+)$/", $marker, $matches)) {
			$smzparts['name'] = $matches[2];
			$smzparts['template'] = $matches[5];
			if ($matches[1] == 'T') {
				$smzparts['isPageType'] = true;
			} else {
				$smzparts['isPageType'] = false;
			}
			if ($matches[3]) {
				$smzparts['hierarchy'] = substr($matches[3], 1);
			} else {
				$smzparts['hierarchy'] = '';
			}
			if ($matches[4]) {
				$smzparts['subGroup'] = substr($matches[4], 1);
			} else {
				$smzparts['subGroup'] = '';
			}
			return $smzparts;
		} else {
			return false;
		}
	}
	
	function resolvePageType($pageType, $elementId, $elementType) {
		$db = $this->db;
		
		$columnArr = array(
			tx_ms3commerce_constants::ELEMENT_GROUP => 'GroupId',
			tx_ms3commerce_constants::ELEMENT_PRODUCT => 'ProductId',
			tx_ms3commerce_constants::ELEMENT_DOCUMENT => ''
		);
		$col = $columnArr[$elementType];
		$key = $elementType.':'.$elementId;
		
		if (!array_key_exists($pageType, $this->pageTypeCache)) {
			$this->pageTypeCache[$pageType] = array();
		}

		if (!array_key_exists($key, $this->pageTypeCache[$pageType])) {
			$pageTypeEsc = $db->sql_escape($pageType);
			$where = " featureCompilation.Id = FeatureCompValue.featureCompId" .
					" AND FeatureCompValue.$col = $elementId" .
					" AND featureCompilation.type= $pageTypeEsc";

			$name = '';
			$res = $db->exec_SELECTquery('featureCompilation.Name', 'featureCompilation,FeatureCompValue', $where);
			if ($res) {
				$row = $db->sql_fetch_row($res);
				$db->sql_free_result($res);
				if ($row) {
					//put it into the cache
					$name = $row[0];
				}
			}
			$this->pageTypeCache[$pageType][$key] = $name;
		}
		//get the smzName out of the cache
		return $this->pageTypeCache[$pageType][$key]; 
	}

	function substituteSMZRecursive($elementId, $elementType, $marker) {
		$content = $this->substituteSMZ($elementId, $elementType, $marker);

		$markers = $this->template->tplutils->getMarkerArray($content);
		foreach ($markers as $m) {
			if ($this->isSMZMarker($m)) {
				$markContent = $this->substituteSMZRecursive($elementId, $elementType, $m);
				$content = $this->template->tplutils->substituteMarker($content, "###$m###", $markContent);
			}
		}

		return $content;
	}

	/**
	 * Replaces an SMZ Marker by its resolved SM markers
	 * @param elementId int The Id of the item to resolve 
	 * the SMZ for
	 * @param elementType int The type of element (see tx_ms3commerce_constants::ELEMENT_XXX)
	 * @param marker string The SMZ Marker (without. ###)
	 * @return string The SMZ View template with SMZSM_* Markers 
	 * resolved and repeated for each SM in SMZ
	 */
	function substituteSMZ($elementId, $elementType, $marker) {
		$start = microtime(true);
		$db = $this->template->db;
		$content = '';
		$smzparts = $this->getSMZMarkerParts($marker);
		if (!$smzparts) {
			return $content;
		}
		$this->template->plugin->timeTrackStart("SMZ");
		
		// Resolve Page Type
		if ($smzparts['isPageType']) {
			$smzparts['name'] = $this->resolvePageType($smzparts['name'], $elementId, $elementType);
		}


		//check if smz already substituted out of the cache
		$smzKey = "{$smzparts['name']}@{$smzparts['hierarchy']}:{$smzparts['subGroup']}####{$smzparts['template']}";
		if (array_key_exists($smzKey, $this->cache)) {
			$end = microtime(true);
			$this->template->plugin->timeTrackStop();
			//echo "CACHE HIT (". ($end-$start).")";
			return $this->cache[$smzKey];
		} else {

			$this->template->plugin->timeTrackStart("SMZ Load");
			$query = $this->buildSMZQuery($smzparts);
			//print_r($query);
			$rs = $db->exec_SELECTquery($query['SELECT'], $query['FROM'], $query['WHERE'], '', $query['ORDER']);
			if (!$rs) {
				$this->template->plugin->timeTrackStop();
				$this->template->plugin->timeTrackStop();
				return 'SQL ERROR: ' . $db->sql_error();
			}
			$mid = microtime(true);
			$dur = $mid - $start;
			//print_r($query);
			if ($db->sql_num_rows($rs) == 0) {
				$this->template->plugin->timeTrackStop();
				$this->template->plugin->timeTrackStop();
				return '';
			}
			//get view template
			$template = $this->template->plugin->getTemplate('###SMZVIEW_' . $smzparts['template'] . '###');
			if (strlen($template) == 0) {
				$this->template->plugin->timeTrackStop();
				$this->template->plugin->timeTrackStop();
				return $content;
			}

			// Load data
			$smzdata = array();
			$features = array();
			while ($smzsmrow = $this->template->db->sql_fetch_assoc($rs)) {
				$smzdata[$smzsmrow['HierarchyType']][] = $smzsmrow;
				$features[] = $smzsmrow['FeatureId'];
			}
			$db->sql_free_result($rs);

			// Preload Features
			$features = array_unique($features);
			$this->template->dbutils->preloadFeatures($features);

			$fgf = $this->template->tplutils->getSubpart($template, "###FGF###");
			$sf = $this->template->tplutils->getSubpart($template, "###SF###");
			$rgf = $this->template->tplutils->getSubpart($template, "###RGF###");
			$allEmpty = false;
			if (empty($fgf) && empty($sf) && empty($rgf)) {
				$allEmpty = true;
				
				// If hierarchy is explicilty selected in marker,
				// use whole template as this hierarchy. Default is FGF
				switch ($smzparts['hierarchy']) {
					case 'RGF': $rgf = $template; break;
					case 'SF': $sf = $template; break;
					case 'FGF':
					default:
						$fgf = $template;
				}
			}
			
			$fgf = $this->doLayoutSMZ($smzdata[1], $fgf, $smzparts);
			$sf = $this->doLayoutSMZ($smzdata[2], $sf, $smzparts);
			$rgf = $this->doLayoutSMZ($smzdata[3], $rgf, $smzparts);
			
			if ($allEmpty) {
				switch ($smzparts['hierarchy']) {
					case 'RGF': $template = $rgf;  break;
					case 'SF': $template = $sf; break;
					case 'FGF':
					default:
						$template = $fgf;
				}
			} else {
				$template = $this->template->tplutils->substituteSubpart($template, "###FGF###", $fgf);
				$template = $this->template->tplutils->substituteSubpart($template, "###SF###", $sf);
				$template = $this->template->tplutils->substituteSubpart($template, "###RGF###", $rgf);
			}

			$this->cache[$smzKey] = $template;

			$end = microtime(true);
			//echo "CACHE MISS (". ($end-$start).") / (".($mid-$start).")";

			$this->template->plugin->timeTrackStop();
			$this->template->plugin->timeTrackStop();
			return $template;
		}
	}

	private function doLayoutSMZ($smzdata, $template, $smzinfo) {
		// Set ROOTNODE name/title and skip the "ghost" record
		$subsArray = array();
		$subsArray['###SMZROOT_NAME###'] = $smzdata[0]['SubGroup'];
		$subsArray['###SMZROOT_TITLE###'] = $smzdata[0]['GroupTitle'];
		$template = $this->template->tplutils->substituteMarkerArray($template, $subsArray);
		// Skip not composed Root nodes! ==> MOVED
		/*
		  if ($smzdata[0]['IsComposed'] == 0) {
		  array_shift($smzdata);
		  }
		 */

		// Do SM-repeated part, if present
		$repeatedPart = $this->template->tplutils->getSubpart($template, '###REPEAT_CONTENT###');
		if (strlen($repeatedPart) > 0) {
			//reset($smzdata);
			$repContent = $this->substituteSMZ_Repeat($repeatedPart, $smzdata, $smzinfo);
			$template = $this->template->tplutils->substituteSubpart($template, '###REPEAT_CONTENT###', $repContent);
		}

		// Do SM-placement part
		$subs2 = $this->substituteSMZ_Placement($template, $smzdata, $smzinfo);
		$content = $this->template->tplutils->substituteMarkerArray($template, $subs2);

		return $content;
	}
	
	function substituteSMZ_Placement($template, $smzdata, $smzinfo) {
		// Get all SMZSM markers in template (unique)
		$markers = array();
		$ct = preg_match_all("/\#\#\#SMZSM_(\d+)_([^#]*)\#\#\#/", $template, $tmplMarkersSM, PREG_PATTERN_ORDER);
		$ct += preg_match_all("/\#\#\#SMZNODE_(\d+)_([^#]*)\#\#\#/", $template, $tmplMarkersNode, PREG_PATTERN_ORDER);
		if ($ct == 0) {
			return array();
		}

		// Restructure to mapping from PlacementNr => Tags (unique)
		$markers = array();
		$i = 0;
		foreach ($tmplMarkersSM[1] as $placenr) {
			if (!array_key_exists($placenr, $markers)) {
				$markers[$placenr] = array();
			}

			$markers[$placenr][] = $tmplMarkersSM[2][$i];
			$markers[$placenr] = array_unique($markers[$placenr]);
			$i++;
		}

		foreach ($tmplMarkersNode[1] as $placenr) {
			if (!array_key_exists($placenr, $markers)) {
				$markers[$placenr] = array();
			}

			$markers[$placenr][] = $tmplMarkersNode[2][$i];
			$markers[$placenr] = array_unique($markers[$placenr]);
			$i++;
		}

		// Sort by PlacementNr
		ksort($markers);

		$i = 1;
		$subsArray = array();
		
		
		// Skip non-composed root nodes (skip it in SMZ array)
		if ($smzdata[0]['IsComposed'] == 0) {
			$fix = 0;
		} else {
			$fix = -1;
		}

		foreach ($markers as $nr => $m) {
			if ($nr >= count($smzdata)) {
				foreach ($m as $tag) {
					$subsArray['###SMZSM_' . $nr . '_' . $tag . '###'] = '';
					$subsArray['###SMZNODE_' . $nr . '_' . $tag . '###'] = '';
				}
			} else {
				foreach ($m as $tag) {
					$discard = false;
					$row = $smzdata[$nr + $fix];
					$smzsmConstraints = $row['UserRights'];
					$visible = $this->template->tplutils->checkFeatureVisibility($row['FeatureId'],$smzsmConstraints);
					if(!$visible){		
						$discard = true;
						if($tag == 'VALUEEXISTS'||$tag == 'EXISTS'){
							$subsArray['###SMZSM_' . $nr . '_' . $tag . '###'] = '###REMOVE_SUBPART###';
							continue;
						}					
					}
					if ($row['IsNode']) {
						if (strpos(strtolower($tag), 'includeview_') === 0) {
							// Include SMZ View
							$view = substr($tag, 12);
							$subsArray['###SMZNODE_' . $nr . '_' . $tag . '###'] = $this->includeNodeSMZ($view, $row['FeatureName'], $smzinfo);
						} else {
							$subsArray['###SMZNODE_' . $nr . '_' . $tag . '###'] = $this->replaceSMZSMTag($tag, $row);
						}
					} else {
						//if not visible discard block
						if($discard){
							if(key_exists('###SMZSM_' . $nr . '_VALUEEXISTS###', $subsArray)|| key_exists('###SMZSM_' . $nr . '_EXISTS###', $subsArray)){
							$subsArray['###SMZSM_' . $nr . '_' . $tag . '###'] = '';
							}
						}else{
							$subsArray['###SMZSM_' . $nr . '_' . $tag . '###'] = $this->replaceSMZSMTag($tag, $row);	
						}		
					}
				}
			}
		}
		return $subsArray;
	}

	function substituteSMZ_Repeat($template, &$smzdata, $smzinfo, $nesting = 0, $level = 0, &$ct = 0) {
		$subsArray = array();

		if ($nesting > 0) {
			$NestStr = "_$nesting";
		} else {
			$NestStr = "";
		}
		// wie soll ein NODE layoutet
		$templNode = $this->template->tplutils->getSubpart($template, "###REPEAT_NODE{$NestStr}###");
		// wie soll ein SM loyoutet 
		$templSM = $this->template->tplutils->getSubpart($template, "###REPEAT_SM{$NestStr}###");

		// Get all SMZSM markers in template (unique)
		$SMMarkers = array();
		// sind SM Markers in SM Template vorhanden?
		$hasMarkerSM = preg_match_all("/\#\#\#SMZSM_([^\#]*)\#\#\#/", $templSM, $SMMarkers);
		// Get unique marker postfixes
		if ($hasMarkerSM) { // wegen REGexp 
			$SMMarkers = $SMMarkers[1];
			//filter alle mehrfach vorhandene markers in den Array
			$SMMarkers = array_unique($SMMarkers);
		} else {
			$SMMarkers = array();
		}
		$NdMarkers = array();
		//sind Node Markers in den Node Template vorhanden?
		$hasMarkerNode = preg_match_all("/\#\#\#SMZNODE_([^\#]*)\#\#\#/", $templNode, $NdMarkers);
		if ($hasMarkerNode) { // wegen REGexp [1]
			$NdMarkers = $NdMarkers[1];
			//filter alle mehrfach vorhandene markers in den Array
			$NdMarkers = array_unique($NdMarkers);
		} else {
			$NdMarkers = array();
		}

		// Skip non-composed root nodes
		if ($ct == 0 && $smzdata[0]['IsComposed'] == 0) {
			$ct = 1;
		}

		if (!$hasMarkerNode && !$hasMarkerSM) {
			$content = '';
			// Skip until end of this sub-group
			for (/* $ct is initialized */; $ct < count($smzdata); $ct++) {
				$row = $smzdata[$ct];
				
				if ($row['Level'] < $level) {
					$level = $row['Level'];
					break;
				}
				if ($row['IsNode']) {
					$content .= $templNode;
				} else {
					$content .= $templSM;
				}
			}
			return $content;
		}

		$content = '';
		for (/* $ct is initialized */; $ct < count($smzdata); $ct++) {
			$row = $smzdata[$ct];
			//check if visible
			$smzsmConstraints = $row['UserRights'];
			$visible = $this->template->tplutils->checkFeatureVisibility($row['FeatureId'],$smzsmConstraints);
			if(!$visible){
				//not visible, discard this SM jump to the next Feature
				continue;
			}
				
			$subsArray = array();

			if ($row['Level'] < $level) {
				$level = $row['Level'];
				break;
			}
			if ($row['IsNode']) {
				//echo $nesting.";";
				$hasInclude = false;
				foreach ($NdMarkers as $nm) {
					if (strtolower($nm) == 'counter') {
						$subsArray['###SMZNODE_' . $nm . '###'] = $ct;
					} else if (strpos(strtolower($nm), 'includeview_') === 0) {
						// Include SMZ View
						$view = substr($nm, 12);
						$subsArray['###SMZNODE_' . $nm . '###'] = $this->includeNodeSMZ($view, $row['FeatureName'], $smzinfo);
						$hasInclude = true;
					} else {
						$subsArray['###SMZNODE_' . $nm . '###'] = $this->replaceSMZSMTag($nm, $row);
					}
				}

				if ($hasInclude) {
					// Skip content!
					$includeLevel = $row['Level'] + 1;
					for ($ct++; $ct < count($smzdata); $ct++) {
						$row = $smzdata[$ct];
						if ($row['Level'] < $includeLevel) {
							break;
						}
					}
					$ct--;

					$innerContent = $templNode;
				} else {
					$repeatedPart = $this->template->tplutils->getSubpart($template, "###REPEAT_CONTENT_" . ($nesting + 1) . "###");
					if (strlen($repeatedPart) > 0) {
						$rCt = $ct + 1;
						//echo "Before: $ct;";
						$nodeContent = $this->substituteSMZ_Repeat($repeatedPart, $smzdata, $smzinfo, $nesting + 1, $row['Level'] + 1, $rCt);
						$ct = $rCt - 1;
						//echo "After: $ct;";
						$innerContent = $this->template->tplutils->substituteSubpart($templNode, "###REPEAT_CONTENT_" . ($nesting + 1) . "###", $nodeContent);
					} else {
						$innerContent = $templNode;
					}
				}
				$content .= $this->template->tplutils->substituteMarkerArray($innerContent, $subsArray);
			} else {
				foreach ($SMMarkers as $m) {
					if (strtolower($m) == 'counter') {
						$subsArray['###SMZSM_' . $m . '###'] = $ct;
					} else {
						$subsArray['###SMZSM_' . $m . '###'] = $this->replaceSMZSMTag($m, $row);
					}
				}
				$content .= $this->template->tplutils->substituteMarkerArray($templSM, $subsArray);
			}
		}
		return $content;
	}

	function isSMZMarker($marker) {
		if ($this->getSMZMarkerParts($marker)) {
			return true;
		} else {
			return false;
		}
	}

	function includeNodeSMZ($view, $nodeName, $smzinfo) {
		$marker = '###SMZ_';
		$marker .= $smzinfo['name'];
		if (!empty($smzinfo['subGroup'])) {
			$marker .= ':' . $smzinfo['subGroup'];
		}
		$marker .= ':' . $nodeName;
		$marker .= '_SMZVIEW_';
		$marker .= $view;
		$marker .= '###';
		return $marker;
	}
}

?>
