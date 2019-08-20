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

/*
 * tx_ms3commerce_core
 * Basic core functionality for mS3 Commerce.
 * Essentially this is the search backend functionality.
 */

require_once ('class.tx_ms3commerce_constants.php');
require_once ('class.itx_ms3commerce_pagetypehandler.php');
require_once ('class.tx_ms3commerce_plugin_sessionUtils.php');
require_once ('class.tx_ms3commerce_searchfetcher.php');

define('MULTIVALUE_SEP_CHAR', MS3C_MULTIVALUE_SEPARATOR);
define('MS3C_SUGGEST_KEY', 'B4D4F6BF004B6C4213E79838512FA3C2');

/**
 * This is the mS3 Commerce core.
 * It is platform independent and only has dependency on the mS3 Commerce
 * product database being present.  The database should also be optimized
 * to keep SELECTs under one second.  If any searches require more than
 * one second, please further optimize.
 * A main feature of this module is the ability to filter sets of products
 * by features and also return sets of feature values.
 * The sequence is performed as so:
 * __construct($db,$marketId,$languageId,$menuIds,$musts)
 * The basic structure here is the query structure.
 * $query[initialized]=true|false - The query has not been called
 * $query[features]=array - Array of feature names on intialization and 
 * by Jordan Stevens
 */
class tx_ms3commerce_search implements itx_ms3commerce_pagetypehandler {

	/** @var tx_ms3commerce_template */
	var $template;

	/** @var tx_ms3commerce_db */
	var $db;

	/** @var tx_ms3commerce_DbUtils */
	var $dbutils;

	/** @var tx_ms3commerce_pi1 */
	var $plugin;
	var $conf;

	/** @var tx_ms3commerce_timetracker */
	var $timetracker;

	/** @var itx_ms3commerce_custom */
	var $custom;

	public function __construct($template = null, $db = null, $dbutils = null, $custom = null) {
		$this->template = $template;
		if ($template == null) {
			$this->db = $db;
			$this->conf = array();
			$this->timetracker = new tx_ms3commerce_timetracker();
			$this->dbutils = $dbutils;
			$this->custom = $custom;
		} else {
			$this->db = $this->template->db;
			$this->conf = $this->template->conf;
			$this->plugin = $this->template->plugin;
			$this->timetracker = $this->plugin->timetracker;
			$this->dbutils = $this->template->dbutils;
			// Custom will not be there yet...
			//$this->custom = $this->template->custom;
		}
	}

	private function setupCustom() {
		// When initialized with template, the template->custom was
		// not there in c'tor. So we assign it here
		if (!$this->custom) {
			if ($this->template) {
				$this->custom = $this->template->custom;
			}
		}
		// Fallback: use a default-Custom if there is none at all
		if (!$this->custom) {
			$this->custom = new tx_ms3commerce_custom();
		}
	}

	//////////////////////////////////////////////////
	///			MAIN QUERY PART						//
	//////////////////////////////////////////////////

	/**
	 * Prepares the Request object comming from json or http_POST
	 * adds features[] and includes
	 * @param type $json
	 * @return type
	 * @throws Exception 
	 */
	private function preprocessRequest($request) {
		$this->timetracker->timeTrackStart("preprocessRequest");
		$request->include = array();
		$request->pairedFeatures = array();
		$request->features = array();
		$request->IsFulltext = false;
		$request->IsFulltextDisplay = false;
		//$request->features = array();
		if (isset($request->Selection)) {
			foreach ($request->Selection as $sel) {
				$isMultiFeature = $sel->IsMultiFeature;
				if (!is_array($sel->Value)) {
					$sel->Value = array($sel->Value);
				}
				foreach ($sel->Value as &$v) {
					$v = str_replace('<', '&lt;', $v);
					$v = str_replace('>', '&gt;', $v);
				}
				$sql = '';
				switch ($sel->Type) {
					case 'EqualsNumber':
						if (count($sel->Value) > 0 && strlen($sel->Value[0]) > 0) {
							$sql = "ContentNumber =" . floatval($sel->Value[0]); //Nr1
						}
						break;
					case 'Equals':
						if (count($sel->Value) > 0 && strlen($sel->Value[0]) > 0) {
							if ($isMultiFeature) {
								$sql = "ContentPlain like '%" . MULTIVALUE_SEP_CHAR . $this->db->sql_escape($sel->Value[0], false) . MULTIVALUE_SEP_CHAR . "%'"; // Nr3
							} else {
								$sql = "ContentPlain =" . $this->db->sql_escape($sel->Value[0]); //Nr2
							}
						}
						break;
					case 'Contains':
						if (count($sel->Value) > 0 && strlen(trim($sel->Value[0])) > 0) {

							$val = explode(' ', $sel->Value[0]);
							$newval = implode('%', $val);

							if ($isMultiFeature) {
								$sql = "ContentPlain like '%" . MULTIVALUE_SEP_CHAR . "%" . $this->db->sql_escape($newval, false) . "%" . MULTIVALUE_SEP_CHAR . "%'"; // Nr5
							} else {
								$sql = "ContentPlain like " . $this->db->sql_escape("%" . $newval . "%"); // Nr4
							}
						}
						break;
					case 'Any':
						if (count($sel->Value) > 0) {
							if ($isMultiFeature) {
								foreach ($sel->Value as $value) {
									$sql.= " ContentPlain like '%" . MULTIVALUE_SEP_CHAR . $this->db->sql_escape($value, false) . MULTIVALUE_SEP_CHAR . "%' or"; // Nr7
								}
								$sql = substr($sql, 0, -2);
								unset($value);
							} else {
								$valuesIn = '';
								foreach ($sel->Value as $value) {
									$value = $this->db->sql_escape($value);
									$valuesIn.=$valuesIn . $value . ",";
								}
								$valuesIn = substr($valuesIn, 0, -1);

								$sql = "ContentPlain IN (" . $valuesIn . ")"; // Nr6
							}
						} else {
							$sql = "ContentPlain = '' OR ContentPlain IS NULL";
						}
						break;
					case 'All': {
							foreach ($sel->Value as $value) {
								$sql.= " ContentPlain like '%" . MULTIVALUE_SEP_CHAR . $this->db->sql_escape($value, false) . MULTIVALUE_SEP_CHAR . "%' and"; // Nr8
							}
							$sql = substr($sql, 0, -3);
							break;
						}
					case 'Between':
						if (count($sel->Feature) > 1) {  // Nr9
							$value = floatval($sel->Value[0]);
							$sql = "ContentNumber <= {$value}";
							$sql2 = "ContentNumber >= {$value}";
						} else {
							$value = $sel->Value;
							array_walk($value, "floatval");
							$vals = array(min($value), max($value));
							$sql = "ContentNumber BETWEEN {$vals[0]} AND {$vals[1]}"; // Nr10
						}
						break;
					case 'Intersect': // Nr11
						// Cannot work with MultiFeature!
						$value = $sel->Value;
						array_walk($value, "floatval");
						sort($value);
						// MIN must be <= Upper Bound
						$sql = "ContentNumber <= " . end($value);
						// MAX must be >= Lower Bound
						$sql2 = "ContentNumber >= " . $value[0];
						break;
					case 'Less': // Nr12
						if (count($sel->Value) > 0 && strlen($sel->Value[0]) > 0) {
							$sql = "ContentNumber <=" . floatval($sel->Value[0]);
						}
						break;
					case 'Greater': // Nr13
						if (count($sel->Value) > 0 && strlen($sel->Value[0]) > 0) {
							$sql = "ContentNumber >=" . floatval($sel->Value[0]);
						}
						break;
					case 'Fulltext':// Nr14
						$request->FulltextTerm = trim($sel->Value[0]);
						$request->IsFulltext = true;
						// ->Continue loop
						continue 2;
					case 'Custom':
						// Nothing to do here
						// ->Continue loop
						continue 2;
					default:
						throw new Exception("Unknown selection type: $sel->Type");
				}

				$request->include[] = $sql;
				$request->features = array_merge($request->features, $sel->Feature);
				if (isset($sql2)) {
					$ct = count($request->include);
					$request->include[] = $sql2;
					$request->pairedFeatures[] = $ct;
					$request->pairedFeatures[] = $ct - 1;
				} else {
					$request->pairedFeatures[] = null;
				}
				unset($sql2);
			}
		}
		$this->timetracker->timeTrackStop();
		return $request;
	}

	/**
	 * @param boolean $isFullText 
	 * @return mS3CommerceSearchRecordFetcher
	 */
	private function getRecordFetcher($isFullText) {
		$recordFetcher = null;
		if ($isFullText) {
			switch (MS3C_SEARCH_BACKEND) {
				//get fulltext neededed information (fulltext table name,mode and keyword )
				case 'MySQL':
					$recordFetcher = new mS3CommerceMySQLFulltextRecordFetcher($this);
					break;
				case 'ElasticSearch':
					$recordFetcher = new mS3CommerceElasticSearchRecordFetcher($this);
					break;
				default:
					$recordFetcher = new mS3CommerceSearchMySQLRecordFetcher($this);
			}
		} else {
			$recordFetcher = new mS3CommerceSearchMySQLRecordFetcher($this);
		}

		return $recordFetcher;
	}

	/**
	 * Create and fill temporary table with all products Ids that exist within a range
	 * of Elements(Groups,products,documents) constrained by the menu context 
	 * OR if it is a FULLTEXTSEARCH filled with records that match values in a fulltextable-column 
	 * against a given  keyword  
	 * @param array $menutype (posible values:document,group,product)
	 * @param object $quey is a request object
	 * @param type $sql_out for debugging purpouses
	 * @return nothing
	 */
	private function prepareElement($menutypes, $query, &$sql_out) {
		$this->timetracker->timeTrackStart("prepareElement");
		$recordFetcher = $this->getRecordFetcher($query->IsFulltext);

		$recordFetcher->setup($query, $menutypes);
		$this->timetracker->timeTrackStop();
		return $recordFetcher;
	}

	private function filterRestrictions($query) {
		$this->timetracker->timeTrackStart("filterRestrictions");
		if ($this->template->restrictionFeatureId) {
			$this->markRestrictions('Restrictions', $this->template->restrictionValues, true);
		}
		if ($this->template->userRightsFeatureId) {
			$this->markRestrictions('Rights', $this->template->userRightsValues, false);
		}

		$this->custom->customUnmarkSearchRestrictions('TempSearch', $query);

		$delSql = "DELETE FROM TempSearch WHERE delRestrictions = 1 OR delRights = 1";

		$this->db->sql_query($delSql);
		$this->timetracker->timeTrackStop();
	}

	private function filterRestrictionsByValue($request, $restrictionVals, $userRightsVals) {
		if (!is_null($restrictionVals)) {
			$this->markRestrictions('Restrictions', $restrictionVals, true);
		}
		if (!is_null($userRightsVals)) {
			$this->markRestrictions('Rights', $userRightsVals, false);
		}

		//$this->custom->customUnmarkSearchRestrictions('TempSearch', $query);

		$delSql = "DELETE FROM TempSearch WHERE delRestrictions = 1 OR delRights = 1";

		$this->db->sql_query($delSql);
	}

	private function getDistinctFieldCount($field, $cond = '') {
		$countSql = "SELECT COUNT(*) FROM (SELECT DISTINCT $field FROM TempSearch $cond) tmp";
		$res = $this->db->sql_query($countSql);
		if (!$res) {
			return 0;
		}
		$row = $this->db->sql_fetch_row($res);
		$this->db->sql_free_result($res);
		if (!$row) {
			return 0;
		}
		return $row[0];
	}

	private function getElementCount($cond = '') {
		$countSql = "SELECT COUNT(*) FROM TempSearch $cond";
		$res = $this->db->sql_query($countSql);
		if (!$res) {
			return 0;
		}
		$row = $this->db->sql_fetch_row($res);
		$this->db->sql_free_result($res);
		if (!$row) {
			return 0;
		}
		return $row[0];
	}

	/**
	 * marks all the records from TempTable that not meets the user or parameter Rights 
	 * @param type $elemType describe if is Product or a Document
	 */
	private function markRestrictions($rightsType, $values, $visibleIfNoRights) {
		$condition = "";
		if (empty($values) && $visibleIfNoRights) {
			// No values to match, and should be visible in that case => NOOP
			return;
		}

		foreach ($values as $val) {
			$condition.=" CONCAT(';',$rightsType,';') COLLATE utf8_bin NOT LIKE '%;$val;%' AND";
			$condition.=" CONCAT('" . MULTIVALUE_SEP_CHAR . "',$rightsType,'" . MULTIVALUE_SEP_CHAR . "') COLLATE utf8_bin NOT LIKE '%" . MULTIVALUE_SEP_CHAR . "$val" . MULTIVALUE_SEP_CHAR . "%' AND ";
		}
		$condition = substr($condition, 0, -4);
		if (!empty($condition)) {
			$condition = "AND  ( $condition )";
		}

		$delSql = "UPDATE TempSearch SET del$rightsType = 1 WHERE $rightsType <>'' $condition  ";

		$this->db->sql_query($delSql);
	}

	/**
	 * if any include criteria is set by user selection, then set those records as selected
	 * if no includes exists, all records are set as selected
	 * @param type $menutype
	 * @param type $featIds
	 * @param type $includes
	 * @param type $language
	 * @param type $level
	 * @param type $query
	 * @return number of selected records
	 */
	private function setSelected($menutypes, $featIds, $includes, $language, $level, $query, &$hasCustomSelection) {
		$this->timetracker->timeTrackStart("setSelected");
		//check if includes is really empty 
		$values = 0;
		foreach ($includes as $incl) {
			if (!empty($incl)) {
				$values++;
			}
		}

		// Perform custom selection
		$hasCustom = $this->custom->customSearchFilterSelection($menutypes, 'TempSearch', $language, $level, $query);
		if ($hasCustom) {
			$hasCustomSelection |= true;
		}

		$num = 0;
		foreach ($menutypes as $menutype) {
			list($tid, $tvalue,, $elemType) = mS3CommerceSearchRecordFetcher::getTableInfo($menutype, false);
			$productTables = array($tvalue);
			$where = '';

			if ($values > 0) {
				// Set selection for single filters
				foreach ($includes as $i => $sql) {
					if (!empty($includes[$i]) && (!$featIds[$i] == null) && ($i <= $level || $level < 0)) {
						$updateSql = "UPDATE TempSearch as t1\r\n";
						$updateSql.=" INNER JOIN(SELECT $tid from  $tvalue WHERE LanguageId=$language AND(FeatureId=" . $featIds[$i] . " AND (" . $includes[$i] . "))) a$i ON t1.id=a$i.$tid AND ElemType = '$elemType'\r\n";
						$updateSql.="SET selcol" . $i . "=1";
						$result = $this->db->sql_query($updateSql, $productTables);
						//$num+=$this->db->sql_affected_rows();
						$where.=" selcol" . $i . "=1 AND";
					}
				}
				//set selall to 1 for all products that match all includes
				$where .= " selcustom=1 AND ElemType = '$elemType' AND selall = 0 ";
				$where = " WHERE " . $where;
				$updateSelAll = "UPDATE TempSearch as t1 SET selall=1" . $where . "\r\n";
				$result = $this->db->sql_query($updateSelAll);
				$num += $this->db->sql_affected_rows();
			} else {
				// no filters so every record is set as selected (value 1)
				$updateSql = "UPDATE TempSearch as t1\r\n";
				$setColumns.="SET t1.selall=1";
				$updateSql.=$setColumns . " WHERE selcustom=1 AND ElemType = '$elemType'";
				$result = $this->db->sql_query($updateSql);
				$num += $this->db->sql_affected_rows();
			}
		}

		$this->timetracker->timeTrackStop();
		return $num;
	}

	/*
	 * Runs a query against both product values and feature value sets.
	 * @param query - Jagged array of query information in and out.
	 * INPUT:
	 * query["market] Current market id of this query. Req.
	 * query["language"] Current language id of this query. Req.
	 * query["menus"] Optional array of menuIds with which to constrain the resuls
	 * query["mustKey"] Optional key
	 * query["mustValues"] Optional array of must FeatureValueLookupIds all base products must have.
	 * query["features"] Array of strings of filter feature names.
	 * query["returns"] Array of strings of return feature names. Optional.
	 * query["exclude"] feature id, sql pairs siting what we should exclude.
	 * query["include"] feature id, sql pairs siting what we should include.
	 * query["level"] Used only in hierarchical. Optional.
	 * query["start"] Start, i.e. 0 
	 * query["limit"] Limit - Maximum number of records to return.
	 * OUTPUT:
	 * query["status"] Empty is successful, otherwise contains an error message.
	 * query["total"] This is the total number of records available without limit.
	 * query["beginning"] - Which record starting from 1 that we are returning.
	 * query["end"] - The last record starting from 1 that we are returning.
	 * query["time"] - The total time spent in this routine.
	 * query["products"] - Jagged array of return features, with product id's as keys
	 * 	Each sub-array is a list with keys named by query["return"] and then the value of that feature.
	 * query["filter"] - Array where each key is the name of a feature. -- Comes from $query["features"]
	 * 		["id"] - Id of this feature.
	 * 		["name"] - Name of the feature for this filter.
	 * 		["title"] - Title for this feature in the current language.
	 * 		["unit"] - Unit symbol for this feature.
	 * 		["values"] - All possible FeatureValueLookupIds are returned depending upon level.
	 * 			If level = -1 then all possible values for all filter features are returned.
	 * Note: You can reset the query by setting level=-1 and exclude=empty.
	 */

	public function runQuery($query) {
		$this->timetracker->timeTrackStart("runquery");

		$this->setupCustom();

		$this->db->sql_query('SET SQL_BIG_SELECTS=1;');
		$query = $this->custom->adjustSearchRequest($query);
		$time = microtime(true);
		$query = $this->preprocessRequest($query);
		$language = intval($query->Language);
		$market = intval($query->Market);
		$out = new stdClass();
		$out->Status = "";
		$out->Beginning = $out->End = 0; // Invalid value.
		$out->Hierarchy = new stdClass();

		$query->Shop = $this->dbutils->getShopId($language, $market);

		switch ($query->UpdateType) {
			case 'all':
				$level = -1;
				break;
			case 'none':
				$level = 1000;
				break;
			case 'fromproducts':
				$level = -1;
				break;
			case 'hierarchy':
			default:
				$level = $query->Level;
		}

		$out->ids = $query->features;

		$result_types = $query->ResultTypes;
		$xxx = "";
		$total = 0;
		$start = intval($query->Start);
		$limit = intval($query->Limit);

		$query->restrictionFeatureId = $this->template->restrictionFeatureId;
		$query->userRightsFeatureId = $this->template->userRightsFeatureId;
		$query->restrictionValues = $this->template->restrictionValues;
		$query->userRightsValues = $this->template->userRightsValues;
		$query->distinctResults = $this->template->conf['distinct_result'] == 1 ? true : false;

		// Fetch records until there are enough for our request (<0 means all)
		if ($limit < 0) {
			// Load all
			$requiredCount = -1;
		} else if ($limit == 0) {
			// Load nothing
			$requiredCount = 0;
		} else {
			// Load as much as we need
			$requiredCount = $start + $limit;
		}

		$loadAll = $requiredCount < 0;
		// Special case: if filters are required, we must always fetch all...
		if (!empty($query->Filter)) {
			$loadAll = true;
		}
		if ($query->WithHierarchy) {
			$loadAll = true;
		}

		$fetcher = $this->prepareElement($result_types, $query, $xxx);
		$count = 0;
		$hasCustomSelection = false;
		$this->timetracker->timeTrackStart("FetchLoop");
		while ($count < $requiredCount || $loadAll) {
			// If current fetcher has no more data, go to next one
			$this->timetracker->timeTrackStart("fetching");
			$hasData = $fetcher->fetchMore();
			$this->timetracker->timeTrackStop();
			if (!$hasData) {
				break;
			} else {
				// Filter out restrictions and apply filter selection
				$this->filterRestrictions($query);
				$count += $this->setSelected($result_types, $out->ids, $query->include, $language, $level, $query, $hasCustomSelection);
			}
		}
		$this->timetracker->timeTrackStop();

		$out->Total = $fetcher->getTotalCount();
		$out->debug = $xxx;

		// We have all product ids now in the temporary table, lets get a slice of the products.
		// If we loaded everything just because of filters, don't create output
		if ($count > 0 && $requiredCount != 0) {
			// Get the elements
			$res = $this->getElement($result_types, $language, $market, $query->Results, $start, $limit, $query->Order, $fetcher->getOrderClause(), $query->includeFeatureValues, $query->includeLinks);

			$out->Product = $res['P'];
			$out->Group = $res['G'];
			$out->Document = $res['D'];

			if ($query->WithHierarchy) {
				$out->Hierarchy->Product = $this->getHierarchy('P', $fetcher->getOrderClause());
				$out->Hierarchy->Group = $this->getHierarchy('G', $fetcher->getOrderClause());
				$out->Hierarchy->Document = $this->getHierarchy('D', $fetcher->getOrderClause());
			}

			$out->Beginning = $query->Start + 1;
			$out->End = $query->Start + count($out->Product) + count($out->Document) + count($out->Group);
		} else {
			$out->Product = array();
			$out->Group = array();
			$out->Document = array();
			$out->Hierarchy->Product = array();
			$out->Hierarchy->Group = array();
			$out->Hierarchy->Document = array();
			$out->Beginning = 0;
			$out->End = 0;
		}

		// Let's get all the feature parameters
		if (!empty($query->Filter)) {
			switch ($query->UpdateType) {
				case 'none':
					$out->Filter = array();
					break;
				case 'hierarchical':
				/*
				  if (in_array('product', $result_types)) {
				  $out->Filter = $this->getFilterFeatures('product', $language, $market, $query->Filter, $level);
				  }
				  if (in_array('document', $result_types)) {
				  $out->Filter = $this->getFilterFeatures('document', $language, $market, $query->Filter, $level);
				  }
				  if (in_array('group', $result_types)) {
				  $out->Filter = $this->getFilterFeatures('group', $language, $market, $query->Filter, $level);
				  }
				 */
				// Fallthrough
//						break;
				case 'fromproducts':
					/*
					  if (in_array('product', $result_types)) {
					  $out->Filter = $this->getFilterFeatures('product', $language, $market, $query->Filter, $level);
					  }
					  if (in_array('document', $result_types)) {
					  $out->Filter = $this->getFilterFeatures('document', $language, $market, $query->Filter, $level);
					  }
					  if (in_array('group', $result_types)) {
					  $out->Filter = $this->getFilterFeatures('group', $language, $market, $query->Filter, $level);
					  }
					 */
					$out->Filter = $this->getFilterFeatures($result_types, $language, $market, $query->Filter, $level, $hasCustomSelection);
					break;
				case 'all':
					$out->Filter = $this->getFilterFeaturesForAll($result_types, $query->features, $query->pairedFeatures, $query->Menu, $language, $market, $level, $query->Filter, $hasCustomSelection);
					break;
			}
		}



		$fetcher->destroy();
		$time2 = microtime(true);
		$out->time = $time2 - $time;
		$out->t1 = $time;
		$out->t2 = $time2;
		//$out->sql = $GLOBALS["sqlDebug"];
		$this->timetracker->timeTrackStop();
		return $out;
	}

	/**
	 * Performs the search given the input and returns a productid array (object Product member of search result object)
	 * @param int $languageId 
	 * @param array feature names that we want back. since we can't display all the features for a product at once, 
	 * also in this case feature is an array of features to be displayed in the output mask
	 * @param int $start Start record.
	 * @param int $limit Number of records to return.
	 * @param array $orderFeatures two dimentional array ["feature"]=>columns to sort ["sort"]=>ascending descending 
	 * @return array Returns an array of all products with $returnFeatures features in each row.
	 */
	private function getElement($menutypes, $language, $market, $features = null, $start = 0, $limit = null, $orderFeatures = null, $orderRelevance = '', $includeFeatureValues = false, $includeLinks = false) {
		$this->timetracker->timeTrackStart("getElement");
		$postfixes = array();
		foreach ($menutypes as $mt) {
			list($tid, $tvalue,, $pf) = mS3CommerceSearchRecordFetcher::getTableInfo($mt, false);
			$postfixes[] = array('id' => $tid, 'table' => $tvalue, 'pf' => $pf);
		}

		$resElems = array();

		if ($limit === null || $limit < 0) {
			$limitStr = "";
		} else {
			$limitStr = "LIMIT $start,$limit ";
		}


		// Get feature values for required features
		$ids = $features;
		if (!empty($ids)) {
			$columns = "";
			$joins = "";
			$tables = [];
			$sortString = "";
			//query building with dynamic joins
			foreach ($ids as $key => $value) {

				$includeThisFeature = $includeFeatureValues;
				if ($orderFeatures != NULL) {
					// orderFeatures (2n dim.Array) contains the Sort columns and sort direction if there are some sortcolumns
					// they need to be included in the select columns string and create a list of sorting columns in the ORDER BY statement
					// therefore two columns are used: ContentNumber containing numerical values (if this feature contains num values) and
					// ContentPlain for Alphabetical sort if no numeric values exist
					foreach ($orderFeatures as $colArr) {
						$sortFeatureId = $colArr[0];
						if ($value == $sortFeatureId) {
							// Also get values for sorting
							$colN = $colP = '';
							foreach ($postfixes as $pf) {
								$colN .= "pv${key}_{$pf['pf']}.ContentNumber,";
								$colP .= "pv${key}_{$pf['pf']}.ContentPlain,";
							}
							$columns.=",coalesce($colN NULL) as '${value}ContentNumber' ,coalesce($colP NULL) as '${value}ContentPlain'";

							$includeThisFeature = true;
						}
					}
				}

				// Don't include feature values if not sorting by this or explicitely requested
				if (!$includeThisFeature) {
					continue;
				}

				//columns list and sorting if needed 
				$col = '';
				foreach ($postfixes as $pf) {
					$col .= "pv${key}_{$pf['pf']}.ContentHtml,";
				}
				$columns.= ",coalesce($col NULL) as '${value}'";

				foreach ($postfixes as $pf) {
					$joins.= "LEFT JOIN {$pf['table']} pv${key}_{$pf['pf']} ON " .
							"pf.id=pv${key}_{$pf['pf']}.{$pf['id']} AND " .
							"pv${key}_{$pf['pf']}.LanguageId=$language AND " .
							"pv${key}_{$pf['pf']}.FeatureId=$value AND " .
							"pf.ElemType='{$pf['pf']}'\n";
					$tables[] = "{$pf['table']} pv${key}_{$pf['pf']}";
				}
			}

			$limitStrInner = "";

			// Make ORDER BY text
			if ($orderFeatures != NULL) {
				foreach ($orderFeatures as $colArr) {
					$sortString.="`${colArr[0]}ContentNumber` ${colArr[1]},`${colArr[0]}ContentPlain` ${colArr[1]},";
				}
			} else {
				$limitStrInner = $limitStr;
				$limitStr = "";
			}

			$sortString .= $orderRelevance;

			$sortString .= " pf.menuId";

			//$columns = substr($columns, 0, -1);
			$sql = "SELECT pf.id As Id,pf.MenuId AS MenuId,pf.ElemType AS ElemType " . $columns
					. "\n FROM (SELECT id,MenuId,ElemType FROM TempSearch WHERE id IS NOT NULL AND selall=1 $limitStrInner) pf\n"
					. $joins
					. " ORDER BY $sortString $limitStr";
			$tables[] = 'Feature f';
		} else {  // If we do not want any features, just return the product ids
			$sql = "SELECT DISTINCT p.id As Id, p.MenuId AS MenuId,p.ElemType AS ElemType, '' As Name".($orderRelevance == "Relevance DESC," ? ", Relevance ":"")."  FROM TempSearch  p WHERE p.selall = 1 ORDER BY ";
			$sql .= $orderRelevance;
			$sql .= " p.menuId $limitStr";
			$tables = '';
		}

		// Get the elements
		$result = $this->db->sql_query($sql, $tables);
		if ($result) {
			$resElems = array('P' => array(), 'G' => array(), 'D' => array());
			$linkFunctions = array(
				'P' => 'getProductLink',
				'G' => 'getGroupLink',
				'D' => 'getDocumentLink'
			);
			while ($elem = $this->db->sql_fetch_object($result)) {
				$type = $elem->ElemType;
				$tlink = $linkFunctions[$type];
				if ($includeLinks) {
					if ($this->plugin != null) {
						$link = $this->plugin->$tlink($elem->Id, $elem->MenuId);
					} else {
						$link = $this->custom->$tlink($elem->Id, $elem->MenuId, true);
					}
				} else {
					$link = null;
				}
				$resElems[$type][] = array(
					"Id" => $elem->Id,
					"MenuId" => $elem->MenuId,
					"Values" => $elem,
					"Link" => $link);
			}
		} else {
			$err = $this->db->sql_error();
		}

		$this->timetracker->timeTrackStop();

		return $resElems;
	}

	private function getHierarchy($elemType, $orderClause = '') {
		// Menu Path now already in tempsearch
		//$sql = "SELECT t.Id, m.Path FROM TempSearch t, Menu m WHERE t.MenuId = m.Id AND t.selall = 1 AND t.ElemType = '$elemType' ORDER BY t.MenuId";
		$orderClause .= ' MenuId';
		$sql = "SELECT Id, MenuPath, MenuId FROM TempSearch WHERE selall = 1 AND ElemType = '$elemType' ORDER BY $orderClause";

		$result = $this->db->sql_query($sql);
		$ret = array();
		while ($row = $this->db->sql_fetch_row($result)) {
			$ret[$row[0]][$row[2]] = $row[1];
		}
		$this->db->sql_free_result($result);
		return $ret;
	}

	/**
	 * Return an stdobject containing information about the given features ids ($filters param)
	 * @param type $language Language id
	 * @param type $market Market id
	 * @param type $ids Feature IDs 
	 * Returns the filterInfo (object) array
	 */
	private function getFilterFeatures($menutypes, $language, $market, $filter, $level = -1, $hasCustomSelection = false) {
		$this->timetracker->timeTrackStart("getFilterFeatures");
		$filterInfo = array();
		$ids = $filter;
		$index = 0;

		foreach ($menutypes as $mt) {

			list($tid, $tvalue) = mS3CommerceSearchRecordFetcher::getTableInfo($mt, false);

			// Remove "Custom" as it is not a feature (handled later by custom module)
			$customIdx = array_search('Custom', $ids);
			if ($customIdx !== false) {
				unset($ids[$customIdx]);
			}

			$filter_where = "";
			if($ids){
				$filter_where = " AND f.Id IN (" . implode(",", $ids) . ") ";
			}

			// Get the values for all filter features
			$sql = "SELECT DISTINCT f.Name,f.Id,f.IsMultiFeature,pv.ContentNumber,pv.ContentPlain,pv.ContentHtml,fv.Title,fv.UnitToken,COUNT(pv.ContentPlain) AS Count\r\n"
					. " FROM $tvalue pv\r\n"
					. " INNER JOIN TempSearch p ON p.Id=pv.$tid  \r\n"
					. " INNER JOIN Feature f ON f.Id=pv.FeatureId\r\n"
					. " INNER JOIN FeatureValue fv ON fv.FeatureId=f.Id\r\n"
					. " WHERE pv.LanguageId=$language AND f.MarketId=$market AND pv.LanguageId=$language $filter_where AND p.selall=1 \r\n"
					. " GROUP BY f.Id,fv.Title,fv.UnitToken,pv.ContentPlain,pv.ContentNumber,pv.ContentHtml\r\n"
					. " ORDER BY f.Id,pv.ContentNumber,pv.ContentPlain";
			$result = $this->db->sql_query($sql, "$tvalue pv, Feature f, FeatureValue fv");
			if ($result) {
				$temp = array();
				$counts = array();
				while ($feature = $this->db->sql_fetch_object($result)) {
					$temp[$feature->Id]["Feature"] = $feature->Name;
					$temp[$feature->Id]["Id"] = $feature->Id;

					if ($feature->IsMultiFeature) {

						// Undo Multifeature (split by separator)
						$temp[$feature->Id]["IsMulti"] = true;

						if (!isset($temp[$feature->Id]["Values"])) {
							$temp[$feature->Id]["Values"] = array();
							$temp[$feature->Id]["Counts"] = array();
							$temp[$feature->Id]["Numbers"] = array();
							$temp[$feature->Id]["HTMLs"] = array();
						}

						$newVals = array_filter(explode(MULTIVALUE_SEP_CHAR, $feature->ContentPlain));
						foreach ($newVals as $v) {
							// Must always add, to enable HTML reduction below
							$temp[$feature->Id]["Values"][] = $v;
							$temp[$feature->Id]["Numbers"][] = floatval($v);
							$temp[$feature->Id]["Counts"][] = $feature->Count;
						}

						// Undo Mutlifeature (extract ul/li contents)
						if (preg_match('#<ul[^>]*>(.*)</ul>#ism', $feature->ContentHtml, $match)) {
							preg_match_all('#<li[^>]*>(.*)</li>#iUsm', $match[1], $vals);
							$temp[$feature->Id]["HTMLs"] = array_merge($temp[$feature->Id]["HTMLs"], $vals[1]);
						} else if ($feature->ContentHtml == $feature->ContentPlain) {
							// Special case for faked MutliFeature: HTML and Plain are equal!
							// Use split Plain values as HTML, but wrap every element
							foreach ($newVals as $v) {
								$temp[$feature->Id]["HTMLs"][] = "<span class=\"mS3Commerce {$feature->Name}\">$v</span>";
							}
						} else {
							$temp[$feature->Id]["HTMLs"][] = $feature->ContentHtml;
						}
					} else {
						$temp[$feature->Id]["IsMulti"] = false;
						$temp[$feature->Id]["Numbers"][] = floatval($feature->ContentNumber);
						$temp[$feature->Id]["Values"][] = $feature->ContentPlain;
						$temp[$feature->Id]["HTMLs"][] = $feature->ContentHtml;
						$temp[$feature->Id]["Counts"][] = $feature->Count;
					}

					$temp[$feature->Id]["Title"] = $feature->Title;
					$temp[$feature->Id]["Unit"] = $feature->UnitToken;
				}
			}
		}


		// Restructure filter data
		for ($index = $level + 1; $index < count($ids); ++$index) {
			if ($ids[$index] == null)
				continue;
			if (array_key_exists($ids[$index], $temp)) {
				// Copy into target array
				$filterInfo[$index] = $temp[$ids[$index]];

				// Consolidate multi features
				if ($filterInfo[$index]["IsMulti"]) {
					// Find all entries with same value, and sum up their counts
					$uniqueVals = array_unique($filterInfo[$index]["Values"]);
					$counts = array();
					foreach ($uniqueVals as $v) {
						$indexMap = array_keys($filterInfo[$index]["Values"], $v);
						foreach ($indexMap as $idx) {
							$counts[$indexMap[0]] += $filterInfo[$index]["Counts"][$idx];
						}
					}

					// Remove duplicate values
					$filterInfo[$index]["Values"] = $uniqueVals;
					$filterInfo[$index]["HTMLs"] = array_intersect_key($filterInfo[$index]["HTMLs"], $filterInfo[$index]["Values"]);
					$filterInfo[$index]["Numbers"] = array_intersect_key($filterInfo[$index]["Numbers"], $filterInfo[$index]["Values"]);

					// Re-index
					$filterInfo[$index]["Values"] = array_values($filterInfo[$index]["Values"]);
					$filterInfo[$index]["HTMLs"] = array_values($filterInfo[$index]["HTMLs"]);
					$filterInfo[$index]["Numbers"] = array_values($filterInfo[$index]["Numbers"]);
					$filterInfo[$index]["Counts"] = array_values($counts);
				}
			} else {
				$filterInfo[$index] = null;
			}
		}

		if ($customIdx !== false) {
			$customFilter = $this->custom->getCustomFilterValues($menutypes, 'TempSearch', $language, $market, $level);
			if ($customFilter) {
				$customFilter['Id'] = 'Custom';
				$filterInfo[$customIdx] = $customFilter;
			}
		}

		$this->timetracker->timeTrackStop();
		return $filterInfo;
	}

	/**
	 * Fill the Object FilterInfo with Feature,Values[],HTMLs[],Numbers[] 
	 * @param type $include array of selected featureid
	 * @param type $pairedFeatures, array of paired features (e.g Slider from to has 2 values)
	 * @param type $menus context menu    
	 * @param type $language context language
	 * @param type $market context market
	 * @param type $featureIds all selectable featureIds
	 * @param type $level 
	 * @param type $filter array of filter names
	 * @return type $retFilter
	 */
	private function getFilterFeaturesForAll($menutype, $selectedFeatures, $pairedFeatures, $menus, $language, $market, $level, $filter, $hasCustomSelection) {

		//no includes= no selections = all possible Values for all possible Features
		if (empty($selectedFeatures) && !$hasCustomSelection) {
			return $this->getFilterFeatures($menutype, $language, $market, $filter, $level, $hasCustomSelection);
		}

		$this->timetracker->timeTrackStart("getFilterFeaturesForAll");
		//Return array with Filters-values for controls
		$retFilters = array();

		//all the Features not selected by the user yet
		$missingFeatures = array_values(array_diff($filter, $selectedFeatures));

		if ($hasCustomSelection) {
			if (($idx = array_search('Custom', $missingFeatures)) !== false) {
				unset($missingFeatures[$idx]);
			}
		}

		$missingCount = count($missingFeatures);
		// handling missingfeatures

		if ($missingCount > 0) {
			//tempProduct Temporary table still containing the Prod Id for the output this is the output product set.
			//1)find possible values for not selected features(missing features)
			//2)for the set of selected products find the possible features values that this Products may have 

			$filters = $this->getFilterFeatures($menutype, $language, $market, $missingFeatures, $level); //#1
			$retFilters = array_merge($retFilters, $filters);
		}

		//#2
		if ($hasCustomSelection) {
			// Apply all filters, except custom
			$num = count($selectedFeatures);
			$cols = "";
			for ($j = 0; $j < $num; $j++) {
				$cols.=" selcol" . $j . "=1 AND";
			}
			if (!empty($cols)) {
				$where = "WHERE $cols";
			}
			$ret = $this->db->sql_query("UPDATE TempSearch set selall=1 $where");
			$customFilters = $this->custom->getCustomFilterValues($menutype, 'TempSearch', $language, $market, $level);
			if ($customFilters) {
				$customFilters['Id'] = 'Custom';
				$retFilters[] = $customFilters;
			}
		}

		foreach ($selectedFeatures as $i => $inc) {
			// Do nothing if already handled
			if (array_search($inc, $missingFeatures) === false) {
				$curFeature = array($inc);
				$i2 = $i;

				// Check if feature is paired
				if ($pairedFeatures[$i] != NULL) {
					if (array_key_exists($i, $pairedFeatures)) {
						// Check both in one step
						$i2 = $pairedFeatures[$i];
						$curFeature[] = $selectedFeatures[$i2];
						// Ignore the paired feature (don't check again)
						$missingFeatures[] = $selectedFeatures[$i2];
					}
				}
				//build the where condition dinamically to update tempProd 
				$num = count($selectedFeatures);
				$where = "WHERE ";
				$cols = "";
				for ($j = 0; $j < $num; $j++) {
					if ($j != $i && $j != $i2) {
						$cols.=" selcol" . $j . "=1 AND";
					}
				}
				$where = $where . $cols . " selcustom = 1";

				if ($cols == "") {
					//Table reset $cols not set, means no selection was taken and all products are the output
					$ret1 = $this->db->sql_query("UPDATE TempSearch set selall=1 WHERE selcustom = 1");
				} else {
					//reset tenpProd as no selection 
					$ret1 = $this->db->sql_query("UPDATE TempSearch set selall=0");
					//set a flag for all products that match the currentFeatures  this is the sub-set!
					$ret2 = $this->db->sql_query("UPDATE TempSearch set selall=1 $where");
				}
				// Get possible Feature-values for the product sub-set!
				$filters = $this->getFilterFeatures($menutype, $language, $market, $curFeature, $level);
				$retFilters = array_merge($retFilters, $filters);
			}
		}

		$this->timetracker->timeTrackStop();
		return $retFilters;
	}

	//////////////////////////////////////////////////
	///			CONSOLIDATION PART					//
	//////////////////////////////////////////////////


	public function consolidateResultsParent($result) {
		// Can only have groups...
		$this->timetracker->timeTrackStart("Consolidate results parent");
		$consolidatedResult = new stdClass();
		$consolidatedResult->Product = array(); // Empty
		$consolidatedResult->Group = array(); // Id, MenuId
		$consolidatedResult->Document = array(); // Empty

		$consolidatedResult->Hierarchy = new stdClass();
		$consolidatedResult->Hierarchy->Product = array(); // Empty
		$consolidatedResult->Hierarchy->Group = array(); // MenuId=>Path
		$consolidatedResult->Hierarchy->Document = array(); // Empty

		$consolidatedResult = $this->doConsolidateParent($consolidatedResult, 'Product', $result->Hierarchy->Product);
		$consolidatedResult = $this->doConsolidateParent($consolidatedResult, 'Document', $result->Hierarchy->Document);
		$consolidatedResult = $this->doConsolidateParent($consolidatedResult, 'Group', $result->Hierarchy->Group);

		$consolidatedResult->Total = count($consolidatedResult->Group);

		$this->timetracker->timeTrackStop();

		return $consolidatedResult;
	}

	public function consolidateResults($result, $consolidationMenus) {
		$this->timetracker->timeTrackStart("Consolidate results");
		//modified result object containing the consolidated results
		$consolidatedResult = new stdClass();
		$consolidatedResult->Product = array(); // Id, MenuId
		$consolidatedResult->Group = array(); // Id, MenuId
		$consolidatedResult->Document = array(); // Id, MenuId
		$consolidatedResult->Status = '';
		$consolidatedResult->Beinning = 0;
		$consolidatedResult->End = 0;
		$consolidatedResult->Filter = $result->Filter;
		$consolidatedResult->Total = 0;

		$consolidationHierarchy = $this->template->getMenuPaths($consolidationMenus, true);

		//Documents
		$productHierarchy = $result->Hierarchy->Document;
		$consolidatedResult = $this->doConsolidate($consolidatedResult, 'Document', $consolidationHierarchy, $productHierarchy);

		//Products
		$productHierarchy = $result->Hierarchy->Product;
		if (!empty($consolidationHierarchy)) {
			$consolidatedResult = $this->doConsolidate($consolidatedResult, 'Product', $consolidationHierarchy, $productHierarchy);
		}
		//Groups
		$productHierarchy = $result->Hierarchy->Group;
		$consolidatedResult = $this->doConsolidate($consolidatedResult, 'Group', $consolidationHierarchy, $productHierarchy);

		// TODO: Fix sorting of results
		// Get Group Children of all SearchMenus
		// Reorder Consolidated Result by order groups:
		//foreach (searchmenu) -> GetGroupChildren ==> Defines order for CR->Group
		// Analog for Document / Product


		$consolidatedResult->Total = count($consolidatedResult->Document) + count($consolidatedResult->Product) + count($consolidatedResult->Group);

		$this->timetracker->timeTrackStop();
		return $consolidatedResult;
	}

	/**
	 * 
	 * @param type $consolidatedResult (result object to be filled with consolidated results
	 * @param type $elemType (Product, Document or Groups)
	 * @param type $consolidationHierarchy (Groups that defines the consolidation)
	 * @param type $elementHierarchy (elements to be consolidated)
	 */
	private function doConsolidate($consolidatedResult, $elemType, $consolidationHierarchy, $elementHierarchy) {
		//iteration over results elements(products,documents,groups)
		foreach ($elementHierarchy as $elemId => $elements) {
			foreach ($elements as $elemMenuId => $elemPath) {
				//iterate over consolidation menus
				foreach ($consolidationHierarchy as $consolidationPath) {
					//find the consolidated-Menu-Path in the hit path
					$conMenPos = strpos($elemPath, $consolidationPath);
					if ($conMenPos !== false) {

						//strip out the consolidatedMenu out of the path
						$string = str_replace($consolidationPath . "/", '', $elemPath);
						//get the child menu 
						if ($string != '' && $consolidationPath != $elemPath) {
							//there are more than one children put it in group
							$pos = strpos($string, '/');
							if ($pos !== false) {
								$menuId = substr($string, 0, $pos);
							} else {
								$menuId = $string;
							}
							$res = $this->dbutils->selectMenu_SingleRow('GroupId', "Id=$menuId", true);
							$groupId = $res[0];
							$group = array();
							$group['Id'] = $groupId;
							$group['MenuId'] = $menuId;
							if (count($consolidatedResult->Group) > 0) {
								if (array_key_exists($groupId, $consolidatedResult->Group)) {
									$consolidatedResult->Group[$groupId]['ConsolidationCount'] ++;
								} else {
									$group['ConsolidationCount'] = 1;
									$consolidatedResult->Group[$groupId] = $group;
								}
							} else {
								$group['ConsolidationCount'] = 1;
								$consolidatedResult->Group[$groupId] = $group;
							}
						} else {
							//the children menu is the element itself Menu 
							$element = array('Id' => $elemId, 'MenuId' => $elemMenuId);
							switch ($elemType) {
								case 'Product' :
									$consolidatedResult->Product[] = $element;
									break;

								case 'Document' :
									$consolidatedResult->Document[] = $element;
									break;

								case 'Group' :
									$element['ConsolidationCount'] = 1;
									$consolidatedResult->Group[$elemId][$elemMenuId] = $element;
							}
						}
					}
				}
			}
		}
		return $consolidatedResult;
	}

	private function doConsolidateParent($consolidatedResult, $type, $hierarchy) {
		foreach ($hierarchy as $elemId => $element) {
			foreach ($element as $elementMenuId => $elemPath) {
				$elemPath = explode('/', $elemPath);
				$menuId = array_pop($elemPath);
				$res = $this->dbutils->selectMenu_SingleRow('GroupId', "Id=$menuId", true);
				$groupId = $res[0];
				$group = array();
				$group['Id'] = $groupId;
				$group['MenuId'] = $menuId;
				if (array_key_exists($groupId, $consolidatedResult->Group)) {
					$consolidatedResult->Group[$groupId]['ConsolidationCount'] ++;
				} else {
					$group['ConsolidationCount'] = 1;
					$consolidatedResult->Group[$groupId] = $group;
					$consolidatedResult->Hierarchy->Group[$groupId][$menuId] = join('/', $elemPath);
				}
			}
		}
		return $consolidatedResult;
	}

	//////////////////////////////////////////////////
	///			SUGGESTION PART						//
	//////////////////////////////////////////////////


	public function handlePageTypeCall(tx_ms3commerce_template $template) {
		$this->template->plugin->timeTrackStart("Suggestion handler");
		$term = $_GET["term"];
		$featureId = $_GET["feature"];

		if ($_GET["fulltext"] == 1) {
			$this->template->plugin->timeTrackStart("fulltext");
			// handle suggest request for auto completion with fulltext
			$result = $this->getSuggestionFullText($term);
			$this->template->plugin->timeTrackStop();
		} else {
			if (!is_numeric($featureId)) {
				$featureId = $template->dbutils->getFeatureIdByName($featureId);
			}
			// handle suggest request for auto completion
			$result = $this->getSuggestion($term, $featureId);
		}

		$ret = $result;



		/*
		  $this->template->plugin->timeTrackStart("Restructure");
		  $res = array();
		  foreach ($result as $Result) {
		  $obj = new stdClass();
		  $obj->value = $Result;
		  $res[] = $obj;
		  }
		  $this->template->plugin->timeTrackStop();
		  $this->template->plugin->timeTrackStop();

		  $ret = new stdClass();
		  $ret->values = $res;
		  $ret->dbg = $this->template->plugin->timeTrackPrint();
		 */
		$ret = $result;
		$ret = json_encode($ret);

		echo $ret;
		exit;
	}

	public function getSuggestion($term, $featureId) {

		// Get Suggestion based on normal, unconstrained Query


		$menu = $this->template->searchMenuIds;
		$selection = new stdClass();
		$selection->Feature = array($featureId);
		$selection->Value = $term;
		$selection->Type = "Contains";
		$selection->IsMultiFeature = FALSE;

		if (is_null($menu)) {
			$menu = array($this->template->rootMenuId);
		}
		$request = new stdClass();
		$request->Menu = $menu;
		$request->Selection = array($selection);
		$request->Filter = $selection->Feature;
		$request->Limit = 0;
		$request->UpdateType = "fromproducts";
		$request->Language = $this->template->languageId;
		$request->Market = $this->template->marketId;

		if (array_key_exists('result_types', $this->template->conf)) {
			$result_types = explode(';', $this->template->conf['result_types']);
			//convert all values to lowercase
			$request->ResultTypes = array_map('strtolower', $result_types);
		} else {
			$request->ResultTypes = array();
		}
		$request->includeFeatureValues = false;

		$result = $this->runQuery($request);
		$values = $result->Filter[0]['Values'];

		if (isset($this->template->conf['suggestDelimitChar'])) {
			$suggestDelimitChar = $this->template->conf['suggestDelimitChar'];

			$testedResult = array();
			$merged_result = array();

			// Split direct results by delimiter
			foreach ($values as &$result) {
				$result = explode($suggestDelimitChar, $result);
				$merged_result = array_merge($merged_result, $result);
			}
			$test = array_unique($merged_result);

			// Extract matching terms from delimited values
			foreach ($test as $a) {
				if (stripos($a, $term) !== false) {
					$testedResult[] = $a;
				}
			}
			$result = $testedResult;
		}

		if (isset($this->template->conf['suggestLimitResults'])) {
			$suggestLimitResults = $this->template->conf['suggestLimitResults'];
		} else {
			$suggestLimitResults = 10;
		}

		$result = array_slice($values, 0, $suggestLimitResults);

		return $result;
	}

	/**
	 *
	 * @param type $term ( the given searchstring)
	 * @param type $template: needed object for search 
	 * @return type 
	 */
	private function getSuggestionFullText($term) {
		$time = microtime(true);
		//create request object
		$this->template->plugin->timeTrackStart("Build query");
		$request = tx_ms3commerce_suggest_helper::config2Request($this->template, $term);
		$this->template->plugin->timeTrackStop();

		$this->template->plugin->timeTrackStart("suggest");
		$valuesList = $this->getSuggestionFullText_direct($request, $suggestLimitResults);
		$this->template->plugin->timeTrackStop();

		if ($request->SuggestSingleItems) {
			$valuesList = $this->layoutSuggestItems($request, $valuesList);
		}

		$time2 = microtime(true);
		$totaltime = $time2 - $time;
		//$valuesList = array_slice($valuesList, 0, $suggestLimitResults);

		return $valuesList;
	}

	private function layoutSuggestItems($request, $values) {
		$tmpl = $this->plugin->getTemplate('###SUGGESTITEM###');
		if (empty($tmpl)) {
			$suggestLayout = tx_ms3commerce_suggest_helper::config2SuggestLayout($this->template);
			return $this->layoutSuggestItemsSimple($values, $suggestLayout);
		}

		// Use layout engine
		$gTmpl = $this->template->tplutils->getSubpart($tmpl, '###GROUP###');
		$pTmpl = $this->template->tplutils->getSubpart($tmpl, '###PRODUCT###');
		$dTmpl = $this->template->tplutils->getSubpart($tmpl, '###DOCUMENT###');
		$gMarker = $this->template->tplutils->getMarkerArray($gTmpl);
		$pMarker = $this->template->tplutils->getMarkerArray($pTmpl);
		$dMarker = $this->template->tplutils->getMarkerArray($dTmpl);
		$gCont = '';
		$pCont = '';
		$dCont = '';

		foreach ($values['values'] as $obj) {
			switch ($obj['elemType']) {
				case 'G':
					$subs = $this->template->fillGroupMarkerContentArray($gMarker, $obj['id'], $obj['menuId']);
					$gCont .= $this->template->tplutils->substituteMarkerArray($gTmpl, $subs);
					break;
				case 'P':
					$subs = $this->template->fillProductMarkerContentArray($pMarker, $obj['id'], $obj['menuId']);
					$pCont .= $this->template->tplutils->substituteMarkerArray($pTmpl, $subs);
					break;
				case 'D':
					$subs = $this->template->fillDocumentMarkerContentArray($dMarker, $obj['id'], $obj['menuId']);
					$dCont .= $this->template->tplutils->substituteMarkerArray($dTmpl, $subs);
					break;
			}
		}

		$tmpl = $this->template->tplutils->substituteSubpart($tmpl, '###GROUP###', $gCont);
		$tmpl = $this->template->tplutils->substituteSubpart($tmpl, '###PRODUCT###', $pCont);
		$tmpl = $this->template->tplutils->substituteSubpart($tmpl, '###DOCUMENT###', $dCont);

		return $tmpl;
	}

	public function layoutSuggestItemsSimple($values, $suggestLayout) {
		$linker = new tx_ms3commerce_linker($this->db, $suggestLayout['linker'], null, null, $this->dbutils);
		if ($suggestLayout['linker']['force_realurl']) {
			$linker->forceRealURLOnStandalone();
		}

		// Resolve configuration
		$imgId = $this->dbutils->getFeatureIdByName($suggestLayout['image']);
		$titId = $this->dbutils->getFeatureIdByName($suggestLayout['text']);

		// Setup type dependent parameters
		$funcs = array(
			'P' => array('link' => 'getProductLink', 'value' => 'getProductValue'),
			'G' => array('link' => 'getGroupLink', 'value' => 'getGroupValue'),
			'D' => array('link' => 'getDocumentLink', 'value' => 'getDocumentValue'),
		);
		$res = array();
		foreach ($values['values'] as $obj) {
			$id = $obj['id'];
			$lbl = $obj['label'];
			$getValue = $funcs[$obj['elemType']]['value'];
			$getLink = $funcs[$obj['elemType']]['link'];
			$linkPid = $suggestLayout['pids'][$obj['elemType']];

			$img = $this->dbutils->$getValue($id, $imgId, true);
			$tit = $this->dbutils->$getValue($id, $titId, false);
			$lbl = $this->dbutils->$getValue($id, $titId, true);

			$link = $linker->$getLink($id, $obj['menuId'], $linkPid);

			$layout = '<a href="' . $link . '"><span class="search_image" style="background-image:url(\'' . $img . '\')"></span><span class="search_text">' . $tit . '</span><div style="clear: both;"></div></a>';

			$res[] = array(
				'id' => $id,
				'label' => $lbl,
				'layout' => $layout
			);
		}
		$ret = array(
			'values' => $res,
			'dbg' => $values['dbg']
		);
		return $ret;
	}

	public function getSuggestionFullText_direct($request, $limit) {
		$fetcher = $this->getRecordFetcher(true);

		// Check for fast implementation by specific fetcher
		$ret = $fetcher->executeSuggestDirect($request, $limit);

		if ($ret === false) {
			// No fast implementation, do normal

			$singleItemResults = $request->SuggestSingleItems;

			$fetcher->setupSuggest($request, $request->ResultTypes);

			$count = 0;
			while ($count < $limit) {
				if (!$fetcher->fetchMoreSuggest()) {
					break;
				}
				$this->filterRestrictionsByValue($request, $request->restrictionValues, $request->userRightsValues);
				if ($singleItemResults) {
					$count = $this->getElementCount();
				} else {
					$count = $this->getDistinctFieldCount('Display');
				}
			}

			$query = $fetcher->getSuggestionFetchQuery($singleItemResults);
			$valuesList = $this->getSuggestionResults($query, $limit, $singleItemResults);

			$ret = array('values' => $valuesList);
		}
		return $ret;
	}

	/**
	 * @abstract: fetch items from the fulltext search temporary table(s) ordered by relevaces 
	 * @param type $result_types (array containing the result types (product,group,documents)
	 * @param type $limit (how many items show the pulldown list 
	 * @return array with suggestions to be displayed in the pulldown menu  
	 */
	private function getSuggestionResults($query, $limit, $singleItemResults) {
		$selQuery = $query;
		$selQuery.=" LIMIT 0,$limit";

		$result = array();
		$res = $this->db->sql_query($selQuery);
		if ($res) {
			while ($row = $this->db->sql_fetch_row($res)) {
				if ($singleItemResults) {
					$result[] = array('id' => $row[0], 'elemType' => $row[1], 'label' => $row[2], 'menuId' => $row[3]);
				} else {
					$result[] = $row[0];
				}
			}
		}
		return $result;
	}

}

class tx_ms3commerce_suggest_helper {

	/**
	 *
	 * @param tx_ms3commerce_template $template
	 * @return type 
	 */
	public static function config2QuickParams($template) {
		if (is_null($template->searchMenuIds)) {
			$menus = $template->rootMenuId;
		} else {
			$menus = join(',', $template->searchMenuIds);
		}
		$data = array(
			'suggest' => 1,
			'menu' => $menus,
			'lang' => $template->languageId,
			'market' => $template->marketId,
			'types' => $template->conf['result_types'],
			'shop' => $template->dbutils->getShopId($template->languageId, $template->marketId)
		);
		if ($template->restrictionFeatureId) {
			$data['restrict'] = $template->restrictionFeatureId;
			$vals = join(';', $template->restrictionValues);
			$data['restrvals'] = $vals;
		}
		if ($template->userRightsFeatureId) {
			$data['userrights'] = $template->userRightsFeatureId;
			$vals = join(';', $template->userRightsValues);
			$data['uservals'] = $vals;
		}
		if ($template->conf['distinct_result'] == 1) {
			$data['distinct'] = 1;
		}
		if ($template->conf['suggestLimitResults']) {
			$data['limit'] = $template->conf['suggestLimitResults'];
		}

		if ($template->conf['suggestSingleItems']) {
			$data['singleItems'] = 1;
		} else {
			$data['singleItems'] = 0;
		}

		if ($data['singleItems']) {
			$data['use_map_id_links'] = $template->conf['use_map_id_links'];
			$data['realurl_level_depth'] = $template->conf['realurl_level_depth'];
			$data['force_realurl'] = $template->conf['force_realurl_suggest'];

			if (array_key_exists('suggestSingleItemsConf.', $template->conf)) {
				$singleItemConf = $template->conf['suggestSingleItemsConf.'];
				if (is_array($singleItemConf)) {
					$data['suggestImg'] = $singleItemConf['image'];
					$data['suggestText'] = $singleItemConf['text'];
					if (array_key_exists('pids.', $singleItemConf)) {
						$data['suggestPidProduct'] = $singleItemConf['pids.']['product'];
						$data['suggestPidGroup'] = $singleItemConf['pids.']['group'];
						$data['suggestPidDocument'] = $singleItemConf['pids.']['document'];
					}
				}
			}
		}

		$data['searchType'] = $template->conf['searchType'];

		return $data;
	}

	/**
	 *
	 * @param tx_ms3commerce_template $template 
	 */
	public static function config2Request($template, $term) {
		$selection = new stdClass();
		$selection->Type = "Fulltext";
		$selection->Value = array($term);

		$menu = $template->searchMenuIds;
		if (is_null($menu)) {
			$menu = array($template->rootMenuId);
		}
		$request = new stdClass();
		$request->Menu = $menu;
		$request->Selection = array($selection);
		$request->FulltextTerm = $term;
		$request->IsFulltext = true;
		$request->Limit = 0;
		$request->Language = $template->languageId;
		$request->Market = $template->marketId;
		$request->Shop = $template->dbutils->getShopId($template->languageId, $template->marketId);
		$request->restrictionFeatureId = $template->restrictionFeatureId;
		$request->userRightsFeatureId = $template->userRightsFeatureId;
		$request->restrictionValues = $template->restrictionValues;
		$request->userRightsValues = $template->userRightsValues;
		$request->distinctResults = $template->conf['distinct_result'] == 1 ? true : false;

		if (array_key_exists('result_types', $template->conf)) {
			$result_types = explode(';', $template->conf['result_types']);
			$request->ResultTypes = array_map('strtolower', $result_types);
		} else {
			$request->ResultTypes = array();
		}
		if (isset($template->conf['suggestLimitResults'])) {
			$request->SuggestLimitResults = $template->conf['suggestLimitResults'];
		} else {
			$request->SuggestLimitResults = 10;
		}
		if ($template->conf['suggestSingleItems']) {
			$request->SuggestSingleItems = 1;
		} else {
			$request->SuggestSingleItems = 0;
		}
		$request->SearchType = $template->conf['searchType'];

		return $request;
	}

	public static function config2SuggestLayout($template) {
		$conf = $template->conf['suggestSingleItemsConf.'];
		$conf['linker'] = array(
			'force_realurl' => $template->conf['force_realurl_suggest'],
			'use_map_id_links' => $template->conf['use_map_id_links'],
			'realurl_level_depth' => $template->conf['realurl_level_depth'],
		);
		$conf['pids'] = array(
			'P' => $conf['pids.']['product'],
			'G' => $conf['pids.']['group'],
			'D' => $conf['pids.']['document'],
		);
		return $conf;
	}

	public function quickParams2Request($params, $term) {
		$selection = new stdClass();
		$selection->Type = "Fulltext";
		$selection->Value = array($term);
		//$selection->Type = 'FulltextDisplay';

		$request = new stdClass();
		//$request->include = null;
		$request->Menu = explode(',', $params['menu']);
		$request->Selection = array($selection);
		$request->FulltextTerm = $term;
		$request->IsFulltext = true;
		$request->Limit = 0;
		$request->Language = intval($params['lang']);
		$request->Market = intval($params['market']);
		$request->Shop = intval($params['shop']);
		$request->SearchType = $params['searchType'];

		if (array_key_exists('restrict', $params)) {
			$request->restrictionFeatureId = $params['restrict'];
			$request->restrictionValues = explode(';', $params['restrvals']);
		} else {
			$request->restrictionFeatureId = null;
			$request->restrictionValues = null;
		}
		if (array_key_exists('userrights', $params)) {
			$request->userRightsFeatureId = $params['userrights'];
			$request->userRightsValues = explode(';', $params['uservals']);
		} else {
			$request->userRightsFeatureId = null;
			$request->userRightsValues = null;
		}
		if (array_key_exists('distinct', $params)) {
			$request->distinctResults = $params['distinct'] == 1 ? true : false;
		} else {
			$request->distinctResults = false;
		}

		$types = explode(';', $params['types']);
		$request->ResultTypes = array_map('strtolower', $types);

		if (array_key_exists('limit', $params)) {
			$request->SuggestLimitResults = intval($params['limit']);
		} else {
			$request->SuggestLimitResults = 10;
		}

		if (array_key_exists('singleItems', $params)) {
			$request->SuggestSingleItems = $params['singleItems'];
		} else {
			$request->SuggestSingleItems = 0;
		}

		return $request;
	}

	public function quickParams2SuggestLayout($params) {
		$conf = array();
		$conf['image'] = $params['suggestImg'];
		$conf['text'] = $params['suggestText'];
		$conf['linker'] = array(
			'force_realurl' => $params['force_realurl'],
			'use_map_id_links' => $params['use_map_id_links'],
			'realurl_level_depth' => $params['realurl_level_depth'],
		);

		$conf['pids'] = array(
			'P' => $params['suggestPidProduct'],
			'G' => $params['suggestPidGroup'],
			'D' => $params['suggestPidDocument'],
		);
		return $conf;
	}

	public static function getSuggestHMac($data) {
		ksort($data);
		$data = array_map('strval', $data);
		$keyData = array('key' => MS3C_SUGGEST_KEY, 'data' => $data);
		$val = md5(serialize($keyData));
		return $val;
	}

	public static function addSuggestHMac(&$data) {
		$data['hmac'] = self::getSuggestHMac($data);
	}

	public static function verifySuggestHMac($data) {
		if (!array_key_exists('hmac', $data)) {
			return false;
		}

		$hmac = $data['hmac'];
		unset($data['hmac']);
		$checkHmac = self::getSuggestHMac($data);
		if ($checkHmac != $hmac) {
			return false;
		}
		return true;
	}

}

?>
