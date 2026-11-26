<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Backend\Form\RenderType;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use MarioMatzulla\Cal\Backend\Form\RenderType\AbstractCalFormElementHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Creates the form elements with stubs: labels are the language keys, icons are "[icon]",
 * page 1 has a site with the week start sunday, page 2 a site with monday, other pages no site
 */
trait FormElementTestTrait
{
    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
        parent::tearDown();
    }

    /**
     * @template T of AbstractCalFormElementHelper
     * @param class-string<T> $className
     * @return T
     */
    private function createElement(string $className, array $row): AbstractCalFormElementHelper
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturnCallback(static fn(string $key): string => substr($key, (int)strrpos($key, ':') + 1));
        $GLOBALS['LANG'] = $languageService;

        $icon = $this->createStub(Icon::class);
        $icon->method('render')->willReturn('[icon]');
        $iconFactory = $this->createStub(IconFactory::class);
        $iconFactory->method('getIcon')->willReturn($icon);

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturnCallback(function (int $pageId): Site {
            if ($pageId !== 1 && $pageId !== 2) {
                throw new SiteNotFoundException('no site', 1);
            }
            $site = $this->createStub(Site::class);
            $site->method('getSettings')->willReturn(SiteSettings::createFromSettingsTree(['plugin' => ['tx_cal' => ['settings' => ['view' => ['weekStartDay' => $pageId === 1 ? 'Sunday' : 'monday']]]]]));
            return $site;
        });

        $element = new $className($iconFactory, $siteFinder);
        $element->setData(['tableName' => 'tx_cal_domain_model_event', 'databaseRow' => $row + ['uid' => 7, 'pid' => 3]]);
        return $element;
    }

    /**
     * Value of the hidden field of the element
     */
    private static function hiddenValue(string $html, string $field): string
    {
        preg_match('/name="data\[tx_cal_domain_model_event\]\[7\]\[' . $field . '\]"[^>]* value="([^"]*)"/', $html, $match);
        return html_entity_decode($match[1] ?? 'missing');
    }

    /**
     * @return list<string> values of the options/checkboxes with the given class
     */
    private static function values(string $html, string $pattern): array
    {
        preg_match_all($pattern, $html, $matches);
        return $matches[1];
    }
}
