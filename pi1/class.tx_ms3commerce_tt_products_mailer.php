<?php

require_once('class.tx_ms3commerce_tt_products.php');

// Alias for Typo3 6
if (interface_exists('Swift_Events_SendListener')) {

	class tx_ms3commerce_tt_products_mailer extends ux_t3lib_mail_Mailer {

		public function __construct(Swift_Transport $transport = NULL) {
			parent::__construct($transport);
		}

	}

}
?>
