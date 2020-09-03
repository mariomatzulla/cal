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
 * A service which renders a form to confirm the category edit/create.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class ConfirmCategoryView extends \TYPO3\CMS\Cal\View\FeEditingBaseView {

  public function __construct() {

    parent::__construct();
  }

  /**
   * Draws a create category form.
   *
   * @param
   *          string Comma separated list of pids.
   * @param
   *          object A location or organizer object to be updated
   * @return string HTML output.
   */
  public function drawConfirmCategory() {

    $this->objectString = 'category';
    $this->isConfirm = true;
    unset( $this->controller->piVars ['formCheck'] );
    $page = Functions::getContent( $this->conf ['view.'] ['confirm_category.'] ['template'] );
    if ($page == '') {
      return '<h3>category: no create category template file found:</h3>' . $this->conf ['view.'] ['confirm_category.'] ['template'];
    }
    
    $a = Array ();
    $this->object = new \TYPO3\CMS\Cal\Model\CategoryModel( $a, '' );
    $this->object->updateWithPIVars( $this->controller->piVars );
    
    $lastViewParams = $this->controller->shortenLastViewAndGetTargetViewParameters();
    
    if ($lastViewParams ['view'] == 'edit_category') {
      $this->isEditMode = true;
    }
    
    $rems = Array ();
    $sims = Array ();
    $wrapped = Array ();
    $sims ['###L_CONFIRM_CATEGORY###'] = $this->controller->pi_getLL( 'l_confirm_category' );
    $sims ['###UID###'] = $this->conf ['uid'];
    $sims ['###TYPE###'] = $this->conf ['type'];
    $sims ['###VIEW###'] = 'save_category';
    $sims ['###L_SUBMIT###'] = $this->controller->pi_getLL( 'l_submit' );
    $sims ['###L_CANCEL###'] = $this->controller->pi_getLL( 'l_cancel' );
    $sims ['###ACTION_URL###'] = htmlspecialchars( $this->controller->pi_linkTP_keepPIvars_url( array (
        
        'view' => 'save_category'
    ) ) );
    
    $this->getTemplateSubpartMarker( $page, $sims, $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, Array (), $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, Array (), Array () );
    $sims = Array ();
    $rems = Array ();
    $wrapped = Array ();
    $this->getTemplateSingleMarker( $page, $sims, $rems, $wrapped );
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, Array (), $rems, $wrapped );
    ;
    $page = \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, Array (), Array () );
    return \TYPO3\CMS\Cal\Utility\Functions::substituteMarkerArrayNotCached( $page, $sims, Array (), Array () );
  }

  public function getCalendarIdMarker(& $template, & $sims, & $rems) {

    $sims ['###CALENDAR_ID###'] = '';
    $sims ['###CALENDAR_ID_VALUE###'] = '';
    if ($this->isAllowed( 'calendar_id' )) {
      if ($calendar = $this->object->getCalendarObject()) {
        $sims ['###CALENDAR_ID###'] = $this->applyStdWrap( $calendar->getTitle(), 'calendar_id_stdWrap' );
        $sims ['###CALENDAR_ID_VALUE###'] = htmlspecialchars( $calendar->getUID() );
      }
    }
  }

  public function getHeaderstyleMarker(& $template, & $sims, & $rems) {

    $sims ['###HEADERSTYLE###'] = '';
    $sims ['###HEADERSTYLE_VALUE###'] = '';
    if ($this->isAllowed( 'headerstyle' )) {
      $headerStyleValue = $this->object->getHeaderStyle();
      $sims ['###HEADERSTYLE###'] = $this->applyStdWrap( $headerStyleValue, 'headerstyle_stdWrap' );
      $sims ['###HEADERSTYLE_VALUE###'] = $headerStyleValue;
    }
  }

  public function getBodystyleMarker(& $template, & $sims, & $rems) {

    $sims ['###BODYSTYLE###'] = '';
    $sims ['###BODYSTYLE_VALUE###'] = '';
    if ($this->isAllowed( 'bodystyle' )) {
      $bodyStyleValue = $this->object->getBodyStyle();
      $sims ['###BODYSTYLE###'] = $this->applyStdWrap( $bodyStyleValue, 'bodystyle_stdWrap' );
      $sims ['###BODYSTYLE_VALUE###'] = $bodyStyleValue;
    }
  }

  public function getParentCategoryMarker(& $template, &$sims, & $rems) {

    $sims ['###PARENT_CATEGORY###'] = '';
    $sims ['###PARENT_CATEGORY_VALUE###'] = '';
    if ($this->isAllowed( 'parent_category' )) {
      $parentUid = $this->object->getParentUid();
      if ($parentUid) {
        /* Get parent category title */
        $category = $this->modelObj->findCategory( $parentUid, 'tx_cal_category', $this->conf ['pidList'] );
        $sims ['###PARENT_CATEGORY###'] = $this->applyStdWrap( $category->getTitle(), 'parent_category_stdWrap' );
        $sims ['###PARENT_CATEGORY_VALUE###'] = $parentUid;
      }
    }
  }

  public function getSharedUserAllowedMarker(& $template, & $sims, & $rems) {

    $sims ['###SHARED_USER_ALLOWED###'] = '';
    $sims ['###SHARED_USER_ALLOWED_VALUE###'] = '';
    if ($this->isAllowed( 'shared_user_allowed' )) {
      if ($this->object->isSharedUserAllowed()) {
        $value = 1;
        $label = $this->controller->pi_getLL( 'l_true' );
      } else {
        $value = 0;
        $label = $this->controller->pi_getLL( 'l_false' );
      }
      
      $sims ['###SHARED_USER_ALLOWED###'] = $this->applyStdWrap( $label, 'shared_user_allowed_stdWrap' );
      $sims ['###SHARED_USER_ALLOWED_VALUE###'] = $value;
    }
  }
}

?>