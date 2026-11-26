<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Service;

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
use MarioMatzulla\Cal\Domain\Model\Event;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * The iCalendar data (RFC 5545, RFC 7986) of an event, built with sabre/vobject: escaping of texts, line
 * folding and the formats of dates are done by the library.
 */
class IcsBuilder
{
    public const PRODID = '-//TYPO3/NONSGML Calendar Base (cal) V%s//EN';

    /**
     * CSS3 color names (RFC 7986: COLOR is a CSS3 color name)
     */
    private const COLOR_NAMES = [
        'aliceblue' => 'f0f8ff', 'antiquewhite' => 'faebd7', 'aqua' => '00ffff', 'aquamarine' => '7fffd4', 'azure' => 'f0ffff',
        'beige' => 'f5f5dc', 'bisque' => 'ffe4c4', 'black' => '000000', 'blanchedalmond' => 'ffebcd', 'blue' => '0000ff',
        'blueviolet' => '8a2be2', 'brown' => 'a52a2a', 'burlywood' => 'deb887', 'cadetblue' => '5f9ea0', 'chartreuse' => '7fff00',
        'chocolate' => 'd2691e', 'coral' => 'ff7f50', 'cornflowerblue' => '6495ed', 'cornsilk' => 'fff8dc', 'crimson' => 'dc143c',
        'darkblue' => '00008b', 'darkcyan' => '008b8b', 'darkgoldenrod' => 'b8860b', 'darkgray' => 'a9a9a9', 'darkgreen' => '006400',
        'darkkhaki' => 'bdb76b', 'darkmagenta' => '8b008b', 'darkolivegreen' => '556b2f', 'darkorange' => 'ff8c00', 'darkorchid' => '9932cc',
        'darkred' => '8b0000', 'darksalmon' => 'e9967a', 'darkseagreen' => '8fbc8f', 'darkslateblue' => '483d8b', 'darkslategray' => '2f4f4f',
        'darkturquoise' => '00ced1', 'darkviolet' => '9400d3', 'deeppink' => 'ff1493', 'deepskyblue' => '00bfff', 'dimgray' => '696969',
        'dodgerblue' => '1e90ff', 'firebrick' => 'b22222', 'floralwhite' => 'fffaf0', 'forestgreen' => '228b22', 'fuchsia' => 'ff00ff',
        'gainsboro' => 'dcdcdc', 'ghostwhite' => 'f8f8ff', 'gold' => 'ffd700', 'goldenrod' => 'daa520', 'gray' => '808080',
        'green' => '008000', 'greenyellow' => 'adff2f', 'honeydew' => 'f0fff0', 'hotpink' => 'ff69b4', 'indianred' => 'cd5c5c',
        'indigo' => '4b0082', 'ivory' => 'fffff0', 'khaki' => 'f0e68c', 'lavender' => 'e6e6fa', 'lavenderblush' => 'fff0f5',
        'lawngreen' => '7cfc00', 'lemonchiffon' => 'fffacd', 'lightblue' => 'add8e6', 'lightcoral' => 'f08080', 'lightcyan' => 'e0ffff',
        'lightgoldenrodyellow' => 'fafad2', 'lightgray' => 'd3d3d3', 'lightgreen' => '90ee90', 'lightpink' => 'ffb6c1', 'lightsalmon' => 'ffa07a',
        'lightseagreen' => '20b2aa', 'lightskyblue' => '87cefa', 'lightslategray' => '778899', 'lightsteelblue' => 'b0c4de', 'lightyellow' => 'ffffe0',
        'lime' => '00ff00', 'limegreen' => '32cd32', 'linen' => 'faf0e6', 'maroon' => '800000', 'mediumaquamarine' => '66cdaa',
        'mediumblue' => '0000cd', 'mediumorchid' => 'ba55d3', 'mediumpurple' => '9370db', 'mediumseagreen' => '3cb371', 'mediumslateblue' => '7b68ee',
        'mediumspringgreen' => '00fa9a', 'mediumturquoise' => '48d1cc', 'mediumvioletred' => 'c71585', 'midnightblue' => '191970', 'mintcream' => 'f5fffa',
        'mistyrose' => 'ffe4e1', 'moccasin' => 'ffe4b5', 'navajowhite' => 'ffdead', 'navy' => '000080', 'oldlace' => 'fdf5e6',
        'olive' => '808000', 'olivedrab' => '6b8e23', 'orange' => 'ffa500', 'orangered' => 'ff4500', 'orchid' => 'da70d6',
        'palegoldenrod' => 'eee8aa', 'palegreen' => '98fb98', 'paleturquoise' => 'afeeee', 'palevioletred' => 'db7093', 'papayawhip' => 'ffefd5',
        'peachpuff' => 'ffdab9', 'peru' => 'cd853f', 'pink' => 'ffc0cb', 'plum' => 'dda0dd', 'powderblue' => 'b0e0e6',
        'purple' => '800080', 'rebeccapurple' => '663399', 'red' => 'ff0000', 'rosybrown' => 'bc8f8f', 'royalblue' => '4169e1',
        'saddlebrown' => '8b4513', 'salmon' => 'fa8072', 'sandybrown' => 'f4a460', 'seagreen' => '2e8b57', 'seashell' => 'fff5ee',
        'sienna' => 'a0522d', 'silver' => 'c0c0c0', 'skyblue' => '87ceeb', 'slateblue' => '6a5acd', 'slategray' => '708090',
        'snow' => 'fffafa', 'springgreen' => '00ff7f', 'steelblue' => '4682b4', 'tan' => 'd2b48c', 'teal' => '008080',
        'thistle' => 'd8bfd8', 'tomato' => 'ff6347', 'turquoise' => '40e0d0', 'violet' => 'ee82ee', 'wheat' => 'f5deb3',
        'white' => 'ffffff', 'whitesmoke' => 'f5f5f5', 'yellow' => 'ffff00', 'yellowgreen' => '9acd32',
    ];

    /**
     * Properties of a VEVENT written by cal. The other properties and components of a CalDAV client
     * (e.g. VALARM, ATTENDEE, TRANSP, X- properties) are kept, see keepClientData().
     */
    private const MANAGED_PROPERTIES = [
        'UID', 'DTSTAMP', 'CREATED', 'LAST-MODIFIED', 'SEQUENCE', 'DTSTART', 'DTEND', 'DURATION', 'RRULE', 'RDATE',
        'EXDATE', 'RECURRENCE-ID', 'SUMMARY', 'DESCRIPTION', 'X-ALT-DESC', 'URL', 'LOCATION', 'GEO', 'ORGANIZER',
        'CATEGORIES', 'COLOR', 'ATTACH', 'IMAGE',
    ];

    public function __construct(
        private readonly IcsRecurrence $icsRecurrence,
    ) {}

    /**
     * @param array{crdate: int, tstamp: int, sequence: int} $meta of the record
     * @param array{description: string, attachments: list<string>, images: list<string>} $resources the description as HTML
     *        (links resolved) and the absolute URLs of the files, see IcsGenerator
     */
    public function build(Event $event, string $version, string $eventUrl, array $meta, array $resources, string $vTimeZone = ''): VCalendar
    {
        $calendar = $event->getCalendarObject();
        $timezoneId = (string)$calendar?->getTimezone();
        $timezone = in_array($timezoneId, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true) ? new \DateTimeZone($timezoneId) : null;
        // the recurrence uses the same convention: TZID of the calendar, otherwise UTC
        $recurrenceTimezone = $timezone !== null ? $timezoneId : '';

        $vcalendar = new VCalendar(['PRODID' => sprintf(self::PRODID, $version)]);
        if ($vTimeZone !== '') {
            foreach (self::parseLines([], $vTimeZone, 'VTIMEZONE') as $component) {
                $vcalendar->add($component);
            }
        }
        $stamp = new \DateTimeImmutable('@' . ($meta['tstamp'] > 0 ? $meta['tstamp'] : time()));

        $vEvent = $this->addEvent($vcalendar, (string)$event->getIcsUid(), $stamp);
        if ($meta['crdate'] > 0) {
            $vEvent->add('CREATED', new \DateTimeImmutable('@' . $meta['crdate']));
        }
        if ($meta['tstamp'] > 0) {
            $vEvent->add('LAST-MODIFIED', $stamp);
        }
        $vEvent->add('SEQUENCE', $meta['sequence']);
        if ($event->isAllday()) {
            $vEvent->add('DTSTART', self::toDateTime($event->getStart(), null), ['VALUE' => 'DATE']);
            $vEvent->add('DTEND', self::toDateTime($event->getIcsEnd(), null), ['VALUE' => 'DATE']);
        } else {
            $vEvent->add('DTSTART', self::toDateTime($event->getStart(), $timezone));
            $vEvent->add('DTEND', self::toDateTime($event->getIcsEnd(), $timezone));
        }
        foreach (self::parseLines(explode("\n", $this->icsRecurrence->getRecurrenceLines($event, $recurrenceTimezone))) as $property) {
            $vEvent->add($property);
        }
        $vEvent->add('SUMMARY', (string)$event->getTitle());
        $html = trim($resources['description']);
        if ($html !== '') {
            $vEvent->add('DESCRIPTION', self::htmlToText($html));
            if ($html !== strip_tags($html)) {
                $vEvent->add('X-ALT-DESC', '<!DOCTYPE html><html><body>' . $html . '</body></html>', ['FMTTYPE' => 'text/html']);
            }
        }
        $url = $event->getExtUrl() !== '' ? $event->getExtUrl() : $eventUrl;
        if ($url !== '') {
            $vEvent->add('URL', $url);
        }
        $this->addLocation($vEvent, $event);
        $organizer = $event->getOrganizerObject();
        if ($organizer !== null && trim((string)$organizer->getEmail()) !== '') {
            $vEvent->add('ORGANIZER', 'mailto:' . trim((string)$organizer->getEmail()), ['CN' => (string)$organizer->getName()]);
        }
        $categories = [];
        foreach ($event->getCategories() ?? [] as $category) {
            $categories[] = (string)$category->getTitle();
        }
        if ($categories !== []) {
            $vEvent->add('CATEGORIES', $categories);
        }
        if ($event->getStatus() !== '') {
            $vEvent->add('STATUS', strtoupper($event->getStatus()));
        }
        if ($event->getClassification() !== '') {
            $vEvent->add('CLASS', strtoupper($event->getClassification()));
        }
        $color = self::colorName($event->getColor());
        if ($color !== '') {
            $vEvent->add('COLOR', $color);
        }
        foreach ([...$resources['attachments'], ...$resources['images']] as $file) {
            $vEvent->add('ATTACH', $file);
        }
        foreach ($resources['images'] as $image) {
            $vEvent->add('IMAGE', $image, ['VALUE' => 'URI']);
        }

        foreach ($this->icsRecurrence->getOverrides($event, $recurrenceTimezone) as $override) {
            $lines = [
                'RECURRENCE-ID' . $override['recurrenceId'],
                'DTSTART' . $override['start'],
                'DTEND' . $override['end'],
                'SUMMARY:' . $override['title'],
            ];
            if ($override['description'] !== '') {
                $lines[] = 'DESCRIPTION:' . $override['description'];
            }
            if ($override['location'] !== '') {
                $lines[] = 'LOCATION:' . $override['location'];
            }
            $vOverride = $this->addEvent($vcalendar, (string)$event->getIcsUid(), $stamp);
            $vOverride->add('SEQUENCE', $meta['sequence']);
            foreach (self::parseLines($lines) as $property) {
                $vOverride->add($property);
            }
        }
        return $vcalendar;
    }

    /**
     * Takes over the properties and components cal does not write (e.g. alarms and attendees of a CalDAV
     * client) from the previous iCalendar data of the event, so saving the event in the backend does not
     * remove them. The VEVENTs are assigned by their RECURRENCE-ID.
     */
    public static function keepClientData(VCalendar $vcalendar, string $previous): void
    {
        if (trim($previous) === '') {
            return;
        }
        try {
            $old = Reader::read($previous, Reader::OPTION_FORGIVING);
        } catch (\Throwable) {
            return;
        }
        if (!$old instanceof VCalendar) {
            return;
        }
        $targets = [];
        foreach ($vcalendar->select('VEVENT') as $vEvent) {
            $targets[self::getRecurrenceKey($vEvent)] = $vEvent;
        }
        foreach ($old->select('VEVENT') as $vEvent) {
            $target = $targets[self::getRecurrenceKey($vEvent)] ?? null;
            if ($target === null) {
                continue;
            }
            // the status and class of the event are fields of cal, the occurrences keep those of the client
            $managed = $target === ($targets[''] ?? null) ? [...self::MANAGED_PROPERTIES, 'STATUS', 'CLASS'] : self::MANAGED_PROPERTIES;
            foreach ($vEvent->children() as $child) {
                if (!in_array(strtoupper($child->name), $managed, true)) {
                    $target->add(clone $child);
                }
            }
        }
    }

    /**
     * RECURRENCE-ID of a VEVENT in UTC, empty for the event itself
     */
    private static function getRecurrenceKey(Component $vEvent): string
    {
        $recurrenceId = $vEvent->select('RECURRENCE-ID')[0] ?? null;
        if (!$recurrenceId instanceof \Sabre\VObject\Property\ICalendar\DateTime) {
            return '';
        }
        return $recurrenceId->getDateTime(new \DateTimeZone('UTC'))->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis');
    }

    private function addEvent(VCalendar $vcalendar, string $uid, \DateTimeImmutable $stamp): Component
    {
        $vEvent = $vcalendar->createComponent('VEVENT', null, false);
        $vEvent->add('UID', $uid);
        $vEvent->add('DTSTAMP', $stamp);
        $vcalendar->add($vEvent);
        return $vEvent;
    }

    private function addLocation(Component $vEvent, Event $event): void
    {
        $location = $event->getLocationObject();
        $name = $location !== null ? (string)$location->getName() : (string)$event->getLocation();
        if (trim($name) !== '') {
            $vEvent->add('LOCATION', $name);
        }
        if ($location === null) {
            return;
        }
        $latitude = (float)$location->getLatitude();
        $longitude = (float)$location->getLongitude();
        if (($latitude !== 0.0 || $longitude !== 0.0) && abs($latitude) <= 90 && abs($longitude) <= 180) {
            $vEvent->add('GEO', [$latitude, $longitude]);
        }
    }

    /**
     * Wall clock time of cal as DateTime in the time zone (UTC without time zone, like the time zone of the server)
     */
    public static function toDateTime(\DateTimeInterface $wallClock, ?\DateTimeZone $timezone): \DateTimeImmutable
    {
        $dateTime = \DateTimeImmutable::createFromFormat('YmdHis', $wallClock->format('Ymd') . $wallClock->format('His'), $timezone);
        return $timezone !== null ? $dateTime : $dateTime->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * Plain text of an HTML text: line breaks for paragraphs, line breaks and list items
     */
    public static function htmlToText(string $html): string
    {
        $text = (string)preg_replace(['#<br\s*/?>#i', '#</(p|div|li|h[1-6]|tr|blockquote)>#i'], "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $text)));
        return trim((string)preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /**
     * The nearest CSS3 color name of a color "#rrggbb" / "#rgb", empty for other values
     */
    public static function colorName(string $color): string
    {
        $hex = strtolower(ltrim(trim($color), '#'));
        if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/', $hex)) {
            return '';
        }
        [$red, $green, $blue] = array_map('hexdec', str_split($hex, 2));
        $nearest = '';
        $distance = PHP_INT_MAX;
        foreach (self::COLOR_NAMES as $name => $value) {
            [$r, $g, $b] = array_map('hexdec', str_split($value, 2));
            $current = ($red - $r) ** 2 + ($green - $g) ** 2 + ($blue - $b) ** 2;
            if ($current < $distance) {
                $distance = $current;
                $nearest = $name;
            }
        }
        return $nearest;
    }

    /**
     * Properties (or the components of $content) of iCalendar lines, which contain their parameters already
     *
     * @param list<string> $lines
     * @return list<\Sabre\VObject\Node>
     */
    private static function parseLines(array $lines, string $content = '', string $component = ''): array
    {
        $lines = array_values(array_filter($lines, static fn(string $line): bool => trim($line) !== ''));
        if ($lines === [] && $content === '') {
            return [];
        }
        $data = "BEGIN:VCALENDAR\r\n" . ($content !== '' ? $content : "BEGIN:VEVENT\r\n" . implode("\r\n", $lines) . "\r\nEND:VEVENT\r\n") . "END:VCALENDAR\r\n";
        $parsed = Reader::read($data);
        $nodes = [];
        foreach ($component !== '' ? $parsed->select($component) : $parsed->select('VEVENT')[0]->children() as $node) {
            $nodes[] = clone $node;
        }
        return $nodes;
    }
}
