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
 * Interface for Custom Shop Calculation
 * @author valentin.giselbrecht
 */
interface itx_ms3commerce_custom_shop {
	public function setup($db, $dbutils, $conf, $calc);
	
	public function init();
	
	/**
	 * Preis
	 */
	public function getPrice($ms3Oid, $forQty, $qty, $markt, $userper, $variant = null);
	
	public function getMinQuantityForPrice($ms3Oid, $forQty, $variant = null);

	/**
	 * Verfügbarkeit
	 */
	public function getAvailability($ms3Oid);
	
	/**
	 * Einzelnes Item ändern 
	 */
	public function adjustBasketItem(&$item);
	
	/**
	 * Warenkorb ändern
	 */
	public function adjustBasket(&$basket, &$changes);
	
	/**
	 * Versandbrechung
	 */
	public function adjustShipping($basket, $userper);
	
	
	/**
	 * Der nicht reduzierte Preis ermitteln 
	 */
	public function getNotReducedPrice($ms3Oid, $qty, $markt, $userperm);
	
	/**
	 * Ändert gegebenenfalls den Markt 
	 */
	public function getMarket($markt);
	
	/**
	 * Custom Shop Markers
	 * Must return null for default implementation! 
	 */
	public function fillShopMarkerContent($marker,$productId,$ms3Oid,$ttUid,$basket = null);
	
	/**
	 * Custom Shop Markers
	 */
	public function getItemMarker($productId,$ms3Oid,$ttUid,$item=null);
	
	public function getOrderFromBasket();
/**
	 * Customize Condition for getting order list 
	 */
	public function getOrderListCondition();
	
	public function getOrderListMarkers(&$markerArray, $orderUid, $orderRow);
	public function getOrderDetailMarkers(&$markerArray, $item, $order);
	
	public function getBasketItemQuantity($basket, $uid);
	
	/**
	 * Reactivate a basket stored as order
	 */
	public function reactivateBasket($order);
	
	public function finalizeOrder(&$orderObj);
	
	public function finalizeOCI(&$orderObj);
	
	public function getOCIMapping($ms3Oid, $quantity, $varLine);

	public function getOCIStartLink();

	public function addGlobalMarkers(&$markerArray, $existingMarkers);
	
	public function suppressFinalizeMail(&$toEMail = null, &$ccEMail = null, &$bccEMail = null);
	
	public function addAddressInfoToOrder($orderId, &$map, $bil, $del);
	
	public function getItemArrayForOCIRequest($productId, $qty);
}
?>
