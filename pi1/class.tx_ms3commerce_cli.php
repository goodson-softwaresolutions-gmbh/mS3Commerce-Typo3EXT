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

if (!defined ('TYPO3_cliMode')) 	die ('Access denied: CLI only.');

require_once(__DIR__.'/../load_dataTransfer_config.php');

require_once('class.tx_ms3commerce_scheduler.php');
/**
 * tx_ms3commerce_cli mS3 Commerce COMMAND LINE INTERFACE extension
 * to invoke mS3 Commerce tasks like cron Jobs directly throughout CLI
 */
class tx_ms3commerce_cli extends \TYPO3\CMS\Core\Controller\CommandLineController {
	var $prefixId      = 'tx_ms3commerce_cli';
	var $scriptRelPath = 'pi1/class.tx_ms3commerce_cli.php';
	var $extKey        = 'ms3commerce';

	function __construct() {
		parent::__construct();

		$this->cli_options = array_merge($this->cli_options, array(
		));

		$this->cli_help = array_merge($this->cli_help, array(
			'name' => 'mS3 Commerce CLI',
			'synopsis' => 'mS3 Commerce task invokation',
			'description' => 'Invokes mS3 Commerce reocurring tasks directly via CLI',
			'examples' => 'typo3/cli_dispatch.phpsh ' . $this->extKey . ' TASK',
			'author' => '(c) 2019 Goodson GmbH',
		));

		// read backend conf
		$this->conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
	}
/**
 * Validate the arguments  passed 
 * execute commands in the CLI
 * catches exceptions and echoes success or errors messages back 
 * 
 * @param type $argv (array containing all parameters given in the CLI)
 */
	function cli_main($argv) {
		// disable output buffer
		ob_end_clean();

		// validate input
		$this->cli_validateArgs();

		$err = "";
		$task = (string)$this->cli_args['_DEFAULT'][1];
		// Allow different ext key, e.g. "ms3commercecustom:my_task"
		$taskparts = explode(':', $task);
		if (count($taskparts) > 1) {
			$task = join('_', $taskparts);
		} else {
			// Default ist mS3 Commerce
			$task = 'ms3commerce_'.$taskparts[0];
		}
		$cls = "tx_$task";
		if (class_exists($cls)) {
			$obj = new $cls();
			if (method_exists($obj, "execute")) {
				try {
					if (!$obj->execute()) {
						if (method_exists($obj, "getErrorDescription")) {
							$err = "Task $cls failed: ".$obj->getErrorDescription();
						} else {
							$err = "Task $cls failed: Unknown error";
						}
					}
				} catch (Exception $e) {
					$err = "Task $cls failed: ".$e;
				}
			} else {
				$err = "Class $cls has no execute method";
			}
		} else {
			$err = "No such class: $cls";
		}
		
		if (strlen($err)) {
			echo "$err\n";
		} else {
			echo "Success\n";
		}
		
	}

}

$extensionkey = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_ms3commerce_cli');
$extensionkey->cli_main($_SERVER['argv']);

?>
