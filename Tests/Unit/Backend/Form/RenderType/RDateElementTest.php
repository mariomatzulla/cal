<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Backend\Form\RenderType;

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
use MarioMatzulla\Cal\Backend\Form\RenderType\RDateElement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Form\NodeInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(RDateElement::class)]
final class RDateElementTest extends UnitTestCase
{
    use FormElementTestTrait;

    /**
     * @var list<array<string, mixed>> data of the date fields
     */
    private array $fields = [];

    private function render(array $row): string
    {
        $element = $this->createElement(RDateElement::class, $row);
        $nodeFactory = self::createStub(NodeFactory::class);
        $nodeFactory->method('create')->willReturnCallback(function (array $data): NodeInterface {
            $this->fields[] = $data;
            $node = $this->createStub(NodeInterface::class);
            $value = $data['parameterArray']['itemFormElValue'];
            $node->method('render')->willReturn(['html' => '[field ' . $data['fieldName'] . ' ' . ($value === null ? 'empty' : $value->format('Y-m-d H:i:s')) . ']']);
            return $node;
        });
        $element->injectNodeFactory($nodeFactory);
        $result = $element->render();
        self::assertCount(2, $result['javaScriptModules']);
        return $result['html'];
    }

    #[Test]
    public function datesWithoutTime(): void
    {
        $html = $this->render(['rdate_type' => ['date'], 'rdate' => '20261110, 20261224,']);
        self::assertSame(['date', 'date', 'date'], array_map(static fn(array $field): string => $field['parameterArray']['fieldConf']['config']['format'], $this->fields));
        self::assertSame('data[tx_cal_domain_model_event][7][rdate1]', $this->fields[1]['parameterArray']['itemFormElName']);
        // each field in the wrapper of the JavaScript, an empty field for a new date
        self::assertStringContainsString('<div class="cal-rdate-datetime-rendering">[field rdate0 2026-11-10 00:00:00]</div>', $html);
        self::assertStringContainsString('[field rdate1 2026-12-24 00:00:00]', $html);
        self::assertStringContainsString('[field rdate2 empty]', $html);
        self::assertStringContainsString('id="data_tx_cal_domain_model_event_7_rdate" value="20261110, 20261224,"', $html);
        self::assertSame(3, substr_count($html, '[icon]tx_cal_event.remove_recurrence'));
        self::assertStringNotContainsString('rdateYear', $html);
    }

    #[Test]
    public function datesWithTime(): void
    {
        $html = $this->render(['rdate_type' => 'date_time', 'rdate' => '20261110T143000Z,2026-11-11T15:00']);
        self::assertSame('datetime', $this->fields[0]['processedTca']['columns']['rdate0']['config']['format']);
        self::assertStringContainsString('[field rdate0 2026-11-10 14:30:00]', $html);
        self::assertStringContainsString('[field rdate1 2026-11-11 15:00:00]', $html);
    }

    #[Test]
    public function periods(): void
    {
        $html = $this->render(['rdate_type' => ['period'], 'rdate' => '20261110T140000Z/P1Y2M3W4DT5H6M,invalid/PT']);
        self::assertStringContainsString('[field rdate0 2026-11-10 14:00:00]', $html);
        self::assertStringContainsString('[field rdate1 empty]', $html);
        foreach (['Year' => 1, 'Month' => 2, 'Week' => 3, 'Day' => 4, 'Hour' => 5, 'Minute' => 6] as $field => $value) {
            self::assertStringContainsString('value="' . $value . '" class="form-control rdateChanged rdate-change-action" name="rdate' . $field . '0"', $html);
            self::assertStringContainsString('value="0" class="form-control rdateChanged rdate-change-action" name="rdate' . $field . '1"', $html);
            self::assertStringContainsString('l_' . strtolower($field), $html);
        }
        self::assertStringContainsString('l_duration', $html);
    }
}
