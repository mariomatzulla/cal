<?php

namespace TYPO3\CMS\Cal\View\Module;

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
use TYPO3\CMS\Cal\Service\AbstractModul;
use TYPO3\CMS\Cal\Utility\Functions;

/**
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class LocationLoader extends AbstractModul {

  /**
   * The function adds location markers into the event template
   *
   * @param Object $moduleCaller
   *          Instance of the event model (phpicalendar_model)
   */
  public function start(&$moduleCaller, $onlyMarker = FALSE) {

    if ($moduleCaller->getLocationId() > 0) {
      $this->modelObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'modelcontroller' );
      $this->cObj = &\TYPO3\CMS\Cal\Utility\Registry::Registry( 'basic', 'cobj' );
      
      $moduleCaller->confArr = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class )->get( 'cal' );
      $useLocationStructure = ($moduleCaller->confArr ['useLocationStructure'] ? $moduleCaller->confArr ['useLocationStructure'] : 'tx_cal_location');
      $location = $this->modelObj->findLocation( $moduleCaller->getLocationId(), $useLocationStructure );
      if (is_object( $location )) {
        $page = Functions::getContent( $moduleCaller->conf ['module.'] ['locationloader.'] ['template'] );
        if ($page == '') {
          return '<h3>module locationloader: no template file found:</h3>' . $moduleCaller->conf ['module.'] ['locationloader.'] ['template'];
        }
        $sims = Array ();
        $rems = Array ();
        $wrapped = Array ();
        $location->getMarker( $page, $sims, $rems, $wrapped );
        if ($onlyMarker) {
          return $sims;
        }
        return \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, $rems, Array () );
      }
    }
    return '';
  }
}
?>