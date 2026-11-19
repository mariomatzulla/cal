<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Model;

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
 * Interface for the calendar location.
 * Provides basic model functionality that other
 * models can use or extend.
 *
 * @author Mario Matzulla <mario@matzullas.de>
 */
interface LocationInterface extends ObjectInterface, CommunicationInterface
{
    public function getStreet();

    public function getZip();

    public function getCity();

    public function getCountryZone();

    public function getCountry();

    public function getLongitude();

    public function getLatitude();

}
