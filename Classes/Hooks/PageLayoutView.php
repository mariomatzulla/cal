<?php

namespace TYPO3\CMS\Cal\Hooks;

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
use TYPO3\CMS\Cal\Utility\TemplateLayout;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Hook to display verbose information about pi1 plugin in Web>Page module
 */
class PageLayoutView {

  /**
   * Extension key
   *
   * @var string
   */
  const KEY = 'cal';

  /**
   * Path to the locallang file
   *
   * @var string
   */
  const LLPATH = 'LLL:EXT:cal/Resources/Private/Language/locallang_plugin.xml:';

  /**
   * Max shown settings
   */
  const SETTINGS_IN_PREVIEW = 7;

  /**
   * Table information
   *
   * @var array
   */
  public $tableData = [ ];

  /**
   * Flexform information
   *
   * @var array
   */
  public $flexformData = [ ];

  /**
   *
   * @var IconFactory
   */
  protected $iconFactory;

  /** @var TemplateLayout $templateLayoutsUtility */
  protected $templateLayoutsUtility;

  public function __construct() {

    $this->templateLayoutsUtility = GeneralUtility::makeInstance( TemplateLayout::class );
    $this->iconFactory = GeneralUtility::makeInstance( IconFactory::class );
  }

  /**
   * Returns information about this extension's pi1 plugin
   *
   * @param array $params
   *          Parameters to the hook
   * @return string Information about pi1 plugin
   */
  public function getExtensionSummary(array $params) {

    $actionTranslationKey = $result = '';
    
    $header = '<strong>' . htmlspecialchars( $this->getLanguageService()->sL( self::LLPATH . 'pi1_title' ) ) . '</strong>';
    
    $result = $header . '<br>' . htmlspecialchars( $this->getLanguageService()->sL( self::LLPATH . 'pi1_plus_wiz_description' ) );
    
    return $result;
  }

  /**
   * Render archive settings
   */
  public function getArchiveSettings() {

    $archive = $this->getFieldFromFlexform( 'settings.archiveRestriction' );
    
    if (! empty( $archive )) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.archiveRestriction' ),
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.archiveRestriction.' . $archive )
      ];
    }
  }

  /**
   * Render single news settings
   */
  public function getSingleNewsSettings() {

    $singleNewsRecord = ( int ) $this->getFieldFromFlexform( 'settings.singleNews' );
    
    if ($singleNewsRecord > 0) {
      $newsRecord = BackendUtilityCore::getRecord( 'tx_news_domain_model_news', $singleNewsRecord );
      
      if (is_array( $newsRecord )) {
        $pageRecord = BackendUtilityCore::getRecord( 'pages', $newsRecord ['pid'] );
        
        if (is_array( $pageRecord )) {
          $content = $this->getRecordData( $newsRecord ['uid'], 'tx_news_domain_model_news' );
        } else {
          $text = sprintf( $this->getLanguageService()->sL( self::LLPATH . 'pagemodule.pageNotAvailable' ), $newsRecord ['pid'] );
          $content = $this->generateCallout( $text );
        }
      } else {
        $text = sprintf( $this->getLanguageService()->sL( self::LLPATH . 'pagemodule.newsNotAvailable' ), $singleNewsRecord );
        $content = $this->generateCallout( $text );
      }
      
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.singleNews' ),
          $content
      ];
    }
  }

  /**
   * Render single news settings
   */
  public function getDetailPidSetting() {

    $detailPid = ( int ) $this->getFieldFromFlexform( 'settings.detailPid', 'additional' );
    
    if ($detailPid > 0) {
      $content = $this->getRecordData( $detailPid );
      
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.detailPid' ),
          $content
      ];
    }
  }

  /**
   * Render listPid news settings
   */
  public function getListPidSetting() {

    $listPid = ( int ) $this->getFieldFromFlexform( 'settings.listPid', 'additional' );
    
    if ($listPid > 0) {
      $content = $this->getRecordData( $listPid );
      
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.listPid' ),
          $content
      ];
    }
  }

  /**
   * Get the rendered page title including onclick menu
   *
   * @param int $detailPid          
   * @return string
   * @deprecated use getRecordData() instead
   */
  public function getPageRecordData($detailPid) {

    return $this->getRecordData( $detailPid, 'pages' );
  }

  /**
   *
   * @param int $id          
   * @param string $table          
   * @return string
   */
  public function getRecordData($id, $table = 'pages') {

    $record = BackendUtilityCore::getRecord( $table, $id );
    
    if (is_array( $record )) {
      $data = '<span data-toggle="tooltip" data-placement="top" data-title="id=' . $record ['uid'] . '">' . $this->iconFactory->getIconForRecord( $table, $record, Icon::SIZE_SMALL )->render() . '</span> ';
      $content = BackendUtilityCore::wrapClickMenuOnIcon( $data, $table, $record ['uid'], true, '', '+info,edit,history' );
      
      $linkTitle = htmlspecialchars( BackendUtilityCore::getRecordTitle( $table, $record ) );
      
      if ($table === 'pages') {
        $id = $record ['uid'];
        $currentPageId = ( int ) GeneralUtility::_GET( 'id' );
        $link = htmlspecialchars( $this->getEditLink( $record, $currentPageId ) );
        $switchLabel = $this->getLanguageService()->sL( self::LLPATH . 'pagemodule.switchToPage' );
        $content .= ' <a href="#" data-toggle="tooltip" data-placement="top" data-title="' . $switchLabel . '" onclick=\'top.jump("' . $link . '", "web_layout", "web", ' . $id . ');return false\'>' . $linkTitle . '</a>';
      } else {
        $content .= $linkTitle;
      }
    } else {
      $text = sprintf( $this->getLanguageService()->sL( self::LLPATH . 'pagemodule.recordNotAvailable' ), $id );
      $content = $this->generateCallout( $text );
    }
    
    return $content;
  }

  /**
   * Get order settings
   */
  public function getOrderSettings() {

    $orderField = $this->getFieldFromFlexform( 'settings.orderBy' );
    if (! empty( $orderField )) {
      $text = $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.orderBy.' . $orderField );
      
      // Order direction (asc, desc)
      $orderDirection = $this->getOrderDirectionSetting();
      if ($orderDirection) {
        $text .= ', ' . strtolower( $orderDirection );
      }
      
      // Top news first
      $topNews = $this->getTopNewsFirstSetting();
      if ($topNews) {
        $text .= '<br />' . $topNews;
      }
      
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.orderBy' ),
          $text
      ];
    }
  }

  /**
   * Get order direction
   *
   * @return string
   */
  public function getOrderDirectionSetting() {

    $text = '';
    
    $orderDirection = $this->getFieldFromFlexform( 'settings.orderDirection' );
    if (! empty( $orderDirection )) {
      $text = $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.orderDirection.' . $orderDirection );
    }
    
    return $text;
  }

  /**
   * Get topNewsFirst setting
   *
   * @return string
   */
  public function getTopNewsFirstSetting() {

    $text = '';
    $topNewsSetting = ( int ) $this->getFieldFromFlexform( 'settings.topNewsFirst', 'additional' );
    if ($topNewsSetting === 1) {
      $text = $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.topNewsFirst' );
    }
    
    return $text;
  }

  /**
   * Render category settings
   *
   * @param bool $showCategoryMode
   *          show the category conjunction
   */
  public function getCategorySettings($showCategoryMode = true) {

    $categories = GeneralUtility::intExplode( ',', $this->getFieldFromFlexform( 'settings.categories' ), true );
    if (count( $categories ) > 0) {
      $categoriesOut = [ ];
      foreach ( $categories as $id ) {
        $categoriesOut [] = $this->getRecordData( $id, 'sys_category' );
      }
      
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.categories' ),
          implode( ', ', $categoriesOut )
      ];
      
      // Category mode
      if ($showCategoryMode) {
        $categoryModeSelection = $this->getFieldFromFlexform( 'settings.categoryConjunction' );
        if (empty( $categoryModeSelection )) {
          $categoryMode = $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.categoryConjunction.all' );
        } else {
          $categoryMode = $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.categoryConjunction.' . $categoryModeSelection );
        }
        
        if (count( $categories ) > 0 && empty( $categoryModeSelection )) {
          $categoryMode = $this->generateCallout( $categoryMode );
        } else {
          $categoryMode = htmlspecialchars( $categoryMode );
        }
        
        $this->tableData [] = [ 
            
            $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.categoryConjunction' ),
            $categoryMode
        ];
      }
      
      $includeSubcategories = $this->getFieldFromFlexform( 'settings.includeSubCategories' );
      if ($includeSubcategories) {
        $this->tableData [] = [ 
            
            $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.includeSubCategories' ),
            '<i class="fa fa-check"></i>'
        ];
      }
    }
  }

  /**
   * Get the restriction for tags
   */
  public function getTagRestrictionSetting() {

    $tags = GeneralUtility::intExplode( ',', $this->getFieldFromFlexform( 'settings.tags', 'additional' ), true );
    if (count( $tags ) === 0) {
      return;
    }
    
    $categoryTitles = [ ];
    foreach ( $tags as $id ) {
      $categoryTitles [] = $this->getRecordData( $id, 'tx_news_domain_model_tag' );
    }
    
    $this->tableData [] = [ 
        
        $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.tags' ),
        implode( ', ', $categoryTitles )
    ];
  }

  /**
   * Render offset & limit configuration
   */
  public function getOffsetLimitSettings() {

    $offset = $this->getFieldFromFlexform( 'settings.offset', 'additional' );
    $limit = $this->getFieldFromFlexform( 'settings.limit', 'additional' );
    $hidePagination = $this->getFieldFromFlexform( 'settings.hidePagination', 'additional' );
    
    if ($offset) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.offset' ),
          $offset
      ];
    }
    if ($limit) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.limit' ),
          $limit
      ];
    }
    if ($hidePagination) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.hidePagination' ),
          '<i class="fa fa-check"></i>'
      ];
    }
  }

  /**
   * Render date menu configuration
   */
  public function getDateMenuSettings() {

    $dateMenuField = $this->getFieldFromFlexform( 'settings.dateField' );
    
    $this->tableData [] = [ 
        
        $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.dateField' ),
        $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.dateField.' . $dateMenuField )
    ];
  }

  /**
   * Render time restriction configuration
   */
  public function getTimeRestrictionSetting() {

    $timeRestriction = $this->getFieldFromFlexform( 'settings.timeRestriction' );
    
    if (! empty( $timeRestriction )) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.timeRestriction' ),
          htmlspecialchars( $timeRestriction )
      ];
    }
    
    $timeRestrictionHigh = $this->getFieldFromFlexform( 'settings.timeRestrictionHigh' );
    if (! empty( $timeRestrictionHigh )) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.timeRestrictionHigh' ),
          htmlspecialchars( $timeRestrictionHigh )
      ];
    }
  }

  /**
   * Render top news restriction configuration
   */
  public function getTopNewsRestrictionSetting() {

    $topNewsRestriction = ( int ) $this->getFieldFromFlexform( 'settings.topNewsRestriction' );
    if ($topNewsRestriction > 0) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.topNewsRestriction' ),
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.topNewsRestriction.' . $topNewsRestriction )
      ];
    }
  }

  /**
   * Render template layout configuration
   *
   * @param int $pageUid          
   */
  public function getTemplateLayoutSettings($pageUid) {

    $title = '';
    $field = $this->getFieldFromFlexform( 'settings.templateLayout', 'template' );
    
    // Find correct title by looping over all options
    if (! empty( $field )) {
      $layouts = $this->templateLayoutsUtility->getAvailableTemplateLayouts( $pageUid );
      foreach ( $layouts as $layout ) {
        if (( string ) $layout [1] === ( string ) $field) {
          $title = $layout [0];
        }
      }
    }
    
    if (! empty( $title )) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_template.templateLayout' ),
          $this->getLanguageService()->sL( $title )
      ];
    }
  }

  /**
   * Get information if override demand setting is disabled or not
   */
  public function getOverrideDemandSettings() {

    $field = $this->getFieldFromFlexform( 'settings.disableOverrideDemand', 'additional' );
    
    if ($field == 1) {
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_additional.disableOverrideDemand' ),
          '<i class="fa fa-check"></i>'
      ];
    }
  }

  /**
   * Get the startingpoint
   */
  public function getStartingPoint() {

    $value = $this->getFieldFromFlexform( 'settings.startingpoint' );
    
    if (! empty( $value )) {
      $pageIds = GeneralUtility::intExplode( ',', $value, true );
      $pagesOut = [ ];
      
      foreach ( $pageIds as $id ) {
        $pagesOut [] = $this->getRecordData( $id, 'pages' );
      }
      
      $recursiveLevel = ( int ) $this->getFieldFromFlexform( 'settings.recursive' );
      $recursiveLevelText = '';
      if ($recursiveLevel === 250) {
        $recursiveLevelText = $this->getLanguageService()->sL( 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:recursive.I.5' );
      } elseif ($recursiveLevel > 0) {
        $recursiveLevelText = $this->getLanguageService()->sL( 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:recursive.I.' . $recursiveLevel );
      }
      
      if (! empty( $recursiveLevelText )) {
        $recursiveLevelText = '<br />' . htmlspecialchars( $this->getLanguageService()->sL( 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.recursive' ) ) . ' ' . $recursiveLevelText;
      }
      
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.startingpoint' ),
          implode( ', ', $pagesOut ) . $recursiveLevelText
      ];
    }
  }

  /**
   * Get list of selected news items
   */
  protected function getSelectedListSetting() {

    $value = $this->getFieldFromFlexform( 'settings.selectedList' );
    
    if (! empty( $value )) {
      $idList = GeneralUtility::intExplode( ',', $value, true );
      $out = [ ];
      
      foreach ( $idList as $id ) {
        $out [] = $this->getRecordData( $id, 'tx_news_domain_model_news' );
      }
      
      $this->tableData [] = [ 
          
          $this->getLanguageService()->sL( self::LLPATH . 'flexforms_general.selectedList' ),
          implode( '<br>', $out )
      ];
    }
  }

  /**
   * Render an alert box
   *
   * @param string $text          
   * @return string
   */
  protected function generateCallout($text) {

    return '<div class="alert alert-warning">
            ' . htmlspecialchars( $text ) . '
        </div>';
  }

  /**
   * Render the settings as table for Web>Page module
   * System settings are displayed in mono font
   *
   * @param string $header          
   * @param int $recordUid          
   * @return string
   */
  protected function renderSettingsAsTable($header = '', $recordUid = 0) {

    $pageRenderer = GeneralUtility::makeInstance( PageRenderer::class );
    $pageRenderer->loadRequireJsModule( 'TYPO3/CMS/News/PageLayout' );
    $pageRenderer->addCssFile( 'EXT:news/Resources/Public/Css/Backend/PageLayoutView.css' );
    
    $view = GeneralUtility::makeInstance( StandaloneView::class );
    $view->setTemplatePathAndFilename( GeneralUtility::getFileAbsFileName( 'EXT:news/Resources/Private/Backend/PageLayoutView.html' ) );
    $view->assignMultiple( [ 
        
        'header' => $header,
        'rows' => [ 
            
            'above' => array_slice( $this->tableData, 0, self::SETTINGS_IN_PREVIEW ),
            'below' => array_slice( $this->tableData, self::SETTINGS_IN_PREVIEW )
        ],
        'id' => $recordUid
    ] );
    
    return $view->render();
  }

  /**
   * Get field value from flexform configuration,
   * including checks if flexform configuration is available
   *
   * @param string $key
   *          name of the key
   * @param string $sheet
   *          name of the sheet
   * @return string|NULL if nothing found, value if found
   */
  public function getFieldFromFlexform($key, $sheet = 'sDEF') {

    $flexform = $this->flexformData;
    if (isset( $flexform ['data'] )) {
      $flexform = $flexform ['data'];
      if (isset( $flexform [$sheet] ['lDEF'] [$key] ['vDEF'] )) {
        return $flexform [$sheet] ['lDEF'] [$key] ['vDEF'];
      }
    }
    
    return null;
  }

  /**
   * Build a backend edit link based on given record.
   *
   * @param array $row
   *          Current record row from database.
   * @param int $currentPageUid
   *          current page uid
   * @return string Link to open an edit window for record.
   * @see \TYPO3\CMS\Backend\Utility\BackendUtilityCore::readPageAccess()
   */
  protected function getEditLink($row, $currentPageUid) {

    $editLink = '';
    $localCalcPerms = $GLOBALS ['BE_USER']->calcPerms( BackendUtilityCore::getRecord( 'pages', $row ['uid'] ) );
    $permsEdit = $localCalcPerms & Permission::PAGE_EDIT;
    if ($permsEdit) {
      $uriBuilder = GeneralUtility::makeInstance( UriBuilder::class );
      $returnUrl = $uriBuilder->buildUriFromRoute( 'web_layout', [ 
          
          'id' => $currentPageUid
      ] );
      $editLink = $uriBuilder->buildUriFromRoute( 'web_layout', [ 
          
          'id' => $row ['uid'],
          'returnUrl' => $returnUrl
      ] );
    }
    return $editLink;
  }

  /**
   * Return language service instance
   *
   * @return \TYPO3\CMS\Core\Localization\LanguageService
   */
  public function getLanguageService() {

    return $GLOBALS ['LANG'];
  }

  /**
   * Get the DocumentTemplate
   *
   * @return DocumentTemplate
   */
  protected function getDocumentTemplate() {

    return GeneralUtility::makeInstance( \TYPO3\CMS\Backend\Template\DocumentTemplate::class );
  }
}
