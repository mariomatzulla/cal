<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Model;

use MarioMatzulla\Cal\Model\OrganizerInterface;

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
 * Class Organizer
 */
class Organizer extends Location implements OrganizerInterface
{
    protected $modelType = 'tx_cal_organizer';

    /**
     * @return string
     */
    public function getModelType()
    {
        return $this->modelType;
    }

    /**
     * @param string $modelType
     */
    public function setModelType($modelType)
    {
        $this->modelType = $modelType;
    }

}
