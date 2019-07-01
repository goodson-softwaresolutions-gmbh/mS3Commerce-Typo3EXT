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
 * Interface for customization functions.
 * @author philip.masser
 */
interface itx_ms3commerce_custom {
	public function init();
	
	/**
	 * Builds the image path for scaled images as used in products.
	 * @param string $src The original image's path
	 * @param int $productId The product's ID
	 * @param int $featureId The feature ID of the image Feature
	 * @param int $menuId The menu Id in which the product is used. For 
	 *		distinguishing multiple occurances of the same product in different groups
	 * @return array	'path' => Destination file path
	 *					'name' => Destination file name
	 *					'other' => Other path information
	 *		This information is passed to tx_ms3commerce_template::buildScaleImgMarker,
	 *		which prepares the marker for the scaleImage function. Ths in turn uses the 
	 *		configured image name pattern. In this pattern, '%P' is replaced by 'path', 
	 *		'%N' by 'name, and '%O' by 'other'
	 */
	public function getScaledImagePathProduct($src, $productId, $featureId, $menuId);
	/**
	 * Builds the image path for scaled images as used in groups. 
	 * @param string $src The original image's path
	 * @param int $groupId The group's ID
	 * @param int $featureId The feature ID of the image Feature
	 * @param int $menuId The menu Id in which the group is used
	 * @return array	'path' => Destination file path
	 *					'name' => Destination file name
	 *					'other' => Other path information
	 *		This information is passed to tx_ms3commerce_template::buildScaleImgMarker,
	 *		which prepares the marker for the scaleImage function. Ths in turn uses the 
	 *		configured image name pattern. In this pattern, '%P' is replaced by 'path', 
	 *		'%N' by 'name, and '%O' by 'other'
	 */
	public function getScaledImagePathGroup($src, $groupId, $featureId, $menuId);
	/**
	 * Builds the image path for scaled images as used in documents.
	 * @param string $src The original image's path
	 * @param int $docId The document's ID
	 * @param int $menuId The menu Id in which the document is used
	 * @param array $asFeatureId Inidicates if the image is used as the
	 *		value of another Object's feature, or the main-document of a Document-Object.
	 *		If an empty array, this is the main document of a Document-Object.
	 *		If used as Feature Value:
	 *			'featureId' => (int) The Feature ID
	 *			'groupId' => (int) The Group this is a value of, or 0
	 *			'productId' => (int) The Product this is a value of, or 0
	 *			'documentId' => (int) The Document this is a value of, or 0
	 *			'menuId' => (int) The Menu Id of the containing Object
	 * @return array	'path' => Destination file path
	 *					'name' => Destination file name
	 *					'other' => Other path information
	 *		This information is passed to tx_ms3commerce_template::buildScaleImgMarker,
	 *		which prepares the marker for the scaleImage function. Ths in turn uses the 
	 *		configured image name pattern. In this pattern, '%P' is replaced by 'path', 
	 *		'%N' by 'name, and '%O' by 'other'
	 */
	public function getScaledImagePathDocument($src, $docId, $menuId, $asFeatureId = array());
	/**
	 * Returns the template name for the listview template to be used for the
	 * given Menu Id. This can be used for different LISTVIEW Tempaltes based
	 * on Hierarchy Depth
	 * @param int $menuId The menu's ID
	 * @return string The list view template name (incl. '###' for markers) 
	 */
	public function getListviewTemplateName($menuId);
	/**
	 * Returns the template name for included sub-templates based on the 
	 * group's Id. This can be used to include different templates based
	 * on group properties, e.g. for handling different levels of displayed
	 * sub-groups
	 * @param string $marker The original include marker
	 * @param int $productGroupId The group's Id
	 * @return string A possibly modified include marker, that accounts for
	 *		the varied include template. 
	 *		E.g. getIncludeTemplateName('###INCLUDE_LEVEL2###', $myGroupId)
	 *		could return '###INCLUDE_LEVEL2_WITHSUBGROUP###' for certain groups,
	 *		and the original marker for others
	 */
	public function getIncludeTemplateName($marker, $productGroupId);
	/**
	 * Calls a customer specific Frontend Function
	 * @param string $functionMarker The orignial Function Call marker
	 * @param string $function The pure function name
	 * @param array $params The parsed function parameters as associative map
	 * @return string The result content of the function call that should 
	 *		replace the function call marker
	 */
	public function callCustomMS3CFunction($functionMarker, $function, $params);
	/**
	 * Builds a link to a group. This method can be used to only modify the PID
	 * by changing the $pid parameter and returning null
	 * @param int $groupId The id of the group
	 * @param int $menuId The menu id of the group
	 * @param int $pid [inout] The typo3 Page id for the link
	 * @param boolean $noRealURL If true, the link will be post-processed
	 * 		by RealURL, false otherwise.
	 * @return string The relative link to the group. If the return value evaluates
	 * 		to false, default link generation is perfomed.
	 */
	public function buildGroupLink($groupId, $menuId, &$pid = 0, $noRealURL = false);
	/**
	 * Builds a link to a product. This method can be used to only modify the PID
	 * by changing the $pid parameter and returning null
	 * @param int $productId The id of the product
	 * @param int $menuId The menu id of the product
	 * @param int $pid The typo3 Page Id of the link
	 * @param boolean $noRealURL If true, the link will be post-processed
	 * 		by RealURL, false otherwise.
	 * @return string The relative link to the product. If the return value evaluates
	 * 		to false, default link generation is perfomed.
	 */
	public function buildProductLink($productId, $menuId, &$pid = 0, $noRealURL = false);
	
	/**
	 * Builds a link to a document. This method can be used to only modify the PID
	 * by changing the $pid parameter and returning null
	 * @param int $documentId The id of the document
	 * @param int $menuId The menu id of the document
	 * @param int $pid The typo3 Page Id of the link
	 * @param boolean $noRealURL If true, the link will be post-processed
	 * 		by RealURL, false otherwise.
	 * @return (string) The relative link to the document. If the return value evaluates
	 * 		to false, default link generation is perfomed.
	 */
	public function buildDocumentLink($documentId, $menuId, &$pid = 0, $noRealURL = false);
	
	
	/**
	 * Adjusts a search request for applying custom search parameter rules
	 * @param array The original request
	 * return The adjusted request
	 */
	public function adjustSearchRequest($query);
	
	/**
	 * Handles custom views. Custom view types must be managed by the
	 * customization module, e.g. by a certain configuration parameter
	 * @return string The custom view content
	 */
	public function getCustomView();
	
	/**
	 * Handles a custom include template
	 * @param string $include  The name of the custom include marker. 
	 *			Determines the view
	 * @param int $context  Context. 1 = Group, 2 = Product, 3 = Document
	 * @param int $elemId  Id of the current context element
	 * @param int $menuId  Menu Id of the current context element
	 * @return string Custom include content
	 */
	public function getCustomInclude($include, $context, $elemId, $menuId);
	
	/**
	 * Checks if a fulltext search should fall back to a contains-search.
	 * This is necessary if the search term is not fit for the fulltext search.
	 * @param string $term  The search term
	 * @param string $likeTerm  The like-term including wildcards for the contains search (out)
	 * @param string $locateTerm  The search term for LOCATE inside the found elements for ranking (out)
	 * @return boolean true if search should fall back to contains-search, false otherwise
	 */
	public function checkFulltextFallbackForTerm($term, &$likeTerm, &$locateTerm);
	
	/**
	 * Modifies parameters for a search suggestion field.
	 * @param string url  Override URL for suggestion handler (out)
	 * @param array data  Key-Value pairs for parameters sent to suggestion handler (out)
	 * @return boolean true if the default handler should be called
	 */
	public function updateQuickCompleteParams(&$url, &$data);

	/**
	 * Defines custom visibility rules for groups.
	 * @param int $groupId The groups Id
	 * @return boolean true if the group is visible, false if it is not visible,
	 * and null if default visibility rules should be applied
	 */
	public function customCheckGroupVisibility($groupId);
	
	/**
	 * Defines custom visibility rules for products.
	 * @param int $prodId The products Id
	 * @return boolean true if the product is visible, false if it is not visible,
	 * and null if default visibility rules should be applied
	 */
	public function customCheckProductVisibility($prodId);
	
	/**
	 * Defines custom visibility rules for documents.
	 * @param int $docId The documents Id
	 * @return boolean true if the document is visible, false if it is not visible,
	 * and null if default visibility rules should be applied
	 */
	public function customCheckDocumentVisibility($docId);
	
	/**
	 * Defines custom visibility rules for Features.
	 * @param int $featureId The Feature Id to be checked
	 * @return boolean true if the Feature is visible, false if it is not visible,
	 * and null if default visibility rules should be applied
	 */
	public function customCheckFeatureVisibility($featureId);
	
	
	/**
	 * Modifies the internal search temporary table to perform custom filtering.
	 * The table contains a field 'selcustom' that must be set to 0 for items
	 * that do not match the search criteria. By default, the value is set to 1
	 * meaning that no custom filter is active.
	 * @param string $menutype The type of item to filter (Product, Group, Document)
	 * @param string $ttemp The name of the temporary table
	 * @param int $language The language Id
	 * @param int $level The search level for hierarchical filters
	 * @param mixed $query The current search query object
	 */
	public function customSearchFilterSelection($menutypes, $ttemp, $language, $level, $query);
	
	public function getCustomFilterValues($menutype, $ttemp, $language, $market, $level);
	
	/**
	 * Modifies the internal search temporary table for custom visibility rules.
	 * The fields delRestrictions and delRights must be set to 0 for items that
	 * are visible due to custom rules, otherwise they are not returned if filtered
	 * out by standard rules.
	 * @param string $ttemp The name of the temporary table
	 * @param mixed $query The current search query object
	 */
	public function customUnmarkSearchRestrictions($ttemp, $query);
	
	public function customLayoutSearchResultsTemplate(&$result,$template);
	
	public function getFullTextCustomHandler($type);
	
}

?>
