<?php

/* * *************************************************************
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
 * ************************************************************* */

require_once(__DIR__ . '/../load_dataTransfer_config.php');

/**
 * Implementation when tt_product used as shop system.
 * @author valentin.giselbrecht
 */
require_once('itx_ms3commerce_shop.php');
require_once("class.tx_ms3commerce_custom_shop.php");
require_once("class.tx_ms3commerce_DbUtils.php");
require_once("class.tx_ms3commerce_linker.php");
require_once("class.tx_ms3commerce_pi1.php");
@include_once('class.tx_ms3commerce_OCI.php');

class tx_ms3commerce_tt_products implements itx_ms3commerce_shop {

	/** @var tx_ms3commerce_template */
	var $template;

	/** @var tx_ms3commerce_shop_calc */
	var $calc;
	var $staticInfo;
	/*	 * @var tx_ms3commerce_OCI */
	var $oci;

	/** @var itx_ms3commerce_custom_shop */
	var $custom;

	/**
	 * Konstruktor
	 * @param type $template 
	 */
	public function __construct($template) {
		$this->template = $template;
		$this->calc = new tx_ms3commerce_shop_calc($this->template->db, $this->template->conf['shop_market'], $this->template->conf, $this->template->marketId, $this->template->languageId, false, $this->template->plugin->linker);
		$this->custom = &$this->calc->custom;
		require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('static_info_tables') . 'pi1/class.tx_staticinfotables_pi1.php');
		//Mithilfe dieser Funktion kÃ¶nnen lÃ¤nderspeziefische Preisanzeigen gemacht werden
		//Wird momentan nicht verwendet
		$staticInfoObj = &TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('&tx_staticinfotables_pi1');
		if ($staticInfoObj->needsInit()) {
			$staticInfoObj->init();
		}
		$this->staticInfo = $staticInfoObj;

		// Check for basket recreation
		if ($_REQUEST['basket_reactivate']) {
			$orderId = $this->calc->getOrderIdFromParam();
			$this->calc->reactivateBasket($orderId);
			unset($_REQUEST['basket_reactivate']);
		}
	}

	public function isShopView($view) {
		if (strtolower(substr($view, 0, 4)) == 'shop') {
			return true;
		}
		return false;
	}

	public function getShopView($view) {
		switch (strtolower($view)) {
			case 'shoporders':
				return $this->getShopOrderList();
			case 'shoporderdetail':
				return $this->getShopOrderDetail();
			case 'shopbasketview':
				return $this->getShopBasketView();
		}
	}

	public function getPrice($asimOid, $forQty = 1, $qty = 1, $variant = null) {
		return $this->calc->getPrice($asimOid, $forQty, $qty, $variant);
	}

	public function formatPrice($price) {
		return $this->calc->formatPrice($price);
	}

	/**
	 * Ist der Marker ein Shop Marker?
	 * @param type $marker
	 * @return boolean 
	 */
	public function isShopMarker($marker) {
		if (substr($marker, 0, 5) == 'SHOP_') {
			return true;
		}
		return false;
	}

	/**
	 * Product Marker erstellen
	 * @global type $TSFE
	 * @param type $marker
	 * @param type $productId
	 * @return string 
	 */
	public function fillShopMarkerContent($marker, $productId) {
		$asimOid = $this->calc->getAsimOidForProdId($productId);
		$uid = $this->calc->getTTUidForAsimOid($asimOid);

		$basket = $this->calc->getCleanedTTBasket();
		$cust = $this->calc->custom->fillShopMarkerContent($marker, $productId, $asimOid, $uid, $basket);
		if ($cust !== null) {
			return $cust;
		}

		switch ($marker) {
			//aktuelle Menge
			case 'SHOP_BASKET_QTY':
				return $this->calc->getItemQty($basket, $uid);
				break;
			//eingabe Feld für Menge, wenn Menge 0 wird 1 eingefügt
			case 'SHOP_BASKET_QTY_FIELD':
				$uidQty = $this->calc->getItemQty($basket, $uid);
				if ($uidQty == 0)
					$qty = 1;
				else
					$qty = $uidQty;
				return '<input class="shop_basket_qty_field" type="text" name="ttp_basket[' . $uid . '][quantity]" value="' . $qty . '" maxlength="8" />';
				break;
			//Name mit welchem per POST oder GET ein Product in den Warenkorb gesendet werden kann
			case 'SHOP_BASKET_NAME':
				return 'ttp_basket[' . $uid . '][quantity]';
				break;
			//Link auf welcher Seite verlinkt wird wenn ein Produkt bestellt wird üb
			case 'SHOP_ADD_BASKET_LINK':
				//Wurde basket_pid angegeben wird auf den Warenkorb verlinkt
				if (isset($this->template->conf["basket_pid"])) {
					return $this->template->plugin->pi_getPageLink($this->template->conf["basket_pid"], '', '');
				}
				//Ansonsten auf die eigene Seite
				else {
					return $_SERVER['REQUEST_URI'];
				}
				break;
			//Anzeige des aktuellen Preis
			case 'SHOP_PRICE':
				$price = $this->calc->getPrice($asimOid, 1);
				$price = $this->calc->formatPrice($price);
				return $price;
				break;
			case 'SHOP_PRICE_FOR_QTY':
				$uidQty = $this->calc->getItemQty($basket, $uid);
				$price = $this->calc->getPrice($asimOid, $uidQty, 1, $basket[$uid]);
				$price = $this->calc->formatPrice($price);
				return $price;
				break;
			case 'SHOP_PRICE_TOTAL':
				$uidQty = $this->calc->getItemQty($basket, $uid);
				$price = $this->calc->getPrice($asimOid, $uidQty, $uidQty, $basket[$uid]);
				$price = $this->calc->formatPrice($price);
				return $price;
				break;

			case 'SHOP_PRICE_NOT_REDUCED':
				$price = $this->calc->getPrice($asimOid);
				$oldprice = $this->calc->getNotReducedPrice($asimOid);

				if ($price != $oldprice) {
					$oldprice = $this->calc->formatPrice($oldprice);
					return $oldprice;
				}
				break;
			//Anzeige der Verfügbarkeit
			case 'SHOP_AVAILABILITY':
				return $this->calc->getAvailability($asimOid);
				break;
			//Anzeige der Notiz zum Produkt
			case 'SHOP_BASKET_NOTE':
				$notes = $this->template->plugin->loadSession("shopNotes");
				return $notes[$uid];
				break;
			//Name mit welchem per POST oder GET eine Notiz hinzugefügt werden kann
			case 'SHOP_BASKET_NOTE_NAME':
				return 'ttp_note[' . $uid . ']';
				break;
			case 'SHOP_CUR_SYM':
				return $this->template->conf['currencySymbol'];
				break;
			case 'SHOP_PRODUCT_UID':
				return $uid;
				break;

			default:
				//Anzeige des Prices für eine bestimmte Menge
				if (substr($marker, 0, 13) == 'SHOP_PRICE(q=') {
					$q = intval(substr($marker, 13, -1));
					$price = $this->calc->getPrice($asimOid, $q, 1);
					$price = $this->calc->formatPrice($price);
					return $price;
				}
				if (substr($marker, 0, 25) == 'SHOP_PRICE_NOT_REDUCED(q=') {
					$q = intval(substr($marker, 25, -1));
					$oldprice = $this->calc->getNotReducedPrice($asimOid, $q);
					$oldprice = $this->calc->formatPrice($oldprice);

					return $oldprice;
				}
				//Gibt die Menge Plus die hinter add_ angegeben Menge zurück
				//Wird z.B. benötigt wenn immer nur ein Produkt hinzugefügt werden soll
				if (substr($marker, 0, 20) == 'SHOP_BASKET_QTY_ADD_') {
					$q = substr($marker, 20);
					$uidQty = $this->calc->getItemQty($basket, $uid);
					return $uidQty + $q;
				}
				break;
		}
	}

	public function getOrderById($orderId) {
		return $this->calc->getOrderById($orderId);
	}

	function clearBasket() {
		return $this->calc->clearBasket();
	}

	private function getShopOrderList() {
		// Might redirect to the SHOP template file
		if (array_key_exists('shopTemplate', $this->template->conf)) {
			$templateCode = $this->template->plugin->fileResource($this->template->conf['shopTemplate']);
			//$templateCode = $this->template->tplutils->getSubpart($tmpl, '###ORDERS_LIST_TEMPLATE###');
		} else {
			$templateCode = $this->template->plugin->getTemplate('###SHOP###');
		}

		$feusers_uid = $GLOBALS['TSFE']->fe_user->user['uid'];
		if (!$feusers_uid) {
			return $this->template->tplutils->getSubpart($templateCode, '###MEMO_NOT_LOGGED_IN###');
		}

		$cond = array();
		$cond['select'] = '*';
		$cond['from'] = 'sys_products_orders';
		$cond['order'] = 'crdate';

		$cond['limit'] = '';

		// Get a custom where, or default if none
		$cond = $this->calc->custom->getOrderListCondition();
		if (!$cond) {
			$cond['where'] = 'feusers_uid=' . intval($feusers_uid) . ' AND NOT deleted';
		} else if (!is_array($cond)) {
			$cond['where'] = $cond;
		}

		return $this->layoutOrderList($templateCode, $cond);
	}

	function layoutOrderList($template, $query, $pidTracking = null, $pidOverview = null) {
		///////////////////////////////////////////
		// COPIED FROM tt_products: FILE view/class.tx_ttproducts_order_view.php, function printView
		// WITH MODIFICATIONS FOR THIS IMPLEMENTATION
		///////////////////////////////////////////
		$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
		$res = $t3db->exec_SELECTquery($query['select'], $query['from'], $query['where'], '', $query['order'], $query['limit']);

		if ($pidTracking == null) {
			$pidTracking = $this->template->conf['shop.']['pid_tracking'];
		}
		if ($pidOverview == null) {
			$pidOverview = $this->template->conf['shop.']['overview_pid'];
		}

		$content = $this->template->tplutils->getSubpart($template, '###ORDERS_LIST_TEMPLATE###');
		$orderitem = $this->template->tplutils->getSubpart($content, '###ORDER_ITEM###');
		$count = $t3db->sql_num_rows($res);
		if ($count) {
			// Fill marker arrays
			$markerArray = array();
			$subpartArray = array();
			while ($row = $t3db->sql_fetch_assoc($res)) {
				$markerArray['###TRACKING_CODE###'] = $row['tracking_code'];
				$markerArray['###ORDER_DATE###'] = $this->template->plugin->cObj->stdWrap($row['crdate'], $this->template->conf['shop.']['orderDate_stdWrap.']);
				$markerArray['###ORDER_NUMBER###'] = $row['uid'];

				$markerArray['###ORDER_CREDITS###'] = '';
				$markerArray['###ORDER_AMOUNT###'] = $this->calc->formatPrice($row['amount']);
				$markerArray['###ORDER_NAME###'] = $row['mS3C_basketname'];

				$markerArray['###ORDER_LINK_TRACKING###'] = $this->template->plugin->getPageLink($pidTracking, array('tracking' => $row['tracking_code']));

				$this->calc->custom->getOrderListMarkers($markerArray, $row['uid'], $row);

				$thisorder = $this->template->tplutils->substituteMarkerArray($orderitem, $markerArray);
				$thisorder = $this->template->tplutils->substituteSubpart($thisorder, "###REMOVE###", '');

				$orderlistc .= $thisorder;
			}
			$content = $this->template->tplutils->substituteSubpart($content, '###ORDER_LIST###', $orderlistc);
			$t3db->sql_free_result($res);

			$res1 = $t3db->exec_SELECTquery('username ', 'fe_users', 'uid="' . intval($feusers_uid) . '"');
			if ($row = $t3db->sql_fetch_assoc($res1)) {
				$username = $row['username'];
			}
			$t3db->sql_free_result($res1);

			$markerArray = array();
			$subpartArray = array();

			$markerArray['###CLIENT_NUMBER###'] = $feusers_uid;
			$markerArray['###CLIENT_NAME###'] = $username;
			$markerArray['###CREDIT_POINTS_SAVED###'] = '';
			$markerArray['###CREDIT_POINTS_SPENT###'] = '';
			$markerArray['###CREDIT_POINTS_CHANGED###'] = '';
			$markerArray['###CREDIT_POINTS_USED###'] = '';
			$markerArray['###CREDIT_POINTS_GIFTS###'] = '';
			$markerArray['###CREDIT_POINTS_TOTAL###'] = '';
			$markerArray['###CREDIT_POINTS_VOUCHER###'] = '';
			$markerArray['###CALC_DATE###'] = date('d M Y');
			$markerArray['###PID_TRACKING###'] = $pidTracking;
			$content = $this->template->tplutils->substituteMarkerArray($content, $markerArray);
			$content = $this->template->tplutils->substituteSubpart($content, '###ORDER_NOROWS###', '');
		} else {
			$t3db->sql_free_result($res);
			$norows = $this->template->tplutils->getSubpart($content, '###ORDER_NOROWS###');
			$content = $norows;
		}

		$linkOverview = $this->template->plugin->getPageLink($pidOverview);
		$content = $this->template->tplutils->substituteMarker($content, '###ORDER_LINK_OVERVIEW###', $linkOverview);

		return $content;
	}

	private function getShopOrderDetail() {
		$orderId = $this->calc->getOrderIdFromParam();

		if ($orderId == '') {
			return '';
		}

		// Get order detail
		$order = $this->getOrderById($orderId);
		if (!$order) {
			return '';
		}

		$trackingId = $order->generalInfo['trackingId'];

		// Might redirect to the SHOP template file
		if (array_key_exists('shopTemplate', $this->template->conf)) {
			$template = $this->template->plugin->fileResource($this->template->conf['shopTemplate']);
		} else {
			$template = $this->template->plugin->getTemplate('###SHOP###');
		}

		$template = $this->template->tplutils->getSubpart($template, '###ORDER_DETAIL_VIEW###');
		$tmplItem = $this->template->tplutils->getSubpart($template, '###ORDER_ITEMS###');
		$markerItems = $this->template->tplutils->getMarkerArray($tmplItem);
		$contItem = '';

		foreach ($order->items as $item) {
			$pid = $this->calc->getProdIdForAsimOid($item['asimOid']);
			$subs = $this->template->fillProductMarkerContentArray($markerItems, $pid);
			$subs['###ORDER_QUANTITY###'] = $item['quantity'];
			// THESE WILL USE NEW PRICES ACCORDING TO USER!!!
			//$subs['###ORDER_PRICE###'] = $this->calc->getPrice($item->asimOid, 1);
			//$subs['###ORDER_PRICE_TOTAL###'] = $this->calc->getPrice($item['asimOid'], $item['quantity']);

			$this->calc->custom->getOrderDetailMarkers($markerArray, $item, $order);

			$contItem .= $this->template->tplutils->substituteMarkerArray($tmplItem, $subs);
		}

		// Reactivate fields
		$subs = array();
		if (isset($this->template->conf["basket_pid"])) {
			$subs['###BASKET_REACTIVATE_LINK###'] = $this->template->plugin->pi_getPageLink($this->template->conf["basket_pid"], '', '');
		} else {
			$subs['###BASKET_REACTIVATE_LINK###'] = $_SERVER['REQUEST_URI'];
		}
		$subs['###BASKET_REACTIVATE_FIELD###'] = '<input type="hidden" name="basket_reactivate" value="1"/>
			<input type="hidden" name="tracking" value="###ORDER_ID###"/>';

		$subs['###ORDER_NAME###'] = $order->generalInfo['name'];

		$this->calc->custom->addGlobalMarkers($subs);
		$this->calc->custom->getOrderDetailMarkers($subs, null, $order);

		$template = $this->template->tplutils->substituteMarkerArray($template, $subs);
		$template = $this->template->tplutils->substituteSubpart($template, "###ORDER_ITEMS###", $contItem);
		$template = $this->template->tplutils->substituteSubpart($template, "###REMOVE###", "");
		$template = $this->template->tplutils->substituteSubpart($template, "###REMOVE_SUBPART###", '');
		$template = $this->template->tplutils->substituteMarker($template, '###ORDER_ID###', $trackingId);

		return $template;
	}

	private function getShopBasketView() {
		$order = $this->calc->getOrderFromBasket();

		// Might redirect to the SHOP template file
		if (array_key_exists('shopTemplate', $this->template->conf)) {
			$template = $this->template->plugin->fileResource($this->template->conf['shopTemplate']);
			//$templateCode = $this->template->tplutils->getSubpart($tmpl, '###ORDERS_LIST_TEMPLATE###');
		} else {
			$template = $this->template->plugin->getTemplate('###SHOP###');
		}

		$template = $this->template->tplutils->getSubpart($template, '###BASKET_VIEW###');
		$tmplItem = $this->template->tplutils->getSubpart($template, '###BASKET_ITEMS###');
		$markerItems = $this->template->tplutils->getMarkerArray($tmplItem);
		$contItem = '';

		if ($order->items == '') {
			$template = $this->template->tplutils->substituteSubpart($template, "###BASKET_ITEMS###", '');
			$marker = $this->template->fillFeatureMarkerContentArray($this->template->tplutils->getMarkerArray($template));
			$template = $this->template->tplutils->substituteMarkerArray($template, $marker);
		} else {

			$template = $this->template->tplutils->substituteSubpart($template, "###BASKET_EMPTY###", '');
			foreach ($order->items as $key => $item) {
				$pid = $this->calc->getProdIdForAsimOid($item['asimOid']);
				$subs = $this->template->fillProductMarkerContentArray($markerItems, $pid);
				$subs['###BASKET_POSITION###'] = $key + 1;
				$contItem .= $this->template->tplutils->substituteMarkerArray($tmplItem, $subs);
			}
			$template = $this->template->tplutils->substituteSubpart($template, "###BASKET_ITEMS###", $contItem);
		}
		$template = $this->template->tplutils->substituteSubpart($template, "###REMOVE_SUBPART###", '');
		return $template;
	}

}

class tx_ms3commerce_shop_calc {

	/** @var tx_ms3commerce_db */
	var $db;

	/** @var itx_ms3commerce_custom_shop */
	var $custom;

	/** Der Price Markt z.B.: DEU oder COM */
	var $market;

	/** @var tx_ms3commerce_DbUtils */
	var $dbutils;
	var $conf;
	var $marketId;
	var $languageId;
	var $fromTTProducts;

	/** @var tx_ms3commerce_linker */
	var $linker;

	/**
	 * Konstuktor
	 * @param type $db
	 * @param type $markt 
	 */
	function __construct($db, $markt, $conf, $marketId, $languageId, $fromTTProducts = false, $linker = null) {
		$this->db = $db;
		$this->conf = $conf;
		$this->marketId = $marketId;
		$this->languageId = $languageId;
		$this->dbutils = new tx_ms3commerce_DbUtils($db, $marketId, $languageId, null);

		$this->fromTTProducts = $fromTTProducts;

		$this->custom = tx_ms3commerce_pi1::makeObjectInstance('tx_ms3commerce_custom_shop');
		$this->custom->setup($db, $this->dbutils, $conf, $this);
		$this->market = $this->custom->getMarket($markt);

		$this->linker = $linker;
	}

	function init() {
		$this->custom->init();
	}

	function isBasketEmpty($validateItems = false) {
		$basket = $this->getCleanedTTBasket($validateItems);
		if (is_null($basket) || count($basket) == 0) {
			return true;
		}
		return false;
	}

	function getCleanedTTBasket($validateItems = false) {
		$basket = tx_ms3commerce_plugin_sessionUtils::loadSession("::basketExt");
		return $this->cleanupTTBasket($basket, $validateItems);
	}

	function cleanupTTBasket($basket, $validateItems = false) {
		if ($basket == null || empty($basket)) {
			return array();
		}
		foreach ($basket as $art => $val) {
			if (empty($val)) {
				// If value is not set, it is removed
				unset($basket[$art]);
			} else {
				if ($validateItems) {
					// Strange case where tt_products do not represent valid asim products!
					$uid = $this->getProdIdForTTuid($art);
					if (!$uid) {
						unset($basket[$art]);
						continue;
					}
				}

				// Check all variants
				foreach ($val as $var => $q) {
					if (intval($q) == 0) {
						unset($basket[$art][$var]);
					}
				}
				if (count($basket[$art]) == 0) {
					unset($basket[$art]);
				}
			}
		}
		return $basket;
	}

	function getItemQty($basket, $uid) {
		$uidQty = $this->custom->getBasketItemQuantity($basket, $uid);
		if ($uidQty === null) {
			$uidQty = (array_key_exists($uid, $basket)) ? $basket[$uid][';;;;;;;;;'] : 0;
		}
		return $uidQty;
	}

	function getItemNote($uid) {
		$notes = tx_ms3commerce_plugin_sessionUtils::loadSession("shopNotes");
		return $notes[$uid];
	}

	function formatPrice($price) {
		// Like in tt_products
		$price = round($price, 10);
		return number_format($price, intval($this->conf['shop.']['priceDec']), $this->conf['shop.']['priceDecPoint'], $this->conf['shop.']['priceThousandPoint']);
	}

	function getBasketPrices() {
		$basket = &TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('&tx_ttproducts_basket');
		return $basket->calculatedArray;
	}

	function clearBasket() {
		$basket = &TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('&tx_ttproducts_basket');
		$basket->clearBasket(true);
	}

	function getOrderFromBasket() {
		$order = $this->custom->getOrderFromBasket();
		if ($order != null) {
			return $order;
		} else {
			$basket = $this->getCleanedTTBasket();

			$items = array();
			foreach ($basket as $pid => $variants) {
				$asimOid = $this->getAsimOidForTTUid($pid);
				$q = $variants[';;;;;;;;;'];
				$items[] = array(
					'asimOid' => $asimOid,
					'quantity' => $q,
					'tt_article_uid' => 0
				);
			}

			$order = new stdClass();
			$order->generalInfo = array('orderId' => 'NULL');
			$order->items = $items;
			return $order;
		}
	}

	public function reactivateBasket($orderId) {
		$order = $this->getOrderById($orderId);
		$basket = $this->custom->reactivateBasket($order);
		if (!$basket) {
			$items = $order->items;
			$basket = array();
			foreach ($items as $item) {
				$pid = $this->getTTUidForAsimOid($item['asimOid']);
				$qty = $item['quantity'];
				$basket[$pid][';;;;;;;;;'] = $qty;
			}
		}

		tx_ms3commerce_plugin_sessionUtils::storeSession('::basketExt', $basket);
	}

	/**
	 * Herausfinden des Preises aus der shopprice Tabelle
	 * @global type $TSFE
	 * @param type $asimOid
	 * @param type $qty
	 * @return price 
	 */
	function getPrice($asimOid, $forQty = 1, $qty = 1, $variant = null) {
		$markt = $this->market;
		$userperm = $this->getUserRights();

		// Custom price
		$price = $this->custom->getPrice($asimOid, $forQty, $qty, $markt, $userperm, $variant);
		if ($price !== null) {
			return $price;
		}

		// Simple price
		$pid = $this->getProdIdForAsimOid($asimOid);
		$fid = $this->dbutils->getFeatureIdByName($this->conf['product_price_feature_name']);
		if ($fid != 0) {
			$price = $this->dbutils->getProductValue($pid, $fid, "NUMBER");
			$price = doubleval($price);
			if ($price !== null && $price !== '') {
				return $price;
			}
		}

		// Do normal price finding
		if ($markt) {
			$markt = "= '" . $markt . "'";
		} else {
			$markt = 'IS NULL';
		}
		if ($userperm) {
			foreach ($userperm as $perm) {
				$usersql .= "'" . $perm . "', ";
			}
			$usersql = substr($usersql, 0, -2);
			$rs = $this->db->exec_SELECTquery("Price", "ShopPrices", "ProductAsimOID = '" . $asimOid . "' AND Market " . $markt . " AND User in (" . $usersql . ") AND StartQty <= " . $forQty, '' .
					"StartQty DESC", "1");
		}
		if ($rs) {
			$row = $this->db->sql_fetch_row($rs);
			if (!$row) {
				$rs = $this->db->exec_SELECTquery("Price", "ShopPrices", "ProductAsimOID = '" . $asimOid . "' AND Market " . $markt . " AND User IS NULL AND StartQty <= " . $forQty, '', "StartQty DESC", "1");
				if ($rs) {
					$row = $this->db->sql_fetch_row($rs);
				}
			}
			$this->db->sql_free_result($rs);
			$price = $row[0];
		}
		return $price * $qty;
	}

	function getMinQuantityForPrice($asimOid, $forQty = 1, $variant = null) {
		$minQty = $this->custom->getMinQuantityForPrice($asimOid, $forQty, $variant);
		if (is_null($minQty) || intval($minQty) == 0) {
			return 1;
		}
		return $minQty;
	}

	/**
	 * Herausfinden des nicht Reduziertem Preises welcher angezeigt wird wenn der aktuelle Preis geringer ist.
	 * @param type $asimOid
	 * @param type $qty
	 * @return notReducedPrice 
	 */
	function getNotReducedPrice($asimOid, $qty = 1) {

		$markt = $this->market;
		$userperm = $this->getUserRights();

		// Custom
		$price = $this->custom->getNotReducedPrice($asimOid, $qty, $markt, $userperm);
		if ($price !== null) {
			return $price;
		}

		// Simple price
		$pid = $this->getProdIdForAsimOid($asimOid);
		$fid = $this->dbutils->getFeatureIdByName($this->conf['product_not_reduced_price_feature_name']);
		if ($fid != 0) {
			$price = $this->dbutils->getProductValue($pid, $fid, "NUMBER");
			$price = doubleval($price);
			if ($price !== null && $price !== '') {
				return $price;
			}
		}

		$usersql = 'User IS NULL';
		if ($userperm) {
			foreach ($userperm as $perm) {
				$usersql .= " OR User = '" . $perm . "'";
			}
		}
		if ($markt) {
			$markt = "= '" . $markt . "'";
		} else {
			$markt = 'IS NULL';
		}

		$rs = $this->db->exec_SELECTquery("Price", "ShopPrices", "ProductAsimOID = '" . $asimOid . "' AND Market " . $markt . " AND (" . $usersql . ") AND StartQty <= " . $qty, '', "Price DESC", "1");
		if ($rs) {
			$row = $this->db->sql_fetch_row($rs);
			$this->db->sql_free_result($rs);
			$price = $row[0];
		}

		return $price;
	}

	/**
	 * Verfügbarkeit von Produkten
	 * @param type $asimOid
	 * @return  availability
	 */
	function getAvailability($asimOid) {
		$availability = $this->custom->getAvailability($asimOid);
		if (!$availability) {
			if ($this->market) {
				$markt = "= '" . $this->market . "'";
			} else {
				$markt = 'IS NULL';
			}

			$rs = $this->db->exec_SELECTquery("Availability", "ShopAvailability", "ProductAsimOID = '" . $asimOid . "' AND Market " . $markt . " LIMIT 1");
			$row = $this->db->sql_fetch_row($rs);
			$this->db->sql_free_result($rs);
			if (!$row && $this->market != null) {
				$rs = $this->db->exec_SELECTquery("Availability", "ShopAvailability", "ProductAsimOID = '" . $asimOid . "' AND Market IS NULL LIMIT 1");
				$row = $this->db->sql_fetch_row($rs);
				$this->db->sql_free_result($rs);
			}

			$availability = $row[0];
		}
		return $availability;
	}

	/**
	 * Warenkorb manuel ändern
	 * @param array $basket
	 * @param array $changes
	 */
	function adjustBasket(&$basket, &$changes) {
		$this->custom->adjustBasket($basket, $changes);
	}

	/**
	 * Versandkosten berechnen
	 * @param array basket
	 * @return array basket
	 */
	function adjustShipping($basket) {
		$userperm = $this->getUserRights();
		return $this->custom->adjustShipping($basket, $userperm);
	}

	/**
	 * Benutzerrechte aus fe_user auslesen
	 * @return Benutzerrechte 
	 */
	function getUserRights() {
		if ($GLOBALS['TSFE']->loginUser) {
			$rights = $GLOBALS['TSFE']->fe_user->user['ms3commerce_user_rights'];
			$urights = explode(";", $rights);
			return $urights;
		}
		return false;
	}

	/**
	 * 	Gibt die AsimOid mithilfe der uid aus der tt_products Tabelle zurück
	 * @param type $uid
	 * @return type 
	 */
	function getAsimOidForTTUid($uid) {
		$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
		$rs = $t3db->exec_SELECTquery("AsimOid", "tt_products", "uid = " . $uid);
		if ($rs) {
			$row = $t3db->sql_fetch_row($rs);
			$t3db->sql_free_result($rs);
			return $row[0];
		}
		return null;
	}

	function getProdIdForTTuid($uid) {

		$oid = $this->getAsimOidForTTUid($uid);
		return $this->getProdIdForAsimOid($oid);
	}

	function getAsimOidForProdId($pid) {
		$rs = $this->db->exec_SELECTquery("AsimOid", "Product", "Id = " . $pid);
		if ($rs) {
			$row = $this->db->sql_fetch_row($rs);
			$this->db->sql_free_result($rs);
			return $row[0];
		}
		return null;
	}

	/**
	 * Herausfinden der Uid aus der tt_products Tabelle
	 * @param id $productId
	 * @return AsimOid oder false wenn nicht vorhanden 
	 */
	function getTTUidForAsimOid($asimOid) {
		$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
		$rs = $t3db->exec_SELECTquery('uid', "tt_products", "`AsimOid` = '$asimOid'");
		if ($rs) {
			$row = $t3db->sql_fetch_row($rs);
			$t3db->sql_free_result($rs);
			return $row[0];
		}
		return null;
	}

	/**
	 * Herausfinden der Uid aus der tt_products Tabelle
	 * @param id $productId
	 * @return tt_products uid for product id
	 */
	function getTTUidForProdId($prodId) {
		$asimOid = $this->getAsimOidForProdId($prodId);
		return $this->getTTUidForAsimOid($asimOid);
	}

	function getVariantRowFromArticle($artUid) {
		$variant = TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('&tx_ttproducts_variant');
		if (!$variant->useArticles) {
			return null;
		}

		$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
		$varLine = null;

		// Get article
		$rs = $t3db->exec_SELECTquery('*', 'tt_products_articles', 'uid = ' . intval($artUid));
		if (!$rs) {
			return null;
		}
		$rowArt = $t3db->sql_fetch_assoc($rs);
		$t3db->sql_free_result($rs);
		if (!$rowArt) {
			return null;
		}

		// Get Product
		$rs = $t3db->exec_SELECTquery('*', 'tt_products', 'uid = ' . intval($rowArt['uid_product']));
		if (!$rs) {
			return null;
		}
		$rowProd = $t3db->sql_fetch_assoc($rs);
		$t3db->sql_free_result($rs);
		if (!$rowProd) {
			return null;
		}

		$fields = $variant->getSelectableFieldArray();
		$varPos = array();
		foreach ($fields as $k => $f) {
			$vals = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(';', $rowProd[$f], true);
			$idx = array_search($rowArt[$f], $vals);
			if ($idx === false) {
				$varPos[] = '';
			} else {
				$varPos[] = $idx;
			}
		}

		$varLine = implode(';', $varPos);
		return $varLine;
	}

	function getProdIdForAsimOid($asimOid) {
		return $this->dbutils->getProductIdByOid($asimOid);
		/*
		  $rs = $this->db->exec_SELECTquery("Id", "Product", "asimOid = '$asimOid'");
		  if ( $rs ) {
		  $row = $this->db->sql_fetch_row( $rs );
		  $this->db->sql_free_result( $rs );
		  return $row[0];
		  }
		  return null;
		 */
	}

	function addAdressInfoToOrder($orderId, $address = null) {
		if ($address) {
			$bil = $address['billing'];
			$del = $address['delivery'];
		} else {
			$recs = tx_ms3commerce_plugin_sessionUtils::loadSession("::recs");
			if (!$recs) {
				return;
			}
			$bil = $recs['personinfo'];
			$del = $recs['delivery'];
		}

		if (MS3C_SHOP_USE_ORDER_BILLING_ADDRESS) {
			$map = array(
				'bill_name' => $bil['name'],
				'bill_first_name' => $bil['first_name'],
				'bill_last_name' => $bil['last_name'],
				'bill_title' => $bil['title'],
				'bill_gender' => $bil['gender'],
				'bill_company' => $bil['company'],
				'bill_address' => $bil['address'],
				'bill_zip' => $bil['zip'],
				'bill_city' => $bil['city'],
				'bill_country' => $bil['country'],
				'bill_static_info_country' => $bil['country_code'],
				'bill_telephone' => $bil['telephone'],
				'bill_fax' => $bil['fax'],
				'bill_email' => $bil['email'],
				'bill_www' => $bil['www'],
			);
		}

		$this->custom->addAddressInfoToOrder($orderId, $map, $bil, $del);

		if (!empty($map)) {
			$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
			$sql = "UPDATE sys_products_orders SET ";
			foreach ($map as $f => $d) {
				$sql .= $f . '=' . $t3db->sql_escape($d) . ',';
			}
			$sql = substr($sql, 0, strlen($sql) - 1);
			$sql .= " WHERE uid = $orderId";
			$t3db->sql_query($sql);
		}
	}

	function getOrderById($orderId) {
		$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
		$rsOrder = $t3db->exec_SELECTquery("*", "sys_products_orders", "uid=" . $orderId);

		$rowOrder = $t3db->sql_fetch_assoc($rsOrder);
		$t3db->sql_free_result($rsOrder);
		if (!$rowOrder) {
			return null;
		}

		$orderObj = new stdClass();

		//general info from sys_products_orders
		$generalInfo['orderId'] = $orderId;
		$generalInfo['trackingId'] = $rowOrder['tracking_code'];
		$generalInfo['feusers_uid'] = $rowOrder['feusers_uid'];
		$generalInfo['status'] = $rowOrder['status'];
		$generalInfo['tstamp'] = $rowOrder['tstamp'];
		$generalInfo['orderDate'] = date('Y-m-d', $rowOrder['crdate']);
		$generalInfo['userid'] = $rowOrder['feusers_uid'];
		$generalInfo['note'] = $rowOrder['note'];
		$generalInfo['name'] = $rowOrder['mS3C_basketname'];
		$generalInfo['type'] = $rowOrder['mS3C_order_type'];

		//$generalInfo['amount']=$row['amount'];
		//Delivery address from sys_products_orders					
		$delivery['name'] = $rowOrder['name'];
		$delivery['first_name'] = $rowOrder['first_name'];
		$delivery['last_name'] = $rowOrder['last_name'];
		$delivery['salutation'] = $rowOrder['salutation'];
		$delivery['title'] = $rowOrder['title'];
		$delivery['gender'] = $rowOrder['gender'];
		$delivery['address'] = $rowOrder['address'];
		$delivery['zip'] = $rowOrder['zip'];
		$delivery['city'] = $rowOrder['city'];
		$delivery['country'] = $rowOrder['country'];
		$delivery['countrycode'] = $rowOrder['static_info_country'];
		$delivery['company'] = $rowOrder['company'];
		$delivery['telephone'] = $rowOrder['telephone'];
		$delivery['email'] = $rowOrder['email'];
		$delivery['fax'] = $rowOrder['fax'];

		//items from sys_products_orders_mm_tt_products
		$items = array();
		$resItems = $t3db->exec_SELECTquery("sys_products_orders_qty as quantity, tt_products_uid , tt_products_articles_uid", " sys_products_orders_mm_tt_products", "sys_products_orders_uid=" . $orderId);
		if ($resItems) {
			while ($row = $t3db->sql_fetch_row($resItems)) {
				$items[] = array('quantity' => $row[0], 'asimOid' => $this->getAsimOidForTTUid($row[1]), 'tt_article_uid' => $row[2]);
			}
			$orderObj->items = $items;
		}
		$t3db->sql_free_result($resItems);


		if (!$generalInfo['feusers_uid']) {
			$rowBill = false;
		} else {
			$resBill = $t3db->exec_SELECTquery("*", "fe_users", "uid=" . $generalInfo['feusers_uid']);
			$rowBill = $t3db->sql_fetch_assoc($resBill);
			$t3db->sql_free_result($resBill);
		}
		if ($rowBill) {
			$generalInfo['username'] = $rowBill['username'];
			//taxId   
			$generalInfo['ustid'] = $rowBill['tt_products_vat'];
		}

		if (MS3C_SHOP_USE_ORDER_BILLING_ADDRESS) {
			$billing['name'] = $rowOrder['bill_name'];
			$billing['first_name'] = $rowOrder['bill_first_name'];
			$billing['last_name'] = $rowOrder['bill_last_name'];
			$billing['title'] = $rowOrder['bill_title'];
			$billing['gender'] = $rowOrder['bill_gender'];
			$billing['company'] = $rowOrder['bill_company'];
			$billing['address'] = $rowOrder['bill_address'];
			$billing['zip'] = $rowOrder['bill_zip'];
			$billing['city'] = $rowOrder['bill_city'];
			$billing['country'] = $rowOrder['bill_country'];
			$billing['countrycode'] = $rowOrder['bill_static_info_country'];
			$billing['telephone'] = $rowOrder['bill_telephone'];
			$billing['fax'] = $rowOrder['bill_fax'];
			$billing['email'] = $rowOrder['bill_email'];
			$billing['www'] = $rowOrder['bill_www'];
		} else {
			// billing address from fe_users
			if ($rowBill) {
				$billing['name'] = $rowBill['name'];
				$billing['first_name'] = $rowBill['first_name'];
				$billing['middle_name'] = $rowBill['middle_name'];
				$billing['last_name'] = $rowBill['last_name'];
				$billing['title'] = $rowBill['title'];
				$billing['gender'] = $rowBill['gender'];
				$billing['company'] = $rowBill['company'];
				$billing['address'] = $rowBill['address'];
				$billing['zip'] = $rowBill['zip'];
				$billing['city'] = $rowBill['city'];
				$billing['country'] = $rowBill['country'];
				$billing['countrycode'] = $rowBill['static_info_country'];
				$billing['telephone'] = $rowBill['telephone'];
				$billing['fax'] = $rowBill['fax'];
				$billing['email'] = $rowBill['email'];
				$billing['www'] = $rowBill['www'];
			}
		}

		$orderObj->generalInfo = $generalInfo;
		$orderObj->delivery = $delivery;
		$orderObj->billing = $billing;
		return $orderObj;
	}

	public function getOrderIdFromParam() {
		//$orderId = TYPO3\CMS\Core\Utility\GeneralUtility::_GP("orderid");
		//if ($orderId == '') {
		// Might be a tt_products Tracking detail list
		$tracking = TYPO3\CMS\Core\Utility\GeneralUtility::_GP("tracking");
		$t3db = tx_ms3commerce_db_factory_cms::getT3Database();
		if ($tracking) {
			$rs = $t3db->exec_SELECTquery("uid", "sys_products_orders", "tracking_code = " . $this->db->sql_escape($tracking));
			$row = $t3db->sql_fetch_row($rs);
			$t3db->sql_free_result($rs);
			$orderId = $row[0];
		}
		//}

		return $orderId;
	}

}

class tx_tt_products_hooks {

	/** @var tx_ms3commerce_db */
	var $db;

	/** @var tx_ms3commerce_shop_calc */
	var $calc;

	/** @var tx_ms3commerce_linker */
	var $linker;
	var $conf;
	var $ttconf;

	/**
	 * Hooks Konstruktor 
	 */
	function __construct() {
		$this->db = tx_ms3commerce_db_factory::buildDatabase(true);
		//tt_products Conf auslesen
		$cnf = &TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('&tx_ttproducts_config');
		$this->ttconf = $cnf->conf;
		$this->conf = &$cnf->conf['mS3Commerce.'];
		$this->calc = new tx_ms3commerce_shop_calc($this->db, $this->conf['shop_market'], $this->conf, $this->conf['market_id'], $this->conf['language_id'], true, $this->getLinker());
	}

	function init() {
		$this->calc->init();
	}

	private function getLinker() {
		if ($this->linker == null) {
			global $TSFE;
			$cObj = $TSFE->cObj;
			// Must provide the custom object
			$custom = tx_ms3commerce_pi1::makeObjectInstance('tx_ms3commerce_custom');
			$custom->setup($this->db, null, null, $this->conf, null);
			$this->linker = new tx_ms3commerce_linker($this->db, $this->conf, $cObj, null, $this->calc->dbutils, $custom);
		}
		return $this->linker;
	}

	/**
	 * tt_products hook um Preise zu definieren
	 * @param array $row
	 * @param type $fetchMode
	 * @param type $funcTablename
	 * @param type $item 
	 */
	function changeBasketItem(&$row, $fetchMode, $funcTablename, &$item) {
		$this->dbgstart();
		//Preis herausfinden
		$asimOid = $this->calc->getAsimOidForTTUid($row['uid']);
		if ($asimOid) {
			// get variant
			if ($fetchMode == 'useExt') {
				$varLine = current($row['ext']['tt_products']);
				$var = $varLine['vars'];
			} else {
				// What to do?
				// Comes only in tt_products LIST and SINGLE view. Not used with mS3 Commerce
				$var = null;
			}
			$price = $this->calc->getPrice($asimOid, $item['count'], 1, $var);

			//Preisaddition
			$row['tax'] = $this->ttconf['TAXpercentage'];
			$price_brutto = $price * ($row['tax'] / 100 + 1);
			$item['price0Tax'] = $item['priceTax'] = $price_brutto;
			$item['price0NoTax'] = $item['priceNoTax'] = $price;
			$row['price'] = $price_brutto;
			$item['rec'] = $row;
		}

		$this->calc->custom->adjustBasketItem($item);
		$this->dbgend();
	}

	/**
	 * @abstract  tt_products Hook um den Warenkorb zu ändern, 
	 * @param type $parent
	 * @param type $basketExtRaw
	 * @param type $extVars
	 * @param type $paramProduct
	 * @param type $uid
	 * @param type $sameGiftData
	 * @param type $identGiftnumber 
	 */
	function changeBasket($parent, &$basketExtRaw, $extVars, $paramProduct, $uid, $sameGiftData, $identGiftnumber) {
		$this->dbgstart();
		//Notizes aus GET oder POST in session einfügen
		//Muss sich in diesem Hook befinden da dieser als erstes aufgerufen wird
		$notes = tx_ms3commerce_plugin_sessionUtils::loadSession("shopNotes");

		if (isset($_REQUEST['ttp_note'])) {
			$deb = $_REQUEST['ttp_note'];
			if ($notes) {
				$notes = $deb + $notes;
			} else {
				$notes = $deb;
			}
			//$notes = null;
		}

		// Delete notes of products that are removed from basket
		if (is_array($notes) && !empty($basketExtRaw)) {
			foreach ($basketExtRaw as $ttUid => $row) {
				if (array_key_exists('quantity', $row) && $row['quantity'] == 0) {
					// Deleted
					unset($notes[$ttUid]);
				}
			}
		}

		tx_ms3commerce_plugin_sessionUtils::storeSession("shopNotes", $notes);

		$this->calc->adjustBasket($parent->basketExt, $basketExtRaw);
		$this->dbgend();
	}

	/**
	 * tt_products hook welches ausgelößt wird wenn ein Warenkorb abgeschieckt wird
	 * @param type $parent
	 * @param type $address
	 * @param type $templateCode
	 * @param type $basketView
	 * @param type $funcTablename
	 * @param type $orderUid
	 * @param type $orderConfirmationHTML
	 * @param type $error_message
	 * @return boolean 
	 */
	function finalizeOrder($parent, $address, $templateCode, $basketView, $funcTablename, $orderUid, $orderConfirmationHTML, $error_message) {
		$this->dbgstart();
		$this->calc->addAdressInfoToOrder($orderUid, $address->infoArray);
		$orderObj = $this->calc->getOrderById($orderUid);
		if (defined('MS3C_ENABLE_OCI') && MS3C_ENABLE_OCI == true) {
			if (tx_ms3commerce_OCI::isOCISession()) {
				$oci = new tx_ms3commerce_OCI();
				$oci->finalizeOCI($orderObj, $this->calc);
				$this->dbgend();
				return;
			}
		}

		$this->calc->custom->finalizeOrder($orderObj);

		$this->dbgend();
		return true;
	}

	/**
	 * @abstract tt_product Hook um Marker hinzuzufügen tt_products für Typo3 <= 6
	 * @param type $parent
	 * @param type $markerArray
	 * @param type $cObjectMarkerArray
	 * @param type $item
	 * @param type $catTitle
	 * @param type $imageNum
	 * @param type $imageRenderObj
	 * @param type $forminfoArray
	 * @param type $theCode
	 * @param type $id
	 * @param type $linkWrap 
	 */
	function getItemMarkerArray($parent, &$markerArray, $cObjectMarkerArray, $item, $catTitle, $imageNum, $imageRenderObj, $forminfoArray, $theCode, $id, $linkWrap) {
		$this->getItemMarker($markerArray, $item['rec']);
	}

	/**
	 * @abstract tt_product Hook um Marker hinzuzufügen für Typo3 7
	 * @param type $parent
	 * @param type $markerArray
	 * @param type $cObjectMarkerArray
	 * @param type $item
	 * @param type $catTitle
	 * @param type $imageNum
	 * @param type $imageRenderObj
	 * @param type $forminfoArray
	 * @param type $theCode
	 * @param type $id
	 * @param type $linkWrap 
	 */
	function getRowMarkerArray($pObj, &$markerArray, $cObjectMarkerArray, $row, $imageNum, $imageRenderObj, $forminfoArray, $theCode, $id, $linkWrap) {
		$this->getItemMarker($markerArray, $row);
	}

	/**
	 * Method for getItemMarkerArray and getRowMarkerArray
	 * @param type $markerArray
	 * @param type $row
	 */
	private function getItemMarker(&$markerArray, $row) {
		$this->dbgstart();
		$priceViewObj = &TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('&tx_ttproducts_field_price_view');
		$uid = $markerArray['###PRODUCT_UID###'];
		$asimOid = $this->calc->getAsimOidForTTUid($uid);
		$pid = $this->calc->getProdIdForAsimOid($asimOid);

		$notes = tx_ms3commerce_plugin_sessionUtils::loadSession("shopNotes");
		$markerArray['###PRODUCT_NOTE###'] = $notes[$uid];
		$markerArray['###PRODUCT_NOTE_NAME###'] = 'ttp_note[' . $uid . ']';
		$markerArray['###PRODUCT_TITLE###'] = $this->getProductValue($asimOid, $this->conf['product_title_feature_name']);
		$markerArray['###PRODUCT_TITLE_RAW###'] = $this->getProductValue($asimOid, $this->conf['product_title_feature_name'], true);
		$markerArray['###PRODUCT_DESCRIPTION###'] = $this->getProductValue($asimOid, $this->conf['product_description_feature_name']);
		$markerArray['###PRODUCT_DESCRIPTION_RAW###'] = $this->getProductValue($asimOid, $this->conf['product_description_feature_name'], true);
		$markerArray['###PRODUCT_NAME###'] = $this->getProductName($asimOid);
		$markerArray['###PRODUCT_LINK###'] = $this->getLinker()->getProductLink($pid);
		$markerArray['###SHOP_AVAILABILITY###'] = $this->calc->getAvailability($asimOid);

		$oldprice = $this->calc->getNotReducedPrice($asimOid);
		$span = '';
		if ($this->calc->getPrice($asimOid) != $oldprice) {
			$oldprice = $priceViewObj->printPrice($priceViewObj->priceFormat($oldprice));
			$span = $oldprice;
		}
		$markerArray['###PRICE_TAX_NOT_REDUCED###'] = $span;

		$add = $this->fillAdditionalBasketSMs($asimOid);
		$markerArray = array_merge($markerArray, $add);

		$cust = $this->calc->custom->getItemMarker($pid, $asimOid, $uid, array('rec' => $row));
		if (is_array($cust)) {
			foreach ($cust as $k => $v) {
				$markerArray[$k] = $v;
			}
		}
		$this->dbgend();
	}

	private static $s_globalMarker_cache = null;

	function addGlobalMarkers(&$markerArray) {
		if (is_null(self::$s_globalMarker_cache)) {
			$marker = array();
			// Titles of additional Basket markers
			$sms = $this->getAdditionalBasketSMs();

			foreach ($sms as $smName) {
				$title = "";
				$fid = $this->calc->dbutils->getFeatureIdByName($smName);
				#echo "$smName;$fid";
				$rc = $this->calc->dbutils->getFeatureValueRecord($fid);
				if ($rc != null) {
					$title = $rc->Title;
				}
				$marker["###SM_{$smName}_TITLE###"] = $title;
			}

			if (defined('MS3C_ENABLE_OCI') && MS3C_ENABLE_OCI) {
				// Get the tt_products main object (to get its cObj)
				$ttProdTemplate = TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj("&tx_ttproducts_template");
				$ttProdMain = TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj("&tx_ttproducts_main");
				$tmpl = $ttProdTemplate->get('BASKET', null, $ttProdMain->cObj, $templateFile, $errorMessage);
				$tplUtils = new tx_ms3commerce_TplUtils(null);
				if (tx_ms3commerce_OCI::isOCISession()) {
					$part = $tplUtils->getSubpart($tmpl, '###MS3C_SHOP_BASKET_OCI###');
				} else {
					$part = $tplUtils->getSubpart($tmpl, '###MS3C_SHOP_BASKET_NO_OCI###');
				}
				$marker['###MS3C_SHOP_BASKET_OCI_ADDITIONS###'] = $part;
			}
			
			$this->calc->custom->addGlobalMarkers($marker, $markerArray);
			self::$s_globalMarker_cache = $marker;
		}
		tx_ms3commerce_plugin_sessionUtils::mergeRecursiveWithOverrule($markerArray, self::$s_globalMarker_cache);
	}

	/**
	 * Versandkosten
	 * @param array $basket
	 * @return basket 
	 */
	function getShipping($basket) {
		$this->dbgstart();
		$ret = $this->calc->adjustShipping($basket);
		$this->dbgend();
		return $ret;
	}

	/**
	 * 	Mit dieser Funktion wird er Inhalt des Sachmerkmals angezeigt.
	 * @param type $asimOid product asimOID
	 * @param type $featureName SM Name
	 * @return Value oder false 
	 */
	function getProductValue($asimOid, $featureName, $raw = false) {
		$this->dbgstart();
		$FeatureId = $this->calc->dbutils->getFeatureIdByName($featureName);
		$pid = $this->calc->getProdIdForAsimOid($asimOid);
		$ret = $this->calc->dbutils->getProductValue($pid, $FeatureId, $raw);
		$this->dbgend();
		return $ret;
	}

	/**
	 * Produktname aus der Asim Product Tabelle
	 * @param type $asimOid tt_products product uid
	 * @return value oder false
	 */
	function getProductName($asimOid) {
		$this->dbgstart();
		$sql = "SELECT Name FROM Product WHERE AsimOid = '$asimOid'";
		$result = $this->db->sql_query($sql);
		if ($result) {
			$row = $this->db->sql_fetch_row($result);
			$this->dbgend();
			return $row[0];
		}
		$this->dbgend();
		return false;
	}

	/**
	 *
	 * @return array 
	 */
	function getAdditionalBasketSMs() {
		if (array_key_exists('basketSMList', $this->conf)) {
			$sms = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(";", $this->conf['basketSMList']);
		} else if (array_key_exists('basketSMZ', $this->conf)) {
			// TODO
			$sms = array();
		} else {
			$sms = array();
		}

		return $sms;
	}

	function fillAdditionalBasketSMs($asimOid) {
		$sms = $this->getAdditionalBasketSMs();
		$ret = array();
		foreach ($sms as $smName) {
			$ret["###SM_{$smName}_VALUE###"] = $this->getProductValue($asimOid, $smName);
			$ret["###SM_{$smName}_RAWVALUE###"] = $this->getProductValue($asimOid, $smName, true);
		}
		return $ret;
	}

	/**
	 *
	 * @var type time
	 */
	var $dbg_start = null;

	private function dbgstart() {
		if (array_key_exists('ms3debug', $_GET) && $_GET['ms3debug']) {
			if ($this->dbg_start === null) {
				$this->db_start = microtime(true);
			}
		}
	}

	private function dbgend() {
		if (array_key_exists('ms3debug', $_GET) && $_GET['ms3debug']) {
			if ($this->dbg_start !== null) {
				$dbg_end = microtime(true);
				$el = $dbg_end - $this->dbg_start;
				echo "<span style='display:none;'>mS3Commerce TT Hook Execution Time: $el</span>";
				$this->dbg_start = null;
			}
		}
	}

}

class tx_tt_products_hooks_proxy {

	function __construct() {
		$hook = &TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj('EXT:ms3commerce/pi1/class.tx_ms3commerce_tt_products.php:&tx_tt_products_hooks');
		$hook->init();
	}

}

// For suppressing tt_products automatic mails.
// 2 Situations:
// 1. normal mail ==> tt_products hook (sendMail)
// 2. Swift Mail ==> Swift plugin (below)
class user_tx_ms3commerce_tt_products_mail_suppressor {

	var $db;
	var $custom;

	public function __construct() {
		$this->db = tx_ms3commerce_db_factory::buildDatabase(true);
		$this->custom = tx_ms3commerce_pi1::makeObjectInstance('tx_ms3commerce_custom_shop');
		$this->custom->setup($this->db, null, null, null);
	}

	public function sendMail(&$Typo3_htmlmail, &$toEMail, $subject, $message, $html, $fromEMail, $fromName, $attachment) {
		$cc = null;
		$bcc = null;
		if ($this->suppressMail($toEMail)) {
			return false;
		}
		$toEMail = implode(';', array($toEMail, $cc, $bcc));
		$Typo3_htmlmail->setRecipient(explode(';', $toEMail));
		return true;
	}

	protected function suppressMail(&$toEMail = null, &$ccEMail = null, &$bccEMail = null) {
		$to = $toEMail;
		$cc = $ccEMail;
		$bcc = $bccEMail;
		if ($this->custom->suppressFinalizeMail($toEMail, $ccEMail, $bccEMail)) {
			return true;
		} else {
			if (defined('MS3C_OVERWRITE_BASKET_MAIL_RECV')) {
				// Allow customizer to change addresses...
				if ($to == $toEMail && $cc == $ccEMail && $bcc == $bccEMail) {
					// Customizer didn't change mail addresses, so overwrite
					$bccEMail = $ccEMail = "";
					$toEMail = MS3C_OVERWRITE_BASKET_MAIL_RECV;
				}
			}
			return false;
		}
	}

}

@include_once(PATH_typo3 . 'contrib/swiftmailer/swift_required.php');

if (interface_exists('Swift_Events_SendListener')) {

	class ux_t3lib_mail_Mailer extends TYPO3\CMS\Core\Mail\Mailer {

		public function __construct(Swift_Transport $transport = NULL) {
			parent::__construct($transport);
			$this->registerPlugin(new tx_ms3commerce_tt_products_mail_suppressor_plugin);
		}

	}

	/*
	  // Alias for Typo3 6
	  class mS3C_typo3_ttproducts_mailer extends ux_t3lib_mail_Mailer {
	  public function __construct(Swift_Transport $transport = NULL)
	  {
	  parent::__construct($transport);
	  }
	  }
	 */

	class tx_ms3commerce_tt_products_mail_suppressor_plugin extends user_tx_ms3commerce_tt_products_mail_suppressor implements Swift_Events_SendListener {

		private function isFinalizeMail() {
			$fin = TYPO3\CMS\Core\Utility\GeneralUtility::_GP("products_finalize");
			if (isset($fin) && $fin != null) {
				return true;
			}
		}

		public function beforeSendPerformed(Swift_Events_SendEvent $evt) {
			if ($this->isFinalizeMail()) {
				$to = $evt->getMessage()->getTo();
				$cc = $evt->getMessage()->getCc();
				$bcc = $evt->getMessage()->getBcc();

				if ($this->suppressMail($to, $cc, $bcc)) {
					$evt->cancelBubble();
				} else {
					$evt->getMessage()->setTo($to);
					if ($cc) {
						$evt->getMessage()->setCc($cc);
					}
					if ($bcc) {
						$evt->getMessage()->setBcc($bcc);
					}
				}
			}
		}

		public function sendPerformed(Swift_Events_SendEvent $evt) {
			
		}

	}

}
?>
