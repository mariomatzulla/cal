<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\ViewHelpers;

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
use MarioMatzulla\Cal\ViewHelpers\ZuluDateTimeViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;

#[CoversClass(ZuluDateTimeViewHelper::class)]
final class ZuluDateTimeViewHelperTest extends UnitTestCase
{
    private static function render(array $arguments): string
    {
        $viewHelper = new ZuluDateTimeViewHelper();
        $viewHelper->setRenderingContext(new RenderingContext());
        $viewHelper->initializeArguments();
        $viewHelper->setArguments($arguments);
        return $viewHelper->render();
    }

    #[Test]
    public function rendersDateAndTimeInUtc(): void
    {
        $berlin = new \DateTimeZone('Europe/Berlin');
        self::assertSame('20261125T090000Z', self::render([
            'date' => new \DateTime('2026-11-25 10:00', $berlin),
            'time' => new \DateTimeImmutable('2026-11-25 10:00', $berlin),
        ]));
    }

    #[Test]
    public function rendersNothingWithoutDateOrTime(): void
    {
        self::assertSame('', self::render(['date' => null, 'time' => new \DateTime()]));
        self::assertSame('', self::render(['date' => new \DateTime(), 'time' => null]));
    }
}
