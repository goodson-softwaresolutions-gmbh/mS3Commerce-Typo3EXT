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

if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('ms3commerce').'/load_dataTransfer_config.php');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY, 'pi1/class.tx_ms3commerce_pi1.php', '_pi1', 'list_type', 0);
// Register for AJAX calls
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['ms3commerce'] = "EXT:$_EXTKEY/pi1/tx_ajaxsearchresults.php";

if ( defined('MS3C_SHOP_SYSTEM') && MS3C_SHOP_SYSTEM == 'tt_products' ) {
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['changeBasket'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_tt_products.php:&tx_tt_products_hooks";
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['changeBasketItem'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_tt_products.php:&tx_tt_products_hooks";
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['finalizeOrder'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_tt_products.php:&tx_tt_products_hooks";
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['PRODUCT'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_tt_products.php:&tx_tt_products_hooks";
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['addGlobalMarkers'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_tt_products.php:&tx_tt_products_hooks";
	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['addGlobalMarkers'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_tt_products.php:tx_tt_products_hooks_proxy";

	$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tt_products']['sendMail'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_tt_products.php:&user_tx_ms3commerce_tt_products_mail_suppressor";
		
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['t3lib_mail_mailer'] = array(
		'className' => 'tx_ms3commerce_tt_products_mailer'
	);
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\CMS\Core\Mail\Mailer'] = array(
		'className' => 'tx_ms3commerce_tt_products_mailer'
	);
	
}

if ( defined('MS3C_ENABLE_OCI') && MS3C_ENABLE_OCI ) {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['logoff_post_processing'][] = "EXT:$_EXTKEY/pi1/class.tx_ms3commerce_OCI.php:&tx_ms3commerce_OCI->userLogout";
}

// This adds a tasks in Scheduler extension of Typo3:
/*
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_ms3commerce_sampletask'] = array(
    'extension'        => $_EXTKEY,
    'title'            => 'mS3 Commerce Sample Task',
    'description'      => 'mS3 Commerce Sample Task'
);
*/

if (TYPO3_MODE == 'BE') {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys'][$_EXTKEY] = array('EXT:'.$_EXTKEY.'/pi1/class.tx_ms3commerce_cli.php','_CLI_scheduler');
}

require(PATH_site.'dataTransfer/runtime_config.php');

?>
