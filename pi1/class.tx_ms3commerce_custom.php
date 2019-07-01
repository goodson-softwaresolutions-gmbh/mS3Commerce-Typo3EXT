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
require_once('itx_ms3commerce_custom.php');

/**
 * Default implementation for customization functions.
 * @author philip.masser
 */
class tx_ms3commerce_custom implements itx_ms3commerce_custom {

	public function setup($db, $plugin, $template, $conf, $customConf) {
	}

	/**
	 * Returns null, creating a temporary scaled image, or original if
	 * scaling is not required
	 */
	public function getScaledImagePathProduct($src, $productId, $featureId, $menuId)
	{
		return null;
	}
	
	/**
	 * Returns null, creating a temporary scaled image, or original if
	 * scaling is not required
	 */
	public function getScaledImagePathGroup($src, $groupId, $featureId, $menuId)
	{
		return null;
	}
	
	public function getScaledImagePathDocument($src, $docId, $menuId, $asFeatureId = array())
	{
		return null;
	}
	
	/**
	 * Default uses "###LISTVIEW###" for all menu IDs
	 */
	public function getListviewTemplateName($menuId)
	{
		return null;
	}
	
	/**
	 * Default uses original merker for all product group IDs
	 */
	public function getIncludeTemplateName($marker, $productGroupId)
	{
		return null;
	}
	
	/**
	 * Default does nothing
	 */
	public function callCustomMS3CFunction($functionMarker, $function, $params)
	{
		return "";
	}
	
	/**
	 * Default returns null, causing default-link generation
	 */
	public function buildGroupLink($groupId, $menuId, &$pid = 0, $noRealURL = false)
	{		 
		return null;
	}
	
	/**
	 * Default returns null, causing default-link generation
	 */
	public function buildProductLink($productId, $menuId, &$pid = 0, $noRealURL = false)
	{
		return null;
	}
	
	/**
	 * Default returns null, causing default-link generation
	 */
	public function buildDocumentLink($documentId, $menuId, &$pid = 0, $noRealURL = false)
	{
		return null;
	}
	
	public function adjustSearchRequest($query){
		return $query;
	}
	public function getCustomView() {
		return "";
	}
	
	public function getCustomInclude($include, $context, $elemId, $menuId) {
		return "";
	}

	public function updateQuickCompleteParams(&$url, &$data) {
		return false;
	}

	public function checkFulltextFallbackForTerm($term, &$likeTerm, &$locateTerm) {
		
	}
	
	public function customCheckGroupVisibility($groupId) {
		return null;
	}
	
	public function customCheckProductVisibility($prodId) {
		return null;
	}
	
	public function customCheckDocumentVisibility($docId) {
		return null;
	}
	
	public function customSearchFilterSelection($menutypes, $ttemp, $language, $level, $query) {
		
	}
	
	public function getCustomFilterValues($menutype, $ttemp, $language, $market, $level) {
		return array();
	}
	
	public function customUnmarkSearchRestrictions($ttemp, $query) {
		
	}
	
	public function customLayoutSearchResultsTemplate(&$result,$template) {
		return null;
	}

	public function init() {
		
	}
	
	public function customCheckFeatureVisibility($featureId) {
		
	}
	
	public function getFullTextCustomHandler($type) {
		return null;
	}

	protected static function layoutHelp($helpDef) {
		return self::doLayoutHelp($helpDef, true);
	}
	
	private static function doLayoutHelp($def, $wrap) {
		$ret = "";
		if (array_key_exists('description', $def)) {
			$ret .= $def['description'].'<br/>';
		}
		if (array_key_exists('context', $def)) {
			$ret .= '<b>Kontext:</b> <i>'.$def['context'].'</i><br/>';
		}
		if (array_key_exists('marker', $def)) {
			$ret .= '<b>Marker:</b><ul>';
			foreach ($def['marker'] as $marker => $descr) {
				$ret .= "<li><i>$marker</i>: $descr</li>";
			}
			$ret .= '</ul>';
		}
		if (array_key_exists('subparts', $def)) {
			$ret .= '<b>Subparts:</b><ul>';
			foreach ($def['subparts'] as $subpart => $subdef) {
				$ret .= "<li>";
				$ret .= "<b>$subpart</b>:";
				$ret .= self::doLayoutHelp($subdef, false);
				$ret .= "</li>";
			}
			$ret .= '</ul>';
		}
		
		if ($wrap) {
			return "<div class=\"mS3CHelp\">$ret</div>";
		}
		return $ret;
	}
}

?>
