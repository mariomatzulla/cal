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
     * Start of the time grid in hours (e.g. 7.5 = 07:30)
     */
    private ?float $startOffset = null;

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
                    $timed = $this->isTimedSingleDayEvent($event);
                    if ($includeAlldayEvents && !$timed) {
                        $this->eventCount++;
                        $lengthArray = $this->addEventIntoTheArray($week, $lengthArray, $event);
                    } elseif ($includeNormalEvents && $timed) {
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
        // last day of the event: an end at midnight (00:00) belongs to the day before
        $lastDay = clone $event->getEnd();
        if (!$event->isAllday() && $lastDay->format('His') === '000000' && $lastDay->format('YmdHis') > $event->getStart()->format('YmdHis')) {
            $lastDay->subtractSeconds(1);
        }
        $eventEnd = $lastDay->format('Ymd');
        if ($eventStart <= $week->getWeekStart()) {
            if ($eventEnd >= $week->getWeekEnd()) {
                // lasts the whole week
                $length = 7;
                $start = 0;
            } else {
                $length = (int)($lastDay->getDayOfWeek());
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
                $weekEnd = (int)($lastDay->getDayOfWeek());
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
        [$startHour, $startMinute] = $this->getDayTime('dayStart', '070000');
        [$endHour, $endMinute] = $this->getDayTime('dayEnd', '230000');
        $count = (int)ceil((($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute)) / 60);
        return $count > 0 ? range(0, $count - 1) : [];
    }

    protected function getTimeCells(string $view): array
    {
        [$startHour, $startMinute, $startSecond] = $this->getDayTime('dayStart', '070000');
        [$endHour, $endMinute, $endSecond] = $this->getDayTime('dayEnd', '230000');

        $start = new CalendarDateTime();
        $start->setTime($startHour, $startMinute, $startSecond);
        $end = clone $start;
        $end->setTime($endHour, $endMinute, $endSecond);

        $timeCells = [];
        while ($end->after($start)) {
            $timeCells[] = ['time' => clone $start, 'value' => $start->getHour()];
            $start->addSeconds(3600);
        }
        return $timeCells;
    }

    /**
     * Events shown in the hours of the time grid: not all-day, on one day (an end at midnight belongs to the day).
     * All other events are shown in the all-day area.
     */
    protected function isTimedSingleDayEvent($event): bool
    {
        if ($event->isAllday()) {
            return false;
        }
        $start = $event->getStart();
        $end = $event->getEnd();
        if ($start->format('Ymd') === $end->format('Ymd')) {
            return true;
        }
        $nextDay = clone $start;
        $nextDay->setTime(0, 0, 0);
        $nextDay->addSeconds(86400);
        return $end->format('YmdHis') === $nextDay->format('Ymd') . '000000';
    }

    /**
     * Events of the all-day area which cover the given column (0 - 6) of the matrix
     *
     * @param array<int, array<int, mixed>> $matrix
     * @return list<object>
     */
    protected function getAlldayEventsOfColumn(array $matrix, int $column): array
    {
        $events = [];
        foreach ($matrix as $startColumn => $rows) {
            foreach ($rows as $event) {
                if (is_object($event) && $startColumn <= $column && $column < $startColumn + (int)$event->matrixValue) {
                    $events[] = $event;
                }
            }
        }
        return $events;
    }

    /**
     * Start or end of the time grid (settings.view.day.dayStart / dayEnd) as [hour, minute, second].
     * Accepts "HH", "HHMM" and "HHMMSS" (TypoScript: 070000, FlexForm: 070000 or 0700).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    protected function getDayTime(string $key, string $default): array
    {
        $value = (string)preg_replace('/\D/', '', (string)($this->settings ['view'] ['day'] [$key] ?? ''));
        if ($value === '') {
            $value = $default;
        }
        if (strlen($value) % 2 === 1) {
            $value = '0' . $value;
        }
        $value = substr(str_pad($value, 6, '0'), 0, 6);
        return [(int)substr($value, 0, 2), (int)substr($value, 2, 2), (int)substr($value, 4, 2)];
    }

    /**
     * Extends the time grid (dayStart / dayEnd) to full hours so that all timed events of the days are visible
     *
     * @param iterable<\MarioMatzulla\Cal\Model\DayView> $days
     */
    protected function extendDayTimesToEvents(iterable $days): void
    {
        [$startHour, $startMinute] = $this->getDayTime('dayStart', '070000');
        [$endHour, $endMinute] = $this->getDayTime('dayEnd', '230000');
        $gridStart = $startHour * 60 + $startMinute;
        $gridEnd = $endHour * 60 + $endMinute;
        foreach ($days as $day) {
            foreach ($day->getEvents() as $events) {
                foreach ($events as $event) {
                    if (!$this->isTimedSingleDayEvent($event)) {
                        continue;
                    }
                    $eventStart = (int)$event->getStart()->format('H') * 60 + (int)$event->getStart()->format('i');
                    $eventEnd = $event->getStart()->format('Ymd') === $event->getEnd()->format('Ymd')
                        ? (int)$event->getEnd()->format('H') * 60 + (int)$event->getEnd()->format('i')
                        : 24 * 60;
                    $gridStart = min($gridStart, intdiv($eventStart, 60) * 60);
                    $gridEnd = max($gridEnd, (int)ceil($eventEnd / 60) * 60);
                }
            }
        }
        $this->settings ['view'] ['day'] ['dayStart'] = sprintf('%02d%02d00', intdiv($gridStart, 60), $gridStart % 60);
        $this->settings ['view'] ['day'] ['dayEnd'] = sprintf('%02d%02d00', intdiv($gridEnd, 60), $gridEnd % 60);
        $this->startOffset = null;
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

        $gridLength = (int)($this->settings ['view'] ['day'] ['gridLength'] ?? 15); // Grid distance in minutes for day view, multiples of 15 preferred
        if ($gridLength <= 0) {
            $gridLength = 15;
        }

        [$startHour, $startMinute, $startSecond] = $this->getDayTime('dayStart', '070000');
        [$endHour, $endMinute, $endSecond] = $this->getDayTime('dayEnd', '230000');
        $start = clone $day->getTime();
        $start->setTime($startHour, $startMinute, $startSecond);
        $end = clone $start;
        $end->setTime($endHour, $endMinute, $endSecond);

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

                if ($this->isTimedSingleDayEvent($currentEvent)) {
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
                            [$offset, $duration] = $this->getGridPosition($event, $day);
                            $daydisplay[] = [
                                'left' => $left,
                                'width' => $width,
                                'colspan' => $colSpan,
                                'top' => $this->getTop($event),
                                'length' => $this->getLength($event),
                                // position in the time grid of cal >= 4.1: minutes since the start of the grid, side by side columns
                                'offset' => $offset,
                                'duration' => $duration,
                                'columnWidth' => round(100 / max(1, $colSpan), 4),
                                'columnLeft' => round($left, 4),
                                'event' => $event,
                            ];

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

    /**
     * Start (minutes since the start of the grid) and duration (minutes) of a timed event in the grid of a day,
     * limited to the grid (dayStart - dayEnd)
     *
     * @return array{0: int, 1: int}
     */
    protected function getGridPosition($event, $day): array
    {
        $gridStart = (int)round($this->getStartOffset() * 60);
        [$endHour, $endMinute] = $this->getDayTime('dayEnd', '230000');
        $gridEnd = $endHour * 60 + $endMinute;
        $midnight = clone $day->getTime();
        $midnight->setTime(0, 0, 0);
        $dayBegin = (int)$midnight->format('U');
        $start = (int)floor(((int)$event->getStart()->format('U') - $dayBegin) / 60);
        $end = (int)ceil(((int)$event->getEnd()->format('U') - $dayBegin) / 60);
        $start = min(max($start, $gridStart), $gridEnd);
        $end = min(max($end, $start), $gridEnd);
        return [$start - $gridStart, $end - $start];
    }

    protected function getStartOffset()
    {
        if ($this->startOffset === null) {
            [$startHour, $startMinute] = $this->getDayTime('dayStart', '070000');
            $this->startOffset = (float)($startHour + $startMinute / 60);
        }
        return $this->startOffset;
    }

    protected function getLength($event)
    {
        $x = $event->getEnd()->format('U') - $event->getStart()->format('U');
        return ($x / 3600 * 42) - 3;
    }
}
