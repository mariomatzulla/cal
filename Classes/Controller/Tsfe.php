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
namespace TYPO3\CMS\Cal\Controller;

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Tsfe
 */
class Tsfe extends TypoScriptFrontendController {

  public function __construct($context = null, $siteOrId = null, $siteLanguageOrType = null, $pageArguments = null, $cHashOrFrontendUser = null, $_2 = null, $MP = null) {
    // $this->myInitializeContextWithGlobalFallback($context);
    
    // Fetch the request for fetching data (site/language/pageArguments) for compatibility reasons, not needed
    // in TYPO3 v11.0 anymore.
    /** @var ServerRequestInterface $request */
    $request = $GLOBALS ['TYPO3_REQUEST'] ?? \TYPO3\CMS\Core\Http\ServerRequestFactory::fromGlobals();
    
    $this->myInitializeSiteWithCompatibility( $siteOrId, $request );
    $this->myInitializeSiteLanguageWithCompatibility( $siteLanguageOrType, $request );
    // $pageArguments = $this->myBuildPageArgumentsWithFallback($pageArguments, $request);
    // $pageArguments = $this->myInitializeFrontendUserOrUpdateCHashArgument($cHashOrFrontendUser, $pageArguments);
    // $pageArguments = $this->myInitializeLegacyMountPointArgument(null, $pageArguments);
    
    $this->setPageArguments( $pageArguments );
    
    $this->uniqueString = md5( microtime() );
    $this->initPageRenderer();
    // $this->initCaches();
    // Initialize LLL behaviour
    $this->setOutputLanguage();
    // parent::__construct($context, $siteOrId, $siteLanguageOrType, $pageArguments, $cHashOrFrontendUser, $_2, $MP);
  }

  private function myInitializeLegacyMountPointArgument(string $MP, \TYPO3\CMS\Core\Routing\PageArguments $pageArguments): \TYPO3\CMS\Core\Routing\PageArguments {

    if ($MP === null) {
      return $pageArguments;
    }
    trigger_error( 'TypoScriptFrontendController should evaluate the MountPoint Parameter "MP" by the PageArguments object, not by a separate constructor argument. This functionality will be removed in TYPO3 v11.0', E_USER_DEPRECATED );
    if (! $GLOBALS ['TYPO3_CONF_VARS'] ['FE'] ['enable_mount_pids']) {
      return $pageArguments;
    }
    return new \TYPO3\CMS\Core\Routing\PageArguments( $pageArguments->getPageId(), $pageArguments->getPageType(), $pageArguments->getRouteArguments(), array_replace_recursive( $pageArguments->getStaticArguments(), [ 
        
        'MP' => $MP
    ] ), $pageArguments->getDynamicArguments() );
  }

  private function myInitializeFrontendUserOrUpdateCHashArgument($cHashOrFrontendUser, \TYPO3\CMS\Core\Routing\PageArguments $pageArguments): \TYPO3\CMS\Core\Routing\PageArguments {

    if ($cHashOrFrontendUser === null) {
      return $pageArguments;
    }
    if ($cHashOrFrontendUser instanceof \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication) {
      $this->fe_user = $cHashOrFrontendUser;
      return $pageArguments;
    }
    trigger_error( 'TypoScriptFrontendController should evaluate the parameter "cHash" by the PageArguments object, not by a separate constructor argument. This functionality will be removed in TYPO3 v11.0', E_USER_DEPRECATED );
    return new \TYPO3\CMS\Core\Routing\PageArguments( $pageArguments->getPageId(), $pageArguments->getPageType(), $pageArguments->getRouteArguments(), array_replace_recursive( $pageArguments->getStaticArguments(), [ 
        
        'cHash' => $cHashOrFrontendUser
    ] ), $pageArguments->getDynamicArguments() );
  }

  private function myBuildPageArgumentsWithFallback($pageArguments, \Psr\Http\Message\ServerRequestInterface $request): \TYPO3\CMS\Core\Routing\PageArguments {

    if ($pageArguments instanceof \TYPO3\CMS\Core\Routing\PageArguments) {
      return $pageArguments;
    }
    if ($request->getAttribute( 'routing' ) instanceof \TYPO3\CMS\Core\Routing\PageArguments) {
      return $request->getAttribute( 'routing' );
    }
    trigger_error( 'TypoScriptFrontendController must be constructed with a valid PageArguments object or a resolved page argument in the current request as fallback. None given.', E_USER_DEPRECATED );
    $queryParams = $request->getQueryParams();
    $pageId = $this->id ?: ($queryParams ['id'] ?? $request->getParsedBody() ['id'] ?? 0);
    $pageType = $this->type ?: ($queryParams ['type'] ?? $request->getParsedBody() ['type'] ?? 0);
    return new \TYPO3\CMS\Core\Routing\PageArguments( ( int ) $pageId, ( string ) $pageType, [ ], $queryParams );
  }

  private function myInitializeSiteLanguageWithCompatibility($siteLanguageOrType, \Psr\Http\Message\ServerRequestInterface $request): void {

    if ($siteLanguageOrType instanceof \TYPO3\CMS\Core\Site\Entity\SiteLanguage) {
      $this->language = $siteLanguageOrType;
    } else {
      trigger_error( 'TypoScriptFrontendController should evaluate the parameter "type" by the PageArguments object, not by a separate constructor argument. This functionality will be removed in TYPO3 v11.0', E_USER_DEPRECATED );
      $this->type = $siteLanguageOrType;
      if ($request->getAttribute( 'language' ) instanceof \TYPO3\CMS\Core\Site\Entity\SiteLanguage) {
        $this->language = $request->getAttribute( 'language' );
      } else {
        throw new \InvalidArgumentException( 'TypoScriptFrontendController must be constructed with a valid SiteLanguage object or a resolved site in the current request as fallback. None given.', 1561583127 );
      }
    }
  }

  private function myInitializeSiteWithCompatibility($siteOrId, \Psr\Http\Message\ServerRequestInterface $request): void {

    if ($siteOrId instanceof \TYPO3\CMS\Core\Site\Entity\SiteInterface) {
      $this->site = $siteOrId;
    } else {
      trigger_error( 'TypoScriptFrontendController should evaluate the parameter "id" by the PageArguments object, not by a separate constructor argument. This functionality will be removed in TYPO3 v11.0', E_USER_DEPRECATED );
      $this->id = $siteOrId;
      if ($request->getAttribute( 'site' ) instanceof \TYPO3\CMS\Core\Site\Entity\SiteInterface) {
        $this->site = $request->getAttribute( 'site' );
      } else {
        throw new \InvalidArgumentException( 'TypoScriptFrontendController must be constructed with a valid Site object or a resolved site in the current request as fallback. None given.', 1561583122 );
      }
    }
  }

  private function myInitializeContextWithGlobalFallback($context): void {

    if ($context instanceof \TYPO3\CMS\Core\Context\Context) {
      $this->context = $context;
    } else {
      // Use the global context for now
      trigger_error( 'TypoScriptFrontendController requires a context object as first constructor argument in TYPO3 v11.0, now falling back to the global Context. This fallback layer will be removed in TYPO3 v11.0', E_USER_DEPRECATED );
      $this->context = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Core\Context\Context::class );
    }
    if (! $this->context->hasAspect( 'frontend.preview' )) {
      $this->context->setAspect( 'frontend.preview', \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Frontend\Aspect\PreviewAspect::class ) );
    }
  }

  /**
   *
   * @param mixed $code          
   * @param string $header          
   * @param string $reason          
   */
  function pageNotFoundHandler($code, $header = '', $reason = '') {
    // do nothing
  }

  public function getSite(): Site {

    return new \TYPO3\CMS\Core\Site\Entity\Site( "/", 0, [ ] );
  }

  /**
   *
   * @param string $reason          
   * @param string $header          
   */
  function pageNotFoundAndExit($reason = '', $header = '') {
    // do nothing
  }
}