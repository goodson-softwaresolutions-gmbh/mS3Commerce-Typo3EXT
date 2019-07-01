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

$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key,pages';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(array(
    'LLL:EXT:ms3commerce/locallang_db.xml:tt_content.list_type_pi1',
    $_EXTKEY . '_pi1',
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'ext_icon.gif'
),'list_type');

// For flexforms
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1']='pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($_EXTKEY.'_pi1', 'FILE:EXT:'.$_EXTKEY . '/flexform_ds_pi1.xml');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', '--div--;mS3 Commerce,ms3commerce_user_rights'); 
$TCA['fe_users']['columns']['ms3commerce_user_rights'] = array(
		'label'=>'mS3 Commerce User Rights',
		'config'=>array(
			'type'=>'input',
			'size'=>'80',
			'max'=>'80')
		);

if (defined('MS3C_ENABLE_OCI') && MS3C_ENABLE_OCI) {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', 'mS3C_oci_allow'); 
	$TCA['fe_users']['columns']['mS3C_oci_allow'] = array(
		'label'=>'Allow OCI',
		'config'=>array(
			'type'=>'check'
			)
		);
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_groups', '--div--;mS3 Commerce,ms3commerce_group_rights'); 
$TCA['fe_groups']['columns']['ms3commerce_group_rights'] = array(
		'label'=>'mS3 Commerce Group Rights',
		'config'=>array(
			'type'=>'input',
			'size'=>'80',
			'max'=>'80')
		);
if (TYPO3_MODE == 'BE') {
    $TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses']['tx_ms3commerce_pi1_wizicon'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY).'pi1/class.tx_ms3commerce_pi1_wizicon.php';
}

?>
