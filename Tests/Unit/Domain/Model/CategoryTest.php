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
use MarioMatzulla\Cal\Domain\Model\Category;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Category::class)]
final class CategoryTest extends UnitTestCase
{
    public static function colorDataProvider(): array
    {
        return [
            'six digits' => ['#AABBCC', true, '#aabbcc'],
            'three digits' => ['#abc', true, '#abc'],
            'without hash' => ['aabbcc', false, ''],
            'invalid digits' => ['#ggg', false, ''],
            'four digits' => ['#abcd', false, ''],
            'empty' => ['', false, ''],
            'null' => [null, false, ''],
        ];
    }

    #[Test]
    #[DataProvider('colorDataProvider')]
    public function colorIsOnlyReturnedWhenValid(?string $color, bool $valid, string $expected): void
    {
        self::assertSame($valid, Category::isValidColor($color));
        $category = new Category();
        $category->setColor($color);
        self::assertSame($expected, $category->getColor());
    }

    #[Test]
    public function colorPriority(): void
    {
        $category = new Category();
        self::assertSame(0, $category->getColorPriority());
        $category->setColorPriority(5);
        self::assertSame(5, $category->getColorPriority());
        $category->setColorPriority(null);
        self::assertSame(0, $category->getColorPriority());
    }

    private static function category(int $uid, ?Category $parent = null): Category
    {
        $category = new Category();
        $category->_setProperty('uid', $uid);
        if ($parent !== null) {
            $category->setParent($parent);
        }
        return $category;
    }

    #[Test]
    public function getDepthCountsTheParents(): void
    {
        $root = self::category(1);
        self::assertSame(0, $root->getDepth());
        self::assertSame(2, self::category(3, self::category(2, $root))->getDepth());
    }

    #[Test]
    public function getDepthStopsAtLoops(): void
    {
        $first = self::category(1);
        $second = self::category(2, $first);
        $first->setParent($second);
        self::assertSame(2, $first->getDepth());
    }

    #[Test]
    public function getDepthIsLimited(): void
    {
        $category = self::category(1);
        for ($uid = 2; $uid < 60; $uid++) {
            $category = self::category($uid, $category);
        }
        self::assertSame(50, $category->getDepth());
    }
}
