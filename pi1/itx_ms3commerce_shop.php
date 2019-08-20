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
 * Interface for Shop Systems 
 * @author valentin.giselbrecht
 */
interface itx_ms3commerce_shop {
	
	/**
	 * Konstruktor
	 */
	public function __construct($template);
	
	/**
	 * Ist dieser Marker ein Shop Marker
	 */
	public function isShopMarker($marker);
	
	/**
	 * Füllt die Shop Marker
	 */
	public function fillShopMarkerContent($marker,$productId);
	
	
	/**
	 *retrieve the order data by orderId 
	 */
	public function getOrderById($orderId);
	
	public function isShopView($view);
	public function getShopView($view);
	public function getPrice($ms3Oid, $qty = 1);
	public function formatPrice($price);
	public function clearBasket();
}
?>
