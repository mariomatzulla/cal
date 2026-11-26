<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Utility;

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
use MarioMatzulla\Cal\Utility\HelperFunctions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(HelperFunctions::class)]
final class HelperFunctionsTest extends UnitTestCase
{
    public static function trimExplodeDataProvider(): array
    {
        return [
            'null' => [null, []],
            'empty string' => ['', []],
            'list with spaces and empty values' => [' 1, 2 ,,3 , ', ['1', '2', '3']],
            'array' => [['a', ' b', ''], ['a', 'b']],
            'zero is kept' => ['0,1', ['0', '1']],
        ];
    }

    #[Test]
    #[DataProvider('trimExplodeDataProvider')]
    public function trimExplodeReturnsTrimmedNonEmptyValues(string|array|null $input, array $expected): void
    {
        self::assertSame($expected, HelperFunctions::trimExplode($input));
    }
}
