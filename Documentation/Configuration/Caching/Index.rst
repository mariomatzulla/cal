..  include:: /Includes.rst.txt

..  _caching:

=======
Caching
=======

The calendar plugins are cached with their page. The pages show changes of the
calendar records at once, without TSconfig (`TCEMAIN.clearCacheCmd`) or clearing
the frontend cache by hand:

*   Every page with a calendar plugin gets the names of the cal tables as cache
    tags: `tx_cal_domain_model_event`, `tx_cal_domain_model_eventdeviation`,
    `tx_cal_domain_model_exception_event`,
    `tx_cal_domain_model_exception_event_group`, `tx_cal_domain_model_calendar`,
    `tx_cal_domain_model_location`, `tx_cal_domain_model_organizer` and
    `sys_category`.
*   Saving a record in the backend flushes the cache tag of its table (done by
    the TYPO3 core), so only the pages with calendar plugins are flushed.
*   Changes without the backend flush the pages with the tag
    `tx_cal_domain_model_event`: events written by CalDAV clients (e.g.
    Thunderbird) and the import of external calendars and ICS files.

Own code writing cal records directly into the database can flush the pages
with :php:`\MarioMatzulla\Cal\Service\PageCacheService::flush()`.

..  note::
    Views showing the upcoming events (e.g. the list view with a relative
    start like "today") change with the date as well. Limit the cache lifetime
    of these pages (page property :guilabel:`Cache Lifetime` or
    `config.cache_period`) if they must change at midnight.
