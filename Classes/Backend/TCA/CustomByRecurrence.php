<?php


/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */
  
namespace TYPO3\CMS\Cal\Backend\TCA;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;

/**
 * Backend class for user-defined TCA type used in recurring event setup.
 *
 * @author Mario Matzulla <mario@matzullas.de>
 * @package TYPO3
 * @subpackage cal
 */
class CustomByRecurrence extends AbstractFormElement {
  
  protected $defaultFieldWizard = [
      'localizationStateSelector' => [
          'renderType' => 'localizationStateSelector',
      ],
      'otherLanguageContent' => [
          'renderType' => 'otherLanguageContent',
          'after' => [
              'localizationStateSelector'
          ],
      ],
      'defaultLanguageDifferences' => [
          'renderType' => 'defaultLanguageDifferences',
          'after' => [
              'otherLanguageContent',
          ],
      ],
  ];
  
  private $weekdays;
  
  private $counts;
  
  private $garbageIcon;
  
  private $newIcon;
  
  private function init() {
    $startDay = $this->getWeekStartDay( );
    $this->weekdays = $this->getWeekDaysArray( $startDay );
    $this->counts = $this->getCountsArray();
    $this->garbageIcon = '<span class="t3-icon fa t3-icon fa fa-trash"> </span>';
    $languageService = $this->getLanguageService();
    $this->newIcon = '<span title="' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.add_recurrence' ) . '" class="t3-icon fa t3-icon fa fa-plus-square"> </span>';
  }
  
  private function getWeekStartDay() {
    
    $pageID = $this->data['databaseRow'] ['pid'];
    $tsConfig = \TYPO3\CMS\Backend\Utility\BackendUtility::getPagesTSconfig( $pageID )['options.']['tx_cal_controller.']['weekStartDay'];
    $weekStartDay = strtolower( $tsConfig ['value'] );
    
    switch ($weekStartDay) {
      case 'sunday' :
        $startDay = 'su';
        break;
        /* If there's any value other than sunday, assume we want Monday */
      default :
        $startDay = 'mo';
        break;
    }
    
    return $startDay;
  }
  
  private function getWeekdaysArray($startDay) {
    
    $weekdays = Array ();
    
    $languageService = $this->getLanguageService();
    
    if ($startDay == 'su') {
      $weekdays ['su'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_sunday' );
    }
    
    $weekdays ['mo'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_monday' );
    $weekdays ['tu'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_tuesday' );
    $weekdays ['we'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_wednesday' );
    $weekdays ['th'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_thursday' );
    $weekdays ['fr'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_friday' );
    $weekdays ['sa'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_saturday' );
    
    if ($startDay != 'su') {
      $weekdays ['su'] = $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_sunday' );
    }
    
    return $weekdays;
  }
  
  public function render() {
    $this->init();
    $resultArray = $this->initializeResultArray();
    
    $fieldWizardResult = $this->renderFieldWizard();
    $fieldWizardHtml = $fieldWizardResult['html'];
    $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);
    
    $mainFieldHtml = [];
    $mainFieldHtml[] = "<script type=\"text/javascript\">function RecurUI(containerID, storageID, rowClass, rowHTML){
	this.containerID = containerID;
	this.storageID = storageID;
	this.rowClass = rowClass;
	this.rowHTML = rowHTML;
};
        
RecurUI.prototype = {
        
	addRecurrence: function(defaultValue) {

		var container = document.getElementById(escapeRegExp(this.containerID));
        
		container.insertAdjacentHTML('beforeend', this.rowHTML);
        
		if(defaultValue) {
            for(const [key, value] of Object.entries(defaultValue)) {
            var queryResult = container.getElementsByTagName('select');
              if(queryResult) {
			   queryResult[queryResult.length-1].value = value;
              }
			}
		}
        
		this.save();
	},
        
	setCheckboxes: function(defaultValue) {
		var container = document.getElementById(escapeRegExp(this.containerID));
		var rowSelector = '.' + this.rowClass;
		if(defaultValue) {
            for(const [key, value] of Object.entries(defaultValue)) {
    		  var queryResult = container.querySelector(rowSelector + ' input[value=\"' + value +'\"]');
              if(queryResult) {
    			 queryResult.checked = true;
    		  }
            }
		}
	},
        
	removeRecurrence: function(icon) {
		icon.parentNode.remove();
		this.save();
	},
        
	save: function() {
		var storage = document.getElementById(escapeRegExp(this.storageID));
		storage.value = '';
        
		//@todo  Figure out how to differentiate selector based forms from element based forms
		var container = document.getElementById(escapeRegExp(this.containerID));
		var elementsWithClassName = container.getElementsByClassName(this.rowClass);
        for(var i=0; i < elementsWithClassName.length; i++) {
			var rowValue = '';
        
			var elementsByTagName = elementsWithClassName[i].getElementsByTagName(\"select\");
            for(var j=0; j < elementsByTagName.length; j++) {
				rowValue += elementsByTagName[j].value;
			}
        
            var inputElements = elementsWithClassName[i].getElementsByTagName(\"input\");
            for(var j=0; j < inputElements.length; j++) {
              if(inputElements[j].checked && inputElements[j].value) {
                rowValue += inputElements[j].value;
              }
            }
        
			if(rowValue && rowValue.trim().length > 0 ) {
        
				if(storage.value) {
					storage.value = storage.value +  ',';
				}
				storage.value += rowValue;
			}
		}
	},
        
	load: function() {
		var initialValue = document.getElementById(escapeRegExp(this.storageID)).value;
		var recurArray = initialValue.split(\",\");
		var obj = this;
        
		for(var i = 0; i < recurArray.length; i++) {
			var hash = obj.storageToHash(recurArray[i]);
			if(obj.rowHTML) {
				obj.addRecurrence(hash);
			} else {
				obj.setCheckboxes(hash);
			}
        
		}
	}
}
        
function escapeRegExp(str) {
  return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, \"\\$&\");
}
        
function ByDayUI(containerID, storageID, rowClass, rowHTML){
	RecurUI.call(this, containerID, storageID, rowClass, rowHTML);
};
ByDayUI.prototype = Object.create(RecurUI.prototype, {
	storageToHash: {
		value: function(recur) {
			var splitLocation = 0;
			if(recur.length > 2) {
				for (i=1; i<recur.length; i++) {
					var character = recur.charAt(i);
					if(((character < \"0\") || (character > \"9\")) && (character != '-')) {
						splitLocation = i;
						break;
					}
				}
			}
        
			var countValue = recur.substr(0, splitLocation);
			var dayValue   = recur.substr(splitLocation, recur.length);
        
			return { count: countValue, day: dayValue };
		}
	}
        
});
        
function ByMonthDayUI(containerID, storageID, rowClass, rowHTML){
	RecurUI.call(this, containerID, storageID, rowClass, rowHTML);
};
ByMonthDayUI.prototype = Object.create(RecurUI.prototype, {
	storageToHash: {
		value: function(recur) {
			var dayValue = recur;
			return { day: dayValue };
		}
	}
});
        
function ByMonthUI(containerID, storageID, rowClass, rowHTML){
	RecurUI.call(this, containerID, storageID, rowClass, rowHTML);
};
ByMonthUI.prototype = Object.create(RecurUI.prototype, {
	storageToHash: {
		value: function(recur) {
			var monthValue = recur;
			return { month: monthValue };
		}
	}
});</script>";
    $mainFieldHtml[] = '<div class="form-control-wrap">';
    $mainFieldHtml[] =  '<div class="form-wizards-wrap">';
    $mainFieldHtml[] =      '<div class="form-wizards-element">';
    
    $parameterArray = $this->data['parameterArray'];
    $row = $this->data['databaseRow'];
    $itemValue = $parameterArray['itemFormElValue'];
    //\TYPO3\CMS\Core\Utility\DebugUtility::debug($parameterArray);
    if(substr( $parameterArray['itemFormElID'], -strlen('byday') ) === 'byday'){
     switch ($row['freq'][0]) {
       case 'week' :
         $mainFieldHtml[] = $this->byDay_checkbox();
         break;
      case 'month' :
        $row = $this->getByDayRow( $this->everyMonthText );
        $mainFieldHtml[] = $this->byDay_select( $row );
        break;
      case 'year' :
        $row = $this->getByDayRow( $this->selectedMonthText );
        $mainFieldHtml[] = $this->byDay_select( $row );
        break;
     }
     $mainFieldHtml[] = '<input type="hidden" name="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][byday]" id="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][byday]" value="' . $itemValue . '" />';
    } else if (substr( $parameterArray['itemFormElID'], -strlen('bymonthday') ) === 'bymonthday'){
      switch ($row['freq'][0]) {
        case 'week' :
          $row = $this->getByMonthDayRow( $this->everyMonthText );
          $mainFieldHtml[] = $this->byMonthDay_select( $row );
          break;
        case 'month' :
          $row = $this->getByMonthDayRow( $this->everyMonthText );
          $mainFieldHtml[] = $this->byMonthDay_select( $row );
          break;
        case 'year' :
          $row = $this->getByMonthDayRow( $this->selectedMonthText );
          $mainFieldHtml[] = $this->byMonthDay_select( $row );
          break;
      }
      $mainFieldHtml[] = '<input type="hidden" name="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][bymonthday]" id="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][bymonthday]" value="' . $itemValue . '" />';
    } else if (substr( $parameterArray['itemFormElID'], -strlen('bymonth') ) === 'bymonth'){
      $mainFieldHtml[] = $this->byMonth();
      $mainFieldHtml[] = '<input type="hidden" name="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][bymonth]" id="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][bymonth]" value="' . $itemValue . '" />';
    }
    
    $resultArray['html'] = implode(LF, $mainFieldHtml);
    return $resultArray;
  }
  
  private function byDay_checkbox() {
    
    $out = [];
    $out [] = '<script type="text/javascript">';
    $out [] = "var byDay = new ByDayUI('byday-container', 'data[" . $this->data['tableName'] . "][" . $this->data['vanillaUid'] . "][byday]', 'cal-row');";
    $out [] = "document.addEventListener('DOMContentLoaded', function(){ byDay.load();}, false);";
    $out [] = '</script>';
    $out [] = '<div id="byday-container" style="margin-bottom: 5px;">';
    foreach ( $this->weekdays as $value => $label ) {
      $name = "byday_" . $value;
      $out [] = '<div class="cal-row">';
      $out [] = '<input style="padding: 0px; margin: 0px;" type="checkbox" name="' . $name . '" value="' . $value . '" onchange="byDay.save();"/><label style="padding-left: 2px;" for="' . $name . '">' . $label . '</label>';
      $out [] = '</div>';
    }
    $out [] = '</div>';
    
    return implode( chr( 10 ), $out );
  }
  
  private function byDay_select($row) {
    
    $languageService = $this->getLanguageService();
    
    $out = array ();
    $out [] = '<script type="text/javascript">';
    $out [] = "var byDay = new ByDayUI('byday-container', 'data[" . $this->data['tableName'] . "][" . $this->data['vanillaUid'] . "][byday]', 'cal-row', '" . $row . "');";
    $out [] = "document.addEventListener('DOMContentLoaded', function(){ byDay.load();}, false);";
    $out [] = '</script>';
    
    $out [] = '<div id="byday-container"></div>';
    $out [] = '<div style="padding: 5px 0px 5px 0px;"><a href="javascript:byDay.addRecurrence();">' . $this->newIcon . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.add_recurrence' ) . '</a></div>';
    
    return implode( chr( 10 ), $out );
  }
  
  private function getByDayRow($endString) {
    
    $html = '<div class="cal-row">';
    
    $html .= '<select class="count" onchange="byDay.save()">';
    $html .= '<option value="" />';
    foreach ( $this->counts as $value => $label ) {
      $html .= '<option value="' . $value . '">' . $label . '</option>';
    }
    $html .= '</select>';
    
    $html .= '<select class="day" onchange="byDay.save()">';
    $html .= '<option value="" />';
    
    foreach ( $this->weekdays as $value => $label ) {
      $html .= '<option value="' . $value . '">' . $label . '</option>';
    }
    $html .= '</select>';
    
    $html .= ' ' . $endString;
    $html .= '<a id="garbage" href="#" onclick="byDay.removeRecurrence(this);">' . $this->garbageIcon . '</a>';
    $html .= '</div>';
    
    return $this->removeNewLines( $html );
  }
  
  private function getCountsArray() {
    
    $languageService = $this->getLanguageService();
    
    return Array (
        
        '1' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_first' ),
        '2' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_second' ),
        '3' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_third' ),
        '4' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_fourth' ),
        '5' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_fifth' ),
        '-3' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_thirdtolast' ),
        '-2' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_secondtolast' ),
        '-1' => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.byday_count_last' )
    );
  }
  
  /**
   * Converts newlines to <br/> tags.
   *
   * @access private
   * @param
   *          string The input string to filtered.
   * @return string converted string.
   */
  private function removeNewlines($input) {
    
    $order = Array (
        
        "\r\n",
        "\n",
        "\r",
        "\t"
    );
    $replace = '';
    $newstr = str_replace( $order, $replace, $input );
    
    return $newstr;
  }
  
  private function getByMonthDayRow($endString) {
    
    $languageService = $this->getLanguageService();
    
    $html = '<div class="cal-row">';
    
    $html .= $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.recurs_day' ) . ' ';
    $html .= '<select class="day" onchange="byMonthDay.save()">';
    $html .= '<option value=""></option>';
    for($i = 1; $i < 32; $i ++) {
      $html .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $html .= '</select>';
    
    $html .= ' ' . $endString;
    $html .= '<a id="garbage" href="#" onclick="byMonthDay.removeRecurrence(this);">' . $this->garbageIcon . '</a>';
    $html .= '</div>';
    
    return $this->removeNewLines( $html );
  }
  
  private function byMonthDay_select($row) {
    
    $languageService = $this->getLanguageService();
    
    $out = array ();
    $out [] = '<script type="text/javascript">';
    $out [] = "var byMonthDay = new ByMonthDayUI('bymonthday-container', 'data[" . $this->data['tableName'] . "][" . $this->data['vanillaUid'] . "][bymonthday]', 'cal-row', '" . $row . "');";
    $out [] = "document.addEventListener('DOMContentLoaded', function(){ byMonthDay.load();}, false);";
    $out [] = '</script>';
    
    $out [] = '<div id="bymonthday-container"></div>';
    $out [] = '<div style="padding: 5px 0px 5px 0px;"><a href="javascript:byMonthDay.addRecurrence();">' . $this->newIcon . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.add_recurrence' ) . '</a></div>';
    
    return implode( chr( 10 ), $out );
  }
  
  private function byMonth() {
    
    $out = Array ();
    
    $out [] = '<script type="text/javascript">';
    $out [] = "var byMonth = new ByMonthUI('bymonth-container', 'data[" . $this->data['tableName'] . "][" . $this->data['vanillaUid'] . "][bymonth]', 'cal-row');";
    $out [] = "document.addEventListener('DOMContentLoaded', function(){ byMonth.load();}, false);";
    $out [] = '</script>';
    
    $out [] = '<div id="bymonth-container" style="margin-bottom: 5px;">';
    foreach ( $this->getMonthsArray() as $value => $label ) {
      $name = "bymonth_" . $value;
      $out [] = '<div class="cal-row">';
      $out [] = '<input style="padding: 0px; margin: 0px;" type="checkbox" name="' . $name . '" value="' . $value . '" onchange="byMonth.save();"/><label style="padding-left: 2px;" for="' . $name . '">' . $label . '</label>';
      $out [] = '</div>';
    }
    $out [] = '</div>';
    
    return implode( chr( 10 ), $out );
  }
  
  private function getMonthsArray() {
    $languageService = $this->getLanguageService();
    return Array (
        
        "1" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_january' ),
        "2" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_february' ),
        "3" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_march' ),
        "4" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_april' ),
        "5" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_may' ),
        "6" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_june' ),
        "7" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_july' ),
        "8" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_august' ),
        "9" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_september' ),
        "10" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_october' ),
        "11" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_november' ),
        "12" => $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.bymonth_december' )
    );
  }
}

?>