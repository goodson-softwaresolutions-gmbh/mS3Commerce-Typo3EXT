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
 * tx_ms3commerce_template
 * All code for working with templates goes here.
 * This file is independent of the CMS.
 */

require_once('class.tx_ms3commerce_search.php');
require_once('class.itx_ms3commerce_plugin.php');
require_once('class.tx_ms3commerce_db.php');
require_once('class.tx_ms3commerce_ajaxbuilder.php');
require_once('class.tx_ms3commerce_FeFunctions.php');
require_once('class.tx_ms3commerce_TplUtils.php');
require_once('class.tx_ms3commerce_FormBuilder.php');
require_once('class.tx_ms3commerce_SMZ.php');
require_once('class.tx_ms3commerce_DbUtils.php');
require_once('class.tx_ms3commerce_relations.php');
require_once('itx_ms3commerce_custom_shop.php');
@include_once('class.tx_ms3commerce_OCI.php');

//define('PLACEMENT_PARAMETER_REGEXP_PART', '\(((?:\w=[^,]*,)*(?:\w=[^\)]*))?\)');
define('PLACEMENT_PARAMETER_REGEXP_PART','\((.*)\)');
define('CHECK_IF_NOT_MODIFIED', true);

define('GROUP_CONTEXT', 1);
define('PRODUCT_CONTEXT', 2);
define('DOCUMENT_CONTEXT', 3);

class tx_ms3commerce_gui_version
{
    const jquery1_6 = 116;
    const mootools1_4 = 214;
    const guiDefault=jquery1_6;
}

/**
 * class template handles all template related activities
 * @see getTemplate(),getResultsOutputTemplate
 * 
 * 
 */
class tx_ms3commerce_template
{
	var $languageId     = 1;

	/* GET VARIABLES */
	var $productId      = 0;
	var $documentId     = 0;
	var $productGroupId = 0;
	var $currentMenuId  = 0;
	var $itemStart      = 1;
	var $childProductId = 0;
	var $childGroupId   = 0;
	var $typoLanId      = 0;

	/* CONF VARIABLES */
	var $conf;
	var $detailPageId   = 1;
	var $listPageId     = 1;
	var $itemsPerPage   = 0;
	//function itemsPerPage() { static $a; return $a?:$a=($this->conf['items_per_page']?:(1)); }
	var $marketId       = 0;
	var $noResultsPageId = 0;
	var $ResultsPageId = 0;
	var $guiversion;
	var $debug_enabled  = FALSE;
	var $restrictionValues = array();
	var $restrictionFeatureId = 0;
	var $userRightsFeatureId = 0;
	var $userRightsValues = array();
	
	var $breadCrumbConf = array();

	/* MENU OPTIONS */
	var $showProducts     = TRUE;
	var $showDocuments     = TRUE;
	var $lastVisibleLevel = FALSE;
	var $menuDisplayRoleFeatureId = 0;
	var $pageRoleFeatureId = 0;
	var $rootMenuId = 0;
	var $skipMenuLevels = 0;
	var $searchMenuIds = null;
	var $pageLinkAddParameters = FALSE;

	/* TEMPLATE VARS */
	/** @var itx_ms3commerce_plugin */
	var $plugin;
	/** @var tx_ms3commerce_db */
	var $db;
	/** @var itx_ms3commerce_custom */
	var $custom;
	/** @var tx_ms3commerce_FeFunctions */
	var $fefunctions;
	/** @var tx_ms3commerce_TplUtils */
	var $tplutils;
	/** @var tx_ms3commerce_FormBuilder */
	var $formbuilder;
	/** @var tx_ms3commerce_ajaxbuilder */
	var $ajaxbuilder;
	/** @var tx_ms3commerce_SMZ */
	var $smz;
	/** @var tx_ms3commerce_relations */
	var $relations;
	/** @var tx_ms3commerce_DbUtils */
	var $dbutils;
	/** @var tx_ms3commerce_search */
	var $search;
	/** @var itx_ms3commerce_shop */
	var $shop;
	/** @var tx_ms3commerce_OCI */
	var $oci;
	
	/* SEARCH OPTIONS */
	var $FullSearchFeatureId   = 0;
	var $searchNameCounter     = 0;
	var $noSelectFeatureId     = 0;
	var $emptySelectFeatureId     = 0;
	var $searchValidProductIds = array();
	var $searchByFeatureEnabled = FALSE;

	/* DEFAULT FUNCTION PARAMS */
	var $mS3CFunctionParams = array();

	/* OTHER */
	var $titleSMName = null;
	
	function __construct($plugin,$db,&$conf)
	{
		$this->conf = &$conf;
		$this->db = &$db;
		$this->plugin = &$plugin;
		
		if ( $conf['ajaxGUIVersion'] ) {
			switch ( $conf['ajaxGUIVersion'] )
			{
			case 'mootools1.4':
				$this->guiversion = tx_ms3commerce_gui_version::mootools1_4;
				break;
			case 'jquery1.6':
			default:
				$this->guiversion = tx_ms3commerce_gui_version::jquery1_6;	
			}
		} else {
			$this->guiversion = tx_ms3commerce_gui_version::jquery1_6;
		}
	}
	/**
	 * Initialize varialbles and create instances of helper classes
	 * 
	 */
	public function init() {
		$this->fefunctions=new tx_ms3commerce_FeFunctions($this);
		$this->tplutils=new tx_ms3commerce_TplUtils($this);
		$this->formbuilder=new tx_ms3commerce_FormBuilder($this);
		$this->ajaxbuilder = new tx_ms3commerce_ajaxbuilder($this, $this->conf);
		$this->smz=new tx_ms3commerce_SMZ($this);
		$this->relations=new tx_ms3commerce_relations($this);
		$this->dbutils =new tx_ms3commerce_DbUtils($this->db,$this->marketId,$this->languageId,$this); 
		$this->search=new tx_ms3commerce_search($this);	
		if(defined('MS3C_ENABLE_OCI') && MS3C_ENABLE_OCI == true){
			$this->oci = new tx_ms3commerce_OCI($this);
		}
	}
  
	/**
	 * Manage view handling methods for preconfigured views and returns HTML content to give back to CMS 
	 * also handle special frontend functions (like image scaling) 
	 * @see getDetailView(),getListViewRoot(),tx_ms3commerce_FormBuilder::getSearchViewContent()
	 * @see tx_ms3commerce_FormBuilder::getSearchResultViewContent()
	 * @see tx_ms3commerce_ajaxbuilder::getAjaxViewContent(),tx_ms3commerce_FormBuilder::getSearchCompletionQuickView()
	 * @see tx_ms3commerce_custom::getCustomView()tx_ms3commerce_FeFunctions::callACFrontendFunctions()
	 * @param itx_ms3commerce_plugin $plugin
	 * @param tx_ms3commerce_db $db
	 * @return type string (HTML content)  
	 */    
	function getTemplate(itx_ms3commerce_plugin $plugin = null, tx_ms3commerce_db $db = null)
	{
		$plugin = $this->plugin; // intellisense
		$db = $this->db;
		$view = strtolower($plugin->conf['view']);
		$this->plugin->timeTrackStart("getTemplate ($view)");
		switch ($view)
		{
		case 'detailview':
			$content = $this->getDetailView();
			$this->setPageTitle( true );
			break;
		case 'listview':
			// Sets the custom detail page identifier for the current view.
			$this->setDetailPageId();
			$content = $this->getListViewRoot();
			$this->setPageTitle( false );
			break;
		case 'searchview':
			$content = $this->formbuilder->getSearchViewContent();
			break;
		case 'searchresultsview':
			$content = $this->formbuilder->getSearchResultViewContent();
			break;
		case 'extsearchview':
			$content = $this->formbuilder->getSearchViewContent();
			break;
		case 'ajaxsearchview':
			$content = $this->ajaxbuilder->getAjaxViewContent();
			//$content = $this->getAjaxViewContent();
			break;
		
		case 'searchcompletionquickview':
			$content = $this->formbuilder->getSearchCompletionQuickView();
			break;
		
		case 'customview':
			$content=$this->custom->getCustomView();
				break;
			
		default:
			if ($this->shop->isShopView($view)) {
				$content = $this->shop->getShopView($view);
			}
		}
		
		$this->plugin->timeTrackStart("Call FE Functions");
		$content = $this->fefunctions->callACFrontendFunctions( $content );
		
		
		//remove features subparts that should not be visible
		$content=$this->tplutils->substituteSubpart($content, '###REMOVE_SUBPART####','');
		
		$this->plugin->timeTrackStop();
		$this->plugin->timeTrackStop();
		
		return $content;
	}
	/**
	 * Fetch out of the Template the result features to be retrieved in the output mask
	 * @param type $templateContentProduct
	 * @return type array
	 */
	public function getSearchResultItems($templateContent){		
		if (!empty($templateContent))
		{
			$templateContentProduct = $this->tplutils->getSubpart($templateContent, '###PRODUCT###');
			$templateContentGroup = $this->tplutils->getSubpart($templateContent, $this->getGroupSubpartName());
			$templateContentDocument = $this->tplutils->getSubpart($templateContent, '###DOCUMENT###');
			
			$returnedProductMarkers = $this->getResultMarkers($templateContentProduct);
			$returnedProductMarkers = array_merge($returnedProductMarkers, $this->getResultMarkers($templateContentGroup));
			$returnedProductMarkers = array_merge($returnedProductMarkers, $this->getResultMarkers($templateContentDocument));
			
			return $returnedProductMarkers;
		
		}	
		return array();
	}	
	/**
	 * retrive array of SM markers contained in a template subpart(PRODUCT, GROUP, DOCUMENT)
	 * to be displayed in a result set 
	 * @param type $templateContent
	 * @return type array
	 */
	public function getResultMarkers($templateContent) 
	{
		if (!empty($templateContent))
		{
			$markerArrayProduct = $this->tplutils->getMarkerArray($templateContent);
			$ProductMarkers = array();
			foreach ($markerArrayProduct as $marker)
			{
				if($this->smz->isSMZMarker($marker))
				{
					$smzContent=$this->smz->substituteSMZ($this->productGroupId, tx_ms3commerce_constants::ELEMENT_GROUP, $marker);
					$templateContent = $this->tplutils->substituteMarker($templateContent, "###$marker###", $smzContent);
				}
				
			}
			
			$markerArrayProduct = $this->tplutils->getMarkerArray($templateContent);
			foreach($markerArrayProduct as $marker)
				{
				$markerParts = $this->tplutils->getMarkerParts($marker);
				$featureId = $this->dbutils->getFeatureIdByName($markerParts['name']);
				if ($featureId > 0)
				{
					if (!in_array($markerParts['name'], $ProductMarkers) AND !empty($markerParts['name']))
					{
						$ProductMarkers[] = $markerParts['name'];
					} 
				}
			}
			return $ProductMarkers;
		}
		return array();
	}
    
	public function getResultsPaginationTemplate($result, $template, $addLinkParams = array()) {
		$templateHeader = $this->tplutils->getSubpart($template, '###HEADER###');
		$templateFooter = $this->tplutils->getSubpart($template, '###FOOTER###');
		
		if (empty($templateHeader) && empty($templateFooter)) {
			return $template;
		}
		
		$pagination = $this->fillPaginationMarkerArray($result->Total, $this->itemStart, $addLinkParams);
		$marker = array();
		if (!empty($templateHeader)) {
			$templateHeader = $this->tplutils->substituteMarkerArray(
					$templateHeader,
					$pagination
					);

			// Get any feature markers that may be used.
			$marker = $this->tplutils->getMarkerArray($templateHeader);
		}
		if (!empty($templateFooter)) {
			$templateFooter = $this->tplutils->substituteMarkerArray(
					$templateFooter,
					$pagination
					);

			// Get any feature markers that may be used.
			$markerf = $this->tplutils->getMarkerArray($templateFooter);
			$marker = array_merge($marker, $markerf);
		}
		
		
		$contentArray = $this->fillFeatureMarkerContentArray($marker);
		$headerContent = $this->tplutils->substituteMarkerArray($templateHeader, $contentArray);
		$footerContent = $this->tplutils->substituteMarkerArray($templateFooter, $contentArray);

		$template = $this->tplutils->substituteSubpart($template, '###HEADER###', $headerContent);
		$template = $this->tplutils->substituteSubpart($template, '###FOOTER###', $footerContent);
		
		return $template;
	}
	
	public function getResultsOutputTemplate($result,$template){
		$res = $this->custom->customLayoutSearchResultsTemplate($result,$template);
		if ($res != null) {
			return $res;
		}
		if ($result != null)
		{
			$templateContentProduct = $this->tplutils->getSubpart($template, '###PRODUCT###');
			if (!empty($templateContentProduct))
			{
				$markerArrayProduct = $this->tplutils->getMarkerArray($templateContentProduct);
				// Preload products
				$prodIds = array();
				foreach ($result->Product as $product)
				{
					$prodIds[] = $product["Id"];
				}
				$this->dbutils->preloadProducts($prodIds);
				
				foreach ($result->Product as $product)
				{
					$templateSearch = $templateSearch.$this->tplutils->substituteMarkerArray($templateContentProduct, $this->fillProductMarkerContentArray($markerArrayProduct, $product["Id"], $product["MenuId"]));
				}
			}
			$template = $this->tplutils->substituteSubpart($template, '###PRODUCT###', $templateSearch);
			
			$templateSearch = '';
			$templateContentDocument = $this->tplutils->getSubpart($template, '###DOCUMENT###');
			if (!empty($templateContentDocument))
			{
				$markerArrayDocument = $this->tplutils->getMarkerArray($templateContentDocument);
				
				// Preload documents
				$docIds = array();
				foreach ($result->Document as $doc)
				{
					$docIds[] = $doc["Id"];
				}
				$this->dbutils->preloadDocuments($docIds);
				
				foreach ($result->Document as $document)
				{
					$templateSearch = $templateSearch.$this->tplutils->substituteMarkerArray($templateContentDocument, $this->fillDocumentMarkerContentArray($markerArrayDocument, $document["Id"], $document["MenuId"]));
				}
			}
			$template = $this->tplutils->substituteSubpart($template, '###DOCUMENT###', $templateSearch);

			
			$templateSearch = '';
			$templateContentGroup = $this->tplutils->getSubpart($template, $this->getGroupSubpartName());
			 
			if (!empty($templateContentGroup))
			{
				$markerArrayGroup = $this->tplutils->getMarkerArray($templateContentGroup);
				
				// Preload groups
				$groupIds = array();
				foreach ($result->Group as $group)
				{
					$groupIds[] = $group["Id"];
				}
				$this->dbutils->preloadGroups($groupIds);
				
				foreach ($result->Group as $group)
				{	
		
					$subs = $this->fillGroupMarkerContentArray($markerArrayGroup, $group["Id"], $group["MenuId"]);
					$subs['###RESULT_COUNT###'] = $group["ConsolidationCount"];
					$curGroup = $this->tplutils->substituteMarkerArray($templateContentGroup, $subs);
					$templateSearch = $templateSearch.$curGroup;
				}
			}
			
			
			$template = $this->tplutils->substituteSubpart($template, $this->getGroupSubpartName(), $templateSearch);
		}
		$template = $this->fefunctions->callACFrontendFunctions($template);
		
		//from existst valueexists wrappers
		$template = $this->tplutils->substituteSubpart($template, "###REMOVE_SUBPART###", '');
		return $template;		
	}
	
	
	/**
	 *
	 * @param itx_ms3commerce_plugin $plugin
	 * @return type 
	 */
	public function getPluginRoot(itx_ms3commerce_plugin $plugin = null)
	{
		if($plugin == null)
			$plugin = $this->plugin;
		return $plugin->getPluginRoot();
	}
  
	/**
	 * Sets the details page id if defined in conf otherwise do nothing.
	 * @return void
	 */
	function setDetailPageId()
	{
		if (!array_key_exists('detail_pid_feature_name', $this->conf))
			return;

		$featureId = $this->dbutils->getFeatureIdByName($this->conf['detail_pid_feature_name']);
		if (!$featureId)
			return;

		$menuId = $this->currentMenuId;

		for (;;)
		{
			$row = $this->dbutils->selectMenu_SingleRow('`GroupId`', "`Id`=$menuId");
			if (!$row)
				break;
			$groupId = $row[0];

			$pid = intval($this->getGroupValue($groupId, $featureId));
			if ($pid > 0)
			{
				// Found a valid page id.
				$this->detailPageId = $pid;
				// Must also update linker!
				$this->plugin->linker->detailPageId = $pid;
				return;
			}

			$row = $this->dbutils->selectMenu_SingleRow('`ParentId`', "`Id`=$menuId");
			if (!$row)
				break;
			$menuId = $row[0];
		}
	}
	
	/**
	 * Calculate Item Start
	 * @param int $position
	 * @return int 
	 */  
	function calculateItemStart($position)
	{
		$pageCount = intval($position / $this->itemsPerPage);
		if (($position % $this->itemsPerPage) != 0)
			$pageCount++;
		return ($pageCount - 1) * $this->itemsPerPage + 1;
	}
 
	/**
	 * Fill-out the 'BREADCRUMB' subpart, which contains information about location.
	 * The subpart is optional.
	 * @param type $template
	 * @return type 
	 */ 
	function fillBreadcrumbSubpart($template)
	{
		$breadcrumbTemplate = $this->tplutils->getSubPart($template, '###BREADCRUMB###');
		if (!empty($breadcrumbTemplate))
		{
			$this->plugin->timeTrackStart("Breadcrumb");
			$breadcrumbTemplate = str_replace('###BREADCRUMBCONTENT###',$this->getBreadcrumbContent($this->currentMenuId),$breadcrumbTemplate);

			// Replace the feature related markers.
			$contentArray = $this->fillFeatureMarkerContentArray($this->tplutils->getMarkerArray($breadcrumbTemplate));
			$breadcrumbTemplate = $this->tplutils->substituteMarkerArray($breadcrumbTemplate, $contentArray);

			$template = $this->tplutils->substituteSubpart($template, '###BREADCRUMB###', $breadcrumbTemplate, FALSE);
			$this->plugin->timeTrackStop();
		}
		return $template;
	}

	/**
	 * 
	 * @param array $markerArray
	 * @return array 
	 */ 
	function fillFeatureMarkerContentArray($markerArray)
	{
		$contentArray = array();
		foreach ($markerArray as $marker)
		{
			$parts = $this->tplutils->getMarkerParts($marker);
			$featureId = $this->dbutils->getFeatureIdByName($parts['name']);
			if ($featureId > 0)
				$contentArray['###'.$marker.'###'] = $this->getFeatureValue($featureId, $parts['attr'], $this->languageId);
		}		
		return $contentArray;
	}

	/**
	 * Fills an array of group markers.
	 * @param array $markerArray Array of marker strings
	 * @param int $productGroupId
	 * @return array 
	 */
	function fillGroupMarkerContentArray($markerArray, $productGroupId = NULL, $parentMenuId = NULL )
	{
		$this->plugin->timeTrackStart("fillGroupMarkerContentArray");
		if (is_null($productGroupId))
			$productGroupId = $this->productGroupId;
		if (is_null($parentMenuId))
			$parentMenuId = $this->dbutils->getGroupMenuId($productGroupId);

		$contentArray = array();
		foreach ($markerArray as $marker)
		{	
			if($marker == 'REMOVE_SUBPART'){
			continue;
			}
			
			if($this->tplutils->isIncludeMarker($marker)) {
				$contentArray['###'.$marker.'###'] = $this->getListView($productGroupId,$parentMenuId,$this->getIncludeMarkerContent($marker, $productGroupId));
				continue;
			}
			if ($this->smz->isSMZMarker($marker)) {
				$smzcontent = $this->smz->substituteSMZ($productGroupId, tx_ms3commerce_constants::ELEMENT_GROUP, $marker);
				
				$markerValues = $this->fillGroupMarkerContentArray($this->tplutils->getMarkerArray($smzcontent), $productGroupId, $parentMenuId);
				$content = $this->tplutils->substituteMarkerArray($smzcontent, $markerValues);
				$contentArray['###'.$marker.'###'] = $content;
				continue;
			}
			if($this->relations->isRelationMarker($marker)){
				$relcontent = $this->relations->substituteRelationMarker($productGroupId,$marker, 'Group');
				$contentArray['###'.$marker.'###'] = $relcontent;
				continue;
			}
			if ($this->tplutils->isCustomIncudeMarker($marker)) {
				$custContent = $this->custom->getCustomInclude($marker, GROUP_CONTEXT, $productGroupId, $parentMenuId);
				$contentArray['###'.$marker.'###'] = $custContent;
				continue;
			}
			
			
			switch ($marker)
			{
			// The following markers are related directly with the group
			case 'GROUP_NAME':
				$contentArray['###'.$marker.'###'] = $this->dbutils->getGroupName($productGroupId);
				break;
			case 'GROUP_UID':
				$contentArray['###'.$marker.'###'] = $productGroupId;
				break;
			case 'GROUP_AUXNAME':
				$contentArray['###'.$marker.'###'] = $this->dbutils->getGroupAuxName($productGroupId);
				break;
			case 'GROUP_LINK':
				$contentArray['###'.$marker.'###'] = $this->plugin->getGroupLink($productGroupId);
				break;
			case 'GROUP_NAMEID':
				$contentArray['###'.$marker.'###'] = preg_replace('#[^\w\d-]#u', '_', $this->dbutils->getGroupName($productGroupId));
				break;
			case 'GROUP_PARENTUID':
				$row = $this->dbutils->selectMenu_SingleRow('ParentId', 'Id = ' . $parentMenuId);
				$row = $this->dbutils->selectMenu_SingleRow('GroupId', 'Id = ' . $row[0]);
				$contentArray['###'.$marker.'###'] = $row[0];
				break;
			case 'GROUP_PARENTLINK':
				$row = $this->dbutils->selectMenu_SingleRow('ParentId', 'Id = ' . $parentMenuId);
				$row = $this->dbutils->selectMenu_SingleRow('GroupId', 'Id = ' . $row[0]);
				$contentArray['###'.$marker.'###'] = $this->plugin->getGroupLink($row[0]);
				break;
			
			
			// The following markers are filled using the (group, feature) combination.
			default:
				$parts = $this->tplutils->getMarkerParts($marker);
				if ($parts === false) {
					break;
				}
				$featureId = $this->dbutils->getFeatureIdByName($parts['name']);
				
 				$featureVisibility=$this->tplutils->checkFeatureVisibility($featureId);
			
				$featureValue = $this->dbutils->getGroupValue($productGroupId,$featureId);
								
				switch (strtolower($parts['attr']))
				{
				case 'value':
					$contentArray['###'.$marker.'###'] =$this->getGroupValue($productGroupId, $featureId);
					break;
				case 'rawvalue':
					$contentArray['###'.$marker.'###'] =$this->getGroupValue($productGroupId, $featureId, true);
					break;
				case 'name':
					$contentArray['###'.$marker.'###'] = $parts['name'];
					break;
				case 'empty':
					$contentArray['###'.$marker.'###'] = strlen(trim($this->getGroupValue($productGroupId, $featureId,true))) > 0
						? 'mS3Commerce_isnotempty' : 'mS3Commerce_isempty';
					break;
				case 'valueexists':
					if($featureVisibility && $featureValue != ''){
					//if visible and ist not empty! remove the Marker ###REMOVE_SUBPART###
					$contentArray['###'.$marker.'###']='';
					}else{
					// ###REMOVE_SUBPART### marker will be handled later at the end of template rendering so leave it now 
						$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART### ";
					}
					break; 
				case 'exists':
					if($featureVisibility){
					//if visible remove the Marker ###REMOVE_SUBPART###
					$contentArray['###'.$marker.'###']='';
					}else{
					// ###REMOVE_SUBPART### marker will be handled later at the end of template rendering so leave it now 
						$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART###";
					}
					break;
				case 'documentexists':
					if (!$featureVisibility) {
						$docCount = 0;
					} else {
						$Documents = $this->dbutils->getAllFeatureDocs($featureId, 'gv', $productGroupId);
						$Documents = $this->tplutils->filterDocumentVisibilityList( $Documents );
						$docCount = count($Documents);
					}
					if($docCount > 0) {
						$contentArray['###'.$marker.'###'] = '';
					} else {
						$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART###";
					}
					break;
				case 'document':
					$contentArray['###'.$marker.'###'] = $this->getDocumentView($productGroupId, $featureId, 'gv', $parentMenuId);
					break;
					
				default:
					//No featurevalue no featuremarker
					if(!$featureVisibility ){			
						$contentArray['###'.$marker.'###'] = '';
						break;
					}
					
					if ( preg_match('/(\w+)(?:'.PLACEMENT_PARAMETER_REGEXP_PART.')/i', $parts['attr'], $matches) ) {
						// SM Function call
						$func = $matches[1];
						$params = array_key_exists(2, $matches) ? $matches[2] : '';
						$contentArray['###'.$marker.'###'] = $this->fefunctions->handleSMFunctionCall($func, $params, $productGroupId, $parentMenuId, $featureId, 'group');
					} else if (strpos(strtolower($parts['attr']), 'document:') === 0) {
						// Specific document view
						$viewName = substr($parts['attr'], strlen('document:'));
						$contentArray['###'.$marker.'###'] = $this->getDocumentView($productGroupId, $featureId, 'gv', $parentMenuId, $viewName);
					}
					else {
						$contentArray['###'.$marker.'###'] = $this->getFeatureValue($featureId, $parts['attr'], $this->languageId);
					}
					break;
				}
			}
		}
			
		$this->plugin->timeTrackStop();
		
		
		return $contentArray;
	}
	
	function getGroupSubpartName()
	{
		// Legacy mode: use PRODUCTGROUP
		// Else: use GROUP
		if (array_key_exists('legacyMode', $this->conf)) {
			if ($this->conf['legacyMode'] == '2.5') {
				return '###PRODUCTGROUP###';
			}
		}
		
		return '###GROUP###';
	}
	
	function getListDocumentContentSubpartName()
	{
		// Legacy mode: use DOCUMENTS
		// Else: use DOCUMENT
		if (array_key_exists('legacyMode', $this->conf)) {
			if ($this->conf['legacyMode'] == '2.5') {
				return '###DOCUMENTS###';
			}
		}
		
		return '###DOCUMENT###';
	}
	
	/**
	 * 
	 * @param string $headerTemplate
	 * @param type $itemCount
	 * @return type 
	 */
	function fillHeaderContent($headerTemplate, $itemCount)
	{
		$content = '';
		if (!empty($headerTemplate))
		{
			$content = $this->tplutils->substituteMarkerArray(
				$headerTemplate,
				$this->fillPaginationMarkerArray($itemCount, $this->itemStart));

			// Now replace any features that may be used.
			$contentArray = $this->fillFeatureMarkerContentArray($this->tplutils->getMarkerArray($content));
			$content = $this->tplutils->substituteMarkerArray($content, $contentArray);
		}
		return $content;
	}
	 
	/**
	 * 
	 * @param int $currentMenuId
	 * @return string 
	 */  
	function getBreadcrumbContent($currentMenuId)
	{
		// selects the ancestors and makes a nice navigation
		$content = '';
		
		$startId = $this->rootMenuId;
		$skipCount = 0;
		$preItem = $postItem = "";
		$preFix = $this->breadCrumbConf['preFix'];
		$postFix = $this->breadCrumbConf['postFix'];
		$connector = $this->breadCrumbConf['connector'];
		$pids =	 $this->breadCrumbConf['pids.'];	
		
		
		if ($this->breadCrumbConf['startId']) {
			$startId = $this->breadCrumbConf['startId'];
		}
		if ($this->breadCrumbConf['skipCount']) {
			$skipCount = $this->breadCrumbConf['skipCount'];
		}
		if ($this->breadCrumbConf['itemWrap']) {
			list($preItem, $postItem) = explode('$', $this->breadCrumbConf['itemWrap']);
		}
		
	
		
		if ( $startId == $currentMenuId ) {
			if ($this->breadCrumbConf['allowEmpty']) {
				return '';
			} else {
				$ancestorArray = array();
			}
		} else {
			// If the menu tree has a root item, we may need to exclude some items in
			// the bread crumb.
			$excludePath = '';
			if ($startId > 0)
			{
				$where = "`Id` = $startId";
				$row = $this->dbutils->selectMenu_SingleRow('`Path`', $where);
				if ($row)
				{
					$excludePath = $row[0];
					if (strrchr($excludePath, '/') !== (strlen($excludePath) - 1))
						$excludePath .= '/';
					$excludePath .= strval($startId);
				}
			}

			// Gets the ancestors of the current menu item, they will be part of the
			// breadcrumb navigation.
			$where = "`Id` = $currentMenuId";
			
			$row = $this->dbutils->selectMenu_SingleRow('`Path`', $where);
			if (!$row)
				return $content;
			$path = $row[0];
			unset($row);

			if (strpos($path, $excludePath) === 0)
				$path = substr($path, strlen($excludePath));
			$ancestorArray = explode('/', $path);

			// Remove empty entry at beginning
			array_shift($ancestorArray);
		}
		
		for ($i = 0; $i < $skipCount; $i++) {
			array_shift($ancestorArray);
		}
		$ancestorArray[] = $currentMenuId;

		
		// For each ancestor get the text and the link.
		$count=0;
		foreach ($ancestorArray as $ancestor)
		{	$count++;
			if (empty($ancestor))
				continue;
			$row = $this->dbutils->selectMenu_SingleRow('`GroupId`',
				sprintf('`Id` = %d AND `GroupId` IS NOT NULL', $ancestor));
			if (!$row)
				continue;
			$groupId = $row[0];
			$pid=$this->listPageId;
			if($pids){
				$levelrange=$this->dbutils->getMenuLevels($ancestor, $groupId);
				$level=$levelrange['level'];
				$maxLevel=$levelrange['maxLevel'];
				$lbDepht=$maxLevel-$level;	
				
				if (array_key_exists("LB$lbDepht", $pids)) {
					$pid = $pids["LB$lbDepht"];
				} else if (array_key_exists("L$level", $pids)) {
					$pid = $pids["L$level"];
				}
			}
			if (!empty($content)) {
				$content .= $connector;
			}
			
			$aname = $this->getMenuTitleForGroup($groupId);
			if ($ancestor != $currentMenuId)
			{	
				$ahref = $this->plugin->getGroupLink($groupId, $ancestor, $pid);
				$content .= $preItem.sprintf('<a href=\'%s\'>%s</a>', $ahref, $aname).$postItem;
			}
			else
				$content .= $preItem.$aname.$postItem;
		}
		if (!empty($content)) {
			$content = $preFix.$content.$postFix;
		}
		return $content;
	}

	/**
	 * This is a recursive function.
	 * TEMPLATE EXAMPLE --> TRANSFORMATION RESULT
	 * @param int $groupId 
	 * @param string  $templateListView
	 * @return string 
	 */
	function getListView($groupId,$parentMenuId,$templateListView)
	{
		// This method expects a template file (usually having the TMPL extension).
		// We can load different templates, depending on the current group's level.
		// The basic name is '###LISTVIEW###' and '###LISTVIEW_L??###' for 
		// level-dependent templates.
		
		$this->plugin->timeTrackStart("getListView");
		$content = '';
		if ($this->debug_enabled)
		{
			$content .= 'TemplateFile: '.$this->conf['templateFile'];
			$content .= "<br>GroupId: $groupId";
		}

		if (empty($templateListView)) {
			$this->plugin->timeTrackStop();
			return $content;
		}
		
		//check if group is visible
		if($this->tplutils->checkGroupVisibility($groupId)==false) {
			$this->plugin->timeTrackStop();
			return $content;
		}
		
		$this->plugin->timeTrackStart("This Group");
		// Fill-out the 'BREADCRUMB' subpart.
		$templateListView = $this->fillBreadcrumbSubpart($templateListView);

		// Fill-out the 'SELF' subpart, this contains information about the group self.
		$selfTemplate = $this->tplutils->getSubpart($templateListView, '###SELF###');
		if (!empty($selfTemplate))
		{
			$selfMarker = $this->tplutils->getMarkerArray($selfTemplate);
			$selfContent = $this->tplutils->substituteMarkerArray(
				$selfTemplate,
				$this->fillGroupMarkerContentArray($selfMarker, $groupId, $parentMenuId));
			$templateListView = $this->tplutils->substituteSubpart($templateListView, '###SELF###', $selfContent, FALSE);
		}

		// Fill-out the 'PARENT' subpart, which contains information about the parent
		// group. The subpart is optional.
		$parentTemplate = $this->tplutils->getSubpart($templateListView, '###PARENT###');
		if (!empty($parentTemplate))
		{
			$parentGroupId = $this->dbutils->getParentGroupIdByMenu($parentMenuId);
			$parentMarker = $this->tplutils->getMarkerArray($parentTemplate);
			$parentContent = $this->tplutils->substituteMarkerArray(
					$parentTemplate, 
					$this->fillGroupMarkerContentArray($parentMarker, $parentGroupId));
			$templateListView = $this->tplutils->substituteSubpart($templateListView, '###PARENT###', $parentContent, FALSE);
		}
		
		$this->plugin->timeTrackStart("Get children");
		
		// Work on CONTENT Subpart
		$templateContent = $this->tplutils->getSubpart($templateListView, '###CONTENT###');
		
		// Determines the number of products that the current group has. If the
		// current template does not have a 'PRODUCT' subpart, then the number
		// of products should remain zero - then we get the correct header info.
		$productCount = 0;
		$templateProduct = $this->tplutils->getSubpart($templateContent, '###PRODUCT###');
		if (!empty($templateProduct)) {
			$allprods=$this->dbutils->getChildProducts($groupId,'', $parentMenuId);
			array_walk($allprods, "getSubIndex", 0);
			$this->dbutils->preloadProducts($allprods);
			$visibleProdArray=$this->tplutils->filterProductVisibilityList($allprods);
			$this->dbutils->preloadProductMenus($visibleProdArray,$groupId);
			$productCount=count($visibleProdArray);
		}
		
		$groupCount = 0;
		$templateProductGroup = $this->tplutils->getSubpart($templateContent, $this->getGroupSubpartName());
		if (!empty($templateProductGroup)) {
			$allgrups=$this->dbutils->getChildGroups($groupId, '', $parentMenuId);
			$this->dbutils->preloadGroups($allgrups);
			$visibleGroupArray=$this->tplutils->filterGroupVisibilityList($allgrups);
			$this->dbutils->preloadGroupMenus($visibleGroupArray, $parentMenuId);
			$groupCount=count($visibleGroupArray);
		}
		
		$documentCount = 0;
		$templateDocument = $this->tplutils->getSubpart($templateContent, $this->getListDocumentContentSubpartName());
		if (!empty($templateDocument)) {
			$alldocs=$this->dbutils->getChildDocument($groupId, '', $parentMenuId);
			array_walk($alldocs, "getSubIndex", 0);
			$this->dbutils->preloadDocuments($alldocs);
			$visibleDocArray=$this->tplutils->filterDocumentVisibilityList($alldocs);
			$this->dbutils->preloadDocMenus($visibleDocArray,$groupId);
			$documentCount = count($visibleDocArray);
		}
		$this->plugin->timeTrackStop();

		// Fill-out the 'HEADER' subpart.
		$headerSubpart = $this->tplutils->getSubpart($templateListView, '###HEADER###');
		if (!empty($headerSubpart))
		{
			$headerSubpart = $this->fillHeaderContent(
				$headerSubpart,
				$groupCount + $productCount + $documentCount);
			$templateListView = $this->tplutils->substituteSubpart($templateListView, '###HEADER###', $headerSubpart, FALSE);
		}
		else
			$this->itemsPerPage = 0;

		$this->plugin->timeTrackStop();
		$this->plugin->timeTrackStart("Content");
		
		// Fill-out the 'CONTENT' subpart.
		$contentContent = '';
		
		$groupContent = '';	
		if ($groupCount > 0)
		{
			$this->plugin->timeTrackStart("Groups");
			$continue = TRUE;
			if (($this->itemsPerPage > 0) && ($this->itemStart > $groupCount))
				$continue = FALSE;

			if ($continue)
			{
				$limit = '';
				if ($this->itemsPerPage > 0)
					//$limit = sprintf('%d,%d', $this->itemStart - 1, $this->itemsPerPage);
					$visibleGroupArray=array_slice($visibleGroupArray,$this->itemStart - 1,$this->itemsPerPage);
				
				$markerArray = $this->tplutils->getMarkerArray($templateProductGroup);
				
				$first = TRUE;
				$last = FALSE;
				$i = 1;
				
				foreach ($visibleGroupArray as $subGroupId)
				{
					$this->plugin->timeTrackStart("Handle Group");
					if ( $i == count($visibleGroupArray) )
						$last = TRUE;
					$subMenuId = $this->dbutils->getGroupMenuId($subGroupId, $parentMenuId);

					$markerValueArray = $this->fillGroupMarkerContentArray($markerArray, $subGroupId, $subMenuId);
					$markerValueArray['###GROUP_UID###'] = $subGroupId;
					// GroupLink might be time consuming
					if (array_search('GROUP_LINK', $markerArray) !== false)
						$markerValueArray['###GROUP_LINK###'] = $this->plugin->getGroupLink($subGroupId, $subMenuId);
					$markerValueArray['###IS_FIRST###'] = ($first) ? 'first' : '';
					$markerValueArray['###IS_LAST###'] = ($last) ? 'last' : '';
					$markerValueArray['###GROUP_COUNTER###'] = $i;
					
					$groupContent .= $this->tplutils->substituteMarkerArray($templateProductGroup, $markerValueArray);
					
					$first = FALSE;
					$i++;
					$this->plugin->timeTrackStop();
				}
				unset($markerArray);
			}
			$this->plugin->timeTrackStop();
		}

		$prodContent = '';
		if ($productCount > 0)
		{
			$this->plugin->timeTrackStart("Products");
			if ($this->itemsPerPage > 0)
			{
				$limitStart = $this->itemStart - $groupCount - 1;
				$visibleProdArray=array_slice($visibleProdArray,$this->itemStart - 1,$this->itemsPerPage);
			}
			
			//$productArray = $this->dbutils->getChildProducts ($groupId, $limit);
			$markerArray = $this->tplutils->getMarkerArray($templateProduct);

			foreach ($visibleProdArray as $productId) 
			{
				$menuId=$this->dbutils->getMenuIdByProdAndGroup($productId, $groupId);
				$markerValueArray = $this->fillProductMarkerContentArray($markerArray, $productId, $menuId, $groupId);
				$prodContent .= $this->tplutils->substituteMarkerArray($templateProduct, $markerValueArray);
			}
			unset($markerArray);
			$this->plugin->timeTrackStop();
		}
		
		$docContent = '';
		if ($documentCount > 0)
		{
			$this->plugin->timeTrackStart("Documents");
			if ($this->itemsPerPage > 0)
			{
				$limitStart = $this->itemStart - $groupCount - $productCount - 1;
				$visibleDocArray=array_slice($visibleDocArray,$this->itemStart - 1,$this->itemsPerPage);
			}
			
			$markerArray = $this->tplutils->getMarkerArray($templateDocument);
			//$Documents[] = 1000001;
			foreach ($visibleDocArray as $documentId)
			{
				$menuId=$this->dbutils->getMenuIdByDocAndGroup($docId, $groupId);
				$markerValues = $this->fillDocumentMarkerContentArray($markerArray, $documentId,$menuId);
				$docContent .= $this->tplutils->substituteMarkerArray($templateDocument, $markerValues);
			}
			$this->plugin->timeTrackStop();
		}
		
		$contentContent = $this->tplutils->substituteSubpart($templateContent, $this->getGroupSubpartName(), $groupContent);
		$contentContent = $this->tplutils->substituteSubpart($contentContent, '###PRODUCT###', $prodContent);
		$contentContent = $this->tplutils->substituteSubpart($contentContent, $this->getListDocumentContentSubpartName(), $docContent);
		
		
		$content = $this->tplutils->substituteSubpart($templateListView, '###CONTENT###', $contentContent);
		$this->plugin->timeTrackStop();
		
		// Counter marker
		$contentArray = array(
			'###GROUP_COUNT###' => $groupCount,
			'###PRODUCT_COUNT###' => $productCount,
			'###DOCUMENT_COUNT###' => $documentCount
		);
		$content = $this->tplutils->substituteMarkerArray($content, $contentArray);
		
		$markerArray = $this->tplutils->getMarkerArray($content);
		$contentArray=array();
		foreach($markerArray as $marker){
			if($this->tplutils->isIncludeMarker($marker)) {
				$contentArray['###'.$marker.'###'] = $this->getListView ($groupId,$parentMenuId,$this->getIncludeMarkerContent($marker));
			}
		}
		if(!empty($contentArray))
			$content = $this->tplutils->substituteMarkerArray($content, $contentArray);

		$this->plugin->timeTrackStop();
		
		//from existst valueexists wrappers
		$content = $this->tplutils->substituteSubpart($content, "###REMOVE_SUBPART###", '');
		return $content;
	}
	
	/**
	 * This is the start point, non-recursive
	 */
	function getListViewRoot()
	{
		return $this->getListView($this->productGroupId,$this->currentMenuId,$this->getListViewTemplate());
	}
	
	/**
	 * Gets the HTML template used by the LIST view. The LIST view supports 
	 * group level-dependent templates.
	 * @return string containing the HTML template, or an empty string if the template
	 *         was not found.
	 */
	function getListViewTemplate(itx_ms3commerce_plugin $plugin = null)
	{
		//check if customer template exist
		$templateName = $this->custom->getListviewTemplateName($this->currentMenuId);
		
		if($templateName == null){
		
			// Gets the default template for the list view, optionally using the current level
			// to load a different template. The maximum depth of the hierarchy is also 
			// used to load the templates from the back.
			
			$levels=$this->dbutils->getMenuLevels($this->currentMenuId, $this->productGroupId);
			$level=$levels['level'];
			$maxLevel=$levels['maxLevel'];
			
			if ($maxLevel === null) 
				$maxLevel = $level;

			for ($i = $level; $i >= 0; $i--)
			{
				if ($i == 0)
				{
					// the default template will be used
					$templateName = '###LISTVIEW##';
					break;
				}

				// check for a back template
				$templateName = sprintf('###LISTVIEW_LB%d###', $maxLevel - $i);
				$tmpl = $this->plugin->getTemplate($templateName);
				if (!empty($tmpl))
					break;

				// check for a front template
				$templateName = "###LISTVIEW_L$i##";
				$tmpl = $this->plugin->getTemplate($templateName);
				if (!empty($tmpl))
					break;
			}
		}
		
		$template = $this->plugin->getTemplate($templateName);
		return $template;
	}
			
	 
	/**
	 *
	 * @param type $menuItemArray
	 * @return type 
	 */
	function fillMenuTitles(&$menuItemArray, $add = array())
	{
		if (!is_array($menuItemArray) || !array_key_exists('_SUB_MENU', $menuItemArray))
			return;

		foreach ($menuItemArray['_SUB_MENU'] as &$menuItem)
		{
			// Add additional menu item parameters
			foreach ($add as $k => $v) {
				if ( array_key_exists($k, $menuItem) ) {
					continue;
				}
				if ($k === 'uid' && $v === -1) {
					// Why? From old MENNEKES code
					$v = $menuItem['id'] + 9000;
				}
				$menuItem[$k] = $v;
			}
			
			// Set group title
			if (array_key_exists('groupId', $menuItem))
			{
				$groupId = $menuItem['groupId'];
				if ($groupId > 0)
				{
					$href = null;
					// If this group as a page id, and does not need additional
					// parameters (groupid, menuid, ...), make naked page link!
					if ($this->pageRoleFeatureId > 0 && !$this->pageLinkAddParameters) {
						$pid = intval($this->getGroupValue($groupId, $this->pageRoleFeatureId, true));
						if ($pid) {
							$href = $this->plugin->getPageLink($pid);
						}
					}
					if ($href == null) {
						// getGroupLink will handle pid (also page role feature)
						$href = $this->plugin->getGroupLink($groupId, $menuItem['id']);
					}
					
					$menuItem['title'] = $this->getMenuTitleForGroup($groupId, true);

					$menuItem['_OVERRIDE_HREF'] = $href;
				}
			}
			
			// Set product title
			if (array_key_exists('productId', $menuItem))
			{
				$productId = $menuItem['productId'];
				if ($productId > 0)
				{
					$menuItem['title'] = $this->getMenuTitleForProduct($productId, true);
					$menuItem['_OVERRIDE_HREF'] = 
						$this->plugin->getProductLink( $productId, $menuItem['id'], $this->detailPageId );
				}
			}
			
			// Set document title
			if (array_key_exists('documentId', $menuItem))
			{
				$documentId = $menuItem['documentId'];
				if ($documentId > 0)
				{
					$menuItem['title'] = $this->getMenuTitleForDocument($documentId, true);
					$menuItem['_OVERRIDE_HREF'] = 
						$this->plugin->getDocumentLink($documentId, $menuItem['id']);
				}
			}

			if (array_key_exists('_SUB_MENU', $menuItem))
				$this->fillMenuTitles($menuItem, $add);
		}
		unset($menuItem);
	}
	
	function makeMenuHierarchicalSearch($curItemState, $parentItemState, $additionalItems) {
		return $this->formbuilder->makeMenuHierarchicalSearch($curItemState, $parentItemState, $additionalItems);
	}
	

	/**
	 * Retrieves the value of a group associated with the specified feature. The current
	 * language is used. The default value is an empty string.
	 * @param type $groupId
	 * @param type $featureId
	 * @param type $raw
	 * @return type string
	 */
	function getGroupValue($groupId, $featureId, $raw = false)
	{
		$value = $this->dbutils->getGroupValue($groupId, $featureId, $raw);
		// The value may contain markers which should be replaced.
		if ($value)
			$value = $this->replaceGroupValueMarkers($value);
		return $value;
	}
  
	/**
	 * Retrieves the title of a menu item associated with the specified product.
	 * @param int $productId ID of product.
	 * @return string title or name (if title does not exist.)
	 */  
	function getMenuTitleForProduct($productId, $raw = false)
	{
		// Get the title of the menu item using the feature for display role. This
		// allows language-dependent titles.
		
		$title = '';
		if ($this->menuDisplayRoleFeatureId > 0)
			$title = $this->dbutils->getProductValue($productId, $this->menuDisplayRoleFeatureId, $raw);
		if (strlen($title) > 0)
			return $title;
		return $this->dbutils->getProductName($productId);
	}
	
	/**
	 * Retrieves the title of a menu item associated with the specified document.
	 * @param int $documentId ID of document.
	 * @return string title or name (if title does not exist.)
	 */  
	function getMenuTitleForDocument($documentId, $raw = false)
	{
		// Get the title of the menu item using the feature for display role. This
		// allows language-dependent titles.
		
		$title = '';
		if ($this->menuDisplayRoleFeatureId > 0)
			$title = $this->dbutils->getDocumentValue($documentId, $this->menuDisplayRoleFeatureId, $raw);
		if (strlen($title) > 0)
			return $title;
		return $this->dbutils->getDocumentName($documentId);
	}
	
	/**
	 * Fill pagination marker array.
	 * @param int $count
	 * @param int $start
	 * @return array 
	 */  
	function fillPaginationMarkerArray($count, $start, $addPageParams = array())
	{
		$markerArray = array();
		$startOfLastPage = $this->calculateItemStart($count); 
		$thisPid = $GLOBALS['TSFE']->id;

		$markerArray['###PAGE_ITEMCOUNT###'] = $count;
		$markerArray['###PAGE_ITEMBEGIN###'] = ($count > 0) ? $start : $count;
		$markerArray['###PAGE_ITEMEND###'] = ($this->itemsPerPage > 0)
			? (($count > 0) ? min($start + $this->itemsPerPage - 1, $count) : $count)
			: $count;

		$nrFirst = 1;
		$nrLast = ($this->itemsPerPage > 0) ? $startOfLastPage : 1;
		$nrPrev = ($this->itemsPerPage > 0) ? max($start - $this->itemsPerPage, 1) : 1;
		$nrNext = ($this->itemsPerPage > 0) ? min($start + $this->itemsPerPage, $startOfLastPage) : 1;
		
		if ($this->productGroupId) {
			$markerArray['###PAGE_LINKFIRST###'] = $this->plugin->getGroupLink($this->productGroupId, 0, $thisPid, $nrFirst);
			$markerArray['###PAGE_LINKLAST###'] = $this->plugin->getGroupLink($this->productGroupId, 0, $thisPid, $nrLast);
			$markerArray['###PAGE_LINKPREVIOUS###'] = $this->plugin->getGroupLink($this->productGroupId, 0, $thisPid, $nrPrev);
			$markerArray['###PAGE_LINKNEXT###'] = $this->plugin->getGroupLink($this->productGroupId, 0, $thisPid, $nrNext);
		} else {
			// Search Result Pagination. Add additional parameters!
			// Shortcuts:
			$a = is_array($addPageParams) ? $addPageParams : array();
			$k = tx_ms3commerce_constants::QUERY_PARAM_ITEMSTART;
			
			$markerArray['###PAGE_LINKFIRST###'] = $this->plugin->getPageLink($thisPid, array_merge($a, array($k=>$nrFirst)));
			$markerArray['###PAGE_LINKLAST###'] = $this->plugin->getPageLink($thisPid, array_merge($a, array($k=>$nrLast)));
			$markerArray['###PAGE_LINKPREVIOUS###'] = $this->plugin->getPageLink($thisPid, array_merge($a, array($k=>$nrPrev)));
			$markerArray['###PAGE_LINKNEXT###'] = $this->plugin->getPageLink($thisPid, array_merge($a, array($k=>$nrNext)));
		}
		
		return $markerArray;
	}
	
	/**
	 * Fill product content array.
	 * @param array $markerArray
	 * @param int $productId
	 * @param int $menuId
	 * @return array
	 */
	function fillProductMarkerContentArray($markerArray, $productId = 0, $menuId = 0, $groupId = 0,tx_ms3commerce_db $db = null)
	{
		$this->plugin->timeTrackStart("fillProductMarkerContentArray");
		global $TSFE;
		$db = $this->db;	// Für intellisense
		
		if ($productId == 0)
			$productId = $this->productId;
		
		if($menuId!=0 && $groupId==0 )
		{
			$groupId= $this->dbutils->getParentGroupIdByMenu($menuId);
		} else if($groupId!=0 && $menuId == 0)
		{
			$menuId=$this->dbutils->getMenuIdByProdAndGroup($productId,$groupId);				
		} else if ($groupId==0 && $menuId==0) 
		{
			//no groupId neither menuId
			$menuId = $this->dbutils->getMenuIdByElementId($productId,'2');
			$groupId= $this->dbutils->getParentGroupIdByMenu($menuId);
		}				
		
		
		$contentArray = array();
		foreach ($markerArray as $marker)
		{	
			if($marker == 'REMOVE_SUBPART'){
				continue;
			}
			
			if ($this->smz->isSMZMarker($marker)) {
				$smzcontent = $this->smz->substituteSMZ($productId, tx_ms3commerce_constants::ELEMENT_PRODUCT, $marker);
				// Apply on resolved markers
				$markerValues = $this->fillProductMarkerContentArray($this->tplutils->getMarkerArray($smzcontent), $productId, $menuId, $groupId);
				$content = $this->tplutils->substituteMarkerArray($smzcontent, $markerValues);
				$contentArray['###'.$marker.'###'] = $content;
				
				continue;
			}
			
			if ($this->shop->isShopMarker($marker))
			{
				$contentArray['###'.$marker.'###'] = $this->shop->fillShopMarkerContent($marker, $productId);
				continue;
			}
			
			if($this->relations->isRelationMarker($marker)){
				$relcontent = $this->relations->substituteRelationMarker($productId,$marker, 'Product');
				$contentArray['###'.$marker.'###'] = $relcontent;
				continue;
			}
			
			if ($this->tplutils->isIncludeMarker($marker)) {
				$tpl = $this->getIncludeMarkerContent($marker, $groupId);
				if ($tpl) {
					$incMarker = $this->tplutils->getMarkerArray($tpl);
					$subs = $this->fillProductMarkerContentArray($incMarker, $productId, $menuId, $groupId);
					$tpl = $this->tplutils->substituteMarkerArray($tpl, $subs);
				} else {
					$tpl = '';
				}
				$contentArray["###$marker###"] = $tpl;
				continue;
			}
			
			if ($this->tplutils->isCustomIncudeMarker($marker)) {
				$custContent = $this->custom->getCustomInclude($marker, PRODUCT_CONTEXT, $productId, $menuId);
				$contentArray['###'.$marker.'###'] = $custContent;
				continue;
			}
			
			switch ($marker)
			{
			// The following markers are directly related to product.
			case 'PRODUCT_NAME':
				$contentArray['###'.$marker.'###'] = $this->dbutils->getProductName($productId);
				break;
			case 'PRODUCT_AUXNAME':
				$contentArray['###'.$marker.'###'] = $this->dbutils->getProductAuxName($productId);
				break;
			case 'PRODUCT_UID':
				$contentArray['###'.$marker.'###'] = $productId;
				break;
			case 'PRODUCT_LINK':
				$contentArray['###'.$marker.'###'] = $this->plugin->getProductLink($productId, $menuId);
				break;	
			case 'PRODUCT_NAMEID':
				$contentArray['###'.$marker.'###'] = preg_replace('#[^\w\d-]#u', '_', $this->dbutils->getProductName($productId));
				break;			
			// The following markers are filled using the (product, feature) combination.
			default:
				
				
				$parts = $this->tplutils->getMarkerParts($marker);
				if ($parts === false) {
					break;
				}
				$featureId = $this->dbutils->getFeatureIdByName($parts['name']);			
				$featureVisibility=$this->tplutils->checkFeatureVisibility($featureId);	
				$featureValue = $this->dbutils->getProductValue($productId, $featureId);
				
				
				/*DEPRECATED
				if ($featureId == 0)
				{
					// For not found features we will set the empty flag - since there is
					// currently no need to distinguish between missing/empty.
					if (strtolower($parts['attr']) == 'empty')
						$contentArray['###'.$marker.'###'] = 'mS3Commerce_isempty';
					break;
				}
				
				//check if feature is visible for this User
				$featureVisibility=$this->tplutils->checkFeatureVisibility($featureId);
				if($featureVisibility){
					
				}else{
					break;
				}*/
				switch (strtolower($parts['attr']))
				{
				case 'value':
					$contentArray['###'.$marker.'###'] = $this->dbutils->getProductValue($productId, $featureId);
					break;
				case 'rawvalue':
					$contentArray['###'.$marker.'###'] = $this->dbutils->getProductValue($productId, $featureId,true);
					break;
				case 'name':
					$contentArray['###'.$marker.'###'] = $parts['name'];
					break;
				case 'empty':
					$contentArray['###'.$marker.'###'] = strlen(trim($this->dbutils->getProductValue($productId, $featureId,true))) > 0
						? 'mS3Commerce_isnotempty' : 'mS3Commerce_isempty';
					break;
				
				// TODO: REMOVE?
				case 'text':
				    $tmp_value = $this->dbutils->getProductValue($productId, $featureId);
				    if (substr($tmp_value,0,4) == "<img") {
						$contentArray['###'.$marker.'###'] = "/typo3conf/ext/ms3commerce/pi1/image.php?bild=".substr($tmp_value,strpos($tmp_value,"src=")+5,28);
					} else {
						$contentArray['###'.$marker.'###'] = $tmp_value;
					}
					break;
				case 'valueexists':
					if($featureVisibility && $featureValue != ''){
					//if visible and ist not empty! remove the Marker ###REMOVE_SUBPART###
					$contentArray['###'.$marker.'###']='';
					}else{
					// ###REMOVE_SUBPART### marker will be handled later at the end of template rendering so leave it now 
						$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART### ";
					}
					break; 
				case 'exists':
					if($featureVisibility){
					//if visible remove the Marker ###REMOVE_SUBPART###
						$contentArray['###'.$marker.'###'] = '';
					}else{
					// ###REMOVE_SUBPART### marker will be handled later at the end of template rendering so leave it now 
						$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART###";
					}
					break;
				
				case 'documentexists':
					if (!$featureVisibility) {
						$docCount = 0;
					} else {
						$Documents = $this->dbutils->getAllFeatureDocs($featureId, 'pv', $productId);
						$Documents = $this->tplutils->filterDocumentVisibilityList( $Documents );
						$docCount = count($Documents);
					}
					if($docCount > 0) {
						$contentArray['###'.$marker.'###'] = '';
					} else {
						$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART###";
					}
					break;
					
				case 'document':
					// default document view
					$contentArray['###'.$marker.'###'] = $this->getDocumentView($productId, $featureId, 'pv', $menuId);
					break;
					
				default:
					if(!$featureVisibility ){			
						$contentArray['###'.$marker.'###'] = '';
						break;
					}
					if ( preg_match('/(\w+)(?:'.PLACEMENT_PARAMETER_REGEXP_PART.')/i', $parts['attr'], $matches) ) {
						// SM Function call
						$func = $matches[1];
						$params = array_key_exists(2, $matches) ? $matches[2] : '';
						$contentArray['###'.$marker.'###'] = $this->fefunctions->handleSMFunctionCall($func, $params, $productId, $menuId, $featureId, 'product');
 					} else if (strpos(strtolower($parts['attr']), 'document:') === 0) {
						// Specific document view
						$viewName = substr($parts['attr'], strlen('document:'));
						$contentArray['###'.$marker.'###'] = $this->getDocumentView($productId, $featureId, 'pv', $menuId, $viewName);
					}
					else {
						$contentArray['###'.$marker.'###'] = $this->getFeatureValue($featureId, $parts['attr'], $this->languageId);
					}
					break;
				}
			}
		}
		$this->plugin->timeTrackStop();
		return $contentArray;			
	}
	
	function getDocumentView($parentId, $valueFeatureId, $valueType, $menuId, $viewName = null)
	{	
		$content = '';
		$Documents = $this->dbutils->getAllFeatureDocs($valueFeatureId, $valueType, $parentId);
		$Documents = $this->tplutils->filterDocumentVisibilityList( $Documents );
		
		if (count($Documents) == 0) {
			return $content;
		}
		
		// Resolve View
		if (empty($viewName)) {
			$viewName = 'DOCUMENTVIEW';
		} else {
			$viewName = 'DOCUMENTVIEW_'.$viewName;
		}
		
		$documentcontent = $this->plugin->getTemplate('###'.$viewName.'###');
		$markerArray = $this->tplutils->getMarkerArray($documentcontent);
		
		// Container for extension specific sub-views names ('.' is default)
		$extDocViews = array('.' => $viewName);
		// Container for sub-view templates and markers
		$extDocTemplates = array($viewName => array('template' => $documentcontent, 'markers' => $markerArray));
		
		$asFeature = array( 
			'featureId' => $valueFeatureId,
			'groupId' => $valueType == 'gv' ? $parentId : 0,
			'productId' => $valueType == 'pv' ? $parentId : 0,
			'documentId' => $valueType == 'dv' ? $parentId : 0,
			'menuId' => $menuId
		);
		
		// Make multi-SMs as <UL>-List
		$pre = $post = $preList = $postList = "";
		if ($this->conf['wrapDocumentLists'] == 'ul') {
			if ( count($Documents) > 1) {
				$featureName = $this->getFeatureValue($valueFeatureId, 'Name', $this->languageId);
				$preList = "<ul class=\"{$featureName}_LIST mS3CDocumentList mS3CDocumentView\">";
				$postList = "</ul>";
				$pre = "<li>";
				$post = "</li>";
			}
		}
		foreach ($Documents as $documentId)
		{
			// Find extension sub-view
			$ext = pathinfo($this->dbutils->getDocumentFile($documentId), PATHINFO_EXTENSION);
			$ext = strtoupper($ext);
			if (!array_key_exists($ext, $extDocViews)) {
				// Find extension specific view template
				$extViewName = $viewName . '_' . $ext;
				$tmpl = $this->plugin->getTemplate('###'.$extViewName.'###');
				if (!empty($tmpl)) {
					$mrk = $this->tplutils->getMarkerArray($tmpl);
					$extDocTemplates[$extViewName] = array('template' => $tmpl, 'markers' => $mrk);
					$extDocViews[$ext] = $extViewName;
				} else {
					$extDocViews[$ext] = $viewName;
				}
			}
			
			$view = $extDocViews[$ext];
			$tmplMark = $extDocTemplates[$view];
			
			$markerValues = $this->fillDocumentMarkerContentArray($tmplMark['markers'], $documentId, 0, $asFeature);
			$content .= $pre.$this->tplutils->substituteMarkerArray($tmplMark['template'], $markerValues).$post;
		}
		//from existst valueexists wrappers
		$content = $this->tplutils->substituteSubpart($content, "###REMOVE_SUBPART###", '');
		return $preList.$content.$postList;
	}

	function fillDocumentMarkerContentArray($markerArray, $documentId = 0, $menuId = 0, $asFeatureValue = array(), tx_ms3commerce_db $db = null)
	{
		
		if ($documentId == 0)
		{
			$documentId = $this->documentId;
			$menuId = $this->currentMenuId;
		}
		if ($menuId == 0)
		{
			$row = $this->dbutils->selectMenu_SingleRow('`Id`', "DocumentId = $documentId");
			if ($row)
			{
				$menuId = $row[0];
			}
			else if(!empty($asFeatureValue))
			{
				$menuId = $asFeatureValue['menuId'];
			}
		}
				
		$contentArray = array();

		foreach ($markerArray as $marker)
		{
			if($marker == 'REMOVE_SUBPART'){
				continue;
			}
			if ($this->smz->isSMZMarker($marker)) {
				$smzcontent = $this->smz->substituteSMZ($documentId, tx_ms3commerce_constants::ELEMENT_DOCUMENT, $marker);
				// Apply on resolved markers
				$markerValues = $this->fillDocumentMarkerContentArray($this->tplutils->getMarkerArray($smzcontent), $documentId,$menuId, $asFeatureValue);
				$content = $this->tplutils->substituteMarkerArray($smzcontent, $markerValues);
				$contentArray['###'.$marker.'###'] = $content;
				
				continue;
			}
			
			if ($this->tplutils->isCustomIncudeMarker($marker)) {
				$custContent = $this->custom->getCustomInclude($marker, DOCUMENT_CONTEXT, $documentId, $menuId);
				$contentArray['###'.$marker.'###'] = $custContent;
				continue;
			}
			
			switch (strtolower($marker))
			{
				case 'document_name':
					$contentArray['###'.$marker.'###'] = $this->dbutils->getDocumentName($documentId);
					break;
				case 'document_auxname':
					$contentArray['###'.$marker.'###'] = $this->dbutils->getDocumentAuxName($documentId);
					break;
				case 'document_uid':
					$contentArray['###'.$marker.'###'] = $documentId;
					break;
				case 'document_file':
					$contentArray['###'.$marker.'###'] = $this->dbutils->getDocumentFile($documentId);
					break;
				case 'document_filelink':
					//quite useless
					$contentArray['###'.$marker.'###'] = $this->plugin->getDocumentLink($documentId, $menuId, 0, true);
					break;
				case 'document_link':
					$contentArray['###'.$marker.'###'] = $this->plugin->getDocumentLink($documentId, $menuId);
					break;
				case 'document_type':
					$path_parts = pathinfo($this->dbutils->getDocumentFile($documentId));
					$contentArray['###'.$marker.'###'] = $path_parts['extension'];
					break;
				case 'document_size':
					$contentArray['###'.$marker.'###'] = filesize(MS3C_ROOT."/".$this->dbutils->getDocumentFile($documentId));
					break;
				case 'document_nameid':
					$contentArray['###'.$marker.'###'] = preg_replace('#[^\w\d-]#u', '_', $this->dbutils->getDocumentName($documentId));
					break;
				default;
					if ( substr(strtolower($marker), 0, 9) == 'document_') {
						// Document function call
						$func = substr($marker, 9);
						if ( preg_match('/(\w+)(?:'.PLACEMENT_PARAMETER_REGEXP_PART.')/i', $func, $matches) ) {
							// SM Function call
							$func = $matches[1];
							$params = array_key_exists(2, $matches) ? $matches[2] : '';
							$contentArray['###'.$marker.'###'] = $this->fefunctions->handleDocFunctionCall($func, $params, $documentId, $menuId, $asFeatureValue);
						}
						break;
					}
					
					$parts = $this->tplutils->getMarkerParts($marker);
					if ($parts === false) {
						break;
					}
					
					$featureId = $this->dbutils->getFeatureIdByName($parts['name']);
					$featureVisibility=$this->tplutils->checkFeatureVisibility($featureId);	
					$featureValue = $this->dbutils->getProductValue($productId, $featureId);
					
					/*
					 if ($featureId == 0)
					{
						if (strtolower($parts['attr']) == 'empty')
							$contentArray['###'.$marker.'###'] = 'mS3Commerce_isempty';
						break;
					}
					*/
					switch (strtolower($parts['attr']))
					{
						case 'value':
							$contentArray['###'.$marker.'###'] = $this->dbutils->getDocumentValue($documentId, $featureId);
							break;
						case 'rawvalue':
							$contentArray['###'.$marker.'###'] = $this->dbutils->getDocumentValue($documentId, $featureId,true);
							break;
						case 'name':
							$contentArray['###'.$marker.'###'] = $parts['name'];
							break;
						case 'empty':
							$contentArray['###'.$marker.'###'] = strlen(trim($this->dbutils->getDocumentValue($documentId, $featureId,true))) > 0
								? 'mS3Commerce_isnotempty' : 'mS3Commerce_isempty';
							break;
						
						// TODO: REMOVE?
						case 'text':
							$tmp_value = $this->dbutils->getDocumentValue($documentId, $featureId);
							if (substr($tmp_value,0,4) == "<img") {
								$contentArray['###'.$marker.'###'] = "/typo3conf/ext/ms3commerce/pi1/image.php?bild=".substr($tmp_value,strpos($tmp_value,"src=")+5,28);
							} else {
								$contentArray['###'.$marker.'###'] = $tmp_value;
							}
							break;
						case 'valueexists':
							if($featureVisibility && $featureValue != ''){
							//if visible and ist not empty! remove the Marker ###REMOVE_SUBPART###
							$contentArray['###'.$marker.'###']='';
							}else{
							// ###REMOVE_SUBPART### marker will be handled later at the end of template rendering so leave it now 
								$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART### ";
							}
							break; 
						case 'exists':
							if($featureVisibility){
							//if visible remove the Marker ###REMOVE_SUBPART###
							$contentArray['###'.$marker.'###']='';
							}else{
							// ###REMOVE_SUBPART### marker will be handled later at the end of template rendering so leave it now 
								$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART###";
							}
							break;
						case 'documentexists':
							if (!$featureVisibility) {
								$docCount = 0;
							} else {
								$Documents = $this->dbutils->getAllFeatureDocs($featureId, 'dv', $documentId);
								$Documents = $this->tplutils->filterDocumentVisibilityList( $Documents );
								$docCount = count($Documents);
							}
							if($docCount > 0) {
								$contentArray['###'.$marker.'###'] = '';
							} else {
								$contentArray['###'.$marker.'###'] = "###REMOVE_SUBPART###";
							}
							break;
						case 'document':
							$contentArray['###'.$marker.'###'] = $this->getDocumentView($documentId, $featureId, 'dv', $menuId);
							break;
							
						default:
							if ( preg_match('/(\w+)(?:'.PLACEMENT_PARAMETER_REGEXP_PART.')/i', $parts['attr'], $matches) ) {
								// SM Function call
								$func = $matches[1];
								$params = array_key_exists(2, $matches) ? $matches[2] : '';
								$contentArray['###'.$marker.'###'] = $this->fefunctions->handleSMFunctionCall($func, $params, $documentId, $menuId, $featureId, 'document');
							} else if (strpos(strtolower($parts['attr']), 'document:') === 0) {
								// Specific document view
								$viewName = substr($parts['attr'], strlen('document:'));
								$contentArray['###'.$marker.'###'] = $this->getDocumentView($documentId, $featureId, 'dv', $menuId, $viewName);
							}
							else {
								$contentArray['###'.$marker.'###'] = $this->getFeatureValue($featureId, $parts['attr'], $this->languageId);
							}
						break;
					}
				break;
			}
		}
		return $contentArray;
	}
	
	/**
	 * get the DETAILVIEW section out of a template and Fill it with content
	 * Resolves information about Products 
	 * Information about Parent GROUP
	 * Information about Relatated PRODUCT(S)
	 * Information about the PRODUCT itself
	 * Optional  DOCUMENTS information associated to the Product
	 * Detail view may contain a Header with BREADCRUMB navigation, this sub-part will be also resolved here
	 * Visiblility check is performed here
	 * @see  fillBreadcrumbSubpart()
	 * @param itx_ms3commerce_plugin $plugin
	 * @param tx_ms3commerce_db $db
	 * @return type string (HTML content)
	 */
	function getDetailView(itx_ms3commerce_plugin $plugin = null, tx_ms3commerce_db $db = null)
	{
		$plugin = $this->plugin;	// Intellisense
		$db = $this->db;		// Intellisense
		$content = '';
			
		if ($this->debug_enabled) 
		{
			$content .= 'The detail view content.<br>';
			$content .= 'TemplateFile: <code>'.$this->conf['templateFile'].'</code><br>';
			$content .= 'productId: <code>'.$this->productId.'</code><br>';
		}		
		$template = $plugin->getTemplate('###DETAILVIEW###');
		// Fill-out the 'BREADCRUMB' subpart.
		$template = $this->fillBreadcrumbSubpart($template);

		// Preload product template, as in some cases, the parent might substitute it
		// (e.g., relation to some other product)
		$productTemplate = $this->tplutils->getSubpart($template, '###PRODUCT###');
		
		// Fill-out the 'PARENT' subpart, which contains information about the parent
		// group. The subpart is optional.
		$parentTemplate = $this->tplutils->getSubpart($template, '###PARENT###');
		if (!empty($parentTemplate))
		{
			$markerValues = $this->fillGroupMarkerContentArray($this->tplutils->getMarkerArray($parentTemplate));
			$parentTemplate = $this->tplutils->substituteMarkerArray($parentTemplate, $markerValues);

			$template = $this->tplutils->substituteSubpart($template, '###PARENT###', $parentTemplate, FALSE);
		}
		
		//is this prod visible?
		if(($this->productId)&&($this->tplutils->checkProductVisibility($this->productId)==true))
		{	
			// Fill-out the 'PRODUCT' subpart, which contains information about the product
			// group. The subpart is optional.
			if (!empty($productTemplate))
			{
				$markerValues = $this->fillProductMarkerContentArray($this->tplutils->getMarkerArray($productTemplate),$this->productId,$this->currentMenuId);
				$productTemplate = $this->tplutils->substituteMarkerArray($productTemplate, $markerValues);

				$template = $this->tplutils->substituteSubpart($template, '###PRODUCT###', $productTemplate);
			}
		} else{
				$template = $this->tplutils->substituteSubpart($template, '###PRODUCT###', '');
		}
		
		
		// Fill-out the 'DOCUMENTS' subpart, which contains information about the Document
		// group. The subpart is optional.
		if (($this->documentId)&&($this->tplutils->checkDocumentVisibility($this->documentId)==true))
		{
			$documentTemplate = $this->tplutils->getSubpart($template, '###DOCUMENT###');
			if (!empty($documentTemplate))
			{
				$markerValues = $this->fillDocumentMarkerContentArray($this->tplutils->getMarkerArray($documentTemplate),$this->documentId,$this->currentMenuId);
				$documentTemplate = $this->tplutils->substituteMarkerArray($documentTemplate, $markerValues);

				$template = $this->tplutils->substituteSubpart($template, '###DOCUMENT###', $documentTemplate);
			}
		}
		else{
			$template = $this->tplutils->substituteSubpart($template, '###DOCUMENT###', '');
		}
		
		//from existst valueexists wrappers
		$template = $this->tplutils->substituteSubpart($template, "###REMOVE_SUBPART###", '');
		return $template;
	}
		
	/**
	 * Gets the value of a specified feature attribute.
	 * @param $featureId   The identifier of the feature.
	 * @param $featureAttr The feaure's attribute. Must be one of the following
	 *  values: 'title', 'info'.
	 * @param $languageId  The language identifier.
	 * @return string HTML
	 */
	function getFeatureValue($featureId, $featureAttr, $languageId = 0, tx_ms3commerce_db $db = null)
	{
		$db = $this->db; // intellisense
		$value = '';
		do 
		{
			if ($featureId <= 0)
				break;
				
			$field = '';
			$emptyCheck = false;
			$featureAttr = strtolower($featureAttr);
			switch ($featureAttr)
			{
				case 'titleempty':	$emptyCheck = true; //fallthrough
				case 'title':		$field = '`Title`';     break;
				case 'infoempty':	$emptyCheck = true; //fallthrough
				case 'info':		$field = '`Info`';      break;
				case 'unitempty':	$emptyCheck = true; //fallthrough
				case 'unit':		$field = '`UnitToken`'; break;
				case 'prefixempty':	$emptyCheck = true; //fallthrough
				case 'prefix':		$field = '`Prefix`';    break;
				case 'dimensionempty':$emptyCheck = true; //fallthrough
				case 'dimension':	$field = '`Dimension`'; break;
				
				case 'id':			$field = '`FeatureId`'; break;
				
				case 'name':
					return $this->dbutils->getFeatureName($featureId);
			}
			if (empty($field))
				break;
			
			
			$value = $this->dbutils->selectFeatureValue_singleRow($field,$featureId);
			if ($emptyCheck)
			{
				$cls = substr($featureAttr, 0, strlen($featureAttr)-5);
				if (strlen(trim($value)) == 0)
				{

					$value = "mS3Commerce_{$cls}_isempty";
				} else {
					$value = "mS3Commerce_{$cls}_isnotempty";
				}
			}
		} 
		while (FALSE);
		return $value;
	}

	/**
	 * 
	 * @param type $source The path to the source file
	 * @param type $dest The destination file: i.e. assets/catalog/%G/%A-%N_%Wx%H
	 * @param type $width
	 * @param type $height
	 * @param type $groupPath
	 * @param type $itemNumber
	 * @param type $productName
	 * @return type 
	 */
	function getDocumentPath($source,$pattern,$path,$name,$other,$width,$height,$ext)
	{
		if (!isset($ext))
			$ext = pathinfo($source,PATHINFO_EXTENSION);
		if ($width < 0) $width = '';
		if ($height < 0) $height = '';
		$pattern=str_replace('%N',$name,$pattern);
		$pattern=str_replace('%P',$path,$pattern);
		$pattern=str_replace('%W',$width,$pattern);
		$pattern=str_replace('%H',$height,$pattern);
		$pattern=str_replace('%O',$other,$pattern);
		return $pattern.'.'.$ext;
	}

	/**
	 * Get include marker content.
	 * @param string $marker 
	 * @return string 
	 */  
	function getIncludeMarkerContent($marker, $productGroupId = null,itx_ms3commerce_plugin $plugin = null)
	{
		if ($productGroupId === null) {
			$productGroupId = $this->productGroupId;
		}
		//if you are a 'INCLUDE_' marker
		if (strpos($marker, 'INCLUDE_') === 0)
		{			//then get 
			$templateName = $this->custom->getIncludeTemplateName($marker, $productGroupId);
			
			// If no custom...
			if($templateName==null){	
				// ... get data driven postfix
				$confFeature=$this->conf['list_include_feature_name'];
				$featId=$this->dbutils->getFeatureIdByName($confFeature,true);
				$featValue=$this->getGroupValue($productGroupId, $featId, true);
				$InclName=$this->tplutils->getIncludeName($marker);
				
				if(!empty($featValue)){
					// Check if postfix template exists, if not, use without postfix
					$templateName=$InclName."_".$featValue;
					$cont = $this->plugin->getTemplate('###'.$templateName.'###');
					if (empty($cont)) {
						$templateName = $InclName;
					}
				} else {
					// No postfix, use without
					$templateName = $InclName;
				}
			}			
			return $this->plugin->getTemplate('###'.$templateName.'###'); 
		}
		else
		{
			return '';
		}
	}
		
	/**
	 * Gets menu paths as an array of strings.
	 * @param array $menuIdArray
	 * @return array array of string path
	 * JCS OPTIMIZED
	 */  
	function getMenuPaths($menuIdArray, $addThisMenuToPath = false)
	{
		if(is_array($menuIdArray))
		{
			$idList=  join(',', $menuIdArray);		
		} else if ($menuIdArray != '') 
		{
			$idList=$menuIdArray;
		} else 
		{
			return array();
		}
		
		$menuPaths = array();
		$result = $this->db->exec_SELECTquery("Id,Path","Menu","Id IN (".$idList.")");
		while($menu=$this->db->sql_fetch_row($result)){
				$menuId=$menu[0];
				$path= $menu[1];
				if ($addThisMenuToPath) {
					$path .= '/'.$menuId;
				}
				$menuPaths[$menuId] = $path;
		}
		return $menuPaths;
	}

	/**
	 * Retrieves the title of a menu item associated with the specified group.
	 * @param int $groupId Group id
	 * @return string get group name
	 */
	function getMenuTitleForGroup($groupId, $raw = false)
	{
		// Get the title of the menu item using the feature for display role. This
		// allows language-dependent titles.
		$title = '';
		if ($this->menuDisplayRoleFeatureId > 0)
			$title =$this->getGroupValue($groupId, $this->menuDisplayRoleFeatureId, $raw);
		if (strlen($title) > 0)
			return $title;
		return $this->dbutils->getGroupAuxName($groupId);
	}
  
	function setPageTitle( $isProduct )
	{
		if ( !empty($this->titleSMName) ) {
			$featureId = $this->dbutils->getFeatureIdByName($this->titleSMName);
			if ( $featureId > 0 ) {
				if ( $isProduct ) {
					if ($this->productId)
					{
						$value = $this->dbutils->getProductValue($this->productId, $featureId, true);
					}
					else if ($this->documentId)
					{
						$value = $this->dbutils->getDocumentValue($this->documentId, $featureId, true);
					}
				} else {
					$value =$this->getGroupValue($this->productGroupId, $featureId, true);
				}
				
				if ( !empty($value) ) {
					$this->plugin->setPageTitle( $value );
				}
			}
		}
	}

	/**
	 * 
	 * @param string $content
	 * @param array $conf
	 * @return array 
	 */
	function makeMenuArray($curItemState, $parentItemState, $additionalItems,itx_ms3commerce_plugin $plugin=null) 
	{
		$plugin = $this->plugin; // intellisense
		//error_reporting(-1);
		$wantFullMenu = $this->conf['make_full_menu'];
		
		if ($wantFullMenu) {
			$menuArray = $this->dbutils->selectFullMenuItems($this->showProducts, $this->showDocuments, $this->lastVisibleLevel);
			
			// Get root
			$rootPath = $this->dbutils->selectMenu_SingleRow('Path', 'Id = '.$this->rootMenuId);
			$rootPath = $rootPath[0] . '/' . $this->rootMenuId;
			$menuPath = $this->dbutils->selectMenu_SingleRow('Path', 'Id = '.$this->currentMenuId);
			$menuArray = $this->parseMenuArray($menuArray, $this->currentMenuId, $menuPath[0], $rootPath, $curItemState, $parentItemState);
		} else {
			// If current item is a product, but we should not show products,
			// get its parent
			$selectMenuId = $this->currentMenuId;
			$row = $this->dbutils->selectMenu_SingleRow('ProductId, DocumentId, ParentId', "Id = {$this->currentMenuId}");
			if (isset($row[0]) OR isset($row[1])) {
				$selectMenuId = $row[2];
			}
			
			$menuArray = $this->selectPartialMenuItems($selectMenuId, $this->rootMenuId, $curItemState, $parentItemState);
		}
		
		$this->plugin->timeTrackStart("fill Titles");
		$this->fillMenuTitles($menuArray, $additionalItems);
		$this->plugin->timeTrackStop();
		//print_r($menuArray);
		return $menuArray['_SUB_MENU'];
	}
	
	/**
	 * Replaces the group value markers
	 * @param string $value
	 * @return string replacement. 
	 * @deprecated
	 */
	function replaceGroupValueMarkers($value)
	{
		do
		{
			if (strlen($value) == 0)
				// Nothing to do for empty values.
				break;

			$markerArray = $this->tplutils->getMarkerArray($value);
			if (count($markerArray) == 0)
				// Nothing to do if there are no markers.
				break;

			foreach ($markerArray as $marker)
			{
				$parts = explode('_', $marker);
				switch (strtolower($parts[0]))
				{
				case 'plink':
					$productId = intval($parts[1]);
					$menuId = $this->dbutils->getChildMenuByProductId($this->currentMenuId, $productId);
					$href = $this->plugin->getProductLink($productId, $menuId);
					$value = str_replace('###'.$marker.'###', $href, $value);
					break;
				}
			}				
		}
		while (false);

		return $value;
	}

	/**
	 * Returns for the specified menu item all its ancestors.
	 * @param int $currentMenuId
	 * @param int $rootId
	 * @return array
	 */
	function selectPartialMenuItems($currentMenuId, $rootId = NULL, $curItemState = NULL, $parentItemState = NULL, tx_ms3commerce_db $db = null)
	{
		$showProducts=$this->showProducts;
		$showDocuments=$this->showDocuments;
		$lastVisibleLevel=$this->lastVisibleLevel;
		$menuArray = array();
 
		// Apply the item state - we use the menu's 'Path' to get the ancestors.
		//$menuItem = &$menuArray;
		$partMenu =$this->dbutils->getPartialMenuItems($currentMenuId, $rootId,$showProducts, $showDocuments,$lastVisibleLevel);
		$menuArray = $partMenu['array'];
		$menuPath = $partMenu['path'];
		$rootPath = $partMenu['root'];
		return $this->parseMenuArray($menuArray, $currentMenuId, $menuPath, $rootPath, $curItemState, $parentItemState);
	}
	
	function parseMenuArray($menuArray, $currentMenuId, $menuPath, $rootPath, $curItemState, $parentItemState)
	{
		$menuItem =&$menuArray;
		$ancestorArray = explode('/', $menuPath);
		
		if (isset($parentItemState)) {
			foreach ($ancestorArray as $ancestor) 
			{
				if (empty($ancestor))
					continue;
				if (count($menuItem['_SUB_MENU']) == 0)
					// no children...
					break;

				$menuItem = &$menuItem['_SUB_MENU'][$ancestor];

				$menuItem['ITEM_STATE'] = $parentItemState;
			}
		}

		// Set the state of the current menu item. Depending on the value of 
		// lastVisibleLevel, the current menu item may not be visible.
		if (isset($curItemState)) {
			if (is_array( $menuItem['_SUB_MENU'])&& array_key_exists(strval($currentMenuId), $menuItem['_SUB_MENU'])) {
				$menuItem['_SUB_MENU'][strval($currentMenuId)]['ITEM_STATE'] = $curItemState;
			}
		}

		$rootSkip = 0;
		if (!empty($rootPath))
		{
			$menuItem = &$menuArray;
			$rootArray = explode('/', $rootPath);
			foreach ($rootArray as $ancestor)
			{
				$rootSkip++;
				if (!empty($ancestor))
					$menuItem = &$menuItem['_SUB_MENU'][$ancestor];
			}
			$menuArray = $menuItem;
		}

		$skipcount = $this->skipMenuLevels;
		if ($skipcount > 0) {
			$menuItem = &$menuArray;
			for ($i = $rootSkip; $i < $rootSkip+$skipcount && $i < count($ancestorArray); ++$i) {
				$menuItem = &$menuItem['_SUB_MENU'][$ancestorArray[$i]];
			}
			$menuArray = $menuItem;
		}
		
		return $menuArray;
	}
  
	/**
	 * Get search results view content
	 * @return type 
	 */
	function getSearchResultsViewContent()
	{ 
		$params = $this->tplutils->getSearchParams();

		$productIdArray = array();
		$this->search->search($params, $productIdArray);
		$itemCount = count($productIdArray);

		if ($itemCount == 0) {
			return $this->plugin->getNoResultView();
		}

		if ($this->itemsPerPage > 0)
			$productIdArray = array_slice($productIdArray, $this->itemStart - 1, $this->itemsPerPage);

		$template = $this->plugin->getTemplate('###SEARCHRESULTSVIEW###');
		$content = $template;

		$templateHeader = $this->tplutils->getSubpart($template, '###HEADER###');
		if (!empty($templateHeader))
		{
			$headerContent = $this->tplutils->substituteMarkerArray(
				$templateHeader,
				$this->fillPaginationMarkerArray($itemCount, $this->itemStart));
			
			// Substitute any feature markers that may be used.
			$contentArray = $this->fillFeatureMarkerContentArray($this->tplutils->getMarkerArray($headerContent));
			$headerContent = $this->tplutils->substituteMarkerArray($headerContent,	$contentArray);
			
			$content = $this->tplutils->substituteSubpart($content, '###HEADER###', $headerContent);
		}

		$templateContent = $this->tplutils->getSubpart($template, '###CONTENT###');
		if (!empty($templateContent))
		{
			$templateProduct = $this->tplutils->getSubpart($template, '###PRODUCT###');
			if (!empty($templateProduct))
			{
				$productMarkerArray = $this->tplutils->getMarkerArray($templateProduct);
				
				$productContent = '';
				foreach ($productIdArray as $productId)
				{
					$menuId = 0;
					
					foreach ($this->searchMenuIds as $searchMenuId)
					{
						if (empty($searchMenuId))
							continue;
						$row = $this->dbutils->selectMenu_SingleRow('`Id`',
							"`ProductId`=$productId AND `Path` LIKE '%/$searchMenuId/%'");
						if ($row)
						{
							$menuId = $row[0];
							break;
						}
					}
					
					$productContent .= $this->tplutils->substituteMarkerArray(
						$templateProduct,
						$this->fillProductMarkerContentArray($productMarkerArray, $productId, $menuId));
				}
				
				$content = $this->tplutils->substituteSubpart($content, '###CONTENT###', $productContent);
			}
		}
		//from existst valueexists wrappers
		$content = $this->tplutils->substituteSubpart($content, "###REMOVE_SUBPART###", '');
		return $content;
	}
        
	function getNoResultView()
	{
		$no_result_pid = $this->noResultsPageId;
		if($no_result_pid != 0 && $this->plugin != null) {
			$this->plugin->pageRedirect($no_result_pid);
		}
	}
	
	function display_post_get() {
		if ($_POST) {
			print_r($_POST);
			echo "Displaying POST Variables: <br> \n";
			echo "<table border=1> \n";
			echo " <tr> \n";
			echo "  <td><b>result_name </b></td> \n ";
			echo "  <td><b>result_val  </b></td> \n ";
			echo " </tr> \n";
			while (list($result_nme, $result_val) = each($_POST)) {
				echo " <tr> \n";
				echo "  <td> $result_nme </td> \n";
				echo "  <td> $result_val </td> \n";
				echo " </tr> \n";
			}
			echo "</table> \n";
		}
	}
	
	function downloadDocument()
	{
		$documentId = $this->documentId;
		if ($this->tplutils->checkDocumentVisibility($documentId)==true)
		{
			$file = $this->dbutils->getDocumentFile($documentId);
			//$file = "Graphics/Pic0/00002839_2.jpg";
			
			$ext = pathinfo($file, PATHINFO_EXTENSION);
			// Find context id (from Menu or from Document for loose files)
			$row = $this->dbutils->selectMenu_SingleRow('contextid', "id = $this->currentMenuId AND DocumentId = $documentId");
			if ($row) {
				$mapId = $row[0];
			}
			else
			{
				$row = $this->dbutils->selectDocument_singleRow('contextid', "id = $documentId");
				if ($row) {
					$mapId = $row->contextid;
				}
			}
			
			// Find RealURL file Name for contextid
			$row = $this->dbutils->selectRealUrlSingleRow("realurl_seg_mapped", "asim_mapid = '".$mapId."'");
			if ($row[0]) {
				$docName = $row[0];
			}
			else
			{
				$docName = $this->dbutils->getDocumentName($documentId);
			}
			
			$fileName = $docName.'.'.$ext;
			
			if (file_exists(MS3C_ROOT."/".$file))
			{
				// Make sure that no out of memory or timing issues can occure
				@$ct = ob_get_level();
				for ($i = 0; $i < $ct; $i++) {
					@ob_end_clean();
				}
				@set_time_limit(0);
				
				// Disable caching
				header("Cache-Control: must-revalidate");
				header("Pragma: must-revalidate");
				header("Expires: 0");
				
				if (defined('CHECK_IF_NOT_MODIFIED') && CHECK_IF_NOT_MODIFIED) {
					$fTime = filemtime(MS3C_ROOT."/".$file);
					// Check for a "If-Modified-Since" and "ETag" header
					$reqHead = getallheaders();
					if (is_array($reqHead)) {
						$reqHead = array_change_key_case($reqHead);
						if (array_key_exists("if-modified-since", $reqHead) && array_key_exists("etag", $reqHead)) {
							$time = strtotime($reqHead['if-modified-since']);
							$etag = $reqHead['etag'];
							if ( $time !== false && $etag == md5($file)) {
								// Compare to current and file modification time
								if ( $time <= time()) {
									if ( $fTime && $fTime <= $time) {
										// We have a If-modified-since that is greater than
										// the last change of the file ==> Do a 304 (Not Modified)
										header(':', true, 304);
										exit();
									}
								}
							}
						}
					}
					
					// Modified, set Last-Modified date so that future
					// request can use a If-Modified-Since header
					if ( $fTime ) {
						header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fTime).' GMT');
						header('ETag: '.md5($file));
					}
				}
				
				// Set file download header
				header('Content-Description: File Transfer');
				header("Content-Type: application/octet-stream");
				header("Content-Length: " . filesize(MS3C_ROOT."/".$file));	
				header("Content-Disposition: attachment; filename=\"$fileName\"");
				header('Content-Transfer-Encoding: binary');
				
				
				// Return the file content
				readfile(MS3C_ROOT."/".$file);
			}
			else
			{
				$this->plugin->page404Error( "File $fileName not found" );
			}
		}
		else
		{
			if (array_key_exists('access_denied_pid', $this->conf)) {
				$this->plugin->pageRedirect(intval($this->conf['access_denied_pid']));
			} else {
				//http_response_code(403);
				$this->plugin->pageUnavailableError( 'Access denied' );
			}
		}
	}
	
	function generateNonce(){
		//generate a nonce from a concatenation of a hex random and a timestamp
		$rnd = dechex(mt_rand(0x80000000,0xFFFFFFFF));
		$timestamp=time();
		$nonce=$rnd.$timestamp;
		return $nonce;
	}  
	
	

}

function getSubIndex(&$val, $key, $idx) {
	$val = $val[$idx];
}

?>
