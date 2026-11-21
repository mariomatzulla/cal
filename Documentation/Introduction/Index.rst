..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

Calendar Base (cal) manages events in calendars and shows them on the website.
It is based on the iCalendar standard (RFC 5545), so events, recurrence rules
and exceptions can be exchanged with other calendar applications.

Version 4 is a rewrite for TYPO3 v14 with Extbase and Fluid. It also contains
the former extension `caldav`.

..  _features:

Features
========

*   Year, month, week, day and list view, detail views of events, locations and
    organizers.
*   Recurring events after RFC 5545 (daily, weekly, monthly, yearly, by weekday,
    day of the month, month, count, until date, interval, additional dates).
*   Exception events and exception groups (e.g. holidays) and deviations of
    single dates of a recurring event.
*   Categories (`sys_category`) with their own color.
*   Locations and organizers with images, address and links.
*   iCalendar download (`.ics`) of single events.
*   Import of external iCalendar feeds and ICS files, refreshed by the scheduler.
*   CalDAV server for frontend users.
*   Speaking URLs with a route enhancer shipped with the extension.
*   Site sets for the configuration and the default styles, CSS custom
    properties for an own design.

..  _screenshots:

Screenshots
===========

..  figure:: /Images/MonthView.png
    :alt: Month view
    :class: with-shadow

    Month view with the view switch and the navigation

..  figure:: /Images/WeekView.png
    :alt: Week view
    :class: with-shadow

    Week view with a time grid

..  figure:: /Images/DayView.png
    :alt: Day view
    :class: with-shadow

    Day view: overlapping events are shown side by side

..  figure:: /Images/YearView.png
    :alt: Year view
    :class: with-shadow

    Year view: a dot marks the days with events, hovering a day shows its
    events, a click opens the day view

..  figure:: /Images/ListView.png
    :alt: List view
    :class: with-shadow

    List view with event cards

..  figure:: /Images/EventView.png
    :alt: Event view
    :class: with-shadow

    Event view with the iCalendar download

..  figure:: /Images/LocationView.png
    :alt: Location view
    :class: with-shadow

    Location view, the organizer view looks the same

The labels of the screenshots are German, the extension is translated to
English and German.

..  _requirements:

Requirements
============

========  ==========  ===========
cal       TYPO3       PHP
========  ==========  ===========
4.x       14.x        8.2 - 8.5
1.x       <= 8.7      5.5 - 5.6
========  ==========  ===========

The last version for older TYPO3 versions is tagged `v1.12.0`.

..  _support:

Support
=======

Report bugs and feature requests in the
`issue tracker <https://github.com/mariomatzulla/cal/issues>`__.
Pull requests are welcome, please create an issue first.
