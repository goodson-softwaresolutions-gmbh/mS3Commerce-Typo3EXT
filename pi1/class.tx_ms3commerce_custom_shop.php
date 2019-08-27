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
require_once('itx_ms3commerce_custom_shop.php');

/**
 * Default Custom Price Calculation
 * @author valentin.giselbrecht
 */
class tx_ms3commerce_custom_shop implements itx_ms3commerce_custom_shop {
	
	
	public function setup($db,$dbutils,$conf,$calc)
	{
		return null;
	}
	
	public function init()
	{
		
	}
	
	public function getPrice($ms3Oid, $forQty, $qty, $markt, $userper, $variant = null)
	{
		return null;
	}
	
	public function getMinQuantityForPrice($ms3Oid, $forQty, $variant = null)
	{
		return null;
	}
	
	public function getAvailability($ms3Oid)
	{
		return null;
	}
	
	public function adjustBasket(&$basket, &$changes)
	{
		return null;
	}
	
	public function adjustBasketItem(&$item)
	{
		return null;
	}
	
	public function adjustShipping($basket, $userper)
	{
		return $basket;
	}
	
	public function getNotReducedPrice($ms3Oid, $qty, $markt, $userperm)
	{
		return null;
	}
	
	public function getMarket($markt) {
		return $markt;
	}

	public function getOrderById($orderId){
		return null;
	}

	public function getBasketItemQuantity($basket, $uid) {
		return null;
	}
	
	public function finalizeOrder(&$orderObj) {
		return null;
	}

	public function fillShopMarkerContent($marker,$productId,$ms3Oid,$ttUid,$basket = null) {
		return null;
	}

	public function getItemMarker($productId, $ms3Oid, $ttUid, $item=null) {
		return null;
	}

	public function getOrderFromBasket() {
		return null;
	}
	
	public function getOrderListCondition() {
		return null;
	}

	public function reactivateBasket($order) {
		return null;
	}

	public function addGlobalMarkers(&$markerArray, $existingMarkers) {
	}

	public function getOCIMapping($ms3Oid, $quantity, $varLine) {
		return array();
	}
	
	public function suppressFinalizeMail(&$toEMail = null, &$ccEMail = null, &$bccEMail = null) {
		return false;
	}
	
	public function addAddressInfoToOrder($orderId, &$map, $bil, $del) {
		
	}

	public function finalizeOCI(&$orderObj) {
		
	}

	public function getOCIStartLink() {
		
	}

	public function getOrderDetailMarkers(&$markerArray, $item, $order) {
		
	}

	public function getOrderListMarkers(&$markerArray, $orderUid, $orderRow) {
		
	}
	
	public function getItemArrayForOCIRequest($productId, $qty) {
		return null;
	}
}

?>
