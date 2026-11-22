..  include:: /Includes.rst.txt

..  _extension-configuration:

=======================
Extension configuration
=======================

The extension configuration is set in
:guilabel:`Admin Tools > Settings > Extension Configuration > cal`.

`hideLocationTextfield`, `hideOrganizerTextfield`
    Hide the text fields for the location and the organizer of events, so
    only records can be selected. Default `0`.

`showTimes`
    Show the start and end time in the record lists of the backend.
    Default `1`.

`useTeaser`
    Show the field "Teaser" in addition to the description. Default `0`.

`hideDeviationRecords`
    Hide the deviation records in the record list, they are edited inside of
    the event. Default `1`.

`recurrenceStart`, `recurrenceEnd`
    Range of the index of the recurring events: a date `YYYYMMDD`, a relative
    value like `+3 years` or `cal:yearstart`. Defaults `cal:yearstart` and
    `+3 years`. Recurring events without end are indexed until
    `recurrenceEnd`.
