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
 * A concrete model for the calendar.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class EventRecDeviationModel extends EventRecModel
{
    private $origStartDate;

    protected $deviation;

    public function __construct(Event $event, EventDeviation $deviation, float $start, float $end)
    {
        parent::__construct($event, $start, $end);
        $this->deviation = $deviation;
    }

    public function getDeviationId()
    {
        return $this->deviation->getUid();
    }

    public function getTitle()
    {
        if ($this->deviation->getTitle() != '') {
            return $this->deviation->getTitle();
        }
        return $this->parentEvent->getTitle();
    }

    public function getImage()
    {
        if (count($this->deviation->getImage()) > 0) {
            return $this->deviation->getImage();
        }
        return $this->parentEvent->getImage();
    }

}
