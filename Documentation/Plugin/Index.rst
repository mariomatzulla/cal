..  include:: /Includes.rst.txt

..  _plugin:

=====================
Plugin configuration
=====================

The content element :guilabel:`Calendar Plugin` is configured on the tab
:guilabel:`Plugin`. The values of the plugin override the TypoScript settings
(see :ref:`typoscript`). Empty fields keep the TypoScript value.

..  figure:: /Images/BackendPluginGeneral.png
    :alt: Plugin options of the calendar plugin
    :class: with-shadow

    Plugin options, tab "General Settings"

..  _plugin-general:

General Settings
================

:guilabel:`Allowed views`
    The views the plugin may show. The first view is the default view. Links
    to views that are not allowed are not rendered, e.g. the location of an
    event is not linked without the view "Location", and the iCalendar
    download is not available without the view "ICS".

    Month, Year, Week, Day, List
        The calendar views. The view switch in the header shows the allowed
        ones.

    Single Event
        The event view.

    Location, Organizer
        The detail views of location and organizer records.

    ICS
        The iCalendar download of single events.

..  _plugin-filters:

Filters
=======

:guilabel:`Calendar selection mode`
    Show all calendars, only the selected calendars, or all except the
    selected calendars.

:guilabel:`Calendar selection`
    The calendars of the selection mode.

:guilabel:`Category selection mode`
    Show all categories, only events with all selected categories (exact
    match), only events with one of the selected categories (any), or all
    except the selected categories.

:guilabel:`Category selection`
    The categories of the selection mode.

..  _plugin-views:

Day and Week View
=================

:guilabel:`Day start time`, :guilabel:`Day endtime`
    The time range of the time grid of the week and day view. Default 07:00 to
    23:00. Events outside of the range are cut at the start or end of the
    grid, see `view.day.extendGridToEvents` in :ref:`typoscript-day`.

:guilabel:`Show divider every x minutes`
    Lines of the time grid.

..  _plugin-list:

List View
=========

:guilabel:`Start time`, :guilabel:`End time`
    Time range of the list, e.g. "Now" until "1 Month From Now".
    "Use Typoscript Default" takes `view.list.starttime` and
    `view.list.endtime`.

:guilabel:`Max events`
    Maximum number of events of the list.

:guilabel:`Images of an event`
    Show all images as thumbnails below the "read more" link, or only the
    first image.

..  _plugin-event:

Event View
==========

:guilabel:`Event view page`
    Page with a calendar plugin that shows the event view. Empty: the event
    view is shown on the current page.

The pages of the location and organizer view are set with TypoScript
(`view.location.locationViewPid`, `view.organizer.organizerViewPid`).

..  _plugin-ics:

Ics View
========

:guilabel:`Show ics links`
    Show the link "Add to calendar (.ics)" in the event view. The view "ICS"
    must be allowed as well.

..  _plugin-typoscript:

Typoscript
==========

TypoScript for this plugin only. It is relative to `plugin.tx_cal.settings`:

..  code-block:: typoscript

    view.day.dayStart = 080000
    view.list.event.card.descriptionLength = 120

..  _plugin-ignore-flexform:

Ignore the plugin values
========================

`plugin.tx_cal.settings.dontListenToFlexForm = 1` ignores all values of the
plugin. Single fields are ignored with their section and name, e.g.:

..  code-block:: typoscript

    plugin.tx_cal.settings.dontListenToFlexForm {
      day.dayStart = 1
      list.maxEvents = 1
    }
