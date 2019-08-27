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

define('RELATIVE_SEARCH_SCRIPT_PATH','pi1/tx_ajaxsearchresults.php');

require_once("class.tx_ms3commerce_template.php");
/**
 * Generates control element and their corresponding JavaScript functions  for a dynamic search 
 * 
 * 
 * @see getAjaxViewContent(),replaceFormInitMarker(),getControlRegister() 
 */

class tx_ms3commerce_ajaxbuilder
{
	/** @var tx_ms3commerce_pi1 */
	var $pi;
	var $conf;
	/** @var tx_ms3commerce_template */
	var $template;
	var $p;
	var $nextControlId=0;
	//var $gui;
	static $ajaxFilters=array();
	static $needResult=0;
	static $resItemPart="";
	static $inhFilters="";
	
	public function __construct($template, $conf) {
		$this->template = $template;
		$this->p = $template->p;
		$this->pi = $template->plugin;
		$this->conf = $conf;
		return $this;
	}
/**
 * Retrieves the control panel and result panel for ajaxsearch Subpart
 * @see getControlPanelView(),getResultPanelView(), replaceFormInitMarker()
 * @return type string 
 */
	function getAjaxViewContent()
	{
		$content = $this->pi->getTemplate("AJAXSEARCH");
		$content = $this->getControlPanelView( $content );
		$content = $this->getResultPanelView( $content );
		$content = $this->replaceFormInitMarker( $content );		
		return $content;
	}
	/**
	 * Initialize the ajax form  with features to be selected (prefill it if configured), check wich features are not selectable,
	 * add a filter form,check if there are inherited filters from previous search
	 * finaly return the filled AJAXSEARCH subpart 
	 * @param type $content
	 * @return type string
	 */
	function replaceFormInitMarker($content)
	{
		$markerArr=$this->template->tplutils->getMarkerArray($content);
		if(in_array("MS3C_INIT_FORM_PLAIN", $markerArr)|| in_array("MS3C_INIT_FORM", $markerArr))
		{	 
			$template = $this->template; //intellisense
			if ($this->template->noSelectFeatureId == null)
			{
				$noSelectFeatureText = '---   PLEASE SELECT   ---';
			}
			else
			{
				$noSelectFeatureText = $this->template->getFeatureValue($this->template->noSelectFeatureId, 'title', $this->template->languageId);
			}
			if ($this->template->emptySelectFeatureId == null)
			{
				$emptySelectFeatureText = 'No Selection Possible';
			}
			else
			{
				$emptySelectFeatureText = $this->template->getFeatureValue($this->template->emptySelectFeatureId, 'title', $this->template->languageId);
			}
							
			if($template->conf["initializeAJAX"])
			{				
				$initFilters=true;
				$selection = stripslashes($_POST['mS3CInhFilters']);
				$selection=  json_decode($selection);
				$content=$this->PreFill(self::$ajaxFilters,$template->productGroupId,$content,$selection);
			
			}else{
				//include filterform 
				$initFilters=0;
			}
			$content.=$this->getFilterForm($_POST['mS3CInhFilters']);
			$initPlain = 'mS3CInitForm(
						'.$template->marketId.', 
						'.$template->languageId.', 
						'.$template->currentMenuId.', 
						"type='.MS3C_AJAX_SEARCH_PAGETYPE.'",
						'.$template->detailPageId.',
						{noselect:"'.$noSelectFeatureText.'", emptyselect:"'.$emptySelectFeatureText.'"},
						'.$template->itemsPerPage.',
						"'.$template->conf['result_types'].'",
						"'.$initFilters.'");';

			$initCall = $template->tplutils->getJSStartupFunctionCall($initPlain);	

			$subs = array(
				'###MS3C_INIT_FORM###' => $initCall,
				'###MS3C_INIT_FORM_PLAIN###' => $initPlain
			);
			$content=$template->tplutils->substituteMarkerArray($content, $subs);
			return $content;			
		}
		return $content;
		
	}
	/**
	 * 
	 * @param type $content
	 * @return type string
	 */
	function replaceGeneralTemplateParts($content) {
		// Add some more content things
		$content = $this->template->fillBreadcrumbSubpart($content);
		
		$self = $this->template->tplutils->getSubpart($content, "###SELF###");
		if (strlen($self) > 0) {
			$selfMarker=$this->template->tplutils->getMarkerArray($self);
			$selfMarker = $this->template->fillGroupMarkerContentArray($selfMarker);
			$self=$this->template->tplutils->substituteMarkerArray($self, $selfMarker);
			$content = $this->template->tplutils->substituteSubpart($content, "###SELF###", $self);
		}
		
		$parent = $this->template->tplutils->getSubpart($content, "###PARENT###");
		if (strlen($parent) > 0) {
			$parentId = $this->template->dbutils->getParentGroupIdByMenu($this->template->currentMenuId);
			$parent = $this->template->fillGroupMarkerContentArray($parent, $parentId);
			$content = $this->template->tplutils->substituteSubpart($content, "###PARENT###", $parent);
		}
		
		//Remove content marked as such
		$content = $this->template->tplutils->substituteSubpart($content, "###REMOVE_SUBPART###", '');
				
		return $content;
	}
	/**
	 * Generates the Control panel form, 
	 * @see replaceAjaxControls()
	 * @param type $templateText
	 * @return type string, empty div  if
	 */
	function getControlPanelView($templateText)
	{
		$template = $this->template; //intellisense
		// Get the template
		$content = $template->tplutils->getSubpart($templateText, "###SEARCHVIEW###");
		
		if ( strlen($content) == 0 )
			return $templateText;
		
		$content = $this->replaceGeneralTemplateParts($content);
		
		$contentInner = $this->replaceAjaxControls($content);
		
		$content = $template->tplutils->getHtmlJSInclude( true );
		$content .= '<form name="mS3SearchForm" id="mS3SearchForm" onsubmit="return false" >';
		$content .= $contentInner;
		$content .= '</form>';
		
		return $template->tplutils->substituteSubpart($templateText, "###SEARCHVIEW###", $content);
	}
	/**
	 * Generate the result panel, checks if any Initial Results are needed 
	 * 
	 * @param type $templateText
	 * @return type
	 */
	function getResultPanelView($templateText)
	{
		$resultView = $this->template->tplutils->getSubpart($templateText, "###RESULTVIEW###");
		
		if ( empty($resultView))
			return $templateText;
		
		$resultView = $this->replaceGeneralTemplateParts($resultView);
		
		$markers = $this->template->tplutils->getMarkerArray($resultView);
		
		if (!in_array("RESULT_ITEM_PANEL", $markers)) {
			//1)case return an empty div
			$content = '<div id="mS3CResultPanel"></div>';
		}else if(in_array('INITIAL_RESULT', $markers))
		{			
			$viewPart = $this->template->tplutils->getSubpart($templateText,"###INITIAL_VIEW###");	
			
			if(!empty($viewPart))
			{	
				//is there any view for initial results?
				self::$needResult=0;
				$viewPart='<!-- ###CONTENT### begin-->'.$subPart.'<!-- ###CONTENT### end-->';
				$subPart=$this->template->getListView($this->template->productGroupId, $parentMenuId, $viewPart);
				$content = $this->template->tplutils->substituteMarker($resultView, "###RESULT_ITEM_PANEL###", "mS3CResultPanel");
				$content = $this->template->tplutils->substituteMarker($content, "###INITIAL_RESULT###", $viewPart);
				return $content;
			}
			else
			{	
				//2)case
				self::$needResult=1;
				$resItemPart=$this->template->tplutils->getSubpart($resultView, "###RESULTITEM###");
				self::$resItemPart=$resItemPart;
								
				$content = $this->template->tplutils->substituteMarker($resultView, "###RESULT_ITEM_PANEL###", "mS3CResultPanel");
				$content = $this->template->tplutils->substituteMarker($content, "###INITIAL_RESULT###", "");
			
			} 
		
		}else {
			$content = $this->template->tplutils->substituteMarker($resultView, "###RESULT_ITEM_PANEL###", "mS3CResultPanel");
		}
		
		$content = $this->template->tplutils->substituteSubpart($content, "###RESULTITEM###", '');
		$content = $this->template->tplutils->substituteSubpart($content, "###INITIAL_VIEW###", '');
		$content = $this->template->tplutils->substituteSubpart($templateText, "###RESULTVIEW###", $content);
		return $content;

	}
	
	/**
	 * Generates dynamic functions
	 * @param type $index Filter index for this function
	 * @param type $controlType Which dynamic control to generate for this function
	 * @return type 
	 */
	function getHtmlControl($index,$feature,$controlType)
	{
		switch($controlType)
		{
		case "RANGE":
			$control = $this->getHtmlSetRange($index, $feature);
			break;
		case "SLIDER":
			$control = $this->getHtmlSetSlider($index, $feature);
			break;
		case "SLIDERLESS":
			$control = $this->getHtmlSetSlider($index, $feature, 'Less');
			break;
		case "SLIDERGREATER":
			$control = $this->getHtmlSetSlider($index, $feature, 'Greater');
			break;
		case "SELECT":
			$control = $this->getHtmlSetSelect($index, $feature);
			break;
		case "RADIO":
			$control = $this->getHtmlSetRadio($index, $feature);
			break;
		case "CHECKBOXOR":
			$control = $this->getHtmlSetCheckbox($index, $feature, 'Any');
			break;
		case "CHECKBOXAND":
			$control = $this->getHtmlSetCheckbox($index, $feature, 'All');
			break;
		case "TEXTEQUALS":
			$control = $this->getHtmlSetTextInput($index, $feature, 'Equals');
			break;
		case "TEXTCONTAINS":
			$control = $this->getHtmlSetTextInput($index, $feature, 'Contains');
			break;
		case "TEXTNUMBER":
			$control = $this->getHtmlSetTextInput($index, $feature, 'EqualsNumber');
			break;
		case "NUMBERLESS":
			$control = $this->getHtmlSetTextInput($index, $feature, 'Less');
			break;
		case "NUMBERGREATER":
			$control = $this->getHtmlSetTextInput($index, $feature, 'Greater');
			break;
		case "BETWEEN":
			$control = $this->getHtmlSetTextInput($index, $feature, 'Between');
			break;
		case "MINMAX":
			$control = $this->getHtmlSetTextInputWithTwoFields($index, $feature, 'Between');
			break;
		case "INTERSECT":
			$control = $this->getHtmlSetTextInputWithTwoFields($index, $feature, 'Intersect');
			break;
		default:
			$control = "UNKNOWN CONTROL TYPE: $controlType";
		}
		return $control;
	}
	
	/**
	 * Replaces all SM and SMZ markers, and finds out, which are
	 * relevant for AJAX search
	 * @param string $template The template
	 * @return mixed array ret[0] is replaced template,
	 * ret[1] is array of features => control type for ajax search
	 */
	function replaceAjaxControls($templateText)
	{
		$template = $this->template; //intellisense
		$markers = $template->tplutils->getMarkerArray($templateText);
		
		// Load for SESSION Features
		$sessionParams = $this->template->formbuilder->getSearchParams();
		
		// Resolve all SMZs
		foreach ($markers as $marker)
		{
			if ( $template->smz->isSMZMarker($marker) )
			{
				$smzcontent = $template->smz->substituteSMZRecursive($template->productGroupId, tx_ms3commerce_constants::ELEMENT_GROUP, $marker);
				$templateText = $template->tplutils->substituteMarker($templateText, "###$marker###", $smzcontent);
			}
		}
		
		// Get resolved markers
		$markers = $template->tplutils->getMarkerArray($templateText);
		
		$substitutions = array();
		$defaultMarkers = array();
		$index = $this->nextControlId;
		// Filter out AJAX markers
		foreach ($markers as $marker)
		{
			$parts = $template->tplutils->getMarkerParts($marker);
			$type = strtoupper($parts['attr']);
			if(strpos($parts['name'],";")!==false)
			{
				$feature = explode(";", $parts['name']);
			}
			else
			{
				$feature = $parts['name'];
			}
			switch ($type)
			{
			case 'SLIDER':
				
			case 'RANGE':
			case 'SELECT':
			case 'RADIO':
			case 'CHECKBOXOR':
			case 'CHECKBOXAND':
			case 'TEXTEQUALS':
			case 'TEXTCONTAINS':
			case 'TEXTNUMBER':
			case 'NUMBERLESS':
			case 'NUMBERGREATER':
			case 'SLIDERLESS':
			case 'SLIDERGREATER':
			case "BETWEEN":
			case "MINMAX":
			case "INTERSECT":
				$substitutions["###$marker###"] = $this->getHtmlControl($index++, $feature, $type);
				$filters[]=$this->template->dbutils->getFeatureIdByName($feature);
				break;
			
			case "SESSION":
				// Get Infos from Session for this Feature!
				
				$substitutions["###$marker###"] = $this->getHtmlSessionInput($index++, $feature, $sessionParams);
				break;
			
			case "CUSTOMFILTER":
				// Feature can be anything, not only a Feature
				$substitutions["###$marker###"] = $this->getHtmlCustomControl($index++, $feature);
				$filters[] = 'Custom';
				break;
			
			default:
				$defaultMarkers[] = $marker;
				break;
			}
		}
		
		$this->nextControlId = $index;
		
		// Make the default substitutions
		$subs = $template->fillGroupMarkerContentArray($defaultMarkers, $template->productGroupId);
		
		//if controls have to be preinitialized remember the filters
		if($template->conf["initializeAJAX"]) {
			if (!self::$ajaxFilters) {
				self::$ajaxFilters = array();
			}
			self::$ajaxFilters = array_merge($filters, self::$ajaxFilters);
		}
		
		$substitutions = array_merge($substitutions, $subs);
		$content = $template->tplutils->substituteMarkerArray($templateText, $substitutions);
		return $content;
	}
	
	function registerCustomFilter()
	{
		//if controls have to be preinitialized remember the filters
		if($this->template->conf["initializeAJAX"]) {
			if (!self::$ajaxFilters) {
				self::$ajaxFilters = array();
			}
			self::$ajaxFilters[] = 'Custom';
		}
		return $this->getNextControlId();
	}
	
	function getNextControlId() {
		return $this->nextControlId++;
	}
	
	function getHtmlNullControl( $idx, $feature ) {
		$controlId = $this->getControlName($idx);
		$html = '<div id="'.$controlId.'" class="mS3CControl mS3CNullControl"></div>';
		$html .= $this->getNullControlRegister( $idx );
		return $html;
	}
	
	function getHtmlSetTextInput( $idx , $feature, $type)
	{
		$controlId = $this->getControlName($idx);
		$html = '<div id="'.$controlId.'" class="mS3CControl">';
		$html .= '<input class="textinput" type="text" onblur="mS3CControlChanged('.$idx.')" name="'.$controlId.'">';
		$init = 'mS3CKeyHandler("'.$controlId.'")';
		$html .= $this->getControlRegister( $idx, $feature, 'Text', $type, $init);
		$html .= '</div>';
		return $html;
	}
	
	function getHtmlSessionInput( $idx , $feature, $sessionParams)
	{
		foreach($sessionParams->Selection as $sel)
		{
		$type = $sel->Type; //Example, Type can be Contains, Equals, etc.
		$value = implode (";", $sel->Value); 
		}
		$controlId = $this->getControlName($idx);
		$html .= '<input class="textinput" type="hidden" id="'.$controlId.'" value="'.$value.'">';
		$html .= $this->getSessionControlRegister( $idx, $feature, 'SESSION', $type, $value);
		return $html;
	}
	
	function getHtmlCustomControl( $idx, $data, $innerHtml = '', $init = '' )
	{
		$controlId = $this->getControlName($idx);
		$html = '<div id="'.$controlId.'" class="mS3CControl mS3CCustomControl">';
		$html .= $innerHtml;
		$html .= $this->getControlRegister( $idx, /*$data*/'Custom', 'Custom', 'Custom', $init );
		$html .= '</div>';
		return $html;
	}
	
	function getHtmlSetTextInputWithTwoFields( $idx , $feature, $type)
	{
		$controlId = $this->getControlName($idx);
		$html = '<div id="'.$controlId.'">';
		$html .= '<input class="field1" type="text" onblur="TextFieldsChange('.$idx.',\''.$controlId.'\')">';
		$html .= '<input class="field2" type="text" onblur="TextFieldsChange('.$idx.',\''.$controlId.'\')" ></div>';
		$init = 'mS3CKeyHandler2Fields("'.$controlId.'")';
		$html .= $this->getControlRegister( $idx, $feature, 'TextFields', $type, $init);
		return $html;
	}
	
	function getHtmlSetSelect( $idx, $feature, $noSelectFeatureText=null )
	{
		// TODO Translations?
		if ($this->template->noSelectFeatureId == null)
		{
			$noSelectFeatureText = '---   PLEASE SELECT   ---';
		}
		else
		{
			$noSelectFeatureText = $this->template->getFeatureValue($this->template->noSelectFeatureId, 'title', $this->template->languageId);
		}
		//$noSelectFeatureText=isset($this->template->noSelectFeatureId) ? $noSelectFeatureText:'---   PLEASE SELECT   ---';
		$controlId = $this->getControlName($idx);
		
		$html = '<div id="'.$controlId.'" class="mS3CControl">';
		$html .= '<select size="1">';
		$html .= '<option value="">'.$noSelectFeatureText.'</option>';
		
		// TEST
		//for ($i = 0; $i < 4; $i++) {
		//	$html .= '<option value="'.$i.'"/>Wert #'.$i.'</option>';
		//}
		
		$html .= '</select>';
		$html .= '</div>';
		$html .= $this->getControlRegister( $idx, $feature, 'Select', 'Equals' );
		
		return $html;
	}
	
	function getHtmlSetCheckbox( $idx, $feature, $type)
	{
		$controlId = $this->getControlName($idx);
		$html = '<div id="' . $controlId . '" class="mS3CCheckBox mS3CControl">';

		// TEST
		//for ($i = 0; $i < 4; $i++) {
		//	$html .= '<label class="checked"><input type="checkbox" name="'.$controlId.'_val_'.$i.'" value="'.$i.'"/>Wert #'.$i.'</label>';
		//}
		
		$html .= '</div>';
		$html .= $this->getControlRegister( $idx, $feature, 'Checkbox', $type );
		return $html;
	}
	
	function getHtmlSetRadio( $idx, $feature )
	{
		$controlId = $this->getControlName($idx);
		$html = '<div id="' . $controlId . '" class="mS3CRadio mS3CControl">';
		
		// TEST
		//for ($i = 0; $i < 4; $i++) {
		//	$cls = $i==1 ? 'selected' : 'unselected';
		//	$html .= '<label class="'.$cls.'"><input type="radio" name="'.$controlId.'_radiogroup" value="'.$i.'"/>Option '.$i.'</label>';
		//}
		
		$html .= '</div>';
		$html .= $this->getControlRegister( $idx, $feature, 'Radio', 'Equals' );
		return $html;
	}
	
	function getHtmlSetSlider( $idx, $feature, $type = null)
	{
		if ($type == null)
		{
			if (!is_array($feature))
			{
				$type = 'EqualsNumber';
			}
			else
			{
				$type = 'Between';
			}
		}
		$html = $this->template->tplutils->getSliderView($this->getControlName($idx), 'slider');
		$init = "mS3CInitSlider($idx);";
		$html .= $this->getControlRegister($idx, $feature, 'Slider', $type, $init);
		return $html;
	}
	
	function getHtmlSetRange( $idx, $feature )
	{
	
		if (!is_array($feature))
		{
			$type = 'Between';
		}
		else
		{
			$type = 'Intersect';
		}
		
		$html = $this->template->tplutils->getSliderView($this->getControlName($idx), 'range');
		$init = "mS3CInitRange($idx);";
		$html .= $this->getControlRegister($idx, $feature, 'Range', $type, $init);
		
		return $html;
	}
	
	function getControlName( $controlId, $postfix = null )
	{
		return ($postfix === null) 
			? ('mS3CControl_' . $controlId) 
			: ('mS3CControl_' . $controlId . '_' . $postfix);
	}
	
	function getControlRegister( $idx, $feature, $ctrlType, $compareType, $init = "" )
	{
		$multi = 'false';
		if ($ctrlType == 'Custom') {
			$feature = "['$feature']";
		} else if (is_array($feature)) {
			foreach($feature as $key=>$value){
				$feature[$key]=$this->template->dbutils->getFeatureIdByName($value);
			}
			$feature = "['".implode("','", $feature)."']";
		} else {
			$featureid = $this->template->dbutils->getFeatureIdByName($feature);
			$feat = $this->template->dbutils->getFeatureRecord($featureid);
			if (isset($feat)) {				
				$multi = $feat->IsMultiFeature ? 'true' : 'false';
				
			}
			$feature = "['$featureid']";
		}
		
		return $this->template->tplutils->getJSStartupFunctionCall(
				"mS3CRegisterControl( $idx, $feature, '$ctrlType', '$compareType', $multi );
				$init"
				);
	}
	
	function getSessionControlRegister( $idx, $feature, $ctrlType, $compareType)
	{
		
		
		if (is_array($feature)) {
			
			foreach($feature as $key=>$value){
				$feature[$key]=$this->template->dbutils->getFeatureIdByName($value);
			}
			
			$feature = "['".implode("','", $feature)."']";
		
			
		} else {
			$featureid = $this->template->dbutils->getFeatureIdByName($feature);
			//$feat = $this->template->dbutils->getFeatureRecord($featureid);
			
			$feature="['$featureid']";

	}
		
		
		return $this->template->tplutils->getJSStartupFunctionCall("mS3CRegisterControl( $idx, $feature, '$ctrlType', '$compareType',false);");
	}
	
	function getNullControlRegister( $idx )
	{
		return $this->template->tplutils->getJSStartupFunctionCall(
				"mS3CRegisterControl( $idx, [], 'null', 'null', false );"
				);
	}
	
			
	/**
	 * Preprocessor ajax search Request Object
	 * @return type
	 */
	function getJsonEncodeRes() {
						
		$start = microtime(true);
		header("Content-type: text/plain");
		
		// Typo3 always with slashes in POST 
		$query = $_POST["query"];
		$query = stripslashes($query);
		
		$request = json_decode($query);
		$request->WithHierarchy=$this->template->conf['with_hierarchy'];		
		//add Results array to Request Object out of the template
		$prodTemplate=$this->getProdTemplate();
		$tempResults=$this->template->getSearchResultItems($prodTemplate);
		
		//masking the feature array values and then convert them to featuresId		
		foreach($tempResults as $key=>$value){
			$tempResults[$key]=$this->template->db->sql_escape($value,false);
			$tempResults[$key]=$this->template->dbutils->getFeatureIdByName($value,true);
		}
		
		//if $request has results merge 
		$request->Results = array_merge($tempResults,$request->Results);
		$request->Results = array_unique($request->Results);
		$contTemplate=$prodTemplate;
 
		if(array_key_exists('result_types', $this->template->conf)) {
			$result_types = explode(';', $this->template->conf['result_types']);
			//convert all values to lowercase
			$request->ResultTypes = array_map('strtolower', $result_types);		
		} else{
			$request->ResultTypes = array();
		}
		
		if ($this->conf['include_pure_results'] != 1) {
			$request->includeFeatureValues = false;
			$request->includeLinks = false;
		} else {
			$request->includeFeatureValues = true;
			$request->includeLinks = true;
		}
		
		$result=$this->getResults($request);
		
		$layout=$this->getResultLayout($result,$contTemplate);
		
		$result->ResultLayout=$layout;
		
		if ($this->conf['include_pure_results'] != 1) {
			$result->Product = null;
			$result->Document = null;
			$result->Group = null;
		}
		$end = microtime(true);
		$el = $end-$start;
		$result->TotalTime = $el;
		return json_encode($result);
	}
	
	
	function getResultLayout($result,$contTemplate){
		//add result layout
		$layoutPaginated = $this->getResultsPaginationTemplate($result, $contTemplate);
		$layoutWithResults = $this->template->getResultsOutputTemplate($result,$layoutPaginated);
		$link=" onclick='mS3CSubmitFiltered(this);return false;'";
		$layout = $this->template->tplutils->substituteMarker($layoutWithResults,"###FILTERED_LINK###",$link);
		
		return $layout;
	}
	
	function getProdTemplate()
	{
		 
		$templ = $this->template->plugin->getTemplate('###AJAXSEARCH###');
		//Find the if an Initial Result exist(empty string means exists)
		$markerArr=$this->template->tplutils->getMarkerArray($templ);
			
		// Find the output-markers (for layouting products)
		$templateContent = $this->template->tplutils->getSubpart($templ, '###RESULTITEM###');
		return $templateContent;		
	}
	
	
	
	function PreFill($filter,$groupId,$content,$selection=null)
	{			
		
		
		$template=  $this->template;
			
		$request=$this->template->formbuilder->getSearchRequestObject($filter, $template->searchMenuIds);
		if($selection){
			$request->Selection=$selection;
		}
		$request->UpdateType="all";
		$request->WithHierarchy=$this->template->conf['with_hierarchy'];
		$request->Limit = $this->template->itemsPerPage;
		
		if(array_key_exists('result_types', $this->template->conf)) {
			$result_types = explode(';', $this->template->conf['result_types']);
			//convert all values to lowercase
			$request->ResultTypes = array_map('strtolower', $result_types);		
		} else{
			$request->ResultTypes = array();
		}
		$request->includeFeatureValues = false;
		$result=$this->getResults($request);
		
		//prefill controls	
		$content.=$this->preFillControls($result->Filter);
		$content.=$this->preFillResult($result);
		
		return $content;
	}
	
	function getFilterForm($inhFilters=null){
		$inhFilters=  stripslashes($inhFilters);
		$form=	"<form id='mS3CFilterForm' name='mS3CFilterForm' method='POST' action=''>\n".
						"<input type='hidden' id='mS3CInhFilters' name='mS3CInhFilters' value='$inhFilters'>\n".
					"</form>\n";
		
		return $form;
	}
	
	function getResults($request){
		if (array_key_exists('narrow_to_feature_name', $this->template->conf))
		{	
			$delimiter = '|';
			$featureName = $this->template->conf['narrow_to_feature_name'];
			$featureId = $this->template->dbutils->getFeatureIdByName($featureName);
			if ($featureId > 0)
			{
				$selInfo=new stdClass();
				
				$selInfo->Feature = array($featureId);
				$selInfo->IsMultiFeature = false;
				$selInfo->Type = 'Any';
				$selInfo->Value = array();
				
				$featureValues = explode($delimiter, $this->template->conf['narrow_to_feature_values']);
				if($featureValues)
				{
					$selInfo->Value = $featureValues;
				}

				$request->Selection[] = $selInfo;
			}		
		}

		// go for products 
		$result = $this->template->search->runQuery($request);
		
		//check if consolidatedResults are needed
		if($this->template->conf['consolidateSearchResults'])
		{
			if ($this->template->conf['consolidateSearchResults'] == 'parent') {
                $result = $this->template->search->consolidateResultsParent($result);
            } else {
                $result = $this->template->search->consolidateResults($result, $this->template->searchMenuIds);
            }
		}
		return $result;
	}
	
	function preFillResult($result)
	{
		if(self::$needResult)
		{	
			$layout=$this->getResultLayout($result, self::$resItemPart);
			//$layout=$this->template->getResultsPaginationTemplate($result, 	self::$resItemPart);
			//$layout=$this->template->getResultsOutputTemplate($result, 	$layout);
			$layoutEncoded=  json_encode($layout);
		
			if ($this->template->conf['include_pure_results'] != 1) 
			{
				$result->Product = null;
				$result->Document = null;
				$result->Group = null;
			}

			$prodEncoded= json_encode($result->Product);
			$scriptStr="<script type=\"text/javascript\"> \n".
					"function mS3CInitializeResults(){ \n".
					"   var prods = $prodEncoded;\n".
					"	var layout= $layoutEncoded;\n".
					"	var start=0 ;\n".
					"	var end=$result->Total ;\n".
					"	var total=$result->Total ;\n".
					"	mS3CHandleResponseProducts(layout, prods, start, end, total); \n".
					"} \n".
				"</script>";
			return $scriptStr;//add jscode with results values
			
		}else{
			$scriptStr="<script type=\"text/javascript\"> \n".
				"function mS3CInitializeResults(){ \n".
				"} \n".
			"</script>";
			return $scriptStr;//add jscode with results values
		}
	}
	
	function preFillControls($Filter){
		// initializing JsCode
		$values=json_encode($Filter);
		$scriptStr="<script type=\"text/javascript\"> \n".
				"function mS3CInitializeFilterValues(){\n".
				"	mS3CHandleResponseFilters(-1,$values); \n".
				"	}\n".				
				"</script>";
		return $scriptStr;
	}

	public function getResultsPaginationTemplate($result, $template)
	{
		$templateHeader = $this->template->tplutils->getSubpart($template, '###HEADER###');
		$templateFooter = $this->template->tplutils->getSubpart($template, '###FOOTER###');
		
		if (empty($templateHeader) && empty($templateFooter)) {
			return $template;
		}
		
		$pagination = $this->fillPagerMarkerContentArray($result->Total, $result->Beginning);
		$marker = array();
		if (!empty($templateHeader)) {
			$templateHeader = $this->template->tplutils->substituteMarkerArray(
					$templateHeader,
					$pagination
					);

			// Get any feature markers that may be used.
			$marker = $this->template->tplutils->getMarkerArray($templateHeader);
		}
		if (!empty($templateFooter)) {
			$templateFooter = $this->template->tplutils->substituteMarkerArray(
					$templateFooter,
					$pagination
					);

			// Get any feature markers that may be used.
			$markerf = $this->template->tplutils->getMarkerArray($templateFooter);
			$marker = array_merge($marker, $markerf);
		}
		
		
		$contentArray = $this->template->fillFeatureMarkerContentArray($marker);
		$headerContent = $this->template->tplutils->substituteMarkerArray($templateHeader, $contentArray);
		$footerContent = $this->template->tplutils->substituteMarkerArray($templateFooter, $contentArray);

		$template = $this->template->tplutils->substituteSubpart($template, '###HEADER###', $headerContent);
		$template = $this->template->tplutils->substituteSubpart($template, '###FOOTER###', $footerContent);
		
		return $template;
	}
	
	function fillPagerMarkerContentArray($count, $start)
	{
		$markerArray = array();

		$markerArray['###PAGE_ITEMCOUNT###'] = $count;
		$markerArray['###PAGE_ITEMBEGIN###'] = ($count > 0) ? $start : $count;
		$markerArray['###PAGE_ITEMEND###'] = ($this->template->itemsPerPage > 0)
			? (($count > 0) ? min($start + $this->template->itemsPerPage - 1, $count) : $count)
			: $count;

		$markerArray['###PAGE_LINKFIRST###'] = 'mS3CPageMove(MS3C_PAGE_POS1);';
		$markerArray['###PAGE_LINKLAST###'] = 'mS3CPageMove(MS3C_PAGE_END);';

		$markerArray['###PAGE_LINKPREVIOUS###'] = 'mS3CPageMove(MS3C_PAGE_UP);';
		$markerArray['###PAGE_LINKNEXT###'] = 'mS3CPageMove(MS3C_PAGE_DOWN);';

		return $markerArray;
	}
}
?>
