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
use MarioMatzulla\Cal\Backend\TCA\ItemsProcFunc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ItemsProcFunc::class)]
final class ItemsProcFuncTest extends UnitTestCase
{
    #[Test]
    public function getDayTimesReturnsHalfHours(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] = 'g:i a';
        $params = ['items' => [['label' => 'Default', 'value' => '']]];
        (new ItemsProcFunc())->getDayTimes($params);
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm']);
        self::assertCount(50, $params['items']);
        self::assertSame(['label' => 'Default', 'value' => ''], $params['items'][0]);
        self::assertSame(['label' => '12:00 am', 'value' => '000000'], $params['items'][1]);
        self::assertSame(['label' => '1:30 pm', 'value' => '133000'], $params['items'][28]);
        self::assertSame(['label' => '11:59 pm', 'value' => '240000'], $params['items'][49]);

        $params = [];
        (new ItemsProcFunc())->getDayTimes($params);
        self::assertSame(['label' => '23:30', 'value' => '233000'], $params['items'][47]);
    }

    #[Test]
    public function getRecordsWithoutTableDoesNothing(): void
    {
        $params = ['config' => ['itemsProcFunc_config' => ['table' => 'tx_unknown']], 'items' => []];
        (new ItemsProcFunc())->getRecords($params);
        $params2 = ['items' => []];
        (new ItemsProcFunc())->getRecords($params2);
        self::assertSame([], $params['items']);
        self::assertSame([], $params2['items']);
    }
}
