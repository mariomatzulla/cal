<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Model;

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
class EventDeviation extends Event
{
    /**
     * @var int
     */
    protected $refEventId;

    /**
     * @var string
     */
    protected $modelType = 'tx_cal_event_deviation';

    /**
     * @var int
     */
    protected $origStartDate;

    /**
     * @var int
     */
    protected $origEndDate;

    /**
     * @var int
     */
    protected $origStartTime;

    /**
     * @var int
     */
    protected $origEndTime;

    /**
     * @return int
     */
    public function getOrigStartTime()
    {
        return $this->origStartTime;
    }

    /**
     * @return int
     */
    public function getOrigEndTime()
    {
        return $this->origEndTime;
    }

    /**
     * @param int $origStartTime
     */
    public function setOrigStartTime($origStartTime)
    {
        $this->origStartTime = $origStartTime;
    }

    /**
     * @param int $origEndTime
     */
    public function setOrigEndTime($origEndTime)
    {
        $this->origEndTime = $origEndTime;
    }

    /**
     * @return int
     */
    public function getRefEventId()
    {
        return $this->refEventId;
    }

    /**
     * @return string
     */
    public function getModelType()
    {
        return $this->modelType;
    }

    /**
     * @return int
     */
    public function getOrigStartDate()
    {
        return $this->origStartDate;
    }

    /**
     * @return int
     */
    public function getOrigEndDate()
    {
        return $this->origEndDate;
    }

    /**
     * @param int $refEventId
     */
    public function setRefEventId($refEventId)
    {
        $this->refEventId = $refEventId;
    }

    /**
     * @param int $origStartDate
     */
    public function setOrigStartDate($origStartDate)
    {
        $this->origStartDate = $origStartDate;
    }

    /**
     * @param int $origEndDate
     */
    public function setOrigEndDate($origEndDate)
    {
        $this->origEndDate = $origEndDate;
    }
}
