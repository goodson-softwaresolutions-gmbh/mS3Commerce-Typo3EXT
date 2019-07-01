<?php
//Function getShpping in der Klasse tx_tt_products_hooks öffnen
$hookObj= &t3lib_div::getUserObj('EXT:ms3commerce/pi1/class.tx_ms3commerce_tt_products.php:&tx_tt_products_hooks');
$this->basket = $hookObj->getShipping($this->basket);
?>