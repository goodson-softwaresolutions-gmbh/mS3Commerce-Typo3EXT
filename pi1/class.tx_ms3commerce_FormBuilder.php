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
 * Formbuilder class generates HTML Elements for the Input-SM Markers defined in the searchview.
 * Select {@see getFeatureHtmlSelect()} <br>
 * Checkboxes {@see getFeatureHtmlCheckboxlistOR()}<br> {@see getFeatureHtmlCheckboxlistAND()}<br> 
 * Text {@see getFeatureHtmlText()}<br> 
 * Text with comparisson (textequals,textcontains,textnumber,numbergreater,numberless )<br>{@see getFeatureHtmlTextEdit()}<br>
 * Between {getFeatureHtmlSliderBetween()}<br>
 * Radio {@see getFeatureHtmlRadio()}<br>
 * Range {@see getFeatureHtmlRange()}<br>
 * Slider (){@see getFeatureHtmlSlider()}<br>
 * Slider greater, less {@see getFeatureHtmlSliderContinuous()}<br>
 * MinMax two Text field elements same feature {@see getFeatureHtmlTwoTextfields()}<br><br>
 *
 * Between (two features) {@see getFeatureHtmlTextBetween()}<br>
 * Slider (two features) {@see getFeatureHtmlSliderBetween()}<br>
 * Range (two features) {@see getFeatureHtmlRangeIntersect()}<br>
 * Intersect (two features) {@see getFeatureHtmlTwoTextfields()}<br>
 * 
 * to generate the Input elements it's necesary to know the possible values, 
 * those are determined by a search request {@see getSearchViewContent()}<br> 
 * this search is constrained by Menu id from  $conf
 * 
 * Handle SerachResultviews  {@see getSearchResultViewContent()}<br> 
 */
class tx_ms3commerce_FormBuilder {

	/** @var tx_ms3commerce_template */
	var $template;
	var $inputmarker;
	var $inputmarkerfilter;
	var $sharedResult;
	var $sharedParams;
	var $webSearchKey;
	var $webSearchFeature;
	var $webSearchType;
	var $JSelements = array();
	static $isSearchShopChange = false;

	public function __construct($template) {
		$this->template = $template;
		$this->inputmarker = array();
		$this->inputmarker[] = 'select';
		$this->inputmarker[] = 'checkboxor';
		$this->inputmarker[] = 'checkboxand';
		$this->inputmarker[] = 'textequals';
		$this->inputmarker[] = 'textcontains';
		$this->inputmarker[] = 'numberless';
		$this->inputmarker[] = 'numbergreater';
		$this->inputmarker[] = 'between';
		$this->inputmarker[] = 'radio';
		$this->inputmarker[] = 'text';
		$this->inputmarker[] = 'slider';
		$this->inputmarker[] = 'slidergreater';
		$this->inputmarker[] = 'sliderless';
		$this->inputmarker[] = 'range';
		$this->inputmarker[] = 'minmax';
		$this->inputmarker[] = 'intersect';
		$this->inputmarker[] = 'textnumber';
		$this->inputmarker[] = 'suggest';

		$this->inputmarker[] = 'fulltext';

		$this->inputmarkerfilter = array();
		$this->inputmarkerfilter[] = 'select';
		$this->inputmarkerfilter[] = 'checkboxor';
		$this->inputmarkerfilter[] = 'checkboxand';
		$this->inputmarkerfilter[] = 'radio';
		$this->inputmarkerfilter[] = 'text';
		$this->inputmarkerfilter[] = 'slider';
		$this->inputmarkerfilter[] = 'slidergreater';
		$this->inputmarkerfilter[] = 'sliderless';
		$this->inputmarkerfilter[] = 'range';

		$this->sharedResult = $this->template->conf['sharedSearchResults'];
		$this->sharedParams = $this->template->conf['sharedSearchParams'];
		$this->webSearchKey = $this->template->conf['webSearchKey'];
		$this->webSearchFeature = $this->template->conf['webSearchFeature'];
		$this->webSearchType = $this->template->conf['webSearchType'];
		if (empty($this->webSearchType)) {
			$this->webSearchType = "contains";
		}
	}

	/**
	 * Creates a HTML element containing zero, one or more INPUT elements of 'checkbox' type.
	 * @param int $featureId
	 * @param int $languageId 
	 * @param array $Filter 
	 * @return string HTML checkbox list
	 */
	function getFeatureHtmlCheckboxlistOR($featureId, $Filter) {
		++$this->template->searchNameCounter;
		$content = '<div>';
		for ($i = 0; $i < count($Filter["Values"]); $i++) {
			if (!empty($Filter["Values"][$i])) {
				$content .=
						'<label><input type="checkbox" value="' . $Filter["Values"][$i] . '" name="checkboxor|' . $featureId . '|' . $Filter["Values"][$i] . '">' .
						'<span>' . $Filter["HTMLs"][$i] . '</span></label><br />';
			}
		}
		$content .= '</div>';
		return $content;
	}

	/**
	 * Creates a HTML element containing zero, one or more INPUT elements of 'checkbox' type.
	 * @param int $featureId
	 * @param int $languageId 
	 * @param array $Filter 
	 * @return string HTML checkbox list
	 */
	function getFeatureHtmlCheckboxlistAND($featureId, $Filter) {
		++$this->template->searchNameCounter;
		$content = '<div>';
		for ($i = 0; $i < count($Filter["Values"]); $i++) {
			if (!empty($Filter["Values"][$i])) {
				$content .=
						'<label><input type="checkbox" value="' . $Filter["Values"][$i] . '" name="checkboxand|' . $featureId . '|' . $Filter["Values"][$i] . '">' .
						'<span>' . $Filter["HTMLs"][$i] . '</span></label><br />';
			}
		}
		$content .= '</div>';
		return $content;
	}

	/**
	 * Creates a HTML element containing zero, one or more INPUT elements of 'radio' type.
	 * @param int $featureId
	 * @param int $languageId 
	 * @param array $Filter 
	 * @return string HTML
	 */
	function getFeatureHtmlRadio($featureId, $Filter) {
		++$this->template->searchNameCounter;
		$content = '<div>';
		for ($i = 0; $i < count($Filter["Values"]); $i++) {
			if (!empty($Filter["Values"][$i])) {
				$content .=
						'<label><input type="radio" name="radio|' . $featureId . '|equals" value="' . $Filter["Values"][$i] . '">' .
						'<span>' . $Filter["HTMLs"][$i] . '</span></label><br />';
			}
		}
		/* foreach ($valueArray as $key => $value)
		 */
		$content .= '</div>';
		return $content;
	}

	/**
	 * Creates a HTML SELECT element containing all the values of the specified feature. 
	 * @param int $featureId
	 * @param int $languageId 
	 * @param array $Filter 
	 * @return The HTML content.
	 */
	function getFeatureHtmlSelect($featureId, $Filter) {
		++$this->template->searchNameCounter;
		// Builds the HTML SELECT element.
		$content = '<select name="select|' . $featureId . '|equals">';

		if ($this->template->noSelectFeatureId)
			$content .= '<option value="">'
					. $this->template->getFeatureValue($this->template->noSelectFeatureId, 'title')
					. '</option>';
		else
			$content .= '<option value="">---   PLEASE SELECT   ---</option>';


		for ($i = 0; $i < count($Filter["Values"]); $i++) {
			if (!empty($Filter["Values"][$i])) {
				$content .= '<option value="' . $Filter["Values"][$i] . '">' . $Filter["Values"][$i] . '</option>';
			}
		}


		$content .= '</select>';

		return $content;
	}

	/**
	 * Creates a HTML element containing zero, one element of 'text' type.
	 * @param int $featureId
	 * @param int $languageId 
	 * @param array $Filter 
	 * @return string 
	 */
	function getFeatureHtmlText($featureId, $Filter) {
		++$this->template->searchNameCounter;

		foreach ($Filter["Values"] as $key => $value)
			$content .= $value;

		return $content;
	}

	/**
	 * Creates an INPUT HTML element of type TEXT, that uses a equals, contains, greater and less search mode.
	 * @param int $featureId
	 * @param int $languageId
	 * @param string $type
	 * @return string HTML
	 */
	function getFeatureHtmlTextEdit($featureId, $type) {
		++$this->template->searchNameCounter;

		$content = '<input type="text" name="textedit|' . $featureId . '|' . $type . '">';
		return $content;
	}

	/*
	 * This getFeatureHtmlSuggest function created for Auto Completion in Text input
	 */

	function getFeatureHtmlSuggest($featureId) {

		++$this->template->searchNameCounter;
		$id = "suggest_" . $this->template->searchNameCounter;

		$requestedPhpUri = $_SERVER['REQUEST_URI'];
		if (strpos($requestedPhpUri, '?') !== false) {
			$requestedPhpUri .= '&';
		} else {
			$requestedPhpUri .= '?';
		}
		$requestedPhpUri .= "type=" . MS3C_SUGGEST_PAGETYPE . "&feature=$featureId";
		$inputTypeName = "textedit|$featureId|contains";

		$content = '<input type="text"  id = "' . $id . '" name="' . $inputTypeName . '">';
		$content .= "<script>";
		$content .= <<<EOT
  jQuery(function() {
        var cache = {};
        jQuery( "#$id" ).autocomplete({
            minLength: 2,
			delay: 500,
            source: function( request, response ) {
                var term = request.term;
                if ( term in cache ) {
                    response( cache[ term ] );
                    return;
                }
 
                jQuery.getJSON( "$requestedPhpUri", request, function( data, status, xhr ) {
                    cache[ term ] = data;
                    response( data );
                });
            }
        });
    });
	

	</script>
EOT;


		return $content;
	}

	/**
	 * Creates a Slider
	 * @param int $featureId 
	 * @param int $languageId
	 * @param array $Filter 
	 * @return string HTML
	 */
	function getFeatureHtmlSlider($featureId, $Filter) {
		$id = ++$this->template->searchNameCounter;
		// Empty check needed?
		$slidervalues = array_filter($Filter["Numbers"], "notEmpty");
		$sliderhtmls = array_filter($Filter["HTMLs"], "notEmpty");

		$slidervalues = array_unique($slidervalues, SORT_REGULAR);
		$sliderhtmls = array_unique($sliderhtmls, SORT_REGULAR);

		$sliderhtmls = $this->fixHtmlForJS($sliderhtmls);
		;
		//sort($slidervalues);
		array_multisort($slidervalues, $sliderhtmls);
		$sliderValueJS = implode(", ", array_merge(array('undefined'), $slidervalues));
		$sliderHtmlsJS = implode(", ", array_merge(array('undefined'), $sliderhtmls));
		$func = "mS3CSlider({$id}, [$sliderValueJS], [$sliderHtmlsJS]);";
		$this->JSelements[] = $func;
		$content = $this->getSliderView($slidervalues, $featureId, 'equalsnumber');
		$content = $content . $this->template->tplutils->getJSStartupFunctionCall($func);

		return $content;
	}

	/**
	 * Creates a Slider for Propotion(less, greater) 
	 * @param int $featureId 
	 * @param int $languageId
	 * @param array $Filter 
	 * @param string $type 
	 * @return string HTML
	 */
	function getFeatureHtmlSliderContinuous($featureId, $Filter, $type) {
		$id = ++$this->template->searchNameCounter;

		$slidervalues = array_filter($Filter["Numbers"], "notEmpty");
		$slidervalues = array_unique($slidervalues, SORT_REGULAR);
		sort($slidervalues);
		$content = $this->getSliderView($slidervalues, $featureId, $type);
		$func = 'mS3CSliderContinuous("' . $id . '", ' . $slidervalues[0] . ', ' . end($slidervalues) . ',"' . $type . '");';
		$this->JSelements[] = $func;
		$content = $content . $this->template->tplutils->getJSStartupFunctionCall($func);

		return $content;
	}

	/**
	 * Creates a Between Slider
	 * @param int $featureId 1
	 * @param int $featureId 2
	 * @param int $languageId
	 * @param array $Filters 
	 * @return string HTML
	 */
	function getFeatureHtmlSliderBetween($featureId1, $featureId2, $Filters) {
		$id = ++$this->template->searchNameCounter;
		$slidervalues = array();
		foreach ($Filters as $Filter) {
			$vals = array_filter($Filter["Numbers"], "notEmpty");
			$slidervalues = array_merge($slidervalues, $vals);
		}

		$slidervalues = array_unique($slidervalues, SORT_REGULAR);
		sort($slidervalues);
		$content = $this->getSliderView($slidervalues, $featureId1 . '|' . $featureId2, 'between');
		$func = 'mS3CSliderContinuous("' . $id . '", ' . $slidervalues[0] . ', ' . end($slidervalues) . ');';
		$this->JSelements[] = $func;
		$content = $content . $this->template->tplutils->getJSStartupFunctionCall($func);

		return $content;
	}

	/**
	 * Creates a Range
	 * @param int $featureId 
	 * @param int $languageId
	 * @param array $Filter 
	 * @return string HTML
	 */
	function getFeatureHtmlRange($featureId, $Filter) {
		$id = ++$this->template->searchNameCounter;
		$slidervalues = array_filter($Filter["Numbers"], "notEmpty");
		$sliderhtmls = array_filter($Filter["HTMLs"], "notEmpty");

		$slidervalues = array_unique($slidervalues, SORT_REGULAR);
		$sliderhtmls = array_unique($sliderhtmls, SORT_REGULAR);

		$sliderhtmls = $this->fixHtmlForJS($sliderhtmls);
		//sort($slidervalues);
		array_multisort($slidervalues, $sliderhtmls);
		$sliderValueJS = implode(", ", $slidervalues);
		$sliderHtmlsJS = implode(", ", $sliderhtmls);
		$content = $this->getSliderView($slidervalues, $featureId, 'between', 'range');
		$func = "mS3CRange({$id}, [$sliderValueJS], [$sliderHtmlsJS]);";
		$this->JSelements[] = $func;
		$content .= $this->template->tplutils->getJSStartupFunctionCall($func);

		return $content;
	}

	/**
	 * Creates a Range Intersect
	 * @param int $featureId 1
	 * @param int $featureId 2
	 * @param int $languageId
	 * @param array $Filters 
	 * @return string HTML
	 */
	function getFeatureHtmlRangeIntersect($featureId1, $featureId2, $Filters) {
		$id = ++$this->template->searchNameCounter;
		$slidervalues = array();
		$sliderhtmls = array();
		foreach ($Filters as $Filter) {
			$vals = array_filter($Filter["Numbers"], "notEmpty");
			$slidervalues = array_merge($slidervalues, $vals);
			$vals2 = array_filter($Filter["HTMLs"], "notEmpty");
			$sliderhtmls = array_merge($slidervalues, $vals2);
		}

		$slidervalues = array_unique($slidervalues, SORT_REGULAR);
		$sliderhtmls = array_unique($sliderhtmls, SORT_REGULAR);
		array_multisort($slidervalues, $sliderhtmls);

		$sliderhtmls = $this->fixHtmlForJS($sliderhtmls);

		$sliderValueJS = implode(", ", $slidervalues);
		$sliderHtmlsJS = implode(", ", $sliderhtmls);
		$content = $this->getSliderView($slidervalues, $featureId1 . '|' . $featureId2, 'intersect', 'range');
		$func = "mS3CRange($id, [$sliderValueJS], [$sliderHtmlsJS]);";
		$this->JSelements[] = $func;
		$content .= $this->template->tplutils->getJSStartupFunctionCall($func);

		return $content;
	}

	/**
	 * Creates an Number Between text input
	 * @param int $featureId1
	 * @param int $featureId2
	 * @param int $languageId 
	 * @return string HTML
	 */
	function getFeatureHtmlTextBetween($featureId1, $featureId2) {
		++$this->template->searchNameCounter;
		$content = '<input type="text" name="between|' . $featureId1 . '|' . $featureId2 . '">';
		return $content;
	}

	/**
	 * Creates two Textfields
	 * @param int $featureId 
	 * @param int $languageId 
	 * @return string HTML
	 */
	function getFeatureHtmlTwoTextfields($featureId, $featureId2, $type) {
		++$this->template->searchNameCounter;
		if ($featureId2 == null) {
			$name = $featureId;
		} else {
			$name = $featureId . '|' . $featureId2;
		}
		$content = '<input type="text" name="textfield1|' . $name . '">';
		$content .= '<input type="text" name="textfield2|' . $name . '|' . $type . '">';
		return $content;
	}

	/**
	 * Creates an HTML Code for a Slider
	 * @param double[] $slidervalues 
	 * @param string Name 
	 * @return string $type
	 */
	function getSliderView($slidervalues, $Name, $type, $slidertype = 'slider') {
		$id = $this->template->searchNameCounter;
		$content = $this->template->tplutils->getSliderView($this->getControlName($id), $slidertype);
		$content .= '<input id="mS3CInput' . $id . '" type="hidden" name="' . $slidertype . '|' . $Name . '|' . $type . '" value="">';
		return $content;
	}

	function getControlName($controlId, $postfix = null) {
		return ($postfix === null) ? ('mS3CControl_' . $controlId) : ('mS3CControl_' . $controlId . '_' . $postfix);
	}

	function fixHtmlForJS($htmls) {
		foreach ($htmls as &$v) {
			$v = '"' . str_replace('"', '\\"', $v) . '"';
		}
		return $htmls;
	}

	/**
	 * Generates the search HTML form with search features and special filters
	 * to determinate the possible values in the input elements create a request object and runs a query
	 * @return string representing the content of the 'SearchView' area.
	 */
	function getSearchViewContent() {
		// gets from the user-defined template our part
		$template = $this->template->plugin->getTemplate('###SEARCHVIEW###');

		if (strlen($template) == 0) {
			return '';
		} else {
			// Process SMZs
			$markerArray = $this->template->tplutils->getMarkerArray($template);
			foreach ($markerArray as $marker) {
				if ($this->template->smz->isSMZMarker($marker)) {
					$smzContent = $this->template->smz->substituteSMZRecursive($this->template->productGroupId, tx_ms3commerce_constants::ELEMENT_GROUP, $marker);
					$template = $this->template->tplutils->substituteMarker($template, "###$marker###", $smzContent);
				}
			}

			// processes the template, only features are expected in the template
			$markerContentArray = array();
			$markerArray = $this->template->tplutils->getMarkerArray($template);
			$InputMarkerName = array();
			foreach ($markerArray as $marker) {
				$markerParts = $this->template->tplutils->getMarkerParts($marker);

				if ($this->isInputMarkerFilter($markerParts['attr'])) {
					if (strpos($markerParts['name'], ";") !== false) {
						$features = explode(";", $markerParts['name']);
						$featureId1 = $this->template->dbutils->getFeatureIdByName($features[0]);
						$featureId2 = $this->template->dbutils->getFeatureIdByName($features[1]);

						if ($featureId1 > 0 && $featureId2 > 0) {
							$InputMarkerName[] = $featureId1;
							$InputMarkerName[] = $featureId2;
						}
					} else {
						$featureId = $this->template->dbutils->getFeatureIdByName($markerParts['name']);
						if ($featureId > 0) {
							$InputMarkerName[] = $featureId;
						}
					}
				}

				//fulltext is not feature driven so imputmarker is fix
				if ($markerParts['attr'] == 'FULLTEXT') {
					$markerContentArray['###' . $marker . '###'] = "<input type='text' name='textedit|search|fulltext'>";
				}
			}

			$menu = array();
			// Narrow to products within certain menus
			if (array_key_exists('narrow_to_menu_ids', $this->template->conf)) {
				$menus = explode("|", $this->template->conf['narrow_to_menu_ids']);
				if (count($menus) > 0) {
					foreach ($menus as $value) {
						if (!is_numeric($value)) {
							$row = $this->template->dbutils->selectMenu_SingleRow('Id', "ContextId = '$value'");
							if ($row) {
								$menu[] = $row[0];
							}
						} else {
							$menu[] = $value;
						}
					}
				}
			} else {
				$menu = $this->template->searchMenuIds;
			}
			//create request object 
			$request = $this->getSearchRequestObject($InputMarkerName, $menu);

			$result = $this->template->search->runQuery($request);
			$counter = 0;
			$defaultMarkers = array();
			foreach ($markerArray as $marker) {
				if (!isset($markerContentArray['###' . $marker . '###'])) {
					$markerParts = $this->template->tplutils->getMarkerParts($marker);
					//if the input needs two features
					if (strpos($markerParts['name'], ";") !== false) {
						$features = explode(";", $markerParts['name']);
						$featureId1 = $this->template->dbutils->getFeatureIdByName($features[0]);
						$featureId2 = $this->template->dbutils->getFeatureIdByName($features[1]);

						if ($featureId1 > 0 && $featureId2 > 0) {
							$Filter = array();
							if ($this->isInputMarkerFilter($markerParts['attr'])) {
								$Filter[] = $result->Filter[$counter];
								++$counter;
								$Filter[] = $result->Filter[$counter];
								++$counter;
							}
							$markerContentArray['###' . $marker . '###'] = $this->getInputFormForTwoFeatures($featureId1, $featureId2, $markerParts['attr'], 1, $Filter);
						}
					} else {
						$featureId = $this->template->dbutils->getFeatureIdByName($markerParts['name']);

						if ($featureId > 0) {
							if ($this->isInputMarker($markerParts['attr'])) {
								$Filter = null;
								if ($this->isInputMarkerFilter($markerParts['attr'])) {
									$Filter = $result->Filter[$counter];
									++$counter;
								}
								$markerContentArray['###' . $marker . '###'] = $this->getInputForm($featureId, $markerParts['attr'], $Filter);
							} else {
								$defaultMarkers[] = $marker;
							}
						}
					}
				}
			}

			// changed for language-dependend search
			//$markerContentArray['###L###'] = 'L='.$_GET['L'];
			//$markerContentArray['###hit_pid###'] = $this->template->ResultsPageId;


			$template = $this->template->tplutils->substituteMarkerArray($template, $markerContentArray);

			$defaultContentArray = $this->template->fillGroupMarkerContentArray($defaultMarkers);
			$template = $this->template->tplutils->substituteMarkerArray($template, $defaultContentArray);

			$nonce = $this->template->generateNonce();
			//FORM Tags
			$FORM_begin = '<form id="mS3CForm" name="mS3CForm" onsubmit="submitJS();" onreset="resetJS();" action="' . $this->template->plugin->getPageLink($this->template->ResultsPageId) . '" method="post">';
			$FROM_end = '<input type="hidden" name="info|menuid" value="' . $String = implode(",", $menu) . '"><input type="hidden" name="nonce" value=""></form>';

			//Reset Slider
			$script = "<script>function resetJS(){ ";
			foreach ($this->JSelements as $elements) {
				$script .= $elements;
			}
			$script .= " }
				";
			$script .= <<<EOT
			
   function submitJS() {
	   document.mS3CForm.nonce.value = '{$nonce}_' + (new Date().getTime());
	   
	}
	
	</script>
EOT;

			$script .= $this->template->tplutils->getHtmlJSInclude(false);

			$template = $FORM_begin . $template . $FROM_end . $script;

			return $template;
		}
	}

	/**
	 * Retrieves the content for the 'SEARCHRESULTSVIEW', using an user-defined template. 
	 * 
	 * @return string representing the content of the 'SEARCHRESULTSVIEW' area.
	 */
	function getSearchResultViewContent() {
		$result = $this->buildSearchResult();
		$this->template->plugin->timeTrackStart("layout result");
		$ret = $this->layoutSearchResultViewContent($result);
		$this->template->plugin->timeTrackStop();
		return $ret;
	}

	function getSearchCompletionQuickView() {
		$url = $this->template->getPluginRoot() . 'pi1/suggest.php';
		$scriptSrc = $this->template->tplutils->getHtmlJSInclude(false);

		$data = tx_ms3commerce_suggest_helper::config2QuickParams($this->template);

		$noCall = $this->template->custom->updateQuickCompleteParams($url, $data);
		if (!$noCall) {
			$data['call'] = 1;
		}

		tx_ms3commerce_suggest_helper::addSuggestHMac($data);

		$callUrl = "var mS3CSCQUrl = '$url';\n";
		$dataObj = "var mS3CSCQData = {";
		foreach ($data as $k => $v) {
			$dataObj .= "\n\t$k: '$v',";
		}
		$dataObj = substr($dataObj, 0, strlen($dataObj) - 1);
		$dataObj .= "\n};";

		$template = $this->template->plugin->getTemplate('###SEARCHCOMPLETIONQUICKVIEW###');
		if (empty($template)) {
			$content = $scriptSrc . '
				<script type="text/javascript">
				' . $callUrl . $dataObj . '
				</script>';
		} else {
			$content = $this->template->tplutils->substituteMarker($template, '###MS3C_VARIABLES###', $callUrl . $dataObj);
			$content = $this->template->tplutils->substituteMarker($content, '###MS3C_INCLUDE###', $scriptSrc);
		}
		return $content;
	}

	/**
	 * Fetch results from session if exist or start a query search with the passed parameters {@see getSearchParams()}
	 * @return type Resultobject
	 */
	function buildSearchResult() {
		$this->template->plugin->timeTrackStart("build result");
		$params = $this->getSearchParams();
		//if results are already stored in the session and the session id corresponds to the session stored in the request
		$res = $this->template->plugin->loadSession('searchResult');
		if ($this->sharedResult && $res && $res->nonce != '' && $res->nonce == $params->nonce) {
			$result = $res;
		} else {
			$this->template->plugin->timeTrackStart("run query");
			$result = $this->template->search->runQuery($params);
			$this->template->plugin->timeTrackStop();
			$result->nonce = $params->nonce;


			//store the whole result in the session
			$this->template->plugin->storeSession('searchResult', $result);
		}

		$this->template->plugin->timeTrackStop();
		return $result;
	}

	/**
	 * 
	 * @param type $result Resultobject 
	 * @return type string HTML 
	 */
	function layoutSearchResultViewContent($result) {
		if ($result == null || (count($result->Product) == 0 && count($result->Document) == 0 && count($result->Group) == 0)) {
			// Break infinite redirection loop!
			if ($this->template->noResultsPageId == $this->template->plugin->getCurrentPID() || $this->template->noResultsPageId == 0) {
				$template = $this->template->plugin->getTemplate('###NORESULTSVIEW###');
				// Replace Translations
				$markerArray = $this->template->tplutils->getMarkerArray($template);
				$subs = $this->template->fillFeatureMarkerContentArray($markerArray);
				$template = $this->template->tplutils->substituteMarkerArray($template, $subs);
				return $template;
			} else {
				$this->template->getNoResultView();
			}
		}

		$template = $this->template->plugin->getTemplate('###SEARCHRESULTSVIEW###');
		$templateContent = $this->template->tplutils->getSubpart($template, '###CONTENT###');

		//check if consolidatedResults are needed
		if (array_key_exists('consolidatedSearchResults', $this->template->conf)) {
			if ($this->template->conf['consolidatedSearchResults'] == 'parent') {
				$result = $this->template->search->consolidateResultsParent($result);
			} else {
				$result = $this->template->search->consolidateResults($result, $this->template->searchMenuIds);
			}

			// Consolidation is via Path, which contains ALL results
			// Must slice out the required part if itemsPerPage is set
			// 
			if ($this->template->itemsPerPage >= 0) {
				$count = $this->template->itemsPerPage;
				$start = max(0, $this->template->itemStart - 1);
				if ($count > 0 && count($result->Product) > $start) {
					$result->Product = array_slice($result->Product, $start, $count, true);
					$start = 0;
					$count -= count($result->Product);
				} else {
					$start -= count($result->Product);
				}

				if ($count > 0 && count($result->Document) > $start) {
					$result->Document = array_slice($result->Document, $start, $count, true);
					$start = 0;
					$count -= count($result->Document);
				} else {
					$start -= count($result->Document);
				}

				if ($count > 0 && count($result->Group) > $start) {
					$result->Group = array_slice($result->Group, $start, $count, true);
					$start = 0;
					$count -= count($result->Group);
				} else {
					$start -= count($result->Group);
				}
			}
		}

		$prevParams = $this->getPreservedSearchParams();
		$template = $this->template->getResultsPaginationTemplate($result, $template, $prevParams);
		$templateContent = $this->template->getResultsOutputTemplate($result, $templateContent);
		$template = $this->template->tplutils->substituteSubpart($template, '###CONTENT###', $templateContent);

		return $template;
	}

	/**
	 * Builds a Menu based on a hierarchicalsearch (search childrens of a Tree leave ) and give back the new menu structure    
	 * @param type $curItemState
	 * @param type $parentItemState
	 * @param type $additionalItems
	 * @return type menu array 
	 */
	function makeMenuHierarchicalSearch($curItemState, $parentItemState, $additionalItems) {
		$this->template->plugin->timeTrackStart("mS3 Search Menu");
		$this->buildSearchResult();

		$result = $this->template->plugin->loadSession('searchResult');
		$params = $this->template->plugin->loadSession('searchParms');
		$elementType = array_map("ucfirst", $params->ResultTypes);

		$this->template->plugin->timeTrackStart("Select menus");
		$menuArray = array();
		if (count(array_diff($params->Menu, $this->template->searchMenuIds)) === 0) {
			// No menu selected, make menu for all search-Menus!
			$menuArray = array('_SUB_MENU' => array());
			foreach ($this->template->searchMenuIds as $m) {
				$curMenu = $this->template->selectPartialMenuItems($m, $m, $curItemState, $parentItemState);
				if (is_array($curMenu)) {
					if ($curMenu['_SUB_MENU']) {
						$menuArray['_SUB_MENU'] = $menuArray['_SUB_MENU'] + $curMenu['_SUB_MENU'];
					}
				}
			}
		} else {
			// Menu directly selected. Search in search-Menus, until the item is found
			$selectMenuId = $params->Menu[0];
			foreach ($this->template->searchMenuIds as $m) {
				$curMenu = $this->template->selectPartialMenuItems($selectMenuId, $m, $curItemState, $parentItemState);
				if ($curMenu) {
					$menuArray = $curMenu;
					break;
				}
			}
		}
		$this->template->plugin->timeTrackStop();

		$add = $this->handleWebSearchIntegration();
		$postVars = array_merge($add, $_POST);
		if ($this->isNewPostSearch($postVars)) {
			$oldMenu = null;
			$menuItem = null;
		} else if (self::$isSearchShopChange) {
			//checken if shopid has changed
			$oldMenu = null;
			$menuItem = null;
		} else if (!isset($_GET) || !isset($_GET['menuid'])) {
			// Only start has changed, return old menu
			$oldMenu = $this->template->plugin->loadSession('searchHMenu');
			$this->template->plugin->timeTrackStop();
			return $oldMenu;
		} else {
			// Only Menu has changed, reload old data and merge with new data
			$oldMenu = $this->template->plugin->loadSession('searchHMenu');
			$menuItem = $_GET['menuid'];
		}

		// Add additional link params
		$addLinkParams = $this->getPreservedSearchParams();

		//itemsList array contains the children Items under the given selected Menu 
		$itemsList = $menuArray['_SUB_MENU'];

		// Consolidate...
		if (array_key_exists('consolidatedSearchResults', $this->template->conf)) {
			if ($this->template->conf['consolidatedSearchResults'] == 'parent') {
				$result = $this->template->search->consolidateResultsParent($result);
			} else {
				$result = $this->template->search->consolidateResults($result, $this->template->searchMenuIds);
			}
			// Must also consider groups now...
			if (array_search('Group', $elementType) === false) {
				$elementType[] = 'Group';
			}
		}

		$this->template->plugin->timeTrackStart("Filter Menu");
		// filterung und Quantifizierung für 2 menulevel
		$this->filterHierarchicalSearchMenu($itemsList, $result, $elementType);
		$this->template->plugin->timeTrackStop();

		$this->template->plugin->timeTrackStart("Fill Titles");
		$menuArray['_SUB_MENU'] = $itemsList;
		//set the items names and how many elements are under this branch
		$this->fillHierarchichalSeachMenuTitles($menuArray, $additionalItems, 1, $addLinkParams);
		$theMenu = $menuArray['_SUB_MENU'];
		$this->template->plugin->timeTrackStop();

		$this->template->plugin->timeTrackStart("Merge menus");
		// Merge the old menu with the new branch!
		$noItem = '';
		if (array_key_exists('ITEM_STATE', $additionalItems)) {
			$noItem = $additionalItems['ITEM_STATE'];
		}
		$theMenu = $this->mergeSearchHierarchy($theMenu, $oldMenu, $menuItem, $noItem, $parentItemState);
		$this->template->plugin->timeTrackStop();

		$this->template->plugin->storeSession('searchHMenu', $theMenu);
		$this->template->plugin->timeTrackStop();
		return $theMenu;
	}

	function filterHierarchicalSearchMenu(&$menu, $result, $elementType) {
		//check for each itemlist if has a resultitem and count how many
		foreach ($elementType as $el) {
			foreach ($result->Hierarchy->$el as $elemId => $element) {
				foreach ($element as $elemMenuId => $path) {
					$pathArr = explode('/', $path);
					foreach ($menu as $key2) {
						if (in_array($key2['id'], $pathArr)) {
							if (!$menu[$key2['id']]['count']) {
								$menu[$key2['id']]['count'] = 1;
							} else {
								$menu[$key2['id']]['count'] ++;
							}
							break;
						}
					}
				}
			}
		}

		//Filter all menu branches that has no results,
		foreach ($menu as $key => &$element) {
			if (!array_key_exists('count', $element)) {
				unset($menu[$key]);
			} else if (array_key_exists('_SUB_MENU', $element)) {
				// Filter sub-Menu
				$subMenu = $element['_SUB_MENU'];
				$oldSubMenu = $oldSubMenu[$element]['_SUB_MENU'];

				$this->filterHierarchicalSearchMenu($subMenu, $result, $elementType, $oldSubMenu);
				$element['_SUB_MENU'] = $subMenu;
			}
		}
	}

	/**
	 *
	 * @param type $menuItemArray
	 * @return type 
	 */
	function fillHierarchichalSeachMenuTitles(&$menuItemArray, $add = array(), $level = 1, $addLinkParams = array()) {
		if (!array_key_exists('_SUB_MENU', $menuItemArray))
			return;

		$thisPid = $this->template->plugin->getCurrentPID();
		foreach ($menuItemArray['_SUB_MENU'] as $menuItemKey => &$menuItem) {
			// Add additional menu item parameters
			foreach ($add as $k => $v) {
				if (array_key_exists($k, $menuItem)) {
					continue;
				}
				if ($k === 'uid' && $v === -1) {
					// Why? From old MENNEKES code
					$v = $menuItem['id'] + 9000;
				}
				$menuItem[$k] = $v;
			}

			// Set group title
			if (array_key_exists('groupId', $menuItem)) {
				$groupId = $menuItem['groupId'];
				if ($groupId > 0) {
					$count = " (" . $menuItem['count'] . ")";
					$menuItem['title'] = $this->template->getMenuTitleForGroup($groupId) . $count;

					if ($level >= $this->template->conf['search_menu_last_level']) {
						$menuItem['_OVERRIDE_HREF'] = $this->template->plugin->getGroupLink($groupId, 0, $this->template->conf['search_menu_next_pid']);
					} else {
						$params = array_merge($addLinkParams, array('menuid' => $menuItem['id']));
						$menuItem['_OVERRIDE_HREF'] = $this->template->plugin->getPageLink($thisPid, $params, false);
					}
				}
			}

			// Remove products
			if (array_key_exists('productId', $menuItem)) {
				unset($menuItemArray[$menuItemKey]);
			}

			// Remove documents
			if (array_key_exists('documentId', $menuItem)) {
				unset($menuItemArray[$menuItemKey]);
			}

			if (array_key_exists('_SUB_MENU', $menuItem))
				$this->fillHierarchichalSeachMenuTitles($menuItem, $add, $level + 1, $addLinkParams);
		}
		unset($menuItem);
	}

	function mergeSearchHierarchy($theMenu, $oldMenu, $currentMenu, $noItemState, $parentItemState) {
		if (isset($oldMenu)) {
			foreach ($oldMenu as $key => &$val) {
				if (array_key_exists($key, $theMenu)) {
					if ($key == $currentMenu) {
						// Replace branch
						$oldMenu[$key] = $theMenu[$key];
					} else {
						// Search recusively
						$oldMenu[$key]['_SUB_MENU'] = $this->mergeSearchHierarchy($theMenu[$key]['_SUB_MENU'], $val['_SUB_MENU'], $currentMenu, $noItemState, $parentItemState);
					}
				} else {
					unset($oldMenu[$key]['_SUB_MENU']);
					$oldMenu[$key]['ITEM_STATE'] = $noItemState;
				}
			}

			$theMenu = $oldMenu;
		}
		return $theMenu;
	}

	function getPreservedSearchParams() {
		$addLinkParams = array();
		if (array_key_exists('preserve_search_params', $this->template->conf)) {
			$pres = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(';', $this->template->conf['preserve_search_params']);
			foreach ($pres as $param) {
				$val = TYPO3\CMS\Core\Utility\GeneralUtility::_GP($param);
				if ($val) {
					$addLinkParams[$param] = $val;
				}
			}
		}
		return $addLinkParams;
	}

	//If the marker ist as InputMarker
	function isInputMarker($MarkerAtt) {
		if (in_array(strtolower($MarkerAtt), $this->inputmarker)) {
			return true;
		} else {
			return false;
		}
	}

	//If the marker is a InputMarker and needs filter information
	function isInputMarkerFilter($MarkerAtt) {
		if (in_array(strtolower($MarkerAtt), $this->inputmarkerfilter)) {
			return true;
		} else {
			return false;
		}
	}

	//For InputForms with one Feature
	function getInputForm($featureId, $featureAttr, $Filter = null) {
		if ($featureId <= 0) {
			return;
		}

		$featureAttr = strtolower($featureAttr);
		switch ($featureAttr) {
			case 'select':
				return $this->getFeatureHtmlSelect($featureId, $Filter);
			case 'checkboxor':
				return $this->getFeatureHtmlCheckboxlistOR($featureId, $Filter);
			case 'checkboxand':
				return $this->getFeatureHtmlCheckboxlistAND($featureId, $Filter);
			case 'textequals':
				return $this->getFeatureHtmlTextEdit($featureId, 'equals');
			case 'textnumber':
				return $this->getFeatureHtmlTextEdit($featureId, 'equalsnumber');
			case 'textcontains':
				return $this->getFeatureHtmlTextEdit($featureId, 'contains');
			case 'numbergreater':
				return $this->getFeatureHtmlTextEdit($featureId, 'greater');
			case 'numberless':
				return $this->getFeatureHtmlTextEdit($featureId, 'less');
			case 'between':
				return $this->getFeatureHtmlTextBetween($featureId);
			case 'radio':
				return $this->getFeatureHtmlRadio($featureId, $Filter);
			case 'text':
				return $this->getFeatureHtmlText($featureId, $Filter);
			case 'range':
				return $this->getFeatureHtmlRange($featureId, $Filter);
			case 'slider':
				return $this->getFeatureHtmlSlider($featureId, $Filter);
			case 'slidergreater':
				return $this->getFeatureHtmlSliderContinuous($featureId, $Filter, 'greater');
			case 'sliderless':
				return $this->getFeatureHtmlSliderContinuous($featureId, $Filter, 'less');
			case 'minmax':
				return $this->getFeatureHtmlTwoTextfields($featureId, null, 'between');
			case 'suggest':
				return $this->getFeatureHtmlSuggest($featureId);
		}
	}

	//For InputForms with two Features
	function getInputFormForTwoFeatures($featureId1, $featureId2, $featureAttr, $Filter = null) {
		if ($featureId1 <= 0 && $featureId2 <= 0) {
			return;
		}

		$featureAttr = strtolower($featureAttr);
		switch ($featureAttr) {
			case 'between':
				return $this->getFeatureHtmlTextBetween($featureId1, $featureId2);
			case 'slider':
				return $this->getFeatureHtmlSliderBetween($featureId1, $featureId2, $Filter);
			case 'range':
				return $this->getFeatureHtmlRangeIntersect($featureId1, $featureId2, $Filter);
			case 'intersect':
				return $this->getFeatureHtmlTwoTextfields($featureId1, $featureId2, 'intersect');
		}
	}

	function getSearchRequestObject($filter, $menu) {
		$request = new stdClass();
		$request->Results = array();
		$request->Filter = $filter;
		$request->Start = 0;
		$request->Limit = 0;
		$request->Order = array();
		$request->UpdateType = "fromproducts";
		$request->Level = -1;
		$request->Language = $this->template->languageId;
		$request->Market = $this->template->marketId;
		$request->includeFeatureValues = false;
		$request->includeLinks = false;

		$request->Menu = $menu;
		$request->Selection = array();

		$delimiter = '|';

		if (array_key_exists('result_types', $this->template->conf)) {
			$result_types = explode(';', $this->template->conf['result_types']);
			//convert all values to lowercase
			$request->ResultTypes = array_map('strtolower', $result_types);
		} else {
			$request->ResultTypes = array();
		}

		// Narrow to products with specified feature value
		if (array_key_exists('narrow_to_feature_name', $this->template->conf)) {
			$featureName = $this->template->conf['narrow_to_feature_name'];
			$featureId = $this->template->dbutils->getFeatureIdByName($featureName);
			if ($featureId > 0) {
				$productIdArray = array();
				$featureValues = explode($delimiter, $this->template->conf['narrow_to_feature_values']);

				$sel = array(
					'Feature' => array($featureName),
					'IsMultiFeature' => false,
					'Type' => 'Any',
					'Value' => array()
				);
				foreach ($featureValues as $featureValue) {
					$sel['Value'][] = $featureValue;
				}

				$request->Selection[] = $sel;
			}
		}

		//add restrictions to the selection array
		/*
		  if($this->template->restrictionFeatureId){
		  $class = new stdClass();
		  $class->Feature = $this->template->restrictionFeatureId;
		  $class->Value = $this->template->restrictionValues;
		  $request->Restriction =$class;
		  }
		 */
		//Selection Array to Class
		$selectionclass = array();
		foreach ($request->Selection as $value) {
			$class = new stdClass();
			$class->Feature = $value["Feature"];
			$class->Value = $value["Value"];
			$class->Type = $value["Type"];
			$class->IsMultiFeature = $value["IsMultiFeature"];
			$selectionclass[] = $class;
		}
		$request->Selection = $selectionclass;

		return $request;
	}

	public function isNewPostSearch($postVars) {
		if (is_null($postVars))
			$postVars = &$_POST;
		return array_key_exists("nonce", $postVars);
	}

	/**
	 * Returns the search parameters passed using the POST method.
	 */
	function getSearchParams() {
		$this->template->plugin->timeTrackStart("get search params");
		$addVars = $this->handleWebSearchIntegration();
		$postVars = array_merge($_POST, $addVars);

		if ($this->sharedParams) {
			// Replace post by combined value!
			$_POST = &$postVars;
		}

		// If there are no search parameters, then check the session; otherwise, save
		// the new search parameters into current session.
		$params = $this->template->plugin->loadSession('searchParms');

		if (!$this->isNewPostSearch($postVars)) {

			if (isset($_GET) && isset($params)) {
				if (((isset($_GET['menuid']) && $_GET['menuid'] != $params->Menu[0]))  // Menu changed
						|| (( isset($_GET['mS3ItemStart']) && $_GET['mS3ItemStart'] - 1 != $params->Start ) )) { // Start changed
					// Delete old search results!
					$this->template->plugin->storeSession('searchResult', null);

					// Update parameters
					if (isset($_GET['menuid'])) {
						$params->Menu = array($_GET['menuid']);
					}
					if (isset($_GET['mS3ItemStart'])) {
						$params->Start = $this->template->itemStart - 1;
					} else {
						$params->Start = 0;
					}
				}
			}
			//special case: Shop change 
			if (isset($params) && $params->ShopId != $this->template->dbutils->getShopId()) {
				$this->template->plugin->storeSession('searchResult', null);

				//in the case that the user changes language , needed for the left menu build				
				self::$isSearchShopChange = true;
				$params->ShopId = $this->template->dbutils->getShopId();
				$params->Language = $this->template->languageId;
//				$this->template->
				$params->Market = $this->template->marketId;
				$params->Menu = $this->template->searchMenuIds;
			}
			//Can happen if there is no search at all... prevent later warnings by creating empty object
			if (!isset($params)) {
				$params = new stdClass();
			}
		} else {
			if ($this->sharedParams && $params && $postVars['nonce'] && $postVars['nonce'] == $params->nonce) {
				$this->template->plugin->timeTrackStop();
				return $params;
			} else {
				$this->template->plugin->timeTrackStart("translate params");
				$params = $this->translateParams($postVars);
				$this->template->plugin->timeTrackStop();
				$params->nonce = $postVars['nonce'];
			}
		}
		$this->template->plugin->storeSession('searchParms', $params);
		$this->template->plugin->timeTrackStop();
		return $params;
	}

	function handleWebSearchIntegration() {
		$add = array();
		// Translate indexed search form box
		if (!empty($this->webSearchKey) && !empty($this->webSearchFeature)) {

			//$this->webSearchKey = "tx_indexedsearch[sword2]";
			$destKey = "textedit|$this->webSearchFeature|$this->webSearchType";

			if (!array_key_exists($destKey, $_POST)) {
				// Check if there is a search
				$firstKey = substr($this->webSearchKey, 0, strpos($this->webSearchKey, '['));
				if (array_key_exists($firstKey, $_POST)) {
					$matches = array();
					$part = &$_POST;
					$key = $this->webSearchKey;
					do {
						$ct = preg_match('/^([^\[]+)(?:\[(.*)\])?$/', $key, $matches);
						$part = &$part[$matches[1]];
						$key = $matches[2];
					} while ($ct && is_array($part));

					// If the last key part did still match
					// (otherwise, there is no search term...)
					if (!is_array($part) && !is_null($part)) {
						$term = $part;

						$add[$destKey] = $term;

						// Set Nonce if there is none
						if (!array_key_exists('nonce', $_POST)) {
							$add['nonce'] = time();
						}
					}
				}
			}
		}
		return $add;
	}

	function translateParams($postVars) {
		$menu = null;
		$textfield1 = null;
		$selection = array();
		$checkboxorvalues = array();
		$checkboxandvalues = array();

		foreach ($postVars as $key => $value) {
			if (is_object($value) || is_array($value) || strlen($value) < 1)
				continue;

			$value = stripslashes($value);

			$parts = explode('|', $key);
			if (count($parts) < 0)
				continue;

			//For Infos i.g. the menuid
			if ($parts[0] == 'info') {
				if ($parts[1] == 'menuid') {
					$menu = explode(",", $value);
				}
			}
			//Inmput for 2 fields
			else if ($parts[0] == 'textfield1' && count($parts) <= 3) {
				$textfield1 = $value;
			} else if ($parts[0] == 'textfield2' && count($parts) <= 4) {

				$feature = array();
				if (count($parts) == 4) {
					$feature[] = $parts[1];
					$feature[] = $parts[2];
					$type = 'Intersect';
					$multi = false;
				} else {
					$feature[] = $parts[1];
					$type = 'Between';
					$multi = $this->isMultiFeature($parts[1]);
				}
				$selection[] = array(
					'Feature' => $feature,
					'Value' => array($textfield1, $value),
					'Type' => $type,
					'IsMultiFeature' => $multi
				);
				$textfield1 = null;
			}
			//between
			else if ($parts[0] == 'between' && count($parts) == 3) {
				$selection[] = array(
					'Feature' => array($parts[1], $parts[2]),
					'Value' => array($value),
					'Type' => 'Between',
					'IsMultiFeature' => false
				);
			}
			//sliderbetween
			else if ($parts[0] == 'slider' && count($parts) == 4) {
				if ($parts[3] == 'between') {
					$type = 'Between';
				}
				$selection[] = array(
					'Feature' => array($parts[1], $parts[2]),
					'Value' => array($value),
					'Type' => $type,
					'IsMultiFeature' => false
				);
			}
			//range
			else if ($parts[0] == 'range' && count($parts) == 3) {
				$type;
				if ($parts[2] == 'between') {
					$type = 'Between';
					$value = explode('|', $value);
				}
				$selection[] = array(
					'Feature' => array($parts[1]),
					'Value' => $value,
					'Type' => $type,
					'IsMultiFeature' => $this->isMultiFeature($parts[1])
				);
			}
			//rangeintersect
			else if ($parts[0] == 'range' && count($parts) == 4) {
				$type;
				if ($parts[3] == 'intersect') {
					$type = 'Intersect';
					$value = explode('|', $value);
				}
				$selection[] = array(
					'Feature' => array($parts[1], $parts[2]),
					'Value' => $value,
					'Type' => $type,
					'IsMultiFeature' => false
				);
			}
			//Checkboxor
			else if ($parts[0] == 'checkboxor' && count($parts) == 3) {
				$checkboxorvalues[$parts[1]][] = $value;
			}
			//Checkboxand
			else if ($parts[0] == 'checkboxand' && count($parts) == 3) {
				$checkboxandvalues[$parts[1]][] = $value;
			}
			//select, radio, textequals, textcontains, numberless, numbergreater, sliderequals, sliderless, slidergreater,  
			else if (count($parts) == 3) {
				$type;
				if ($parts[2] == 'equals') {
					$type = 'Equals';
				} else if ($parts[2] == 'equalsnumber') {
					$type = 'EqualsNumber';
				} else if ($parts[2] == 'contains') {
					$type = 'Contains';
				} else if ($parts[2] == 'less') {
					$type = 'Less';
				} else if ($parts[2] == 'greater') {
					$type = 'Greater';
				} else if ($parts[2] == 'fulltext') {
					$type = 'Fulltext';
				}
				if (isset($type)) {
					$selection[] = array(
						'Feature' => array($parts[1]),
						'Value' => array($value),
						'Type' => $type,
						'IsMultiFeature' => $this->isMultiFeature($parts[1])
					);
				}
			}
		}


		//Make selection for checkboxor
		foreach ($checkboxorvalues as $key => $value) {
			$selection[] = array(
				'Feature' => array($key),
				'Value' => $value,
				'Type' => 'Any',
				'IsMultiFeature' => $this->isMultiFeature($key)
			);
		}
		//Make selection for checkboxand
		foreach ($checkboxandvalues as $key => $value) {
			$selection[] = array(
				'Feature' => array($key),
				'Value' => $value,
				'Type' => 'All',
				'IsMultiFeature' => $this->isMultiFeature($key)
			);
		}
		/*
		  if($this->template->restrictionFeatureId){
		  $class = new stdClass();
		  $class->Feature = $this->template->restrictionFeatureId;
		  $class->Value = $this->template->restrictionValues;
		  $request->Restriction =$class;
		  }
		 */
		//Selection Array to Class
		$selectionclass = array();
		foreach ($selection as $value) {
			//Convert Feature Name in FeatueId
			$FeatureID = array();
			foreach ($value["Feature"] as $FeatureId) {
				if (!is_numeric($FeatureId)) {
					$FeatureID[] = $this->template->dbutils->getFeatureIdByName(urldecode($FeatureId));
				} else {
					$FeatureID[] = $FeatureId;
				}
			}


			$class = new stdClass();
			$class->Feature = $FeatureID;
			$class->Value = $value["Value"];
			$class->Type = $value["Type"];
			$class->IsMultiFeature = $value["IsMultiFeature"];
			$selectionclass[] = $class;
		}
		$selection = $selectionclass;



		if (is_null($menu)) {
			$menu = $this->template->searchMenuIds;
		}
		$request = new stdClass();
		$request->Menu = $menu;
		$request->Selection = $selection;
		$request->Results = array(); // Not Needed. Template will be evaluated anyway
		$request->Filter = array();
		$request->Start = 0;
		$request->Limit = $this->template->itemsPerPage > 0 ? $this->template->itemsPerPage : -1;
		$request->Order = array();
		$request->UpdateType = "none";
		$request->Level = -1;
		$request->Language = $this->template->languageId;
		$request->Market = $this->template->marketId;
		$request->WithHierarchy = $this->template->conf['with_hierarchy'];
		$request->ShopId = $this->template->dbutils->getShopId();
		$request->includeFeatureValues = false;
		$request->includeLinks = false;

		if (array_key_exists('result_types', $this->template->conf)) {
			$result_types = explode(';', $this->template->conf['result_types']);
			//convert all values to lowercase
			$request->ResultTypes = array_map('strtolower', $result_types);
		} else {
			$request->ResultTypes = array();
		}

		return $request;
	}

	//If this Feature have multi Features    
	function isMultiFeature($feature) {
		$feat = $this->template->dbutils->getFeatureRecord($this->template->dbutils->getFeatureIdByName($feature));
		if (isset($feat)) {
			if ($feat->IsMultiFeature) {
				return true;
			}
		}
		return false;
	}
}

// Used as array_filter callback
function notEmpty($val) {
	return !empty($val);
}

?>
