<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\ViewHelpers;

use MarioMatzulla\Cal\Domain\Model\Event;
use MarioMatzulla\Cal\Model\CalendarDateTime;
use MarioMatzulla\Cal\Model\WeekView;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

abstract class BaseWeekViewHelper extends AbstractViewHelper
{
    /**
     * @var int
     */
    protected $rowspan = 0;

    /**
     * @var int
     */
    protected $eventCount = 0;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var int
     */
    private $startOffset;

    /**
     * @param \MarioMatzulla\Cal\Model\WeekView $week
     * @param bool $includeAlldayEvents
     * @param bool $includeNormalEvents
     */
    protected function generateTheRenderingMatrix($week, $includeAlldayEvents = true, $includeNormalEvents = true)
    {
        //for alldays;
        $lengthArray = [];
        foreach ($week->getDays() as $day) {
            foreach ($day->getEvents() as $events) {
                foreach ($events as $event) {
                    if ($includeAlldayEvents && $event->isAllday()) {
                        $this->eventCount++;
                        $lengthArray = $this->addEventIntoTheArray($week, $lengthArray, $event);
                    } elseif ($includeNormalEvents && !$event->isAllday()) {
                        $this->eventCount++;
                        $lengthArray = $this->addEventIntoTheArray($week, $lengthArray, $event);
                    }
                }
            }
        }
        //longest events first
        krsort($lengthArray);

        $theMatrix = [];
        $lengthArrayKeys = array_keys($lengthArray);
        $this->rowspan = 1;
        foreach ($lengthArrayKeys as $lengthArrayKey) {
            $values = explode('_', (string)$lengthArrayKey);

            $eventArrayKeys = array_keys($lengthArray [$lengthArrayKey]);
            foreach ($eventArrayKeys as $eventArrayKey) {
                $done = false;
                for ($i = $values [1]; $i < 7 && ! $done; $i++) {
                    for ($j = 0; $j < 1000 && ! $done; $j++) {
                        if (! isset($theMatrix [$i] [$j]) && $values [0] + $i < 8) {
                            // Found an empty start spot
                            $empty = true;
                            for ($k = $i; $k < $values [0] + $i && $empty; $k++) {
                                if (isset($theMatrix [$k] [$j])) {
                                    $empty = false;
                                }
                            }
                            if ($empty) {
                                $theMatrix [$i] [$j] = $lengthArray [$lengthArrayKey] [$eventArrayKey];
                                //FIXME This is bad: we should not manipulate the event to store a rendering variable
                                $theMatrix [$i] [$j]->matrixValue = $values [0];
                                // fill it
                                for ($k = $i + 1; $k < $values [0] + $i && $empty; $k++) {
                                    $theMatrix [$k] [$j] = true;
                                }
                                $done = true;
                            }
                        }
                    }
                    if ($this->rowspan < $j) {
                        $this->rowspan = $j;
                    }
                }
            }
        }

        return $theMatrix;
    }

    /**
     * @param WeekView $week
     * @param array $lengthArray An array with key = length _ start - of the event
     * @param Event $event
     */
    protected function addEventIntoTheArray($week, $lengthArray, $event)
    {

        $eventStart = $event->getStart()->format('Ymd');
        $eventEnd = $event->getEnd()->format('Ymd');
        if ($eventStart <= $week->getWeekStart()) {
            if ($eventEnd >= $week->getWeekEnd()) {
                // lasts the whole week
                $length = 7;
                $start = 0;
            } else {
                $length = (int)($event->getEnd()->getDayOfWeek());
                if ($length === 0) {
                    if (DATE_CALC_BEGIN_WEEKDAY == 1) {
                        $length = 7;
                    } else {
                        $length = 1;
                    }
                }
                $start = 0;
            }
        } else {
            $start = (int)($event->getStart()->getDayOfWeek());
            if (DATE_CALC_BEGIN_WEEKDAY === 1) {
                $start--;
                if ($start === - 1) {
                    $start = 6;
                }
            }
            if ($eventEnd >= $week->getWeekEnd()) {
                $length = 7 - $start;
            } else {
                $weekEnd = (int)($event->getEnd()->getDayOfWeek());
                if (DATE_CALC_BEGIN_WEEKDAY === 1) {
                    $weekEnd--;
                    if ($weekEnd === - 1) {
                        $weekEnd = 6;
                    }
                }
                $length = ($weekEnd - $start) + 1;
            }
        }
        $lengthArray [$length . '_' . $start] [] = $event;
        return $lengthArray;
    }

    protected function getHourCells(): array
    {

        $dayStart = (string)($this->settings ['view'] ['day'] ['dayStart'] ?? '0700'); // '0700'; // Start time for day grid
        $dayEnd = (string)($this->settings ['view'] ['day'] ['dayEnd'] ?? '2300'); // '2300'; // End time for day grid

        while (strlen($dayStart) < 6) {
            $dayStart .= '0';
        }
        while (strlen($dayEnd) < 6) {
            $dayEnd .= '0';
        }

        $count = ((int)$dayEnd - (int)$dayStart) / 10000;
        $hourCells = [];
        for ($i = 0; $i < $count; $i++) {
            $hourCells[] = $i;
        }
        return $hourCells;
    }

    protected function getTimeCells(string $view): array
    {

        $dayStart = (string)($this->settings ['view'] ['day'] ['dayStart'] ?? '0700'); // '0700'; // Start time for day grid
        $dayEnd = (string)($this->settings ['view'] ['day'] ['dayEnd'] ?? '2300'); // '2300'; // End time for day grid

        $dTimeStart = [];
        $dTimeEnd = [];
        preg_match('/([0-9]{2})([0-9]{2})([0-9]{2})/', sprintf('%06d', (int)$dayStart), $dTimeStart);
        preg_match('/([0-9]{2})([0-9]{2})([0-9]{2})/', sprintf('%06d', (int)$dayEnd), $dTimeEnd);

        $start = new CalendarDateTime();
        $start->setTime((int)$dTimeStart[1], (int)$dTimeStart[2], (int)$dTimeStart[3]);
        $end = clone $start;
        $end->setTime((int)$dTimeEnd[1], (int)$dTimeEnd[2], (int)$dTimeEnd[3]);

        $timeCells = [];
        while ($end->after($start)) {
            $timeCells[] = ['time' => clone $start, 'value' => $start->getHour()];
            $start->addSeconds(3600);
        }
        return $timeCells;
    }

    protected function getEventsColumn($day): array
    {

        $events = [];
        foreach ($day->getEvents() as $eventArrayTmp) {
            foreach ($eventArrayTmp as $event) {
                if ((!$event->isAllday())) {
                    $events[$event->getStart()->format('YmdHi')][] = $event;
                }
            }
        }

        $dayStart = (string)($this->settings ['view'] ['day'] ['dayStart'] ?? '0700'); // '0700'; // Start time for day grid
        $dayEnd = (string)($this->settings ['view'] ['day'] ['dayEnd'] ?? '2300'); // '2300'; // End time for day grid
        $gridLength = $this->settings ['view'] ['day'] ['gridLength']; // '15'; // Grid distance in minutes for day view, multiples of 15 preferred

        if ($gridLength === 0) {
            $gridLength = 15;
        }

        $dTimeStart = [];
        $dTimeEnd = [];
        preg_match('/([0-9]{2})([0-9]{2})([0-9]{2})/', sprintf('%06d', (int)$dayStart), $dTimeStart);
        preg_match('/([0-9]{2})([0-9]{2})([0-9]{2})/', sprintf('%06d', (int)$dayEnd), $dTimeEnd);

        $start = clone $day->getTime();
        $start->setTime((int)$dTimeStart[1], (int)$dTimeStart[2], (int)$dTimeStart[3]);
        $end = clone $start;
        $end->setTime((int)$dTimeEnd[1], (int)$dTimeEnd[2], (int)$dTimeEnd[3]);

        // splitting the events into H:M, to find out if events run in parallel
        $i = new CalendarDateTime();
        $eventArray = [];
        $viewArray = [];
        $positionArray = [];

        $timeKeys = array_keys($events);

        // Sort by starttime, otherwise $pos_array keys may be assigned multiple times and events may therefore overwrite each other
        asort($timeKeys);

        foreach ($timeKeys as $timeKey) {
            $eventKeys = array_keys($events [$timeKey]);
            foreach ($eventKeys as $eventKey) {
                $currentEvent = $events [$timeKey] [$eventKey];

                if (! $currentEvent->isAllday() && ($currentEvent->getStart()->format('Ymd') == $currentEvent->getEnd()->format('Ymd'))) {
                    $eventMappingKey = $currentEvent->getModelType() . '_' . $currentEvent->getUid() . '_' . $currentEvent->getStart()->format('YmdHis');
                    $eventArray [$eventMappingKey] = $currentEvent;

                    $i = clone $currentEvent->getStart();
                    $i->subtractSeconds(($currentEvent->getStart()->format('U') % ($gridLength * 60)));
                    if ($i->before($start)) {
                        $i = clone $start;
                    }
                    $entries = 0;
                    while ($i->before($currentEvent->getEnd())) {
                        $ymd = $i->format('Ymd');
                        $hm = $i->format('Hi');
                        $viewArray [$ymd] [$hm] [] = $eventMappingKey;
                        $entries++;

                        $count = count($viewArray [$ymd] [$hm]);

                        foreach ($viewArray [$ymd] [$hm] as $mappingKey) {
                            if (! isset($positionArray [$mappingKey]) || $positionArray [$mappingKey] < $count) {
                                $positionArray [$mappingKey] = $count;
                            }
                        }
                        $i->addSeconds($gridLength * 60);
                    }
                }
            }
        }

        if (! empty($viewArray [$day->getYmd()])) {
            $max = [];
            foreach ($viewArray [$day->getYmd()] as $array_time => $time_val) {
                $c = count($viewArray [$day->getYmd()] [$array_time]);
                $max[] = $c;
            }
            $nbrGridCols = max($max);
        } else {
            $nbrGridCols = 1;
        }

        // splitting the events into H:M, to find out if events run in parallel
        $pos_array = [];
        $i = clone $start;
        $t_array = [];
        $ymdOfDay = $day->getYmd();

        while ($i->before($end)) {
            $timeframe = $i->format('Hi');
            $entrysOfTimeframe = $viewArray [$ymdOfDay] [$timeframe] ?? [];
            if (count($entrysOfTimeframe) > 0) {
                foreach ($entrysOfTimeframe as $eventKey) {
                    $event = $eventArray [$eventKey];
                    $eventStart = $event->getStart();
                    $eventMappingKey = $event->getModelType() . '_' . $event->getUid() . '_' . $eventStart->format('YmdHis');
                    if (array_key_exists($eventMappingKey, $pos_array)) {
                        $eventEnd = $event->getEnd();
                        $eventEnd->subtractSeconds((($eventEnd->getMinute() % $gridLength) * 60));
                        if ($timeframe >= $eventEnd->format('Hi')) {
                            $t_array [$timeframe] [$pos_array [$eventMappingKey]] =  [

                                'ended' => $eventMappingKey,
                            ];
                        } else {
                            $t_array [$timeframe] [$pos_array [$eventMappingKey]] =  [

                                'started' => $eventMappingKey,
                            ];
                        }
                    } else {
                        for ($j = 0; $j < $nbrGridCols; $j++) {
                            if (! isset($t_array [$timeframe] [$j]) || ((is_array($t_array [$timeframe] [$j]) || is_countable($t_array [$timeframe] [$j])) && count($t_array [$timeframe] [$j]) == 0)) {
                                $pos_array [$eventMappingKey] = $j;
                                $t_array [$timeframe] [$j] =  [

                                    'begin' => $eventMappingKey,
                                ];
                                break;
                            }
                        }
                    }
                }
            } else {
                $t_array [$timeframe] = '';
            }

            $i->addSeconds($gridLength * 60);
        }
        $daydisplay = [];
        $cal_time_obj = clone $day->getTime();
        foreach ($t_array as $cal_time => $val) {

            $dTimeStart = [];
            preg_match('/([0-9]{2})([0-9]{2})/', sprintf('%04d', (int)$cal_time), $dTimeStart);
            $cal_time_obj->setTime((int)$dTimeStart [1], (int)$dTimeStart [2], 0);

            if ($val != '' && count($val) > 0) {
                for ($i = 0, $iMax = count($val); $i < $iMax; $i++) {
                    if (! empty($val [$i])) {
                        $keys = array_keys($val [$i]);
                        if ($keys [0] === 'begin') {
                            $event = $eventArray [$val [$i] [$keys [0]]];

                            $colSpan = $positionArray [$val [$i] [$keys [0]]];
                            // left
                            // 1 = 0
                            // 2 = 50
                            // 3 = 33.333
                            // 4 = 25

                            $left = 0;
                            if ($colSpan > 1) {
                                $left = 100 / $colSpan * $i;
                            }

                            // width
                            // 1 = 100
                            // 2 = 85,50
                            // 3 = 56.666, 56.666, 33.333
                            // 4 = 42.5, 42.5, 42.5, 25
                            // 5 = 34,34,34,34,20

                            $width = 100;
                            if ($colSpan > 1) {
                                //TODO make this magic number 145 a constant
                                $width = 145 / ($colSpan + 1);
                            }
                            $daydisplay[] = ['left' => $left, 'width' => $width, 'colspan' => $colSpan, 'top' => $this->getTop($event), 'length' => $this->getLength($event), 'event' => $event];

                            // End event drawing
                        }
                    }
                }
            }
        }
        return $daydisplay;
    }

    protected function getTop($event)
    {
        $x = $event->getStart()->getHour() + $event->getStart()->getMinute() / 60 - $this->getStartOffset();
        return ($x * 42) - 3;
    }

    protected function getStartOffset()
    {

        if (! isset($this->startOffset)) {
            $dayStart = (string)($this->settings ['view'] ['day'] ['dayStart'] ?? '0700'); // '0700'; // Start time for day grid
            while (strlen($dayStart) < 6) {
                $dayStart .= '0';
            }
            $d_start = new CalendarDateTime();
            $dTimeStart = [];
            preg_match('/([0-9]{2})([0-9]{2})([0-9]{2})/', $dayStart, $dTimeStart);
            $d_start->setTime((int)$dTimeStart[1], (int)$dTimeStart[2], (int)$dTimeStart[3]);
            $this->startOffset = $d_start->getHour() + $d_start->getMinute() / 60;
        }
        return $this->startOffset;
    }

    protected function getLength($event)
    {
        $x = $event->getEnd()->format('U') - $event->getStart()->format('U');
        return ($x / 3600 * 42) - 3;
    }
}
