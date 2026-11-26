<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Controller;

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
use MarioMatzulla\Cal\Controller\ContentController;
use MarioMatzulla\Cal\Service\PrivateEventService;
use MarioMatzulla\Cal\Utility\RecurrenceGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(ContentController::class)]
#[CoversClass(PrivateEventService::class)]
final class ContentControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'cal' => [
                'hideLocationTextfield' => '0',
                'hideOrganizerTextfield' => '0',
                'useLocationStructure' => 'tx_cal_domain_model_location',
                'useOrganizerStructure' => 'tx_cal_domain_model_organizer',
                'showTimes' => '1',
                'useTeaser' => '0',
                'hideDeviationRecords' => '1',
                'recurrenceStart' => 'cal:yearstart',
                'recurrenceEnd' => '+3 years',
            ],
        ],
        'FE' => [
            'pageNotFoundOnCHashError' => false,
            'cacheHash' => ['enforceValidation' => false],
        ],
    ];

    private \DateTimeImmutable $concertDay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Frontend.csv');
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'https://example.org/');
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:cal/Configuration/TypoScript/constants.typoscript'],
            'setup' => [
                'EXT:cal/Configuration/TypoScript/setup.typoscript',
                'EXT:cal/Tests/Functional/Controller/Fixtures/Page.typoscript',
            ],
        ]);

        $today = new \DateTimeImmutable('today');
        $this->concertDay = $today->modify('+3 days');
        $events = $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event');
        foreach ([
            ['uid' => 1, 'title' => 'Concert', 'start_date' => $this->concertDay->getTimestamp(), 'end_date' => $this->concertDay->getTimestamp(), 'start_time' => 72000, 'end_time' => 79200, 'location_id' => 1, 'organizer_id' => 1],
            ['uid' => 2, 'title' => 'Festival', 'start_date' => $today->modify('+5 days')->getTimestamp(), 'end_date' => $today->modify('+7 days')->getTimestamp(), 'allday' => 1],
            ['uid' => 3, 'title' => 'Weekly meeting', 'start_date' => $today->modify('+1 day')->getTimestamp(), 'end_date' => $today->modify('+1 day')->getTimestamp(), 'start_time' => 36000, 'end_time' => 39600, 'freq' => 'week', 'until' => $today->modify('+60 days')->getTimestamp()],
            ['uid' => 4, 'title' => 'Other calendar', 'calendar_id' => 2, 'start_date' => $today->modify('+2 days')->getTimestamp(), 'end_date' => $today->modify('+2 days')->getTimestamp(), 'start_time' => 36000, 'end_time' => 39600],
        ] as $event) {
            $events->insert('tx_cal_domain_model_event', $event + ['pid' => 5, 'calendar_id' => 1, 'allday' => 0, 'freq' => 'none', 'timezone' => date_default_timezone_get()]);
        }
        $this->get(RecurrenceGenerator::class)->setRange()->generateIndex();
    }

    /**
     * @param array<string, string> $fields FlexForm fields of the plugin
     */
    private function setFlexForm(array $fields): void
    {
        $xml = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data><sheet index="sDEF"><language index="lDEF">';
        foreach ($fields as $field => $value) {
            $xml .= '<field index="' . $field . '"><value index="vDEF">' . htmlspecialchars($value) . '</value></field>';
        }
        $xml .= '</language></sheet></data></T3FlexForms>';
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_content')->update('tt_content', ['pi_flexform' => $xml], ['uid' => 10]);
    }

    /**
     * @param array<string, string|int> $arguments arguments of the plugin
     */
    private function render(array $arguments = [], array $query = []): string
    {
        $request = new InternalRequest('https://example.org/events');
        if ($arguments !== []) {
            $query['tx_cal_controller'] = $arguments;
        }
        if ($query !== []) {
            // InternalRequest only accepts scalar values: tx_cal_controller[action] = ...
            $request = $request->withQueryParameters(self::flatten($query));
        }
        return (string)$this->executeFrontendSubRequest($request)->getBody();
    }

    private static function flatten(array $parameters, string $prefix = ''): array
    {
        $result = [];
        foreach ($parameters as $key => $value) {
            $name = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';
            $result += is_array($value) ? self::flatten($value, $name) : [$name => $value];
        }
        return $result;
    }

    #[Test]
    public function listIsTheDefaultView(): void
    {
        $body = $this->render();
        self::assertStringContainsString('Concert', $body);
        self::assertStringContainsString('Festival', $body);
        self::assertStringContainsString('Weekly meeting', $body);
        self::assertStringContainsString('Other calendar', $body);

        // FlexForm: only one calendar, at most two events
        $this->setFlexForm(['allowedViews' => 'list,event', 'calendarMode' => '1', 'calendarSelection' => '1', 'maxEvents' => '2']);
        $body = $this->render();
        self::assertStringNotContainsString('Other calendar', $body);
        self::assertStringContainsString('Weekly meeting', $body);
        self::assertStringContainsString('Concert', $body);
        self::assertStringNotContainsString('Festival', $body);

        // all calendars except the selected one
        $this->setFlexForm(['calendarMode' => '2', 'calendarSelection' => '1']);
        $body = $this->render();
        self::assertStringContainsString('Other calendar', $body);
        self::assertStringNotContainsString('Concert', $body);
    }

    #[Test]
    public function calendarViews(): void
    {
        $this->setFlexForm(['allowedViews' => 'month,year,week,day,list,event']);
        $day = $this->concertDay;
        foreach ([
            ['action' => 'month', 'year' => $day->format('Y'), 'month' => $day->format('n')],
            ['action' => 'year', 'year' => $day->format('Y')],
            ['action' => 'week', 'year' => $day->format('o'), 'week' => $day->format('W')],
            ['action' => 'day', 'year' => $day->format('Y'), 'month' => $day->format('n'), 'day' => $day->format('j')],
        ] as $arguments) {
            self::assertStringContainsString('Concert', $this->render($arguments), $arguments['action']);
        }
        // default view of the plugin (first allowed view) and views without date
        self::assertStringContainsString('Concert', $this->render());
        foreach (['month', 'year', 'week', 'day'] as $action) {
            self::assertNotSame('', $this->render(['action' => $action]));
        }
    }

    #[Test]
    public function exceptionEventsRemoveDatesOfASeries(): void
    {
        $today = new \DateTimeImmutable('today');
        $pool = $this->get(ConnectionPool::class);
        $pool->getConnectionForTable('tx_cal_domain_model_exception_event')->insert('tx_cal_domain_model_exception_event', ['uid' => 1, 'pid' => 5, 'title' => 'Holiday', 'start_date' => $today->modify('+8 days')->getTimestamp()]);
        $pool->getConnectionForTable('tx_cal_domain_model_exception_event_mm')->insert('tx_cal_domain_model_exception_event_mm', ['uid_local' => 3, 'uid_foreign' => 1, 'tablenames' => 'tx_cal_domain_model_exception_event']);
        $this->get(RecurrenceGenerator::class)->setRange()->generateIndexForEventUid(3);
        $this->setFlexForm(['allowedViews' => 'day,list']);
        $day = static fn(\DateTimeImmutable $date): array => ['action' => 'day', 'year' => $date->format('Y'), 'month' => $date->format('n'), 'day' => $date->format('j')];
        // the first date (from the index), the removed date, the next date
        self::assertStringContainsString('Weekly meeting', $this->render($day($today->modify('+1 day'))));
        self::assertStringNotContainsString('Weekly meeting', $this->render($day($today->modify('+8 days'))));
        self::assertStringContainsString('Weekly meeting', $this->render($day($today->modify('+15 days'))));
    }

    #[Test]
    public function defaultViewOfThePlugin(): void
    {
        foreach (['year', 'week', 'day', 'event', 'ics', 'unknown'] as $view) {
            $this->setFlexForm(['allowedViews' => $view . ',list']);
            self::assertNotSame('', $this->render(), $view);
        }
    }

    #[Test]
    public function detailViews(): void
    {
        $this->setFlexForm(['allowedViews' => 'list,event,location,organizer,ics', 'showIcsLinks' => '1']);
        $body = $this->render(['action' => 'event', 'uid' => 1]);
        self::assertStringContainsString('Concert', $body);
        self::assertStringContainsString('Town hall', $body);
        self::assertStringContainsString('Music club', $body);
        self::assertStringContainsString('Concert', $this->render(['action' => 'event', 'uid' => 1, 'type' => 'tx_cal_event', 'view' => 'event']));

        self::assertStringContainsString('Town hall', $this->render(['action' => 'location', 'uid' => 1]));
        self::assertStringContainsString('Music club', $this->render(['action' => 'organizer', 'uid' => 1]));
        // other calendar not allowed by the plugin: no event
        $this->setFlexForm(['allowedViews' => 'list,event', 'calendarMode' => '1', 'calendarSelection' => '1']);
        self::assertStringNotContainsString('Other calendar', $this->render(['action' => 'event', 'uid' => 4]));
        // manipulated arguments
        self::assertNotSame('', $this->render(['action' => 'event', 'uid' => 1, 'type' => '']));
        self::assertNotSame('', $this->render(['action' => 'event', 'uid' => ['1']]));
    }

    #[Test]
    public function icsExport(): void
    {
        // the iCalendar data is generated when the event is saved (DataHandler hook, backend module)
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event')->update(
            'tx_cal_domain_model_event',
            ['caldav_data' => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nSUMMARY:Concert\r\nEND:VEVENT\r\nEND:VCALENDAR"],
            ['uid' => 1],
        );
        $this->setFlexForm(['allowedViews' => 'list,ics']);
        $body = $this->render(['action' => 'ics', 'uid' => 1], ['type' => '150']);
        self::assertStringContainsString('BEGIN:VCALENDAR', $body);
        self::assertStringContainsString('Concert', $body);
        self::assertStringContainsString('Could not find the event', $this->render(['action' => 'ics', 'uid' => 99], ['type' => '150']));

        $this->setFlexForm(['allowedViews' => 'list']);
        self::assertStringContainsString('The ICS view is not allowed', $this->render(['action' => 'ics', 'uid' => 1], ['type' => '150']));
    }

    #[Test]
    public function storageFoldersAndTemplateFiles(): void
    {
        // TypoScript of the plugin: records only from page 5 and its subpages, own list template
        $this->setFlexForm(['myTS' => "pidList = 5\nrecursive = 2\nview.list.templateFile = EXT:cal/Tests/Functional/Controller/Fixtures/List.html\nview.event.templateFile = EXT:cal/Tests/Functional/Controller/Fixtures/Missing.html"]);
        $body = $this->render();
        self::assertMatchesRegularExpression('/Own list template: \\d+ events.* Concert/s', $body);
        self::assertStringContainsString('Concert', $this->render(['action' => 'event', 'uid' => 1]));

        $this->setFlexForm(['myTS' => 'pidList = 2']);
        self::assertStringNotContainsString('Concert', $this->render());
    }

    #[Test]
    public function privateEventsAreBusyAndCancelledEventsAreMarked(): void
    {
        $events = $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event');
        $events->update('tx_cal_domain_model_event', ['classification' => 'PRIVATE', 'caldav_data' => "BEGIN:VCALENDAR\r\nEND:VCALENDAR"], ['uid' => 1]);
        $events->update('tx_cal_domain_model_event', ['status' => 'CANCELLED'], ['uid' => 2]);
        $events->update('tx_cal_domain_model_event', ['status' => 'TENTATIVE'], ['uid' => 3]);
        $this->setFlexForm(['allowedViews' => 'list,event,ics']);

        $body = $this->render();
        // only the time of the private event, without title, location and link
        self::assertStringNotContainsString('Concert', $body);
        self::assertStringNotContainsString('Town hall', $body);
        self::assertStringContainsString('Busy', $body);
        self::assertStringContainsString('cal-event--busy', $body);
        self::assertStringNotContainsString('uid%5D=1&', $body);
        // cancelled and tentative events are shown with a note
        self::assertStringContainsString('Festival', $body);
        self::assertStringContainsString('cal-event--cancelled', $body);
        self::assertStringContainsString('Cancelled', $body);
        self::assertStringContainsString('cal-event--tentative', $body);
        self::assertStringContainsString('Tentative', $body);

        // the detail view shows the time only, the iCalendar data is not downloaded
        $detail = $this->render(['action' => 'event', 'uid' => 1]);
        self::assertStringNotContainsString('Concert', $detail);
        self::assertStringContainsString('Busy', $detail);
        self::assertStringContainsString('Could not find the event', $this->render(['action' => 'ics', 'uid' => 1], ['type' => '150']));

        // own text instead of "Busy"
        $this->setFlexForm(['allowedViews' => 'list,event,ics', 'myTS' => 'view.privateEvents.busyTitle = Occupied']);
        self::assertStringContainsString('Occupied', $this->render());
    }
}
