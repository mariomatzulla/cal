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
 * A private event (iCalendar CLASS PRIVATE / CONFIDENTIAL) on the website: only the time is shown,
 * with a neutral title ("Busy"), without texts, location, organizer, categories, images and links.
 */
class EventBusyModel extends EventRecModel
{
    public function __construct(Event $event, float $start, float $end, private readonly string $busyTitle)
    {
        parent::__construct($event, $start, $end);
    }

    public function isBusy(): bool
    {
        return true;
    }

    public function getTitle()
    {
        return $this->busyTitle;
    }

    public function getDescription()
    {
        return '';
    }

    public function getTeaser()
    {
        return '';
    }

    public function getCaldavData()
    {
        return '';
    }

    public function getLocation()
    {
        return '';
    }

    public function getOrganizer()
    {
        return '';
    }

    public function getLocationId()
    {
        return 0;
    }

    public function getOrganizerId()
    {
        return 0;
    }

    public function getLocationObject()
    {
        return null;
    }

    public function getOrganizerObject()
    {
        return null;
    }

    public function getLocationPid(): int
    {
        return 0;
    }

    public function getLocationLink(): string
    {
        return '';
    }

    public function getOrganizerPid(): int
    {
        return 0;
    }

    public function getOrganizerLink(): string
    {
        return '';
    }

    public function getType(): int
    {
        return 0;
    }

    public function getPage(): int
    {
        return 0;
    }

    public function getExtUrl(): string
    {
        return '';
    }

    public function getCategories()
    {
        return [];
    }

    public function getImage()
    {
        return [];
    }
}
