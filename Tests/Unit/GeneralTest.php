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
use MarioMatzulla\Cal\Controller\ContentController;
use MarioMatzulla\Cal\General;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The TypoScript settings of the plugin (trait General of the ContentController)
 */
#[CoversClass(ContentController::class)]
final class GeneralTest extends UnitTestCase
{
    private static function subject(array $typoScriptSettings): object
    {
        return new class ($typoScriptSettings) {
            use General;

            public function __construct(private readonly array $typoScriptSettings) {}

            protected function getTyposcriptConfig(): array
            {
                return $this->typoScriptSettings;
            }

            public static function settingsOf(array $typoScript): array
            {
                return self::buildSettings($typoScript);
            }
        };
    }

    #[Test]
    public function settingsOfTheTypoScript(): void
    {
        $subject = self::subject([]);
        self::assertSame([], $subject::settingsOf([]));
        self::assertSame(
            ['pidList' => '5', 'view' => ['allowedViews' => 'month']],
            $subject::settingsOf(['plugin' => [
                'tx_cal' => ['settings' => ['pidList' => '5', 'view' => ['allowedViews' => 'list']]],
                'tx_cal_controller' => [
                    'settings' => ['view' => ['allowedViews' => 'month']],
                    'view' => ['templateRootPaths' => ['EXT:cal/']],
                    'persistence' => ['storagePid' => '1'],
                ],
            ]]),
        );
    }

    #[Test]
    #[IgnoreDeprecations]
    public function legacySettingsOfCal1AreDeprecated(): void
    {
        $this->expectUserDeprecationMessage('cal: settings below "plugin.tx_cal_controller" (pidList, view) are deprecated, use "plugin.tx_cal.settings" (or "plugin.tx_cal_controller.settings") instead.');
        self::assertSame(
            ['pidList' => '7', 'view' => ['allowedViews' => 'week', 'weekStartDay' => 'sunday']],
            self::subject([])::settingsOf(['plugin' => ['tx_cal_controller' => [
                'pidList' => '7',
                'view' => ['allowedViews' => 'day', 'weekStartDay' => 'sunday', 'templateRootPaths' => ['EXT:cal/']],
                'settings' => ['view' => ['allowedViews' => 'week']],
            ]]]),
        );
    }
}
