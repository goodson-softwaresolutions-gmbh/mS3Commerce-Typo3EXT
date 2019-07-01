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

if (MS3C_TYPO3_RELEASE == '7')
{
	$t3Path = rtrim(realpath(__DIR__ . '/../../../../typo3'), '\\/');
	$classLoader = require $t3Path . '/../vendor/autoload.php';
	class tx_ms3commerce_t3minibootstrap_base extends \TYPO3\CMS\Core\Core\Bootstrap {
		static $isInitialized = false;
		static $isFinalized = false;
		static $configLoaded = false;
		public static $classLoader = '';
		
		public static function init($relPath) {
			if (self::$isInitialized) {
				return;
			}
			
			// defineLegacyConstants
			if (!defined('TYPO3_MODE')) {
				define('TYPO3_MODE', 'FE');
			}
			
			parent::getInstance()
				->initializeClassLoader(self::$classLoader)
				->baseSetup($relPath)
				;
			
			self::$isInitialized = true;
		}
		public static function finalize() {
			if (self::$isFinalized) {
				return;
			}
			
			parent::getInstance()
				->populateLocalConfiguration()
				->initializeCachingFramework()
				->initializePackageManagement(\TYPO3\CMS\Core\Package\PackageManager::class);
			self::$isFinalized = true;
		}
		
		protected static function loadExtConfig($key)
		{
			if (!self::$configLoaded) {
				parent::getInstance()->loadTypo3LoadedExtAndExtLocalconf(true);
				self::$configLoaded = true;
			}
		}
	}
	tx_ms3commerce_t3minibootstrap_base::$classLoader = $classLoader;
	unset($classLoader);
	unset($t3Path);
}
else
{

	require_once MS3C_EXT_ROOT . '/typo3/sysext/core/Classes/Core/Bootstrap.php';
	
	/**
	 * Minimal bootstrap to use typo3 functions independently from a typo3 environment
	 * for example call directly php file from ajax 
	 */
	class tx_ms3commerce_t3minibootstrap_base extends \TYPO3\CMS\Core\Core\Bootstrap {

		static $isInitialized = false;
		static $isFinalized = false;
		static $configLoaded = false;

		public static function init($relPath) {
			if (self::$isInitialized) {
				return;
			}
			
			parent::getInstance()->baseSetup($relPath);
			$parts = explode('.', TYPO3_version);

			if ($parts[0] == '6' && ($parts[1] == '0' || $parts[1] == '1')) {
				// 6.0 and 6.1
				parent::getInstance()
						->populateLocalConfiguration()
						->registerExtDirectComponents()
						->initializeCachingFramework()
						->registerAutoloader();
			} else {
				// 6.2 or higher
				parent::getInstance()
						->initializeClassLoader();
			}
			self::$isInitialized = true;
		}
		
		public static function finalize() {
			if (self::$isFinalized) {
				return;
			}

			$parts = explode('.', TYPO3_version);
			if ($parts[0] == '6' && ($parts[1] == '0' || $parts[1] == '1')) {
				// 6.0 and 6.1
				// Nothing more to do
			} else {
				// 6.2 or higher
				parent::getInstance()
						->populateLocalConfiguration()
						->initializeCachingFramework()
						->initializeClassLoaderCaches()
						->initializePackageManagement("TYPO3\\CMS\\Core\\Package\\PackageManager");
			}
			
			self::$isFinalized = true;
		}
		
		protected static function loadExtConfig($key)
		{
			$parts = explode('.', TYPO3_version);
			if ($parts[0] == '6' && ($parts[1] == '0' || $parts[1] == '1')) {
				// 6.0 and 6.1
				if (!array_key_exists('TYPO3_LOADED_EXT', $GLOBALS)) {
					parent::getInstance()->populateTypo3LoadedExtGlobal(true);
				}
			} else {
				// 6.2 or higher
				if (!self::$configLoaded) {
					parent::getInstance()->loadTypo3LoadedExtAndExtLocalconf(true);
					self::$configLoaded = true;
				}
			}
		}
	}
}

class tx_ms3commerce_t3minibootstrap extends tx_ms3commerce_t3minibootstrap_base
{
	public static function loadExtensionConfig($extName) {
		tx_ms3commerce_t3minibootstrap_base::loadExtConfig($extName);

		// Should be there if initialised...
		if (!array_key_exists('TYPO3_CONF_VARS', $GLOBALS)) {
			return false;
		}

		if (array_key_exists('EXTCONF', $GLOBALS['TYPO3_CONF_VARS']) && array_key_exists($extName, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'])) {
			// Config already loaded. Do nothing
			return true;
		}

		// Is extension loaded?
		if (!array_key_exists($extName, $GLOBALS['TYPO3_LOADED_EXT'])) {
			return false;
		}

		$conf = $GLOBALS['TYPO3_LOADED_EXT'][$extName];
		$_EXTKEY = $extName;
		$_EXTCONF = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY];

		if (array_key_exists('ext_localconf.php', $conf)) {
			// some exts might depend on a global TYPO3_CONF_VARS
			global $TYPO3_CONF_VARS;
			require $conf['ext_localconf.php'];
		}
		return true;
	}
}

?>
