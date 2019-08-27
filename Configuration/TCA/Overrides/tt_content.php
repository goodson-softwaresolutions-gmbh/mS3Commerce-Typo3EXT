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

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['ms3commerce_pi1']='layout,select_key,pages';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(array(
    'LLL:EXT:ms3commerce/locallang_db.xml:tt_content.list_type_pi1',
    'ms3commerce_pi1',
    'EXT:ms3commerce/ext_icon.gif'
),'list_type', 'ms3commerce');


// For flexforms
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['ms3commerce_pi1']='pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue('ms3commerce_pi1', 'FILE:EXT:ms3commerce/flexform_ds_pi1.xml');
