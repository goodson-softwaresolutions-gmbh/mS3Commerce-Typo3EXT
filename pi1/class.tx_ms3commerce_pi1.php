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

require_once(__DIR__.'/../load_dataTransfer_config.php');

define('USE_CHASH', false);

require_once('class.tx_ms3commerce_constants.php');
require_once('class.tx_ms3commerce_template.php');  
require_once('class.itx_ms3commerce_plugin.php');
require_once('class.itx_ms3commerce_pagetypehandler.php');
require_once('class.tx_ms3commerce_linker.php');
require_once('class.tx_ms3commerce_plugin_sessionUtils.php');
require_once('class.tx_ms3commerce_realurl.php');

require_once('class.tx_ms3commerce_custom.php');
if (defined('MS3C_SHOP_SYSTEM') && MS3C_SHOP_SYSTEM != 'None') {
	require_once('class.tx_ms3commerce_'.MS3C_SHOP_SYSTEM.'.php');
} else {
	require_once('class.tx_ms3commerce_dummyshop.php');
}

/**
 * Plugin 'mS3 Commerce' for the 'mS3 Commerce' extension.
 * ALL code which belongs only to the CMS belongs here.
 * We cannot access Typo3 directly outside of this class.
 * @see main(),
 * @author Goodson GmbH
 * @package	TYPO3
 * @subpackage	tx_ms3commerce
 */
class tx_ms3commerce_pi1 extends TYPO3\CMS\Frontend\Plugin\AbstractPlugin implements itx_ms3commerce_plugin
{
	// Required for plug-in
	var $prefixId      = 'tx_ms3commerce_pi1';			// Same as class name
	var $scriptRelPath = 'pi1/class.tx_ms3commerce_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'ms3commerce';				// The extension key.
	var $pi_checkCHash = false;
	var $gr            = null;   // The graphics magick object
	var $pageTypeHdlrs = array();
	/** @var tx_ms3commerce_template */
	var $template	   = null;
	/** @var tx_ms3commerce_linker */
	var $linker		   = null;
	/** @var tx_ms3commerce_timetracker */
	var $timetracker = null;
	
	function __construct() {
		$this->timetracker = new tx_ms3commerce_timetracker;
	}
	
	/**
	 * Entry point for the plugin 
	 * @see init(),template::getTemplate()
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	Template filled with mS3 Commerce content to be displayed on the website
	 * 
	 * 	 
	 */
	function main($content, $conf)
	{
		$this->timeTrackStart("mS3: main");
		$start = microtime(true);
		
		$db = tx_ms3commerce_db_factory::buildDatabase(true);
		$this->timeTrackStart("init");
		$this->init($conf, $db);
		$this->timeTrackStop();
		
		if ( array_key_exists($GLOBALS['TSFE']->type, $this->pageTypeHdlrs) ) {
			$this->timeTrackStart("calling PageHandler");
			$ret = $this->pageTypeHdlrs[$GLOBALS['TSFE']->type]->handlePageTypeCall($this->template );
			$this->timeTrackStop();
			$this->timeTrackStop();
			return $ret;
		} else {
			$content = '';
			if ($_GET['ms3debug'] == '1') {
				$content .= "<span style='display:none;'>id={$GLOBALS["TSFE"]->id}&mS3MenuId={$this->template->currentMenuId}&mS3GroupId={$this->template->productGroupId}&mS3ProductId={$this->template->productId}&mS3DocumentId={$this->template->documentId}</span>";
				$content .= "<span style='display:none;'>".print_r($_GET,true)."</span>";
				$content .= "<span style='display:none;'>".$GLOBALS['TSFE']->anchorPrefix."</span>";
				$content .= "<span style='display:none;'>".print_r($conf,true)."</span>";
			}
			$content .= $this->template->getTemplate();
			$end = microtime(true);
			$el = $end - $start;
			if ($_GET['ms3debug'] == '1') {
				$content .= "<span style='display:none;'>mS3 Execution Time: $el</span>";
				if ($_GET['ms3debugdb'] == '1') {
					mS3CommerceDBLogger::dump(false, "<span style='display:none;'>", "</span>", "<br/>");
				}
			}

			$this->timeTrackStop();
			return $content;
		}
	}
	
	/**
	 * Entry point for mS3 Commerce data driven menu Array
	 * @see tx_ms3commerce_template::makeMenuArray()
	 * @param type $content
	 * @param type $conf
	 * @return type
	 */
	function makeMenuArray($content, $conf)
	{
		$this->timeTrackStart("mS3: menu");
		$start = microtime(true);
		
		$db = tx_ms3commerce_db_factory::buildDatabase(true);
		$this->init($conf, $db);
		$this->setMenuConfVars($conf);
		$additionalItems = array(
			'uid' => -1,			// Special code: use id+9000 (WHY??)
			'ITEM_STATE' => 'NO',	// Itemstate is normal, if not set otherwise
			'title' => '',			// Set default empty title (will be overridden if found)
		);
		$ret = $this->template->makeMenuArray('CUR', 'ACTIFSUB', $additionalItems);
		
		$end = microtime(true);
		$el = $end - $start;
		if ($_GET['ms3debug'] == '1') {
			echo "<span style='display:none;'>mS3 Menu Execution Time: $el</span>";
		}
		
		$this->timeTrackStop();
		return $ret;
	}
	/**
	 * Build a menu structure for a hierachicalsearch
	 * @see tx_ms3commerce_formbuilder::makeMenuHierarchicalSearch()
	 * @param type $content
	 * @param type $conf
	 * @return type
	 */
	function makeMenuHierarchicalSearch($content,$conf)
	{
		$this->timeTrackStart("mS3: search menu");
		$start = microtime(true);
		
		$db = tx_ms3commerce_db_factory::buildDatabase(true);
		$this->init($conf, $db);
		$this->setMenuConfVars($conf);
		$additionalItems = array(
			'uid' => -1,			// Special code: use id+9000 (WHY??)
			'ITEM_STATE' => 'NO',	// Itemstate is normal, if not set otherwise
			'title' => '',			// Set default empty title (will be overridden if found)
		);
		$ret = $this->template->makeMenuHierarchicalSearch('CUR','ACTIVSUB',$additionalItems);
		
		$end = microtime(true);
		$el = $end - $start;
		if ($_GET['ms3debug'] == '1') {
			echo "<span style='display:none;'>mS3 Search Menu Execution Time: $el</span>";
		}
		
		$this->timeTrackStop();
		return $ret;
	}
	/**
	 * generate template object and initializes Parameters (from get , conf and level dependent)
	 * Install default handlers, create an instance of custom and initialize it, create a shop intance.
	 * @see setGetVars(),setLevelConfig(),setConfVars()
	 * @param type $conf
	 * @param type $db
	 */
	function init(&$conf, $db) {
		$this->conf = &$conf;
		$this->template = new tx_ms3commerce_template($this,$db,$conf);
		$template=$this->template;
		$this->initParameters($template,$db);
		$template->dbutils=new tx_ms3commerce_DbUtils($db, $template->marketId,$template->languageId, $template);
		
		$this->setGetVars($this->template,$db);
		
		$this->setLevelConfig();
		
		$this->setConfVars($template,$db);		
		// Install default handler
		$this->installPageTypeHandler( MS3C_AJAX_SEARCH_PAGETYPE, new tx_ms3commerce_searchHandler() );
		$this->installPageTypeHandler( MS3C_DOCUMENT_DOWNLOAD_PAGETYPE, new tx_ms3commerce_downloadHandler() );
		$this->installPageTypeHandler( MS3C_SUGGEST_PAGETYPE, $this->template->search );
		
		$this->template->custom = $this->makeCustomInstance($db);
	
		$this->linker = new tx_ms3commerce_linker($db, $conf, $this->cObj, $this->template, $this->template->dbutils, $this->template->custom);
		
		if (defined('MS3C_SHOP_SYSTEM') && MS3C_SHOP_SYSTEM != 'None')
		{
			$shopClass = 'tx_ms3commerce_' . MS3C_SHOP_SYSTEM;
			$this->template->shop = new $shopClass($this->template);
		} else {
			$this->template->shop = new tx_ms3commerce_dummyshop($this->template);
		}
		
			
		$this->template->custom->init();
	}
	/**
	 * Iinitalize Level dependent parameters
	 */
	function setLevelConfig()
	{
		$template=$this->template;
		if($template->conf['levels.']){
			$levels=$template->dbutils->getMenuLevels($template->currentMenuId, $template->productGroupId);
			$level=$levels['level'];
			$maxLevel=$levels['maxLevel'];
					
			for ($i = $level; $i >= 0; $i--)
			{
				if ($i == 0)
				{
					break;
				}
				$lb=$maxLevel-$i;
				// check for back configurations
				if ($this->conf['levels.']["LB$lb."])
				{
					$levelConf = $this->conf['levels.']["LB$lb."];
					//merge with conf array
					$this->conf = tx_ms3commerce_plugin_sessionUtils::mergeRecursiveWithOverrule($this->conf, $levelConf);					
					break;					
				}
				// check for front configurations
				if($this->conf['levels.']["L$i."])
				{
					$levelConf = $this->conf['levels.']["L$i."];
					//merge with conf array
					$this->conf = tx_ms3commerce_plugin_sessionUtils::mergeRecursiveWithOverrule($this->conf, $levelConf);
					break;
				}			
			}
		}
	}

	public function installPageTypeHandler( $pageType, itx_ms3commerce_pagetypehandler $handler)
	{
		if ( array_key_exists($pageType, $this->pageTypeHdlrs) ) {
			return false;
		}
		
		$this->pageTypeHdlrs[$pageType] = $handler;
		return true;
	}

	function initParameters(tx_ms3commerce_template $template, tx_ms3commerce_db $db)
	{
		//error_reporting(-1);
		if ( $this->conf['individual_config']) {
			$this->loadFlexformConfig();
		}
		// Typo3 functions
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = true;
		// set our internal variables
		
		$template->rootMenuId = is_numeric($this->conf['root_menu_id']) ? intval( $this->conf['root_menu_id'] ) : 0;
		$template->marketId = intval( NVL($this->conf['market_id'], 0) );
		$template->languageId = intval( NVL($this->conf['language_id'], 1) );
		
	}
	
	function loadFlexformConfig()
	{
		$this->pi_initPIflexForm();
		$localConf = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'configuration');
		$lines = preg_split('/\n/', $localConf);
		
		$arr = &$this->conf;
		$this->parseFlexFormPart($arr, $lines);
	}
	
	function parseFlexFormPart(&$array, $lines)
	{
		for ($lineCt = 0; $lineCt < count($lines); ++$lineCt) {
			$l = $lines[$lineCt];
			if (preg_match('#^\s*//#', $l) || preg_match('/^\s*#/', $l)) {
				continue;
			}
			
			// Normal part (assignment)
			$ct = preg_match('/^\s*([\w_\-\.]*)\s*=\s*(.*)\s*$/u', $l, $matches );
			if ( $ct ) {
				// Split by "." syntax
				$path = explode('.', $matches[1]);
				$item = &$array;
				$key = end($path);
				array_splice($path, -1);
				foreach ($path as $k) {
					$k = trim($k).'.';
					if (!array_key_exists($k, $item)) {
						$item[$k] = array();
					}
					$item = &$item[$k];
				}
				$item[$key] = trim($matches[2]);
				unset($item);
			} else {
				// Handle blocks ( "someblock { .... }" )
				// Use Typo3 Syntax, i.e. "someblock {" and "}" have to be the only content on the line
				$ct = preg_match('/\s*([\w\-\.]*)\s*\{\s*/', $l, $matches);
				if ($ct) {
					// Split by "." syntax
					$path = explode('.', $matches[1]);
					$item = &$array;
					foreach ($path as $k) {
						$k = trim($k).'.';
						if (!array_key_exists($k, $item)) {
							$item[$k] = array();
						}
						$item = &$item[$k];
					}
					
					$subarray = &$item;
					$sublines = array();
					
					// Parse till matching } is found
					$braceCt = 1;
					for ($lineCt = $lineCt+1; $braceCt > 0 && $lineCt < count($lines); ++$lineCt) {
						$l = $lines[$lineCt];
						if (preg_match('/\s*\}\s*/', $l)) {
							// Found closing }
							$braceCt--;
							if ($braceCt) {
								$sublines[] = $l;
							}
						} else if (preg_match('/\s*([\w\-\.]*)\s*\{\s*/', $l)) {
							// Found nesting {
							$braceCt++;
							$sublines[] = $l;
						} else {
							$sublines[] = $l;
						}
					}
					
					// Recursively parse sublines
					$this->parseFlexFormPart($subarray, $sublines);
					unset($sublines);
					$lineCt--;
				}
			}
		}
	}
	
	function getPluginRoot()
	{
		if (MS3C_TYPO3_RELEASE == '9') {
			return \TYPO3\CMS\Core\Utility\PathUtility::stripPathSitePrefix(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($this->extKey));
		} else {
			return \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($this->extKey);
		}
	}

	function loadSession($key)
	{
		return tx_ms3commerce_plugin_sessionUtils::loadSession($key);
	}
	
	function storeSession($key, $value)
	{
		return tx_ms3commerce_plugin_sessionUtils::storeSession($key, $value);
	}
	
	/**
	 * Loads a template based on the templateFile parameter.
	 * @param string $templateName Name of the template within the template file.
	 * @return string Returns the template
	 */
	function getTemplate($templateName)
	{
		$template = $this->getTemplateFile($this->conf['templateFile']);
		if (empty($template)) {
			return '';
		}
		return tx_ms3commerce_TplUtils::getSubpart($template, $templateName);
	}

	private static $s_filecache = array();
	function getTemplateFile($file) {
		if (array_key_exists($file, self::$s_filecache)) {
			return self::$s_filecache[$file];
		}
		$template = $this->fileResource($file);
		if (empty($template))
			return '';
		
		// Resolve IMPORT_FILE templates recursevly
		$matches = array();
		$ct = preg_match_all('/###IMPORT_FILE\(([^\)]+)\)###/', $template, $matches, PREG_SET_ORDER);
		if ($ct) {
			foreach ($matches as $importFile) {
				// $importFile[1] = Path
				// $importFile[0] = Complete Marker
				$content = $this->getTemplateFile($importFile[1]);
				$template = tx_ms3commerce_TplUtils::substituteMarker($template, $importFile[0], $content);
			}
		}
		
		self::$s_filecache[$file] = $template;
		return $template;
	}
	
	/**
	 * Initializes the parameters passed with the GET method.
	 */
	function setGetVars(tx_ms3commerce_template $template, tx_ms3commerce_db $db)
	{
		$template->itemStart = max(1, intval( NVL($_GET[tx_ms3commerce_constants::QUERY_PARAM_ITEMSTART], 1) ));
		$this->typoLanId = intval( NVL($_GET[tx_ms3commerce_constants::QUERY_PARAM_TLANID], 0) );
		
		// Maybe set via context id
		if ( $template->rootMenuId == 0 ) {
			$row = $template->dbutils->selectMenu_SingleRow('Id', "ContextId = '{$this->conf['root_menu_id']}'");
			if ( $row ) {
				$template->rootMenuId = $row[0];
			}
		}
		
		// Check for a mapped ID
		$hasMappedId = false;
		if ( is_array($_GET[tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY]) )
		{
			if ( isset($_GET[tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY][tx_ms3commerce_constants::QUERY_PARAM_TX_CONTEXTID]) 
					&& !empty($_GET[tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY][tx_ms3commerce_constants::QUERY_PARAM_TX_CONTEXTID]))
			{
				$mapId = $_GET[tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY][tx_ms3commerce_constants::QUERY_PARAM_TX_CONTEXTID];
				//get menuId from Menu for this mapId
				$row = $template->dbutils->selectMenu_SingleRow('`Id`'/*,`GroupId`,`ProductId`'*/, "ContextID = '$mapId'");
				if ( $row )
				{
					$template->currentMenuId = intval($row[0]);
					$hasMappedId = true;
				}
				else
				{
					$row = $template->dbutils->selectDocument_singleRow('`Id`', "ContextID = '$mapId'");
					if ( $row )
					{
						//Loses Dokument, ist nicht im Menü
						$template->documentId = intval($row->Id);
						$template->currentMenuId = $template->rootMenuId;
						return;
					}
				}
			}
		}
		
		// Try to find menu id
		if ( !$hasMappedId )
		{
			$template->productId = intval( NVL($_GET[tx_ms3commerce_constants::QUERY_PARAM_PID], 0) );
			$template->documentId = intval( NVL($_GET[tx_ms3commerce_constants::QUERY_PARAM_DID], 0) );
			$template->productGroupId = intval( NVL($_GET[tx_ms3commerce_constants::QUERY_PARAM_GID], 0) );
			$template->currentMenuId = intval( NVL($_GET[tx_ms3commerce_constants::QUERY_PARAM_MID], 0) );
		
			// Since not all parameters are required, some of them may be missing. If this
			// happens we will try to "find" the parameters by using the other ones 
			// (the ones which were provided).
			if ($template->currentMenuId == 0)
			{
				// The current menu was not specified, we will try to get it using the
				// other parameters.
				if ($template->productId > 0)
				{
					$row = $template->dbutils->selectMenu_SingleRow('`Id`', "`ProductId` = " . $template->productId);
					if ($row)
						$template->currentMenuId = $row[0];
				}
				else if ($template->documentId > 0)
				{
					$row = $template->dbutils->selectMenu_SingleRow('`Id`', "`DocumentId` = " . $template->documentId);
					if ($row)
						$template->currentMenuId = $row[0];
					else
						// Loose document. Don't update menu id
						$template->currentMenuId = 0;
				}
				else if ($template->productGroupId > 0)
				{
					$row = $template->dbutils->selectMenu_SingleRow('`Id`', "`GroupId` = " . $template->productGroupId);
					if ($row)
						$template->currentMenuId = $row[0];
				}
				else if (array_key_exists('start_menu_id', $template->conf))
				{
					// Last resort: Configured Override ID
					$mapId = $template->conf['start_menu_id'];
					$row = $template->dbutils->selectMenu_SingleRow('`Id`', "ContextID = '$mapId'");
					if ( $row )
					{
						$template->currentMenuId = intval($row[0]);
					} else {
						$template->currentMenuId = $template->rootMenuId;
					}
				}
				else
				{
					$template->currentMenuId = $template->rootMenuId;
				}
			}
		}
		
		// check if it's a foreign shopid and change the currentmenuid 
		$this->adjustMenuForeignShop($template);
		
		if ($template->currentMenuId > 0)
		{
			$this->setIdsFromMenuId($template);
		}
	}
	
	function setIdsFromMenuId($template) {
		$template->productId =0;
		$template->documentId =0;
		$template->productGroupId =0;
		// Got a Menu Id, check for Group/Product
		$row = $template->dbutils->selectMenu_SingleRow('`GroupId`,`ParentId`,`ProductId`,`DocumentId` ', '`Id`=' . $template->currentMenuId, FALSE);
		
		if ($row)
		{
			if ($row[0]) {
				// It is a group
				$template->productGroupId = intval($row[0]);
			} else {
				// It is a product or document, find parent-group
				$template->productId = intval($row[2]);
				$template->documentId = intval($row[3]);
				$row = $template->dbutils->selectMenu_SingleRow('`GroupId`', '`Id`=' . intval($row[1]), FALSE);
				if ($row)
					$template->productGroupId = intval($row[0]);
			}
		}
		
	}
	
	function adjustMenuForeignShop($template){
		$pathSeg = tx_ms3commerce_realurl::$nonMappedPathSegement;
		if (empty($pathSeg)) {
			/*
			 * This will never give any result... $menuId doesn't exist...
			$row = $template->dbutils->selectMenu_SingleRow('`ContextID`', "Id = '$menuId'");
			$contextId=$row[0];
			*/
			return;
		} else {
			$contextId = $template->dbutils->getContextIdByPathSeg($pathSeg);
		}
		
		if ($contextId) {
			$sollShopId=$template->dbutils->getShopId();
			//shopid position in contextid string
			$pos = strrpos($contextId,':')+1;
			$isShopId=  substr($contextId,$pos);
			if ($sollShopId != $isShopId) {
				//replace the shopid from the config (marketId + languageId)
				$sollContextId= substr($contextId,0,$pos).$sollShopId;
				// get the new menuId and replace it in Template
				$row = $template->dbutils->selectMenu_SingleRow('`Id`', "ContextID ='". $sollContextId."'");
				if ( $row )
				{
					$template->currentMenuId = intval($row[0]);
				}
			}			
		}
	}
	
	/**
	 * Initializes the parameters passed using the $conf method.
	 * 
	 * @param tx_ms3commerce_template $template
	 * @param tx_ms3commerce_db $db
	 */
	function setConfVars(tx_ms3commerce_template $template, tx_ms3commerce_db $db)
	{
		$template->itemsPerPage = intval( NVL($this->conf['items_per_page'], 0) );
 		//$template->rootMenuId = is_numeric($this->conf['root_menu_id']) ? intval( $this->conf['root_menu_id'] ) : 0;
		//$template->marketId = intval( NVL($this->conf['market_id'], 0) );
		//$template->languageId = intval( NVL($this->conf['language_id'], 1) );
		$template->skipMenuLevels = intval( NVL($this->conf['menu_skip_count'], 0) );
		$this->template->init();

		// Fix searchmenus
		$searchMenuIds = NVL( $this->conf['search_menu_ids'], null );
		if ($searchMenuIds != null) {
			$menuFixed = array();
			$searchMenuIds = explode(',', $searchMenuIds);
			foreach ($searchMenuIds as $m) {
				if($m == 'current'){
					$menuFixed[] = $template->currentMenuId;				
				}else if (!is_numeric($m)) {
					// It's probabely a context-id, resolve to menu-id
					$row = $template->dbutils->selectMenu_SingleRow("Id", "ContextId = ".$template->db->sql_escape($m));
					$menuFixed[] = $row[0];
				} else {
					$menuFixed[] = $m;
				}
			}
			$template->searchMenuIds = $menuFixed;
		} else {
			$template->searchMenuIds = array($template->rootMenuId);
		}
		
		$this->setCommonConfVars($template,$this->conf);
		$this->customConf = $this->extractCustomConfVars($this->conf);
	}

	/**
	 * Initializes the menu parameters passed using the $conf method.
	 * @param array $conf 
	 */
	function setMenuConfVars($conf)
	{
		$template = $this->template;
		$template->showProducts = intval( NVL($conf['show_products'], 0) ) != 0;
		$template->showDocuments = intval( NVL($conf['show_documents'], 0) ) != 0;
		$template->lastVisibleLevel = intval( NVL($conf['last_visible_level'], 0) );
		//$template->rootMenuId = intval( NVL($conf['root_menu_id'], 0) );
	}

	/**
	 * Read and set fullsearch_feature_name
	 */
	/*
	 *UNUSED
	function setFullSearchFeatureId()
	{
		if (!array_key_exists('fullsearch_feature_name', $this->conf))
			return;

		$featureId = $this->getFeatureIdByName($this->conf['fullsearch_feature_name']);
		if (!$featureId)
			return;

		$this->FullSearchFeatureId = $featureId;
		return;
	}
	*/
	/* ---------------------------------------------------------------------- */
	
	
	function substituteMarker($content, $marker, $markContent)
	{
		return tx_ms3commerce_TplUtils::substituteMarker($content, $marker, $markContent);
	}
	
	/**
	 * redirect content to a Typo3-page with resultsPageId
	 */
	function pageRedirect($pid, $params = array(), $force = true, $enableCache = true)
	{
		$this->pageRedirectLink( $this->getPageLink( $pid, $params ), $force, $enableCache );
	}
	
	public function pageRedirectLink( $link, $force = true )
	{
		tx_ms3commerce_plugin_sessionUtils::pageRedirectLink( $link, $force );
	}
	
	function getPageLink( $pid, $params = array(), $enableCache = true)
	{
		return $this->linker->pi_getPageLink($pid, '', $params, $enableCache ); 
	}
	
	function getCurrentPID()
	{
		return $GLOBALS['TSFE']->id;
	}
	
	/**
	 * Returns the HREF for a product.
	 * @param int $productId
	 * @param int $menuId
	 * @return string Link (document and query string).
	 */
	function getProductLink($productId, $menuId = 0, $pid = 0)
	{
		$this->timeTrackStart("getProductLink");
		$ret = $this->linker->getProductLink($productId, $menuId, $pid);
		$this->timeTrackStop();
		return $ret;
	}
	
	/**
	 * Returns the HREF for a document.
	 * @param int $documentId
	 * @param int $menuId
	 * @return string Link (document and query string).
	 */
	function getDocumentLink($documentId, $menuId = 0, $pid = 0, $download = false)
	{
		$this->timeTrackStart("getDocumentLink");
		$ret = $this->linker->getDocumentLink($documentId, $menuId, $pid, $download);
		$this->timeTrackStop();
		return $ret;
	}

	
	
	/**
	 * Set common configuration variables.
	 * @param type $conf 
	 */
	function setCommonConfVars(tx_ms3commerce_template $template, &$conf)
	{
		$template->detailPageId = intval( NVL($conf['detail_pid'], 1) );
		$template->listPageId = intval( NVL($conf['list_pid'], 1) );
		$template->noResultsPageId = intval( NVL($conf['no_results_pid'], 0) );
		$template->ResultsPageId = intval( NVL($conf['results_pid'], 0) );
		$template->productTitle = NVL( $conf['product_title'], null );
		$template->menuDisplayRoleFeatureId = $template->dbutils->getFeatureIdByName( NVL($conf['display_role_feature_name'], "") );
		$template->titleSMName = NVL( $conf['title_feature_name'], null );
		$template->breadCrumbConf = $conf['breadcrumb.'];
		
		if (($template->menuDisplayRoleFeatureId == 0) && (array_key_exists('display_role_feature_id', $conf)))
		{
			$template->menuDisplayRoleFeatureId = intval($conf['display_role_feature_id']);
		}

		if (array_key_exists('page_role_feature_name', $conf))
		{
			$template->pageRoleFeatureId = $template->dbutils->getFeatureIdByName($conf['page_role_feature_name']);
		}
		$template->pageLinkAddParameters = NVL($conf['page_role_add_parameters'], FALSE);

		if (($template->pageRoleFeatureId == 0) && (array_key_exists('page_role_feature_id', $conf)))
		{
			$template->pageRoleFeatureId = intval($conf['page_role_feature_id']);
		}

		if (array_key_exists('no_select_feature_name', $conf))
		{
			$template->noSelectFeatureId = $template->dbutils->getFeatureIdByName($conf['no_select_feature_name']);
		}
		if (array_key_exists('empty_select_feature_name', $conf))
		{
			$template->emptySelectFeatureId = $template->dbutils->getFeatureIdByName($conf['empty_select_feature_name']);
		}
		
		if (array_key_exists('restriction_feature_name', $conf))
		{
			$template->restrictionFeatureId = $template->dbutils->getFeatureIdByName($conf['restriction_feature_name']);
		}
		if(array_key_exists('restriction_feature_values',$conf))
		{
			$template->restrictionValues = explode(';', $conf['restriction_feature_values']);
		}

		if(array_key_exists('user_rights_feature_name',$conf))
		{
			$template->userRightsFeatureId = $template->dbutils->getFeatureIdByName($conf['user_rights_feature_name']);
		}	
		
		$template->userRightsValues = $this->getUserRights();
		
		if(array_key_exists('default_image_scale_path_depth', $conf) ) {
			$template->scaleImageDeph=$this->conf['default_image_scale_path_depth'];
			
		}
		$template->mS3CFunctionParams['scaleimg'] = '';
		$sep = '';
		if (array_key_exists('default_image_scale_size', $conf)) {
			preg_match('/(\d+)x(\d+)/', $conf['default_image_scale_size'], $s);
			$template->mS3CFunctionParams['scaleimg'] .= "w=$s[1],h=$s[2]";
			$sep = ',';
		}
		
		if ( array_key_exists('default_image_scale_path_pattern', $conf) ) {
			$template->mS3CFunctionParams['scaleimg'] .= $sep . 'p='.$this->conf['default_image_scale_path_pattern'];
			$sep = ',';
		}
		
		if ( array_key_exists('default_image_scale_file_type', $conf) ) {
			$template->mS3CFunctionParams['scaleimg'] .= $sep . 'e='.$this->conf['default_image_scale_file_type'];
			$sep = ',';
		}
		
		if ( array_key_exists('default_image_scale_parameters', $conf) ) {
			$template->mS3CFunctionParams['scaleimg'] .= $sep.$this->conf['default_image_scale_parameters'];
			$sep = ',';
		}
		
		if ( array_key_exists('scale_image_file_types', $conf) ) {
			$types = $conf['scale_image_file_types'];
			$conf['SCALEIMG_EXTENSIONS'] = array();
			foreach (explode(',',$types) as $type) {
				$conf['SCALEIMG_EXTENSIONS'][] = strtolower($type);
			}
		}
	}
	
	function extractCustomConfVars( &$conf )
	{
		if ( array_key_exists( 'custom.', $conf ) )
		{
			$custConf = $conf['custom.'];
		}
		return $custConf;
	}

	function checkGeneratedFileNeedsUpdate( $src, $dest )
	{
		$update = false;
		if(false == file_exists(self::pathSite() . $dest))  {
			$update = true;
		} else {
			if(filemtime(self::pathSite() . $dest) < filemtime(self::pathSite() . $src))
				$update = true;
		}
		
		return $update;
	}
	
	public function setPageTitle( $title )
	{
		$GLOBALS['TSFE']->config['config']['noPageTitle'] = 0;
		$GLOBALS['TSFE']->page['title'] = $title;
		$GLOBALS['TSFE']->indexedDocTitle = $title;
	}
	
	function placeGeneratedFile( $src, $dest, $copy )
	{
		// Create dirs and move/copy file from temp to dest
		$paths = dirname($dest);
		TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep(self::pathSite(), $paths);

		if ($copy) {
			$ok = copy(self::pathSite() . $src, self::pathSite() . $dest);
		} else {
			$ok = rename(self::pathSite() . $src, self::pathSite() . $dest);
		}
		
		return $ok;
	}
	
	/* ---------------------------------------------------------------------- */
	function generatePicture( $source, $dest, $width, $height, $isTemp )
	{
		if ($this->gr == null) {
			$this->gr = new TYPO3\CMS\Core\Imaging\GraphicalFunctions;
			$this->gr->init();
		}
		
		$sourceFull = self::pathSite() . $source;
		$destFull = self::pathSite() . $dest;
		
		// Check if picture $source date is new than $dest date
		
		if($this->checkGeneratedFileNeedsUpdate($source, $dest)) {
			$ext = pathinfo($source, PATHINFO_EXTENSION);
			if ($width > 0) {
				$widthm = $width.'m';
			} else {
				$widthm = '';
			}
			if ($height > 0) {
				$heightm = $height.'m';
			} else {
				$heightm = '';
			}
			$res = $this->gr->imageMagickConvert($sourceFull,$ext,$widthm,$heightm);
			
			if ($res)
			{
				// Usually temporary files are generated, returned in $res[3].
				// But if source has correct dimensions, no new file is created.
				// In that case we have to copy the originial, not move the temp!
				$tmpFile = $res[3];
				if (strstr($tmpFile, self::pathSite())) {
					$tmpFile = substr($tmpFile, strlen(self::pathSite()));
				}
				if ($tmpFile == $source) {
					$needCopy = true;
				} else {
					$needCopy = false;
				}
				
				// If we should build a temporary file (e.g. no unique file
				// name from prod/group known), do nothing with the returned
				// file. Original files are ok, and temporary files will be
				// automatically removed by Typo3 when no longer needed
				if ($isTemp) {
					$ok = false;
				} else {
					$ok = $this->placeGeneratedFile($tmpFile, $dest, $needCopy);
				}
				
				// Couldn't move, return temporary name
				if (!$ok) {
					$dest = $tmpFile;
				}

				return array(
					'dest' => $dest,
					'width' => $res[0],
					'height' => $res[1],
					'updated' => true
				);
			} else {
				// Could not create scaled image, return the to-be-generated file name
				// (this will result in an missing-image, but the generated HTML will
				// have the correct resources).
				return array(
					'dest' => $dest,
					'width' => $width,
					'height' => $height,
					'updated' => false
					);
			}
		} else {
			// No update needed
			$res = $this->gr->getImageDimensions($destFull);
			return array(
				'dest' => $dest,
				'width' => $res[0],
				'height' => $res[1],
				'updated' => false
			);
		}
		
	}

	/**
	 * Returns the HREF for a group.
	 * @param int $groupId GROUP id
	 * @param int $itemStart item number
	 * @param int $menuId The identifier of the menu row associated with the specified group.
	 * @param int $pid    The identifier of the target page. If 0, the default page identifier
	 *               is used.
	 */
	function getGroupLink($groupId, $menuId = 0, $pid = 0, $itemStart = 0)
	{
		$this->timeTrackStart("getGroupLink");
		$ret = $this->linker->getGroupLink($groupId, $menuId, $pid, $itemStart);
		$this->timeTrackStop();
		return $ret;
	}

	function fileResource( $path )
	{
		if (MS3C_TYPO3_RELEASE == '9') {
			$path = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Resource\FilePathSanitizer::class)->sanitize($path);
			return file_get_contents($path);
		} else {
			return $this->cObj->fileResource( $path );
		}
	}
	
	function getUserRights()
	{
		$urights=array();
		$grights=array();

		if ($this->isUserLoggedIn()) {
			$uid=$GLOBALS['TSFE']->fe_user->user['uid']; 
			$gruid=$GLOBALS['TSFE']->fe_user->groupData['uid'];
			$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
			foreach($gruid as $id){
				$res = $t3db->exec_SELECTquery('ms3commerce_group_rights', 'fe_groups', 'uid='.$id);			 
				if ($res)
				{
					while ($row = $t3db->sql_fetch_row($res)) {							
						$gr=$row[0];
						if($gr!=''){
							$grights=array_merge($grights,explode(";",$row[0]));
						}
					}
				}		
			}
			$res=$GLOBALS['TSFE']->fe_user->user['ms3commerce_user_rights'];
			$urights=explode(";",$res);
			
			$rightsArray=array_merge($urights,$grights);
		} else {
			$rightsArray = explode(';', $this->conf['no_user_rights']);
		}

		$rightsArray = array_filter($rightsArray, "removeEmpty");
		return $rightsArray;	
	}

	public function isUserLoggedIn()
	{
		return tx_ms3commerce_plugin_sessionUtils::isFeUserLoggedIn();
	}

	public function page404Error( $msg )
	{
		$GLOBALS['TSFE']->pageNotFoundAndExit( $msg );
	}
	
	public function pageUnavailableError( $msg )
	{
		$GLOBALS['TSFE']->pageUnavailableAndExit( $msg );
	}
	
	/**
	 *@abstract Automatic login user
	 * @param type $user
	 * @param type $password
	 * @param type $logout (true if user has to be logged off previously)
	 * @return boolean 
	 */
	public function loginUser($user,$password,$logout) {
		return tx_ms3commerce_plugin_sessionUtils::loginUser($user, $password, $logout);
	}
	
	public function logoutUser() {
		return tx_ms3commerce_plugin_sessionUtils::logoutUser();
	}
	
	public function getUserId() {
		return tx_ms3commerce_plugin_sessionUtils::getUserId();
	}
	
	private function makeCustomInstance($db) {
		$custom = tx_ms3commerce_pi1::makeObjectInstance('tx_ms3commerce_custom');
		$custom->setup($db, $this, $this->template, $this->conf, $this->customConf);
		return $custom;
	}
	
	public static function makeObjectInstance($cls) {
            $obj = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($cls);
            return $obj;
	}
	
	public function timeTrackStart($key) {
		$this->timetracker->timeTrackStart($key);
	}
	public function timeTrackStop() {
		$this->timetracker->timeTrackStop();
	}
	public function timeTrackPrint() {
		$this->timetracker->timeTrackPrint();
	}

	public static function pathSite() {
		if (MS3C_TYPO3_RELEASE == '9') {
			return \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
		} else {
			return PATH_site;
		}	
	}
}
	/*
 * Taken from http://www.php.net/manual/en/normalizer.normalize.php
 * UNUSED
 */
function normalizeUtf8String( $orig)
{  
	$s = $orig;
    // maps German (umlauts) and other European characters onto two characters before just removing diacritics
    $s    = preg_replace( '@\x{00c4}@u'    , "AE",    $s );    // umlaut Ä => AE
    $s    = preg_replace( '@\x{00d6}@u'    , "OE",    $s );    // umlaut Ö => OE
    $s    = preg_replace( '@\x{00dc}@u'    , "UE",    $s );    // umlaut Ü => UE
    $s    = preg_replace( '@\x{00e4}@u'    , "ae",    $s );    // umlaut ä => ae
    $s    = preg_replace( '@\x{00f6}@u'    , "oe",    $s );    // umlaut ö => oe
    $s    = preg_replace( '@\x{00fc}@u'    , "ue",    $s );    // umlaut ü => ue
    $s    = preg_replace( '@\x{00f1}@u'    , "ny",    $s );    // ñ => ny
    $s    = preg_replace( '@\x{00ff}@u'    , "yu",    $s );    // ÿ => yu
   
   // Check if Normalizer-class missing!
    if ( class_exists("Normalizer", $autoload = false))
    {
		// maps special characters (characters with diacritics) on their base-character followed by the diacritical mark
			// exmaple:  Ú => U´,  á => a`
		$s    = Normalizer::normalize( $s, Normalizer::FORM_D );


		$s    = preg_replace( '@\pM@u'        , "",    $s );    // removes diacritics
	}
   
    $s    = preg_replace( '@\x{00df}@u'    , "ss",    $s );    // maps German ß onto ss
    $s    = preg_replace( '@\x{00c6}@u'    , "AE",    $s );    // Æ => AE
    $s    = preg_replace( '@\x{00e6}@u'    , "ae",    $s );    // æ => ae
    $s    = preg_replace( '@\x{0132}@u'    , "IJ",    $s );    // ? => IJ
    $s    = preg_replace( '@\x{0133}@u'    , "ij",    $s );    // ? => ij
    $s    = preg_replace( '@\x{0152}@u'    , "OE",    $s );    // Œ => OE
    $s    = preg_replace( '@\x{0153}@u'    , "oe",    $s );    // œ => oe
   
    $s    = preg_replace( '@\x{00d0}@u'    , "D",    $s );    // �? => D
    $s    = preg_replace( '@\x{0110}@u'    , "D",    $s );    // �? => D
    $s    = preg_replace( '@\x{00f0}@u'    , "d",    $s );    // ð => d
    $s    = preg_replace( '@\x{0111}@u'    , "d",    $s );    // d => d
    $s    = preg_replace( '@\x{0126}@u'    , "H",    $s );    // H => H
    $s    = preg_replace( '@\x{0127}@u'    , "h",    $s );    // h => h
    $s    = preg_replace( '@\x{0131}@u'    , "i",    $s );    // i => i
    $s    = preg_replace( '@\x{0138}@u'    , "k",    $s );    // ? => k
    $s    = preg_replace( '@\x{013f}@u'    , "L",    $s );    // ? => L
    $s    = preg_replace( '@\x{0141}@u'    , "L",    $s );    // L => L
    $s    = preg_replace( '@\x{0140}@u'    , "l",    $s );    // ? => l
    $s    = preg_replace( '@\x{0142}@u'    , "l",    $s );    // l => l
    $s    = preg_replace( '@\x{014a}@u'    , "N",    $s );    // ? => N
    $s    = preg_replace( '@\x{0149}@u'    , "n",    $s );    // ? => n
    $s    = preg_replace( '@\x{014b}@u'    , "n",    $s );    // ? => n
    $s    = preg_replace( '@\x{00d8}@u'    , "O",    $s );    // Ø => O
    $s    = preg_replace( '@\x{00f8}@u'    , "o",    $s );    // ø => o
    $s    = preg_replace( '@\x{017f}@u'    , "s",    $s );    // ? => s
    $s    = preg_replace( '@\x{00de}@u'    , "T",    $s );    // Þ => T
    $s    = preg_replace( '@\x{0166}@u'    , "T",    $s );    // T => T
    $s    = preg_replace( '@\x{00fe}@u'    , "t",    $s );    // þ => t
    $s    = preg_replace( '@\x{0167}@u'    , "t",    $s );    // t => t
   
    // remove all non-ASCii characters
    $s    = preg_replace( '@[^\0-\x80]@u'    , "",    $s );
   
     
    // possible errors in UTF8-regular-expressions
    if (empty($s))
        return $orig;
    else
        return $s;

    
}

class tx_ms3commerce_searchHandler implements itx_ms3commerce_pagetypehandler
{
	public function handlePageTypeCall(tx_ms3commerce_template $template) {
		$res=$template->ajaxbuilder->getJsonEncodeRes();
		// MUST NOT RETURN THE JSON-VALUE!
		// Typo3 will include CRLF in HTML-encoded parts (like ul/li lists)
		// This results in invalid JSON!
		// Instead, simply echo
		echo $res;
		tx_ms3commerce_plugin_sessionUtils::suppressOutput();
	}
}

class tx_ms3commerce_downloadHandler implements itx_ms3commerce_pagetypehandler
{
	public function handlePageTypeCall(tx_ms3commerce_template $template) {
		$res=$template->downloadDocument();
		// Bad hack: Typo3 will re-write the header, so that
		// the mime-type is set to text/html. Download will work, but
		// browser indicate an HTML document...
		// Instead: terminate here!
		// (404-redirect & access denied are also done by headers, so still work)
		exit;
	}
}

function removeEmpty( $val )
{
	$val = trim(strval($val));
	if (strlen($val) == 0) {
		return false;
	}
	return true;
}

?>
