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
	// Mootools extends native arrays so this will always work:
	return arr.indexOf( val );
}

function mS3CFirstValue( val )
{
	if ( typeof(val) == 'array' ) {
		if ( val.length > 0 ) {
			return val[0]
		} else {
			return undefined;
		}
	}
	
	return val;
}

function mS3CElementAddClass( elem, cls )
{
	var e = $$(elem);
	if (e != undefined) {
		e.addClass(cls);
	}
}

function mS3CElementRemoveClass( elem, cls )
{
	var e = $$(elem);
	if (e != undefined) {
		e.removeClass(cls);
	}
}

function mS3CElementSetText( elem, txt )
{
	var e = $$(elem);
	if (e != undefined) {
		e.set('html', txt);
	}
}

function mS3CElementSetVisibility( elem, vis )
{
	if (vis === false) {
		vis = 'none';
	} else if (vis === true) {
		vis = 'block';
	}
	
	var e = $$(elem);
	if (e != undefined) {
		e.setStyle('display', vis);
	}
}

function mS3CGetControl( index )
{
	return $('mS3CControl_'+index);
}

function mS3CDefaultSetControlVisibility( idx, vis )
{
	var ctrl = mS3CGetControl(idx);
	var disp = vis ? 'block' : 'none';
	ctrl.setStyle( 'display', disp );
}

///// AJAX
function mS3CDoAJAXRequest( query )
{
	mS3CCustomAJAXRequestStart(query);
	var req = new Request.JSON({
		secure: false, // Don't use JSON strict mode (will fail on "()" "new", or any other "special" character)
		url: mS3CQueryScriptUrl,
		onSuccess: mS3CParseAJAXResponse,
		onFailure: mS3CAJAXFailure,
		onError: mS3CAJAXError,
		onTimeout: mS3CAJAXTimeout,
		onCancel: mS3CAJAXCanceled
	});
	req.post({'query': JSON.encode(query)});
}

function mS3CParseAJAXResponse(responseJSON, responseText)
{
	mS3CHandleResponseProducts(responseJSON.ResultLayout, responseJSON.Product, responseJSON.Beginning, responseJSON.End, responseJSON.Total);
	mS3CHandleResponseFilters(responseJSON.Level, responseJSON.Filter);
	mS3CCustomAJAXRequestFinished();
}

function mS3CAJAXTimeout()
{
	mS3CCustomAJAXRequestError('Timeout', '');
	mS3CCustomAJAXRequestFinished();
}

function mS3CAJAXCanceled()
{
	mS3CCustomAJAXRequestError('Canceled', '');
	mS3CCustomAJAXRequestFinished();
}

function mS3CAJAXFailure(xhr)
{
	mS3CCustomAJAXRequestError(xhr.status, '');
	mS3CCustomAJAXRequestFinished();
}

function mS3CAJAXError(text, error)
{
	mS3CCustomAJAXRequestError(error, text);
	mS3CCustomAJAXRequestFinished();
}

//////// Checkbox
function mS3CGetCheckboxValue( idx, ctrl )
{
	var items = ctrl.getElements('input');
	var checkedItems = [];
	items.each(function(item) {
		if (item.getProperty('checked')) {
			checkedItems = checkedItems.concat(item);
		}
	});
	
	var values = [];
	checkedItems.each(function(item) {
		values = values.concat(item.getProperty('value'));
	});
	
	return values;
}

function mS3CSetCheckboxValue( idx, ctrl, value )
{
	if (value == undefined) {
		value = [];
	}
	var items = ctrl.getElements('input');
	items.each(function(item) {
		if (value.indexOf(item.getProperty('value')) >= 0) {
			item.setProperty('checked', true);
		} else {
			item.setProperty('checked', false);
		}
	});
}

function mS3CSetCheckboxDisabledValue( idx, ctrl, value )
{
	var newHtml = '';
	value.each(function(v) {
		var name = 'mS3CControl_'+idx+'_val_'+v;
		newHtml +=
			'<label class="disabled"><input type="checkbox" enabled="false" readonly="true" name="'+name+'" value="'+v+'"/>'+v+'</label>';
	});
	ctrl.innerHTML += newHtml;
}

function mS3CSetupCheckboxBounds( idx, ctrl, bounds, htmls )
{
	var newHtml = '';
	bounds.each(function(bound, i){
		var name = 'mS3CControl_'+idx+'_val_'+bound;
		newHtml +=
			'<label class="checked"><input type="checkbox" checked="checked" name="'+name+'" value="'+bound+'"/ onchange="mS3CControlChanged('+idx+');">'+htmls[i]+'</label>';
	});
	
	if (bounds.length <= 1) {
		mS3CControls[idx].hasData = false;
	} else {
		mS3CControls[idx].hasData = true;
	}
	ctrl.innerHTML = newHtml;
}

//////// Radio
function mS3CGetRadioValue( idx, ctrl )
{
	var items = ctrl.getElements('input');
	var checkedItem = undefined;
	for (var i = 0; i < items.length; ++i)
	{
		if ( items[i].getProperty('checked') == true)
		{
			checkedItem = items[i];
			break;
		}
	}
	
	if ( checkedItem == undefined )
	{
		return undefined;
	}
	
	return [checkedItem.getProperty('value')];
}

function mS3CSetRadioValue( idx, ctrl, value )
{
	value = mS3CFirstValue( value );
	
	var items = ctrl.getElements('input');
	for (var i = 0; i < items.length; ++i)
	{
		if ( items[i].getProperty('value') == value)
		{
			items[i].setProperty('checked', true);
			break;
		}
	}
}

function mS3CSetRadioDisabledValue( idx, ctrl, value )
{
	var newHtml = '';
	value.each(function(v) {
		var name = 'mS3CControl_'+idx+'_radiogroup';
		newHtml +=
			'<label class="disabled unselected"><input type="checkbox" radio="false" readonly="true" name="'+name+'" value="'+v+'"/>'+v+'</label>';
	});
	ctrl.innerHTML += newHtml;
}

function mS3CSetupRadioBounds( idx, ctrl, bounds, htmls )
{
	var newHtml = '';
	bounds.each(function(bound, i){
		var name = 'mS3CControl_'+idx+'_radiogroup';
		newHtml +=
			'<label class="unselected"><input type="radio" name="'+name+'" value="'+bound+'"/>'+htmls[i]+'</label>';
	});
	
	if (bounds.length <= 1) {
		mS3CControls[idx].hasData = false;
	} else {
		mS3CControls[idx].hasData = true;
	}
	
	ctrl.innerHTML = newHtml;
}

//////// Select
function mS3CGetSelectValue( idx, ctrl )
{
	var sel = ctrl.getElements('select');
	var val = sel.getSelected();
	if ( val != undefined ) {
		var selVal = val[0].getProperty('value')[0];
		if ( selVal == '__mS3C_Undefined_value__' ) {
			return undefined;
		}
		return [selVal];
	}
	return undefined;
}

function mS3CSetSelectValue( idx, ctrl, value )
{
	value = mS3CFirstValue(value);
	var sel = ctrl.getElements('select');
	var items = sel.getElements('option');
	
	if ( value == undefined ) {
		items[0][0].setProperty('selected', true);
	} else {
		items[0].each(function(item) {
			if (item.getProperty('value') == value) {
				item.setProperty('selected', true);
			}
		});
	}
}

function mS3CSetSelectDisabledValue( idx, ctrl, value )
{
	// TODO
}

function mS3CSetupSelectBounds( idx, ctrl, bounds, htmls )
{
	var sel = ctrl.getElements('select')[0];
	sel.setProperty('onchange','mS3CControlChanged('+idx+');');
	sel.options.length = 0;
	sel.options[0] = new Option('Bitte wählen', '__mS3C_Undefined_value__');
	var i = 1;
	bounds.each(function(bound) {
		sel.options[i++] = new Option(bound, bound);
	});
	
	if (bounds.length <= 1) {
		mS3CControls[idx].hasData = false;
	} else {
		mS3CControls[idx].hasData = true;
	}
}


//////// SESSION
function mS3CGetSessionValue( idx, ctrl )
{
	
	var value = ctrl.get('value');
	if ( value != "") {
		return [value];
	}
	return undefined;
}

//////// Text
function mS3CGetTextValue( idx, ctrl )
{
	var txt = ctrl.getChildren('input')[0];
	var value = txt.get('value');
	if ( value != "") {
		return [value];
	}
	return undefined;
}

function mS3CSetTextValue( idx, ctrl, value)
{
	var txt = ctrl.getChildren('input')[0];
	txt.set('value', value);
}

function mS3CSetupTextBounds( idx, ctrl, bounds, htmls )
{
	var txt = ctrl.getChildren('input')[0];
	if (bounds.length < 1)
	{
		txt.set('disabled', true);
		txt.set('value', trans.emptyselect);
	}
	else
	{
		txt.set('disabled', false);
		txt.set('value','');
	}
	
	if (bounds.length <= 1) {
		mS3CControls[idx].hasData = false;
	} else {
		mS3CControls[idx].hasData = true;
	}
}

//////// TextFields
function mS3CGetTextFieldsValue( idx, ctrl )
{
	var field1 = ctrl.getChildren('.field1')[0];
	var field2 = ctrl.getChildren('.field2')[0];
	var value1 = field1.get('value');
	var value2 = field2.get('value');
	if ( value1 != "" && value2 != "") {
		return [value1, value2];
	}
	return undefined;
}

function mS3CSetTextFieldsValue( idx, ctrl, value)
{
	var field1 = ctrl.getChildren('.field1')[0];
	var field2 = ctrl.getChildren('.field2')[0];
	field1.set('value', value[0]);
	field2.val('value', value[1]);
}

function mS3CSetupTextFieldsBounds( idx, ctrl, bounds, htmls )
{
	var field1 = ctrl.getChildren('.field1')[0];
	var field2 = ctrl.getChildren('.field2')[0];
	
	if (bounds.length < 1)
	{
		field1.set('disabled', true);
		field1.set('value', trans.emptyselect);
		field2.set('disabled', true);
		field2.set('value', trans.emptyselect);
	}
	else
	{
		field1.set('disabled', false);
		field1.set('value', '');
		field2.set('disabled', false);
		field2.set('value', '');
	}
	
	if (bounds.length <= 1) {
		mS3CControls[idx].hasData = false;
	} else {
		mS3CControls[idx].hasData = true;
	}
}

function TextFieldsChange(idx, ctrl)
{
	var field1 = $(ctrl).getChildren('.field1')[0];
	var field2 = $(ctrl).getChildren('.field2')[0];
	var value1 = field1.get('value');
	var value2 = field2.get('value');
	if ( value1 != "" && value2 != "") {
		mS3CControlChanged(idx);
	}
	if ( value1 == "" && value2 == "") {
		mS3CControlChanged(idx);
	}
}

//////// Slider
function mS3CGetSliderValue( idx, ctrl )
{
	// No default implementation
	return undefined;
}

function mS3CSetSliderValue( idx, ctrl, value )
{
	// No default implementation
}

function mS3CSetSliderDisabledValue( idx, ctrl, value )
{
	// No default implementation
}

function mS3CSetupSliderBounds( idx, ctrl, bounds, htmls )
{
	// No default implementation
}

function mS3CSetupFromToSliderBounds( idx, ctrl, bounds, htmls )
{
	// No default implementation
}

function mS3CSetFromToSliderDisabledValue( idx, ctrl, value )
{
	// No default implementation
}

//////// Range
function mS3CGetRangeValue( idx, ctrl )
{
	// No default implementation
	return undefined;
}

function mS3CSetRangeValue( idx, ctrl, value )
{
	// No default implementation
}

function mS3CSetRangeDisabledValue( idx, ctrl, value )
{
	// No default implementation
}

function mS3CSetupRangeBounds( idx, ctrl, bounds, htmls )
{
	// No default implementation
}

function mS3CSetupFromToRangeBounds( idx, ctrl, bounds, htmls )
{
	// No default implementation
}

function mS3CSetFromToRangeDisabledValue( idx, ctrl, value )
{
	// No default implementation
}

function mS3CKeyHandler(ctrl)
{
	$(ctrl).addEvent('keydown', 
		function(ev) {
			if ( ev.key == 'enter' ) {
				ev.target.blur();
			}
		}
	);
}

function mS3CKeyHandler2Fields(ctrl)
{
	var field1 = $(ctrl).getChildren('.field1')[0];
	var field2 = $(ctrl).getChildren('.field2')[0];
	field1.addEvent('keydown', 
		function(ev) {
			if(ev.key == 'enter') {
				jQuery(this).blur();
			}
		});
	field2.addEvent('keydown', 
		function(ev) {
			if(ev.key == 'enter') {
				jQuery(this).blur();
			}
		});
}

