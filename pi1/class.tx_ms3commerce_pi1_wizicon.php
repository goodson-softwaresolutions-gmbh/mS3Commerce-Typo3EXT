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

/**
 * Class that adds the wizard icon.
 *
 * @author	 <>
 * @package	TYPO3
 * @subpackage	tx_ms3commerce
 */
class tx_ms3commerce_pi1_wizicon {

	/**
	 * Processing the wizard items array
	 *
	 * @param	array		$wizardItems: The wizard items
	 * @return	Modified array with wizard items
	 */
	function proc($wizardItems)	{
		$LL = $this->includeLocalLang();

		$wizardItems['plugins_tx_ms3commerce_pi1'] = array(
			'icon'=>\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('ms3commerce').'pi1/ce_wiz.gif',
			'title'=>$GLOBALS['LANG']->getLLL('pi1_title', $LL),
			'description'=>$GLOBALS['LANG']->getLLL('pi1_plus_wiz_description',$LL),
			'params'=>'&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=ms3commerce_pi1'
		);
		
		return $wizardItems;
	}

	/**
	 * Reads the [extDir]/locallang.xml and returns the $LOCAL_LANG array found in that file.
	 *
	 * @return	The array with language labels
	 */
	function includeLocalLang()	{
		$llFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('ms3commerce').'locallang.xml';
		$LOCAL_LANG = $GLOBALS['LANG']->includeLLFile($llFile,FALSE);
		return $LOCAL_LANG;
	}
}

?>
