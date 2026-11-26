<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Model\MonthView;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Creates the month view model with the events.
 *
 * Variables: monthObject, weekdayObject0 to weekdayObject6 (the days of the first week, for the weekday names)
 */
final class MonthViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('events', 'array', 'The event array', true);
        $this->registerArgument('dateObject', \DateTime::class, 'A date of the month (default: today)', false);
    }

    public function render(): mixed
    {
        $monthDate = isset($this->arguments['dateObject'])
            ? CalendarDateTime::generateFromDateTime($this->arguments['dateObject'])
            : new CalendarDateTime();
        $monthModel = new MonthView($monthDate->getMonth(), $monthDate->getYear());
        $monthModel->setCurrent(new CalendarDateTime());

        $variables = $this->renderingContext->getVariableProvider();
        $weeks = $monthModel->getWeeks();
        $count = 0;
        foreach (reset($weeks)->getDays() as $day) {
            $variables->add('weekdayObject' . $count++, $day->getTime());
        }
        foreach ($this->arguments['events'] as $event) {
            $monthModel->addEvent($event);
        }
        $variables->add('monthObject', $monthModel);
        return $this->renderChildren();
    }
}
