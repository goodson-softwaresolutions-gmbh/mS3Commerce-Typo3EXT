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

require_once('class.tx_ms3commerce_plugin_sessionUtils.php');
require_once('class.tx_ms3commerce_db.php');

define('MS3C_LOCK_DIR', MS3C_EXT_ROOT.'/dataTransfer/custom/locks/');
/**
 * Class tx_ms3commerce_task_utils  
 */

class tx_ms3commerce_task_utils {

	/**
	 * Handles a task result. Notifies by mail about failure / success, and sets
	 * the error/success state in the Typo3 Task list
	 * @param string $desc Task name
	 * @param mixed $ret Task result. true == success, everything else is error description
	 * @param boolean $throwErrorException If errors should result in an exception.
	 *		Exceptions cause a message in the Typo3 task list
	 * @param boolean $forceSuccessMail If a email should be sent in any case
	 * @return boolean true on success, false otherwise
	 * @throws Exception Thrown on error if $throwErrorException is true to 
	 *		create a message in the Typo3 BE Task list
	 */
	public static function handleTaskResult($desc, $ret, $throwErrorException = true, $forceSuccessMail = false) {
		if ($ret !== true) {
			// Set error description?
			$mail = array('emailAddress' => MS3C_TASK_MAIL_RECEIVER, 'subject' => $desc . ' FAILED!', 'message' => $ret);
			@tx_ms3commerce_plugin_sessionUtils::sendMail($mail);
			if ($throwErrorException) {
				throw new Exception($ret);
			}
			return false;
		} else if (MS3C_TASK_NOTIFY_ON_SUCCESS || $forceSuccessMail) {
			$mail = array('emailAddress' => MS3C_TASK_MAIL_RECEIVER, 'subject' => $desc . ' SUCCESS!', 'message' => $desc . ' successully finished');
			@tx_ms3commerce_plugin_sessionUtils::sendMail($mail);
		}
		return true;
	}

}

// At-End callback to clean up locks
function mS3C_scheduler_do_leaveMutex()
{
	foreach (tx_ms3commerce_process_launcher::$autoleaveMutexes as $m) {
		tx_ms3commerce_process_launcher::leaveMutex($m);
	}
}

/**
 *  Helper for starting PHP processes for Typo3 CLI
 */
class tx_ms3commerce_process_launcher {
	static $autoleaveMutexes = array();
	static $autoleaveRegistered = false;
	static function tryEnterMutex($mutex, $autoLeave = true) {
		if (self::checkMutexLocked($mutex)) {
			return false;
		}
		
		if ($autoLeave) {
			self::$autoleaveMutexes[] = $mutex;
			if (!self::$autoleaveRegistered) {
				self::$autoleaveRegistered = true;
				register_shutdown_function('mS3C_scheduler_do_leaveMutex');
			}
		}
		
		$fp = fopen(MS3C_LOCK_DIR.$mutex, 'w');
		fwrite($fp, getmypid());
		fclose($fp);
		return true;
	}
	static function leaveMutex($mutex) {
		if (is_file(MS3C_LOCK_DIR.$mutex)) {
			unlink(MS3C_LOCK_DIR.$mutex);
		}
	}
	static function checkMutexLocked($mutex) {
		if (is_file(MS3C_LOCK_DIR.$mutex)) {
			return true;
		}
		return false;
	}
	
	static function startCLIProcess($process) {
		$php = MS3C_PHP_BINARY;
		$clistarter = MS3C_EXT_ROOT.'/typo3/cli_dispatch.phpsh';
		return self::execInBackground("$php \"$clistarter\" \"ms3commerce\" \"$process\"", $process, MS3C_EXT_ROOT.'/dataTransfer/custom/log/cli.log', MS3C_EXT_ROOT.'/dataTransfer/custom/log/cli.log');
	}
	
	static function execInBackground($cmd, $name = null, $out = null, $err = null) {
		if ($name == null) $name = $cmd;
		if (substr(php_uname(), 0, 7) == "Windows"){
			$cmd = "start /B ".$cmd;
		} else {
			$cmd .= " &";
		}
		$pipes = array();
		$descriptorspec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w"),
		);
		if ($out) {
			$descriptorspec[1] = array("file", $out, "at");  // stdout is a pipe that the child will write to
		}
		if ($err) {
			$descriptorspec[2] = array("file", $err, "at"); // stderr is a file to write to
			$fp = fopen($err, 'at');
			fwrite($fp, "\n".date("Y-m-d G:i:s").": Invoking $name\n");
			fclose($fp);
		}

		// Task will execute in background, until finished.
		$r = proc_open($cmd, $descriptorspec, $pipes, getcwd());
		if ($r === false) {
			return false;
		}
		$data = proc_get_status ( $r );
		proc_close($r);

		return $data["pid"];
	}
}

?>
