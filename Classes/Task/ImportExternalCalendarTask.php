<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Task;

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
use MarioMatzulla\Cal\Domain\Repository\CalendarRepository;
use MarioMatzulla\Cal\Service\ExternalCalendarImporter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task: imports an external calendar (see ExternalCalendarImporter).
 * The task is created and updated automatically when an external calendar is saved
 * (see CalendarSchedulerService), the interval is the calendar's "Refresh Interval".
 */
class ImportExternalCalendarTask extends AbstractTask
{
    /**
     * Name of the native TCA field of tx_scheduler_task holding the calendar uid.
     */
    public const FIELD = 'tx_cal_calendar';

    protected int $calendarUid = 0;

    public function execute(): bool
    {
        if ($this->calendarUid <= 0) {
            throw new \RuntimeException('No calendar configured for this task.', 1794945001);
        }
        // Throws a RuntimeException on download or parse errors, the scheduler logs it as failure
        GeneralUtility::makeInstance(ExternalCalendarImporter::class)->importCalendar($this->calendarUid);
        return true;
    }

    public function getAdditionalInformation(): string
    {
        $calendar = GeneralUtility::makeInstance(CalendarRepository::class)->findRawByUid($this->calendarUid);
        if ($calendar === null) {
            return 'Calendar [' . $this->calendarUid . '] not found';
        }
        return $calendar['title'] . ' [' . $this->calendarUid . ']';
    }

    public function getCalendarUid(): int
    {
        return $this->calendarUid;
    }

    public function setCalendarUid(int $calendarUid): void
    {
        $this->calendarUid = $calendarUid;
    }

    public function getTaskParameters(): array
    {
        return [
            self::FIELD => $this->calendarUid,
        ];
    }

    public function setTaskParameters(array $parameters): void
    {
        $this->calendarUid = (int)($parameters[self::FIELD] ?? 0);
    }
}
