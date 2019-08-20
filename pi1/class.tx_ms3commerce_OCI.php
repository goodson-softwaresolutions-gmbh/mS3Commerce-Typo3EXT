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
 
/**
 * This class contains all the mS3 Commerce OCI Implementation
 *
 * @author marcelo.stucky
 */
if ( defined('MS3C_ENABLE_OCI') && MS3C_ENABLE_OCI ) {

	define('MS3C_OCI_PAGETYPE', 130);
	define('MS3C_OCI_MAPFILE', 'ociMapping.cnf');

	require_once('class.itx_ms3commerce_pagetypehandler.php');
	require_once('class.tx_ms3commerce_db.php');
	require_once('class.tx_ms3commerce_plugin_sessionUtils.php');

	class tx_ms3commerce_OCI implements itx_ms3commerce_pagetypehandler {

		var $template;

		/** @var tx_ms3commerce_db */
		var $db;

		public function __construct(tx_ms3commerce_template $template = null) {
			if ($template) {
				$template->plugin->installPageTypeHandler(MS3C_OCI_PAGETYPE, $this);
				$this->db = $template->db;
			} else {
				$this->db = tx_ms3commerce_db_factory::buildDatabase(true);
			}
		}

		public function handlePageTypeCall(tx_ms3commerce_template $template) {
			//login user with passed logindata

			/*
			// DEBUG
			$fd = fopen(MS3C_ROOT.'/dataTransfer/data/OCIAccess.log', 'a');
			$dat = print_r($_POST, true);
			fputs($fd,$dat);
			fputs($fd,'-----------------------');
			fclose($fd);
			unset($fd);
			unset($dat);
			*/
			
			if ($template->plugin->loginUser(TYPO3\CMS\Core\Utility\GeneralUtility::_GP('username'), TYPO3\CMS\Core\Utility\GeneralUtility::_GP('password'), true)) {
			//if ($template->plugin->loginUser(TYPO3\CMS\Core\Utility\GeneralUtility::_GP('username'), TYPO3\CMS\Core\Utility\GeneralUtility::_GP('password'), true)) {

				// Check if user can access OCI
				if (!$this->allowOCI()) {
					$template->plugin->page404Error("OCI access not allowed");
					return;
				}

				// Clear the basket
				tx_ms3commerce_plugin_sessionUtils::storeSession("::basketExt", null);

				switch (TYPO3\CMS\Core\Utility\GeneralUtility::_GP('FUNCTION')) {

					case "DETAIL":

						// must parse through custom...
						$item = $template->shop->custom->getItemArrayForOCIRequest(TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PRODUCTID'), 0);
						if ($item) {
							$ms3Oid = $item['asimOid'];
						} else {
							$ms3Oid = TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PRODUCTID');
						}
						$prodId = $template->dbutils->getProductIdByOid($ms3Oid);
						$template->plugin->logoutUser();
						$template->plugin->pageRedirect($template->plugin->getProductLink($prodId));
						break;

					case "VALIDATE":
						$qty = 1;
						if (TYPO3\CMS\Core\Utility\GeneralUtility::_GP('OCI_VERSION') >= 3.0) {
							if (TYPO3\CMS\Core\Utility\GeneralUtility::_GP('QUANTITY') && TYPO3\CMS\Core\Utility\GeneralUtility::_GP('QUANTITY') > 1) {
								$qty = TYPO3\CMS\Core\Utility\GeneralUtility::_GP('QUANTITY');
							}
						}

						$item = $template->shop->custom->getItemArrayForOCIRequest(TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PRODUCTID'), $qty);
						if (!$item) {
							$item = array('asimOid' => TYPO3\CMS\Core\Utility\GeneralUtility::_GP('PRODUCTID'), 'quantity' => $qty);
						}
						$resultArr[] = $item;
						$ociFields = $this->getOciFields($resultArr, $template->dbutils, $template->shop->calc, $template->shop->custom, $template->conf);
						$OCIForm = $this->generateForm('validate', $ociFields);
						echo $OCIForm;
						tx_ms3commerce_plugin_sessionUtils::suppressOutput();
						$template->plugin->logoutUser();
						break;

					case "BACKGROUND_SEARCH":
						$searchString = TYPO3\CMS\Core\Utility\GeneralUtility::_GP('SEARCHSTRING');
						$featureName = $template->conf['searchFeature'];
						if ($featureName == 'FULLTEXT') {
							$isFullText = true;
						} else {
							$featureId = $template->dbutils->getFeatureIdByName($featureName);
							$isFullText = false;
						}

						//get naked request obj
						$request = $template->formbuilder->getSearchRequestObject(null, $template->rootMenuId);

						//fill the request obj with data
						$template->conf['distinct_result'] = 1;
						$request->Limit = $template->itemsPerPage;

						//convert sel array to object
						$class = new stdClass();
						if ($isFullText) {
							$class->Feature = array();
							$class->Type = 'Fulltext';
						} else {
							$class->Feature = array($featureId);
							$class->Type = 'Contains';
						}
						$class->Value = array($searchString);
						$class->IsMultiFeature = false;

						$selectionclass[] = $class;

						$request->Selection = $selectionclass;
						$request->ResultTypes = array('product');
						$request->includeFeatureValues = false;
						$request->WithHierarchy = false;

						$resultArr = array();

						$result = $template->search->runQuery($request);
						foreach ($result->Product as $arr) {
							$idobj = $template->dbutils->selectProduct_singleRow('asimOid', "Id = " . $arr['Id']);
							$resultArr[] = array('asimOid' => $idobj->asimOid, 'quantity' => 1);
						}

						$ociFields = $this->getOciFields($resultArr, $template->dbutils, $template->shop->calc, $template->shop->custom, $template->conf);
						$OCIForm = $this->generateForm('bgSearch', $ociFields);
						echo $OCIForm;
						tx_ms3commerce_plugin_sessionUtils::suppressOutput();
						$template->plugin->logoutUser();
						break;

					default :
						//default case is Shop, first of all store data in the session
						$ociSessionValues = array('HOOK_URL' => TYPO3\CMS\Core\Utility\GeneralUtility::_GP('HOOK_URL'), 'TARGET' => TYPO3\CMS\Core\Utility\GeneralUtility::_GP('~TARGET'));
						$template->plugin->storeSession('OCI', $ociSessionValues);
						//redirect to shopid and then back through finalizeOCI
						$link = $template->shop->custom->getOCIStartLink();
						if (!$link) {
							$link = $template->plugin->getPageLink($template->conf['shop_pid']);
						}
						$template->plugin->pageRedirectLink($link, true, true);
						break;
				}
			} else {
				$template->plugin->page404Error("Username/password wrong");
			}
			return "";
		}
		
		function userLogout(&$params = null, &$ref = null)
		{
			if (tx_ms3commerce_plugin_sessionUtils::isBELogout($ref)) return;
			tx_ms3commerce_plugin_sessionUtils::storeSession('OCI', null);
		}

		public function allowOCI() {
			if ($GLOBALS['TSFE'] && $GLOBALS['TSFE']->loginUser) {
				$allow = $GLOBALS['TSFE']->fe_user->user['mS3C_oci_allow'];
				if ($allow == 1) {
					return true;
				}
			}
			return false;
		}

		/**
		 * @abstract After ordering generate an OCI response Formular
		 * @param type $order basket object
		 * @param tx_ms3commerce_shop_calc $calc methods 
		 */
		public function finalizeOCI($order, tx_ms3commerce_shop_calc $calc) {

			/** @var tx_ms3commerce_DbUtils */
			$dbUtils = $calc->dbutils;
			$custom = $calc->custom;

			$custom->finalizeOCI($order);
			$ociFormFields = $this->getOciFields($order->items, $calc->dbutils, $calc, $custom, $calc->conf);

			$OCIForm = $this->generateForm('basket', $ociFormFields);
			echo $OCIForm;

			$GLOBALS['TSFE']->fe_user->setKey('user', 'basket', array());
			$GLOBALS['TSFE']->fe_user->storeSessionData();
			
			tx_ms3commerce_plugin_sessionUtils::suppressOutput();
			tx_ms3commerce_plugin_sessionUtils::logoutUser();
		}

		/**
		 * @abstract: generate an array with the OCI fields to be returned
		 * @param $items an items array  
		 * @param $dbUtils has the methods needed to fetch data
		 * @param $calc
		 * @param $conf has the config value for currency 
		 */
		private function getOciFields($items, $dbUtils, $calc, $custom, $conf) {
			// Mache alles
			//read config oci
			$filepath = MS3C_EXT_ROOT . "/dataTransfer/" . MS3C_OCI_MAPFILE;
			$handle = @fopen($filepath, "r");
			if ($handle) {
				while (($buffer = fgets($handle, 4096)) !== false) {
					$confLine[] = trim($buffer, "\r\n\t");
				}
				if (!feof($handle)) {
					echo "Error: unexpected fgets() fail\n";
				}
				fclose($handle);
			}
			
			$ociFormFields = array();
			//$arr is an array with items, so we iterate over it
			foreach ($items as $item) {
				if (array_key_exists('variant', $item)) {
					$varLine = $item['variant'];
				} else if (array_key_exists('tt_article_uid', $item) && !empty($item['tt_article_uid'])) {
					$varLine = $calc->getVariantRowFromArticle($item['tt_article_uid']);
				} else {
					$varLine = null;
				}
				
				//standard fields
				$itemArr['QUANTITY'] = $item['quantity'];
				$itemArr['EXT_PRODUCT_ID'] = $item['asimOid'];
				$itemArr['CURRENCY'] = $conf['currencySymbol']; //aus conf currency has to be set 
				$itemArr['PRICE'] = $calc->getPrice($item['asimOid'], $item['quantity'], 1, $varLine);
				//$itemArr['PRICEUNIT'] = $calc->getMinQuantityForPrice($item['asimOid'], $item['quantity'], $varLine);
				$itemArr['PRICEUNIT'] = 1;
				// configurable fields	
				foreach ($confLine as $str) {
					$parts = preg_split('/[\s]*[=][\s]*/', $str);
					$arr = preg_split('/[\s]*[_][\s]*/', $parts[1], 2);
					switch ($arr[0]) {
						case 'SM':
							//this is a feature
							//get the feature
							$featId = $dbUtils->getFeatureIdByName($arr[1]);
							$prodId = $dbUtils->getProductIdByOid($item['asimOid']);
							$featVal = $dbUtils->getProductValue($prodId, $featId, true);

							$itemArr[$parts[0]] = $featVal;

							break;
						case 'OCI':
							$res = $dbUtils->db->exec_SELECTquery($arr[1], MS3C_OCI_TABLE, "asimOid='" . $item['asimOid'] . "'");
							if ($res) {
								$row = $dbUtils->db->sql_fetch_row($res);
								$itemArr[$parts[0]] = $row[0];
							}
							break;
						case 'CONST':
							$itemArr[$parts[0]] = $arr[1];
							break;
					}
				}
				
				$customOCIFields = $custom->getOCIMapping($item['asimOid'], $item['quantity'], $varLine);
				foreach ($customOCIFields as $key => $value) {
					$itemArr[$key] = $value;
				}

				$ociFormFields[] = $itemArr;
			}
			return$ociFormFields;
		}

		/**
		 * @abstract: generate the form to be submited to the given OCI URL
		 * @param type wich kind of form has to be submited depending on 
		 * the call function$formType wich kind of form has to be submited depending on 
		 * the call function
		 * @param type $ociFormFields: wich inputfields has to be included
		 * @return string 
		 */
		public function generateForm($formType, $ociFormFields) {

			if ($formType == 'basket') {
				//if is a Basket the url is stored in the session
				$ociSession = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_ms3commerce_OCI');
				$hookURL = $ociSession['HOOK_URL'];
				$target = $ociSession['TARGET'];
			} else {
				//otherwise they are in $_POST
				$hookURL = TYPO3\CMS\Core\Utility\GeneralUtility::_GP('HOOK_URL');
				$target = TYPO3\CMS\Core\Utility\GeneralUtility::_GP('returntarget');
			}


			$formContent = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>\n<html>\n
	<head><title>OCI RESPONSE</title><meta http-equiv='Content-Type' content='text/html; charset=UTF-8'/></head>\n";
			if ($formType == 'bgSearch') {
				$formContent .= "<body>\n";
			} else {
				$formContent .= "<body onload='document.forms[0].submit();'>\n";
			}
			$formContent .="<form id='ociForm' action='" . $hookURL . "' method='POST' target='" . $target . "'>\n";

			$num = count($ociFormFields);
			for ($i = 0; $i < $num; $i++) {
				foreach ($ociFormFields[$i] as $key => $value) {
					$value = $this->checkOCIFields($key, $value);
					if ($key == 'LONGTEXT') {
						$index = "_" . ($i + 1) . ":132[]";
						$value = "longtext_".($i + 1).": " . $value;
					} else {
						$index = "[" . ($i + 1) . "]";
					}
					$formContent .='	<input type="hidden" name="NEW_ITEM-' . $key . $index . '" value="' . $value . '"/>' . "\n";
				}
			}
			$formContent .="</form>\n</body>\n";
	//		if ($formType == 'bgSearch') {
	//			//no automatic submision!
	//			$formContent .= "</body>\n";
	//		} else {
	//			$formContent .= "\n<script type='text/javascript'></script>\n</body>";
	//		}
			$formContent .= "</html>";
			return $formContent;
		}

		/**
		 * @abstract Check String length to meet OCI Data length specifications
		 * see: http://help.sap.com/saphelp_crm20c/helpdata/en/0F/F2573901F0FE7CE10000000A114084/frameset.htm
		 * @param type $ocifield field to be checked
		 * @param type $value value of the field
		 * @return type 
		 */
		public function checkOCIFields($ocifield, $value) {
			switch ($ocifield) {
				case DESCRIPTION:
					$value = substr($value, 0, 40);
					break;
				case MATNR:
					$value = substr($value, 0, 18);
					break;
				case MATGROUP:
					$value = substr($value, 0, 10);
					break;
				case QUANTITY:
					$value = substr($value, 0, 15);
					break;
				case UNIT:
					$value = substr($value, 0, 3);
					break;
				case PRICE:
					$value = substr($value, 0, 15);
					break;
				case PRICEUNIT:
					$value = substr($value, 0, 9);
					break;
				case CURRENCY:
					$value = substr($value, 0, 5);
					break;
					$value = substr($value, 0, 40);
					break;
				case LEADTIME:
					$value = substr($value, 0, 5);
					break;
				case VENDOR:
					$value = substr($value, 0, 10);
					break;
				case VENDORMAT:
					$value = substr($value, 0, 22);
					break;
				case MANUFACTCODE:
					$value = substr($value, 0, 10);
					break;
				case MANUFACTMAT:
					$value = substr($value, 0, 40);
					break;
				case CONTRACT:
					$value = substr($value, 0, 10);
					break;
				case CONTRACT_ITEM:
					$value = substr($value, 0, 5);
					break;
				case SERVICE:
					$value = substr($value, 0, 1);
					break;
				case EXT_QUOTE_ID:
					$value = substr($value, 0, 35);
					break;
				case EXT_QUOTE_ITEM:
					$value = substr($value, 0, 10);
					break;
				case EXT_PRODUCT_ID:
					$value = substr($value, 0, 40);
					break;
				case LONGTEXT:

				case ATTACHMENT:
					$value = substr($value, 0, 255);
					break;
				case CUST_FIELD1:
					$value = substr($value, 0, 10);
					break;
				case CUST_FIELD2:
					$value = substr($value, 0, 10);
					break;
				case CUST_FIELD3:
					$value = substr($value, 0, 10);
					break;
				case CUST_FIELD4:
					$value = substr($value, 0, 20);
					break;
				case CUST_FIELD5:
					$value = substr($value, 0, 50);
					break;
			}
			return $value;
		}

		public static function isOCISession() {

			$ociSessionData = tx_ms3commerce_plugin_sessionUtils::loadSession("OCI");
			if ($ociSessionData != '') {
				return $ociSessionData;
			} else {
				return false;
			}
		}

	}
}


?>
