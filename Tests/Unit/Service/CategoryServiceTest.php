<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Unit\Service;

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
use MarioMatzulla\Cal\Domain\Model\Category;
use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Events\FilterEventsEvent;
use MarioMatzulla\Cal\Service\CategoryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CategoryService::class)]
final class CategoryServiceTest extends UnitTestCase
{
    /**
     * @return array<string, Event> title => event with the categories of the title
     */
    private static function events(): array
    {
        $events = [];
        foreach (['none' => [], '1' => [1], '2' => [2], '1+2' => [1, 2], '1+3' => [1, 3]] as $title => $uids) {
            $event = new Event();
            $event->setTitle((string)$title);
            $categories = [];
            foreach ($uids as $uid) {
                $category = new Category();
                $category->_setProperty('uid', $uid);
                $categories[] = $category;
            }
            $event->setCategories($categories);
            $events[] = $event;
        }
        $events[] = new Event();
        end($events)->setTitle('null');
        return $events;
    }

    public static function filterDataProvider(): array
    {
        return [
            'all' => [['categoryMode' => '0', 'categoryIds' => ['1']], ['none', '1', '2', '1+2', '1+3', 'null']],
            'no mode' => [[], ['none', '1', '2', '1+2', '1+3', 'null']],
            'unknown mode' => [['categoryMode' => 4, 'categoryIds' => ['1']], ['none', '1', '2', '1+2', '1+3', 'null']],
            'exact' => [['categoryMode' => 1, 'categoryIds' => ['2', '1', '1', '']], ['1+2']],
            'exact, one category' => [['categoryMode' => 1, 'categoryIds' => [1]], ['1']],
            'exact without selection' => [['categoryMode' => 1], []],
            'exclude' => [['categoryMode' => 2, 'categoryIds' => [1]], ['none', '2', 'null']],
            'exclude without selection' => [['categoryMode' => 2, 'categoryIds' => []], ['none', '1', '2', '1+2', '1+3', 'null']],
            'any' => [['categoryMode' => 3, 'categoryIds' => [3, 2]], ['2', '1+2', '1+3']],
            'any without selection' => [['categoryMode' => 3], []],
        ];
    }

    /**
     * @param array<string, mixed> $view
     * @param list<string> $expected titles
     */
    #[Test]
    #[DataProvider('filterDataProvider')]
    public function filtersTheEventsByCategory(array $view, array $expected): void
    {
        $event = new FilterEventsEvent(self::events(), $view === [] ? [] : ['view' => $view]);
        (new CategoryService())($event);
        self::assertSame($expected, array_map(static fn(Event $event): string => $event->getTitle(), $event->getEvents()));
    }
}
