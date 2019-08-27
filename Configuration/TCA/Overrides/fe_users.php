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


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', '--div--;mS3 Commerce,ms3commerce_user_rights'); 
$GLOBALS['TCA']['fe_users']['columns']['ms3commerce_user_rights'] = array(
		'label'=>'mS3 Commerce User Rights',
		'config'=>array(
			'type'=>'input',
			'size'=>'80',
			'max'=>'80')
		);

if (defined('MS3C_ENABLE_OCI') && MS3C_ENABLE_OCI) {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', 'mS3C_oci_allow'); 
	$GLOBALS['TCA']['fe_users']['columns']['mS3C_oci_allow'] = array(
		'label'=>'Allow OCI',
		'config'=>array(
			'type'=>'check'
			)
		);
}