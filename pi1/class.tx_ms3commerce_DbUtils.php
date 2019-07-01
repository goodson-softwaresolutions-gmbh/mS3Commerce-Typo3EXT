<?php

/* * *************************************************************
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
 * ************************************************************* */

/**
 * Description of DbUtils
 *
 * @author marcelo.stucky
 */
class tx_ms3commerce_DbUtils {

	/** @var tx_ms3commerce_db */
	var $db;
	var $marketId;
	var $languageId;

	/** @var tx_ms3commerce_template */
	var $template;

	/** @var tx_ms3commerce_DbUtils_cached */
	var $wrapped;

	public function __construct($db, $marketId, $languageId, $template) {
		$this->db = $db;
		$this->marketId = $marketId;
		$this->languageId = $languageId;
		$this->template = $template;

		$this->initDBCache($db);
	}

	/**
	 * Gets the value of a specified feature attribute.
	 * @param $featureId   The identifier of the feature.
	 * @param $featureAttr The feaure's attribute. Must be one of the following
	 *  values: 'title', 'info'.
	 * @param $languageId  The language identifier.
	 * @return string HTML
	 */
	function getFeatureName($featureId) {
		$record = $this->wrapped->getFeatureRecord($featureId);
		if ($record) {
			return $record['Name'];
		} else {
			return NULL;
		}
	}

	function selectFeatureValue_singleRow($field, $featureId) {
		$field = str_replace("`", "", $field);
		$record = $this->wrapped->getFeatureRecord($featureId, $this->languageId);
		if ($record) {
			if (array_key_exists($field, $record)) {
				return $record[$field];
			}
		}
		return null;
	}

	/**
	 * Returns a single row from the Product table, or FALSE if there is no row.
	 * @param string $selectFields SELECT field
	 * @param string $whereClause
	 * @param int $numIndex
	 * @return string Result of query.
	 */
	function selectProduct_singleRow($selectFields, $whereClause) {
		$db = $this->db;
		$res = $db->exec_SELECTquery($selectFields, "Product", "$whereClause", '', '', "1");
		$row = FALSE;
		if ($res) {
			$row = $db->sql_fetch_object($res);
			$db->sql_free_result($res);
		}
		return $row;
	}

	/**
	 * Returns a single row from the Document table, or FALSE if there is no row.
	 * @param string $selectFields SELECT field
	 * @param string $whereClause
	 * @param int $numIndex
	 * @return string Result of query.
	 */
	function selectDocument_singleRow($selectFields, $whereClause) {
		$db = $this->db;
		$res = $db->exec_SELECTquery($selectFields, "Document", "$whereClause", '', '', "1");
		$row = FALSE;
		if ($res) {
			$row = $db->sql_fetch_object($res);
			$db->sql_free_result($res);
		}
		return $row;
	}

	/**
	 * Return ParentId of the given menu Id
	 * @param type $menuid
	 * @return type 
	 */
	function getParentGroupIdByMenu($menuid) {

		$sql = "SELECT t1.GroupId FROM Menu t1 
			 INNER JOIN Menu t2 on t1.Id=t2.ParentId
			 WHERE t2.Id=$menuid";

		$tables = "Menu t1,Menu t2";

		$res = $this->db->sql_query($sql, $tables);
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			$this->db->sql_free_result($res);
		}
		return $row[0];
	}

	function getMenuPath($menuId) {
		$menuId = intval($menuId);
		$row = $this->selectMenu_SingleRow('`Path`', "Id = $menuId");
		if ($row) {
			return $row[0];
		}
		return "";
	}

	/*
	  function getMenuIdByProdAndGroup($productid,$groupid){

	  $sql="SELECT t1.Id as menuId from Menu t1
	  INNER JOIN Menu t2 on t1.ParentId=t2.Id
	  WHERE t1.ProductId=$productid AND t2.GroupId=$groupid";

	  $tables= "Menu t1,Menu t2";
	  $res=$this->db->sql_query($sql, $tables);
	  if($res)
	  {
	  $row = $this->db->sql_fetch_row($res);
	  $this->db->sql_free_result($res);
	  return $row[0];
	  }
	  return 0;
	  }
	 */

	function getGroupMenuId($groupId, $parentMenuId = 0) {
		return $this->wrapped->getGroupMenuId($groupId, $parentMenuId);
	}

	function changeShop($langId, $marketId) {
		$this->languageId = $langId;
		$this->marketId = $marketId;
		$this->wrapped->changeShop($langId, $marketId);
	}

	/**
	 * SELECT from a single menu row.
	 * @param string $selectFields
	 * @param string $whereClause
	 * @param BOOLEAN $numIndex
	 * @return string result.
	 */
	function selectMenu_SingleRow($selectFields, $whereClause, $checkMarket = true) {
		$db = $this->db;
		// only the menu items in the current market will be returned
		if ($this->marketId > 0 && $checkMarket) {
			if (strlen($whereClause) > 0)
				$whereClause .= ' AND ';
			$whereClause .= sprintf('`MarketId` = %d', $this->marketId);
		}

		$res = $db->exec_SELECTquery($selectFields, "Menu", $whereClause, '', '', '1');
		if (!$res) {
			$err = $db->sql_error();
			if ($err != null)
				$err = "Datenbase Error: " . $err;
			else
				$err = "Unknown Error";
			throw new Exception($err . " (SELECT $selectFields FROM Menu $whereClause LIMIT 1)");
		}

		$output = FALSE;
		if ($res) {
			$output = $db->sql_fetch_row($res);
			$db->sql_free_result($res);
		}
		return $output;
	}

	/**
	 * Retrieves the value of a group associated with the specified feature. The current
	 * language is used. The default value is an empty string.
	 */
	function getGroupValue($groupId, $featureId, $raw = false, $id = false) {
		if (($groupId == 0) || ($featureId == 0))
		// Nothing to do for invalid identifiers.
			return '';

		$row = $this->wrapped->getGroupValue($groupId, $featureId);
		if ($raw === 'NUMBER')
			$value = $row['ContentNumber'];
		else if ($raw)
			$value = $row['ContentPlain'];
		else if ($id)
			$value = $row['Id'];
		else
			$value = $row['ContentHtml'];
		return $value;
	}

	/**
	 * 	
	 * @param type $productId
	 * @param type $featureId
	 * @param type $raw
	 * @return type
	 * @throws Exception 
	 */
	function getProductValue($productId, $featureId, $raw = false, $id = false) {
		if (($productId == 0) || ($featureId == 0)) {
			//print_r('Invalid product or feature id.');
			return '';
		}

		$row = $this->wrapped->getProductValue($productId, $featureId);
		if ($raw === 'NUMBER')
			$value = $row['ContentNumber'];
		else if ($raw)
			$value = $row['ContentPlain'];
		else if ($id)
			$value = $row['Id'];
		else
			$value = $row['ContentHtml'];
		return $value;
	}

	/**
	 * Gets the name of the product from product Id
	 * @param int $productId
	 * @return string
	 */
	function getProductName($productId) {
		$record = $this->wrapped->getProductRecord($productId);
		if ($record) {
			return $record['Name'];
		}
		return '';
	}

	/**
	 * Gets the auxiliary name of the product from product Id
	 * @param int $productId
	 * @return string
	 */
	function getProductAuxName($productId) {
		$record = $this->wrapped->getProductRecord($productId);
		if ($record) {
			return $record['AuxiliaryName'];
		}
	}

	/**
	 * Returns a single row from the 'GroupValue' table, or FALSE if there is no row.
	 * @param string $selectFields
	 * @param string $whereClause
	 * @param int $numIndex
	 * @return string SQL Result.
	 */
	function selectGroupValue_singleRow($selectFields, $whereClause) {
		$db = $this->db;
		$res = $db->exec_SELECTquery("$selectFields", "GroupValue", $whereClause, '', '', '1');
		$row = FALSE;
		if ($res) {
			$row = $db->sql_fetch_row($res);
			$db->sql_free_result($res);
		}
		return $row;
	}

	function getChildGroups($parentGroupId, $limit = '') {
		$db = $this->db;
		$groupArray = array();
		//$res = $this->db->sql_query("CALL getChildGroups($parentGroupId,$this->marketId,$limit)");

		$res = $db->exec_SELECTquery(
				"`GroupId`", "GroupChildGroups", "`parentGroupId` = $parentGroupId ", "", "`Sort`", $limit
		);
		if ($res) {
			while ($row = $db->sql_fetch_row($res))
				$groupArray[] = $row[0];
			$db->sql_free_result($res);
		}

		return $groupArray;
	}

	/**
	 * Retrieves the identifier of the child menu entry associated with the
	 * specified product.
	 * @param int $parentMenuId Menu id
	 * @param int $productId Product id
	 * @return int child menu id.
	 */
	function getChildMenuByProductId($parentMenuId, $productId) {
		$menuId = 0;
		if ($parentMenuId > 0) {
			// parentMenuId might be the product's Menu Id
			$row = $this->selectMenu_SingleRow('`ProductId`', "`Id` = $parentMenuId");
			if ($row && $row[0] == $productId) {
				$menuId = $parentMenuId;
			} else {
				// No, check for real parent
				$row = $this->selectMenu_SingleRow('`Id`', "`ProductId` = $productId AND `ParentId` = $parentMenuId");
				if ($row)
					$menuId = $row[0];
			}
		}
		return $menuId;
	}

	/**
	 * Retrieves the child products of the specified parent group.
	 * @param $parentGroupId The identifier of the parent group.
	 * @param $limit         Optional. The LIMIT clause for the SELECT statement.
	 * @return An array containing the identifiers of the child products.
	 */
	function getChildProducts($parentGroupId, $limit = '') {
		$productArray = array();
		$res = $this->db->exec_SELECTquery(
				"m2.`ProductId`, m2.`Id`", "Menu m1, Menu m2", "m1.Id = m2.ParentId AND m1.GroupId = $parentGroupId AND m2.`ProductId` IS NOT NULL", '', 'm2.Ordinal', $limit);
		if ($res) {
			while ($row = $this->db->sql_fetch_object($res)) {
				$productArray[] = array($row->ProductId, $row->Id);
			}
			$this->db->sql_free_result($res);
		} else {
			die($this->db->sql_error());
		}

		return $productArray;
	}

	function getGroupName($groupId) {
		$record = $this->wrapped->getGroupRecord($groupId);
		if ($record) {
			return $record['Name'];
		}
		return '';
	}

	function getGroupAuxName($groupId) {
		$record = $this->wrapped->getGroupRecord($groupId);
		if ($record) {
			return $record['AuxiliaryName'];
		}
		return '';
	}

	/**
	 * Looks up the identifier of a feature using its name.
	 * @param string $featureName the name
	 * @return int Returns the feature id
	 */
	function getFeatureIdByName($featureName, $caseSensitive = true) {
		$featureId = 0;
		do {
			if (strlen($featureName) == 0)
				break;

			$featureId = $this->wrapped->getFeatureIdByName($featureName, $caseSensitive);

			// Try again without case if nothing found
			if ($featureId == 0 && $caseSensitive == true) {
				return $this->getFeatureIdByName($featureName, false);
			}
		} while (FALSE);

		return $featureId;
	}

	/**
	 *
	 * @param type $featureId
	 * @return type
	 * @throws Exception 
	 */
	function getFeatureRecord($featureId) {
		$record = $this->wrapped->getFeatureRecord($featureId);
		if ($record) {
			return (object) $record;
		}
	}

	function getFeatureValueRecord($featureId) {

		$record = $this->wrapped->getFeatureRecord($featureId);
		if ($record) {
			return (object) $record;
		}
	}

	/**
	 * Gets the number of child groups of a specified group. The count is market-dependent. 
	 * @param int $parentGroupId
	 * @return int number of child groups.
	 */
	function selectCountOfChildGroups($parentGroupId) {
		$db = $this->db;
		$count = 0;

		$res = $db->exec_SELECTquery("COUNT(*) AS cnt", "GroupChildGroups", "`ParentGroupId`=$parentGroupId");
		if ($res) {
			$row = $db->sql_fetch_object($res);
			if ($row)
				$count = intval($row->cnt);
			$db->sql_free_result($res);
		}

		return $count;
	}

	/**
	 * Gets the number of child products of a specified group. The count is 
	 * market-dependent.
	 * @param int $parentGroupId
	 * @return int number of child products.
	 */
	function selectCountOfChildProducts($parentGroupId) {
		$db = $this->db;
		$count = 0;

		$res = $db->exec_SELECTquery(
				"COUNT(*) AS cnt", "Menu m1, Menu m2", "m1.Id = m2.ParentId AND m1.GroupId = $parentGroupId  AND m2.`ProductId` IS NOT NULL AND m1.`MarketId`=$this->marketId");
		if ($res) {
			$row = $db->sql_fetch_object($res);
			if ($row)
				$count = intval($row->cnt);
			$db->sql_free_result($res);
		}
		return $count;
	}

	/**
	 *
	 * @param array $menuArray
	 * @param string $where
	 * @return array 
	 */
	function selectMenuItems($menuArray = array(), $where = '') {
		$db = $this->db;
		// only the menu items in the current market will be returned
		if ($this->marketId > 0) {
			if (strlen($where) > 0)
				$where .= ' AND ';
			$where .= "`MarketId` = $this->marketId AND `LanguageId` = $this->languageId";
		}

		// Build an array, having as key the group's identifier and as value
		// the display HTML.
		$res = $db->exec_SELECTquery("*", "Menu", $where, '', "Depth,Ordinal");
		if ($res) {
			while ($row = $db->sql_fetch_assoc($res)) {
				$menuItem = array(
					// Typo3 Specific code moved!
					// see template::fillMenuTitles
					//'uid' => 9000 + $row['Id'],
					'id' => $row['Id'],
						//'title' => '',
						//'ITEM_STATE' => 'NO'
				);

				//check if group exist and it's visible
				if (($row['GroupId']) && ($this->template->tplutils->checkGroupVisibility($row['GroupId']) == true)) {
					$menuItem['groupId'] = $row['GroupId'];
				} elseif (($row['ProductId']) && ($this->template->tplutils->checkProductVisibility($row['ProductId']) == true)) {
					$menuItem['productId'] = $row['ProductId'];
				} elseif (($row['DocumentId']) && ($this->template->tplutils->checkDocumentVisibility($row['DocumentId']) == true)) {
					$menuItem['documentId'] = $row['DocumentId'];
				} else {
					continue;
				}


				//check if Product exist and it's visible	

				/*
				  if ($row['DocumentId'])
				  $menuItem['documentId'] = $row['DocumentId'];
				 */
				$ancestorArray = explode('/', $row['Path']);
				if (count($ancestorArray) == 0) {
					// root element
					$parentMenuItem["_SUB_MENU"][$menuItem['id']] = &$menuItem;
				} else {
					// child element
					$parentMenuItem = &$menuArray;
					foreach ($ancestorArray as $ancestor) {
						if (strlen($ancestor) > 0)
							$parentMenuItem = &$parentMenuItem["_SUB_MENU"][$ancestor];
					}
					$parentMenuItem["_SUB_MENU"][$menuItem['id']] = &$menuItem;
					unset($parentMenuItem);
				}

				unset($ancestorArray);
				unset($menuItem);
				unset($row);
			}
			unset($res);
		}

		return $menuArray;
	}

	function selectFullMenuItems($showProducts, $showDocuments, $lastVisibleLevel) {
		$where = array();
		if (!$showProducts) {
			$where[] = 'ProductId IS NULL';
		}
		if (!$showDocuments) {
			$where[] = 'DocumentId IS NULL';
		}
		if ($lastVisibleLevel > 0) {
			$where[] = 'Depth <= ' . $lastVisibleLevel;
		}
		$where = join(' AND ', $where);
		return $this->selectMenuItems(array(), $where);
	}

	/**
	 *
	 * @param type $currentMenuId
	 * @param type $rootId
	 * @param type $showProducts
	 * @param type $lastVisibleLevel
	 * @return type 
	 */
	function getPartialMenuItems($currentMenuId, $rootId, $showProducts, $showDocuments, $lastVisibleLevel) {
		$db = $this->db;
		$menuArray = array();

		$rootPath = '';
		if (!is_null($rootId)) {
			$row = $this->selectMenu_SingleRow('`Path`', sprintf('`Id` = %d', $rootId));
			if ($row) {
				if ($row[0] == "/") {
					$row[0] = "";
				}
				$rootPath = $row[0] . '/' . $rootId;
				unset($row);
			}
		}

		$menuPath = '';
		$res = $db->exec_SELECTquery("Path", "Menu", "Id=$currentMenuId");
		if ($res) {
			$row = $db->sql_fetch_object($res);
			if ($row) {
				$menuPath = $row->Path;
				unset($row);
			}
			unset($res);
		}

		// Checks whether all of the menu items should be visible.
		if (is_null($rootId)) {
			$where = '`ParentId` IS NULL';
			if (!$showProducts)
				$where .= ' AND `ProductId` IS NULL';
			if (!$showDocuments)
				$where .= ' AND `DocumentId` IS NULL';
			$menuArray = $this->selectMenuItems($menuArray, $where);
		}

		$ancestorArray = explode('/', $menuPath);
		$ancestorArray[] = $currentMenuId;

		// Skip root levels
		$rootArray = explode('/', $rootPath);
		while (
		count($rootArray) > 1 && // Must not exclude the actual Root Element
		count($ancestorArray) > 0 &&
		reset($rootArray) == reset($ancestorArray)) {
			array_shift($rootArray);
			array_shift($ancestorArray);
		}

		if (empty($ancestorArray)) {
			// Current Item == Root Item
			$ancestorArray = array($currentMenuId);
		}

		foreach ($ancestorArray as $ancestor) {
			if (strlen($ancestor) > 0) {
				$where = sprintf('`ParentId` = %d', intval($ancestor));
				if (!$showProducts)
					$where .= ' AND `ProductId` IS NULL';
				if (!$showDocuments)
					$where .= ' AND `DocumentId` IS NULL';
				if ($lastVisibleLevel > 0)
					$where .= " AND `Depth` <= " . $lastVisibleLevel;
				$menuArray = $this->selectMenuItems($menuArray, $where);
			}
		}
		return array('array' => $menuArray, 'path' => $menuPath, 'root' => $rootPath);
	}

	function getAllFeatureDocs($valueFeatureId, $valueType, $parentId, $limit = '') {

		$db = $this->db; // intellisense

		$valueType = strtolower($valueType);

		switch ($valueType) {
			case 'pv':
				$valueID = $this->getProductValue($parentId, $valueFeatureId, false, true);
				$valueDB = 'ProductValueId';
				break;
			case 'gv':
				$valueID = $this->getGroupValue($parentId, $valueFeatureId, false, true);
				$valueDB = 'GroupValueId';
				break;
			case 'dv':
				$valueID = $this->getDocumentValue($parentId, $valueFeatureId, false, true);
				$valueDB = 'DocumentValueId';
				break;
		}

		$docId = array();

		$res = $db->exec_SELECTquery("`DocumentId`", "DocumentLink", "`$valueDB`=$valueID", "", "`Sort`", $limit);
		if (res) {
			while ($row = $db->sql_fetch_row($res)) {
				$docId[] = $row[0];
			}
		}
		return $docId;
	}

	function getDocumentName($documentId) {
		$record = $this->wrapped->getDocumentRecord($documentId);
		if ($record) {
			return $record['Name'];
		}
		return '';
	}

	function getDocumentAuxName($documentId) {
		$record = $this->wrapped->getDocumentRecord($documentId);
		if ($record) {
			return $record['AuxiliaryName'];
		}
		return '';
	}

	function getDocumentFile($documentId) {
		$record = $this->wrapped->getDocumentRecord($documentId);
		if ($record) {
			return $record['FilePath'];
		}
		return '';
	}

	function getDocumentFileLink($documentId) {
		$path = $this->getDocumentFile($documentId);
		if ($path)
			return '<a href="' . $path . '"></a>';
		return '';
	}

	function getDocumentValue($documentId, $featureId, $raw = false, $id = false) {
		if (($documentId == 0) || ($featureId == 0)) {
			//print_r('Invalid document or feature id.');
			return '';
		}

		$row = $this->wrapped->getDocumentValue($documentId, $featureId);
		if ($raw === 'NUMBER')
			$value = $row['ContentNumber'];
		else if ($raw)
			$value = $row['ContentPlain'];
		else if ($id)
			$value = $row['Id'];
		else
			$value = $row['ContentHtml'];
		return $value;
	}

	/**
	 * Gets the number of child document of a specified group. The count is 
	 * market-dependent.
	 * @param int $parentGroupId
	 * @return int number of child products.
	 */
	function selectCountOfChildDocument($parentGroupId) {
		$db = $this->db;
		$count = 0;

		$res = $db->exec_SELECTquery(
				"COUNT(*) AS cnt", "Menu m1, Menu m2", "m1.Id = m2.ParentId AND m1.GroupId = $parentGroupId  AND m2.`DocumentId` IS NOT NULL AND m1.`MarketId`=$this->marketId");
		if ($res) {
			$row = $db->sql_fetch_object($res);
			if ($row)
				$count = intval($row->cnt);
			$db->sql_free_result($res);
		}
		return $count;
	}

	/**
	 * Retrieves the child products of the specified parent group.
	 * @param $parentGroupId The identifier of the parent group.
	 * @param $limit         Optional. The LIMIT clause for the SELECT statement.
	 * @return An array containing the identifiers of the child products.
	 */
	function getChildDocument($parentGroupId, $limit = '') {
		$db = $this->db;
		$documentArray = array();
		$res = $db->exec_SELECTquery(
				"m2.`DocumentId`, m2.`Id`", "Menu m1, Menu m2", "m1.Id = m2.ParentId AND m1.GroupId = $parentGroupId AND m2.`DocumentId` IS NOT NULL", '', 'm2.Ordinal', $limit);
		if ($res) {
			while ($row = $db->sql_fetch_object($res)) {
				$documentArray[] = array($row->DocumentId, $row->Id);
			}
			$db->sql_free_result($res);
		} else {
			die($db->sql_error());
		}

		return $documentArray;
	}

	function selectRealUrlSingleRow($sel, $where, $type = 'row') {
		$rs = $this->db->exec_SELECTquery($sel, RealURLMap_TABLE, $where);
		if ($rs) {
			switch ($type) {
				case 'assoc': $row = $this->db->sql_fetch_assoc($rs);
					break;
				case 'object': $row = $this->db->sql_fetch_object($rs);
					break;
				case 'row':
				default:
					$row = $this->db->sql_fetch_row($rs);
					break;
			}
			$this->db->sql_free_result($rs);
			return $row;
		}
		return null;
	}

	function getProductIdByOid($Oid) {
		$Oid = $this->db->sql_escape($Oid);
		$sql = "	SELECT DISTINCT p.Id 
				FROM Feature f, ProductValue v, (
					SELECT DISTINCT Id
					FROM Product
					WHERE AsimOid = $Oid
				) AS p
				WHERE p.Id = v.ProductId AND f.Id = v.FeatureId
				AND f.LanguageId = $this->languageId AND f.MarketId = $this->marketId";
		$rs = $this->db->sql_query($sql, "Feature f, ProductValue v, Product");
		if ($rs) {
			$row = $this->db->sql_fetch_row($rs);
			$this->db->sql_free_result($rs);
			return $row[0];
		}
		return null;
	}

	function getGroupIdByName($name) {
		// MAKE MARKET AND LANGUAGE DEPENDED!!!!
		//$rs = $this->db->exec_SELECTquery( 'Id', "Groups", "Name = '".$name."'");
		$name = $this->db->sql_escape($name);
		$sql = "	SELECT DISTINCT g.Id 
				FROM Feature f, GroupValue v, (
					SELECT DISTINCT Id
					FROM Groups
					WHERE Name = $name
				) AS g
				WHERE g.Id = v.GroupId AND f.Id = v.FeatureId
				AND f.LanguageId = $this->languageId AND f.MarketId = $this->marketId";
		$rs = $this->db->sql_query($sql, "Feature f, GroupValue v, Groups");
		if ($rs) {
			$row = $this->db->sql_fetch_row($rs);
			$this->db->sql_free_result($rs);

			return $row[0];
		} else {
			echo $err = $this->db->sql_error();
		}
		return null;
	}

	/**
	 * @abstract get Menu id for the given elementId and elementType(groupid,ProductId,DocumentId)
	 * @param int $elementId The element's ID
	 * @param int $elementType The element's type (see tx_ms3commerce_constants::ELEMENT_X)
	 * @return menuId
	 */
	function getMenuIdByElementId($elementId, $elementType) {
		$columnArr = array(
			tx_ms3commerce_constants::ELEMENT_GROUP => 'GroupId', 
			tx_ms3commerce_constants::ELEMENT_PRODUCT => 'ProductId', 
			tx_ms3commerce_constants::ELEMENT_DOCUMENT => 'DocumentId'
		);
		$res = $this->db->exec_SELECTquery('Id', 'Menu', $columnArr[$elementType] . '=' . $elementId);
		$menuId = null;
		if ($row = $this->db->sql_fetch_assoc($res)) {
			$menuId = $row['Id'];
		}
		$this->db->sql_free_result($res);
		return $menuId;
	}

	/**
	 * @abstract get Menu id for the given elementId and elementType(groupid,ProductId,DocumentId)
	 *	in a specific path
	 * @param int $elementId The element's Id
	 * @param int $elementType (see tx_ms3commerce_constants::ELEMENT_X)
	 * @param string $path The path to search in (should not end in '/')
	 * @return menuId
	 */
	function getMenuIdByElementIdInPath($elementId, $elementType, $path) {
		$columnArr = array(
			tx_ms3commerce_constants::ELEMENT_GROUP => 'GroupId', 
			tx_ms3commerce_constants::ELEMENT_PRODUCT => 'ProductId', 
			tx_ms3commerce_constants::ELEMENT_DOCUMENT => 'DocumentId'
		);
		$idColum = $columnArr[$elementType];
		$res = $this->db->exec_SELECTquery('Id', 'Menu', "$idColum = $elementId AND path LIKE '$path%'");
		$menuId = null;
		if ($row = $this->db->sql_fetch_assoc($res)) {
			$menuId = $row['Id'];
		}
		$this->db->sql_free_result($res);
		return $menuId;
	}
	
	/**
	 * @abstract getshopId by languageId and marketId
	 * @param languageId
	 * @param marketId
	 * @return type shopId
	 */
	function getShopId($languageId = null, $marketId = null) {
		if (is_null($languageId))
			$languageId = $this->languageId;
		if (is_null($marketId))
			$marketId = $this->marketId;
		$res = $this->db->exec_SELECTquery('shopId', 'ShopInfo', 'languageId=' . $languageId . ' AND ' . 'marketId=' . $marketId);
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			$this->db->sql_free_result($res);
			return $row[0];
		}
	}

	/**
	 * @abstract returns the contextId by a given realurl path segment
	 * @param type $pathSeg
	 * @return type contextId
	 */
	function getContextIdByPathSeg($pathSeg) {
		$res = $this->db->exec_SELECTquery('asim_mapid', RealURLMap_TABLE, "realurl_seg_mapped='" . $pathSeg . "'");
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			$this->db->sql_free_result($res);
			return $row[0];
		}
	}

	function getMenuLevels($menuId, $productGroupId) {

		$maxLevel = 0;
		$path = '';
		$row = $this->selectMenu_SingleRow('`Path`', '`Id`=' . $menuId, TRUE);
		if ($row) {
			$path = $row[0];
			$row = $this->selectMenu_SingleRow('MAX(`Depth`)', "`Path` LIKE '" . $path . "/" . $menuId . "/%'", TRUE);
			if ($row) {
				$maxLevel = $row[0];
				unset($row);
				if ($maxLevel == 0) {
					$row = $this->selectMenu_SingleRow('MAX(`Depth`)', "`Path` LIKE '" . $path . "/" . $menuId . "%'", TRUE);
					if ($row)
						$maxLevel = $row[0];
				}
			}
		}

		$level = 0;
		$row = $this->selectMenu_SingleRow('`Depth`', sprintf('`GroupId` = %d', $productGroupId), TRUE);
		if ($row) {
			$level = $row[0];
			unset($row);
		}
		if ($maxLevel === null) {
			$maxLevel = $level;
		}

		return array("level" => $level, "maxLevel" => $maxLevel);
	}

	public function checkGroupEmpty($groupId) {
		$res = $this->db->exec_SELECTquery('count(m2.id)', "Menu m1, Menu m2", "m1.GroupId = $groupId AND m2.Path like CONCAT(m1.Path,'/',m1.Id,'%') AND (m2.ProductId IS NOT NULL OR m2.DocumentId IS NOT NULL)");
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			$this->db->sql_free_result($res);
			if (isset($row[0][0]) && $row[0][0]) {
				return false;
			}
			return true;
		}
	}

	public function getMenuIdByProdAndGroup($prodIds, $groupId) {
		return $this->wrapped->getMenuIdByProdAndGroup($prodIds, $groupId);
	}

	public function getMenuIdByDocAndGroup($docId, $groupId) {
		return $this->wrapped->getMenuIdByDocAndGroup($docId, $groupId);
	}

	public function preloadGroupMenus($grpIds, $parentId = 0) {
		$this->wrapped->preloadGroupMenus($grpIds, $parentId);
	}

	public function preloadProductMenus($prodIds, $groupId = 0) {
		$this->wrapped->preloadProductMenus($prodIds, $groupId);
	}

	public function preloadDocMenus($docIds, $groupId = 0) {
		$this->wrapped->preloadDocMenus($docIds, $groupId);
	}

	public function preloadGroups($grpIds) {
		$this->wrapped->preloadGroups($grpIds);
	}

	public function preloadProducts($prodIds) {
		$this->wrapped->preloadProducts($prodIds);
	}

	public function preloadDocuments($docIds) {
		$this->wrapped->preloadDocuments($docIds);
	}

	public function preloadFeatures($featIds) {
		$this->wrapped->preloadFeatures($featIds);
	}

	private function initDBCache($db) {
		if (array_key_exists('nodbcache', $_GET) && $_GET['nodbcache']) {
			$this->wrapped = new tx_ms3commerce_DbUtils_simple($db, $this);
		} else {
			$this->wrapped = new tx_ms3commerce_DbUtils_cached($db, $this);
		}
	}

}

class tx_ms3commerce_DbUtils_simple {

	var $utils;

	/** @var tx_ms3commerce_db */
	var $db;
	var $marketId;
	var $languageId;

	function __construct(&$db, &$utils) {
		$this->db = &$db;
		$this->utils = &$utils;
		$this->languageId = $utils->languageId;
		$this->marketId = $utils->marketId;
	}

	function changeShop($langId, $marketId) {
		$this->languageId = $langId;
		$this->marketId = $marketId;
		//$this->impl->changeShop($langId,$marketId);
	}

	/**
	 * implements getProductRecord
	 * @param type $productId
	 * @return array with product metadata
	 * @throws Exception
	 */
	function getProductRecord($productId) {
		$table = 'Product';
		$where = "Id = $productId";
		$res = $this->db->exec_SELECTquery("*", "$table", $where, '', '', "1");
		if ($res) {
			$row = $this->db->sql_fetch_assoc($res);
			$this->db->sql_free_result($res);
			return $row;
		} else {
			throw new Exception($this->db->sql_error());
		}
	}

	/**
	 * implements getGroupRecord
	 * @param type $groupId
	 * @return array with Group metadata
	 * @throws Exception
	 */
	function getGroupRecord($groupId) {
		$table = 'Groups';
		$where = "Id = $groupId";
		$res = $this->db->exec_SELECTquery("*", "$table", $where, '', '', "1");
		if ($res) {
			$row = $this->db->sql_fetch_assoc($res);
			$this->db->sql_free_result($res);
			return $row;
		} else {
			throw new Exception($this->db->sql_error());
		}
	}

	/**
	 * implements getDocumentRecord
	 * @param type $documentId
	 * @return array with document metadata
	 * @throws Exception
	 */
	function getDocumentRecord($documentId) {
		$table = 'Document';
		$where = "Id = $documentId";
		$res = $this->db->exec_SELECTquery("*", "$table", $where, '', '', "1");
		if ($res) {
			$row = $this->db->sql_fetch_assoc($res);
			$this->db->sql_free_result($res);
			return $row;
		} else {
			throw new Exception($this->db->sql_error());
		}
	}

	/**
	 * implements getFeatureRecord
	 * @param type $featureId eventually filtered by languageId if given
	 * @return array with Feature metadata
	 * @throws Exception
	 */
	function getFeatureRecord($featureId, $language = NULL) {
		$table = 'Feature f LEFT JOIN FeatureValue fv ON f.Id=fv.FeatureId ';
		$where = 'f.Id=' . $featureId;
		if (!$language == NULL) {
			$where = 'f.Id=' . $featureId . ' AND fv.LanguageId=' . $language;
		}
		$res = $this->db->sql_query("SELECT * FROM $table WHERE $where", "Feature f,FeatureValue fv");
		if ($res) {
			$row = $this->db->sql_fetch_assoc($res);
			$this->db->sql_free_result($res);
			return $row;
		} else {
			throw new Exception($this->db->sql_error());
		}
	}

	function getGroupMenuId($groupId, $parentMenuId) {
		$where = "`GroupId` = $groupId";
		if ($parentMenuId)
			$where .= " AND `ParentId` = $parentMenuId";
		$row = $this->utils->selectMenu_SingleRow('`Id`', $where);
		if ($row) {
			return $row[0];
		}
		return null;
	}

	function getMenuIdByProdAndGroup($productid, $groupid) {

		$sql = "SELECT t1.Id as menuId from Menu t1
			 INNER JOIN Menu t2 on t1.ParentId=t2.Id
			 WHERE t1.ProductId=$productid AND t2.GroupId=$groupid";

		$tables = "Menu t1,Menu t2";
		$res = $this->db->sql_query($sql, $tables);
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			$this->db->sql_free_result($res);
			return $row[0];
		}
		return 0;
	}

	function getMenuIdByDocAndGroup($docId, $groupId) {
		$sql = "SELECT t1.Id as menuId from Menu t1
			 INNER JOIN Menu t2 on t1.ParentId=t2.Id
			 WHERE t1.DocumentId=$docId AND t2.GroupId=$groupid";

		$tables = "Menu t1,Menu t2";
		$res = $this->db->sql_query($sql, $tables);
		if ($res) {
			$row = $this->db->sql_fetch_row($res);
			$this->db->sql_free_result($res);
			return $row[0];
		}
		return 0;
	}

	function getGroupValue($groupId, $featureId) {
		$result = $this->db->exec_SELECTquery('FeatureId,Id,ContentHtml,ContentPlain,ContentNumber', "GroupValue", "GroupId=$groupId AND FeatureId=$featureId AND LanguageId=$this->languageId;");
		if (!$result) {
			throw new Exception($this->db->sql_error());
		}
		$row = $this->db->sql_fetch_assoc($result);
		$this->db->sql_free_result($result);
		return $row;
	}

	function getProductValue($productId, $featureId) {
		$result = $this->db->exec_SELECTquery('FeatureId,Id,ContentHtml,ContentPlain,ContentNumber', "ProductValue", "ProductId=$productId AND FeatureId=$featureId AND LanguageId=$this->languageId;");
		if (!$result) {
			throw new Exception($this->db->sql_error());
		}
		$row = $this->db->sql_fetch_assoc($result);
		$this->db->sql_free_result($result);
		return $row;
	}

	function getDocumentValue($documentId, $featureId) {
		$result = $this->db->exec_SELECTquery('FeatureId,Id,ContentHtml,ContentPlain,ContentNumber', "DocumentValue", "DocumentId=$documentId AND FeatureId=$featureId AND LanguageId=$this->languageId;");
		if (!$result) {
			throw new Exception($this->db->sql_error());
		}
		$row = $this->db->sql_fetch_assoc($result);
		$this->db->sql_free_result($result);
		return $row;
	}

	function getFeatureIdByName($featureName, $caseSensitive) {
		if (strlen($featureName) == 0)
			return 0;

		// When using UTF8-Binary collation, case is relevant (cf MySQL http://dev.mysql.com/doc/refman/5.0/en/case-sensitivity.html)
		$makeCaseSensitive = ($caseSensitive ? ' COLLATE utf8_bin' : '');

		$res = $this->db->exec_SELECTquery("`Id`", "Feature", "Name='$featureName' $makeCaseSensitive AND `MarketId`=$this->marketId AND `LanguageId`=$this->languageId");
		if (!$res) {
			throw new Exception($this->db->sql_error());
		}

		$row = $this->db->sql_fetch_object($res);
		if ($row) {
			$featureId = $row->Id;
		} else {
			$featureId = 0;
		}
		$this->db->sql_free_result($res);

		return $featureId;
	}

	public function preloadGroupMenus($grpIds, $parentId) {
		
	}

	public function preloadProductMenus($prodIds, $groupId) {
		
	}

	public function preloadDocMenus($docIds, $groupId) {
		
	}

	public function preloadGroups($grpIds) {
		
	}

	public function preloadProducts($prodIds) {
		
	}

	public function preloadDocuments($docIds) {
		
	}

}

class tx_ms3commerce_DbUtils_cached {

	var $impl;
	var $dbcache;

	/** @var tx_ms3commerce_db */
	var $db;
	var $marketId;
	var $languageId;

	function __construct(&$db, &$utils) {
		$this->impl = new tx_ms3commerce_DbUtils_simple($db, $utils);
		$this->dbcache = &self::initDBCache($db);
		$this->db = &$db;
		$this->languageId = $utils->languageId;
		$this->marketId = $utils->marketId;
	}

	function changeShop($langId, $marketId) {
		$this->clearDbCache();
		$this->languageId = $langId;
		$this->marketId = $marketId;
		$this->impl->changeShop($langId, $marketId);
	}

	function clearDbCache() {
		//unset(self::$s_dbcache);
		//self::$s_dbcache=null;
		//self::initDBCache($this->db);
		//$var=self::$s_dbcache;
		foreach (self::$s_dbcache as $key => $value) {

			self::$s_dbcache[$key] = array();
		}
		foreach (self::$s_dbcachestat as $key => $value) {
			self::$s_dbcachestat[$key] = 0;
		}
		$var = self::$s_dbcache;
	}

	function getGroupMenuId($groupId, $parentMenuId) {
		$key = "$groupId|$parentMenuId";
		if (array_key_exists($key, $this->dbcache[self::$dbc_gm])) {
			self::$s_dbcachestat[self::$dbc_gm] ++;
			return $this->dbcache[self::$dbc_gm][$key];
		}

		$val = $this->impl->getGroupMenuId($groupId, $parentMenuId);
		$this->dbcache[self::$dbc_gm][$key] = $val;
		return $val;
	}

	function getMenuIdByProdAndGroup($productId, $groupId) {
		$key = "$productId|$groupId";
		if (array_key_exists($key, $this->dbcache[self::$dbc_pm])) {
			self::$s_dbcachestat[self::$dbc_pm] ++;
			return $this->dbcache[self::$dbc_pm][$key];
		}

		$val = $this->impl->getMenuIdByProdAndGroup($productId, $groupId);
		$this->dbcache[self::$dbc_pm][$key] = $val;
		return $val;
	}

	function getMenuIdByDocAndGroup($docId, $groupId) {
		$key = "$docId|$groupId";
		if (array_key_exists($key, $this->dbcache[self::$dbc_dm])) {
			self::$s_dbcachestat[self::$dbc_dm] ++;
			return $this->dbcache[self::$dbc_dm][$key];
		}

		$val = $this->impl->getMenuIdByDocAndGroup($docId, $groupId);
		$this->dbcache[self::$dbc_dm][$key] = $val;
		return $val;
	}

	function getGroupValue($groupId, $featureId) {
		$cacheRow = $this->dbcache[self::$dbc_gv][$groupId];
		if (is_null($cacheRow)) {
			$result = $this->db->exec_SELECTquery('FeatureId,Id,ContentHtml,ContentPlain,ContentNumber', "GroupValue", "GroupId=$groupId AND LanguageId=$this->languageId;");
			if ($result) {
				while ($row = $this->db->sql_fetch_assoc($result)) {
					$cacheRow[$row['FeatureId']] = $row;
				}
				$this->db->sql_free_result($result);
				unset($result);

				$this->dbcache[self::$dbc_gv][$groupId] = $cacheRow;
			} else {
				throw new Exception($this->db->sql_error());
			}
		} else {

			self::$s_dbcachestat[self::$dbc_gv] ++;
		}

		$cacheEntry = $cacheRow[$featureId];

		return $cacheEntry;
	}

	/**
	 * checks if a product record is cached if not get it from Db
	 * and put it into the cache and then return the record
	 * @param type $productId
	 * @return string
	 */
	function getProductRecord($productId) {
		$cacheRow = $this->dbcache[self::$dbc_p][$productId];
		if (is_null($cacheRow)) {
			$record = $this->impl->getProductRecord($productId);
			if ($record) {
				$this->dbcache[self::$dbc_p][$productId] = $record;
				$cacheRow = $record;
			} else {
				return '';
			}
		} else {
			//statistics
			self::$s_dbcachestat[self::$dbc_p] ++;
		}
		$cacheEntry = $cacheRow;
		return $cacheEntry;
	}

	/**
	 * checks if a Group record is cached if not get it from Db
	 * and put it into the cache and then return the record
	 * @param type $groupId
	 * @return string
	 */
	function getGroupRecord($groupId) {
		$cacheRow = $this->dbcache[self::$dbc_g][$groupId];
		if (is_null($cacheRow)) {
			$record = $this->impl->getGroupRecord($groupId);
			if ($record) {
				$this->dbcache[self::$dbc_g][$groupId] = $record;
				$cacheRow = $record;
			} else {
				return '';
			}
		} else {
			//statistics
			self::$s_dbcachestat[self::$dbc_g] ++;
		}
		$cacheEntry = $cacheRow;
		return $cacheEntry;
	}

	/**
	 * checks if a Document record is cached if not get it from Db
	 * and put it into the cache and then return the record
	 * @param type $documentId
	 * @return string
	 */
	function getDocumentRecord($documentId) {
		$cacheRow = $this->dbcache[self::$dbc_d][$documentId];
		if (is_null($cacheRow)) {
			$record = $this->impl->getDocumentRecord($documentId);
			if ($record) {
				$this->dbcache[self::$dbc_d][$documentId] = $record;
				$cacheRow = $record;
			} else {
				return '';
			}
		} else {
			//statistics
			self::$s_dbcachestat[self::$dbc_d] ++;
		}
		$cacheEntry = $cacheRow;
		return $cacheEntry;
	}

	function getFeatureRecord($featureId, $language = NULL) {
		$cacheRow = $this->dbcache[self::$dbc_f][$featureId];
		if (is_null($cacheRow)) {
			$record = $this->impl->getFeatureRecord($featureId, $language);
			if ($record) {
				$this->dbcache[self::$dbc_f][$featureId] = $record;
				$cacheRow = $record;
			} else {
				return '';
			}
		} else {
			//statistics
			self::$s_dbcachestat[self::$dbc_f] ++;
		}
		$cacheEntry = $cacheRow;
		return $cacheEntry;
	}

	function getProductValue($productId, $featureId) {
		$cacheRow = $this->dbcache[self::$dbc_pv][$productId];
		if (is_null($cacheRow)) {

			$result = $this->db->exec_SELECTquery('FeatureId,Id,ContentHtml,ContentPlain,ContentNumber', "ProductValue", "ProductId=$productId AND LanguageId=$this->languageId;");
			if ($result) {
				while ($row = $this->db->sql_fetch_assoc($result)) {
					$cacheRow[$row['FeatureId']] = $row;
				}
				$this->db->sql_free_result($result);
				unset($result);

				$this->dbcache[self::$dbc_pv][$productId] = $cacheRow;
			} else {
				throw new Exception($this->db->sql_error());
			}
		} else {

			self::$s_dbcachestat[self::$dbc_pv] ++;
		}

		$cacheEntry = $cacheRow[$featureId];
		return $cacheEntry;
	}

	function getDocumentValue($documentId, $featureId) {
		$cacheRow = $this->dbcache[self::$dbc_dv][$documentId];
		if (is_null($cacheRow)) {

			$result = $this->db->exec_SELECTquery('FeatureId,Id,ContentHtml,ContentPlain,ContentNumber', "DocumentValue", "DocumentId=$documentId AND LanguageId=$this->languageId;");
			if ($result) {
				while ($row = $this->db->sql_fetch_assoc($result)) {
					$cacheRow[$row['FeatureId']] = $row;
				}
				$this->db->sql_free_result($result);
				unset($result);

				$this->dbcache[self::$dbc_dv][$documentId] = $cacheRow;
			} else {
				throw new Exception($this->db->sql_error());
			}
		} else {
			self::$s_dbcachestat[self::$dbc_dv] ++;
		}

		$cacheEntry = $cacheRow[$featureId];
		return $cacheEntry;
	}

	function getFeatureIdByName($featureName, $caseSensitive) {
		if (strlen($featureName) == 0)
			return 0;

		$key = $featureName . '::' . ($caseSensitive ? 'C' : 'I');
		$cache = $this->dbcache[self::$dbc_fn][$key];
		if (!is_null($cache)) {
			self::$s_dbcachestat[self::$dbc_fn] ++;
			return $cache;
		}

		$featureId = $this->impl->getFeatureIdByName($featureName, $caseSensitive);
		$this->dbcache[self::$dbc_fn][$key] = $featureId;
		return $featureId;
	}

	public function preloadGroupMenus($grpIds, $parentId) {
		$in = implode(',', $grpIds);
		$whereClause = "`GroupId` IN ($in)";
		if ($parentId) {
			$whereClause .= " AND `ParentId` = $parentId";
		}

		if ($this->marketId > 0) {
			$whereClause .= " AND `MarketId` = {$this->marketId}";
		}

		$result = $this->db->exec_SELECTquery("Id,GroupId", "Menu", $whereClause);
		if ($result) {
			while ($row = $this->db->sql_fetch_row($result)) {
				$key = $row[1] . "|" . $parentId;
				$this->dbcache[self::$dbc_gm][$key] = $row[0];
			}
			$this->db->sql_free_result($result);
			unset($result);
		}
	}

	public function preloadProductMenus($prodIds, $groupId) {
		$in = implode(',', $prodIds);
		$sql = "SELECT t1.Id as menuId , t1.ProductId from Menu t1 " .
				"INNER JOIN Menu t2 on t1.ParentId=t2.Id " .
				"WHERE t1.ProductId in ($in) AND t2.GroupId=$groupId";

		if ($this->marketId > 0) {
			$sql .= " AND t1.MarketId = {$this->marketId}";
		}

		$result = $this->db->sql_query($sql, "Menu t1,Menu t2");
		if ($result) {
			while ($row = $this->db->sql_fetch_row($result)) {
				$key = $row[1] . "|" . $groupId;
				$this->dbcache[self::$dbc_pm][$key] = $row[0];
			}
			$this->db->sql_free_result($result);
			unset($result);
		}
	}

	public function preloadDocMenus($docIds, $groupId) {
		$in = implode(',', $docIds);
		$sql = "SELECT t1.Id as menuId , t1.DocumentId from Menu t1 " .
				"INNER JOIN Menu t2 on t1.ParentId=t2.Id " .
				"WHERE t1.DocumentId in ($in) AND t2.GroupId=$groupId";

		if ($this->marketId > 0) {
			$sql .= " AND t1.MarketId = {$this->marketId}";
		}

		$result = $this->db->sql_query($sql, "Menu t1,Menu t2");
		if ($result) {
			while ($row = $this->db->sql_fetch_row($result)) {
				$key = $row[1] . "|" . $groupId;
				$this->dbcache[self::$dbc_dm][$key] = $row[0];
			}
			$this->db->sql_free_result($result);
			unset($result);
		}
	}

	public function preloadGroups($grpIds) {
		$grpIds = $this->filterIDList($grpIds, array_keys($this->dbcache[self::$dbc_gv]));

		$in = implode(',', $grpIds);
		$sql = 'SELECT * FROM Groups g' .
				' LEFT JOIN GroupValue gv ON g.Id=gv.GroupId' .
				" WHERE g.Id IN ($in) AND gv.LanguageId=$this->languageId;";
		$result = $this->db->sql_query($sql, "Groups g,GroupValue gv");
		if ($result) {
			$newCache = array();
			while ($row = $this->db->sql_fetch_assoc($result)) {
				$groupId = $row['GroupId'];
				$featureId = $row['FeatureId'];
				$newCache[$groupId][$featureId] = $row;
			}
			$this->db->sql_free_result($result);
			unset($result);
			foreach ($newCache as $group => &$cacheRow) {
				$this->dbcache[self::$dbc_gv][$group] = &$cacheRow;
			}
		}
	}

	public function preloadProducts($prodIds) {
		$prodIds = $this->filterIDList($prodIds, array_keys($this->dbcache[self::$dbc_pv]));

		$in = implode(',', $prodIds);
		$sql = 'SELECT * FROM Product p' .
				' LEFT JOIN ProductValue pv ON p.Id=pv.ProductId' .
				" WHERE p.Id IN ($in) AND pv.LanguageId=$this->languageId;";
		$result = $this->db->sql_query($sql, "Product p,ProductValue pv");
		if ($result) {
			$newCache = array();
			while ($row = $this->db->sql_fetch_assoc($result)) {
				$prodId = $row['ProductId'];
				$featureId = $row['FeatureId'];
				$newCache[$prodId][$featureId] = $row;
			}
			$this->db->sql_free_result($result);
			unset($result);
			foreach ($newCache as $prod => &$cacheRow) {
				$this->dbcache[self::$dbc_pv][$prod] = &$cacheRow;
			}
		}
	}

	public function preloadDocuments($docIds) {
		$docIds = $this->filterIDList($docIds, array_keys($this->dbcache[self::$dbc_dv]));

		$in = implode(',', $docIds);
		$sql = 'SELECT * FROM Document d' .
				' LEFT JOIN DocumentValue dv ON d.Id=dv.DocumentId' .
				" WHERE d.Id IN ($in) AND dv.LanguageId=$this->languageId;";
		$result = $this->db->sql_query($sql, "Document d,DocumentValue dv");
		if ($result) {
			$newCache = array();
			while ($row = $this->db->sql_fetch_assoc($result)) {
				$docId = $row['DocumentId'];
				$featureId = $row['FeatureId'];
				$newCache[$docId][$featureId] = $row;
			}
			$this->db->sql_free_result($result);
			unset($result);
			foreach ($newCache as $doc => &$cacheRow) {
				$this->dbcache[self::$dbc_dv][$doc] = &$cacheRow;
			}
		}
	}

	public function preloadFeatures($featIds) {
		$featIds = $this->filterIDList($featIds, array_keys($this->dbcache[self::$dbc_f]));

		$in = implode(',', $featIds);
		$sql = 'SELECT *' .
				'FROM Feature f LEFT JOIN FeatureValue fv ON f.Id=fv.FeatureId ' .
				"f.Id IN ($in) AND fv.LanguageId=$this->languageId;";
		$result = $this->db->sql_query($sql, "Feature f,FeatureValue fv");
		if ($result) {
			$newCache = array();
			while ($row = $this->db->sql_fetch_assoc($result)) {
				$featureId = $row['Id'];
				$newCache[$featureId] = $row;
			}
			$this->db->sql_free_result($result);
			unset($result);
			foreach ($newCache as $featId => &$cacheRow) {
				$this->dbcache[self::$dbc_f][$featId] = &$cacheRow;
			}
		}
	}

	private function filterIDList($toFilter, $existing) {
		$filtered = array();
		$exKeys = array_fill_keys($existing, 1);
		foreach ($toFilter as $id) {
			if (!array_key_exists($id, $exKeys)) {
				$filtered[] = $id;
			}
		}
		return $filtered;
	}

	//////////////////////////////////////////

	static $dbc_f = 1;
	static $dbc_g = 2;
	static $dbc_p = 3;
	static $dbc_d = 4;
	static $dbc_gv = 5;
	static $dbc_pv = 6;
	static $dbc_dv = 7;
	static $dbc_fn = 8;
	static $dbc_gm = 9;
	static $dbc_pm = 10;
	static $dbc_dm = 11;
	static $s_dbcache;
	static $s_dbcachestat;

	private static function &initDBCache(tx_ms3commerce_db $db) {
		if (is_null(self::$s_dbcache)) {
			self::$s_dbcache = array(
				self::$dbc_f => array(),
				self::$dbc_g => array(),
				self::$dbc_p => array(),
				self::$dbc_d => array(),
				self::$dbc_gv => array(),
				self::$dbc_pv => array(),
				self::$dbc_dv => array(),
				self::$dbc_fn => array(),
				self::$dbc_gm => array(),
				self::$dbc_pm => array(),
				self::$dbc_dm => array(),
			);
			self::$s_dbcachestat = array(
				self::$dbc_f => 0,
				self::$dbc_g => 0,
				self::$dbc_p => 0,
				self::$dbc_d => 0,
				self::$dbc_gv => 0,
				self::$dbc_pv => 0,
				self::$dbc_dv => 0,
				self::$dbc_fn => 0,
				self::$dbc_gm => 0,
				self::$dbc_pm => 0,
				self::$dbc_dm => 0,
			);
		}

		return self::$s_dbcache;
	}

	public static function printCacheStat() {
		echo "F Cache Hits: " . self::$s_dbcachestat[self::$dbc_f] . "<br/>";
		echo "G Cache Hits: " . self::$s_dbcachestat[self::$dbc_g] . "<br/>";
		echo "P Cache Hits: " . self::$s_dbcachestat[self::$dbc_p] . "<br/>";
		echo "D Cache Hits: " . self::$s_dbcachestat[self::$dbc_d] . "<br/>";
		echo "GV Cache Hits: " . self::$s_dbcachestat[self::$dbc_gv] . "<br/>";
		echo "PV Cache Hits: " . self::$s_dbcachestat[self::$dbc_pv] . "<br/>";
		echo "DV Cache Hits: " . self::$s_dbcachestat[self::$dbc_dv] . "<br/>";
		echo "FN Cache Hits: " . self::$s_dbcachestat[self::$dbc_fn] . "<br/>";
		echo "GM Cache Hits: " . self::$s_dbcachestat[self::$dbc_gm] . "<br/>";
		echo "PM Cache Hits: " . self::$s_dbcachestat[self::$dbc_pm] . "<br/>";
		echo "DM Cache Hits: " . self::$s_dbcachestat[self::$dbc_dm] . "<br/>";
	}

}

?>
