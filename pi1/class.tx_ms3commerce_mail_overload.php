<?php

// For suppressing tt_products automatic mails.
// 2 Situations:
// 1. normal mail ==> tt_products hook (sendMail)
// 2. Swift Mail ==> Swift plugin (below)
class user_tx_ms3commerce_tt_products_mail_suppressor {

	var $db;
	var $custom;

	public function __construct() {
		$this->db = tx_ms3commerce_db_factory::buildDatabase(true);
		$this->custom = tx_ms3commerce_pi1::makeObjectInstance('tx_ms3commerce_custom_shop');
		$this->custom->setup($this->db, null, null, null);
	}

	public function sendMail(&$Typo3_htmlmail, &$toEMail, $subject, $message, $html, $fromEMail, $fromName, $attachment) {
		$cc = null;
		$bcc = null;
		if ($this->suppressMail($toEMail)) {
			return false;
		}
		$toEMail = implode(';', array($toEMail, $cc, $bcc));
		$Typo3_htmlmail->setRecipient(explode(';', $toEMail));
		return true;
	}

	protected function suppressMail(&$toEMail = null, &$ccEMail = null, &$bccEMail = null) {
		$to = $toEMail;
		$cc = $ccEMail;
		$bcc = $bccEMail;
		if ($this->custom->suppressFinalizeMail($toEMail, $ccEMail, $bccEMail)) {
			return true;
		} else {
			if (defined('MS3C_OVERWRITE_BASKET_MAIL_RECV')) {
				// Allow customizer to change addresses...
				if ($to == $toEMail && $cc == $ccEMail && $bcc == $bccEMail) {
					// Customizer didn't change mail addresses, so overwrite
					$bccEMail = $ccEMail = "";
					$toEMail = MS3C_OVERWRITE_BASKET_MAIL_RECV;
				}
			}
			return false;
		}
	}

}

@include_once(PATH_typo3 . 'contrib/swiftmailer/swift_required.php');

if (interface_exists('Swift_Events_SendListener')) {

    class ux_t3lib_mail_Mailer extends TYPO3\CMS\Core\Mail\Mailer {

        public function __construct(Swift_Transport $transport = NULL) {
            parent::__construct($transport);
            $this->registerPlugin(new tx_ms3commerce_tt_products_mail_suppressor_plugin);
        }

    }

    class tx_ms3commerce_tt_products_mail_suppressor_plugin extends user_tx_ms3commerce_tt_products_mail_suppressor implements Swift_Events_SendListener {

        private function isFinalizeMail() {
            $fin = TYPO3\CMS\Core\Utility\GeneralUtility::_GP("products_finalize");
            if (isset($fin) && $fin != null) {
                return true;
            }
        }

        public function beforeSendPerformed(Swift_Events_SendEvent $evt) {
            if ($this->isFinalizeMail()) {
                $to = $evt->getMessage()->getTo();
                $cc = $evt->getMessage()->getCc();
                $bcc = $evt->getMessage()->getBcc();

                if ($this->suppressMail($to, $cc, $bcc)) {
                    $evt->cancelBubble();
                } else {
                    $evt->getMessage()->setTo($to);
                    if ($cc) {
                        $evt->getMessage()->setCc($cc);
                    }
                    if ($bcc) {
                        $evt->getMessage()->setBcc($bcc);
                    }
                }
            }
        }

        public function sendPerformed(Swift_Events_SendEvent $evt) {

        }
    }
}
?>