..  include:: /Includes.rst.txt

..  _backend-modules:

===============
Backend modules
===============

cal adds two modules to :guilabel:`System`. They are available for system
maintainers.

..  _backend-modules-indexer:

Cal Indexer
===========

Rebuilds the index of the recurring events and exception events. The index is
updated automatically when an event is saved. Rebuild it after an import of
records into the database, after changing the extension settings
`recurrenceStart` / `recurrenceEnd`, or when the index range has to be moved
forward (e.g. once a year).

#.  Select a frontend page with the TypoScript of cal for every folder with
    events.
#.  Enter the start and end of the index (`YYYYMMDD` or relative values like
    `-3 month`, `+4 years`).
#.  Start the indexing.

..  _backend-modules-ics-generator:

Cal ICS Generator
=================

Creates the iCalendar data of the events, which is used by CalDAV (see
:ref:`caldav`). The data is created when an event is saved in the backend. Use
the module after the upgrade from cal 1.x, after the upgrade wizard
`cal_icsData`, or after importing records into the database.

:guilabel:`Check` lists the events without iCalendar data,
:guilabel:`Generate` creates it.
