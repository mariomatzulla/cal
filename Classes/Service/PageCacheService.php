<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollectorInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\CacheTag;

/**
 * Page cache of the pages with calendar plugins.
 *
 * The pages get the names of the cal tables as cache tags. The DataHandler flushes the tag of a table
 * when one of its records is saved in the backend, so these pages show the change at once. Changes
 * without DataHandler (CalDAV clients, import of external calendars) flush the pages with flush().
 */
class PageCacheService
{
    /**
     * Tag of every page with a calendar plugin
     */
    public const TAG = 'tx_cal_domain_model_event';

    /**
     * Tables whose records are shown by the calendar plugins
     */
    public const TAGS = [
        self::TAG,
        'tx_cal_domain_model_eventdeviation',
        'tx_cal_domain_model_exception_event',
        'tx_cal_domain_model_exception_event_group',
        'tx_cal_domain_model_calendar',
        'tx_cal_domain_model_location',
        'tx_cal_domain_model_organizer',
        'sys_category',
    ];

    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {}

    /**
     * Adds the cache tags to the page of the request (frontend only)
     */
    public function addCacheTags(ServerRequestInterface $request): void
    {
        $collector = $request->getAttribute('frontend.cache.collector');
        if ($collector instanceof CacheDataCollectorInterface) {
            $collector->addCacheTags(...array_map(static fn(string $tag): CacheTag => new CacheTag($tag), self::TAGS));
        }
    }

    /**
     * Flushes the page cache of all pages with a calendar plugin
     */
    public function flush(): void
    {
        $this->cacheManager->flushCachesInGroupByTags('pages', [self::TAG]);
    }
}
