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

/***
 * STATIC INFO
 ***/
/**
 * Registry of all user input controls.
 * Contains objects with properties:
 *	- id: control id (for getElementById)
 *	- feature: name of feature affected
 *	- ctrlType: type of input control (radio, select, checkbox, slider, ...)
 *	- compType: type for comparision (equals, between, intersect, ...)
 *	- show: if filter should be shown (depending on mS3CFilterVisibilityType)
 *	- optional addtional (type dependent)
 */
var mS3CControls = [];
var languageId;
var marketId;
var menuId;
var trans;
var mS3CResults = [];
var mS3CQueryScriptUrl;
var mS3CPageId;
var mS3CStopChangeTrigger = false;
var mS3CLastUpdateCtrl = -1;
var resultTypes='';


/***
 * FILTER UPDATE METHOD
 ***/
/**
 * Never updates possible values for filters
 */
var MS3C_UPDATE_TYPE_NO_CHANGE = 0;
/**
 * Filters are hierarchical, so that filters with
 * lower id will update all filters with higher id,
 * possibly resetting current selection
 */
var MS3C_UPDATE_TYPE_HIERARCHICAL = 1;
/**
 * All filters will update after every change,
 * so that all possibile values are displayed.
 */
var MS3C_UPDATE_TYPE_ALL = 2;
/**
 * Displays only the values that are present
 * in the current product selection, except for
 * the last changed filter, which keeps its values
 * (until the next update)
 */
var MS3C_UPDATE_TYPE_FROM_PRODUCTS = 3;

/***
 * FILTER DISPLAY STRATEGY
 ***/
var MS3C_SHOW_FILTER_ALWAYS = 0;
var MS3C_SHOW_FILTER_INITIAL_NONEMPTY = 1;
var MS3C_SHOW_FILTER_NONEMPTY = 2;

var MS3C_SHOW_FILTER_VALUES_AVAIL = 1;
var MS3C_SHOW_FILTER_VALUES_DISABLED = 2;


/***
 * RESULTS PAGIN STRATEGY
 ***/
var MS3C_PAGING_NONE = 0;
var MS3C_PAGING_PAGE = 1;
var MS3C_PAGING_MORE = 2;

/***
 * CUSTOM SETUP
 ***/
var mS3CFilterUpdateType = MS3C_UPDATE_TYPE_ALL;
var mS3CFilterVisibilityType = MS3C_SHOW_FILTER_ALWAYS;
var mS3CPagingType = MS3C_PAGING_PAGE;
var mS3CFilterValueVisibilityType = MS3C_SHOW_FILTER_VALUES_AVAIL;

var mS3CShowFilterCounts = false;

/**
 * Registers a control
 */
function mS3CRegisterControl( index, feature, ctrlType, compType, isMulti )
{
	mS3CControls[index] = {
		'id':'mS3C_control_'+index, 
		'feature':feature, 
		'ctrlType':ctrlType, 
		'compType':compType,
		'isMultiFeature':isMulti,
		'show':true,
		'hasData':false
	};	
}

function mS3CInitForm( market, lang, menu, urlArgs, pageId, tr, itemsPerPage, resTypes,initFilters)
{
	marketId = market;
	languageId = lang;
	menuId = menu;
	trans = tr;
	mS3CPageId = pageId;
	resultTypes=resTypes.split(';');
	if (itemsPerPage == 0) {
		mS3CPagingStep = null;
	} else {
		mS3CPagingStep = itemsPerPage;
	}
	
	mS3CQueryScriptUrl = window.location.href;
	if (mS3CQueryScriptUrl.indexOf('#') > 0)
		mS3CQueryScriptUrl = mS3CQueryScriptUrl.substr(0,mS3CQueryScriptUrl.indexOf('#'));
	if (mS3CQueryScriptUrl.indexOf('?') > 0)
		mS3CQueryScriptUrl += '&';
	else 
		mS3CQueryScriptUrl += '?';
	
	mS3CQueryScriptUrl += urlArgs;
	
	if ( typeof(mS3CCustomInit) == 'function' ) {
		mS3CCustomInit( );
	}
	
	///////// DEBUG
	/*
	for (var i = 0; i < mS3CControls.length; ++i) {
		for (var j = 0; j < mS3CControls[i].feature.length; ++j) {
			mS3CResults[mS3CResults.length] = mS3CControls[i].feature[j];
		}
	}
	*/
   
   mS3CSetInheritedSelection();
   
	if(initFilters=='1'){
		mS3CInitializeFilterValues();
		mS3CInitializeResults();
	}else{
		mS3CResetForm();
	}
}

function mS3CRegisterResultFeature( feature )
{
	mS3CResults[mS3CResults.length] = feature;
}

function mS3CGetBasicQuery()
{
	var feats = [];
	var multiFeature = [];
	for (var i = 0; i < mS3CControls.length; ++i) {
		if (mS3CNeedsFilterValues(mS3CControls[i].ctrlType)) {
			for (var j = 0; j < mS3CControls[i].feature.length; ++j) {
				feats[feats.length] = mS3CControls[i].feature[j];
				multiFeature[multiFeature.length] = mS3CControls[i].isMultiFeature;
			}
		}
	}
	
	var step = (mS3CPagingType == MS3C_PAGING_PAGE) ? mS3CPagingStep : -1;
	
	var query = {
		'Menu': [menuId],
		'Results': mS3CResults,
		'Filter': feats,
		'Start': 0,
		'Limit': step,
		'Level': -1,
		'Market': marketId,
		'Language': languageId,
		'ResultTypes':resultTypes
	};
	
	switch (mS3CFilterUpdateType) {
	case MS3C_UPDATE_TYPE_NO_CHANGE:
		break;
	case MS3C_UPDATE_TYPE_FROM_PRODUCTS:
		query['UpdateType'] = 'fromproducts';
		break;
	case MS3C_UPDATE_TYPE_ALL:
		query['UpdateType'] = 'all';
		break;
	case MS3C_UPDATE_TYPE_HIERARCHICAL:
	default:
		query['UpdateType'] = 'hierarchy';
		query['Level'] = mS3CLastUpdateCtrl;
	}
	return query;
}

function mS3CResetForm()
{
	mS3CFormReset = true;
	mS3CLastUpdateCtrl = -1;
	if (mS3CFilterVisibilityType != MS3C_SHOW_FILTER_ALWAYS) {
		mS3CSetFilterVisibility(false);
	}
	var query = mS3CGetBasicQuery();
	
	for (var i = 0; i < mS3CControls.length; ++i) {
		mS3CControls[i].selection = undefined;
	}
	
	var sel = mS3CGetInheritedSelection();
	// Set SESSION Selection
	//mS3CGetSessionSelection();
	//var sel = mS3CGetControlSelection();
	query['Selection'] = sel;
	
	mS3CDoAJAXRequest( query );
}

function mS3CSubmitFiltered(link)
{
	if (document.forms['mS3CFilterForm'] != undefined) {
		var sel=mS3CGetControlSelection();
		if (sel.length > 0) {
			//send post form
			sel = mS3CJSONEncode(sel);
			document.forms["mS3CFilterForm"].mS3Ction=link.href;
			document.forms['mS3CFilterForm'].elements['mS3CInhFilters'].value=sel;
			document.forms['mS3CFilterForm'].submit();
			return;
		}
	}
	// Just go there
	document.location.href = link.href;
}

function mS3CControlChanged( idx )
{
	if (mS3CStopChangeTrigger) return;
	
	mS3CStopChangeTrigger = true;
	
	mS3CLastUpdateCtrl = idx;
	var query = mS3CGetBasicQuery();
	
	switch (mS3CFilterUpdateType) {
	case MS3C_UPDATE_TYPE_NO_CHANGE:
		break;
	case MS3C_UPDATE_TYPE_ALL:
		mS3CControlUpdateAll( idx );
		break;
	case MS3C_UPDATE_TYPE_FROM_PRODUCTS:
		mS3CControlUpdateFromProducts( idx );
		break;
	case MS3C_UPDATE_TYPE_HIERARCHICAL:
	default:
		mS3CControlUpdateHierarchical( idx );
		break;
	}
	
	var sel = mS3CGetControlSelection();
	query['Selection'] = sel;
	
	mS3CDoAJAXRequest( query );
}

function mS3CGetControlSelection()
{
	var selection = [];
	var ct = 0;
	for (var i = 0; i < mS3CControls.length; ++i) {
		if ( mS3CControls[i].selection != undefined )
		{
			selection[ct++] = {
				'Feature': mS3CControls[i].feature,
				'Value': mS3CControls[i].selection,
				'Type': mS3CControls[i].compType,
				'IsMultiFeature': mS3CControls[i].isMultiFeature
			};
		}
	}
	
	return selection;
}

function mS3CGetSessionSelection()
{
	var selection = [];
	var ct = 0;
	
	for (var i = 0; i < mS3CControls.length; ++i) {
		if ( mS3CControls[i].ctrlType == 'SESSION' )
		{
			mS3CControls[i].selection = mS3CGetControlValue(i);
		}
	}

	return selection;
}

function mS3CGetInheritedSelection()
{
	if(document.forms['mS3CFilterForm'] != undefined && document.forms['mS3CFilterForm'].elements['mS3CInhFilters'] != undefined){
		var sel = document.forms['mS3CFilterForm'].elements['mS3CInhFilters'].value;
		sel = mS3CJSONDecode(sel);
		return sel;
	}
	return undefined;
}

function mS3CSetInheritedSelection()
{
	var sel = mS3CGetInheritedSelection();
	if (sel != undefined) {
		for (var i = 0; i < sel.length; ++i) {
			for (var j = 0; j < mS3CControls.length; ++j) {
				if (in_array(sel[i].Feature[0], mS3CControls[j].feature)) {
					mS3CControls[j].selection = sel[i].Value;
					break;
				}
			}
		}
	}
}

function mS3CControlUpdateAll( idx )
{
	mS3CControls[idx].selection = mS3CGetControlValue(idx);
}

function mS3CControlUpdateFromProducts( idx )
{
	mS3CControls[idx].selection = mS3CGetControlValue(idx);
}

function mS3CControlUpdateHierarchical( idx )
{
	for (var i = 0; i <= idx; ++i) {
		var value = mS3CGetControlValue(i);
		mS3CControls[i].selection = value;
	}
	
	for (i = idx+1; i < mS3CControls.length; ++i) {
		mS3CControls[i].selection = undefined;
	}
}

/**
 * Gets the currently selected value for the given control index.
 * Return type depends on control type, usualy string or int of selected value,
 * or array thereof for mutli-select controls.
 * @return The contols value, or undefined if no such control
 */
function mS3CGetControlValue( idx )
{
	var ctrl = mS3CGetControl( idx );
	if ( ctrl == undefined ) {
		return undefined;
	}
	switch ( mS3CControls[idx].ctrlType )
	{
		case 'null':
			return undefined;
		case 'Radio':
			if ( typeof(mS3CCustomGetRadioValue) == 'function' )
				return mS3CCustomGetRadioValue( idx, ctrl );
			else
				return mS3CGetRadioValue( idx, ctrl );
		case 'Checkbox':
			if ( typeof(mS3CCustomGetCheckboxValue) == 'function' )
				return mS3CCustomGetCheckboxValue( idx, ctrl );
			else
				return mS3CGetCheckboxValue( idx, ctrl );
		case 'Slider':
			if ( typeof(mS3CCustomGetSliderValue) == 'function' )
				return mS3CCustomGetSliderValue( idx, ctrl );
			else
				return mS3CGetSliderValue( idx, ctrl );
		case 'Range':
			if ( typeof(mS3CCustomGetRangeValue) == 'function' )
				return mS3CCustomGetRangeValue( idx, ctrl );
			else
				return mS3CGetRangeValue( idx, ctrl );
		case 'Select':
			if ( typeof(mS3CCustomGetSelectValue) == 'function' )
				return mS3CCustomGetSelectValue( idx, ctrl );
			else
				return mS3CGetSelectValue( idx, ctrl );
		case 'Text':
		if ( typeof(mS3CCustomGetTextValue) == 'function' )
				return mS3CCustomGetTextValue( idx, ctrl );
			else
				return mS3CGetTextValue( idx, ctrl );
		case 'TextFields':
		if ( typeof(mS3CCustomGetTextFieldsValue) == 'function' )
				return mS3CCustomGetTextFieldsValue( idx, ctrl );
			else
				return mS3CGetTextFieldsValue( idx, ctrl );
		case 'SESSION':
				return mS3CGetSessionValue( idx, ctrl );
		case 'Custom':
				return mS3CGetCustomFilterValue( idx, ctrl );
		default:
			return undefined;
	}
}

/**
 * Sets the currently selected value of a control.
 */
function mS3CSetControlValue( idx, value )
{
	var ctrl = mS3CGetControl( idx );
	if ( ctrl == undefined ) {
		return;
	}
	switch ( mS3CControls[idx].ctrlType )
	{
		case 'null':
			return;
		case 'Radio':
			if ( typeof(mS3CCustomSetRadioValue) == 'function' )
				mS3CCustomSetRadioValue( idx, ctrl, value );
			else
				mS3CSetRadioValue( idx, ctrl, value );
			break;
		case 'Checkbox':
			if ( typeof(mS3CCustomSetCheckboxValue) == 'function' )
				mS3CCustomSetCheckboxValue( idx, ctrl, value );
			else
				mS3CSetCheckboxValue( idx, ctrl, value );
			break;
		case 'Slider':
			if ( typeof(mS3CCustomSetSliderValue) == 'function' )
				mS3CCustomSetSliderValue( idx, ctrl, value );
			else
				mS3CSetSliderValue( idx, ctrl, value );
			break;
		case 'Range':
			if ( typeof(mS3CCustomSetRangeValue) == 'function' )
				mS3CCustomSetRangeValue( idx, ctrl, value );
			else
				mS3CSetRangeValue( idx, ctrl, value );
			break;
		case 'Select':
			if ( typeof(mS3CCustomSetSelectValue) == 'function' )
				mS3CCustomSetSelectValue( idx, ctrl, value );
			else
				mS3CSetSelectValue( idx, ctrl, value );
			break;
		case 'Text':
			if ( typeof(mS3CCustomSetTextValue) == 'function' )
				mS3CCustomSetTextValue( idx, ctrl, value );
			else
				mS3CSetTextValue( idx, ctrl, value );
			break;
		case 'TextFields':
			if ( typeof(mS3CCustomSetTextFieldsValue) == 'function' )
				mS3CCustomSetTextFieldsValue( idx, ctrl, value );
			else
				mS3CSetTextFieldsValue( idx, ctrl, value );
			break;
		case 'Custom':
				mS3CSetCustomFilterValue( idx, ctrl, value );
			break;
		default:
			break;
	}
}

function mS3CControlSetDisabledValues( idx, value )
{
	var ctrl = mS3CGetControl( idx );
	if ( ctrl == undefined ) {
		return;
	}
	switch ( mS3CControls[idx].ctrlType )
	{
		case 'null':
			return;
		case 'Radio':
			if ( typeof(mS3CCustomSetRadioDisabledValue) == 'function' )
				mS3CCustomSetRadioDisabledValue( idx, ctrl, value );
			else
				mS3CSetRadioDisabledValue( idx, ctrl, value );
			break;
		case 'Checkbox':
			if ( typeof(mS3CCustomSetCheckboxDisabledValue) == 'function' )
				mS3CCustomSetCheckboxDisabledValue( idx, ctrl, value );
			else
				mS3CSetCheckboxDisabledValue( idx, ctrl, value );
			break;
		case 'Slider':
		if ( typeof(mS3CCustomSetSliderDisabledValue) == 'function' )
				mS3CCustomSetSliderDisabledValue( idx, ctrl, value );
			else
				mS3CSetSliderDisabledValue( idx, ctrl, value );
			break;
		case 'Range':
		if ( typeof(mS3CCustomSetRangeDisabledValue) == 'function' )
				mS3CCustomSetRangeDisabledValue( idx, ctrl, value );
			else
				mS3CSetRangeDisabledValue( idx, ctrl, value );
			break;
		case 'Select':
			if ( typeof(mS3CCustomSetSelectDisabledValue) == 'function' )
				mS3CCustomSetSelectDisabledValue( idx, ctrl, value );
			else
				mS3CSetSelectDisabledValue( idx, ctrl, value );
			break;
		case 'Custom':
				mS3CSetCustomFilterDisabledValue( idx, ctrl, value );
			break;
		default:
			break;
	}
}

/**
 * Sets the possible value a user can select for a control
 */
function mS3CSetupControlSelBounds( idx, bounds, boundsNr, htmls, counts )
{
	var ctrl = mS3CGetControl( idx );
	if ( ctrl == undefined ) {
		return;
	}
	switch ( mS3CControls[idx].ctrlType )
	{
		case 'null':
			return;
		case 'Radio':
			if ( typeof(mS3CCustomSetupRadioBounds) == 'function' )
				mS3CCustomSetupRadioBounds( idx, ctrl, bounds, htmls, counts );
			else
				mS3CSetupRadioBounds( idx, ctrl, bounds, htmls, counts );
			break;
		case 'Checkbox':
			if ( typeof(mS3CCustomSetupCheckboxBounds) == 'function' )
				mS3CCustomSetupCheckboxBounds( idx, ctrl, bounds, htmls, counts );
			else
				mS3CSetupCheckboxBounds( idx, ctrl, bounds, htmls, counts );
			break;
		case 'Slider':
			if ( typeof(mS3CCustomSetupSliderBounds) == 'function' )
				mS3CCustomSetupSliderBounds( idx, ctrl, boundsNr, htmls, counts );
			else
				mS3CSetupSliderBounds( idx, ctrl, boundsNr, htmls, counts );
			break;
		case 'Range':
			if ( typeof(mS3CCustomSetupRangeBounds) == 'function' )
				mS3CCustomSetupRangeBounds( idx, ctrl, boundsNr, htmls, counts );
			else
				mS3CSetupRangeBounds( idx, ctrl, boundsNr, htmls, counts );
			break;
		case 'Select':
			if ( typeof(mS3CCustomSetupSelectBounds) == 'function' )
				mS3CCustomSetupSelectBounds( idx, ctrl, bounds, htmls, counts );
			else
				mS3CSetupSelectBounds( idx, ctrl, bounds, htmls, counts );
			break;
		case 'Text':
			if ( typeof(mS3CCustomSetupTextBounds) == 'function' )
				mS3CCustomSetupTextBounds( idx, ctrl, bounds, htmls, counts );
			else
				mS3CSetupTextBounds( idx, ctrl, bounds, htmls, counts );
			break;
		case 'TextFields':
			if ( typeof(mS3CCustomSetupTextFieldsBounds) == 'function' )
				mS3CCustomSetupTextFieldsBounds( idx, ctrl, bounds, htmls, counts );
			else
				mS3CSetupTextFieldsBounds( idx, ctrl, bounds, htmls, counts );
			break;
		case 'Custom':
				mS3CSetupCustomFilterBounds( idx, ctrl, bounds, htmls, counts );
			break;
		default:
			break;
	}
}

function mS3CHandleResponseFilters(level, filters)
{
	if (!filters) filters = [];
	mS3CCollectMultiFeatureControls(filters);
	
	switch (mS3CFilterUpdateType) {
	case MS3C_UPDATE_TYPE_ALL:
		mS3CHandleResponseFilterAll(level, filters);
		break;
	case MS3C_UPDATE_TYPE_HIERARCHICAL:
	default:
		mS3CHandleResponseFilterHierarchical(level, filters);
		break;
	case MS3C_UPDATE_TYPE_FROM_PRODUCTS:
		mS3CHandleResponseFilterFromProducts(level, filters);
		break;
	}
	
	mS3CCheckFilterVisibility();
	mS3CCheckFilterValueVisibility(filters);
	
	mS3CStopChangeTrigger = false;
	mS3CFormReset = false;
}

function mS3CHandleResponseFilterHierarchical(level, filters)
{
	var undef = [];
	if ( level < 0 ) level = -1;
	for (var i = level+1; i < mS3CControls.length; ++i) {
		undef.push(i);
		for (var j = 0; j < filters.length; ++j) {
			if (filters[j] == null) {
				continue;
			}
			if (mS3CInArray(filters[j].Id, mS3CControls[i].feature) >= 0) {
				mS3CSetupControlSelBounds(i, filters[j].Values, filters[j].Numbers, filters[j].HTMLs, filters[j].Counts);
				undef.pop();
				break;
			}
		}
	}
	
	// undef contains all control idxs that had no result
	for (i = 0; i < undef.length; ++i) {
		mS3CSetupControlSelBounds(undef[i], [], [], [], []);
	}
}

function mS3CHandleResponseFilterAll(level, filters)
{
	var undef = [];
	for (i= 0; i < mS3CControls.length; ++i) {
		undef.push(i);
		for (j = 0; j < filters.length; ++j) {
			if (filters[j] == null) {
				continue;
			}
			if (mS3CInArray(filters[j].Id, mS3CControls[i].feature) >= 0) {
				mS3CSetupControlSelBounds(i, filters[j].Values, filters[j].Numbers, filters[j].HTMLs, filters[j].Counts);
				if (mS3CControls[i].selection != undefined) {
					mS3CSetControlValue(i, mS3CControls[i].selection);
				}
				undef.pop();
				break;
			}
		}
	}
	
	// undef contains all control idxs that had no result
	for (i = 0; i < undef.length; ++i) {
		mS3CSetupControlSelBounds(undef[i], [], [], [], []);
	}
}

function mS3CHandleResponseFilterFromProducts(level, filters)
{
	var undef = [];
	for (i=0; i < mS3CControls.length; ++i) {
		if ( i == mS3CLastUpdateCtrl ) {
			mS3CSetControlValue(i, mS3CControls[i].selection);
			if (mS3CControls[i].selection != undefined) {
				continue;
			}
		}
		undef.push(i);
		for (j = 0; j < filters.length; ++j) {
			if (filters[j] == null) {
				continue;
			}
			if (mS3CInArray(filters[j].Id, mS3CControls[i].feature) >= 0) {
				mS3CSetupControlSelBounds(i, filters[j].Values, filters[j].Numbers, filters[j].HTMLs, filters[j].Counts);
				if (mS3CControls[i].selection != undefined) {
					mS3CSetControlValue(i, mS3CControls[i].selection);
				}
				undef.pop();
				break;
			}
		}
	}
	
	for (i = 0; i < undef.length; ++i) {
		mS3CSetupControlSelBounds(undef[i],[], [], [], []);
	}
}

function mS3CCollectMultiFeatureControls( responseVals )
{
	for (var i = 0; i < mS3CControls.length; ++i) {
		if ( mS3CControls[i].feature.length > 1 ) {
			var collectedVals = [];
			var collectedValsNr = [];
			var collectedHtmls = [];
			var collectedCount = [];
			var idxs = [];
			for (var j = 0; j < responseVals.length; ++j) {
				if (responseVals[j] == null) {
					continue;
				}
				// Make array of all values that belong to all features of that control
				if (mS3CInArray(responseVals[j].Id, mS3CControls[i].feature) >= 0) {
					collectedVals.push( responseVals[j].Values );
					collectedValsNr.push( responseVals[j].Numbers );
					collectedHtmls.push( responseVals[j].HTMLs );
					collectedCount.push( responseVals[j].Counts );
					idxs.push(j);
					if (collectedVals.length >= mS3CControls[i].feature.length) {
						break;
					}
				}
			}
			
			// Replace single feature values by collected values array
			for (var l = 0; l < idxs.length; ++l) {
				responseVals[idxs[l]].Values = collectedVals;
				responseVals[idxs[l]].Numbers = collectedValsNr;
				responseVals[idxs[l]].HTMLs = collectedHtmls;
				responseVals[idxs[l]].Counts = collectedCount;
			}
		}
	}
}

function mS3CSetFilterVisibility(visible) 
{
	for (var i = 0; i < mS3CControls.length; ++i) 
	{
		mS3CSetControlVisibility(i, visible);
	}
}

function mS3CCheckFilterVisibility()
{
	if (mS3CFilterVisibilityType == MS3C_SHOW_FILTER_ALWAYS) {
		return;
	}
	
	for (var i = 0; i < mS3CControls.length; ++i) 
	{
		var vis;
		if (mS3CFilterVisibilityType == MS3C_SHOW_FILTER_INITIAL_NONEMPTY) {
			if (mS3CFormReset)
				mS3CControls[i].show = mS3CControls[i].hasData;
			vis = mS3CControls[i].show;
		}
		else if (mS3CFilterVisibilityType == MS3C_SHOW_FILTER_NONEMPTY)
			vis = mS3CControls[i].hasData;
		else
			vis = true;
		
		mS3CSetControlVisibility(i, vis);
	}
}

function mS3CCheckFilterValueVisibility(filters)
{
	if (mS3CFilterValueVisibilityType == MS3C_SHOW_FILTER_VALUES_AVAIL) {
		return;
	}
	
	var undef = [];
	for (var i=0; i < mS3CControls.length; ++i) {
		// Skip last changed filter (did not change)
		if (i == mS3CLastUpdateCtrl) {
			if (mS3CControls[i].selection != undefined) {
				continue;
			}
		}
		
		undef.push(i);
		for (var j = 0; j < filters.length; ++j) {
			if (filters[j] == null) {
				continue;
			}
			if (mS3CInArray(filters[j].Id, mS3CControls[i].feature) >= 0) {
				undef.pop();
				if (mS3CFormReset) {
					mS3CControls[i].totalValues = filters[j].Values.slice(0);
				} else {
					var diff = [];
					
					// Array diff:
					for (idx = 0; idx < mS3CControls[i].totalValues.length; ++idx) {
						if (mS3CInArray(mS3CControls[i].totalValues[idx], filters[j].Values) < 0) {
							diff.push(mS3CControls[i].totalValues[idx]);
						}
					}

					mS3CControlSetDisabledValues(i,diff);
				}
				break;
			}
		}
	}
	
	for (i = 0; i < undef.length; ++i) {
		if (mS3CFormReset) {
			mS3CControls[undef[i]].totalValues = [];
		} else {
			mS3CControlSetDisabledValues(undef[i],mS3CControls[undef[i]].totalValues);
		}
	}
}

function mS3CSetControlVisibility(idx, vis)
{
	if (typeof(mS3CCustomSetControlVisibility) == 'function')
		mS3CCustomSetControlVisibility(idx, vis);
	else
		mS3CDefaultSetControlVisibility(idx, vis);
}

function mS3CHandleResponseProducts(layout, prods, start, end, total)
{
	if (typeof(mS3CCustomHandleResponseProducts) == 'function') {
		mS3CCustomHandleResponseProducts(layout, prods);
	} else {
		mS3CElementSetText('#mS3CResultPanel', layout);
	}
	
	start = parseInt(start);
	end = parseInt(end);
	total = parseInt(total);
	
	switch (mS3CPagingType) {
	case MS3C_PAGING_NONE:
		break;
	case MS3C_PAGING_PAGE:
		mS3CHandlePagingPage(start, end, total);
		break;
	case MS3C_PAGING_MORE:
		mS3CHandlePagingMore(start, end, total);
		break;
	}
}

function mS3CHandlePagingPage(start, end, total)
{
	mS3CPagingStart = start;
	mS3CPagingTotal = total;
}

function mS3CHandlePagingMore(start, end, total)
{
	mS3CPagingTotal = total;
	mS3CPagingStart = mS3CPagingEnd = 0;
	mS3CPageMore();
}

////// Paging

var MS3C_PAGE_POS1 = 0;
var MS3C_PAGE_UP = 1;
var MS3C_PAGE_DOWN = 2;
var MS3C_PAGE_END = 3;
var MS3C_PAGE_MORE = 4;

var mS3CPagingStart = 0;
var mS3CPagingEnd = 0;
var mS3CPagingTotal = 0;
var mS3CPagingStep = 0;

function mS3CPageMove(dir)
{
	var start;
	switch (dir)
	{
	case MS3C_PAGE_POS1:
		start = 1;
		break;
	case MS3C_PAGE_UP:
		start = mS3CPagingStart-mS3CPagingStep;
		break;
	case MS3C_PAGE_DOWN:
		start = mS3CPagingStart+mS3CPagingStep;
		break;
	case MS3C_PAGE_END:
		start = mS3CPagingTotal-(mS3CPagingTotal%mS3CPagingStep)+1;
		if (start == mS3CPagingTotal+1) {
			start = mS3CPagingTotal-mS3CPagingStep+1;
		}
		break;
	case MS3C_PAGE_MORE:
		mS3CPageMore();
		return;
	default:
		return;
	}
	
	if (start > 0 && start <= mS3CPagingTotal)
	{
		var query = mS3CGetBasicQuery();
		query['Selection'] = mS3CGetControlSelection();
		// start and stop are 0-based
		query['Start'] = start-1;
		query['Stop'] = start+mS3CPagingStep-1;
		
		mS3CDoAJAXRequest( query );
	}
}

function mS3CPageMore()
{
	// Move forward one page
	mS3CPagingEnd += mS3CPagingStep;
	if (mS3CPagingEnd > mS3CPagingTotal) {
		mS3CPagingEnd = mS3CPagingTotal;
	}
	
	mS3CCustomHandlePaging(mS3CPagingEnd);
	
	// Update "more prods" link
	var diff = mS3CPagingTotal-mS3CPagingEnd;
	if (diff > mS3CPagingStep) {
		diff = mS3CPagingStep;
	}
	mS3CElementSetText('#mS3CPageMoreCount', diff);
	if (mS3CPagingEnd < mS3CPagingTotal) {
		mS3CElementSetVisibility('#mS3CPageMore', true);
	} else {
		mS3CElementSetVisibility('#mS3CPageMore', false);
	}
}

function mS3CCleanupValues(vals, htmls, makeUnique)
{
	vals = vals.slice(0);
	htmls = htmls.slice(0);
	for (var i = 0; i < vals.length; ++i) {
		vals[i] = parseFloat(vals[i]);
	}
	// INSERTION SORT FROM http://en.wikipedia.org/wiki/Insertion_sort
	for (i = 1; i < vals.length-1; ++i)
	{
		var v = vals[i];
		var h = htmls[i];
		var hole = i;
		while (hole > 0 && vals[hole-1] > v)
		{
			vals[hole] = vals[hole-1];
			htmls[hole] = htmls[hole-1];
			hole--;
		}

		vals[hole] = v;
		htmls[hole] = h;
	}

	if ( makeUnique == undefined ) {
		makeUnique = true;
	}
	if ( makeUnique )
	{
		// Make unique
		var valsOut = [];
		var htmlsOut = [];
		for (i = 0; i < vals.length; ++i) {
			if (mS3CInArray(vals[i], valsOut) < 0) {
				valsOut.push(vals[i]);
				htmlsOut.push(htmls[i]);
			}
		}
		return [valsOut, htmlsOut];
	} else {
		return [vals, htmls];
	}

	
}

function mS3CAdjustRangeValues(values, curMin, curMax)
{
	var value1;
	var value2;
	for (var k = 0; k < values.length; ++k) {
		
		if (values[k] >= curMin && value1 == undefined)
		{
			if (values[k] > curMin)
			{
				value1 = k - 1;
			}
			else
			{
				value1 = k;
			}
		}
		if (values[k] >= curMax && value2 == undefined)
		{
			value2 = k;
		}
	}
	if (value1 == undefined)
	{
		value1 = 0;
	}
	if (value2 == undefined)
	{
		value2 = k - 1;
	}
	
	return [value1,value2];
}

function mS3CNeedsFilterValues(ctrlType)
{
	switch (ctrlType) {
		case 'Text':
		case 'TextFields':
		case 'SESSION':
			return false;
		default:
			return true;
	}
}

function in_array(item,arr) 
{
	for(p=0;p<arr.length;p++) if (item == arr[p]) return true;
	return false;
}	

