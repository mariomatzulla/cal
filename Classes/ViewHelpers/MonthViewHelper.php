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
        $this->registerArgument('offset', 'string', 'A number', false, '0');
        $this->registerArgument('monthOfYear', 'integer', 'A number', false);
        $this->registerArgument('dateObject', '\DateTime', 'A \DateTime object', false);
        $this->registerArgument('getdate', '\DateTime', 'The selected date. A \DateTime object', false);
    }

    public function render()
    {
        $monthDate = null;
        if (isset($this->arguments['dateObject'])) {
            $monthDate = CalendarDateTime::generateFromDateTime($this->arguments ['dateObject']);
        } elseif (isset($this->arguments['monthOfYear']) && isset($this->arguments['getdate'])) {
            $monthDate = clone $this->arguments['getdate'];
            $monthDate->setMonth((int)($this->arguments['monthOfYear']));
        } else {
            $monthDate = new CalendarDateTime();
        }
        $this->renderingContext->getVariableProvider()->add('monthDateObject', $monthDate);

        $monthModel = new MonthView($monthDate->getMonth(), $monthDate->getYear());

        $today = new CalendarDateTime();
        $monthModel->setCurrent($today);

        if (isset($this->arguments['getdate'])) {
            $selected = CalendarDateTime::generateFromDateTime($this->arguments['getdate']);
            $monthModel->setSelected($selected);
        }

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
