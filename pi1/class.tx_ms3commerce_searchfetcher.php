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
 * Base class for Search Record Fetchers 
 */
abstract class mS3CommerceSearchRecordFetcher
{
	/** @var tx_ms3commerce_db */
	protected $db;
	/** @var tx_ms3commerce_timetracker */
	protected $timetracker;
	
	public abstract function setup($query, $menuTypes);
	public abstract function setupSuggest($query, $menuTypes);
	public abstract function fetchMore();
	public abstract function fetchMoreSuggest();
	public abstract function getTotalCount();
	public abstract function getOrderClause();
	public abstract function getSuggestionFetchQuery($forSingleItemSuggest = false);
	
	public function executeSuggestDirect($query, $limit = 10) {
		return false;
	}
	
	public function getNewElementCondition() {
		return '';
	}
	
	public function __construct($search) {
		$this->db = $search->db;
		$this->timetracker = $search->timetracker;
	}
	
	public function destroy()
	{
		//Drop temporary table if exists
		return $this->db->sql_query("DROP TEMPORARY TABLE IF EXISTS TempSearch;");
	}
	
	public static function getTableInfo($menutype, $withKeys = true) {
		if ($menutype == 'group') {
			$vals = array(
				'refid' => 'GroupId', 
				'valuetable' => 'GroupValue', 
				'parenttype' => 1,
				'elemtype' => 'G'
			);
		} else if ($menutype == 'product') {
			$vals = array(
				'refid' => 'ProductId', 
				'valuetable' => 'ProductValue', 
				'parenttype' => 2,
				'elemtype' => 'P'
			);
		} else if ($menutype == 'document') {
			$vals = array(
				'refid' => 'DocumentId', 
				'valuetable' => 'DocumentValue', 
				'parenttype' => 3,
				'elemtype' => 'D'
			);
		} else {
			return null;
		}
		
		if ($withKeys) {
			return $vals;
		}
		return array(
			$vals['refid'], $vals['valuetable'], $vals['parenttype'], $vals['elemtype']
		);
	}
	
	/**
	 * Used by a SearchRecordFetcher that doesn't handle restrictions by itself.
	 * Fills the userrights and restrictions columns of the internal temporary table
	 * @param array $menuTypes Types of elements to handle (product, group, document)
	 */
	protected function injectRestrictions($menuTypes, $restrictionFeatureId, $userRightsFeatureId, $addWhere = '')
	{
		$restrictionType = array();
		if ($restrictionFeatureId) {
			$restrictionType['Restrictions'] = $restrictionFeatureId;
		}
		if ($userRightsFeatureId) {
			$restrictionType['Rights'] = $userRightsFeatureId;
		}
		
		foreach ($menuTypes as $mt) {
			list($id,$table,,$type) = mS3CommerceSearchRecordFetcher::getTableInfo($mt, false);
			foreach ($restrictionType as $column => $featureId) {
				$sql = 
					"UPDATE TempSearch t ".
					"INNER JOIN $table v ON v.$id = t.id AND t.ElemType = '$type' AND v.featureId = $featureId $addWhere ".
					"SET t.$column = v.ContentPlain";
				
				$this->db->sql_query($sql);
			}
		}
	}
	
	protected function getDefaultColumnSetup($query)
	{
		// without filter constraints, column selected is set to 0 not selected (later may set some records as selected (1)
		// Customization filter is set by default
		$selCols = "selcustom TINYINT(1) DEFAULT 1,";
		if (!empty($query->include)) {
			// Filter column for each filter
			foreach ($query->include as $col => $val) {
				$selCols.="selcol" . $col . " TINYINT(1),";
			}
			// Overall selected
			$selCols.=" selall TINYINT(1) default 0";
		} else {
			$selCols .= " selall TINYINT(1) default 0, INDEX USING BTREE (selall)";
		}
		
		$columns = "id INT(11),MenuId INT(11)," .
				"MenuPath VARCHAR(255),Restrictions VARCHAR(80),Rights VARCHAR(80)," .
				"delRestrictions TINYINT(1), delRights TINYINT(1),ElemType CHAR(1), ".
				$selCols;
		
		return $columns;
	}
	
	protected function initTable($columns)
	{
		//Drop temporary table if exists
		$result = $this->db->sql_query("DROP TEMPORARY TABLE IF EXISTS TempSearch;");

		//Create Temptable
		$createSql = "CREATE TEMPORARY TABLE TempSearch ($columns) ENGINE=MEMORY";
		$result = $this->db->sql_query($createSql);
		return $result;
	}
}

/**
 * MySQL Fetcher for ordinary searches. 
 */
class mS3CommerceSearchMySQLRecordFetcher extends mS3CommerceSearchRecordFetcher
{
	protected $conf;
	protected $menuTypes;
	protected $hasMoreData = true;
	
	public function __construct($search) {
		parent::__construct($search);
		
	}
	
	public function setup($query, $menuTypes)
	{
		$this->menuTypes = $menuTypes;
		$this->init($this->getDefaultSetup($query, $menuTypes));
	}
	
	public function setupSuggest($query, $menuTypes) {
		// Non-Fulltext has own suggest logic. Not bound to fetcher
	}
	
	public function getOrderClause() {
		return $this->conf['order'];
	}
	
	public function getTotalCount() {
		$res = $this->db->sql_query('SELECT COUNT(*) FROM TempSearch WHERE selall = 1');
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			if ($row) {
				return $row[0];
			}
		}
		return 0;
	}
	
	public function fetchMore()
	{
		if (!$this->hasMoreData) {
			return false;
		}
		
		$this->timetracker->timeTrackStart("prepare fetch");
		// First we need to filter the products to the entire set without limit 
		// the set of Products depend on the context made of MarketId, LanguageId,and the menue path or some included conditions 
		// and if is any Right restriction are set 			
		$selInsSql = "SELECT DISTINCT {$this->conf['select']} FROM ";
		$selInsSql.= $this->conf['from'];
		$selInsSql.=" WHERE ";
		$selInsSql.=$this->conf['where'];
		//$selInsSql.=$this->conf['order'];
		
		$tables = $this->conf['tables'];
		
		//Insert values out of the select query
		$sql = "INSERT INTO TempSearch ({$this->conf['inserts']}) " . $selInsSql;
		$this->timetracker->timeTrackStop();
		
		foreach ($this->menuTypes as $mt) {
			$this->timetracker->timeTrackStart("build fetch $mt");
			$replacements = mS3CommerceSearchRecordFetcher::getTableInfo($mt);
			if (empty($replacements)) {
				continue;
			}
			$sqlCur = str_replace('%%PARENTTYPE%%', $replacements['parenttype'], $sql);
			$sqlCur = str_replace('%%JOINTABLE%%', $replacements['valuetable'], $sqlCur);
			$sqlCur = str_replace('%%MENUREFID%%', $replacements['refid'], $sqlCur);
			$sqlCur = str_replace('%%ELEMTYPE%%', $replacements['elemtype'], $sqlCur);
			
			$tablesCur = join(',', $tables);
			$tablesCur = str_replace('%%JOINTABLE%%', $replacements['valuetable'], $tablesCur);
			
			$this->timetracker->timeTrackStop();
			
			$this->timetracker->timeTrackStart("Execute Fetch");
			$result = $this->db->sql_query($sqlCur, $tablesCur);
			//$sql_out = $this->db->do_map_sql_query($sql, $tables);
			$this->timetracker->timeTrackStop();
		}
		//if only distinct results desired
		if ($this->conf['unique']) {
			//this will create unique productId (delete all double
			$res = $this->db->sql_query('ALTER IGNORE TABLE TempSearch ADD PRIMARY KEY (id,ElemType)');
		}
		
		$this->hasMoreData = false;
		return true;
	}
	
	public function fetchMoreSuggest() {
		// Non-Fulltext has own suggest logic. Not bound to fetcher
		return false;
	}
	
	public function getSuggestionFetchQuery($forSingleItemSuggest = false) {
		// Non-Fulltext has own suggest logic. Not bound to fetcher
	}
	
	protected function getDefaultSetup($query)
	{
		$market = intval($query->Market);
		$menus = $query->Menu;
		
		$tables = array();
		//list($ttemp, $tid, $jtable) = $this->getTableSetup($menutype);
		
		// if any Right-restriction are set, insert those rights in the tempTable
		if ($query->restrictionFeatureId) {
			$rights = ",v.ContentPlain"; //rights
			$join = "LEFT JOIN %%JOINTABLE%% v ON m.%%MENUREFID%%=v.%%MENUREFID%% AND v.FeatureId ='" . $query->restrictionFeatureId . "'";
			$tables[] = '%%JOINTABLE%% v';
		} else {
			$rights = ",''";
		}
		if ($query->userRightsFeatureId) {
			$rights.=",v2.ContentPlain"; //rights
			$join.="LEFT JOIN %%JOINTABLE%% v2 ON m.%%MENUREFID%%=v2.%%MENUREFID%% AND v2.FeatureId ='" . $query->userRightsFeatureId . "'";
			$tables[] = '%%JOINTABLE%% v2';
		} else {
			$rights.=",''";
		}
		
		//market constrains
		$where = '';
		if (isset($market)) {
			$where = "m.MarketId=$market AND ";
		}
		//path constrains
		$where .= " m.Path LIKE '%' AND m.%%MENUREFID%% IS NOT NULL \r\n";


		if (!empty($menus)) {
			foreach ($menus as &$m) $m = intval($m);
			//array_walk($menus, 'intval');
			$menuSql = "";
			$or = "";

			$result = $this->db->sql_query("SELECT DISTINCT Path,Id FROM Menu WHERE Id IN (" . implode(',', $menus) . ")", 'Menu');
			while ($path = $this->db->sql_fetch_object($result)) {
				if ($path->Path == '/') {
					$path->Path = '';
				}
				$menuSql.=$or . "m.Path LIKE '" . $path->Path . "/" . $path->Id . "%'\r\n";
				$or = " OR ";
			}
			if (strlen($menuSql) > 0) {
				$where.=" AND (\r\n $menuSql )\r\n";
			}
		}
		
		
		
		
		$select = "m.%%MENUREFID%%,'%%ELEMTYPE%%' as ElemType,m.Id,m.Path $rights";
		
		$from = "Menu m ".$join;
		$tables[] = "Menu m";
		
		// Where already set
		
		$order = 'ElemType,';
		
		$columns = $this->getDefaultColumnSetup($query);
		
		//if only distinct results desired
		if ($query->distinctResults) {
			//this will create unique productId (delete all double
			$unique = true;
		} else {
			$unique = false;
		}
		
		return array(
			'select' => $select,
			'from' => $from,
			'where' => $where,
			'order' => $order,
			'columns' => $columns,
			'inserts' => 'id,ElemType,MenuId,MenuPath,Restrictions,Rights',
			'unique' => $unique,
			'tables' => $tables
		);
	}
	
	protected function init($setup)
	{
		$this->conf = $setup;
		return $this->initTable($setup['columns']);
	}
}


/**
 * MySQL Fetcher for Fulltext search 
 */
class mS3CommerceMySQLFulltextRecordFetcher extends mS3CommerceSearchMySQLRecordFetcher
{
	public function __construct($search) {
		parent::__construct($search->db);
	}
	
	public function setup($query, $menuTypes, $onlyDisplay = false)
	{
		$ftParams = $this->getFtParams($query->FulltextTerm, $query->Language, $query->Market);

		//Relevance weighting factors 
		$primaryFactor = 0.6;
		$secondaryFactor = 0.3;
		$tertiaryFactor = 0.1;

		//list(,,,$parentType) = $this->getTableSetup($mt);

		$likeTerm = null;
		$locateTerm = null;
		if ($this->template && $this->template->custom->checkFulltextFallbackForTerm($query->FulltextTerm, $likeTerm, $locateTerm)) {
			$origTerm = $this->db->sql_escape($query->FulltextTerm);
			if ($likeTerm == null) {
				$likeTerm = "'%" . $this->db->sql_escape($query->FulltextTerm, false) . "%'";
			}
			if ($locateTerm == null) {
				$locateTerm = $origTerm;
			}

			$relevance =
					", 0 as relevance," .
					" (((1/(COALESCE(locate($locateTerm,searchterms1),0)-0.5))+2) *$primaryFactor  +" .
					" ((1/(COALESCE(locate($locateTerm,searchterms2),0)-0.5))+2) *$secondaryFactor +" .
					" ((1/(COALESCE(locate($locateTerm,searchterms3),0)-0.5))+2) *$tertiaryFactor ) as pseudorelevance ";

			$ftWhere =
					" ft.parentid=m." . $mt . "id AND " .
					"(ft.searchterms1 LIKE $likeTerm OR " .
					" ft.searchterms2 LIKE $likeTerm OR " .
					" ft.searchterms3 LIKE $likeTerm OR " .
					" ft.display = $origTerm) " .
					" AND ft.parenttype=%%PARENTTYPE%% AND ";
		} else if ($onlyDisplay) {
			$relevance = //", (MATCH(ft.display) AGAINST ('" . $ftParams['keywordNatural'] . "')) as relevance, " .
					", 0 as relevance, " . // No FULLTEXT Idx for display yet...
					" CAST(ft.Display = '" . $ftParams['keywordOrig'] . "' AS UNSIGNED INTEGER)*5 + " .
					" CAST(ft.Display = '" . $ftParams['keywordNatural'] . "' AS UNSIGNED INTEGER)*5 + " .
					" (((1/(COALESCE(locate('" . $ftParams['keywordLocate'] . "',display),0)-0.5))+2)) as pseudorelevance ";


			//Fulltext constrains
			$ftWhere = "ft.parentid=m." . $mt . "id AND (" .
					"MATCH(ft.Display) AGAINST ('" . $ftParams['keywordBool'] . "' IN BOOLEAN MODE) " .
					"OR ft.display = '" . $ftParams['keywordOrig'] . "' " .
					"OR ft.display = '" . $ftParams['keywordNatural'] . "' " .
					") AND ft.parenttype=%%PARENTTYPE%% AND ";
		} else {

			//relevance statements
			/* $relevance = ", MATCH(ft.searchterms) AGAINST ('" . $ftParams['keyword'] . "' ) as relevance ";
				*/
			$relevance = 
					",(MATCH(ft.searchterms1) AGAINST ('" . $ftParams['keywordNatural'] . "')*$primaryFactor +" .
					" MATCH(ft.searchterms2) AGAINST ('" . $ftParams['keywordNatural'] . "')*$secondaryFactor +" .
					" MATCH(ft.searchterms3) AGAINST ('" . $ftParams['keywordNatural'] . "')*$tertiaryFactor ) as relevance, " .
					" CAST(ft.Display = '" . $ftParams['keywordOrig'] . "' AS UNSIGNED INTEGER)*5 + " .
					" CAST(ft.Display = '" . $ftParams['keywordNatural'] . "' AS UNSIGNED INTEGER)*5 + " .
					" (((1/(COALESCE(locate('" . $ftParams['keywordLocate'] . "',searchterms1),0)-0.5))+2) *$primaryFactor  +" .
					" ((1/(COALESCE(locate('" . $ftParams['keywordLocate'] . "',searchterms2),0)-0.5))+2) *$secondaryFactor +" .
					" ((1/(COALESCE(locate('" . $ftParams['keywordLocate'] . "',searchterms3),0)-0.5))+2) *$tertiaryFactor ) as pseudorelevance ";


			//Fulltext constrains
			$ftWhere = "ft.parentid=m." . $mt . "id AND (" .
					"   MATCH(ft.searchterms1) AGAINST ('" . $ftParams['keywordBool'] . "' IN BOOLEAN MODE) " .
					"OR	MATCH(ft.searchterms2) AGAINST ('" . $ftParams['keywordBool'] . "' IN BOOLEAN MODE) " .
					"OR	MATCH(ft.searchterms3) AGAINST ('" . $ftParams['keywordBool'] . "' IN BOOLEAN MODE) " .
					"OR ft.display = '" . $ftParams['keywordOrig'] . "'" .
					"OR Display = '" . $ftParams['keywordNatural'] . "'".
					") AND ft.parenttype=%%PARENTTYPE%% AND ";
		}

		$display = ",ft.display";

		//column that contains the values to be displayed (create statement)
		$relevanceColumns = ",Relevance DOUBLE DEFAULT 0,Pseudorelevance DOUBLE DEFAULT 0";
		$displayColumn = ",display VARCHAR(255)";

		//Order statement 
		$orderByRelevance = "pseudorelevance desc, relevance desc,";

		$additionalInsertFields = ",Relevance,pseudorelevance,display";

		$additionalTable = $ftParams['ftxtable'] . " ft, ";

		$setup = $this->getDefaultSetup($query, $mt);

		$fullSetup = array(
			'select' => $setup['select'].$relevance.$display,
			'from' => $additionalTable.$setup['from'],
			'where' => $setup['where'].$ftWhere,
			'order' => $orderByRelevance,
			'columns' => $setup['columns'].$relevanceColumns.$displayColumn,
			'inserts' => $setup['inserts'].$additionalInsertFields
		);
		$this->init($fullSetup);
	}
	
	public function setupSuggest($query, $menuTypes) {
		// Same setup as usual
		$this->setup($query, $menuTypes);
	}
	
	public function fetchMoreSuggest() {
		// Same fetch as usual
		return $this->fetchMore();
	}
	
	public function getSuggestionFetchQuery($forSingleItemSuggest = false) {
		if ($forSingleItemSuggest) {
			$selQuery ='SELECT Id,ElemType,Display,MenuId FROM ';
			$selQuery.=' TempSearch ';
			$selQuery.=' ORDER BY relevance desc,pseudorelevance desc,Display ';
		} else {
			$selQuery ='SELECT  Display,sum(relevance) as relevance,sum(pseudorelevance)as pseudorelevance FROM ';
			$selQuery.=' TempSearch ';
			$selQuery.=' GROUP BY Display ORDER BY relevance desc,pseudorelevance desc ';
		}
		return $selQuery;
	}
	
	private function getFtParams($keyword, $language, $market) {
		$ftParams = array();
		$keyword = $this->db->sql_escape($keyword, false);
		$ftParams['keywordOrig'] = $keyword;
		$res = $this->db->exec_SELECTquery('shopid', 'ShopInfo', "languageid=" . intval($language) . " AND marketid=" . intval($market));
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			$ftParams['ftxtable'] = 'FullText_' . $row[0];
		}
		$keyword = str_replace('\"', '"', $keyword);
		// Preprocess keyword for differen purposes:
		// Remove modifying characters and stoppers, replace by single space
		$keyword = trim(preg_replace('/[-+~.,?<>\s\*]+/', ' ', $keyword));

		// Natural without quotes and spaces (might be introduced by removing ")
		$ftParams['keywordNatural'] = trim(preg_replace('/\s+/', '', str_replace('"', '', $keyword)));

		// non exact phrase might not be contained in search, so only search for first word!
		// or quoted part at the beginning...
		if ($keyword[0] == '"') {
			// This will get the quoted string at the beginning
			preg_match('/^"([^"]*)"?/', $keyword, $m1);
		} else {
			// This will get only the first word
			preg_match('/(\S+)/', $keyword, $m1);
		}
		$ftParams['keywordLocate'] = $m1[1];

		// Add modifier for boolean mode. Special handling inside quotes (")
		$quoted = explode('"', $keyword);
		$inQuote = false;
		if (reset($quoted) == "") {
			array_shift($quoted);
			$inQuote = true;
		}
		if (end($quoted) == "") {
			array_pop($quoted);
		}
		$boolKey = '+';
		foreach ($quoted as $part) {
			if ($inQuote) {
				$boolKey .= '"' . $part . '"';
			} else {
				$boolKey .= preg_replace('/ /', ' +', $part);
			}
			$inQuote = !$inQuote;
		}
		if ($inQuote) {
			$boolKey .= '*';
		}

		// special case: if last word is 1-3 characters,
		// don't append the '*'. This can lead to empty results, if the word is complete
		// (e.g. "+XXXXX +Typ*" => "Typ" is not indexed => required "Typ*" is not found!)
		$boolKey = preg_replace('/\+(.{1,3}\*)$/', '\1', $boolKey);
		$ftParams['keywordBool'] = $boolKey;

		return $ftParams;
	}
}


/**
 * Elastic Search Fetcher for fulltext search 
 */
class mS3CommerceElasticSearchRecordFetcher extends mS3CommerceSearchRecordFetcher
{
	/** @var tx_ms3commerce_DbUtils */
	var $dbUtils;
	var $elemTypes;
	var $query;
	/** @var MS3ElasticSearchQueryHandler */
	var $handler;
	var $autoCompTerm;
	var $term;
	var $totalHits;
	var $scanKey;
	/** @var tx_ms3commerce_search */
	var $searchCore;
	
	/**
	 *
	 * @param tx_ms3commerce_search $searchCore 
	 */
	public function __construct($searchCore) {
		parent::__construct($searchCore);
		$this->dbUtils = $searchCore->dbutils;
		$this->searchCore = $searchCore;
	}
	
	public function setup($query, $menuTypes) {
		$this->query = $query;
		$columns = $this->getDefaultColumnSetup($query);
		$columns .= ', Display VARCHAR(255),Relevance REAL, IsNewElem TINYINT(1) DEFAULT 1';
		$this->initTable($columns);
		
		$this->term = $query->FulltextTerm;
		$this->elemTypes = $menuTypes;
		$idxName = MS3ElasticSearchClusterHandler::buildIndexName(false, $query->Shop);
		if ($this->searchCore->custom)
			$cust = $this->searchCore->custom->getFullTextCustomHandler('ElasticSearch');
		else
			$cust = null;
		$this->handler = new MS3ElasticSearchQueryHandler($idxName, $cust);
		$this->scanKey = null;
	}
	public function setupSuggest($query, $menuTypes) {
		$this->setup($query, $menuTypes);
		$this->autoCompTerm = $query->FulltextTerm;
	}
	public function destroy() {
		if ($this->scanKey) {
			$this->handler->closeScroll($this->scanKey);
		}
	}
	
	public function getOrderClause() {
		return 'Relevance DESC,';
	}
	public function getNewElementCondition() {
		return 'IsNewElem = 1';
	}
	public function getSuggestionFetchQuery($forSingleItemSuggest = false) {
		if ($forSingleItemSuggest) {
			return 'SELECT Id,ElemType,Display,MenuId FROM TempSearch ORDER BY Relevance DESC';
		} else {
			return 'SELECT DISTINCT Display FROM TempSearch ORDER BY Relevance DESC';
		}
	}
	public function getTotalCount() {
		return $this->totalHits;
	}
	public function fetchMore() {
		if (!$this->scanKey) {
			// Open the scanner
			// Flush cached menu paths. Required e.g. for hierachical menu
			// (The old paths will be loaded, even after menu change...)
			$menuPaths = $this->getMenuPaths($this->query, true);
			$res = $this->handler->searchSingleTermScrolled($this->term, $this->elemTypes, $menuPaths, $this->query->Limit, array('menuPaths', '*_display'), $this->query->userRightsValues, $this->query->restrictionValues);
			$this->totalHits =$res['hits']['total'];
			$this->scanKey = $res['_scroll_id'];
			
			// Check if there are enough elements
			if ($this->totalHits < $this->query->Start) {
				return false;
			}
			
			// Cannot skip through elements, since selection and restrictions
			// are not known here. Outer fetch-loop must do this...
		} else {
			$menuPaths = $this->getMenuPaths($this->query, true);
			$res = $this->handler->scrollSingleTerm($this->scanKey);
		}
		
		$this->scanKey = $res['_scroll_id'];
		$ins = array();

		if (count($res['hits']['hits']) == 0) {
			return false;
		}
		
		// 
		foreach ($res['hits']['hits'] as $obj) {
			$obj = $this->normalizeObject($obj, $menuPaths);
			$vals = array(
				$obj['id'],
				$obj['menu'],
				'\''.$obj['menuPath'].'\'',
				'\''.$obj['elemType'].'\'',
				'\''.$obj['relevance'].'\'',
				1
			);
			$ins[] = '('.join(',', $vals).')';
		}

		$sql = "INSERT INTO TempSearch (id, MenuId, MenuPath, ElemType, Relevance, IsNewElem) VALUES ";
		$sql .= join(',', $ins);

		// Unmark existing elements as new
		$this->db->sql_query('UPDATE TempSearch SET IsNewElem = 0');
		$this->db->sql_query($sql);

		if (!MS3C_ELASTICSEARCH_HANDLES_ACCESS_RIGHTS) {
			$this->injectRestrictions($this->elemTypes, $this->query->restrictionFeatureId, $this->query->userRightsFeatureId, "AND t.IsNewElem = 1");
		}
		
		return true;
	}
	
	// To only execute suggester once
	var $hasRquested = false;
	public function fetchMoreSuggest() {
		
		if ($this->hasRquested) return false;
		
		$this->hasRquested = true;
		$menuPaths = $this->getMenuPaths($this->query);
		$res = $this->handler->autocomplete($this->autoCompTerm, $this->elemTypes, $menuPaths, $this->query->userRightsValues, $this->query->restrictionValues);
		//$res = $this->handler->suggest($this->autoCompTerm, $this->elemTypes);
		$vals = array();
		//foreach ($res['hits']['hits'] as $obj) {
		foreach ($res['aggregations']['autocomp']['buckets'] as $obj) {
			$obj = $obj['tops']['hits']['hits'][0];
			$elem = $this->normalizeObject($obj, $menuPaths);
			$vals[] = "('{$elem['id']}', '{$elem['elemType']}', '{$elem['menu']}', '{$elem['relevance']}'," . $this->db->sql_escape($elem['display']).')';
		}
		
		if (count($vals) > 0) {
			$insSQL = 'INSERT INTO TempSearch (Id,ElemType,MenuId,Relevance,Display) VALUES ';
			$insSQL .= join(',',$vals);
			$this->db->sql_query($insSQL);
			
			if (!MS3C_ELASTICSEARCH_HANDLES_ACCESS_RIGHTS) {
				$this->injectRestrictions($this->elemTypes, $this->query->restrictionFeatureId, $this->query->userRightsFeatureId, "AND t.IsNewElem = 1");
			}
			
			return true;
		}
		return false;
	}
	
	
	public function executeSuggestDirect($query, $limit = 10) {
		$execStart = microtime(true);
		
		// Optimized version for suggest query!
		$types = $query->ResultTypes;
		$term = $query->FulltextTerm;
		$shop = $query->Shop;
		$singleItemResults = $query->SuggestSingleItems;
		$searchType = $query->SearchType;
		
		if (empty($searchType)) {
			$searchType = 'autoComplete';
		}
		
		$idxName = MS3ElasticSearchClusterHandler::buildIndexName(false, $shop);
		if ($this->searchCore->custom)
			$cust = $this->searchCore->custom->getFullTextCustomHandler('ElasticSearch');
		else
			$cust = null;
		$handler = new MS3ElasticSearchQueryHandler($idxName, $cust);
		
		/*
		$ret = $handler->suggest($term, $types, true);
		if (is_array($ret)) {
			$res = array();
			foreach($ret['consolidated'] as $obj) {
				$res[] = $obj['text'];
			}
		} else {
			return "";
		}
		*/
		
		$total = -1;
		if ($singleItemResults || $searchType == 'singleTerm') {
			$menuPaths = $this->getMenuPaths($query);
			$ret = $handler->searchSingleTerm($term, $types, $menuPaths, $query->userRightsValues, $query->restrictionValues, 0, $limit, array('menuPaths', '*_display'));
			if (is_array($ret)) {
				$res = array();
				$total = $ret['hits']['total'];
				foreach ($ret['hits']['hits'] as $obj) {
					$obj = $this->normalizeObject($obj, $menuPaths);
					$res[] = array(
						'id' => $obj['id'],
						'label' => $obj['display'],
						'elemType' => $obj['elemType'],
						'menuId' => $obj['menu'],
						'relevance' => $obj['relevance']
						);
				}
			} else {
				return "";
			}
		} else if ($searchType == 'autoComplete') {
			$ret = $handler->autocomplete($term, $types);
			if (is_array($ret)) {
				$res = array();
				foreach ($ret['aggregations']['autocomp']['buckets'] as $obj) {
					$res[] = $obj['key'];
				}
			} else {
				return "";
			}
		} else if ($searchType == 'suggest') {
			$ret = $handler->suggest($term, $types, true);
			if (is_array($ret)) {
				$res = array();
				foreach ($ret['consolidated'] as $obj) {
					$res[] = $obj['text'];
				}
			} else {
				return "";
			}
		}
		
		$execEnd = microtime(true);
		$exec = intval($execEnd*1000-$execStart*1000);
		$api = $ret['API'];
		$es = $ret['took'];
		return array('dbg'=> "EXEC: $exec / API: $api / ES: $es", 'values' => $res, 'total' => $total);
	}
	
	private function getMenuPaths($query, $flush = false)
	{
		if (!$flush) {
			if (count($query->MenuPaths) > 0) {
				// Special case: If already calculated, or given by caller, do nothing
				return $query->MenuPaths;
			}
		}
		
		$paths = array();
		if (is_array($query->Menu)) {
			$menu = $query->Menu;
		} else if (!empty($query->Menu)) {
			$menu = array($query->Menu);
		} else {
			$menu = array();
		}
		if (count($menu) > 0) {
			foreach ($menu as $m) {
				$paths[] = $this->dbUtils->getMenuPath($m) . '/' . $m;
			}
		}
		$query->MenuPaths = $paths;
		return $paths;
	}
	
	private function normalizeObject($obj, $menuPaths = array()) {
		switch ($obj['_type']) {
		case 'product':
			$prefix = 'prd';
			$type = 'P';
			break;
		case 'group':
			$prefix = 'grp';
			$type = 'G';
			break;
		case 'document':
			$prefix = 'doc';
			$type = 'D';
			break;
		default:
			return $obj;
		}
		
		// Find correct menu path for multi-indexed items
		$objPaths = $obj['_source']['menuPaths'];
		$addMenus = array();
		$foundMenu = false;
		if (count($menuPaths) > 0) {
			foreach ($objPaths as $p) {
				foreach ($menuPaths as $m) {
					if (strpos($p, $m) === 0) {
						// This is a match
						if ($foundMenu) {
							// Additional entry
							$addMenus[] = $p;
						} else {
							// First matching entry
							$foundMenu = true;
							$menu = $p;
						}
					}
				}
			}
		}
		if (!$foundMenu) {
			// No menu restriction, or nothing found
			// Use first entry as reference, others as additional
			$menu = $objPaths[0];
			$addMenus = array_slice($objPaths, 1);
		}
		
		$menu = explode('/', $menu);
		$menuId = array_pop($menu);
		$menu = join('/', $menu);
		
		$ret = array(
			'id' => $obj['_id'],
			'type' => $obj['_type'],
			'relevance' => $obj['_score'],
			'elemType' => $type,
			'display' => $obj['_source'][$prefix.'_display'],
			'menu' => $menuId,
			'menuPath' => $menu,
			'additionalMenus' => $addMenus
		);
		
		return $ret;
	}
}


/// Can become a fetcher for LUCENE

//	/**
//	 * @abstract: test method for fulltext search with lucene indexed files
//	 * @param $reqObj requestobject with parameters
//	 * @param $out result object
//	 */
//	
//	private function luceneFtQuery($reqObj, $out) {
//		$out = new stdClass();
//		$out->Status = '';
//		$out->Beginning = $out->End = 0;
//		$term = $reqObj->FulltextTerm;
//		$start = intval($reqObj->Start);
//		$limit = intval($reqObj->Limit);
//		$market = $reqObj->Market;
//		$resultTypesArray = $reqObj->ResultTypes;
//		$indexPath = MS3C_EXT_DIRECTORY . '/' . $market . '/lucene';
//		$indexPath = str_replace("\\", "/", $indexPath);
//
//		//open Lucene-Searchindex
//		$index = Zend_Search_Lucene::open($indexPath);
//		//Zend_Search_Lucene::setResultSetLimit($limit);
//		//pass the Searchterm to lucene search
//		//split search string in search terms, this is needed to combine subqueries
//		$termquery = Zend_Search_Lucene_Search_QueryParser::parse("+(ft1:$term OR ft2:$term OR ft3:$term)");
//		$query = new Zend_Search_Lucene_Search_Query_Boolean();
//		$query->addSubquery($termquery, true /* required */);
//
//		//generate term for filter result Types(groups,products,documents)
//
//		foreach ($resultTypesArray as $rType) {
//			$rType = ucfirst($rType);
//			//$elemTerm = new Zend_Search_Lucene_Index_Term($rType, 'element');
//			//$elemquery = new Zend_Search_Lucene_Search_Query_Term($elemTerm);
//			//$query->addSubquery($elemquery, true );
//			$out->$rType = array();
//		}
//		$start = intval($reqObj->Start);
//		$limit = intval($reqObj->Limit);
//		$end = $start + $limit;
//
//		
//		//get results ->array of ojects der typ Zend_Search_Lucene_Search_QueryHit
//		$timeStart = microtime(true);
//		$hits = $index->find($query);
//		$timeEnd = microtime(true);
//		$count = 0;
//		/* statistics
//		 * Searchtime with lucene 0.26850891113281 [s] 
//		 * term 'indium oxid'
//		 */
//		
//		
//		
//		
//		
//		foreach ($hits as $hit) {
//			$doc = $hit->getDocument();
//			$uid = $doc->uid;
//			//not implemented now
//			//$menuPath=$hit->menupath;
//			//$menArr=explode('/',$menupath);
//			//$menu=end($menArr);
//			$arrmap = array('Group' => 1, 'Product' => 2, 'Document' => 3);
//			$elem = $doc->element;
//
//			if ($uid != null && in_array(lcfirst($elem), $resultTypesArray)) {
//				$menuId = $this->template->dbutils->getMenuIdByElementId($uid, $arrmap[$elem]);
//				$link = $this->plugin->getGroupLink($uid, $menuId);
//				$value = array("Id" => $uid, "MenuId" => $menuId, "Link" => $link ,"Element"=>$elem);
//				$results[] = $value;
//			}
//		}
//		
//		
//		
//		$total = count($results);
//		if ($total > 0) {
//			if($start+$limit>$total){$end=$total;}
//			//iterate over the hits array 
//			for ($i = $start; $i < $end; $i++) {
//				array_push($out->$results[$i]["Element"], $results[$i]);
//				$count++;
//			}
//
//			$out->Beginning = $i + 1;
//			$out->End = $out->Beginning + $limit;
//		} else {
//			$out->Beginning = 0;
//			$out->End = 0;
//		}
//		
//		
//		/* statistics:
//		 * sort and object filling time value with lucene 3.2186508178711E-5 [s] 
//		 * term 'indium oxid'
//		 */
//		
//		$out->Total = $total;
//		
//		echo "Search time with lucene " . ($timeEnd - $timeStart) . " [s] ";
//		return $out;
//	}

?>
