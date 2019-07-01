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

/// General functions
function mS3CInArray( val, arr )
{
	return jQuery.inArray(val, arr);
}

function mS3CFirstValue( val )
{
	if ( jQuery.isArray(val) ) {
		if ( val.length > 0 ) {
			return val[0];
		} else {
			return undefined;
		}
	}
	
	return val;
}

function mS3CElementAddClass( elem, cls )
{
	var e = jQuery(elem);
	if (e != undefined) {
		e.addClass(cls);
	}
}

function mS3CElementRemoveClass( elem, cls )
{
	var e = jQuery(elem);
	if (e != undefined) {
		e.removeClass(cls);
	}
}

function mS3CElementSetText( elem, txt )
{
	var e = jQuery(elem);
	if (e != undefined) {
		e.html(txt);
	}
}

function mS3CElementSetVisibility( elem, vis )
{
	if (vis === false) {
		vis = 'none';
	} else if (vis === true) {
		vis = 'block';
	}
	
	var e = jQuery(elem);
	if (e != undefined) {
		e.css('display', vis);
	}
}

function mS3CGetControl( index )
{
	return jQuery('#mS3CControl_'+index);
}

function mS3CDefaultSetControlVisibility( idx, vis )
{
	var ctrl = mS3CGetControl(idx);
	var disp = vis ? 'block' : 'none';
	ctrl.css( 'display', disp );
}

///// AJAX
function mS3CDoAJAXRequest( query )
{
	if (typeof(mS3CCustomAJAXRequestStart) === 'function') {
		mS3CCustomAJAXRequestStart(query);
	}
	
	jQuery.ajax({
          url: mS3CQueryScriptUrl,
          type: "POST",
          data: {'query': jQuery.toJSON(query)},
          dataType: "json",
          beforeSend: function(x) {
            if (x && x.overrideMimeType) {
              x.overrideMimeType("application/j-son;charset=UTF-8");
            }
          },
          success: mS3CParseAJAXResponse,
		  error: mS3CAJAXFailure,
		  complete: mS3CAJAXRequestFinished
	});
}

function mS3CParseAJAXResponse(responseJSON, responseText)
{
		mS3CHandleResponseProducts(responseJSON.ResultLayout, responseJSON.Product, responseJSON.Beginning, responseJSON.End, responseJSON.Total);
		mS3CHandleResponseFilters(responseJSON.Level, responseJSON.Filter);
}

function mS3CAJAXFailure(jqXHR, textStatus, errorThrown)
{
	if (typeof(mS3CCustomAJAXRequestError) === 'function') {
		mS3CCustomAJAXRequestError(errorThrown, textStatus);
	}
}

function mS3CAJAXRequestFinished()
{
	if (typeof(mS3CCustomAJAXRequestFinished) === 'function') {
		mS3CCustomAJAXRequestFinished();
	}
}

//////// Checkbox
function mS3CGetCheckboxValue( idx, ctrl )
{
	var items = ctrl.find('input');
	var checkedItems = [];
	jQuery.each(items, function(i, item) {
		item = jQuery(item);
		if (item.prop('checked')) {
			checkedItems = jQuery.merge(checkedItems, item);
		}
	});
	
	var values = [];
	jQuery.each(checkedItems, function(i, item) {
		values[i] = jQuery(item).prop('value');
	});
	if (values.length <1)
	{
		return undefined;
	}
	else
	{
		return values;
	}
}

function mS3CSetCheckboxValue( idx, ctrl, value )
{
	if (value == undefined) {
		value = [];
	}
	var items = ctrl.find('input');
	jQuery.each(items, function(i, item) {
		item = jQuery(item);
		if (jQuery.inArray( item.prop('value'), value) >= 0) {
			item.prop('checked', true);
		} else {
			item.prop('checked', false);
		}
	});
}

function mS3CSetCheckboxDisabledValue( idx, ctrl, value )
{
	var newHtml = '';
	jQuery.each(value, function(i, v) {
		var name = 'mS3CControl_'+idx+'_val_'+v;
		newHtml +=
			'<label class="disabled"><input type="checkbox" enabled="false" readonly="true" name="'+name+'" value="'+v+'"/>'+v+'</label>';
	});
	ctrl.html(ctl.html() + newHtml);
}

function mS3CSetupCheckboxBounds( idx, ctrl, bounds, htmls, counts )
{
	var newHtml = '';
	jQuery.each(bounds, function(i, bound){
		var name = 'mS3CControl_'+idx+'_val_'+bound;
		var content = htmls[i];
		if (mS3CShowFilterCounts) {
			content += '<span class="mS3CCount"> ('+counts[i]+')</span>';
		}
		newHtml +=
			'<label class="checked"><input type="checkbox" name="'+name+'" value="'+bound+'"/ onchange="mS3CControlChanged('+idx+');">'+content+'</label>';
	});
	
	if (bounds.length <= 1) {
		mS3CControls[idx].hasData = false;
	} else {
		mS3CControls[idx].hasData = true;
	}
	ctrl.html( newHtml );
	
}

//////// Radio
function mS3CGetRadioValue( idx, ctrl )
{
	var items = ctrl.find('input');
	var checkedItem = undefined;
	for (var i = 0; i < items.length; ++i)
	{
		var itm = jQuery(items[i]);
		if ( itm.prop('checked') == true)
		{
			checkedItem = itm;
			break;
		}
	}
	
	if ( checkedItem == undefined )
	{
		return undefined;
	}
	
	return [checkedItem.prop('value')];
}

function mS3CSetRadioValue( idx, ctrl, value )
{
	var items = ctrl.find('input');
	value = mS3CFirstValue(value);
	for (var i = 0; i < items.length; ++i)
	{
		var itm = jQuery(items[i]);
		if ( itm.prop('value') == value)
		{
			itm.prop('checked', true);
			break;
		}
	}
}

function mS3CSetRadioDisabledValue( idx, ctrl, value )
{
	var newHtml = '';
	jQuery.each(value, function(i, v) {
		var name = 'mS3CControl_'+idx+'_radiogroup';
		newHtml +=
			'<label class="disabled unselected"><input type="checkbox" radio="false" readonly="true" name="'+name+'" value="'+v+'"/>'+v+'</label>';
	});
	ctrl.html(ctrl.html() + newHtml);
}

function mS3CSetupRadioBounds( idx, ctrl, bounds, htmls, counts )
{
	var newHtml = '';
	var name = 'mS3CControl_'+idx+'_radiogroup';
	jQuery.each(bounds, function(i, bound){
		var content = htmls[i];
		if (mS3CShowFilterCounts) {
			content += '<span class="mS3CCount"> ('+counts[i]+')</span>';
		}
		newHtml +=
			'<label class="unselected"><input type="radio" name="'+name+'" value="'+bound+'"/>'+content+'</label>';
	});
	
	if (bounds.length <= 1) {
		mS3CControls[idx].hasData = false;
	} else {
		mS3CControls[idx].hasData = true;
	}
	
	ctrl.html(newHtml);
	
	jQuery(ctrl).find('input').change(function() {mS3CControlChanged(idx);});
}

//////// Select
function mS3CGetSelectValue( idx, ctrl )
{
	var sel = ctrl.find('select');
	var val = sel.find('option:selected');
	if ( val != undefined ) {
		var selVal = val.prop('value');
		if ( selVal == '__mS3C_Undefined_value__' ) {
			return undefined;
		}
		return [selVal];
	}
	return undefined;
}

function mS3CSetSelectValue( idx, ctrl, value )
{
	var sel = ctrl.find('select');
	var items = sel.find('option');
	
	value = mS3CFirstValue(value);
	
	if ( value == undefined ) {
		items[0].prop('selected', true);
	} else {
		jQuery.each(items, function(i, item) {
			item = jQuery(item);
			if (item.prop('value') == value) {
				item.prop('selected', true);
			}
		});
	}
}

function mS3CSetSelectDisabledValue( idx, ctrl, value )
{
	// TODO
}

function mS3CSetupSelectBounds( idx, ctrl, bounds, htmls, counts )
{
	var sel = ctrl.find('select')[0];
	jQuery(sel).unbind('change');
	jQuery(sel).bind('change', function() {mS3CControlChanged(idx);});
	if (sel != undefined)
	{
		sel.options.length = 0;
		sel.options[0] = new Option(trans.noselect, '__mS3C_Undefined_value__');
		var i = 1;
		jQuery.each(bounds, function(ii, bound) {
			var content = bound;
			if (mS3CShowFilterCounts) {
				content += ' ('+counts[ii]+')';
			}
			sel.options[i++] = new Option(content, bound);
		});
	}
	if (bounds.length < 1) {
		mS3CControls[idx].hasData = false;
		if (sel != undefined)
		{
			sel.options.length = 0;
			sel.options[0] = new Option(trans.emptyselect, '__mS3C_Undefined_value__');
		}
	} else {
		mS3CControls[idx].hasData = true;
	}
}

///SESSION
function mS3CGetSessionValue( idx, ctrl )
{
	var value = ctrl.prop('value');
	if ( value != "") {
		return [value];
	}
	return undefined;
}


//////// Text
function mS3CGetTextValue( idx, ctrl )
{
	var value = ctrl.prop('value');
	if ( value != "") {
		return [value];
	}
	return undefined;
}

function mS3CSetTextValue( idx, ctrl, value)
{
	ctrl.val(value);
}

function mS3CSetupTextBounds( id, ctrl, bounds, htmls, counts )
{
	if (bounds.length < 1)
	{
		ctrl.attr('disabled', true);
		ctrl.val(trans.emptyselect);
	}
	else
	{
		ctrl.removeAttr('disabled');
		ctrl.val('');
	}
}

//////// TextFields
function mS3CGetTextFieldsValue( idx, ctrl )
{
	var field1 = ctrl.find('.field1');
	var field2 = ctrl.find('.field2');
	var value1 = field1.prop('value');
	var value2 = field2.prop('value');
	if ( value1 != "" && value2 != "") {
		return [value1, value2];
	}
	return undefined;
}

function mS3CSetTextFieldsValue( idx, ctrl, value)
{
	var field1 = ctrl.find('.field1');
	var field2 = ctrl.find('.field2');
	field1.val(value[0]);
	field2.val(value[1]);
}

function mS3CSetupTextFieldsBounds( id, ctrl, bounds, htmls, counts )
{
	var field1 = ctrl.find('.field1');
	var field2 = ctrl.find('.field2');
	
	if (bounds.length < 1)
	{
		field1.attr('disabled', true);
		field1.val(trans.emptyselect);
		field2.attr('disabled', true);
		field2.val(trans.emptyselect);
	}
	else
	{
		field1.removeAttr('disabled');
		field1.val('');
		field2.removeAttr('disabled');
		field2.val('');
	}
}

function TextFieldsChange(idx, ctrl)
{
	var field1 = jQuery('#' + ctrl).find('.field1');
	var field2 = jQuery('#' + ctrl).find('.field2');
	var value1 = field1.prop('value');
	var value2 = field2.prop('value');
	if ( value1 != "" && value2 != "") {
		mS3CControlChanged(idx);
	}
	if ( value1 == "" && value2 == "") {
		mS3CControlChanged(idx);
	}
}

//////// Slider
function mS3CInitSlider(id)
{
	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eVal = elem.find('.mS3CValue');
	
	jQuery(eSlider).slider({
		value:0,
		min: 0,
		max: 0,
		step: 1,
		slide: function( event, ui ) {
			if (ui.value == 0)
			{
				jQuery( eVal ).html("-");    
			}
		}
	});
	jQuery( eVal ).html("-"); 	
}

//////// Slider
function mS3CGetSliderValue( idx, ctrl )
{
	var elem = jQuery( "#mS3CControl_" + idx );
	var eSlider = elem.find('.mS3CSlider');
	var slidervalues = jQuery(eSlider).slider( "option", "value" );
	if (slidervalues == 0)
	{
		return undefined;
	}
	else
	{
		var values = jQuery(eSlider).slider( "option", "bound" );
		return [values[slidervalues - 1]];
	}
}

function mS3CSetSliderValue( idx, ctrl, value )
{
	var elem = jQuery( "#mS3CControl_" + idx );
	var eSlider = elem.find('.mS3CSlider');
	var eVal = elem.find('.mS3CValue');
	var values = jQuery(eSlider).slider( "option", "bound" );
	for (k = 0; k < values.length; ++k) {
		if (values[k] == value[0])
			{
				jQuery(eSlider).slider( "option", "value", k + 1);
				break;
			}
		if (values[k] > value[0])
		{
			var step;
			if ( mS3CControls[idx].compType == 'Less' ) {
				step = k;
			} else if ( mS3CControls[idx].compType == 'Greater' ) {
				step = k + 1;
			}
			jQuery(eSlider).slider( "option", "value", step);
			break;
		}
		if (k + 1 == values.length)
		{
			jQuery(eSlider).slider( "option", "value", k + 1);
			break;
		}
	}
}

function mS3CSetSliderDisabledValue( idx, ctrl, value )
{
}

function mS3CSetupSliderBounds( id, ctrl, bounds, htmls, counts )
{
	bounds = jQuery.map( bounds, function(n){
		return n;
	});
	htmls = jQuery.map( htmls, function(n){
		return n;
	});
	var disabled = false;
	if (bounds.length < 1)
	{
		disabled = true;
	}
	
	var cleaned = mS3CCleanupValues(bounds, htmls);
	bounds = cleaned[0];
	htmls = cleaned[1];

	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eLeft = elem.find('.mS3CMinValue');
	var eRight = elem.find('.mS3CMaxValue');
	var eVal = elem.find('.mS3CValue');
	
	jQuery(eSlider).slider( "destroy" );
	
	if ( mS3CControls[id].compType == 'Less' ) {
		range = 'min';
	} else if ( mS3CControls[id].compType == 'Greater' ) {
		range = 'max';
	} else {
		range = false;
	}
	jQuery(eSlider).slider({
		range: range,
		value:0,
		min: 0,
		bound: bounds,
		disabled: disabled,
		max: bounds.length,
		step: 1,
		slide: function( event, ui ) {
			if (ui.value == 0)
			{
				jQuery( eVal ).html("-");    
			}
			else
			{
				jQuery( eVal ).html(htmls[ui.value - 1]);  
			}
		},
		stop: function( event, ui ){
			mS3CControlChanged(id);
		}
	});
	if (bounds.length > 0)
	{
		jQuery( eLeft ).html(htmls[0]);
		jQuery( eRight ).html(htmls[bounds.length - 1]);
	}
	else
	{
		jQuery( eLeft ).html("");
		jQuery( eRight ).html("");
		jQuery( eVal ).html("-"); 
	}
}

//////// Range
function mS3CInitRange(id)
{
	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eVal = elem.find('.mS3CValue');
	
	jQuery(eSlider).slider({
		range:true,
		values:[0,1],
		min: 0,
		max: 1,
		step: 1,
		slide: function( event, ui ) {
			if (ui.values[0] == 0 && ui.values[1] == 1)
			{
				jQuery( eVal ).html("-");    
			}
		}
	});
	jQuery( eVal ).html("-"); 	
}

function mS3CGetRangeValue( idx, ctrl )
{
	var elem = jQuery( "#mS3CControl_" + idx );
	var eSlider = elem.find('.mS3CSlider');
	var slidervalues = jQuery(eSlider).slider( "option", "values" );
	var values = jQuery(eSlider).slider( "option", "bound" );
	if (slidervalues[0] == 0 && slidervalues[1] == values.length - 1)
	{
		return undefined;
	}
	else
	{
		return [values[slidervalues[0]], values[slidervalues[1]]];
	}
}

function mS3CSetRangeValue( idx, ctrl, value )
{
	var elem = jQuery( "#mS3CControl_" + idx );
	var eSlider = elem.find('.mS3CSlider');
	var eVal = elem.find('.mS3CValue');
	var values = jQuery(eSlider).slider( "option", "bound" );
	
	var adjustedValues = mS3CAdjustRangeValues(values, value[0], value[1]);
	
	jQuery(eSlider).slider( "option", "values", adjustedValues);
}

function mS3CSetRangeDisabledValue( idx, ctrl, value )
{
	// No default implementation
}

function mS3CSetupRangeBounds( id, ctrl, bounds, htmls, counts )
{
	
	bounds = jQuery.map( bounds, function(n){
		return n;
	});
	htmls = jQuery.map( htmls, function(n){
		return n;
	});
	
	var cleaned = mS3CCleanupValues(bounds, htmls);
	bounds = cleaned[0];
	htmls = cleaned[1];
	
	var disabled = false;
	if (bounds.length < 2)
	{
		disabled = true;
	}

	
	var elem = jQuery( "#mS3CControl_" + id );
	var eSlider = elem.find('.mS3CSlider');
	var eLeft = elem.find('.mS3CMinValue');
	var eRight = elem.find('.mS3CMaxValue');
	var eVal = elem.find('.mS3CValue');
	
	jQuery(eSlider).slider( "destroy" );
	
	jQuery(eSlider).slider({
		range:true,
		values:[0, bounds.length - 1],
		min: 0,
		disabled: disabled,
		bound: bounds,
		max: bounds.length - 1,
		step: 1,
		slide: function( event, ui ) {
			if (ui.values[0] == 0 && ui.values[1] == bounds.length - 1)
			{
				jQuery( eVal ).html("-");    
			}
			else
			{
				jQuery( eVal ).html(htmls[ui.values[0]] + " - " + htmls[ui.values[1]]);  
			}
		},
		stop: function( event, ui ){
			mS3CControlChanged(id);
		}
	});
	if (bounds.length > 1)
	{
		jQuery( eLeft ).html(htmls[0]);
		jQuery( eRight ).html(htmls[bounds.length - 1]);
	}
	else
	{
		jQuery( eLeft ).html("");
		jQuery( eRight ).html("");
		jQuery( eVal ).html("-"); 
	}
}

function mS3CKeyHandler(ctrl)
{
	jQuery('#' + ctrl).keypress(function(e) {
			if(e.which == 13) {
				jQuery(this).blur();
			}
		});
}

function mS3CKeyHandler2Fields(ctrl)
{
	var field1 = jQuery('#' + ctrl).find('.field1');
	var field2 = jQuery('#' + ctrl).find('.field2');
	jQuery(field1).keypress(function(e) {
			if(e.which == 13) {
				jQuery(this).blur();
			}
		});
	jQuery(field2).keypress(function(e) {
			if(e.which == 13) {
				jQuery(this).blur();
			}
		});
}

function mS3CJSONEncode(str)
{
	return jQuery.toJSON(str);
}

function mS3CJSONDecode(str)
{
	try {
		return jQuery.parseJSON(str);
	} catch(err) {
		return undefined;
	}
}
