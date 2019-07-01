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
 * Implementation when the Shop is not active.
 * @author valentin.giselbrecht
 */
require_once('itx_ms3commerce_shop.php');

class tx_ms3commerce_dummyshop implements itx_ms3commerce_shop {
	
	public function __construct($template)
	{
		return null;
	}
	
	public function isShopMarker($marker)
	{
		return null;
	}
	
	public function fillShopMarkerContent($marker,$productId)
	{
		return null;
	}

	public function getOrderById($orderId) {
		return null;
	}

	public function formatPrice($price) {
		
	}

	public function getPrice($asimOid, $qty = 1) {
		
	}

	public function getShopView($view) {
		
	}

	public function isShopView($view) {
		
	}
	
	public function clearBasket() {
		
	}
	
}
?>
