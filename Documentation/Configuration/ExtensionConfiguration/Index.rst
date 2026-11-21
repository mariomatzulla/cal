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

`useLocationStructure`
    Data model of the locations: the built-in locations
    (`tx_cal_domain_model_location`) or `tt_address`.

`useOrganizerStructure`
    Data model of the organizers: the built-in organizers
    (`tx_cal_domain_model_organizer`), `tt_address` or frontend users.

    ..  note::
        The other data models only change the record selection of the event
        form. The location and organizer views of cal show the built-in
        records.

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
