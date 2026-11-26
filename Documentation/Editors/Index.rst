..  include:: /Includes.rst.txt

..  _editors:

===========
For editors
===========

The records of cal are created with the module :guilabel:`Content > List` in a
folder. All events belong to a calendar.

..  contents::
    :local:
    :depth: 1

..  _editors-calendar:

Calendars
=========

:guilabel:`Calendar Type`
    Standard Calendar (database)
        The events are created in TYPO3.

    External Calendar
        The events are imported from the iCalendar feed in
        :guilabel:`External Calendar URL`.

    Include ICS File
        The events are imported from the uploaded :guilabel:`ICS File`.

    The events of external and ICS file calendars are imported when the
    calendar is saved. With a :guilabel:`Refresh Interval (Minutes)` cal creates
    a scheduler task that imports the calendar again, see
    :ref:`external-calendars`. Imported events are read-only in CalDAV.

:guilabel:`Calendar Title`
    Name of the calendar. It is also the name of the calendar in CalDAV clients.

:guilabel:`Timezone`
    Timezone of the events.

:guilabel:`Owner`
    Frontend users and groups owning the calendar. A calendar without owner is
    public. A calendar with owners is private: its events are only shown to
    the owners when they are logged in.

:guilabel:`Header style`, :guilabel:`Body style`
    CSS class of the events of the calendar, e.g. `green_catheader`. The
    default styles contain the colors `default`, `blue`, `green`, `orange`,
    `pink`, `red`, `yellow` (each with the suffix `_catheader`). The color of a
    category wins over the style of the calendar.

..  _editors-event:

Events
======

..  figure:: /Images/BackendEventGeneral.png
    :alt: Tab "General" of an event
    :class: with-shadow

    Tab "General" of an event

Tab "General"
-------------

:guilabel:`Event type`
    Event with description
        A normal event with a detail view.

    Shortcut to page
        The event links to the page in :guilabel:`Shortcut to page` in all
        views, e.g. a page with more information about the event.

    Link to external url
        The event links to the :guilabel:`External URL` in all views. The link
        can open in a new window (target of the link field).

    The links of these types are rendered even if the event view is not
    allowed in the plugin.

:guilabel:`Title`
    Title of the event.

:guilabel:`URL segment`
    Part of the speaking URL of the event view. It is created from the title.
    See :ref:`speaking-urls`.

:guilabel:`Calendar`
    Calendar of the event.

:guilabel:`Categories`
    Categories (`sys_category`) of the event, used by the filters of the plugin
    and for the color of the event.

:guilabel:`All Day Event`, :guilabel:`Event Start Day`, :guilabel:`Event Start Time`, :guilabel:`Event End Day`, :guilabel:`Event End Time`
    Start and end of the event. An all-day event can span several days.

:guilabel:`Teaser`
    Short text for the list view. Only shown if the extension setting
    `useTeaser` is enabled (see :ref:`extension-configuration`). Without a
    teaser, the list view shows the shortened description.

:guilabel:`Description`
    Text of the event view.

:guilabel:`Status` (`STATUS`)
    Confirmed, tentative or cancelled. Tentative and cancelled events are shown
    with a note ("Tentative", "Cancelled"), cancelled events struck through.

:guilabel:`Visibility` (`CLASS`)
    Private and confidential events are shown on the website as "Busy": only
    their time, without title, texts, location, images and link. The iCalendar
    download of the website is not available for them, CalDAV clients get the
    complete event. The owners of the calendar see the complete event after the
    login, marked with an icon. See :ref:`typoscript-private-events`.

Tab "Recurrence"
----------------

..  figure:: /Images/BackendEventRecurrence.png
    :alt: Tab "Recurrence" of an event
    :class: with-shadow

    A monthly event on the 15th of every month

The recurrence follows the rules of RFC 5545 (`RRULE`):

:guilabel:`Regular Frequency Rule` (`FREQ`)
    None, daily, weekly, monthly or yearly.

:guilabel:`By Day of the Week` (`BYDAY`)
    Weekdays, for monthly and yearly rules with an ordinal number, e.g. "the
    third Wednesday" or "the last Friday".

:guilabel:`By Monthday` (`BYMONTHDAY`)
    Days of the month, 1 to 31. Negative values count from the end of the
    month (-1 is the last day).

:guilabel:`By Month` (`BYMONTH`)
    Months of yearly rules. Without months, a yearly event recurs in the
    month of its start date. With :guilabel:`By Day of the Week` but without
    :guilabel:`By Month` an ordinal refers to the year, e.g. "the 20th Monday
    of the year".

:guilabel:`Until Date` (`UNTIL`), :guilabel:`Count` (`COUNT`)
    End of the recurrence. Without both, the event recurs until the end of the
    recurrence range (extension setting `recurrenceEnd`).

:guilabel:`Interval` (`INTERVAL`)
    Every n-th occurrence, e.g. 2 for every second week.

:guilabel:`Recurrence by` (`RDATE`)
    Additional single dates, dates with time or periods.

:guilabel:`Recurrence rule (iCalendar)`
    Only shown for rules of a CalDAV client the fields above cannot store
    (e.g. "the last workday of the month"). The dates are calculated with
    this rule, changing a recurrence field replaces it, see
    :ref:`caldav-recurrence`.

:guilabel:`Deviation`
    Changes of single dates of a recurring event (another time, title,
    location, description, status, visibility etc.). A deviation refers to the
    original start of the date. Status and visibility "Like the event" use the
    values of the event.

:guilabel:`Exceptions`
    Exception events and exception groups (see below). The event does not take
    place on their dates.

The dates of recurring events are stored in an index table. It is updated
when an event is saved, and can be rebuilt with the backend module
:guilabel:`Cal Indexer` (see :ref:`backend-modules`).

Tabs "Location" and "Organizer"
-------------------------------

The location and the organizer are a text or a location / organizer record.
In the event and list view they are linked:

#.  A record links to the location / organizer view, if this view is allowed in
    the plugin.
#.  Otherwise :guilabel:`Page to link the location to` (or organizer) links to
    a page,
#.  otherwise :guilabel:`External link to the location` (or organizer) to an
    URL.

Without text and record the title of the page or the URL is shown.

..  figure:: /Images/EventViewLinks.png
    :alt: Event view with links to the location and the organizer
    :class: with-shadow

    Event view: the location and the organizer link to their views, address,
    e-mail and phone number are shown next to them

The text fields can be hidden with the extension settings
`hideLocationTextfield` and `hideOrganizerTextfield`.

Tab "Files"
-----------

Images and files of the event. The first image is shown in the list and event
view.

..  _editors-exception:

Exception events and exception groups
=====================================

An :guilabel:`Exception Event` is a date (optionally recurring, e.g. every
24th of December) on which events do not take place. An
:guilabel:`Exception Event Group` combines several exception events, e.g. the
holidays of a year. Both are assigned to events in the field
:guilabel:`Exceptions`.

The dates of the exception events are removed from the recurring event in all
views, the iCalendar download and CalDAV (`EXDATE`). An exception event covers
whole days: every date of the event starting on such a day is removed, also the
first date of the event.

..  _editors-location:

Locations and organizers
========================

Locations and organizers have a name, a URL segment, a description, an address,
phone, fax, e-mail, images and a link. Locations also have the coordinates
(latitude and longitude).

The location view and the organizer view show the first image with the details
next to it and the further images as thumbnails. The size of the images is set
with `view.location.image` and `view.organizer.image` (see
:ref:`typoscript-location`).

..  figure:: /Images/LocationView.png
    :alt: Location view
    :class: with-shadow

    Location view

..  figure:: /Images/OrganizerView.png
    :alt: Organizer view
    :class: with-shadow

    Organizer view

..  _editors-category:

Categories
==========

Events use the system categories (`sys_category`). cal adds two fields to
categories:

:guilabel:`Color in the calendar`
    Color of the events of this category in the calendar. Empty: the color of
    the calendar style or the default color is used.

:guilabel:`Color priority`
    If an event has several categories with a color, the color of the category
    with the highest priority is used. With the same priority, the more
    specific category (deeper in the category tree) wins.
