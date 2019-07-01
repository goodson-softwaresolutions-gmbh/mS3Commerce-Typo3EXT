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

define('MS3C_AJAX_SEARCH_PAGETYPE', 159);
define('MS3C_SUGGEST_PAGETYPE', 158);
define('MS3C_DOCUMENT_DOWNLOAD_PAGETYPE', 160);

define('MS3C_MULTIVALUE_SEPARATOR', "\xC2\x9C");

class tx_ms3commerce_constants
{
	/* CONSTANTS FOR GET PARAMETERS */
	const QUERY_PARAM_PID = 'mS3ProductId';
	const QUERY_PARAM_DID = 'mS3DocumentId';
	const QUERY_PARAM_GID = 'mS3GroupId';
	const QUERY_PARAM_MID = 'mS3MenuId';
	const QUERY_PARAM_ITEMSTART = 'mS3ItemStart';
	const QUERY_PARAM_CPID = 'mS3ChildProductId';
	const QUERY_PARAM_CGID = 'mS3ChildGroupId';
	
	const QUERY_PARAM_TX_ARRAY = 'tx_ms3commerce_pi1';
	const QUERY_PARAM_TX_CONTEXTID = 'mapid';
	const QUERY_PARAM_TX_DUMMYID = 'mapid_dummy_';

	const QUERY_PARAM_TLANID = 'L';
	
	const ELEMENT_GROUP = 1;
	const ELEMENT_PRODUCT = 2;
	const ELEMENT_DOCUMENT = 3;
}

// Used for PHP 5.2 compability for "$val ?: $def"
function NVL( $val, $def ) {
	return $val ? $val : $def;
}

?>
