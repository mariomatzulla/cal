<?php

namespace TYPO3\CMS\Cal\Utility;

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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TemplateLayout utility class
 */
class TemplateLayout implements SingletonInterface {

  /**
   * Get available template layouts for a certain page
   *
   * @param int $pageUid          
   * @return array
   */
  public function getAvailableTemplateLayouts($pageUid) {

    $templateLayouts = [ ];
    
    // Check if the layouts are extended by ext_tables
    $confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
    if (isset( $confArr ['templateLayouts'] ) && is_array( $confArr ['templateLayouts'] )) {
      $templateLayouts = $confArr ['templateLayouts'];
    }
    
    // Add TsConfig values
    foreach ( $this->getTemplateLayoutsFromTsConfig( $pageUid ) as $templateKey => $title ) {
      if (GeneralUtility::isFirstPartOfStr( $title, '--div--' )) {
        $optGroupParts = GeneralUtility::trimExplode( ',', $title, true, 2 );
        $title = $optGroupParts [1];
        $templateKey = $optGroupParts [0];
      }
      $templateLayouts [] = [ 
          
          $title,
          $templateKey
      ];
    }
    
    return $templateLayouts;
  }

  /**
   * Get template layouts defined in TsConfig
   *
   * @param
   *          $pageUid
   * @return array
   */
  protected function getTemplateLayoutsFromTsConfig($pageUid) {

    $templateLayouts = [ ];
    $pagesTsConfig = BackendUtility::getPagesTSconfig( $pageUid );
    if (isset( $pagesTsConfig ['tx_cal_controller.'] ['templateLayouts.'] ) && is_array( $pagesTsConfig ['tx_cal_controller.'] ['templateLayouts.'] )) {
      $templateLayouts = $pagesTsConfig ['tx_cal_controller.'] ['templateLayouts.'];
    }
    return $templateLayouts;
  }
}
