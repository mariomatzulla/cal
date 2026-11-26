<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Backend\TCA;

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
use MarioMatzulla\Cal\Backend\TCA\Labels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Labels::class)]
final class LabelsTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] = 'd.m.Y';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] = 'H:i';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG'], $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm']);
        parent::tearDown();
    }

    private function labels(bool $showTimes): Labels
    {
        $configuration = $this->createStub(ExtensionConfiguration::class);
        $configuration->method('get')->willReturn($showTimes ? '1' : null);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $configuration);
        return new Labels();
    }

    private static function eventParams(int $uid, string $table = 'tx_cal_domain_model_event', array $row = []): array
    {
        return ['table' => $table, 'row' => $row + [
            'uid' => $uid,
            'tstamp' => 1,
            'title' => 'Meeting',
            'start_date' => gmmktime(0, 0, 0, 11, 25, 2026),
            'start_time' => 36000,
            'allday' => 0,
        ]];
    }

    #[Test]
    public function eventLabelWithDateAndTime(): void
    {
        $params = self::eventParams(1);
        $this->labels(true)->getEventRecordLabel($params);
        self::assertSame('25.11.2026 10:00: Meeting', $params['title']);

        // cached: the same record has the same label
        $params = self::eventParams(1);
        $params['row']['start_time'] = 0;
        (new Labels())->getEventRecordLabel($params);
        self::assertSame('25.11.2026 10:00: Meeting', $params['title']);
    }

    #[Test]
    public function eventLabelWithoutTime(): void
    {
        $labels = $this->labels(false);
        $params = self::eventParams(2);
        $labels->getEventRecordLabel($params);
        self::assertSame('25.11.2026: Meeting', $params['title']);

        $labels = $this->labels(true);
        $params = self::eventParams(3, 'tx_cal_domain_model_event', ['allday' => 1]);
        $labels->getEventRecordLabel($params);
        self::assertSame('25.11.2026: Meeting', $params['title']);

        // exception events have no time
        $params = self::eventParams(4, 'tx_cal_domain_model_exception_event');
        $labels->getEventRecordLabel($params);
        self::assertSame('25.11.2026: Meeting', $params['title']);
    }

    #[Test]
    public function eventLabelOfANewRecord(): void
    {
        // new record: incomplete row without uid, not cached
        // without date the configuration (times) is not needed
        $params = ['table' => 'tx_cal_domain_model_event', 'row' => ['uid' => 'NEW123', 'title' => 'New', 'start_date' => '']];
        (new Labels())->getEventRecordLabel($params);
        self::assertSame('New', $params['title']);

        // FormEngine passes ISO dates for new records
        $params = ['table' => 'tx_cal_domain_model_event', 'row' => ['uid' => 'NEW124', 'title' => 'New', 'start_date' => '2026-11-25T00:00:00Z', 'start_time' => '1970-01-01T10:00:00Z', 'allday' => 0]];
        $this->labels(true)->getEventRecordLabel($params);
        self::assertSame('25.11.2026 10:00: New', $params['title']);

        $params = ['table' => 'tx_cal_domain_model_event', 'row' => []];
        (new Labels())->getEventRecordLabel($params);
        self::assertArrayNotHasKey('title', $params);
    }

    #[Test]
    public function otherTablesAreIgnored(): void
    {
        $params = ['table' => 'pages', 'row' => ['uid' => 1]];
        $labels = new Labels();
        $labels->getEventRecordLabel($params);
        $labels->getDeviationRecordLabel($params);
        self::assertArrayNotHasKey('title', $params);
    }

    #[Test]
    public function deviationLabel(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturn('Abweichung');
        $GLOBALS['LANG'] = $languageService;
        $params = ['table' => 'tx_cal_domain_model_eventdeviation', 'row' => ['uid' => 5, 'orig_start_date' => gmmktime(0, 0, 0, 11, 25, 2026), 'orig_start_time' => 36000]];
        (new Labels())->getDeviationRecordLabel($params);
        self::assertSame('Abweichung: 25.11.2026 (10:00)', $params['title']);

        unset($GLOBALS['LANG']);
        $params = ['table' => 'tx_cal_domain_model_eventdeviation', 'row' => ['uid' => 0, 'orig_start_date' => 0, 'orig_start_time' => 0]];
        (new Labels())->getDeviationRecordLabel($params);
        self::assertSame('tx_cal_event.deviation: ', $params['title']);

        $params = ['table' => 'tx_cal_domain_model_eventdeviation', 'row' => []];
        (new Labels())->getDeviationRecordLabel($params);
        self::assertArrayNotHasKey('title', $params);
    }

    #[Test]
    public function defaultFormats(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm']);
        $params = self::eventParams(6);
        $this->labels(true)->getEventRecordLabel($params);
        self::assertSame('25.11.2026 10:00: Meeting', $params['title']);
    }
}
