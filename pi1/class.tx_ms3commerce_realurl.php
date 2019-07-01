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

require_once('class.tx_ms3commerce_db.php');
require_once('class.tx_ms3commerce_constants.php');

/**
 * RealUrl Handling methods for gernerating nice Urls
 *
 */
class tx_ms3commerce_realurl
{
	/** @var tx_ms3commerce_db */
	var $db;
	
	static $nonMappedPathSegement;
	
	public function __construct() {
		$this->db = tx_ms3commerce_db_factory::buildDatabase( true );
	}
	
	/// FORWARDERS
	public function handleRealURLCoding_Lvl0( $params, $parent )
	{
		return $this->handleRealURLCoding_Lvl(0, $params, $parent);
	}
	
	public function handleRealURLCoding_Lvl1( $params, $parent )
	{
		return $this->handleRealURLCoding_Lvl(1, $params, $parent);
	}
	
	public function handleRealURLCoding_Lvl2( $params, $parent )
	{
		return $this->handleRealURLCoding_Lvl(2, $params, $parent);
	}
	
	public function handleRealURLCoding_Lvl3( $params, $parent )
	{
		return $this->handleRealURLCoding_Lvl(3, $params, $parent);
	}
	
	public function handleRealURLCoding_Lvl4( $params, $parent )
	{
		return $this->handleRealURLCoding_Lvl(4, $params, $parent);
	}
	
	////////////////////////////////
	
	/// REAL MAPPING HANDLING
	public function handleRealURLCoding_Mapping( $params, $parent )
	{
		if ($params['decodeAlias']) {
			$this->dbgstart();
			$result = $this->realURLpagePathtoID_Mapped($params, $parent);
			$this->dbgend("decode mapped");
		} else {
			$this->dbgstart();
			$result = $this->realURLIDtoPagePath_Mapped($params, $parent);
			$this->dbgend("encode mapped");
		}
		return $result;
	}
	
	private function realURLIDtoPagePath_Mapped($params, $parent) 
	{
		// Hack: Adjust missing path segments
		while (end($params['pathParts']) === '') {
			array_pop($params['pathParts']);
		}
		
		$row = $this->selectMapSingleRow("realurl_seg_mapped", "asim_mapid = ".$this->db->sql_escape($params['value']));
		if ( $row ) {
			return $row[0];
		}
		return '';
	}
	
	private function realURLpagePathtoID_Mapped($params, $parent)
	{
		$cond = $this->getLanguageCondition($parent);
		$row = $this->selectMapSingleRow("asim_mapid", "realurl_seg_mapped = " . $this->db->sql_escape($params['value']). $cond);
		if ($row) {
			return $row[0];
		} else {
			self::$nonMappedPathSegement = $params['value'];
		}
		return '';
	}
	
	////////////////
	/// INTERMEDIATE LEVELS HANDLING
	private function handleRealURLCoding_Lvl($level, $params, $parent)
	{
		if ($params['decodeAlias']) {
			$this->dbgstart();
			$result = $this->realURLpagePathtoID($level, $params, $parent);
			$this->dbgend("decode intermediate $level");
		} else {
			$this->dbgstart();
			$result = $this->realURLIDtoPagePath($level, $params, $parent);
			$this->dbgend("encode intermediate $level");
		}
		return $result;
	}
	
	private function realURLIDtoPagePath($level, $params, $parent)
	{
		if ($params['value'] != '') {
			$row = $this->selectMapSingleRow("realurl_seg_$level", "asim_mapid_dummy_$level = {$params['value']}");
			if ( $row ) {
				return $row[0];
			}
		}
		
		return '';
	}
	
	private function realURLpagePathtoID($level, $params, $parent)
	{
		// Truncate trailing empty parts
		while (end($params['pathParts']) === '') {
			array_pop($params['pathParts']);
		}
		
		// Hack: If there is only the Mapping-part left, re-evalute it
		if (count($params['pathParts']) == 0) {
			array_push($params['pathParts'], $params['value']);
		}
		
		// Get real mapping, and select our dummy level
		$realMapped = $this->db->sql_escape(end($params['pathParts']));
		$cond = $this->getLanguageCondition($parent);
		$row = $this->selectMapSingleRow("asim_mapid_dummy_$level", "realurl_seg_mapped = $realMapped $cond");
		if ($row) {
			return $row[0];
		}
		return '';
	}
	
	private function getLanguageCondition($parent)
	{
		$and = '';
		if (method_exists($parent, 'getDetectedLanguage')) {
			$langId = intval($parent->getDetectedLanguage());	
		} else {
			$langId = $this->getDetectedLanguageHack($parent);
		}
		
		//if a custom sys language maping exists 
		if (function_exists('mS3C_GetSysLangShopMap')) {
			$MS3C_SYSLANG_SHOP_MAP = mS3C_GetSysLangShopMap();	
		} else {
			$MS3C_SYSLANG_SHOP_MAP = $GLOBALS['MS3C_SYSLANG_SHOP_MAP'];
		}
		
		switch (MS3C_REALURL_SHOP_CHECK_TYPE)
		{
		case 'ShopId':
			if (array_key_exists($langId, $MS3C_SYSLANG_SHOP_MAP)) {
				$shopId = $MS3C_SYSLANG_SHOP_MAP[$langId];
				$and = " AND ShopId = $shopId";
			}
			break;
		case 'ContextId':
			if (array_key_exists($langId, $MS3C_SYSLANG_SHOP_MAP)) {
				$shopId = $MS3C_SYSLANG_SHOP_MAP[$langId];
				$and = " AND asim_mapid LIKE '%:$shopId'";
			}
			break;
		case 'SysLanguageUid':
			if ($langId < 0) $langId = 0;
			$and = " AND sys_language_uid = $langId";
			break;
		}
		return $and;
	}
	
	private $cachedDetectedLanguage = null;
	private function getDetectedLanguageHack($parent) {
		if (!is_null($this->cachedDetectedLanguage)) {
			return $this->cachedDetectedLanguage;
		}
		
		if (class_exists('MS3CommerceHackUrlDecoder')) {
			$x = new MS3CommerceHackUrlDecoder($parent);
			$this->cachedDetectedLanguage = $x->getDetectedLanguage();
			return $this->cachedDetectedLanguage;
		}
		
		// Normal handling
		if (is_array($parent->extConf)) {
			// Pre RealURL 1.10 ? (old version, doesn't have getDetectedLanguage, but valid config in extConf)
			$pagePath = $parent->extConf['pagePath'];
			$preVars = $parent->extConf['preVars'];
			$dirParts = $parent->dirParts;
		} else {
			// RealURL 2.X
			// parameter is DmitryDulepov\Realurl\Configuration\ConfigurationReader\ConfigurationReader::MODE_DECODE = 1
			$configuration = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Configuration\\ConfigurationReader', 1);
			$pagePath = $configuration->get('pagePath');
			$preVars = $configuration->get('preVars');
			// From RealURL's UrlDecoder
			$siteScript = TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_SCRIPT');
			$speakingUri = ltrim($siteScript, '/');
			// processing of speakingUri skipped...
			$uParts = @parse_url($speakingUri);
			$path = $uParts['path'];
			$path = trim($path, '/');
			$pathSegments = $path ? explode('/', $path) : array();
			foreach($pathSegments as $id => $value) {
				$pathSegments[$id] = urldecode($value);
			}
			$dirParts = $pathSegments;
		}
		
		// Get Language Var
		if (isset($pagePath['languageGetVar'])) {
			$languageGetVar = $pagePath['languageGetVar'];
		} else {
			$languageGetVar = 'L';
		}
		
		// Pre-Var detected language can be overridden by L-Param
		if (array_key_exists($languageGetVar, $_GET)) {
			$this->cachedDetectedLanguage = intval($_GET[$languageGetVar]);
			return $this->cachedDetectedLanguage;
		}
		
		// Get PRE var conf
		$conf = null;
		$maxIdx = -1;
		if (count($preVars)) {
			foreach ($preVars as $idx => $val) {
				if ($val['GETvar'] == $languageGetVar) {
					$conf = $val;
					$maxIdx = $idx;
					break;
				}
			}
		}
		
		$lang = -1;
		if ($conf != null) {
			// Find dir part. Go up to the Language Get Var (PRE vars can be skipped)
			for ($i = 0; $i < count($dirParts) && $i <= $maxIdx; $i++) {
				$langCode = $dirParts[$i];
				if (array_key_exists($langCode, $conf['valueMap'])) {
					$lang = intval($conf['valueMap'][$langCode]);
					break;
				}
			}
		}
		
		$this->cachedDetectedLanguage = $lang;
		return $lang;
	}
	
	private function selectMapSingleRow( $sel, $where )
	{
		$rs = $this->db->exec_SELECTquery( $sel, RealURLMap_TABLE, $where, '', '', '1' );
		if ( $rs ) {
			$row = $this->db->sql_fetch_row( $rs );
			$this->db->sql_free_result( $rs );
			return $row;
		}
		return null;
	}
	
	var $dbg_start = null;
	private function dbgstart()
	{
		if (array_key_exists('ms3debug', $_GET) && $_GET['ms3debug']) {
			if ($this->dbg_start === null) {
				$this->db_start = microtime(true);
			}
		}
	}
	
	private function dbgend($key)
	{
		if (array_key_exists('ms3debug', $_GET) && $_GET['ms3debug']) {
			if ($this->dbg_start !== null) {
				$dbg_end = microtime(true);
				$el = $dbg_end - $this->dbg_start;
				echo "<span style='display:none;'>mS3 RealURL $key Execution Time: $el</span>";
				$this->dbg_start = null;
			}
		}
	}
}

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('realurl')) {
	$path = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl').'Classes/Decoder/UrlDecoder.php';
	if (file_exists($path)) {
		require_once $path;
		class MS3CommerceHackUrlDecoder extends \DmitryDulepov\Realurl\Decoder\UrlDecoder {
			private $orig;
			function __construct($origDecoder) {
				$this->orig = $origDecoder;
			}
			public function getDetectedLanguage() {
				return $this->orig->detectedLanguageId;
			}
		}
	}
	unset($path);
}

class tx_ms3commerce_realurl_simple extends tx_ms3commerce_realurl
{
	/** @var tx_ms3commerce_db */
	var $db;
	/** @var tx_ms3commerce_db */
	var $t3db;
	var $namesegs;
	var $overlaynames;
	var $onlyPageNames;
	var $ruConf;
	var $isRealUrlActive;
	
	public function __construct() {
		$this->db = tx_ms3commerce_db_factory::buildDatabase( true );
		$this->t3db = tx_ms3commerce_db_factory_cms::getT3Database();
		$this->initT3();
		
		if ($this->isRealUrlActive) {
			// Compability >= 6.2
			if (!defined('TX_REALURL_SEGTITLEFIELDLIST_DEFAULT')) {
				define('TX_REALURL_SEGTITLEFIELDLIST_DEFAULT', 'tx_realurl_pathsegment,alias,nav_title,title,uid');
			}
			// from RealURL config (< 6.2)
			$segs = $this->ruConf['pagePath']['segTitleFieldList'] ? $this->ruConf['pagePath']['segTitleFieldList'] : TX_REALURL_SEGTITLEFIELDLIST_DEFAULT;
			$this->namesegs = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $segs, 1);
			// Remove uid from list
			$this->namesegs = array_diff($this->namesegs, array('uid'));
			$this->overlaynames = explode(',','tx_realurl_pathsegment,nav_title,title');
			$this->onlyPageNames = array_diff($this->namesegs, $this->overlaynames);
		}
	}
	
	public function buildLink($pid, $params, $lang = 0)
	{
		if (!$this->isRealUrlActive) {
			return "?id=$pid&L=$lang".$params;
		}
		if (!is_array($params)) {
			$params = TYPO3\CMS\Core\Utility\GeneralUtility::explodeUrl2Array($params, true);
		}
		
		// Extract L and convert to prevar
		$pre = '';
		if (array_key_exists('L', $params)) {
			$lang = $params['L'];
		}
		$pre = $this->getLPreVar($lang);
		if ($pre) {
			unset($params['L']);
		}
		
		// Get path to page
		$path = $this->buildPagePath($pid, $lang);
		if (is_null($path)) {
			return null;
		}
		
		// extract mapid
		$mapId = null;
		$dummySelect = array();
		if (array_key_exists(tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY, $params)) {
			$mapArray = $params[tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY];
			if (array_key_exists(tx_ms3commerce_constants::QUERY_PARAM_TX_CONTEXTID, $mapArray)) {
				$mapId = $mapArray[tx_ms3commerce_constants::QUERY_PARAM_TX_CONTEXTID];
			}
		
			// get select for dummies
			for ($i = 0;; $i++) {
				$key = tx_ms3commerce_constants::QUERY_PARAM_TX_DUMMYID.$i;
				if (array_key_exists($key, $mapArray)) {
					$dummySelect[] = 'realurl_seg_'.$i;
				} else {
					break;
				}
			}
			
			unset($params[tx_ms3commerce_constants::QUERY_PARAM_TX_ARRAY]);
		}
		
		$dummySelect[] = 'realurl_seg_mapped';
		$mapPath = '';
		
		// Now we have only left additional parameters
		// Query mapping
		if ($mapId) {
			$select = join(',', $dummySelect);
			$where = "asim_mapid ='$mapId'";
			$rs = $this->db->exec_SELECTquery($select, RealURLMap_TABLE, $where);
			$row = $this->db->sql_fetch_row($rs);
			$this->db->sql_free_result($rs);
			if ($row) {
				$row = array_filter($row);
				$mapPath = '/'.join('/',$row).'/';
			}
		}
		
		// Build additional params
		$addParamsArr = array();
		foreach ($params as $k => $v) {
			$addParamsArr[] = "$k=$v";
		}
		if (!empty($addParamsArr)) {
			$addParams = '?'.join('&', $addParamsArr);
		} else {
			$addParams = '';
		}
		
		// assemble link
		$link = $pre.$path.$mapPath.$addParams;
		return $link;
	}
	
	private function getLPreVar($lang, $lvar = 'L')
	{
		if (array_key_exists('preVars', $this->ruConf)) {
			foreach ($this->ruConf['preVars'] as $pre) {
				if ($pre['GETvar'] == $lvar) {
					$key = array_keys($pre['valueMap'], $lang);
					if (count($key) > 0) {
						return $key[0].'/';
					}
					// Right PreVar, but no mapping => return default
					if ($pre['noMatch'] != 'bypass') {
						if (array_key_exists('valueDefault', $pre)) {
							return $pre['valueDefault'];
						}
					}
					return null;
				}
			}
		}
		// No L-Mapping
		return null;
	}
	
	private function buildPagePath($pid, $lang)
	{
		// If source page is deleted, do nothing
		$pagerec = $this->getPage($pid);
		if (!$pagerec || $pagerec['deleted']) {
			return null;
		}
		
		if ($pagerec['tx_realurl_pathoverride']) {
			// Use override instead of built path
			$path = $pagerec['tx_realurl_pathsegment'];
		} else {
			
			// Build root line
			$stack = array();
			$first = true;
		
			do {
				if (!$pagerec['tx_realurl_exclude'] || $first) {
					// don't exclude the first page!
					if ($pagerec['is_siteroot']) {
						break;
					} else if ($pagerec['deleted']) {
						return null;
					}
					
					// Find name for page and add to stack
					$curName = null;
					foreach ($this->namesegs as $n) {
						$curName = $pagerec[$n];
						if (!empty($curName)) {
							break;
						}
					}
					// Fall back to uid
					if (empty($curName)) {
						$curName = $pagerec['uid'];
					}
					
					// Encode and add to path stack
					$curName = $this->encodeTitle($curName);
					array_unshift($stack, $curName);
				}
				
				// goto parent page in next iteration
				$pid = $pagerec['pid'];
				$first = false;

				$pagerec = $this->getPage($pid, $lang);
			} while ($pagerec);
			
			$path = join('/', $stack);
		}
		
		return $path;
	}
	
	private function getPage($pid, $lang = 0)
	{
		// Get language overlay names if present
		$names = array();
		foreach ($this->overlaynames as $n) {
			$names[] = "COALESCE(l.$n,p.$n) AS $n";
		}
		
		// Add names that are only in the page (not in overlay)
		$names = array_merge($names, $this->onlyPageNames);
		$names = join(',',$names);
		
		$sql =
			"SELECT p.uid, p.pid, p.deleted, p.is_siteroot, p.tx_realurl_pathoverride, p.tx_realurl_exclude, $names ".
			'FROM pages p '.
			"LEFT JOIN pages_language_overlay l ON l.pid = p.uid AND l.sys_language_uid = $lang ".
			"WHERE p.uid = $pid";
		
		
		$rs = $this->t3db->sql_query($sql);
		$row = $this->t3db->sql_fetch_assoc($rs);
		$this->t3db->sql_free_result($rs);
		return $row;
	}
	
	/** @var \TYPO3\CMS\Core\Charset\CharsetConverter */
	static $csConvObj;
	private function initT3() {
		if (is_null(self::$csConvObj)) {
			self::$csConvObj = new TYPO3\CMS\Core\Charset\CharsetConverter();
		}
		
		if (!tx_ms3commerce_t3minibootstrap::loadExtensionConfig('realurl')) {
			// RealURL not active, abort
			$this->isRealUrlActive = false;
			return;
		}
		$this->isRealUrlActive = true;
		// Assume _DEFAULT
		$this->ruConf = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT'];
	}
	
	private function encodeTitle($title) {
		// COPIED FROM REALURL, WITH MODIFICATION (DON'T DEPEND ON GLOBALS,
		// MAKE ASSUMPTIONS WHERE NEEDED (e.g. utf-8)).
		
		// Fetch character set:
		//$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;
		$charset = 'utf-8';

		// Convert to lowercase:
		//$processedTitle = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $title, 'toLower');
		$processedTitle = self::$csConvObj->conv_case($charset, $title, 'toLower');

		// Strip tags
		$processedTitle = strip_tags($processedTitle);

		// Convert some special tokens to the space character
		//$space = isset($this->conf['spaceCharacter']) ? $this->conf['spaceCharacter'] : '_';
		$space = isset($this->ruConf['pagePath']['spaceCharacter']) ? $this->ruConf['pagePath']['spaceCharacter'] : '_';
		$processedTitle = preg_replace('/[ \-+_]+/', $space, $processedTitle); // convert spaces

		// Convert extended letters to ascii equivalents
		//$processedTitle = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($charset, $processedTitle);
		$processedTitle = self::$csConvObj->specCharsToASCII($charset, $processedTitle);

		// Strip the rest
		//if ($this->extConf['init']['enableAllUnicodeLetters']) {
		if ($this->ruConf['init']['enableAllUnicodeLetters']) {
			// Warning: slow!!!
			$processedTitle = preg_replace('/[^\p{L}0-9' . ($space ? preg_quote($space) : '') . ']/u', '', $processedTitle);
		}
		else {
			$processedTitle = preg_replace('/[^a-zA-Z0-9' . ($space ? preg_quote($space) : '') . ']/', '', $processedTitle);
		}
		$processedTitle = preg_replace('/\\' . $space . '{2,}/', $space, $processedTitle); // Convert multiple 'spaces' to a single one
		$processedTitle = trim($processedTitle, $space);

		/*
		if ($this->conf['encodeTitle_userProc']) {
			$encodingConfiguration = array('strtolower' => true, 'spaceCharacter' => $this->conf['spaceCharacter']);
			$params = array('pObj' => &$this, 'title' => $title, 'processedTitle' => $processedTitle, 'encodingConfiguration' => $encodingConfiguration);
			$processedTitle = TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($this->conf['encodeTitle_userProc'], $params, $this);
		}
		*/
		// Return encoded URL:
		return rawurlencode(strtolower($processedTitle));
	}
	
}

?>
