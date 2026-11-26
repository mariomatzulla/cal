..  include:: /Includes.rst.txt

..  _upgrade:

==================
Upgrade from 1.x
==================

cal 4 for TYPO3 v14 is a major rewrite. Compared to cal 1.x it:

*   uses new table names (`tx_cal_domain_model_*`),
*   uses `sys_category` instead of the table `tx_cal_category`,
*   is a content element (CType `cal_controller`) instead of a plugin of the
    type "list_type",
*   renders Fluid templates instead of the marker based templates,
*   contains the CalDAV server of the former extension `caldav`.

The upgrade wizards migrate the data of cal 1.x. They keep the uids of the
records, so links and references stay valid.

..  _upgrade-steps:

Steps
=====

#.  Update TYPO3. Uninstall the extension `caldav`, install cal 4.
#.  :guilabel:`Analyze Database Structure`: create the new tables and fields.

    ..  important::
        Do **not** remove the old `tx_cal_*` tables and fields yet. The wizards
        read the data from them. Tables that were already renamed to
        `zzz_deleted_*` are read from there.

#.  Run the upgrade wizards "Calendar Base (cal) 1.x => 4.0"
    (:guilabel:`Admin Tools > Upgrade > Upgrade Wizard` or
    :bash:`vendor/bin/typo3 upgrade:run`):

    `cal_v4Categories`
        Migrate categories to `sys_category`.

    `cal_v4Content`
        Migrate plugins, permissions and TypoScript templates.

    `cal_v4Records`
        Migrate calendars, events, locations and organizers and rebuild the
        index of the recurring events. The files of "ICS file" calendars are
        copied from :file:`uploads/tx_cal/ics/` to the folder :file:`cal/ics/` of
        the default storage. The events of external and ICS file calendars are
        imported again.

    `cal_v4Scheduler`
        Replace the scheduler tasks of cal 1.x.

#.  Run the other wizards of cal (see below), at least `cal_slugs`.
#.  Create the iCalendar data of the events in the backend module
    :guilabel:`Cal ICS Generator` (see :ref:`backend-modules`) and update the
    reference index.
#.  Check the result. Then remove the old tables and fields with
    :guilabel:`Analyze Database Structure`.

..  _upgrade-wizards:

Further upgrade wizards
=======================

`cal_slugs`
    Creates the URL segments (field `slug`) of events, locations and
    organizers, which are needed for the :ref:`speaking-urls`.

`cal_icsData`
    Earlier versions of cal 4 created invalid iCalendar data: a wrong end of
    all-day events (CalDAV clients showed events of several days on their
    first day only), no `DTSTAMP`, texts and URLs escaped as HTML instead of
    iCalendar, no line folding. The wizard removes the iCalendar data created
    by cal, create it again with the backend module
    :guilabel:`Cal ICS Generator`. Data of CalDAV clients and imported events
    are kept.

`cal_recurrence`
    The recurrence index contains the first date of a recurring event and
    leaves out the dates of exception events now, the iCalendar data contains
    the complete series (`RRULE`, `RDATE`, `EXDATE` and the deviations). The
    wizard rebuilds the index of the recurring events and removes their
    iCalendar data created by cal, create it again with the backend module
    :guilabel:`Cal ICS Generator`.

`cal_categoryColorDefault`
    Removes the old default color of categories, so the default color of the
    styles is used.

`cal_l10nParent`
    Migrates the field `l18n_parent` to `l10n_parent`.

`cal_eventTypes`
    Converts events of the removed types "Meeting" and "Todo" to normal events.

..  _upgrade-typoscript:

TypoScript and templates
========================

All settings are below `plugin.tx_cal.settings` now, see :ref:`typoscript`.
TypoScript below `plugin.tx_cal_controller` is still read but deprecated, move
it to `plugin.tx_cal.settings`.

The marker based templates of cal 1.x cannot be migrated. Override the Fluid
templates instead, see :ref:`styling`.

..  _upgrade-removed:

Removed features
================

The following features of cal 1.x do not exist in cal 4:

*   Frontend editing (create, edit and delete events, calendars, locations,
    organizers and categories in the frontend) and the rights management.
*   Search views, the search box and the sidebar.
*   RSS feed (use the iCalendar download or CalDAV instead).
*   Subscription and meeting manager, attendees, todo views, notifications and
    reminders by e-mail. Events of the types "Meeting" and "Todo" become normal
    events.
*   Shared users of events, locations and organizers, the creator fields of
    frontend editing and the free / busy view of calendars.
*   Mini calendar and the AJAX views (`calajax`).
*   Marker based templates, the Constant Editor templates and the PEAR date
    formats. Date formats are ICU patterns now (e.g. `dd.MM.yyyy`).
*   Connection to `wec_map` (nearby calendars) and the captcha extensions.
*   RealURL configuration, replaced by the route enhancer
    (see :ref:`speaking-urls`).
