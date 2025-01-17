<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2010 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * This script contains the parent class, 'pibase', providing an API with the most basic methods for frontend plugins
 *
 * $Id: class.tslib_pibase.php 7905 2010-06-13 14:42:33Z ohader $
 * Revised for TYPO3 3.6 June/2003 by Kasper Skaarhoj
 * XHTML compliant
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *  132: class tslib_pibase
 *
 *              SECTION: Init functions
 *  214:     function tslib_pibase()
 *  240:     function pi_setPiVarDefaults()
 *
 *              SECTION: Link functions
 *  277:     function pi_getPageLink($id,$target='',$urlParameters=array())
 *  293:     function pi_linkToPage($str,$id,$target='',$urlParameters=array())
 *  308:     function pi_linkTP($str,$urlParameters=array(),$cache=0,$altPageId=0)
 *  331:     function pi_linkTP_keepPIvars($str,$overrulePIvars=array(),$cache=0,$clearAnyway=0,$altPageId=0)
 *  355:     function pi_linkTP_keepPIvars_url($overrulePIvars=array(),$cache=0,$clearAnyway=0,$altPageId=0)
 *  373:     function pi_list_linkSingle($str,$uid,$cache=FALSE,$mergeArr=array(),$urlOnly=FALSE,$altPageId=0)
 *  401:     function pi_openAtagHrefInJSwindow($str,$winName='',$winParams='width=670,height=500,status=0,menubar=0,scrollbars=1,resizable=1')
 *
 *              SECTION: Functions for listing, browsing, searching etc.
 *  456:     function pi_list_browseresults($showResultCount=1,$tableParams='',$wrapArr=array(), $pointerName = 'pointer', $hscText = TRUE)
 *  618:     function pi_list_searchBox($tableParams='')
 *  649:     function pi_list_modeSelector($items=array(),$tableParams='')
 *  687:     function pi_list_makelist($res,$tableParams='')
 *  722:     function pi_list_row($c)
 *  734:     function pi_list_header()
 *
 *              SECTION: Stylesheet, CSS
 *  765:     function pi_getClassName($class)
 *  777:     function pi_classParam($class)
 *  791:     function pi_setClassStyle($class,$data,$selector='')
 *  802:     function pi_wrapInBaseClass($str)
 *
 *              SECTION: Frontend editing: Edit panel, edit icons
 *  858:     function pi_getEditPanel($row='',$tablename='',$label='',$conf=Array())
 *  900:     function pi_getEditIcon($content,$fields,$title='',$row='',$tablename='',$oConf=array())
 *
 *              SECTION: Localization, locallang functions
 *  947:     function pi_getLL($key,$alt='',$hsc=FALSE)
 *  970:     function pi_loadLL()
 *
 *              SECTION: Database, queries
 * 1048:     function pi_list_query($table,$count=0,$addWhere='',$mm_cat='',$groupBy='',$orderBy='',$query='',$returnQueryArray=FALSE)
 * 1140:     function pi_exec_query($table,$count=0,$addWhere='',$mm_cat='',$groupBy='',$orderBy='',$query='')
 * 1155:     function pi_getRecord($table,$uid,$checkPage=0)
 * 1166:     function pi_getPidList($pid_list,$recursive=0)
 * 1191:     function pi_prependFieldsWithTable($table,$fieldList)
 * 1211:     function pi_getCategoryTableContents($table,$pid,$whereClause='',$groupBy='',$orderBy='',$limit='')
 *
 *              SECTION: Various
 * 1255:     function pi_isOnlyFields($fList,$lowerThan=-1)
 * 1275:     function pi_autoCache($inArray)
 * 1306:     function pi_RTEcssText($str)
 *
 *              SECTION: FlexForms related functions
 * 1328:     function pi_initPIflexForm($field='pi_flexform')
 * 1346:     function pi_getFFvalue($T3FlexForm_array,$fieldName,$sheet='sDEF',$lang='lDEF',$value='vDEF')
 * 1363:     function pi_getFFvalueFromSheetArray($sheetArray,$fieldNameArr,$value)
 *
 * TOTAL FUNCTIONS: 35
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */











/**
 * Base class for frontend plugins
 * Most modern frontend plugins are extension classes of this one.
 * This class contains functions which assists these plugins in creating lists, searching, displaying menus, page-browsing (next/previous/1/2/3) and handling links.
 * Functions are all prefixed "pi_" which is reserved for this class. Those functions can of course be overridden in the extension classes (that is the point...)
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage tslib
 */
//class tslib_pibase {
//
//		// Reserved variables:
//	/**
//	 * The backReference to the mother cObj object set at call time
//	 *
//	 * @var tslib_cObj
//	 */
//	var $cObj;
//	var $prefixId;		// Should be same as classname of the plugin, used for CSS classes, variables
//	var $scriptRelPath;	// Path to the plugin class script relative to extension directory, eg. 'pi1/class.tx_newfaq_pi1.php'
//	var $extKey;		// Extension key.
//	var $piVars = Array (	// This is the incoming array by name $this->prefixId merged between POST and GET, POST taking precedence. Eg. if the class name is 'tx_myext' then the content of this array will be whatever comes into &tx_myext[...]=...
//		'pointer' => '',		// Used as a pointer for lists
//		'mode' => '',			// List mode
//		'sword' => '',			// Search word
//		'sort' => '',			// [Sorting column]:[ASC=0/DESC=1]
//	);
//	var $internal = Array(	// Used internally for general storage of values between methods
//		'res_count' => 0,		// Total query count
//		'results_at_a_time' => 20,	// pi_list_browseresults(): Show number of results at a time
//		'maxPages' => 10,		// pi_list_browseresults(): Max number of 'Page 1 - Page 2 - ...' in the list browser
//		'currentRow' => Array(),	// Current result row
//		'currentTable' => '',		// Current table
//	);
//
//	var $LOCAL_LANG = Array();		// Local Language content
//	var $LOCAL_LANG_charset = Array();	// Local Language content charset for individual labels (overriding)
//	var $LOCAL_LANG_loaded = 0;		// Flag that tells if the locallang file has been fetch (or tried to be fetched) already.
//	var $LLkey='default';			// Pointer to the language to use.
//	var $altLLkey='';			// Pointer to alternative fall-back language to use.
//	var $LLtestPrefix='';			// You can set this during development to some value that makes it easy for you to spot all labels that ARe delivered by the getLL function.
//	var $LLtestPrefixAlt='';		// Save as LLtestPrefix, but additional prefix for the alternative value in getLL() function calls
//
//	var $pi_isOnlyFields = 'mode,pointer';
//	var $pi_alwaysPrev = 0;
//	var $pi_lowerThan = 5;
//	var $pi_moreParams='';
//	var $pi_listFields='*';
//
//	var $pi_autoCacheFields=array();
//	var $pi_autoCacheEn=0;
//
//	var $pi_USER_INT_obj = FALSE;		// If set, then links are 1) not using cHash and 2) not allowing pages to be cached. (Set this for all USER_INT plugins!)
//	var $pi_checkCHash = FALSE;		// If set, then caching is disabled if piVars are incoming while no cHash was set (Set this for all USER plugins!)
//
//	/**
//	 * Should normally be set in the main function with the TypoScript content passed to the method.
//	 *
//	 * $conf[LOCAL_LANG][_key_] is reserved for Local Language overrides.
//	 * $conf[userFunc] / $conf[includeLibs]  reserved for setting up the USER / USER_INT object. See TSref
//	 */
//	var $conf = Array();
//
//	// internal, don't mess with...
//	var $pi_EPtemp_cObj;
//	var $pi_tmpPageId=0;
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Init functions
//	 *
//	 **************************/
//
//	/**
//	 * Class Constructor (true constructor)
//	 * Initializes $this->piVars if $this->prefixId is set to any value
//	 * Will also set $this->LLkey based on the config.language setting.
//	 *
//	 * @return	void
//	 */
//	function tslib_pibase()	{
//
//			// Setting piVars:
//		if ($this->prefixId)	{
//			$this->piVars = t3lib_div::_GPmerged($this->prefixId);
//
//				// cHash mode check
//				// IMPORTANT FOR CACHED PLUGINS (USER cObject): As soon as you generate cached plugin output which depends on parameters (eg. seeing the details of a news item) you MUST check if a cHash value is set.
//				// Background: The function call will check if a cHash parameter was sent with the URL because only if it was the page may be cached. If no cHash was found the function will simply disable caching to avoid unpredictable caching behaviour. In any case your plugin can generate the expected output and the only risk is that the content may not be cached. A missing cHash value is considered a mistake in the URL resulting from either URL manipulation, "realurl" "grayzones" etc. The problem is rare (more frequent with "realurl") but when it occurs it is very puzzling!
//			if ($this->pi_checkCHash && count($this->piVars))	{
//				$GLOBALS['TSFE']->reqCHash();
//			}
//		}
//		if (!empty($GLOBALS['TSFE']->config['config']['language'])) {
//			$this->LLkey = $GLOBALS['TSFE']->config['config']['language'];
//			if (!empty($GLOBALS['TSFE']->config['config']['language_alt'])) {
//				$this->altLLkey = $GLOBALS['TSFE']->config['config']['language_alt'];
//			}
//		}
//	}
//
//	/**
//	 * If internal TypoScript property "_DEFAULT_PI_VARS." is set then it will merge the current $this->piVars array onto these default values.
//	 *
//	 * @return	void
//	 */
//	function pi_setPiVarDefaults()	{
//		if (is_array($this->conf['_DEFAULT_PI_VARS.']))	{
//			$this->piVars = t3lib_div::array_merge_recursive_overrule($this->conf['_DEFAULT_PI_VARS.'],is_array($this->piVars)?$this->piVars:array());
//		}
//	}
//
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Link functions
//	 *
//	 **************************/
//
//	/**
//	 * Get URL to some page.
//	 * Returns the URL to page $id with $target and an array of additional url-parameters, $urlParameters
//	 * Simple example: $this->pi_getPageLink(123) to get the URL for page-id 123.
//	 *
//	 * The function basically calls $this->cObj->getTypoLink_URL()
//	 *
//	 * @param	integer		Page id
//	 * @param	string		Target value to use. Affects the &type-value of the URL, defaults to current.
//	 * @param	array		Additional URL parameters to set (key/value pairs)
//	 * @return	string		The resulting URL
//	 * @see pi_linkToPage()
//	 */
//	function pi_getPageLink($id,$target='',$urlParameters=array())	{
//		return $this->cObj->getTypoLink_URL($id,$urlParameters,$target);	// ?$target:$GLOBALS['TSFE']->sPre
//	}
//
//	/**
//	 * Link a string to some page.
//	 * Like pi_getPageLink() but takes a string as first parameter which will in turn be wrapped with the URL including target attribute
//	 * Simple example: $this->pi_linkToPage('My link', 123) to get something like <a href="index.php?id=123&type=1">My link</a> (or <a href="123.1.html">My link</a> if simulateStaticDocuments is set)
//	 *
//	 * @param	string		The content string to wrap in <a> tags
//	 * @param	integer		Page id
//	 * @param	string		Target value to use. Affects the &type-value of the URL, defaults to current.
//	 * @param	array		Additional URL parameters to set (key/value pairs)
//	 * @return	string		The input string wrapped in <a> tags with the URL and target set.
//	 * @see pi_getPageLink(), tslib_cObj::getTypoLink()
//	 */
//	function pi_linkToPage($str,$id,$target='',$urlParameters=array())	{
//		return $this->cObj->getTypoLink($str,$id,$urlParameters,$target);	// ?$target:$GLOBALS['TSFE']->sPre
//	}
//
//	/**
//	 * Link string to the current page.
//	 * Returns the $str wrapped in <a>-tags with a link to the CURRENT page, but with $urlParameters set as extra parameters for the page.
//	 *
//	 * @param	string		The content string to wrap in <a> tags
//	 * @param	array		Array with URL parameters as key/value pairs. They will be "imploded" and added to the list of parameters defined in the plugins TypoScript property "parent.addParams" plus $this->pi_moreParams.
//	 * @param	boolean		If $cache is set (0/1), the page is asked to be cached by a &cHash value (unless the current plugin using this class is a USER_INT). Otherwise the no_cache-parameter will be a part of the link.
//	 * @param	integer		Alternative page ID for the link. (By default this function links to the SAME page!)
//	 * @return	string		The input string wrapped in <a> tags
//	 * @see pi_linkTP_keepPIvars(), tslib_cObj::typoLink()
//	 */
//	function pi_linkTP($str,$urlParameters=array(),$cache=0,$altPageId=0)	{
//		$conf=array();
//		$conf['useCacheHash'] = $this->pi_USER_INT_obj ? 0 : $cache;
//		$conf['no_cache'] = $this->pi_USER_INT_obj ? 0 : !$cache;
//		$conf['parameter'] = $altPageId ? $altPageId : ($this->pi_tmpPageId ? $this->pi_tmpPageId : $GLOBALS['TSFE']->id);
//		$conf['additionalParams'] = $this->conf['parent.']['addParams'].t3lib_div::implodeArrayForUrl('', $urlParameters, '', true).$this->pi_moreParams;
//
//		return $this->cObj->typoLink($str, $conf);
//	}
//
//	/**
//	 * Link a string to the current page while keeping currently set values in piVars.
//	 * Like pi_linkTP, but $urlParameters is by default set to $this->piVars with $overrulePIvars overlaid.
//	 * This means any current entries from this->piVars are passed on (except the key "DATA" which will be unset before!) and entries in $overrulePIvars will OVERRULE the current in the link.
//	 *
//	 * @param	string		The content string to wrap in <a> tags
//	 * @param	array		Array of values to override in the current piVars. Contrary to pi_linkTP the keys in this array must correspond to the real piVars array and therefore NOT be prefixed with the $this->prefixId string. Further, if a value is a blank string it means the piVar key will not be a part of the link (unset)
//	 * @param	boolean		If $cache is set, the page is asked to be cached by a &cHash value (unless the current plugin using this class is a USER_INT). Otherwise the no_cache-parameter will be a part of the link.
//	 * @param	boolean		If set, then the current values of piVars will NOT be preserved anyways... Practical if you want an easy way to set piVars without having to worry about the prefix, "tx_xxxxx[]"
//	 * @param	integer		Alternative page ID for the link. (By default this function links to the SAME page!)
//	 * @return	string		The input string wrapped in <a> tags
//	 * @see pi_linkTP()
//	 */
//	function pi_linkTP_keepPIvars($str,$overrulePIvars=array(),$cache=0,$clearAnyway=0,$altPageId=0)	{
//		if (is_array($this->piVars) && is_array($overrulePIvars) && !$clearAnyway)	{
//			$piVars = $this->piVars;
//			unset($piVars['DATA']);
//			$overrulePIvars = t3lib_div::array_merge_recursive_overrule($piVars,$overrulePIvars);
//			if ($this->pi_autoCacheEn)	{
//				$cache = $this->pi_autoCache($overrulePIvars);
//			}
//		}
//		$res = $this->pi_linkTP($str,Array($this->prefixId=>$overrulePIvars),$cache,$altPageId);
//		return $res;
//	}
//
//	/**
//	 * Get URL to the current page while keeping currently set values in piVars.
//	 * Same as pi_linkTP_keepPIvars but returns only the URL from the link.
//	 *
//	 * @param	array		See pi_linkTP_keepPIvars
//	 * @param	boolean		See pi_linkTP_keepPIvars
//	 * @param	boolean		See pi_linkTP_keepPIvars
//	 * @param	integer		See pi_linkTP_keepPIvars
//	 * @return	string		The URL ($this->cObj->lastTypoLinkUrl)
//	 * @see pi_linkTP_keepPIvars()
//	 */
//	function pi_linkTP_keepPIvars_url($overrulePIvars=array(),$cache=0,$clearAnyway=0,$altPageId=0)	{
//		$this->pi_linkTP_keepPIvars('|',$overrulePIvars,$cache,$clearAnyway,$altPageId);
//		return $this->cObj->lastTypoLinkUrl;
//	}
//
//	/**
//	 * Wraps the $str in a link to a single display of the record (using piVars[showUid])
//	 * Uses pi_linkTP for the linking
//	 *
//	 * @param	string		The content string to wrap in <a> tags
//	 * @param	integer		UID of the record for which to display details (basically this will become the value of [showUid]
//	 * @param	boolean		See pi_linkTP_keepPIvars
//	 * @param	array		Array of values to override in the current piVars. Same as $overrulePIvars in pi_linkTP_keepPIvars
//	 * @param	boolean		If true, only the URL is returned, not a full link
//	 * @param	integer		Alternative page ID for the link. (By default this function links to the SAME page!)
//	 * @return	string		The input string wrapped in <a> tags
//	 * @see pi_linkTP(), pi_linkTP_keepPIvars()
//	 */
//	function pi_list_linkSingle($str,$uid,$cache=FALSE,$mergeArr=array(),$urlOnly=FALSE,$altPageId=0)	{
//		if ($this->prefixId)	{
//			if ($cache)	{
//				$overrulePIvars=$uid?array('showUid'=>$uid):Array();
//				$overrulePIvars=array_merge($overrulePIvars,(array)$mergeArr);
//				$str = $this->pi_linkTP($str,Array($this->prefixId=>$overrulePIvars),$cache,$altPageId);
//			} else {
//				$overrulePIvars=array('showUid'=>$uid?$uid:'');
//				$overrulePIvars=array_merge($overrulePIvars,(array)$mergeArr);
//				$str = $this->pi_linkTP_keepPIvars($str,$overrulePIvars,$cache,0,$altPageId);
//			}
//
//				// If urlOnly flag, return only URL as it has recently be generated.
//			if ($urlOnly)	{
//				$str = $this->cObj->lastTypoLinkUrl;
//			}
//		}
//		return $str;
//	}
//
//	/**
//	 * Will change the href value from <a> in the input string and turn it into an onclick event that will open a new window with the URL
//	 *
//	 * @param	string		The string to process. This should be a string already wrapped/including a <a> tag which will be modified to contain an onclick handler. Only the attributes "href" and "onclick" will be left.
//	 * @param	string		Window name for the pop-up window
//	 * @param	string		Window parameters, see the default list for inspiration
//	 * @return	string		The processed input string, modified IF a <a> tag was found
//	 */
//	function pi_openAtagHrefInJSwindow($str,$winName='',$winParams='width=670,height=500,status=0,menubar=0,scrollbars=1,resizable=1')	{
//		if (preg_match('/(.*)(<a[^>]*>)(.*)/i',$str,$match))	{
//			$aTagContent = t3lib_div::get_tag_attributes($match[2]);
//			$match[2]='<a href="#" onclick="'.
//				htmlspecialchars('vHWin=window.open(\''.$GLOBALS['TSFE']->baseUrlWrap($aTagContent['href']).'\',\''.($winName?$winName:md5($aTagContent['href'])).'\',\''.$winParams.'\');vHWin.focus();return false;').
//				'">';
//			$str=$match[1].$match[2].$match[3];
//		}
//		return $str;
//	}
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Functions for listing, browsing, searching etc.
//	 *
//	 **************************/
//
//	/**
//	 * Returns a results browser. This means a bar of page numbers plus a "previous" and "next" link. For each entry in the bar the piVars "pointer" will be pointing to the "result page" to show.
//	 * Using $this->piVars['pointer'] as pointer to the page to display. Can be overwritten with another string ($pointerName) to make it possible to have more than one pagebrowser on a page)
//	 * Using $this->internal['res_count'], $this->internal['results_at_a_time'] and $this->internal['maxPages'] for count number, how many results to show and the max number of pages to include in the browse bar.
//	 * Using $this->internal['dontLinkActivePage'] as switch if the active (current) page should be displayed as pure text or as a link to itself
//	 * Using $this->internal['showFirstLast'] as switch if the two links named "<< First" and "LAST >>" will be shown and point to the first or last page.
//	 * Using $this->internal['pagefloat']: this defines were the current page is shown in the list of pages in the Pagebrowser. If this var is an integer it will be interpreted as position in the list of pages. If its value is the keyword "center" the current page will be shown in the middle of the pagelist.
//	 * Using $this->internal['showRange']: this var switches the display of the pagelinks from pagenumbers to ranges f.e.: 1-5 6-10 11-15... instead of 1 2 3...
//	 * Using $this->pi_isOnlyFields: this holds a comma-separated list of fieldnames which - if they are among the GETvars - will not disable caching for the page with pagebrowser.
//	 *
//	 * The third parameter is an array with several wraps for the parts of the pagebrowser. The following elements will be recognized:
//	 * disabledLinkWrap, inactiveLinkWrap, activeLinkWrap, browseLinksWrap, showResultsWrap, showResultsNumbersWrap, browseBoxWrap.
//	 *
//	 * If $wrapArr['showResultsNumbersWrap'] is set, the formatting string is expected to hold template markers (###FROM###, ###TO###, ###OUT_OF###, ###FROM_TO###, ###CURRENT_PAGE###, ###TOTAL_PAGES###)
//	 * otherwise the formatting string is expected to hold sprintf-markers (%s) for from, to, outof (in that sequence)
//	 *
//	 * @param	integer		determines how the results of the pagerowser will be shown. See description below
//	 * @param	string		Attributes for the table tag which is wrapped around the table cells containing the browse links
//	 * @param	array		Array with elements to overwrite the default $wrapper-array.
//	 * @param	string		varname for the pointer.
//	 * @param	boolean		enable htmlspecialchars() for the pi_getLL function (set this to FALSE if you want f.e use images instead of text for links like 'previous' and 'next').
//	 * @param   boolean     forces the output of the page browser if you set this option to "true" (otherwise it's only drawn if enough entries are available)
// 	 * @return	string		Output HTML-Table, wrapped in <div>-tags with a class attribute (if $wrapArr is not passed,
//	 */
//	function pi_list_browseresults($showResultCount=1, $tableParams='', $wrapArr=array(), $pointerName='pointer', $hscText=TRUE, $forceOutput=FALSE) {
//
//		// example $wrapArr-array how it could be traversed from an extension
//		/* $wrapArr = array(
//			'browseBoxWrap' => '<div class="browseBoxWrap">|</div>',
//			'showResultsWrap' => '<div class="showResultsWrap">|</div>',
//			'browseLinksWrap' => '<div class="browseLinksWrap">|</div>',
//			'showResultsNumbersWrap' => '<span class="showResultsNumbersWrap">|</span>',
//			'disabledLinkWrap' => '<span class="disabledLinkWrap">|</span>',
//			'inactiveLinkWrap' => '<span class="inactiveLinkWrap">|</span>',
//			'activeLinkWrap' => '<span class="activeLinkWrap">|</span>'
//		); */
//
//			// Initializing variables:
//		$pointer = intval($this->piVars[$pointerName]);
//		$count = intval($this->internal['res_count']);
//		$results_at_a_time = t3lib_div::intInRange($this->internal['results_at_a_time'],1,1000);
//		$totalPages = ceil($count/$results_at_a_time);
//		$maxPages = t3lib_div::intInRange($this->internal['maxPages'],1,100);
//		$pi_isOnlyFields = $this->pi_isOnlyFields($this->pi_isOnlyFields);
//
//		if (!$forceOutput && $count <= $results_at_a_time) {
//			return '';
//		}
//
//			// $showResultCount determines how the results of the pagerowser will be shown.
//			// If set to 0: only the result-browser will be shown
//			//	 		 1: (default) the text "Displaying results..." and the result-browser will be shown.
//			//	 		 2: only the text "Displaying results..." will be shown
//		$showResultCount = intval($showResultCount);
//
//			// if this is set, two links named "<< First" and "LAST >>" will be shown and point to the very first or last page.
//		$showFirstLast = $this->internal['showFirstLast'];
//
//			// if this has a value the "previous" button is always visible (will be forced if "showFirstLast" is set)
//		$alwaysPrev = $showFirstLast?1:$this->pi_alwaysPrev;
//
//		if (isset($this->internal['pagefloat'])) {
//			if (strtoupper($this->internal['pagefloat']) == 'CENTER') {
//				$pagefloat = ceil(($maxPages - 1)/2);
//			} else {
//				// pagefloat set as integer. 0 = left, value >= $this->internal['maxPages'] = right
//				$pagefloat = t3lib_div::intInRange($this->internal['pagefloat'],-1,$maxPages-1);
//			}
//		} else {
//			$pagefloat = -1; // pagefloat disabled
//		}
//
//			// default values for "traditional" wrapping with a table. Can be overwritten by vars from $wrapArr
//		$wrapper['disabledLinkWrap'] = '<td nowrap="nowrap"><p>|</p></td>';
//		$wrapper['inactiveLinkWrap'] = '<td nowrap="nowrap"><p>|</p></td>';
//		$wrapper['activeLinkWrap'] = '<td'.$this->pi_classParam('browsebox-SCell').' nowrap="nowrap"><p>|</p></td>';
//		$wrapper['browseLinksWrap'] = trim('<table '.$tableParams).'><tr>|</tr></table>';
//		$wrapper['showResultsWrap'] = '<p>|</p>';
//		$wrapper['browseBoxWrap'] = '
//		<!--
//			List browsing box:
//		-->
//		<div '.$this->pi_classParam('browsebox').'>
//			|
//		</div>';
//
//			// now overwrite all entries in $wrapper which are also in $wrapArr
//		$wrapper = array_merge($wrapper,$wrapArr);
//
//		if ($showResultCount != 2) { //show pagebrowser
//			if ($pagefloat > -1) {
//				$lastPage = min($totalPages,max($pointer+1 + $pagefloat,$maxPages));
//				$firstPage = max(0,$lastPage-$maxPages);
//			} else {
//				$firstPage = 0;
//				$lastPage = t3lib_div::intInRange($totalPages,1,$maxPages);
//			}
//			$links=array();
//
//				// Make browse-table/links:
//			if ($showFirstLast) { // Link to first page
//				if ($pointer>0)	{
//					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_first','<< First',$hscText),array($pointerName => null),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
//				} else {
//					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_first','<< First',$hscText),$wrapper['disabledLinkWrap']);
//				}
//			}
//			if ($alwaysPrev>=0)	{ // Link to previous page
//				if ($pointer>0)	{
//					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_prev','< Previous',$hscText),array($pointerName => ($pointer-1?$pointer-1:'')),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
//				} elseif ($alwaysPrev)	{
//					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_prev','< Previous',$hscText),$wrapper['disabledLinkWrap']);
//				}
//			}
//			for($a=$firstPage;$a<$lastPage;$a++)	{ // Links to pages
//				if ($this->internal['showRange']) {
//					$pageText = (($a*$results_at_a_time)+1).'-'.min($count,(($a+1)*$results_at_a_time));
//				} else {
//					$pageText = trim($this->pi_getLL('pi_list_browseresults_page','Page',$hscText).' '.($a+1));
//				}
//				if ($pointer == $a) { // current page
//					if ($this->internal['dontLinkActivePage']) {
//						$links[] = $this->cObj->wrap($pageText,$wrapper['activeLinkWrap']);
//					} else {
//						$links[] = $this->cObj->wrap($this->pi_linkTP_keepPIvars($pageText,array($pointerName  => ($a?$a:'')),$pi_isOnlyFields),$wrapper['activeLinkWrap']);
//					}
//				} else {
//					$links[] = $this->cObj->wrap($this->pi_linkTP_keepPIvars($pageText,array($pointerName => ($a?$a:'')),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
//				}
//			}
//			if ($pointer<$totalPages-1 || $showFirstLast)	{
//				if ($pointer>=$totalPages-1) { // Link to next page
//					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_next','Next >',$hscText),$wrapper['disabledLinkWrap']);
//				} else {
//					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_next','Next >',$hscText),array($pointerName => $pointer+1),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
//				}
//			}
//			if ($showFirstLast) { // Link to last page
//				if ($pointer<$totalPages-1) {
//					$links[]=$this->cObj->wrap($this->pi_linkTP_keepPIvars($this->pi_getLL('pi_list_browseresults_last','Last >>',$hscText),array($pointerName => $totalPages-1),$pi_isOnlyFields),$wrapper['inactiveLinkWrap']);
//				} else {
//					$links[]=$this->cObj->wrap($this->pi_getLL('pi_list_browseresults_last','Last >>',$hscText),$wrapper['disabledLinkWrap']);
//				}
//			}
//			$theLinks = $this->cObj->wrap(implode(LF,$links),$wrapper['browseLinksWrap']);
//		} else {
//			$theLinks = '';
//		}
//
//		$pR1 = $pointer*$results_at_a_time+1;
//		$pR2 = $pointer*$results_at_a_time+$results_at_a_time;
//
//		if ($showResultCount) {
//			if ($wrapper['showResultsNumbersWrap']) {
//				// this will render the resultcount in a more flexible way using markers (new in TYPO3 3.8.0).
//				// the formatting string is expected to hold template markers (see function header). Example: 'Displaying results ###FROM### to ###TO### out of ###OUT_OF###'
//
//				$markerArray['###FROM###'] = $this->cObj->wrap($this->internal['res_count'] > 0 ? $pR1 : 0,$wrapper['showResultsNumbersWrap']);
//				$markerArray['###TO###'] = $this->cObj->wrap(min($this->internal['res_count'],$pR2),$wrapper['showResultsNumbersWrap']);
//				$markerArray['###OUT_OF###'] = $this->cObj->wrap($this->internal['res_count'],$wrapper['showResultsNumbersWrap']);
//				$markerArray['###FROM_TO###'] = $this->cObj->wrap(($this->internal['res_count'] > 0 ? $pR1 : 0).' '.$this->pi_getLL('pi_list_browseresults_to','to').' '.min($this->internal['res_count'],$pR2),$wrapper['showResultsNumbersWrap']);
//				$markerArray['###CURRENT_PAGE###'] = $this->cObj->wrap($pointer+1,$wrapper['showResultsNumbersWrap']);
//				$markerArray['###TOTAL_PAGES###'] = $this->cObj->wrap($totalPages,$wrapper['showResultsNumbersWrap']);
//				// substitute markers
//				$resultCountMsg = $this->cObj->substituteMarkerArray($this->pi_getLL('pi_list_browseresults_displays','Displaying results ###FROM### to ###TO### out of ###OUT_OF###'),$markerArray);
//			} else {
//				// render the resultcount in the "traditional" way using sprintf
//				$resultCountMsg = sprintf(
//					str_replace('###SPAN_BEGIN###','<span'.$this->pi_classParam('browsebox-strong').'>',$this->pi_getLL('pi_list_browseresults_displays','Displaying results ###SPAN_BEGIN###%s to %s</span> out of ###SPAN_BEGIN###%s</span>')),
//					$count > 0 ? $pR1 : 0,
//					min($count,$pR2),
//					$count);
//			}
//			$resultCountMsg = $this->cObj->wrap($resultCountMsg,$wrapper['showResultsWrap']);
//		} else {
//			$resultCountMsg = '';
//		}
//
//		$sTables = $this->cObj->wrap($resultCountMsg.$theLinks,$wrapper['browseBoxWrap']);
//
//		return $sTables;
//	}
//
//	/**
//	 * Returns a Search box, sending search words to piVars "sword" and setting the "no_cache" parameter as well in the form.
//	 * Submits the search request to the current REQUEST_URI
//	 *
//	 * @param	string		Attributes for the table tag which is wrapped around the table cells containing the search box
//	 * @return	string		Output HTML, wrapped in <div>-tags with a class attribute
//	 */
//	function pi_list_searchBox($tableParams='')	{
//			// Search box design:
//		$sTables = '
//
//		<!--
//			List search box:
//		-->
//		<div'.$this->pi_classParam('searchbox').'>
//			<form action="'.htmlspecialchars(t3lib_div::getIndpEnv('REQUEST_URI')).'" method="post" style="margin: 0 0 0 0;">
//			<'.trim('table '.$tableParams).'>
//				<tr>
//					<td><input type="text" name="'.$this->prefixId.'[sword]" value="'.htmlspecialchars($this->piVars['sword']).'"'.$this->pi_classParam('searchbox-sword').' /></td>
//					<td><input type="submit" value="'.$this->pi_getLL('pi_list_searchBox_search','Search',TRUE).'"'.$this->pi_classParam('searchbox-button').' />'.
//						'<input type="hidden" name="no_cache" value="1" />'.
//						'<input type="hidden" name="'.$this->prefixId.'[pointer]" value="" />'.
//						'</td>
//				</tr>
//			</table>
//			</form>
//		</div>';
//
//		return $sTables;
//	}
//
//	/**
//	 * Returns a mode selector; a little menu in a table normally put in the top of the page/list.
//	 *
//	 * @param	array		Key/Value pairs for the menu; keys are the piVars[mode] values and the "values" are the labels for them.
//	 * @param	string		Attributes for the table tag which is wrapped around the table cells containing the menu
//	 * @return	string		Output HTML, wrapped in <div>-tags with a class attribute
//	 */
//	function pi_list_modeSelector($items=array(),$tableParams='')	{
//		$cells=array();
//		foreach ($items as $k => $v) {
//			$cells[]='
//					<td'.($this->piVars['mode']==$k?$this->pi_classParam('modeSelector-SCell'):'').'><p>'.
//				$this->pi_linkTP_keepPIvars(htmlspecialchars($v),array('mode'=>$k),$this->pi_isOnlyFields($this->pi_isOnlyFields)).
//				'</p></td>';
//		}
//
//		$sTables = '
//
//		<!--
//			Mode selector (menu for list):
//		-->
//		<div'.$this->pi_classParam('modeSelector').'>
//			<'.trim('table '.$tableParams).'>
//				<tr>
//					'.implode('',$cells).'
//				</tr>
//			</table>
//		</div>';
//
//		return $sTables;
//	}
//
//	/**
//	 * Returns the list of items based on the input SQL result pointer
//	 * For each result row the internal var, $this->internal['currentRow'], is set with the row returned.
//	 * $this->pi_list_header() makes the header row for the list
//	 * $this->pi_list_row() is used for rendering each row
//	 * Notice that these two functions are typically ALWAYS defined in the extension class of the plugin since they are directly concerned with the specific layout for that plugins purpose.
//	 *
//	 * @param	pointer		Result pointer to a SQL result which can be traversed.
//	 * @param	string		Attributes for the table tag which is wrapped around the table rows containing the list
//	 * @return	string		Output HTML, wrapped in <div>-tags with a class attribute
//	 * @see pi_list_row(), pi_list_header()
//	 */
//	function pi_list_makelist($res,$tableParams='')	{
//			// Make list table header:
//		$tRows=array();
//		$this->internal['currentRow']='';
//		$tRows[] = $this->pi_list_header();
//
//			// Make list table rows
//		$c=0;
//		while($this->internal['currentRow'] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
//			$tRows[] = $this->pi_list_row($c);
//			$c++;
//		}
//
//		$out = '
//
//		<!--
//			Record list:
//		-->
//		<div'.$this->pi_classParam('listrow').'>
//			<'.trim('table '.$tableParams).'>
//				'.implode('',$tRows).'
//			</table>
//		</div>';
//
//		return $out;
//	}
//
//	/**
//	 * Returns a list row. Get data from $this->internal['currentRow'];
//	 * (Dummy)
//	 * Notice: This function should ALWAYS be defined in the extension class of the plugin since it is directly concerned with the specific layout of the listing for your plugins purpose.
//	 *
//	 * @param	integer		Row counting. Starts at 0 (zero). Used for alternating class values in the output rows.
//	 * @return	string		HTML output, a table row with a class attribute set (alternative based on odd/even rows)
//	 */
//	function pi_list_row($c)	{
//		// Dummy
//		return '<tr'.($c%2 ? $this->pi_classParam('listrow-odd') : '').'><td><p>[dummy row]</p></td></tr>';
//	}
//
//	/**
//	 * Returns a list header row.
//	 * (Dummy)
//	 * Notice: This function should ALWAYS be defined in the extension class of the plugin since it is directly concerned with the specific layout of the listing for your plugins purpose.
//	 *
//	 * @return	string		HTML output, a table row with a class attribute set
//	 */
//	function pi_list_header()	{
//		return '<tr'.$this->pi_classParam('listrow-header').'><td><p>[dummy header row]</p></td></tr>';
//	}
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Stylesheet, CSS
//	 *
//	 **************************/
//
//
//	/**
//	 * Returns a class-name prefixed with $this->prefixId and with all underscores substituted to dashes (-)
//	 *
//	 * @param	string		The class name (or the END of it since it will be prefixed by $this->prefixId.'-')
//	 * @return	string		The combined class name (with the correct prefix)
//	 */
//	function pi_getClassName($class)	{
//		return str_replace('_','-',$this->prefixId).($this->prefixId?'-':'').$class;
//	}
//
//	/**
//	 * Returns the class-attribute with the correctly prefixed classname
//	 * Using pi_getClassName()
//	 *
//	 * @param	string		The class name(s) (suffix) - separate multiple classes with commas
//	 * @param	string		Additional class names which should not be prefixed - separate multiple classes with commas
//	 * @return	string		A "class" attribute with value and a single space char before it.
//	 * @see pi_getClassName()
//	 */
//	function pi_classParam($class, $addClasses='')	{
//		$output = '';
//		foreach (t3lib_div::trimExplode(',',$class) as $v)	{
//			$output.= ' '.$this->pi_getClassName($v);
//		}
//		foreach (t3lib_div::trimExplode(',',$addClasses) as $v)	{
//			$output.= ' '.$v;
//		}
//		return ' class="'.trim($output).'"';
//	}
//
//	/**
//	 * Sets CSS style-data for the $class-suffix (prefixed by pi_getClassName())
//	 *
//	 * @param	string		$class: Class suffix, see pi_getClassName
//	 * @param	string		$data: CSS data
//	 * @param	string		If $selector is set to any CSS selector, eg 'P' or 'H1' or 'TABLE' then the style $data will regard those HTML-elements only
//	 * @return	void
//	 * @deprecated since TYPO3 3.6, this function will be removed in TYPO3 4.5, I think this function should not be used (and probably isn't used anywhere). It was a part of a concept which was left behind quite quickly.
//	 * @obsolete
//	 * @private
//	 */
//	function pi_setClassStyle($class,$data,$selector='')	{
//		t3lib_div::logDeprecatedFunction();
//
//		$GLOBALS['TSFE']->setCSS($this->pi_getClassName($class).($selector?' '.$selector:''),'.'.$this->pi_getClassName($class).($selector?' '.$selector:'').' {'.$data.'}');
//	}
//
//	/**
//	 * Wraps the input string in a <div> tag with the class attribute set to the prefixId.
//	 * All content returned from your plugins should be returned through this function so all content from your plugin is encapsulated in a <div>-tag nicely identifying the content of your plugin.
//	 *
//	 * @param	string		HTML content to wrap in the div-tags with the "main class" of the plugin
//	 * @return	string		HTML content wrapped, ready to return to the parent object.
//	 */
//	function pi_wrapInBaseClass($str)	{
//		$content = '<div class="'.str_replace('_','-',$this->prefixId).'">
//		'.$str.'
//	</div>
//	';
//
//		if(!$GLOBALS['TSFE']->config['config']['disablePrefixComment'])	{
//			$content = '
//
//
//	<!--
//
//		BEGIN: Content of extension "'.$this->extKey.'", plugin "'.$this->prefixId.'"
//
//	-->
//	'.$content.'
//	<!-- END: Content of extension "'.$this->extKey.'", plugin "'.$this->prefixId.'" -->
//
//	';
//		}
//
//		return $content;
//	}
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Frontend editing: Edit panel, edit icons
//	 *
//	 **************************/
//
//	/**
//	 * Returns the Backend User edit panel for the $row from $tablename
//	 *
//	 * @param	array		Record array.
//	 * @param	string		Table name
//	 * @param	string		A label to show with the panel.
//	 * @param	array		TypoScript parameters to pass along to the EDITPANEL content Object that gets rendered. The property "allow" WILL get overridden/set though.
//	 * @return	string		Returns false/blank if no BE User login and of course if the panel is not shown for other reasons. Otherwise the HTML for the panel (a table).
//	 * @see tslib_cObj::EDITPANEL()
//	 */
//	function pi_getEditPanel($row='',$tablename='',$label='',$conf=Array())	{
//		$panel='';
//		if (!$row || !$tablename)	{
//			$row = $this->internal['currentRow'];
//			$tablename = $this->internal['currentTable'];
//		}
//
//		if ($GLOBALS['TSFE']->beUserLogin)	{
//				// Create local cObj if not set:
//			if (!is_object($this->pi_EPtemp_cObj))	{
//				$this->pi_EPtemp_cObj = t3lib_div::makeInstance('tslib_cObj');
//				$this->pi_EPtemp_cObj->setParent($this->cObj->data,$this->cObj->currentRecord);
//			}
//
//				// Initialize the cObj object with current row
//			$this->pi_EPtemp_cObj->start($row,$tablename);
//
//				// Setting TypoScript values in the $conf array. See documentation in TSref for the EDITPANEL cObject.
//			$conf['allow'] = 'edit,new,delete,move,hide';
//			$panel = $this->pi_EPtemp_cObj->cObjGetSingle('EDITPANEL',$conf,'editpanel');
//		}
//
//		if ($panel)	{
//			if ($label)	{
//				return '<!-- BEGIN: EDIT PANEL --><table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td valign="top">'.$label.'</td><td valign="top" align="right">'.$panel.'</td></tr></table><!-- END: EDIT PANEL -->';
//			} else return '<!-- BEGIN: EDIT PANEL -->'.$panel.'<!-- END: EDIT PANEL -->';
//		} else return $label;
//	}
//
//	/**
//	 * Adds edit-icons to the input content.
//	 * tslib_cObj::editIcons used for rendering
//	 *
//	 * @param	string		HTML content to add icons to. The icons will be put right after the last content part in the string (that means before the ending series of HTML tags)
//	 * @param	string		The list of fields to edit when the icon is clicked.
//	 * @param	string		Title for the edit icon.
//	 * @param	array		Table record row
//	 * @param	string		Table name
//	 * @param	array		Conf array
//	 * @return	string		The processed content
//	 * @see tslib_cObj::editIcons()
//	 */
//	function pi_getEditIcon($content,$fields,$title='',$row='',$tablename='',$oConf=array())	{
//		if ($GLOBALS['TSFE']->beUserLogin){
//			if (!$row || !$tablename)	{
//				$row = $this->internal['currentRow'];
//				$tablename = $this->internal['currentTable'];
//			}
//			$conf=array_merge(array(
//				'beforeLastTag'=>1,
//				'iconTitle' => $title
//			),$oConf);
//			$content=$this->cObj->editIcons($content,$tablename.':'.$fields,$conf,$tablename.':'.$row['uid'],$row,'&viewUrl='.rawurlencode(t3lib_div::getIndpEnv('REQUEST_URI')));
//		}
//		return $content;
//	}
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Localization, locallang functions
//	 *
//	 **************************/
//
//
//	/**
//	 * Returns the localized label of the LOCAL_LANG key, $key
//	 * Notice that for debugging purposes prefixes for the output values can be set with the internal vars ->LLtestPrefixAlt and ->LLtestPrefix
//	 *
//	 * @param	string		The key from the LOCAL_LANG array for which to return the value.
//	 * @param	string		Alternative string to return IF no value is found set for the key, neither for the local language nor the default.
//	 * @param	boolean		If true, the output label is passed through htmlspecialchars()
//	 * @return	string		The value from LOCAL_LANG.
//	 */
//	function pi_getLL($key,$alt='',$hsc=FALSE)	{
//			// The "from" charset of csConv() is only set for strings from TypoScript via _LOCAL_LANG
//		if (isset($this->LOCAL_LANG[$this->LLkey][$key]))	{
//			$word = $GLOBALS['TSFE']->csConv($this->LOCAL_LANG[$this->LLkey][$key], $this->LOCAL_LANG_charset[$this->LLkey][$key]);
//		} elseif ($this->altLLkey && isset($this->LOCAL_LANG[$this->altLLkey][$key]))	{
//			$word = $GLOBALS['TSFE']->csConv($this->LOCAL_LANG[$this->altLLkey][$key], $this->LOCAL_LANG_charset[$this->altLLkey][$key]);
//		} elseif (isset($this->LOCAL_LANG['default'][$key]))	{
//			$word = $this->LOCAL_LANG['default'][$key];	// No charset conversion because default is english and thereby ASCII
//		} else {
//			$word = $this->LLtestPrefixAlt.$alt;
//		}
//
//		$output = $this->LLtestPrefix.$word;
//		if ($hsc)	$output = htmlspecialchars($output);
//
//		return $output;
//	}
//
//	/**
//	 * Loads local-language values by looking for a "locallang.php" file in the plugin class directory ($this->scriptRelPath) and if found includes it.
//	 * Also locallang values set in the TypoScript property "_LOCAL_LANG" are merged onto the values found in the "locallang.php" file.
//	 *
//	 * @return	void
//	 */
//	function pi_loadLL()	{
//		if (!$this->LOCAL_LANG_loaded && $this->scriptRelPath)	{
//			$basePath = 'EXT:' . $this->extKey . '/' . dirname($this->scriptRelPath) . '/locallang.xml';
//
//				// Read the strings in the required charset (since TYPO3 4.2)
//			$this->LOCAL_LANG = t3lib_div::readLLfile($basePath,$this->LLkey,$GLOBALS['TSFE']->renderCharset);
//			if ($this->altLLkey)	{
//				$tempLOCAL_LANG = t3lib_div::readLLfile($basePath,$this->altLLkey);
//				$this->LOCAL_LANG = array_merge(is_array($this->LOCAL_LANG) ? $this->LOCAL_LANG : array(),$tempLOCAL_LANG);
//			}
//
//				// Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
//			$confLL = $this->conf['_LOCAL_LANG.'];
//			if (is_array($confLL)) {
//				foreach ($confLL as $k => $lA) {
//					if (is_array($lA))	{
//						$k = substr($k,0,-1);
//						foreach($lA as $llK => $llV)	{
//							if (!is_array($llV))	{
//								$this->LOCAL_LANG[$k][$llK] = $llV;
//									// For labels coming from the TypoScript (database) the charset is assumed to be "forceCharset" and if that is not set, assumed to be that of the individual system languages
//								$this->LOCAL_LANG_charset[$k][$llK] = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->csConvObj->charSetArray[$k];
//							}
//						}
//					}
//				}
//			}
//		}
//		$this->LOCAL_LANG_loaded = 1;
//	}
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Database, queries
//	 *
//	 **************************/
//
//	/**
//	 * Makes a standard query for listing of records based on standard input vars from the 'browser' ($this->internal['results_at_a_time'] and $this->piVars['pointer']) and 'searchbox' ($this->piVars['sword'] and $this->internal['searchFieldList'])
//	 * Set $count to 1 if you wish to get a count(*) query for selecting the number of results.
//	 * Notice that the query will use $this->conf['pidList'] and $this->conf['recursive'] to generate a PID list within which to search for records.
//	 *
//	 * @param	string		See pi_exec_query()
//	 * @param	boolean		See pi_exec_query()
//	 * @param	string		See pi_exec_query()
//	 * @param	mixed		See pi_exec_query()
//	 * @param	string		See pi_exec_query()
//	 * @param	string		See pi_exec_query()
//	 * @param	string		See pi_exec_query()
//	 * @param	boolean		If set, the function will return the query not as a string but array with the various parts.
//	 * @return	mixed		The query build.
//	 * @access private
//	 * @deprecated since TYPO3 3.6, this function will be removed in TYPO3 4.5, use pi_exec_query() instead!
//	 */
//	function pi_list_query($table,$count=0,$addWhere='',$mm_cat='',$groupBy='',$orderBy='',$query='',$returnQueryArray=FALSE)	{
//
//			// Begin Query:
//		if (!$query)	{
//				// Fetches the list of PIDs to select from.
//				// TypoScript property .pidList is a comma list of pids. If blank, current page id is used.
//				// TypoScript property .recursive is a int+ which determines how many levels down from the pids in the pid-list subpages should be included in the select.
//			$pidList = $this->pi_getPidList($this->conf['pidList'],$this->conf['recursive']);
//			if (is_array($mm_cat))	{
//				$query='FROM '.$table.','.$mm_cat['table'].','.$mm_cat['mmtable'].LF.
//						' WHERE '.$table.'.uid='.$mm_cat['mmtable'].'.uid_local AND '.$mm_cat['table'].'.uid='.$mm_cat['mmtable'].'.uid_foreign '.LF.
//						(strcmp($mm_cat['catUidList'],'')?' AND '.$mm_cat['table'].'.uid IN ('.$mm_cat['catUidList'].')':'').LF.
//						' AND '.$table.'.pid IN ('.$pidList.')'.LF.
//						$this->cObj->enableFields($table).LF;	// This adds WHERE-clauses that ensures deleted, hidden, starttime/endtime/access records are NOT selected, if they should not! Almost ALWAYS add this to your queries!
//			} else {
//				$query='FROM '.$table.' WHERE pid IN ('.$pidList.')'.LF.
//						$this->cObj->enableFields($table).LF;	// This adds WHERE-clauses that ensures deleted, hidden, starttime/endtime/access records are NOT selected, if they should not! Almost ALWAYS add this to your queries!
//			}
//		}
//
//			// Split the "FROM ... WHERE" string so we get the WHERE part and TABLE names separated...:
//		list($TABLENAMES, $WHERE) = preg_split('/WHERE/i', trim($query), 2);
//		$TABLENAMES = trim(substr(trim($TABLENAMES),5));
//		$WHERE = trim($WHERE);
//
//			// Add '$addWhere'
//		if ($addWhere)	{$WHERE.=' '.$addWhere.LF;}
//
//			// Search word:
//		if ($this->piVars['sword'] && $this->internal['searchFieldList'])	{
//			$WHERE.=$this->cObj->searchWhere($this->piVars['sword'],$this->internal['searchFieldList'],$table).LF;
//		}
//
//		if ($count) {
//			$queryParts = array(
//				'SELECT' => 'count(*)',
//				'FROM' => $TABLENAMES,
//				'WHERE' => $WHERE,
//				'GROUPBY' => '',
//				'ORDERBY' => '',
//				'LIMIT' => ''
//			);
//		} else {
//				// Order by data:
//			if (!$orderBy && $this->internal['orderBy'])	{
//				if (t3lib_div::inList($this->internal['orderByList'],$this->internal['orderBy']))	{
//					$orderBy = 'ORDER BY '.$table.'.'.$this->internal['orderBy'].($this->internal['descFlag']?' DESC':'');
//				}
//			}
//
//				// Limit data:
//			$pointer = $this->piVars['pointer'];
//			$pointer = intval($pointer);
//			$results_at_a_time = t3lib_div::intInRange($this->internal['results_at_a_time'],1,1000);
//			$LIMIT = ($pointer*$results_at_a_time).','.$results_at_a_time;
//
//				// Add 'SELECT'
//			$queryParts = array(
//				'SELECT' => $this->pi_prependFieldsWithTable($table,$this->pi_listFields),
//				'FROM' => $TABLENAMES,
//				'WHERE' => $WHERE,
//				'GROUPBY' => $GLOBALS['TYPO3_DB']->stripGroupBy($groupBy),
//				'ORDERBY' => $GLOBALS['TYPO3_DB']->stripOrderBy($orderBy),
//				'LIMIT' => $LIMIT
//			);
//		}
//
//		$query = $GLOBALS['TYPO3_DB']->SELECTquery (
//					$queryParts['SELECT'],
//					$queryParts['FROM'],
//					$queryParts['WHERE'],
//					$queryParts['GROUPBY'],
//					$queryParts['ORDERBY'],
//					$queryParts['LIMIT']
//				);
//		return $returnQueryArray ? $queryParts : $query;
//	}
//
//	/**
//	 * Executes a standard SELECT query for listing of records based on standard input vars from the 'browser' ($this->internal['results_at_a_time'] and $this->piVars['pointer']) and 'searchbox' ($this->piVars['sword'] and $this->internal['searchFieldList'])
//	 * Set $count to 1 if you wish to get a count(*) query for selecting the number of results.
//	 * Notice that the query will use $this->conf['pidList'] and $this->conf['recursive'] to generate a PID list within which to search for records.
//	 *
//	 * @param	string		The table name to make the query for.
//	 * @param	boolean		If set, you will get a "count(*)" query back instead of field selecting
//	 * @param	string		Additional WHERE clauses (should be starting with " AND ....")
//	 * @param	mixed		If an array, then it must contain the keys "table", "mmtable" and (optionally) "catUidList" defining a table to make a MM-relation to in the query (based on fields uid_local and uid_foreign). If not array, the query will be a plain query looking up data in only one table.
//	 * @param	string		If set, this is added as a " GROUP BY ...." part of the query.
//	 * @param	string		If set, this is added as a " ORDER BY ...." part of the query. The default is that an ORDER BY clause is made based on $this->internal['orderBy'] and $this->internal['descFlag'] where the orderBy field must be found in $this->internal['orderByList']
//	 * @param	string		If set, this is taken as the first part of the query instead of what is created internally. Basically this should be a query starting with "FROM [table] WHERE ... AND ...". The $addWhere clauses and all the other stuff is still added. Only the tables and PID selecting clauses are bypassed. May be deprecated in the future!
//	 * @return	pointer		SQL result pointer
//	 */
//	function pi_exec_query($table,$count=0,$addWhere='',$mm_cat='',$groupBy='',$orderBy='',$query='')	{
//		$queryParts = $this->pi_list_query($table,$count,$addWhere,$mm_cat,$groupBy,$orderBy,$query, TRUE);
//
//		return $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($queryParts);
//	}
//
//	/**
//	 * Returns the row $uid from $table
//	 * (Simply calling $GLOBALS['TSFE']->sys_page->checkRecord())
//	 *
//	 * @param	string		The table name
//	 * @param	integer		The uid of the record from the table
//	 * @param	boolean		If $checkPage is set, it's required that the page on which the record resides is accessible
//	 * @return	array		If record is found, an array. Otherwise false.
//	 */
//	function pi_getRecord($table,$uid,$checkPage=0)	{
//		return $GLOBALS['TSFE']->sys_page->checkRecord($table,$uid,$checkPage);
//	}
//
//	/**
//	 * Returns a commalist of page ids for a query (eg. 'WHERE pid IN (...)')
//	 *
//	 * @param	string		$pid_list is a comma list of page ids (if empty current page is used)
//	 * @param	integer		$recursive is an integer >=0 telling how deep to dig for pids under each entry in $pid_list
//	 * @return	string		List of PID values (comma separated)
//	 */
//	function pi_getPidList($pid_list, $recursive = 0) {
//		if (!strcmp($pid_list, '')) {
//			$pid_list = $GLOBALS['TSFE']->id;
//		}
//
//		$recursive = t3lib_div::intInRange($recursive, 0);
//
//		$pid_list_arr = array_unique(t3lib_div::trimExplode(',', $pid_list, 1));
//		$pid_list     = array();
//
//		foreach($pid_list_arr as $val) {
//			$val = t3lib_div::intInRange($val, 0);
//			if ($val) {
//				$_list = $this->cObj->getTreeList(-1 * $val, $recursive);
//				if ($_list) {
//					$pid_list[] = $_list;
//				}
//			}
//		}
//
//		return implode(',', $pid_list);
//	}
//
//	/**
//	 * Having a comma list of fields ($fieldList) this is prepended with the $table.'.' name
//	 *
//	 * @param	string		Table name to prepend
//	 * @param	string		List of fields where each element will be prepended with the table name given.
//	 * @return	string		List of fields processed.
//	 */
//	function pi_prependFieldsWithTable($table,$fieldList)	{
//		$list=t3lib_div::trimExplode(',',$fieldList,1);
//		$return=array();
//		foreach ($list as $listItem) {
//			$return[]=$table.'.'.$listItem;
//		}
//		return implode(',',$return);
//	}
//
//	/**
//	 * Will select all records from the "category table", $table, and return them in an array.
//	 *
//	 * @param	string		The name of the category table to select from.
//	 * @param	integer		The page from where to select the category records.
//	 * @param	string		Optional additional WHERE clauses put in the end of the query. DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
//	 * @param	string		Optional GROUP BY field(s), if none, supply blank string.
//	 * @param	string		Optional ORDER BY field(s), if none, supply blank string.
//	 * @param	string		Optional LIMIT value ([begin,]max), if none, supply blank string.
//	 * @return	array		The array with the category records in.
//	 */
//	function pi_getCategoryTableContents($table,$pid,$whereClause='',$groupBy='',$orderBy='',$limit='')	{
//		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
//					'*',
//					$table,
//					'pid='.intval($pid).
//						$this->cObj->enableFields($table).' '.
//						$whereClause,	// whereClauseMightContainGroupOrderBy
//					$groupBy,
//					$orderBy,
//					$limit
//				);
//		$outArr = array();
//		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
//			$outArr[$row['uid']] = $row;
//		}
//		$GLOBALS['TYPO3_DB']->sql_free_result($res);
//		return $outArr;
//	}
//
//
//
//
//
//
//
//
//
//
//
//
//	/***************************
//	 *
//	 * Various
//	 *
//	 **************************/
//
//	/**
//	 * Returns true if the piVars array has ONLY those fields entered that is set in the $fList (commalist) AND if none of those fields value is greater than $lowerThan field if they are integers.
//	 * Notice that this function will only work as long as values are integers.
//	 *
//	 * @param	string		List of fields (keys from piVars) to evaluate on
//	 * @param	integer		Limit for the values.
//	 * @return	boolean		Returns true (1) if conditions are met.
//	 */
//	function pi_isOnlyFields($fList,$lowerThan=-1)	{
//		$lowerThan = $lowerThan==-1 ? $this->pi_lowerThan : $lowerThan;
//
//		$fList = t3lib_div::trimExplode(',',$fList,1);
//		$tempPiVars = $this->piVars;
//		foreach ($fList as $k) {
//			if (!t3lib_div::testInt($tempPiVars[$k]) || $tempPiVars[$k]<$lowerThan)		unset($tempPiVars[$k]);
//		}
//		if (!count($tempPiVars))	return 1;
//	}
//
//	/**
//	 * Returns true if the array $inArray contains only values allowed to be cached based on the configuration in $this->pi_autoCacheFields
//	 * Used by ->pi_linkTP_keepPIvars
//	 * This is an advanced form of evaluation of whether a URL should be cached or not.
//	 *
//	 * @param	array		An array with piVars values to evaluate
//	 * @return	boolean		Returns true (1) if conditions are met.
//	 * @see pi_linkTP_keepPIvars()
//	 */
//	function pi_autoCache($inArray)	{
//		if (is_array($inArray))	{
//			foreach ($inArray as $fN => $fV) {
//				if (!strcmp($inArray[$fN],''))	{
//					unset($inArray[$fN]);
//				} elseif (is_array($this->pi_autoCacheFields[$fN]))	{
//					if (is_array($this->pi_autoCacheFields[$fN]['range'])
//							 && intval($inArray[$fN])>=intval($this->pi_autoCacheFields[$fN]['range'][0])
//							 && intval($inArray[$fN])<=intval($this->pi_autoCacheFields[$fN]['range'][1]))	{
//								unset($inArray[$fN]);
//					}
//					if (is_array($this->pi_autoCacheFields[$fN]['list'])
//							 && in_array($inArray[$fN],$this->pi_autoCacheFields[$fN]['list']))	{
//								unset($inArray[$fN]);
//					}
//				}
//			}
//		}
//		if (!count($inArray))	return 1;
//	}
//
//	/**
//	 * Will process the input string with the parseFunc function from tslib_cObj based on configuration set in "lib.parseFunc_RTE" in the current TypoScript template.
//	 * This is useful for rendering of content in RTE fields where the transformation mode is set to "ts_css" or so.
//	 * Notice that this requires the use of "css_styled_content" to work right.
//	 *
//	 * @param	string		The input text string to process
//	 * @return	string		The processed string
//	 * @see tslib_cObj::parseFunc()
//	 */
//	function pi_RTEcssText($str)	{
//		$parseFunc = $GLOBALS['TSFE']->tmpl->setup['lib.']['parseFunc_RTE.'];
//		if (is_array($parseFunc))	$str = $this->cObj->parseFunc($str, $parseFunc);
//		return $str;
//	}
//
//
//
//
//
//	/*******************************
//	 *
//	 * FlexForms related functions
//	 *
//	 *******************************/
//
//	/**
//	 * Converts $this->cObj->data['pi_flexform'] from XML string to flexForm array.
//	 *
//	 * @param	string		Field name to convert
//	 * @return	void
//	 */
//	function pi_initPIflexForm($field='pi_flexform')	{
//			// Converting flexform data into array:
//		if (!is_array($this->cObj->data[$field]) && $this->cObj->data[$field])	{
//			$this->cObj->data[$field] = t3lib_div::xml2array($this->cObj->data[$field]);
//			if (!is_array($this->cObj->data[$field]))	$this->cObj->data[$field]=array();
//		}
//	}
//
//	/**
//	 * Return value from somewhere inside a FlexForm structure
//	 *
//	 * @param	array		FlexForm data
//	 * @param	string		Field name to extract. Can be given like "test/el/2/test/el/field_templateObject" where each part will dig a level deeper in the FlexForm data.
//	 * @param	string		Sheet pointer, eg. "sDEF"
//	 * @param	string		Language pointer, eg. "lDEF"
//	 * @param	string		Value pointer, eg. "vDEF"
//	 * @return	string		The content.
//	 */
//	function pi_getFFvalue($T3FlexForm_array,$fieldName,$sheet='sDEF',$lang='lDEF',$value='vDEF')	{
//		$sheetArray = is_array($T3FlexForm_array) ? $T3FlexForm_array['data'][$sheet][$lang] : '';
//		if (is_array($sheetArray))	{
//			return $this->pi_getFFvalueFromSheetArray($sheetArray,explode('/',$fieldName),$value);
//		}
//	}
//
//	/**
//	 * Returns part of $sheetArray pointed to by the keys in $fieldNameArray
//	 *
//	 * @param	array		Multidimensiona array, typically FlexForm contents
//	 * @param	array		Array where each value points to a key in the FlexForms content - the input array will have the value returned pointed to by these keys. All integer keys will not take their integer counterparts, but rather traverse the current position in the array an return element number X (whether this is right behavior is not settled yet...)
//	 * @param	string		Value for outermost key, typ. "vDEF" depending on language.
//	 * @return	mixed		The value, typ. string.
//	 * @access private
//	 * @see pi_getFFvalue()
//	 */
//	function pi_getFFvalueFromSheetArray($sheetArray,$fieldNameArr,$value)	{
//
//		$tempArr=$sheetArray;
//		foreach($fieldNameArr as $k => $v)	{
//			if (t3lib_div::testInt($v))	{
//				if (is_array($tempArr))	{
//					$c=0;
//					foreach($tempArr as $values)	{
//						if ($c==$v)	{
//							#debug($values);
//							$tempArr=$values;
//							break;
//						}
//						$c++;
//					}
//				}
//			} else {
//				$tempArr = $tempArr[$v];
//			}
//		}
//		return $tempArr[$value];
//	}
//}

// NO extension of class - does not make sense here.

?>