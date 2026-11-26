<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Model;

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
use MarioMatzulla\Cal\Domain\Model\Event;

/**
 * Creates events for the tests of the view models.
 */
trait ViewModelTestTrait
{
    private static int $eventUid = 0;

    /**
     * @param string $start YmdHi
     * @param string $end YmdHi
     */
    private static function createEvent(string $start, string $end, bool $allday = false): Event
    {
        $event = new Event();
        $event->_setProperty('uid', ++self::$eventUid);
        $event->setAllday($allday);
        $event->setStartDate(\DateTime::createFromFormat('YmdHi', $start, new \DateTimeZone('UTC')));
        $event->setStartTime(\DateTime::createFromFormat('YmdHi', $start, new \DateTimeZone('UTC')));
        $event->setEndDate(\DateTime::createFromFormat('YmdHi', $end, new \DateTimeZone('UTC')));
        $event->setEndTime(\DateTime::createFromFormat('YmdHi', $end, new \DateTimeZone('UTC')));
        return $event;
    }
}
