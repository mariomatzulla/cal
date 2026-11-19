<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Domain\Model;

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
 * System category (sys_category) with the color of its events (field tx_cal_color)
 * and the priority of this color (field tx_cal_color_priority).
 */
class Category extends \TYPO3\CMS\Extbase\Domain\Model\Category
{
    protected string $color = '';

    protected int $colorPriority = 0;

    /**
     * Color in hex notation (#rrggbb), empty if none is set
     */
    public function getColor(): string
    {
        return self::isValidColor($this->color) ? strtolower($this->color) : '';
    }

    public function setColor(?string $color): void
    {
        $this->color = (string)$color;
    }

    public function getColorPriority(): int
    {
        return $this->colorPriority;
    }

    public function setColorPriority(?int $colorPriority): void
    {
        $this->colorPriority = (int)$colorPriority;
    }

    /**
     * Level in the category tree: 0 = root category, 1 = its sub category, ...
     */
    public function getDepth(): int
    {
        $depth = 0;
        $visited = [];
        $parent = $this->getParent();
        while ($parent !== null && !isset($visited[$parent->getUid()]) && $depth < 50) {
            $visited[$parent->getUid()] = true;
            $depth++;
            $parent = $parent->getParent();
        }
        return $depth;
    }

    public static function isValidColor(?string $color): bool
    {
        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', (string)$color) === 1;
    }
}
