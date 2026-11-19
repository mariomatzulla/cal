<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ZuluDateTimeViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('date', \DateTimeInterface::class, 'Das Date Objekt', true);
        $this->registerArgument('time', \DateTimeInterface::class, 'Das Time Objekt', true);
    }

    /**
     * Convert DateTime to UTC/Zulu format
     */
    public function render(): string
    {
        $date = $this->arguments['date'];
        $time = $this->arguments['time'];

        if (!$date instanceof \DateTimeInterface) {
            return '';
        }
        if (!$time instanceof \DateTimeInterface) {
            return '';
        }

        // Klonen, um das Original-Objekt nicht zu verändern
        $utc = new \DateTimeZone('UTC');
        $utcDate = \DateTimeImmutable::createFromInterface($date)->setTimezone($utc);
        $utcTime = \DateTimeImmutable::createFromInterface($time)->setTimezone($utc);

        // Gibt das ICS-kompatible Format zurück (YYYYMMDDTHHMMSSZ)
        return $utcDate->format('Ymd') . 'T' . $utcTime->format('His') . 'Z';
    }
}
