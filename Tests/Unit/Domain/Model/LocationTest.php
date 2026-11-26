<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Domain\Model;

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
use MarioMatzulla\Cal\Domain\Model\Location;
use MarioMatzulla\Cal\Domain\Model\Organizer;
use MarioMatzulla\Cal\Model\LocationInterface;
use MarioMatzulla\Cal\Model\OrganizerInterface;
use MarioMatzulla\Cal\Tests\Unit\AccessorAssertionsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Location::class)]
#[CoversClass(Organizer::class)]
final class LocationTest extends UnitTestCase
{
    use AccessorAssertionsTrait;

    private const VALUES = [
        'name' => 'Town hall',
        'description' => '<p>Description</p>',
        'street' => 'Main street 1',
        'zip' => '12345',
        'city' => 'Town',
        'countryZone' => 'BY',
        'country' => 'DE',
        'longitude' => '11.5',
        'latitude' => '48.1',
        'phone' => '+49 1',
        'mobilephone' => '+49 2',
        'fax' => '+49 3',
        'link' => 'https://example.org',
        'email' => 'info@example.org',
        'image' => 'image',
    ];

    #[Test]
    public function locationAccessors(): void
    {
        $location = new Location();
        self::assertInstanceOf(LocationInterface::class, $location);
        self::assertSame('tx_cal_location', $location->getModelType());
        $this->assertAccessors($location, self::VALUES + ['modelType' => 'other']);
    }

    #[Test]
    public function organizerAccessors(): void
    {
        $organizer = new Organizer();
        self::assertInstanceOf(OrganizerInterface::class, $organizer);
        self::assertSame('tx_cal_organizer', $organizer->getModelType());
        $this->assertAccessors($organizer, self::VALUES + ['modelType' => 'other']);
    }
}
