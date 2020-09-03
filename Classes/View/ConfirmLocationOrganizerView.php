<?php

namespace TYPO3\CMS\Cal\View;

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
use TYPO3\CMS\Cal\Utility\Functions;

/**
 * A service which renders a form to confirm the location/organizer create/edit.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class ConfirmLocationOrganizerView extends \TYPO3\CMS\Cal\View\FeEditingBaseView {

  var $isLocation = true;

  public function __construct() {

    parent::__construct();
  }

  /**
   * Draws a confirm form for a location or an organizer.
   *
   * @param
   *          boolean True if a location should be confirmed
   * @param
   *          object The cObject of the mother-class.
   * @param
   *          object The rights object.
   * @return string HTML output.
   */
  public function drawConfirmLocationOrOrganizer($isLocation = true) {

    $this->isLocation = $isLocation;
    $this->isConfirm = true;
    if ($isLocation) {
      $this->objectString = 'location';
    } else {
      $this->objectString = 'organizer';
    }
    
    $page = Functions::getContent( $this->conf ['view.'] ['confirm_location.'] ['template'] );
    if ($page == '') {
      return '<h3>calendar: no confirm ' . $this->objectString . ' template file found:</h3>' . $this->conf ['view.'] ['confirm_location.'] ['template'];
    }
    
    if ($isLocation) {
      $this->object = new \TYPO3\CMS\Cal\Model\Location( null, '' );
    } else {
      $this->object = new \TYPO3\CMS\Cal\Model\Organizer( null, '' );
    }
    $this->object->updateWithPIVars( $this->controller->piVars );
    
    $lastViewParams = $this->controller->shortenLastViewAndGetTargetViewParameters();
    
    if (substr( $lastViewParams ['view'], 0, 4 ) == 'edit') {
      $this->isEditMode = true;
    }
    
    $rems = Array ();
    $sims = Array ();
    $wrapped = Array ();
    $sims ['###UID###'] = $this->conf ['uid'];
    $sims ['###TYPE###'] = $this->conf ['type'];
    $sims ['###VIEW###'] = 'save_' . $this->objectString;
    $sims ['###LASTVIEW###'] = $this->controller->extendLastView();
    $sims ['###L_CONFIRM_LOCATION###'] = $this->controller->pi_getLL( 'l_confirm_' . $this->objectString );
    $sims ['###L_SAVE###'] = $this->controller->pi_getLL( 'l_save' );
    $sims ['###L_CANCEL###'] = $this->controller->pi_getLL( 'l_cancel' );
    $sims ['###ACTION_URL###'] = htmlspecialchars( $this->controller->pi_linkTP_keepPIvars_url( array (
        
        'view' => 'save_' . $this->objectString,
        'category' => null
    ) ) );
    
    $this->getTemplateSubpartMarker( $page, $sims, $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, Array (), $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, Array (), Array () );
    $sims = Array ();
    $rems = Array ();
    $wrapped = Array ();
    $this->getTemplateSingleMarker( $page, $sims, $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, Array (), $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, Array (), Array () );
    return \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, Array (), Array () );
  }

  public function getCountryMarker(& $template, & $sims, & $rems) {
    // Initialise static info library
    $sims ['###COUNTRY###'] = '';
    $sims ['###COUNTRY_VALUE###'] = '';
    if ($this->isAllowed( 'country' )) {
      if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded( 'static_info_tables' )) {
        $staticInfo = \TYPO3\CMS\Cal\Utility\Functions::makeInstance( 'SJBR\\StaticInfoTables\\PiBaseApi' );
        $staticInfo->init();
        $current = \SJBR\StaticInfoTables\Utility\LocalizationUtility::translate( array (
            
            'uid' => $this->object->getCountry()
        ), 'static_countries', FALSE );
        $sims ['###COUNTRY###'] = $this->applyStdWrap( $current, 'country_static_info_stdWrap' );
        $sims ['###COUNTRY_VALUE###'] = strip_tags( $this->object->getCountry() );
      } else {
        $sims ['###COUNTRY###'] = $this->applyStdWrap( $this->object->getCountry(), 'country_stdWrap' );
        $sims ['###COUNTRY_VALUE###'] = $this->object->getCountry();
      }
    }
  }

  public function getCountryzoneMarker(& $template, & $sims, & $rems) {
    // Initialise static info library
    $sims ['###COUNTRYZONE###'] = '';
    $sims ['###COUNTRYZONE_VALUE###'] = '';
    if ($this->isAllowed( 'countryzone' )) {
      if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded( 'static_info_tables' )) {
        $staticInfo = \TYPO3\CMS\Cal\Utility\Functions::makeInstance( 'SJBR\\StaticInfoTables\\PiBaseApi' );
        $staticInfo->init();
        $current = \SJBR\StaticInfoTables\Utility\LocalizationUtility::translate( array (
            
            'uid' => $this->object->getCountryzone()
        ), 'static_country_zones', FALSE );
        $sims ['###COUNTRYZONE###'] = $this->applyStdWrap( $current, 'countryzone_static_info_stdWrap' );
        $sims ['###COUNTRYZONE_VALUE###'] = $this->object->getCountryZone();
      } else {
        $sims ['###COUNTRYZONE###'] = $this->applyStdWrap( $this->object->getCountryZone(), 'countryzone_stdWrap' );
        $sims ['###COUNTRYZONE_VALUE###'] = $this->object->getCountryZone();
      }
    }
  }

  public function getSharedMarker(& $template, & $sims, & $rems) {

    $sims ['###SHARED###'] = '';
    if ($this->isAllowed( 'shared' ) && is_array( $this->controller->piVars ['shared'] )) {
      $shareddisplaylist = Array ();
      $sharedids = Array ();
      foreach ( $this->controller->piVars ['shared'] as $value ) {
        preg_match( '/(^[a-z])_([0-9]+)_(.*)/', $value, $idname );
        if ($idname [1] == 'u' || $idname [1] == 'g') {
          $sharedids [] = $idname [1] . '_' . $idname [2];
          $shareddisplaylist [] = $idname [3];
        }
      }
      $sims ['###SHARED###'] = $this->applyStdWrap( implode( ',', $shareddisplaylist ), 'shared_stdWrap' );
      $sims ['###SHARED_VALUE###'] = htmlspecialchars( implode( ',', $sharedids ) );
    }
  }
}

?>