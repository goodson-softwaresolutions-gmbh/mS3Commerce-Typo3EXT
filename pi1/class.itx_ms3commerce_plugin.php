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

require_once('class.itx_ms3commerce_pagetypehandler.php');

/**
 * Contains an interface to the plugin, which must be available for the template, in order
 * that it may access the plugin.
 * All plugins must implement all functions on this list.
 *
 * @author jordan.stevens
 */
interface itx_ms3commerce_plugin
{
	public function generatePicture( $source, $dest, $width, $height, $isTemp );
	public function getGroupLink($groupId, $menuId = 0, $pid = 0, $itemStart = 0);
	public function getPluginRoot();
	public function getProductLink($productId, $menuId = 0, $pid = 0);
	public function getDocumentLink($documentId, $menuId = 0, $pid = 0, $download = false);
	public function getTemplate($templateName);
	public function setMenuConfVars($conf);
	public function substituteMarker($content, $marker, $markContent);
	public function fileResource( $path );
	public function setPageTitle( $title );
	public function installPageTypeHandler( $pageType, itx_ms3commerce_pagetypehandler $handler);
	
	public function loadSession($key);
	public function storeSession($key, $value);
	public function getPageLink( $pid, $params = array(), $enableCache = true );
	public function pageRedirect( $pid, $params = array(), $force = true, $enableCache = true );
	public function pageRedirectLink( $link, $force = true );
	public function page404Error( $msg );
	public function pageUnavailableError( $msg );
	public function loginUser($user,$password,$logout);
	public function logoutUser();
	public function getUserId();
	
	public function timeTrackStart($key);
	public function timeTrackStop();
}

?>
