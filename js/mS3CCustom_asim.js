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

function mS3CCustomInit() {
	mS3CPagingType = MS3C_PAGING_PAGE;
}


function mS3CCustomHandlePaging(end) {
	// Form "More" Paging style. Do nothing
}
/*
function mS3CCustomHandleResponseProducts(prods)
{
	//var root = $('#mS3CResult');
	var content = '';//<table style="width: 100%">';
	
	if ( prods != undefined )
	{
		jQuery.each(prods, function(idx, prod) {
			var title = prod.Values['Artikel Nr.'];
			var img = prod.Values['Produktbild'];
			var descr = prod.Values['Beschreibung der Besonderheiten'];
			var lnk = prod.Link;
			var xxx ="";
			if (descr == null ||descr == undefined) {
				descr = "";
			}
			content += '<div class="overview_4st" style="width:500px">';
			content += '	<a class="trefferList_sk" href="'+lnk+'">'+img+'</a>';
			content += '	<table>';
			content += '		<tbody>';
			content += '		<tr>';
			content += '			<td>';
			content += '				<h2><a href="'+lnk+'"><span ">'+title+'</span></a></h2>';
			content += '			</td>';
			content += '			<td class="text_rechts mS3Commerce_isnotempty" width="150px">';
			content += '				<span>'+xxx+'</span>';
			content += '			</td>';
			content += '		</tr>';
			content += '		<tr>';
			content += '			<td class="text_links" colspan="2">';
			content += '				<div class="mS3 Commerce marketingtext_kurz"><p>'+descr+'</p></div>';
			content += '			</td>';
			content += '		</tr>';
			content += '		</tbody>';
			content += '	</table>';
			content += '</div>';
		});
	}
	//content += '</table>';
	mS3CElementSetText('#mS3CResult', content);
}
*/
