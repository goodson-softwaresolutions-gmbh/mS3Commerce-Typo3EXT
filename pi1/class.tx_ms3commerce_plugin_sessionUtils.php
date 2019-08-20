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

use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Sending mail might depend on this, for suppressor
require_once('class.tx_ms3commerce_tt_products_mailer.php');

/**
 * Helpers to handle Session related tasks 
 * @see loginUser(),logoutUser()
 * and session data manipulation
 * @see loadSession(),storeSession(),suppressOutput(),pageRedirectLink()
 */
class tx_ms3commerce_plugin_sessionUtils {

	/**
	 * Load data from session by given key
	 * @param type $key
	 * @return value of key type depends on session variable to be retrieved
	 */
	public static function loadSession($key) {
		if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE']->fe_user) {
			return null;
		}
		// Allow "::key" to be GLOBAL keys! (not mS3 specific)
		if (strpos($key, "::") === 0) {
			$key = substr($key, 2);
		} else {
			$key = "tx_ms3commerce_$key";
		}
		return $GLOBALS['TSFE']->fe_user->getKey('ses', "$key");
	}

	/**
	 * Store values in actual session 
	 * @param type $key
	 * @param type $value
	 * @return boolean
	 */
	public static function storeSession($key, $value) {
		if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE']->fe_user) {
			return false;
		}
		// Allow "::key" to be GLOBAL keys! (not mS3 specific)
		if (strpos($key, "::") === 0) {
			$key = substr($key, 2);
		} else {
			$key = "tx_ms3commerce_$key";
		}
		$GLOBALS['TSFE']->fe_user->setKey('ses', $key, $value);
		//$GLOBALS['TSFE']->fe_user->storeSessionData();
		return true;
	}

	/**
	 * Clears the output buffer or 
	 * @param  $clean type boolean
	 */
	public static function suppressOutput($clean = false) {
		if ($clean) {
			ob_clean();
		}
		ob_flush();
		ob_start("dummy_output");
	}

	public static function pageRedirectLink($link, $force = true) {
		$link = TYPO3\CMS\Core\Utility\GeneralUtility::locationHeaderUrl($link);
		header('Location: ' . $link);
		ob_clean();
		if ($force) {
			// Ensure session data is really stored to DB
			$GLOBALS['TSFE']->fe_user->storeSessionData();
			exit();
		} else {
			self::suppressOutput();
		}
	}

	public static function loginUser($user, $password, $logout) {
		//login user with passed logindata
		if ($logout) {
			self::logoutUser();
		}
		if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE']->fe_user) {
			return false;
		}

		$loginData = array('uname' => $user, 'uident' => $password, 'uident_text' => $password, 'status' => 'login');

		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('saltedpasswords')) {
			$auth = new tx_saltedpasswords_sv1();
			if (!$auth->init()) {
				$auth = $GLOBALS['TSFE']->fe_user;
			}
		} else {
			$auth = $GLOBALS['TSFE']->fe_user;
		}

		$GLOBALS['TSFE']->fe_user->checkPid = 0;
		$info = $GLOBALS['TSFE']->fe_user->getAuthInfoArray();
		$user = $GLOBALS['TSFE']->fe_user->fetchUserRecord($info['db_user'], $loginData['uname']);
		if (is_array($user)) {
			$ok = $auth->compareUident($user, $loginData);
		} else {
			// invalid user name
			$ok = false;
		}
		if ($ok) {
			//login successfull
			$GLOBALS['TSFE']->fe_user->createUserSession($user);

			//dirty solution for login
			$reflection = new \ReflectionClass($GLOBALS['TSFE']->fe_user);
			$setSessionCookie = $reflection->getMethod('setSessionCookie');
			$setSessionCookie->setAccessible(true);
			$setSessionCookie->invoke($GLOBALS['TSFE']->fe_user);
			
			$GLOBALS['TSFE']->fe_user->user = $GLOBALS['TSFE']->fe_user->fetchUserSession();
			$GLOBALS['TSFE']->initUserGroups();

			return true;
		} else {
			return false;
		}
	}

	public static function logoutUser() {
		if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE']->fe_user) {
			return;
		}
		$GLOBALS['TSFE']->fe_user->logoff();
		$GLOBALS['TSFE']->initUserGroups();
	}

	public static function isBELogout(&$authObj) {
		if ($authObj != null && $authObj->loginType == 'BE')
			return true;
		return false;
	}

	public static function getUserId() {
		if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->loginUser) {
			return $GLOBALS['TSFE']->fe_user->user['uid'];
		}
		return null;
	}

	public static function sendMail($emailContent) {
		if (defined('MS3C_OVERWRITE_MAIL_RECV')) {
			$addrs = MS3C_OVERWRITE_MAIL_RECV;
		} else {
			$addrs = $emailContent['emailAddress'];
		}
		if (!array_key_exists('from', $emailContent)) {
			$fromEMail = MS3C_DEFAULT_FROM_EMAIL;
		} else {
			$fromEMail = $emailContent['from'];
		}

		$emailValues_plain = strip_tags($emailContent['message']); //default is HTML email and strip_tags change it to plain. 

		return tx_div2007_email::sendMail($addrs, $emailContent['subject'], $emailValues_plain, $emailContent['message'], $fromEMail, $emailContent['fromName']);

		//return TYPO3\CMS\Core\Utility\GeneralUtility::plainMailEncoded($addrs,$emailContent['subject'],$emailContent['message'],"From: {$emailContent['from']}\n");
	}

	public function mergeRecursiveWithOverrule($original, $overrule) {
		if (method_exists("TYPO3\CMS\Core\Utility\ArrayUtility", "mergeRecursiveWithOverrule")) {
			TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($original, $overrule);
			return $original;
		} else {
			return TYPO3\CMS\Core\Utility\GeneralUtility::array_merge_recursive_overrule($original, $overrule);
		}
	}

}

// Time tracker
class tx_ms3commerce_timetracker {

	var $tt_local = array();
	var $tt_local_stack = array();
	var $tt_local_lvl = 0;

	public function timeTrackStart($key) {
		if (array_key_exists('TT', $GLOBALS)) {
			$GLOBALS['TT']->push($key);
		} else {
			$this->getTimeTracker()->push($key);
		}
		array_push($this->tt_local_stack, count($this->tt_local));
		$this->tt_local[] = array($key, ++$this->tt_local_lvl, microtime(true));
	}

	public function timeTrackStop() {
		$idx = array_pop($this->tt_local_stack);
		$this->tt_local[$idx][] = microtime(true);
		$this->tt_local_lvl--;
		if (array_key_exists('TT', $GLOBALS))
			$GLOBALS['TT']->pull();
		else
			$this->getTimeTracker()->pull();
	}

	public function timeTrackPrint() {
		$str = "";
		foreach ($this->tt_local as $row) {
			$l = sprintf("%6.3f ", $row[3] - $row[2]);
			$l .= str_repeat('-', $row[1]);
			$l .= ": " . $row[0] . "\n";
			$str .= $l;
		}
		return $str;
	}

    /**
     * @return TimeTracker
     */
    protected function getTimeTracker()
    {
        return GeneralUtility::makeInstance(TimeTracker::class);
    }
}

function dummy_output($s) {
	return "";
}

?>
