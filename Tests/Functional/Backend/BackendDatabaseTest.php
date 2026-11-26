<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Backend;

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
use MarioMatzulla\Cal\Backend\TCA\ItemsProcFunc;
use MarioMatzulla\Cal\Backend\TCA\Labels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(ItemsProcFunc::class)]
#[CoversClass(Labels::class)]
final class BackendDatabaseTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Records.csv');
        // the backend always has a language service
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    /**
     * @return list<string> labels of the items
     */
    private static function records(int $pid, string $orderBy = 'name'): array
    {
        $params = [
            'config' => ['itemsProcFunc_config' => ['table' => 'tx_cal_domain_model_location', 'orderBy' => $orderBy]],
            'row' => ['pid' => $pid],
            'items' => [],
        ];
        (new ItemsProcFunc())->getRecords($params);
        return array_column($params['items'], 'label');
    }

    #[Test]
    public function getRecordsSortsTheRecordsOfThePageFirst(): void
    {
        self::assertSame(['Arena', 'Church', 'Town hall'], self::records(0));
        self::assertSame(['Church', 'Arena', 'Town hall'], self::records(6));
        self::assertSame(['Town hall', 'Arena', 'Church'], self::records(5, 'name DESC'));
        // without order: the records of the page first
        self::assertSame('Church', self::records(5, '')[2]);
    }

    #[Test]
    public function getRecordsRespectsTheMountsOfTheUser(): void
    {
        $this->setUpBackendUser(1);
        self::assertCount(3, self::records(0));
        $this->setUpBackendUser(2);
        self::assertSame(['Church'], self::records(0));
        $this->setUpBackendUser(3);
        self::assertSame([], self::records(0));
    }

    #[Test]
    public function labelsLoadTheRecord(): void
    {
        $labels = new Labels();
        $params = ['table' => 'tx_cal_domain_model_event', 'row' => ['uid' => 1]];
        $labels->getEventRecordLabel($params);
        // formats of the installation ($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] and ['hhmm']), times are shown by default
        $date = gmdate($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], 1794268800);
        $time = gmdate($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'], 36000);
        self::assertSame($date . ' ' . $time . ': Meeting', $params['title']);

        $params = ['table' => 'tx_cal_domain_model_eventdeviation', 'row' => ['uid' => 1]];
        $labels->getDeviationRecordLabel($params);
        self::assertStringEndsWith(': ' . $date . ' (' . $time . ')', $params['title']);

        // deleted or missing record
        $params = ['table' => 'tx_cal_domain_model_event', 'row' => ['uid' => 99, 'title' => 'x']];
        $labels->getEventRecordLabel($params);
        self::assertArrayNotHasKey('title', $params);
    }
}
