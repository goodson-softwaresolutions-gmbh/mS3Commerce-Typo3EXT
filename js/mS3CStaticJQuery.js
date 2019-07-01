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

//a Slider with fixed steps 
function mS3CSlider(id, sliderValue, sliderHtmls)
{
	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eInput = jQuery('#mS3CInput' + id);
	var eVal = elem.find('.mS3CValue');
	var eLeft = elem.find('.mS3CMinValue');
	var eRight = elem.find('.mS3CMaxValue');
	
	//jQuery(eSlider).slider( "destroy" );
	
	jQuery(eSlider).slider({
		value:0,
		min: 0,
		max: sliderValue.length - 1,
		step: 1,
		slide: function( event, ui ) {
			if (ui.value == 0)
			{
				jQuery( eInput ).val("");
				jQuery( eVal ).html("-");    
			}
			else
			{
				jQuery( eInput ).val(sliderValue[ui.value]);
				jQuery( eVal ).html(sliderHtmls[ui.value]);  
			}
		}
	});
	jQuery( eInput ).val("");
	jQuery( eVal ).html("-");
	jQuery( eLeft ).html(sliderHtmls[1]);
	jQuery( eRight ).html(sliderHtmls[sliderValue.length - 1]);
}

//a Slider with dynamic steps 
function mS3CSliderContinuous(id, min, max, sliderType)
{
	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eInput = jQuery('#mS3CInput' + id);
	var eVal = elem.find('.mS3CValue');
	var eLeft = elem.find('.mS3CMinValue');
	var eRight = elem.find('.mS3CMaxValue');
	
	//jQuery(eSlider).slider( "destroy" );
	
	if (sliderType == 'less') {
		sliderType = 'min';
	} else if (sliderType == 'greater') {
		sliderType = 'max';
	} else {
		sliderType = false;
	}
	
	min = parseInt(min)
	max = parseInt(max)
	eSlider.slider({
		range: sliderType,
		value: min - 1,
		min: min - 1,
		max: max,
		steps: 1,
		slide: function( event, ui ) {
			if (ui.value == min - 1)
			{
				jQuery( eInput ).val("");
				jQuery( eVal ).html("-");    
			}
			else
			{
				jQuery( eInput ).val(ui.value);
				jQuery( eVal ).html(ui.value);  
			}
	}
	});
	jQuery( eInput ).val("");
	jQuery( eVal ).html("-");
	jQuery( eLeft ).html(min);
	jQuery( eRight ).html(max);
}

//a Range Slider with dynamic steps 
function mS3CRangeContinuous(id, min, max)
{
	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eInput = jQuery('#mS3CInput' + id);
	var eVal = elem.find('.mS3CValue');
	var eLeft = elem.find('.mS3CMinValue');
	var eRight = elem.find('.mS3CMaxValue');
	
	//jQuery(eSlider).slider( "destroy" );
	
	min = parseInt(min)
	max = parseInt(max)
	eSlider.slider({
		values: [min - 1, max],
		min: min,
		max: max,
		range: true,
		steps: 1,
		slide: function( event, ui ) {
			if (ui.values[0] == min && ui.values[1] == max)
			{
				jQuery( eInput ).val("");
				jQuery( eVal ).html("-");    
			}
			else
			{
				jQuery( eInput ).val(ui.values[0] + "|" + ui.values[1]);
				jQuery( eVal ).html(ui.values[0] + " - " + ui.values[1]);  
			}
		}
	});
	jQuery( eInput ).val("");
	jQuery( eVal ).html("-"); 
	jQuery( eLeft ).html(min);
	jQuery( eRight ).html(max);
}

//a Range Slider with fixed steps 
function mS3CRange(id, sliderValue, sliderHtmls)
{
	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eInput = jQuery('#mS3CInput' + id);
	var eVal = elem.find('.mS3CValue');
	var eLeft = elem.find('.mS3CMinValue');
	var eRight = elem.find('.mS3CMaxValue');
	
	//jQuery(eSlider).slider( "destroy" );
	
	eSlider.slider({
		values: [0, sliderValue.length - 1],
		min: 0,
		max: sliderValue.length - 1,
		range: true,
		steps: 1,
		slide: function( event, ui ) {
			if (ui.values[0] == 0 && ui.values[1] == sliderValue.length - 1)
			{
				jQuery( eInput ).val("");
				jQuery( eVal ).html("-");    
			}
			else
			{
				jQuery( eInput ).val(sliderValue[ui.values[0]] + "|" + sliderValue[ui.values[1]]);
				jQuery( eVal ).html(sliderHtmls[ui.values[0]] + " - " + sliderHtmls[ui.values[1]]);  
			}
		}
	});
	jQuery( eInput ).val("");
	jQuery( eVal ).html("-");
	jQuery( eLeft ).html(sliderHtmls[0]);
	jQuery( eRight ).html(sliderHtmls[sliderValue.length - 1]);
}

function mS3CInvokeCompletion(term, callback)
{
	var req = mS3CSCQData;
	req.term = term;
	jQuery.post(mS3CSCQUrl, req, function( res, status, xhr ) {
			data = res.values;
			callback( data );
		}, "json");
}
