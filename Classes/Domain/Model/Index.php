<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */

/**
 * Class Index
 */
class Index extends AbstractEntity
{
    /**
     * @var string
     */
    protected $tablename;

    /**
     * @var float
     */
    protected $startDatetime;

    /**
     * @var float
     */
    protected $endDatetime;

    /**
     * @var int
     */
    protected $eventUid;

    /**
     * @var int
     */
    protected $eventDeviationUid;

    /**
     * @return string
     */
    public function getTablename()
    {
        return $this->tablename;
    }

    /**
     * @return number
     */
    public function getStartDatetime()
    {
        return $this->startDatetime;
    }

    /**
     * @return number
     */
    public function getEndDatetime()
    {
        return $this->endDatetime;
    }

    /**
     * @return number
     */
    public function getEventUid()
    {
        return $this->eventUid;
    }

    /**
     * @return number
     */
    public function getEventDeviationUid()
    {
        return $this->eventDeviationUid;
    }

    /**
     * @param string $tablename
     */
    public function setTablename($tablename)
    {
        $this->tablename = $tablename;
    }

    /**
     * @param number $startDatetime
     */
    public function setStartDatetime($startDatetime)
    {
        $this->startDatetime = $startDatetime;
    }

    /**
     * @param number $endDatetime
     */
    public function setEndDatetime($endDatetime)
    {
        $this->endDatetime = $endDatetime;
    }

    /**
     * @param number $eventUid
     */
    public function setEventUid($eventUid)
    {
        $this->eventUid = $eventUid;
    }

    /**
     * @param number $eventDeviationUid
     */
    public function setEventDeviationUid($eventDeviationUid)
    {
        $this->eventDeviationUid = $eventDeviationUid;
    }
}
