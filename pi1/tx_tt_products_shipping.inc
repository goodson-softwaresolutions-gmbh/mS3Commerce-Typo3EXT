<?php
//Function getShpping in der Klasse tx_tt_products_hooks öffnen
$hookObj= &t3lib_div::getUserObj('tx_tt_products_hooks');
$tx_ttproducts_basket_calculate = &t3lib_div::getUserObj('tx_ttproducts_basket_calculate');
$tx_ttproducts_basket_calculate = $hookObj->getShipping($tx_ttproducts_basket_calculate);
?>