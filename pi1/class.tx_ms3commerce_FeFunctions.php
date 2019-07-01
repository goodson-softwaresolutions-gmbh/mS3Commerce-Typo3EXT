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
 * This class contains the implemtnation and helper methods for mS3 Commerce Front end Functions
 *
 */

class tx_ms3commerce_FeFunctions 
{
	/** @var tx_ms3commerce_template */
	var $template;  
	public function __construct($template)
	{
		$this->template=$template;  
	}
  
	/**
	* Handles front end functions defined in template with markers with notation:
	* for standard functions: MS3C_{functionname}(p=value,p=value,...) (e.g scaleimage) 
	* or custom functions: MS3C_CUSTOM_{functionname}(p=value,p=value,...)
	* @param $template
	* @return 
	*/
	public function callACFrontendFunctions( $template )
	{
		$markers = $this->template->tplutils->getMarkerArray($template);
		$regexp = '/MS3C_(\w+)'.PLACEMENT_PARAMETER_REGEXP_PART.'/i';
		$substitutes = array();
		foreach ($markers as $funcMarker)
		{
			if ( preg_match($regexp, $funcMarker, $matches) )
			{
				$func = $matches[1];
				$params = $matches[2];
				$this->template->plugin->timeTrackStart($func);
				switch (strtolower($func))
				{
					case 'scaleimage':
						$params = $this->splitParams($params, $this->template->mS3CFunctionParams['scaleimg']);
						$substitutes['###'.$funcMarker.'###'] = $this->callScaleImage($params);
						break;
					
					default:
						$params = $this->splitParams($params, '');
						$substitutes['###'.$funcMarker.'###'] = $this->template->custom->callCustomMS3CFunction($funcMarker, $func, $params);
						break;
				}
				$this->template->plugin->timeTrackStop();
			}
		}
		
		return $this->template->tplutils->substituteMarkerArray($template, $substitutes);
	} 
  
	/**
	 * Split FE Function params and merges it with defaults
	 * @param $params string containing actual parameters, as "k=value,k=value"
	 * @param $defaults string default parameters in same format
	 * @return array associative array with keys => value mappings, using
	 * values from $defaults for keys not present in $params
	 */
	function splitParams($params, $defaults)
	{
		// k=value,k=value,....
		$ret = array();
		
		$k = '';
		if (!empty($defaults)) {
			$pairs = explode(',', $defaults);
			foreach ($pairs as $p) {
				// values might contain ',', so join all elements that don't start with "X="
				if (preg_match('/^(\w)=(.*)/', $p, $matches)) {
					$k = $matches[1];
					$v = $matches[2];
				} else {
					$v .= ','.$p;
				}
				if ($k) {
					$ret[$k]  = $v;
				}
			}
		}

		unset($k);
		if (!empty($params)) {
			$pairs = explode(',', $params);
			foreach ($pairs as $p) {
				// values might contain ',', so join all elements that don't start with "X="
				if (preg_match('/^(\w)=(.*)/', $p, $matches)) {
					$k = $matches[1];
					$v = $matches[2];
				} else {
					$v .= ','.$p;
				}
				if ($k) {
					$ret[$k]  = $v;
				}
			}
		}

		return $ret;
	}
  
	/**
	 * Scale the image  #TO BE PROOF"
	 * @param $params string containing image parameters#TO BE PROOF"
	 * @return $dest where to write the new scaled img #TO BE PROOF"
	 */
    function callScaleImage($params)
	{	
		$dest = $this->template->getDocumentPath($params['s'],$params['p'],$params['d'],$params['f'],$params['o'],$params['w'],$params['h'],$params['e']);
		$dest = $this->template->plugin->generatePicture($params['s'],$dest,$params['w'],$params['h'],$params['x']);
		
		if ( !is_array($dest) )
		{
			$dest = array('dest' => $dest);
		}
		
		if (substr($dest['dest'],0,1) == ".") {
			$dest['dest'] = "";
		}

		switch ($params['t']) {
		case 'tag':
			$includeTag = true;
			// Fallthrough
		case 'intag':
			$p = $dest['dest'];
			$w = $h = '';
			if (array_key_exists('width', $dest))
				$w = ' width="'.$dest['width'].'"';
			if (array_key_exists('height', $dest))
				$h = ' height="'.$dest['height'].'"';
			
			if ($includeTag) {
				// Complete Img Tag
				return "<img src=\"$p\"$w$h>";
			} else {
				// For usage within an img-Tag
				return " src=\"$p\"$w$h";
			}
			
		case 'none':
		default:
			// Only name (e.g. for CSS-URL)
			return $dest['dest'];
		}
	}
  
	/**
	 * Handle function calls from a SM attribute
	 * @param type $func
	 * @param type $params
	 * @param type $thisId
	 * @param type $parentMenuId
	 * @param type $featureId
	 * @param type $isGroup
	 * @return string 
	 */
	function handleSMFunctionCall($func, $params, $thisId, $parentMenuId, $featureId, $ioType)
	{
		switch (strtolower($func)) {
		case 'scaledimg':
			return $this->buildScaledImageCall($params, $thisId, $parentMenuId, $featureId, $ioType);
			break;
		case 'trunc':
			return $this->truncateText($params, $thisId, $featureId, $ioType);
			break;
		default:
			// Unknown call
			return 'UNKOWN FUNCTION CALL';
		}
	}
	/**
	 * Handles function calls from DOCUMENT marker (eg. scaleimage)
	 * @param type $func
	 * @param type $params
	 * @param type $docId
	 * @param type $parentMenuId
	 * @param type $asFeatureValue
	 * @return string
	 */	
	function handleDocFunctionCall($func, $params, $docId, $parentMenuId, $asFeatureValue)
	{
		switch (strtolower($func)) {
		case 'scaledimg':
			return $this->buildDocScaledImageCall($params, $docId, $parentMenuId, $asFeatureValue);
			break;
		default:
			// Unknown call
			return 'UNKOWN FUNCTION CALL';
		}
	}
	
	/**
	 * Builds the functioncall for scaledImage used for Documents
	 * @param type $params
	 * @param type $docId
	 * @param type $parentMenuId
	 * @param type $asFeatureValue
	 * @return string (Function call Marker "###MS3C_SCALEIMAGE(with parameters) OR just a Html '<href  whit Document File link  )
	 */
	
	function buildDocScaledImageCall($params, $docId, $parentMenuId, $asFeatureValue)
	{
		$content = '';
		$src = $this->template->dbutils->getDocumentFile($docId);
		$ext = pathinfo($src, PATHINFO_EXTENSION);
		$ok = array_search( strtolower($ext), $this->template->conf['SCALEIMG_EXTENSIONS'] );
		if ( $ok !== false ) // array_search returns index, so "if ($ok)" would be false for index === 0
		{
			$dest = $this->template->custom->getScaledImagePathDocument($src, $docId, $parentMenuId, $asFeatureValue);
			if ($dest==null){
				$deph=$this->template->conf['default_image_scale_path_depth'];
				$dest=$this->getRealUrlDest($src,$docId,$asFeatureValue['featureId'],$deph,'document');
			}
			$content = $this->buildScaleImgMarker($src, $dest, $params);
		} else {
			$content = $this->template->dbutils->getDocumentFileLink($docId);
		}
		
		return $content;
	}
	
	function truncateText($params, $thisId, $featureId, $ioType)
	{
		$params = $this->splitParams($params, 't=h');
		$len = $params['l'];
		$type = $params['t'];
		
		switch ($ioType) {
		case 'group': $getValue = 'getGroupValue'; break;
		case 'product': $getValue = 'getProductValue'; break;
		case 'document': $getValue = 'getDocumentValue'; break;
		}
		
		$val = $this->template->dbutils->$getValue($thisId, $featureId, true);
		if (strlen($val) > $len) {
			switch ($type) {
			case 's':
				//Soft
				$pos = strcspn($val, " \t\n\r.,-", $len);
				if (strlen($val) > $len+$pos) {
					$val = substr($val, 0, $len+$pos) . '&hellip;';
				}
				break;
			case 'h':
			default:
				// Hard
				$val = substr($val, 0, $len) . '&hellip;';
				break;
			}
		}
		
		return $val;
	}
	
	/**
	 * Builds the function call with corresponding paramenters as called from a SM Marker and generates a Htlm list with the      
	 * function call marker  ###MS3C_SCALEIMAGE(params)### ( if many sources each item in the list is a marker)
	 * @param type $params
	 * @param type $thisId
	 * @param type $parentMenuId
	 * @param type $featureId
	 * @param type $isGroup
	 * @return string  Html list with function call Markers (###MS3C_SCALEIMAGE(with parameters))(one or many items)
	 */
	function buildScaledImageCall($params, $thisId, $parentMenuId, $featureId, $type)
	{
		$content = '';
			
		switch ($type) {
		case 'group':
			$getValue = 'getGroupValue';
			$getScaledImagePath = 'getScaledImagePathGroup';
			break;
		case 'product':
			$getValue = 'getProductValue';
			$getScaledImagePath = 'getScaledImagePathProduct';
			break;
		case 'document':
			$getValue = 'getDocumentValue';
			$getScaledImagePath = 'getScaledImagePathDocument';
			break;
		}
		
		
		$src = $this->template->dbutils->$getValue($thisId, $featureId, true);
		
		if ($src == null) {
			return '';
		} else {
			$srcs = preg_split('/;/', $src);
			
			$pre = $post = $preList = $postList = "";
			if ( count($srcs) > 1) {
				$featureName = $this->template->getFeatureValue($featureId, 'Name', $this->languageId);
				$preList = "<ul class=\"{$featureName}_LIST mS3CDocumentList\">";
				$postList = "</ul>";
				$pre = "<li>";
				$post = "</li>";
			}
			
			foreach ($srcs as $src)
			{
				// Check if image extension belongs to scalable paths
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$ok = array_search( strtolower($ext), $this->template->conf['SCALEIMG_EXTENSIONS'] );
				if ( $ok !== false ) // array_search returns index, so "if ($ok)" would be false for index === 0
				{
					
					$dest = $this->template->custom->$getScaledImagePath($src, $thisId, $featureId, $parentMenuId);
					if ($dest==null){
						$deph=$this->template->conf['default_image_scale_path_depth'];
						$dest=$this->getRealUrlDest($src,$thisId,$featureId,$deph,$type);
					}
					$content .= $pre . $this->buildScaleImgMarker($src, $dest, $params) . $post;
				} else {
					$params=$this->splitParams($params,$this->template->mS3CFunctionParams['scaleimg']);
					switch ($params['t']){
					case  'tag': 
						$content .= $pre . $this->template->dbutils->$getValue($thisId, $featureId) . $post;
						break;
					case 'intag':
						$content = 'src="'.$this->template->dbutils->$getValue($thisId, $featureId, true).'"';
						// Cannot have more than 1 src tag, so also break multi-value loop
						break 2;
					case 'none':
						$content .= $this->template->dbutils->$getValue($thisId, $featureId, true);
						$preList = $postList = "";
					}
				}
			}
		}
		
		return $preList.$content.$postList;
	}
  
	/**
	 * Builds the ScaleImg Marker with corresponding params
	 * @param type $src
	 * @param type $dest
	 * @param type $params
	 * @return type string (Marker with parameters)
	 */
	function buildScaleImgMarker($src, $dest, $params)
	{ 
		if (!isset($dest))
		{
           
			$dir = 'typo3temp';
			$fname = 'mS3C_scaled_';
			$other = time();
			if (!isset($params))
				$params = 'p=%P/%N%O,x=true';
			else
				$params .= ',p=%P/%N%O,x=true';
		} else {
			$dir = $dest['path'];
			$fname = $dest['name'];
			$other = $dest['other'];
		}
		if (isset($params)) {
			$hasW = $hasH = false;
			foreach (preg_split('/,/', $params) as $p) {
				if ( preg_match( '/w=\d+/', $p ) ) {
					$hasW = true;
				} else if (preg_match( '/h=\d+/', $p ) ) {
					$hasH = true;
				}
			}
			if ( $hasW !== $hasH ) {
				if ( $hasW ) {
					$params .= ',h=-1';
				} else {
					$params .= ',w=-1';
				}
			}
			return "###MS3C_SCALEIMAGE(s=$src,d=$dir,f=$fname,o=$other,$params)###";
		}
		return "###MS3C_SCALEIMAGE(s=$src,d=$dir,o=$other,f=$fname)###";
	}
	/**
	 * Generates a REAL URL destination Link 
	 * @param type $src
	 * @param type $id
	 * @param type $featureId
	 * @param type $deph
	 * @param type $type
	 * @return type
	 */
	private function getRealUrlDest($src,$id,$featureId,$deph,$type){
		$db = $this->db;
		$n=$deph;
		$colstring='';
		$ret=null;
		
		// check if is a  Group id or product id
		switch ($type)
		{
		case 'group': $cond="m.GroupId=$id"; break;
		case 'product': $cond=" m.ProductId=$id"; break;
		case 'document': $cond=" m.DocumentId=$id"; break;
		}
		
		if ( $n > 0 ) {
			for ($i=0;$i<$n;$i++){
				$colstring.="r.realurl_seg_$i,'/',";
			}
			$colstring=substr($colstring,0,-1);
			$colstring = "CONCAT($colstring)";
		} else {
			$colstring = "''";
		}
		if ( $featureId > 0 ) {
			$colstring.= " as path,CONCAT(r.realurl_seg_mapped,'_',f.Name)as name";
			$sql="SELECT ".$colstring." FROM ".RealURLMap_TABLE." r, Menu m, Feature f WHERE r.asim_mapid=m.contextId AND f.Id=".$featureId." AND ". $cond;
		} else {
			$colstring.= " as path, r.realurl_seg_mapped as name";
			$sql="SELECT ".$colstring." FROM ".RealURLMap_TABLE." r, Menu m WHERE r.asim_mapid=m.contextId AND ". $cond;
		}
		
		$res=$this->template->db->sql_query($sql, RealURLMap_TABLE." r, Menu m, Feature f");
		if($res)
		{
			$row = $this->template->db->sql_fetch_row($res);
			$ret = array();
			if($row){
				$ret["path"]=$row[0];
				$ret["name"]=$row[1];
			}
			$ret["other"] = pathinfo($src, PATHINFO_FILENAME);
			$this->template->db->sql_free_result($res);
		}  else{
			$res=$this->template->db->sql_error();			
		}
		
		return $ret;			 
	}
	
}

?>
