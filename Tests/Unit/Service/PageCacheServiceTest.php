<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Service;

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
use MarioMatzulla\Cal\Service\PageCacheService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(PageCacheService::class)]
final class PageCacheServiceTest extends UnitTestCase
{
    #[Test]
    public function thePageGetsTheTablesOfCalAsCacheTags(): void
    {
        $collector = new CacheDataCollector();
        $service = new PageCacheService(self::createStub(CacheManager::class));
        $service->addCacheTags((new ServerRequest())->withAttribute('frontend.cache.collector', $collector));
        $tags = array_map(static fn(CacheTag $tag): string => $tag->name, $collector->getCacheTags());
        self::assertSame(PageCacheService::TAGS, array_values($tags));
        self::assertContains('tx_cal_domain_model_event', $tags);
        self::assertContains('sys_category', $tags);

        // requests without frontend (e.g. backend, command line) are ignored
        $service->addCacheTags(new ServerRequest());
    }

    #[Test]
    public function flushRemovesThePagesWithCalendarPlugins(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())->method('flushCachesInGroupByTags')->with('pages', ['tx_cal_domain_model_event']);
        (new PageCacheService($cacheManager))->flush();
    }
}
