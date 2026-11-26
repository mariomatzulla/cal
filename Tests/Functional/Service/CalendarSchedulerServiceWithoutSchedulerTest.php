<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Service;

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
use MarioMatzulla\Cal\Service\CalendarSchedulerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(CalendarSchedulerService::class)]
final class CalendarSchedulerServiceWithoutSchedulerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    #[Test]
    public function nothingHappensWithoutScheduler(): void
    {
        $subject = $this->get(CalendarSchedulerService::class);
        self::assertFalse($subject->isAvailable());
        self::assertSame(['status' => 'none', 'taskUid' => 0, 'interval' => 0], $subject->synchronize(1));
        $subject->removeForCalendar(1);
    }
}
