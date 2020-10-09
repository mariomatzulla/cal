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
class CustomStyles extends AbstractFormElement {
  
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
  
  public function render() {
    $resultArray = $this->initializeResultArray();
    
    $fieldWizardResult = $this->renderFieldWizard();
    $fieldWizardHtml = $fieldWizardResult['html'];
    $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);
    
    $languageService = $this->getLanguageService();
    
    $mainFieldHtml = [];
    
    $parameterArray = $this->data['parameterArray'];
    
    if(substr( $parameterArray['itemFormElID'], -strlen('headerstyle') ) === 'headerstyle'){
      
      $mainFieldHtml[] = $this->getStyles('header' );
    }
    
    if(substr( $parameterArray['itemFormElID'], -strlen('bodystyle') ) === 'bodystyle'){
      
      $mainFieldHtml[] = $this->getStyles('body' );
    }
    
    
    $resultArray['html'] = implode(LF, $mainFieldHtml);
    return $resultArray;
  }
  
  private function getStyles($part) {
    
    $table = $this->data['tableName'];
    $pid = $this->data['databaseRow'] ['pid'];
    $uid = $this->data['databaseRow'] ['uid'];
    $value = $this->data['databaseRow'] [$part . 'style'];
    $html = '<div class="cal-row">';
    $pageTSConf = \TYPO3\CMS\Backend\Utility\BackendUtility::getPagesTSconfig( $pid );
    if ($pageTSConf ['options.'] ['tx_cal_controller.'] [$part . 'Styles']) {
      $html .= '<select class="select" name="data[' . $table . '][' . $uid . '][' . $part . 'style]">';
      $html .= '<option value=""></option>';
      
      $options = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode( ',', $pageTSConf ['options.'] ['tx_cal_controller.'] [$part . 'Styles'], 1 );
      
      foreach ( $options as $option ) {
        $nameAndColor = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode( '=', $option, 1 );
        $selected = '';
        if ($value == $nameAndColor [0]) {
          $selected = ' selected="selected"';
        }
        $html .= '<option value="' . $nameAndColor [0] . '" style="background-color:' . $nameAndColor [1] . ';"' . $selected . '>' . $nameAndColor [0] . '</option>';
      }
      $html .= '</select>';
    } else {
      $html .= '<input class="input" maxlength="30" size="20" name="data[' . $table . '][' . $uid . '][' . $part . 'style]" value="' . $value . '">';
    }
    $html .= '</div>';
    return $html;
  }
  
}

?>