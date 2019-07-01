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

error_reporting(E_ALL & ~(E_STRICT | E_NOTICE | E_DEPRECATED));

require_once __DIR__.'/../load_dataTransfer_config.php';

require_once 'class.tx_ms3commerce_t3minibootstrap.php';
tx_ms3commerce_t3minibootstrap::init('typo3conf/ext/ms3commerce/pi1/');

require_once 'class.tx_ms3commerce_db.php';
require_once 'class.tx_ms3commerce_search.php';
require_once 'class.tx_ms3commerce_DbUtils.php';
require_once 'class.tx_ms3commerce_linker.php';
require_once 'class.tx_ms3commerce_realurl.php';

function doQuickSearchComplete($term, $params)
{
	$db = tx_ms3commerce_db_factory::buildDatabase(false, false);
	$request = tx_ms3commerce_suggest_helper::quickParams2Request($params, $term);
	$limit = $request->SuggestLimitResults;
	$dbUtils = new tx_ms3commerce_DbUtils($db, $request->Market, $request->Language, null);
	$request->Shop = $dbUtils->getShopId($request->Language, $request->Market);
	
	$search = new tx_ms3commerce_search(null, $db, $dbUtils);
	
	$valuesList = $search->getSuggestionFullText_direct($request, $limit);
	if ($request->SuggestSingleItems)
	{
		if (function_exists('mS3CCustomLayoutSuggestItemsSimple')) {
			$valuesList = mS3CCustomLayoutSuggestItemsSimple($db, $search, $request, $valuesList, $params);
		} else {
			$suggestLayout = tx_ms3commerce_suggest_helper::quickParams2SuggestLayout($params);
			
			if ($suggestLayout['linker']['force_realurl']) {
				// Must finalize initializing mini T3 bootstrap for realurl linker to work
				tx_ms3commerce_t3minibootstrap::finalize();
			}
			
			$valuesList = $search->layoutSuggestItemsSimple($valuesList, $suggestLayout);
		}
	}
	
	return $valuesList;
}

if (defined('MS3C_QUICK_COMPLETE'))
{
	$phpStart = microtime(true);
	$data = $_POST;
	
	if (!array_key_exists('hmac', $data)) {
		exit;
	}
	$term = $data['term'];
	unset($data['term']);
	if (!tx_ms3commerce_suggest_helper::verifySuggestHMac($data)) {
		exit;
	}
	
	
	if (is_array($data) && array_key_exists('suggest', $data) && array_key_exists('call', $data) && $data['call'])
	{
		//$start = microtime(true);
		$valuesList = doQuickSearchComplete($term, $data);
		
		/*
		$res = array();
		foreach ($valuesList as $Result) {
			$obj = new stdClass();
			$obj->value = $Result;
			$res[] = $obj;
		}

		$ret = new stdClass();
		$ret->values = $res;
		*/
		$phpEnd = microtime(true);
		$php = intval($phpEnd*1000-$phpStart*1000);
		$valuesList['dbg'] = "PHP: $php / ".$valuesList['dbg'];
		
		$ret = json_encode($valuesList);
		echo $ret;
		exit;
	}
}


?>
