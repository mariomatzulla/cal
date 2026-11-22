<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Model\MonthView;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class MonthViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {

        // registerArgument($name, $type, $description, $required, $defaultValue, $escape)
        $this->registerArgument('events', 'array', 'The event array', true);
        $this->registerArgument('dateObject', '\DateTime', 'A date of the month (default: today)', false);
    }

    public function render()
    {
        $monthDate = isset($this->arguments['dateObject'])
            ? CalendarDateTime::generateFromDateTime($this->arguments ['dateObject'])
            : new CalendarDateTime();
        $this->renderingContext->getVariableProvider()->add('monthDateObject', $monthDate);

        $monthModel = new MonthView($monthDate->getMonth(), $monthDate->getYear());

        $today = new CalendarDateTime();
        $monthModel->setCurrent($today);


        $weeks = $monthModel->getWeeks();
        $week = reset($weeks);
        $count = 0;
        foreach ($week->getDays() as $day) {
            $this->renderingContext->getVariableProvider()->add('weekdayObject' . $count++, $day->getTime());
        }

        foreach ($this->arguments ['events'] as $event) {
            $monthModel->addEvent($event);
        }

        $this->renderingContext->getVariableProvider()->add('monthObject', $monthModel);
        return $this->renderChildren();
    }

}
