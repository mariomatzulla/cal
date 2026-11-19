<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx_cal-indexer-module' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/Module_index.svg',
    ],
    'ext-cal-plugin-cal-views' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/Extension.svg',
    ],
    'tx-cal-ics-generator-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/Module_ics.svg',
    ],
    'cal-calendar-standard' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_domain_model_calendar.svg',
    ],
    'cal-calendar-exturl' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_domain_model_calendar_link.svg',
    ],
    'cal-calendar-ics' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_domain_model_calendar_feed.svg',
    ],
    'cal-eventtype-standard' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_event.svg',
    ],
    'cal-eventtype-intlnk' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_event_link.svg',
    ],
    'cal-eventtype-exturl' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_event_link.svg',
    ],
    'cal-eventtype-meeting' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_event_meeting.svg',
    ],
    'cal-eventtype-todo' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:cal/Resources/Public/Icons/tx_cal_event_todo.svg',
    ],
];
