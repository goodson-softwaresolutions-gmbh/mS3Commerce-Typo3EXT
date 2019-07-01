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
 * Contains all needed methods for handling the relatioships between Elements (Products, Groups, Documents)
 * 
 */
class tx_ms3commerce_relations {

	/** @var tx_ms3commerce_template */
	var $template;
	/** @var tx_ms3commerce_db */
	var $db;

	public function __construct($template, $db = null) {
		$this->template = $template;

		if ($db) {
			$this->db = $db;
		} else {
			$this->db = $this->template->db;
		}
	}

	/**
	 * Check if the marker is a Relation marker
	 * @param type $marker
	 * @return boolean 
	 */
	function isRelationMarker($marker) {
		if ($this->getRelationsMarkerParts($marker)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the Relations parts out of the marker 
	 * @param type $marker
	 * @return array|boolean 
	 */
	function getRelationsMarkerParts($marker) {
		$matches = array();
		if (preg_match("/^REL_(.{1,50})_RELVIEW_(.{1,50})$/", $marker, $matches)) {
			$relparts['name'] = $matches[1];
			$relparts['template'] = $matches[2];

			return $relparts;
		} else {
			return false;
		}
	}

	/**
	 * Replace the Relation marker with content out of the database depending on
	 * the relation destination Element type 
	 * 
	 * @param type $parentId 
	 * @param type $marker
	 */
	function substituteRelationMarker($parentId, $marker, $sourcePrefix) {

		$content = '';
		$relParts = $this->getRelationsMarkerParts($marker);
		
		if (!$relParts) {
			return $content;
		}
		// wich view template?	
		$relViewTemplate = $this->template->plugin->getTemplate('###RELVIEW_' . $relParts['template'] . '###');
		if (strlen($relViewTemplate) == 0) {
			return $content;
		}
		### G R O U P S ###
		if ($groupMarker = $this->template->tplutils->getSubpart($relViewTemplate, '###GROUP###')) {
			$groupMarker = $this->getRelationContent($parentId, $sourcePrefix.'Id', $relParts, 1, $groupMarker);
			$relViewTemplate = $this->template->tplutils->substituteSubpart($relViewTemplate, "###GROUP###", $groupMarker);
		}
		### P R O D U C T S ###
		if ($productMarker = $this->template->tplutils->getSubpart($relViewTemplate, '###PRODUCT###')) {
			$productMarker = $this->getRelationContent($parentId, $sourcePrefix.'Id', $relParts, 2, $productMarker);
			$relViewTemplate = $this->template->tplutils->substituteSubpart($relViewTemplate, "###PRODUCT###", $productMarker);
		}
		### D O C U M E N T S ###
		if ($documentMarker = $this->template->tplutils->getSubpart($template, '###DOCUMENT###')) {
			$documentMarker = $this->getRelationContent($parentId, $sourcePrefix.'Id', $relParts, 3, $documentMarker);
			$relViewTemplate = $this->template->tplutils->substituteSubpart($relViewTemplate, "###DOCUMENT###", $documentMarker);
		}
		
		// Replace any feature markers
		$markers = $this->template->tplutils->getMarkerArray($relViewTemplate);
		$subs = $this->template->fillFeatureMarkerContentArray($markers);
		$relViewTemplate = $this->template->tplutils->substituteMarkerArray($relViewTemplate, $subs);

		return $relViewTemplate;
	}

	/**
	 * Fetch the values for the destination (1=Group, 2=Product,3=Documents)
	 * @param type $parentId
	 * @param type $relParts
	 * @param type $relType
	 * @return string 
	 */
	function getRelationContent($parentId,$parentIdType,$relParts, $relType, $productMarker) {
		$db = $this->template->db;
		$rs = $db->exec_SELECTquery('*', 'Relations', $parentIdType."=" . $parentId . " AND DestinationType=" . $relType . " AND Name='" . $relParts['name'] . "'", '', "Id");

		if ($db->sql_num_rows($rs) == 0) {
			return '';
		}
		$repeatedPart = $this->template->tplutils->getSubpart($productMarker, '###REPEAT###');
		if (strlen($repeatedPart) > 0) {
			$smArray = $this->template->tplutils->getMarkerArray($repeatedPart);

			while ($row = $this->template->db->sql_fetch_assoc($rs)) {

				switch ($relType) {
					### G R O U P S ###
					case 1:
						if(!$this->template->tplutils->checkGroupVisibility($row['DestinationId'])){
							continue 2;
						}
						$markers = $this->template->fillGroupMarkerContentArray($smArray, $row['DestinationId']);
						break;
					### P R O D U C T S ###
					case 2:
						if(!$this->template->tplutils->checkProductVisibility($row['DestinationId'])){
							continue 2;
						}
						$menuId = $this->template->dbutils->getMenuIdByElementId($row['DestinationId'], $relType);
						if(!$menuId){
							continue 2;
						}
						$markers = $this->template->fillProductMarkerContentArray($smArray, $row['DestinationId'], $menuId);
						break;
					### D O C U M E N T S ###
					case 3:
						if(!$this->template->tplutils->checkDocumentVisibility($row['DestinationId'])){
							continue 2;
						}
						$menuId = $this->template->dbutils->getMenuIdByElementId($row['DestinationId'], $relType);
						if(!$menuId){
							continue 2;
						}
						$markers = $this->template->fillDocumentMarkerContentArray($smArray, $row['DestinationId'],$menuId);
						break;
				}

				$markers['###REL_AMOUNT###'] = $row['Amount'];
				$markers['###REL_POSITION###'] = $row['Position'];
				$content.=$this->template->tplutils->substituteMarkerArray($repeatedPart, $markers);
			}
			
			if(!$content){
				return "";
			}

			$content = $this->template->tplutils->substituteSubpart($productMarker, "###REPEAT###", $content);
		}
		
		$content = $this->template->tplutils->substituteSubpart($content, "###REMOVE_SUBPART###", '');
		return $content;
	}

	public function getRelForGroupId($groupId, $destType = null, $relName = null) {
		$groupRels = $this->getRelations("group", $groupId, $destType, $relName);
		return $groupRels;
	}

	public function getRelForProductId($productId, $destType = null, $relName = null) {
		$prodRels = $this->getRelations("product", $productId, $destType, $relName);
		return $prodRels;
	}

	public function getRelForDocumentId($documentId, $destType = null, $relName = null) {
		$docRels = $this->getRelations("document", $documentId, $destType, $relName);
		return $docRels;
	}
	/**
	 * Retrieve the Destination ElementId if exists (if not boolean false) for a given Element Id
	 * @param type $source (the given ElementId)
	 * @param type $Id
	 * @param type $relType
	 * @param type $name (relations name)
	 * @return ID or boolean
	 */
	private function getRelations($source, $Id, $relType = null, $name = null) {
		switch ($source) {
			case 'group':
				$where = "GroupId=" . $Id;
				break;
			case 'product':
				$where = "ProductId=" . $Id;
				break;
			case 'document':
				$where = "DocumentId=" . $Id;
				break;
		}

		if ($relType) {
			$where.=" AND DestinationType=" . $relType;
		}
		if ($name) {
			$where.=" AND Name=" . $this->db->sql_escape($name);
		}

		if ($Id) {
			$res = $this->db->exec_SELECTquery("DestinationId AS Id, DestinationType AS Type,Amount, Position,Name", 'Relations', $where);
			while ($row = $this->db->sql_fetch_object($res)) {
				$resObjArr[] = $row;
			}
			return $resObjArr;
		}

		return false;
	}

}

?>
