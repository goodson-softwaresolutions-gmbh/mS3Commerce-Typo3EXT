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

require_once('class.tx_ms3commerce_constants.php');

/**
 * Class Linker contains all variables and Methods that concerns generating Links
 * @see getGroupLink(),getProductLink(),getDocumentLink(),
 * 
 */
class tx_ms3commerce_linker
{
	/** @var tx_ms3commerce_db */
	var $db;
	/** @var tx_ms3commerce_DbUtils */
	var $dbutils;
	/** @var itx_ms3commerce_custom */
	var $custom;
	/** @var tx_ms3commerce_realurl_simple */
	var $simpleRealURL;
	
	var $forceRealURL = false;
	
	var $cObj;
	
	var $conf;
	var $currentMenuId;
	var $detailPageId;
	var $listPageId;
	var $pageRoleFeatureId;

	/**
	 * 
	 * @param tx_ms3commerce_db $db
	 * @param type $conf
	 * @param type $cObj
	 * @param tx_ms3commerce_template $template
	 * @param tx_ms3commerce_DbUtils $dbutils
	 * @param itx_ms3commerce_custom $custom
	 */
	function __construct($db, $conf, $cObj, $template = null, $dbutils = null, $custom = null) {
		$this->db = $db;
		$this->conf = $conf;
		$this->cObj = $cObj;
		
		if ($dbutils == null) {
			$dbutils = new tx_ms3commerce_DbUtils($db, $conf['market_id'],$conf['language_id'], null);
		}
		$this->dbutils = $dbutils;
		$this->custom = $custom;
		
		if ($template) {
			$this->detailPageId = $template->detailPageId;
			$this->listPageId = $template->listPageId;
			$this->pageRoleFeatureId = $template->pageRoleFeatureId;
		} else {
			$this->detailPageId = $conf['detail_pid'];
			$this->listPageId = $conf['list_pid'];
			$pageFeature = $conf['page_role_feature_name'];
			// Lade pagerolefeatureid!
			$this->pageRoleFeatureId =$dbutils->getFeatureIdByName($pageFeature);
		}
		
		$this->typoLanId = intval( NVL($_GET[tx_ms3commerce_constants::QUERY_PARAM_TLANID], 0) );
	}
	
	function forceRealURLOnStandalone()
	{
		$this->forceRealURL = true;
		$this->simpleRealURL = new tx_ms3commerce_realurl_simple();
	}
	/**
	 * Generates a group link
	 * @param type $groupId 
	 * @param type $menuId
	 * @param type $pid
	 * @param type $itemStart
	 * @param type $addParams
	 * @return string (link)
	 */
	function getGroupLink($groupId, $menuId = 0, $pid = 0, $itemStart = 0, $addParams = array())
	{
		if ($groupId == 0) {
			return "";
		}
		if ($menuId == 0)
		{
			$row = $this->dbutils->selectMenu_SingleRow('`Id`', "`GroupId`=$groupId");
			if ($row)
				$menuId = $row[0];
		}

		// the page can be overwritten...
		if ($pid == 0)
		{
			$pid = $this->listPageId;
			if ($this->pageRoleFeatureId > 0)
			{
				$pid = intval($this->dbutils->getGroupValue($groupId, $this->pageRoleFeatureId));
				if ($pid == 0)
					$pid = $this->listPageId;
			}
		}
		
		if ($this->custom)
			$href = $this->custom->buildGroupLink($groupId, $menuId, $pid);
		if (!$href) 
		{
			if ($this->conf['use_map_id_links']) {
				$href = $this->getMapIdLinkQueryGroup( $groupId );
			} 
			if ($href == null) {
				// Build default link
				$href = sprintf('&%s=%d', tx_ms3commerce_constants::QUERY_PARAM_GID, $groupId);
				if ($menuId > 0)
					$href .= sprintf('&%s=%d', tx_ms3commerce_constants::QUERY_PARAM_MID, $menuId);
			}
		}
		
		if ($itemStart > 0)
			$href .= sprintf('&%s=%d', tx_ms3commerce_constants::QUERY_PARAM_ITEMSTART, $itemStart);
		$href .= $this->handleLParam();
		
		if (!empty($addParams)) {
			$href .= is_array($addParams) ? TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('', $addParams) : $addParams;
		}
		
		$hrefT3 = $this->pi_getPageLink( $pid, '', $href );
		// Hack: If Page is "hidden in menu", getPageLink returns an empty string
		// Use built link instead
		if ( strlen($hrefT3) > 0 ) {
			return $hrefT3;
		} else {
			return "index.php?id=$pid$href";
		}
		
		return $href;
	}
	/**
	 * Generates a Product Link 
	 * @param type $productId
	 * @param type $menuId
	 * @param type $pid
	 * @param type $addParams
	 * @return string
	 */
	function getProductLink($productId, $menuId = 0, $pid = 0, $addParams = array())
	{
		if ($productId == 0) {
			return "";
		}
		if ($menuId == 0)
		{
			$row = $this->dbutils->selectMenu_SingleRow('`Id`', "`ProductId`=$productId");
			if ($row)
				$menuId = $row[0];
		}

		if (!$pid) {
			$pid = $this->detailPageId;
		}
		
		if ($this->custom)
			$href = $this->custom->buildProductLink($productId, $menuId, $pid);
		if (!$href)
		{
			if ($this->conf['use_map_id_links']) {
				$href = $this->getMapIdLinkQueryProduct( $menuId );
			}
			if (!$href){
				$href = sprintf('&%s=%d', tx_ms3commerce_constants::QUERY_PARAM_PID, $productId);
				if ($menuId > 0)
					$href .= sprintf('&%s=%d', tx_ms3commerce_constants::QUERY_PARAM_MID, $menuId);
			}
		}
		
		$href .= $this->handleLParam();
		if (!empty($addParams)) {
			$href .= is_array($addParams) ? TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('', $addParams) : $addParams;
		}
		$hrefT3 = $this->pi_getPageLink( $pid, '', $href );
		
		// Hack: If Page is "hidden in menu", getPageLink returns an empty string
		// Use built link instead
		if ( strlen($hrefT3) > 0 ) {
			return $hrefT3;
		} else {
			return "index.php?id=$pid$href";
		}
	}
	/**
	 * Generates a Document Link 
	 * @param type $documentId
	 * @param type $menuId
	 * @param type $pid
	 * @param type $download
	 * @param type $addParams
	 * @return string
	 */
	public function getDocumentLink($documentId, $menuId = 0, $pid = 0, $download = false, $addParams = array())
	{
		if (!$documentId) {
			return "";
		}
		if (!$menuId)
		{
			$row = $this->dbutils->selectMenu_SingleRow('`Id`', "`DocumentId`=$documentId");
			if ($row)
				$menuId = $row[0];
		}

		if (!$pid) {
			$pid = $this->detailPageId;
		}
		
		$typeNum = null;
		$downType = '';
		if ($download)
		{
			$downloadpage = $this->conf["download_pid"];
			if ($downloadpage)
			{
				$pid = intval($downloadpage);
				$typeNum = MS3C_DOCUMENT_DOWNLOAD_PAGETYPE;
				$downType = '&type='.MS3C_DOCUMENT_DOWNLOAD_PAGETYPE;
			}
		}
		
		if ($this->custom)
			$href = $this->custom->buildDocumentLink($documentId, $menuId, $pid);
		if (!$href)
		{
			if ($this->conf['use_map_id_links']) {
				$href = $this->getMapIdLinkQueryDocument( $menuId, $documentId);
			}
			if (!$href){
				$href = sprintf('&%s=%d', tx_ms3commerce_constants::QUERY_PARAM_DID, $documentId);
				if ($menuId > 0)
					$href .= sprintf('&%s=%d', tx_ms3commerce_constants::QUERY_PARAM_MID, $menuId);
			}
		}
		$href .= $this->handleLParam();
		
		if (!empty($addParams)) {
			$href .= is_array($addParams) ? TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('', $addParams) : $addParams;
		}
		
		$hrefT3 = $this->pi_getPageLink( $pid, '', $href, true, $typeNum );
		// Hack: If Page is "hidden in menu", getPageLink returns an empty string
		// Use built link instead
		if ( strlen($hrefT3) > 0 ) {
			if ($download && $this->conf['forceDownloadExtension']) {
				// Fix links so that the file extension is set correctly
				$hrefT3 = $this->fixDownloadExtension($hrefT3, $documentId);
			}
			return $hrefT3;
		} else {
			return "index.php?id=$pid$href$downType";
		}
	}
	
	private function fixDownloadExtension($hrefT3, $documentId) {
		$uParts = parse_url($hrefT3);
		$query = $uParts['query'];
		$url = str_replace('?'.$query, '', $hrefT3);
		$params = TYPO3\CMS\Core\Utility\GeneralUtility::explodeUrl2Array($query);
		
		// link should contain type={MS3C_DOCUMENT_DOWNLOAD_PAGETYPE}, and be a
		// RealURL layed out link. RealURL might use a Folder (ending in "/"),
		// or HTML-Link (ending in ".html" or ".htm"). Find these
		
		if (!array_key_exists('type', $params) || $params['type'] != MS3C_DOCUMENT_DOWNLOAD_PAGETYPE) {
			return $hrefT3;
		}
		
		// find last part (as html or folder)
		if (!preg_match('#(.+)(?:\.html|\.htm|/)$#', $url, $matches)) {
			return $hrefT3;
		}
		
		/*
		// ALTERNATIVE TO THE 2 ABOVE:
		// ALWAYS Force overwrite extension
		if (!preg_match('#(.+)(?:\.[^\.]+|/)$#', $url, $matches)) {
			return $hrefT3;
		}
		*/
		
		// Find real extension
		$path = $this->dbutils->getDocumentFile($documentId);
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		// Replace extension
		$url = $matches[1];
		$url .= '.'.$ext;
		
		// Add other parameters again
		unset($params['type']);
		$url .= TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('', $params);
		
		return $url;
	}
	
	private function getMapIdLinkQueryProduct( $menuId )
	{
		$context = $this->dbutils->selectMenu_SingleRow('contextid', "id = $menuId");
		if (!$context) {
			return null;
		}
		
		return $this->getMapIdLink($context[0]);
	}
	
	private function getMapIdLinkQueryGroup( $groupId )
	{
		$context = $this->dbutils->selectMenu_SingleRow('contextid', "groupid = $groupId");
		if (!$context) {
			return null;
		}
		
		return $this->getMapIdLink($context[0]);
	}
	
	private function getMapIdLinkQueryDocument( $menuId, $documentId)
	{
		$context = $this->dbutils->selectMenu_SingleRow('contextid', "id = $menuId and DocumentId is Not null");
		if (!$context) {
			$context = $this->dbutils->selectDocument_singleRow('contextid', "id = $documentId");
			if (!$context) {
				return null;
			}
			$context = array($context->contextid);
		}
		
		return $this->getMapIdLink($context[0]);
	}
	
	private function getMapIdLink( $context ) 
	{
		// Get nr of parents to generate
		$depth = intval($this->conf['realurl_level_depth']);
		$sel = '';
		for ($ct = 0; $ct < $depth; ++$ct) {
			$sel .= "asim_mapid_dummy_$ct,";
		}
		$sel .= 'asim_mapid';
		
		// Find the mapping
		$where = "asim_mapid = '$context'";
		$row = $this->dbutils->selectRealUrlSingleRow($sel, $where, 'assoc');
		if ($row) {
			// Build query string dummies
			$query = '';
			for ($ct = 0; $ct < $depth; ++$ct) {
				$query .= '&' .
					tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY . '[' . tx_ms3commerce_constants::QUERY_PARAM_TX_DUMMYID . $ct . ']' .
						"={$row["asim_mapid_dummy_$ct"]}";
			}

			// Build query string mapping
			$query .= '&' .
				tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY . '[' . tx_ms3commerce_constants::QUERY_PARAM_TX_CONTEXTID . ']'.
				"=$context";

			return $query;
		}
		return null;
	}
	
	function pi_getPageLink( $pid, $target = '', $params = array(), $enableCache = true, $typeNum = null )
	{
		if (is_null($this->cObj)) {
			if ($this->forceRealURL) {
				return $this->simpleRealURL->buildLink($pid, $params);
			}
			return "";
		}
		
		// TAKEN FROM tslib_pibase::pi_getPageLink and its called methods
		$conf = array();
		$conf['parameter'] = $pid;
		if ($typeNum) {
			$conf['parameter'].=",$typeNum";
		}
		if ($target) {
			$conf['target'] = $target;
			$conf['extTarget'] = $target;
			$conf['fileTarget'] = $target;
		}
		if (is_array($params)) {
			if (count($params)) {
				$conf['additionalParams'] .= TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('', $params);
			}
		} else {
			$conf['additionalParams'] .= $params;
		}
		
		//// RELEVANT CHANGE: CALCULATE CHASH!
		if ( USE_CHASH && $enableCache ) {
			$conf['useCacheHash'] = true;
		}
		////
		
		$this->cObj->typolink('', $conf);
		return $this->cObj->lastTypoLinkUrl;
	}
	
	private function handleLParam()
	{
		static $l_paramExcludeList = null;
		if ( $this->conf['addLanguageParam'] ) {
			if ( $l_paramExcludeList === null ) {
				if ( array_key_exists('languageParamExclude', $this->conf ) ) {
					$l_paramExcludeList = preg_split('/,/', $this->conf['languageParamExclude']);
				} else {
					$l_paramExcludeList = array();
				}
			}
			
			if ( array_search($this->typoLanId, $l_paramExcludeList) === false ) {
				return '&L=' . $this->typoLanId;
			}
		}
		
		return '';
	}
}

?>
