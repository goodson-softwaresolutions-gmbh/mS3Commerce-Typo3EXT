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

define('MULTIVALUE_SEP_CHAR', "\x1F");

$typo_db_username = TYPO3_db_username;
$typo_db_host = TYPO3_db_host;
$typo_db = TYPO3_db;
$typo_db_password = TYPO3_db_password;

$encoded=array();
$encoded["status"]="";
function custom_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
	$constants = get_defined_constants(1);

	$eName = 'Unknown error type';
	foreach ($constants['Core'] as $key => $value) {
		if (substr($key, 0, 2) == 'E_' && $errno == $value) {
			$eName = $key;
			break;
		}
	}

	$msg = $eName . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline;

	throw new Exception($msg);
}

function connectMS3CommerceDb()
{
	global $TYPO3_CONF_VARS;
	global $typo_db_username;
	global $typo_db_host;
	global $typo_db;
	global $typo_db_password;

	tslib_eidtools::connectDB();
	
	$db = tx_ms3commerce_db_factory::buildDatabase(false);
	$db->sql_query( 'SET SQL_BIG_SELECTS=1;' );
	
	return $db;
}

class tx_ms3commerce_ajaxSearch {
	//to be migrated to core (search)
	function preprocessJSON($json) {
		$json->include = array();
		$json->pairedFeatures = array();
		//$json->features = array();
		if (isset($json->selection)) {
			foreach ($json->selection as $sel) {
				$idx = array_search($sel->feature[0], $json->features);
				$isMultiFeature = $json->multiFeature[$idx];
				$sql = '';
				switch ($sel->type) {
					case 'select':
						if (strlen($sel->sel) > 0) {
							if ($isMultiFeature) {
								$sql = "ContentPlain like '%" . MULTIVALUE_SEP_CHAR . "$sel->sel" . MULTIVALUE_SEP_CHAR . "%'";
							} else {
								$sql = "ContentPlain = '$sel->sel'";
							}
						}
						break;
					case 'radio':
						if (strlen($sel->sel) > 0) {
							if ($isMultiFeature) {
								$sql = "ContentPlain like '%" . MULTIVALUE_SEP_CHAR . "$sel->sel" . MULTIVALUE_SEP_CHAR . "%'";
							} else {
								$sql = "ContentPlain = '$sel->sel'";
							}
						}
						break;
					case 'checkbox':
						if (count($sel->sel) > 0) {
							if ($isMultiFeature) {
								foreach ($sel->sel as $value) {
									$sql.= " ContentPlain like '%" . MULTIVALUE_SEP_CHAR . "$value" . MULTIVALUE_SEP_CHAR . "%' or";
								}
								$sql = substr($sql, 0, -2);
								unset($value);
							} else {
								$sql = "ContentPlain IN ('" . implode("','", $sel->sel) . "')";
							}
						} else {
							$sql = "ContentPlain = '' OR ContentPlain IS NULL";
						}
						break;
					case 'slider':
						if ($isMultiFeature) {
							$sql = "ContentPlain like '%" . MULTIVALUE_SEP_CHAR . "$sel->sel" . MULTIVALUE_SEP_CHAR . "%'";
						} else {
							$sql = "ContentNumber = $sel->sel";
						}
						break;
					case 'range':
						if ($isMultiFeature) {
							// TODO: Even Possible?
							// Sel has 2 values (min, max), but no ContentNumber set!
							// Instead \u1F separated values in a String (ContentPlain)!
						} else {
							if (count($sel->sel) >= 2) {
								$vals = array(min($sel->sel), max($sel->sel));
								$sql = "ContentNumber BETWEEN {$vals[0]} AND {$vals[1]}";
							} else if (isset($sel->sel)) {
								if (is_array($sel->sel)) {
									$val = array_values($sel->sel);
									$val = $val[0];
								} else {
									$val = $sel->sel;
								}
								$val = intval($val);
								$sql = "ContentNumber = $val";
							}
							
						}
						break;
					case 'fromToSlider':
						// Cannot work with MultiFeature!
						$sql = "ContentNumber <= " . $sel->sel;
						$sql2 = "ContentNumber >= " . $sel->sel;
						$idx2 = array_search($sel->feature[1], $json->features);
						break;
					case 'fromToRange':
						// Cannot work with MultiFeature!
						sort($sel->sel);
						// MIN must be <= Upper Bound
						$sql = "ContentNumber <= " . $sel->sel[count($sel->sel) - 1];
						// MAX must be >= Lower Bound
						$sql2 = "ContentNumber >= " . $sel->sel[0];
						$idx2 = array_search($sel->feature[1], $json->features);
						break;
					default:
						throw new Exception("Unknown selection type: $sel->type");
				}

				$json->include[$idx] = $sql;
				if (isset($idx2) && isset($sql2)) {
					$json->include[$idx2] = $sql2;
					$json->pairedFeatures[$idx] = $idx2;
					$json->pairedFeatures[$idx2] = $idx;
				}
				unset($idx2);
				unset($sel2);
			}
		}
		return $json;
	}

	static function standalone()
	{
		if (MS3C_TYPO3_RELEASE == '9') {
			require_once(\TYPO3\CMS\Core\Core\Environment::getPublicPath().'/typo3conf/ext/ms3commerce/runtime_config.php');
		} else {
			require_once(PATH_typo3conf.'/ext/ms3commerce/runtime_config.php');
		}
		
		require_once('class.tx_ms3commerce_db.php');

		require_once 'class.tx_ms3commerce_search.php';
		require_once 'class.tx_ms3commerce_pi1.php';
			
		$db = connectMS3CommerceDb();
		$inst = new tx_ms3commerce_ajaxSearch();
		$pi1 = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_ms3commerce_pi1');
		$conf = array(
			'language_id' => $query['language'],
			'market_id' => $query['market'],
			'root_menu_id' => $query['root']
		);
		$pi1->init($conf, $db);
		$pi1->cObj = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tslib_cObj');
		
		echo $inst->main($db, $pi1);
	}

	function main($db,$pi1) {
		header("Content-type: text/plain");
		
		set_error_handler('custom_error_handler', E_ALL ^ E_NOTICE ^ E_DEPRECATED);
		error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
		
		$res = array();
		try {
			$encoded=json_decode($_REQUEST["query"]);

			$encoded=$this->preprocessJSON($encoded);//hier ein pointer zu 

			$db->sql_query( 'SET SQL_BIG_SELECTS=1;' );
			
			$search = new tx_ms3commerce_search($db, $pi1);
			$res = $search->runQuery($encoded);

		} catch(Exception $e) {
			$res["status"]=$e->getMessage();
		}

		return json_encode($res);
	}
}

?>
