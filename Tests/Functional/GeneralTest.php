<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional;

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
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The settings of the plugin (trait General of the ContentController)
 */
#[CoversClass(ContentController::class)]
final class GeneralTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

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

    private static function flexForm(array $fields): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data>';
        foreach ($fields as $sheet => $sheetFields) {
            $xml .= '<sheet index="' . $sheet . '"><language index="lDEF">';
            foreach ($sheetFields as $field => $value) {
                $xml .= '<field index="' . $field . '"><value index="vDEF">' . htmlspecialchars($value) . '</value></field>';
            }
            $xml .= '</language></sheet>';
        }
        return $xml . '</data></T3FlexForms>';
    }

    #[Test]
    public function flexFormValuesOverrideTypoScript(): void
    {
        $subject = self::subject(['view' => ['allowedViews' => 'list', 'day' => ['dayStart' => '0700', 'dayEnd' => '2300'], 'list' => ['maxEvents' => '10']]]);
        $subject->initSettings(['pi_flexform' => self::flexForm([
            'sDEF' => ['allowedViews' => 'month,list', 'weekStartDay' => 'sunday', 'calendarSelection' => '1,2', 'calendarMode' => '1'],
            'day' => ['dayStart' => '0800', 'dayEnd' => '', 'gridLength' => '30'],
            'list' => ['maxEvents' => '5', 'showAllImages' => '1', 'starttime' => 'today', 'endtime' => '+2 weeks'],
            'other' => ['eventViewPid' => '12', 'showIcsLinks' => '0', 'categorySelection' => '3', 'categoryMode' => '2', 'myTS' => "view.list.heading.dateFormat = yyyy\nview.day.dayEnd = 2200", 'settings.view.custom' => 'x'],
        ])]);
        $settings = $subject->getSettings();
        self::assertSame('month,list', $settings['view']['allowedViews']);
        self::assertSame('sunday', $settings['view']['weekStartDay']);
        self::assertSame(['dayStart' => '0800', 'dayEnd' => '2200', 'gridLength' => '30'], $settings['view']['day']);
        self::assertSame('5', $settings['view']['list']['maxEvents']);
        self::assertSame('1', $settings['view']['list']['event']['card']['showAllImages']);
        self::assertSame(['today', '+2 weeks'], [$settings['view']['list']['starttime'], $settings['view']['list']['endtime']]);
        self::assertSame('12', $settings['view']['event']['eventViewPid']);
        // "0" is a value
        self::assertSame('0', $settings['view']['ics']['showIcsLinks']);
        self::assertSame(['3', '2', '1,2', '1'], [$settings['view']['category'], $settings['view']['categoryMode'], $settings['view']['calendar'], $settings['view']['calendarMode']]);
        self::assertSame('yyyy', $settings['view']['list']['heading']['dateFormat']);
        // unknown fields are not taken over
        self::assertArrayNotHasKey('settings', $settings);

        $subject->setSettings(['a' => 1]);
        self::assertSame(['a' => 1], $subject->getSettings());
    }

    #[Test]
    public function typoScriptCanIgnoreTheFlexForm(): void
    {
        $flexForm = self::flexForm(['sDEF' => ['allowedViews' => 'month', 'maxEvents' => '5', 'dayStart' => '0800']]);
        $subject = self::subject(['dontListenToFlexForm' => '1', 'view' => ['allowedViews' => 'list']]);
        $subject->initSettings(['pi_flexform' => $flexForm]);
        self::assertSame('list', $subject->getSettings()['view']['allowedViews']);

        // single fields
        $subject = self::subject(['dontListenToFlexForm' => ['list' => ['maxEvents' => '1'], 'day' => ['dayStart' => '0']], 'view' => ['list' => ['maxEvents' => '10']]]);
        $subject->initSettings(['pi_flexform' => $flexForm]);
        self::assertSame('month', $subject->getSettings()['view']['allowedViews']);
        self::assertSame('10', $subject->getSettings()['view']['list']['maxEvents']);
        self::assertSame('0800', $subject->getSettings()['view']['day']['dayStart']);
    }

    #[Test]
    public function pluginsOfThePageWithoutContentElement(): void
    {
        $content = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        $content->insert('tt_content', ['uid' => 1, 'pid' => 3, 'sorting' => 1, 'CType' => 'cal_controller', 'pi_flexform' => self::flexForm(['sDEF' => ['allowedViews' => 'list', 'maxEvents' => '5']])]);
        $content->insert('tt_content', ['uid' => 2, 'pid' => 3, 'sorting' => 2, 'CType' => 'cal_controller', 'pi_flexform' => self::flexForm(['sDEF' => ['allowedViews' => 'event,ics', 'maxEvents' => '']])]);
        $content->insert('tt_content', ['uid' => 3, 'pid' => 3, 'sorting' => 3, 'CType' => 'cal_controller', 'hidden' => 1, 'pi_flexform' => self::flexForm(['sDEF' => ['allowedViews' => 'month']])]);
        $content->insert('tt_content', ['uid' => 4, 'pid' => 3, 'sorting' => 4, 'CType' => 'text', 'pi_flexform' => self::flexForm(['sDEF' => ['allowedViews' => 'week']])]);
        $content->insert('tt_content', ['uid' => 5, 'pid' => 4, 'sorting' => 1, 'CType' => 'cal_controller', 'pi_flexform' => self::flexForm(['sDEF' => ['allowedViews' => 'day']])]);

        $subject = self::subject(['view' => ['allowedViews' => '', 'list' => ['maxEvents' => '10']]]);
        $subject->initSettings(null, 3);
        self::assertSame(['allowedViews' => 'event,ics', 'list' => ['maxEvents' => '5']], $subject->getSettings()['view']);
    }

    #[Test]
    public function pluginsWithoutOrWithInvalidFlexForm(): void
    {
        foreach ([null, [], ['pi_flexform' => ''], ['pi_flexform' => '<invalid'], ['pi_flexform' => '<T3FlexForms><data><sheet index="sDEF">x</sheet></data></T3FlexForms>']] as $contentElement) {
            $subject = self::subject(['view' => ['allowedViews' => 'list']]);
            $subject->initSettings($contentElement);
            self::assertSame(['view' => ['allowedViews' => 'list']], $subject->getSettings());
        }
    }
}
