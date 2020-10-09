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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend class for user-defined TCA type used in recurring event setup.
 *
 * @author Mario Matzulla <mario@matzullas.de>
 * @package TYPO3
 * @subpackage cal
 */
class CustomRdate extends AbstractFormElement {
  
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
  
  private $rdate;
  
  private $rdateType;
  
  private $rdateValues;
  
  private function init() {
    $row = $this->data['databaseRow'];
    $this->rdateType = $row ['rdate_type'] [0];
    $this->rdate = $row ['rdate'];
    $this->rdateValues = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode( ',', $row ['rdate'], 1 );
  }
  
  public function render() {
    $this->init();
    $resultArray = $this->initializeResultArray();
    
    $fieldWizardResult = $this->renderFieldWizard();
    $fieldWizardHtml = $fieldWizardResult['html'];
    $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);
    
    $languageService = $this->getLanguageService();
    
    $mainFieldHtml = [];
    
    $this->rdateValues [] = '';
    $out = Array ();
    $jsDate = $GLOBALS ['TYPO3_CONF_VARS'] ['SYS'] ['USdateFormat'] ? '%m-%d-%Y' : '%d-%m-%Y';
    $out [] = '<script type="text/javascript">';
    $out [] = 'var jsDate = "' . $jsDate . '";';
    $out [] = 'function rdateChanged(){';
    $out [] = 'var rdateCount = ' . (count( $this->rdateValues )) . ';';
    $out [] = 'var rdate = document.getElementById("data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][rdate]");';
    $out [] = 'rdate.value="";';
    $out [] = 'for(var i=0; i<rdateCount; i++){';
    if ($this->rdateType == 'date_time' || $this->rdateType == 'period') {
      $out [] = 'var dateFormated = document.getElementById("tceforms-datetimefield-data_' . $this->data['tableName'] . '_' . $this->data['vanillaUid'] . '_rdate"+i+"_hr").value;';
    } else {
      $out [] = 'var dateFormated = document.getElementById("tceforms-datefield-data_' . $this->data['tableName'] . '_' . $this->data['vanillaUid'] . '_rdate"+i+"_hr").value;';
    }
    $out [] = 'if(dateFormated!=""){';
    $out [] = 'var splittedDateTime = dateFormated.split(" ");';
    $out [] = 'var splittedTime = splittedDateTime[0].split(":");';
    $out [] = 'var splittedDate = splittedDateTime[0].split("-");';
    $out [] = 'if(splittedDateTime.length == 2) {';
    $out [] = 'splittedDate = splittedDateTime[1].split("-");';
    $out [] = '} else if(splittedDateTime.length == 1 && splittedDate.length == 2) {';
    $out [] = 'var d=new Date();';
    $out [] = 'splittedDate[2] = d.getFullYear();';
    $out [] = '}';
    $out [] = 'if(jsDate=="%d-%m-%Y"){';
    $out [] = 'dateFormated = splittedDate[2]+(parseInt(splittedDate[1],10)<10?"0":"")+parseInt(splittedDate[1],10)+(parseInt(splittedDate[0],10)<10?"0":"")+parseInt(splittedDate[0],10);';
    $out [] = '} else {';
    $out [] = 'dateFormated = splittedDate[2]+(parseInt(splittedDate[0],10)<10?"0":"")+parseInt(splittedDate[0],10)+(parseInt(splittedDate[1],10)<10?"0":"")+parseInt(splittedDate[1],10);';
    $out [] = '}';
    if ($this->rdateType == 'date_time') {
      $out [] = 'dateFormated += "T"+(parseInt(splittedTime[0],10)<10?"0":"")+parseInt(splittedTime[0],10)+(parseInt(splittedTime[1],10)<10?"0":"")+parseInt(splittedTime[1],10)+"00Z";';
    } else if ($this->rdateType == 'period') {
      $out [] = 'dateFormated += "T"+(parseInt(splittedTime[0],10)<10?"0":"")+parseInt(splittedTime[0],10)+(parseInt(splittedTime[1],10)<10?"0":"")+parseInt(splittedTime[1],10)+"00Z/P";';
      $out [] = 'var rdateYear = parseInt(document.getElementById("rdateYear"+i).value,10);';
      $out [] = 'var rdateMonth = parseInt(document.getElementById("rdateMonth"+i).value,10);';
      $out [] = 'var rdateWeek = parseInt(document.getElementById("rdateWeek"+i).value,10);';
      $out [] = 'var rdateDay = parseInt(document.getElementById("rdateDay"+i).value,10);';
      $out [] = 'var rdateHour = parseInt(document.getElementById("rdateHour"+i).value,10);';
      $out [] = 'var rdateMinute = parseInt(document.getElementById("rdateMinute"+i).value,10);';
      $out [] = 'dateFormated += rdateYear>0?rdateYear+"Y":"";';
      $out [] = 'dateFormated += rdateMonth>0?rdateMonth+"M":"";';
      $out [] = 'dateFormated += rdateWeek>0?rdateWeek+"W":"";';
      $out [] = 'dateFormated += rdateDay>0?rdateDay+"D":"";';
      $out [] = 'dateFormated += "T";';
      $out [] = 'dateFormated += rdateHour>0?rdateHour+"H":"";';
      $out [] = 'dateFormated += rdateMinute>0?rdateMinute+"M":"";';
    }
    $out [] = 'rdate.value += dateFormated+",";';
    
    $out [] = '}';
    $out [] = '}';
    $out [] = 'rdate.value = rdate.value.substr(0,rdate.value.length-1);';
    $out [] = '}';
    $out [] = '</script>';
    $key = 0;
    foreach ( $this->rdateValues as $value ) {
      $formatedValue = '';
      $splittedPeriod = Array (
          
          '',
          ''
      );
      if ($value != '') {
        $splittedPeriod = explode( '/', $value );
        $splittedDateTime = explode( 'T', $splittedPeriod [0] );
        if ($jsDate == '%d-%m-%Y') {
          $formatedValue = substr( $splittedDateTime [0], 6, 2 ) . '-' . substr( $splittedDateTime [0], 4, 2 ) . '-' . substr( $splittedDateTime [0], 0, 4 );
        } else if ($jsDate == '%m-%d-%Y') {
          $formatedValue = substr( $splittedDateTime [0], 4, 2 ) . '-' . substr( $splittedDateTime [0], 6, 2 ) . '-' . substr( $splittedDateTime [0], 0, 4 );
        } else {
          $formatedValue = 'unknown date format';
        }
        if ($this->rdateType == 'date_time' || $this->rdateType == 'period') {
          $formatedValue = count( $splittedDateTime ) == 2 ? substr( $splittedDateTime [1], 0, 2 ) . ':' . substr( $splittedDateTime [1], 2, 2 ) . ' ' . $formatedValue : '00:00 ' . $formatedValue;
        }
      }
      $params = Array ();
      $params ['table'] = $this->data['tableName'];
      $params ['uid'] = $this->data['vanillaUid'];
      $params ['field'] = 'rdate' . $key;
      $params ['md5ID'] = $this->data['tableName'] . '_' . $this->data['vanillaUid'] . '_' . 'rdate' . $key;
      if ($this->rdateType == 'date_time' || $this->rdateType == 'period') {
        $out [] = '<div class="form-control-wrap" style="max-width: 192px">
						<div class="input-group">
						    <input type="hidden" value="' . $formatedValue . '" id="data_' . $this->data['tableName'] . '_' . $this->data['vanillaUid'] . '_rdate' . $key . '" />
							<div class="form-control-clearable">
						    	<input data-date-type="datetime" onblur="rdateChanged();" onchange="rdateChanged();" data-formengine-validation-rules="[{&quot;type&quot;:&quot;datetime&quot;,&quot;config&quot;:{&quot;type&quot;:&quot;input&quot;,&quot;size&quot;:&quot;13&quot;,&quot;default&quot;:&quot;0&quot;}}]" data-formengine-input-params="{&quot;field&quot;:&quot;data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][rdate' . $key . '_hr]&quot;,&quot;evalList&quot;:&quot;datetime&quot;,&quot;is_in&quot;:&quot;&quot;}" data-formengine-input-name="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][rdate' . $key . '_hr]" id="tceforms-datetimefield-data_' . $this->data['tableName'] . '_' . $this->data['vanillaUid'] . '_rdate' . $key . '_hr" value="' . $formatedValue . '" maxlength="20" class="t3js-datetimepicker form-control t3js-clearable hasDefaultValue" type="text">
						    	    
							</div>
						</div>
					</div>';
      } else {
        $out [] = '<div class="form-control-wrap" style="max-width: 192px">
						<div class="input-group">
						    <input type="hidden" value="' . $formatedValue . '" id="data_' . $this->data['tableName'] . '_' . $this->data['vanillaUid'] . '_rdate' . $key . '" />
							<div class="form-control-clearable">
						    	<input data-date-type="date" onblur="rdateChanged();" onchange="rdateChanged();" data-formengine-validation-rules="[{&quot;type&quot;:&quot;date&quot;,&quot;config&quot;:{&quot;type&quot;:&quot;input&quot;,&quot;size&quot;:&quot;12&quot;,&quot;max&quot;:&quot;20&quot;}}]" data-formengine-input-params="{&quot;field&quot;:&quot;data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][rdate' . $key . '_hr]&quot;,&quot;evalList&quot;:&quot;date&quot;,&quot;is_in&quot;:&quot;&quot;}" data-formengine-input-name="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][rdate' . $key . '_hr]" id="tceforms-datefield-data_' . $this->data['tableName'] . '_' . $this->data['vanillaUid'] . '_rdate' . $key . '_hr" value="' . $formatedValue . '" maxlength="20" class="t3js-datetimepicker form-control t3js-clearable hasDefaultValue" type="text">
								<button style="display: none;" type="button" class="close" tabindex="-1" aria-hidden="true" onclick="rdateChanged();">
									<span class="fa fa-times"></span>
								</button>
							</div>
						</div>
					</div>';
      }
      if ($this->rdateType == 'date') {
        $params ['wConf'] ['evalValue'] = 'date';
      } else if ($this->rdateType == 'date_time' || $this->rdateType == 'period') {
        $params ['wConf'] ['evalValue'] = 'datetime';
      }
      if ($this->rdateType == 'period') {
        $periodArray = array ();
        preg_match( '/P((\d+)Y)?((\d+)M)?((\d+)W)?((\d+)D)?T((\d+)H)?((\d+)M)?((\d+)S)?/', $splittedPeriod [1], $periodArray );
        $params ['item'] .= '<span style="padding-left:10px;">' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:l_duration' ) . ':</span>' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:l_year' ) . ':<input type="text" value="' . intval( $periodArray [2] ) . '" name="rdateYear' . $key . '" id="rdateYear' . $key . '" size="2" onchange="rdateChanged();" />' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:l_month' ) . ':<input type="text" value="' . intval( $periodArray [4] ) . '" name="rdateMonth' . $key . '" id="rdateMonth' . $key . '" size="2" onchange="rdateChanged();" />' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:l_week' ) . ':<input type="text" value="' . intval( $periodArray [6] ) . '" name="rdateWeek' . $key . '" id="rdateWeek' . $key . '" size="2" onchange="rdateChanged();" />' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:l_day' ) . ':<input type="text" value="' . intval( $periodArray [8] ) . '" name="rdateDay' . $key . '" id="rdateDay' . $key . '" size="2" onchange="rdateChanged();" />' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:l_hour' ) . ':<input type="text" value="' . intval( $periodArray [10] ) . '" name="rdateHour' . $key . '" id="rdateHour' . $key . '" size="2" onchange="rdateChanged();" />' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:l_minute' ) . ':<input type="text" value="' . intval( $periodArray [12] ) . '" name="rdateMinute' . $key . '" id="rdateMinute' . $key . '" size="2" onchange="rdateChanged();" />' . '<br/>';
      }
      $out [] = $params ['item'];
      
      $key ++;
    }
    
    $out [] = '<input type="hidden" name="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][rdate]" id="data[' . $this->data['tableName'] . '][' . $this->data['vanillaUid'] . '][rdate]" value="' . $this->rdate . '" />';
    
    $mainFieldHtml[] = implode( chr( 10 ), $out );
    
    $resultArray['html'] = implode(LF, $mainFieldHtml);
    return $resultArray;
  }
  
}

?>