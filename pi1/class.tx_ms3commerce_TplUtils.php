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
 * Helper Methods for resolving Templates 
 * 
 *
 * 
 */
class tx_ms3commerce_TplUtils {
	/** @var tx_ms3commerce_template */
	var $template;
	
	var $sliderTemplate;
	
	public function __construct($template)
	{
		$this->template=$template;
	} 
 
	/**
	 * Fetch out  a given subpart (identified by start and end Markers) 
	 * @param type $content
	 * @param type $marker
	 * @return string|array 
	 */
	function getSubpart($content, $marker) 
	{
		$start = strpos($content, $marker);
		if ($start===false)     { return ''; }
		$start += strlen($marker);
		$stop = strpos($content, $marker, $start);
		// Q: What shall get returned if no stop marker is given /*everything till the end*/ or nothing
		if ($stop===false)      { return /*substr($content, $start)*/ ''; }
		$content = substr($content, $start, $stop-$start);
		$matches = array();
		if (preg_match('/^([^\<]*\-\-\>)(.*)(\<\!\-\-[^\>]*)$/s', $content, $matches)===1)      {
			return $matches[2];
		}
		$matches = array();
		if (preg_match('/(.*)(\<\!\-\-[^\>]*)$/s', $content, $matches)===1)     {
			return $matches[1];
		}
		$matches = array();
		if (preg_match('/^([^\<]*\-\-\>)(.*)$/s', $content, $matches)===1)      {
			return $matches[2];
		}
		return $content;
	} 
  
	function trimExplode($delim, $string, $onlyNonEmptyValues=0)
	{
		// This explodes a comma-list into an array where the values are parsed through trim();
		$temp = explode($delim,$string);
		$newtemp=array();
		while(list($key,$val)=each($temp))      {
			if (!$onlyNonEmptyValues || strcmp("",trim($val)))      {
				$newtemp[]=trim($val);
			}
		}
		reset($newtemp);
		return $newtemp;
	}
	
	/**
	 * Replace the Subpart- Marker with subpart content in the passed content
	 * @param type $content (content where subpartmarker exists)
	 * @param type $marker (the subpartmarker)
	 * @param type $subpartContent (the content to resolve the subpartmarker
	 * @param type $recursive (if is recursive ==1)
	 * @param type $keepMarker (if the subpartmarker has to be kept)
	 * @return type String 
	 */
  	function substituteSubpart($content,$marker,$subpartContent,$recursive=1,$keepMarker=0)
	{
		$start = strpos($content, $marker);
		if ($start===false)     { return $content; }
		$startAM = $start+strlen($marker);
		$stop = strpos($content, $marker, $startAM);
		if ($stop===false)      { return $content; }
		$stopAM = $stop+strlen($marker);
		$before = substr($content, 0, $start);
		$after = substr($content, $stopAM);
		$between = substr($content, $startAM, $stop-$startAM);

		if ($recursive) {
			$after = $this->substituteSubpart($after, $marker, $subpartContent, $recursive, $keepMarker);
		}
		
		if ($keepMarker)        {
			$matches = array();
			if (preg_match('/^([^\<]*\-\-\>)(.*)(\<\!\-\-[^\>]*)$/s', $between, $matches)===1)      {
				$before .= $marker.$matches[1];
				$between = $matches[2];
				$after = $matches[3].$marker.$after;
			} elseif (preg_match('/^(.*)(\<\!\-\-[^\>]*)$/s', $between, $matches)===1)      {
				$before .= $marker;
				$between = $matches[1];
				$after = $matches[2].$marker.$after;
			} elseif (preg_match('/^([^\<]*\-\-\>)(.*)$/s', $between, $matches)===1)        {
				$before .= $marker.$matches[1];
				$between = $matches[2];
				$after = $marker.$after;
			} else  {
				$before .= $marker;
				$after = $marker.$after;
			}
		} else  {
			$matches = array();
			if (preg_match('/^(.*)\<\!\-\-[^\>]*$/s', $before, $matches)===1)       {
				$before = $matches[1];
			}
			if (is_array($subpartContent))  {
				$matches = array();
				if (preg_match('/^([^\<]*\-\-\>)(.*)(\<\!\-\-[^\>]*)$/s', $between, $matches)===1)      {
					$between = $matches[2];
				} elseif (preg_match('/^(.*)(\<\!\-\-[^\>]*)$/s', $between, $matches)===1)      {
					$between = $matches[1];
				} elseif (preg_match('/^([^\<]*\-\-\>)(.*)$/s', $between, $matches)===1)        {
					$between = $matches[2];
				}
			}
			$matches = array();
			if (preg_match('/^[^\<]*\-\-\>(.*)$/s', $after, $matches)===1)  {
				$after = $matches[1];
			}
		}

		if (is_array($subpartContent))  {
			$between = $subpartContent[0].$between.$subpartContent[1];
		} else  {
			$between = $subpartContent;
		}
		return $before.$between.$after;
	}
	
	/**
	 * Gets an array of strings containing all of the markers (###) embedded in the specified template.
	 * @param string $template
	 * @return string the text between ### tags a
	 */
	function getMarkerArray($template)
	{
		preg_match_all("/\#\#\#(.{1,512})\#\#\#/U", $template, $matches, PREG_PATTERN_ORDER);
		return array_unique($matches[1]);
	}
  
	/**
	 * Extracts the name and the attribute of the specified marker. 
	 * @param string $marker 
	 * @return array 
	 */	
	function getMarkerParts($marker)
	{
		$parts = array( 'name' => '', 'attr' => '');

		$start = strpos($marker, 'SM_');
		if ($start === false)
			return false;

		$start += strlen('SM_');
		$end = strrpos($marker, '_');

		if ($end === false) {
			$parts["name"] = substr($marker, $start);
			$parts["attr"] = "VALUE";
		} 
		else {
			$parts["name"] = substr($marker, $start, $end - $start);
			$parts["attr"] = substr($marker, $end + 1);

			// checks whether the attribute ends with a number and extracts it
			// allows us to have multiple selects with the same name
			//$matches = array();
			preg_match('/^(\w*)(\d+)$/', $parts['attr'], $matches);
			if (count($matches) > 0)
				$parts['attr'] = $matches[1];
		}
		return $parts;
	}
	
	/**
	 * Calls a  native typo3 Method throughout the Plugin interface
	 * 
	 * @param type $content
	 * @param type $marker
	 * @param type $markContent
	 * @return type 
	 */
	function substituteMarker($content, $marker, $markContent)
	{
		return $this->template->plugin->substituteMarker($content, $marker, $markContent);
	}
	
	/**
	 * Resolve markers within a content  with values contained in a values Array
	 * @param type $content
	 * @param type $markContentArray
	 * @param type $wrap
	 * @param type $uppercase
	 * @return type string 
	 */ 
	function substituteMarkerArray($content,$markContentArray,$wrap='',$uppercase=0)
	{
		if (is_array($markContentArray))        {
			$markContentArray['###MS3C_HASH###'] = '###';
			reset($markContentArray);
			$wrapArr=$this->trimExplode('|',$wrap);
			while(list($marker,$markContent)=each($markContentArray))       {
				if($uppercase)  $marker=strtoupper($marker);
				if(strcmp($wrap,''))            $marker=$wrapArr[0].$marker.$wrapArr[1];
				$content=str_replace($marker,$markContent,$content);
			}
		}
		return $content;
	}
	
  /**
	 * Read and set fullsearch_feature_name
	 */
	/* UNUSED
	function setFullSearchFeatureId()
	{
		if (!array_key_exists('fullsearch_feature_name', $this->template->conf))
			return;

		$featureId = $this->getFeatureIdByName($this->template->conf['fullsearch_feature_name']);
		if (!$featureId)
			return;

		$this->template->FullSearchFeatureId = $featureId;

		return;
	}
	 */
	
	/**
	 * Tests for the include marker.
	 * @param type $marker 
	 * @return BOOLEAN Returns TRUE or FALSE
	 */
	function isIncludeMarker($marker)
	{
		return strpos($marker, 'INCLUDE_') === 0;
	}
	
	/**
	 * Tests for the custom include marker.
	 * @param type $marker 
	 * @return BOOLEAN Returns TRUE or FALSE
	 */
	function isCustomIncudeMarker($marker)
	{
		return strpos($marker, 'CUSTOM_INCLUDE_') === 0;
	}
	
	/**
	 * Returns the include name 
	 * @param type $marker
	 * @return type 
	 */
	function getIncludeName($marker)
	{
		$name = '';
		$start = strlen('INCLUDE_');
		$name=substr($marker, $start);
		return $name;
	}
	
	function getJSStartupFunctionCall( $func )
	{
		switch ( $this->template->guiversion ) {
			case tx_ms3commerce_gui_version::mootools1_4:
				$call = 
					"window.addEvent('domready', function() {
						$func
					});";
				break;
			case tx_ms3commerce_gui_version::jquery1_6:
				$call = 
					"jQuery(function() {
						$func
					});";
        
				break;
		}
		
		return
			"<script type=\"text/javascript\">
			$call
			</script>";
	}
	
	function getHtmlJSInclude( $forDynamic, tx_ms3commerce_template $template=null)
	{
		$template = $this->template; //intellisense
		$files = array();
		switch ( $template->guiversion ) {
			case tx_ms3commerce_gui_version::mootools1_4:
				if ($forDynamic) {
					$files = array(
						$template->getPluginRoot()."js/mS3CCommon.js",
						$template->getPluginRoot()."js/mS3CMootools.js",
					);
				} else {
					$files = array(
						$template->getPluginRoot()."js/mS3CStaticMootools.js"
					);
				}
				
				break;
			case tx_ms3commerce_gui_version::jquery1_6:
				if ($forDynamic) {
					$files = array(
						$template->getPluginRoot()."js/mS3CCommon.js",
						$template->getPluginRoot()."js/mS3CJQuery.js",
						$template->getPluginRoot()."js/jquery.json-2.3.min.js"
					);
				} else {
					$files = array(
						$template->getPluginRoot()."js/mS3CStaticJQuery.js"
					);
				}
				
				break;
		}
		
		$custFile = $template->getPluginRoot().'js/mS3CCustom.js';
		if (is_file($custFile)) {
			$files[] = $custFile;
		}
		
		$content = '';
		foreach ( $files as $f )
		{
			$content .= "<script src=\"$f\" type=\"text/javascript\"></script>";
		}
		
		return $content;
	}
	
	function getSliderView($ctrlName, $slidertype = 'slider')
	{
		$this->loadSliderTemplate();
		if ($slidertype == 'range')
		{
			$templatetype = '###RANGETEMPLATE###';
		}
		else
		{
			$templatetype = '###SLIDERTEMPLATE###';
		}
		$templateText = $this->getSubpart($this->sliderTemplate, $templatetype);
		if (!empty($templateText))
		{
			$markers = array('###MS3C_DYN_CONTROLID###' => $ctrlName);
			$content = $this->substituteMarkerArray($templateText, $markers);
		}
		else
		{
			$content = '<table id="'.$ctrlName.'"><tr><td class="mS3CMinValue"></td>
								<td class="slider_center" style="padding-left:6px; padding-right:6px" width="100%"><div class="mS3CSlider"></div></td>
								<td class="mS3CMaxValue"></td>
								</tr>
								<tr>
								<td></td>
								<td class="mS3CValue" style="text-align:center"></td>
								<td></td>
					</tr></table>';
		}
		return $content;
	}
	
	function loadSliderTemplate()
	{
		if ( isset($this->sliderTemplate) ) {
			return;
		}
		
		$this->sliderTemplate = $this->template->plugin->fileResource($this->template->conf['sliderTemplateFile']);
	}
	

	
	/**
	 * Check every group in the array against visibility
	 * @param type $ids  array of groups ids
	 * @return an Array of visible groups
	 */
	function filterGroupVisibilityList($ids){
		$visibleGroups=array();
		foreach($ids as $grId){
			$visible=$this->checkGroupVisibility($grId);
			if($visible==true){
				$visibleGroups[]=$grId;
			}
		}
	return $visibleGroups;
	}
	
	/**
	 * Check every Product in the array against visibility
	 * @param type $ids  array of Products ids
	 * @return an Array of visible Products
	 */	
	function filterProductVisibilityList($prods){
		$visibleProducts=array();
		foreach($prods as $val){
			$visible=$this->checkProductVisibility($val);
			if($visible==true){
				$visibleProducts[]=$val;
			}
		}
	return $visibleProducts;
	}
	
	/**
	 * Check every Document in the array against visibility
	 * @param type $ids  array of Documents ids
	 * @return an Array of visible Documents
	 */	
	function filterDocumentVisibilityList($ids){
		$visibleDocuments=array();
		foreach($ids as $docId){
			$visible=$this->checkDocumentVisibility($docId);
			if($visible==true){
				$visibleDocuments[]=$docId;
			}
		}
	return $visibleDocuments;
	}		
	
	/** 
	 * Checks if group is visible 
	 * @param type $id Group id to be checked if visible
	 * @return true or false
	 */
	function checkGroupVisibility($groupId)
	{
		$custVis = $this->template->custom->customCheckGroupVisibility($groupId);
		if ($custVis === false) {
			return false;
		} else if ($custVis === true) {
			return true;
		}
		if ($this->template->conf['hideEmptyGroups']) {
			if ($this->template->dbutils->checkGroupEmpty($groupId)) {
				return false;
			}
		}
		$visible=true;
		$restr=$this->template->getGroupValue($groupId, $this->template->restrictionFeatureId, true);
		$rights=$this->template->getGroupValue($groupId, $this->template->userRightsFeatureId, true);
		$visible = $this->checkVisibility($restr, $rights);
		return $visible;
	}
	
	/** 
	 * Checks if Product is visible 
	 * @param type $id Product id to be checked if visible
	 * @return true or false
	 */
	function checkProductVisibility($prodId)
	{
		$custVis = $this->template->custom->customCheckProductVisibility($prodId);
		if ($custVis === false) {
			return false;
		} else if ($custVis === true) {
			return true;
		}
		$visible=true;		
		$restr=$this->template->dbutils->getProductValue($prodId, $this->template->restrictionFeatureId, true);
		$rights=$this->template->dbutils->getProductValue($prodId, $this->template->userRightsFeatureId, true);
		$visible = $this->checkVisibility($restr, $rights);
		return $visible;
	}
	
	/** 
	 * Checks if document is visible 
	 * @param type $id Document id to be checked if visible
	 * @return true or false
	 */
	function checkDocumentVisibility($docId)
	{
		$custVis = $this->template->custom->customCheckDocumentVisibility($docId);
		if ($custVis === false) {
			return false;
		} else if ($custVis === true) {
			return true;
		}
		$visible=true;
		$restr=$this->template->dbutils->getDocumentValue($docId, $this->template->restrictionFeatureId, true);
		$rights=$this->template->dbutils->getDocumentValue($docId, $this->template->userRightsFeatureId, true);
		$visible = $this->checkVisibility($restr, $rights);
		return $visible;
	}
	
	/** 
	 * Checks if Feature is visible within a Element(Product,Group,Document)
	 * @param type $id Document id to be checked if visible
	 * @return true or false
	 */
	function checkFeatureVisibility($featureId,$smzsmRights = NULL)
	{
		$custVis = $this->template->custom->customCheckFeatureVisibility($featureId);
		if ($custVis === false) {
			return false;
		} else if ($custVis === true) {
			return true;
		}
		if($featureId == 0){
			return false;
		}
		$visible=true;
		
		if($smzsmRights != null){
			$rights = $smzsmRights;
		}else{
			$rights = $this->template->dbutils->getFeatureRecord($featureId)->UserRights;
		}
			
		$visible = $this->checkVisibility('', $rights);
		return $visible;
	}
	
	
	function checkVisibility($restriction, $rights)
	{
		$visible = true;
		if ($restriction != "") {
			$visible = $this->checkVisibilityMatch($restriction, $this->template->restrictionValues, true);
		}
		if ($visible && $rights != "") {
			$visible = $this->checkVisibilityMatch($rights, $this->template->userRightsValues, false);
		}
		return $visible;
	}
	
	/**
	 * @Check object(group,product,document) rights against site restriction params
	 * @param semicolon separated list of object rights
	 * @return true or false
	 */
	function checkVisibilityMatch($value,$list,$visibleIfEmptyList)
	{
		//if no values exist for comparisson then is VISIBLE
		// no feature values =>visible
		if(count($list)>0){
			if (strpos($value, MS3C_MULTIVALUE_SEPARATOR) !== false) {
				$valueArray=explode(MS3C_MULTIVALUE_SEPARATOR,$value);
			} else {
				$valueArray=explode(';',$value);
			}
			$valueArray=array_filter($valueArray);
			foreach($valueArray as $v){
				//if one of the features rights match at least one of the config rights then=VISIBLE
				if(in_array($v, $list)){
					return true;
				}
			}
			// if both params exist and no correspondency found = NOT VISIBLE
			return false;
		}
		return $visibleIfEmptyList;	
	}
}
?>
