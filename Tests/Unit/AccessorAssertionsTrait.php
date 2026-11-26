<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit;

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

/**
 * Checks pairs of setters and getters (or is-methods).
 */
trait AccessorAssertionsTrait
{
    /**
     * @param array<string, mixed> $values property name => value
     */
    private function assertAccessors(object $subject, array $values): void
    {
        foreach ($values as $property => $value) {
            $setter = 'set' . ucfirst($property);
            $getter = method_exists($subject, 'get' . ucfirst($property)) ? 'get' . ucfirst($property) : 'is' . ucfirst($property);
            $subject->$setter($value);
            self::assertSame($value, $subject->$getter(), $property);
        }
    }
}
