# TYPO3 Extension `cal` (Calendar Base)

A calendar extension based on the iCalendar standard (RFC 5545) for TYPO3 v14. It renders month, week, day,
year, list and event views, imports external ICS calendars and ships a CalDAV server (formerly the separate
extension `caldav`), so calendars can be synchronized with clients like Apple Calendar or Thunderbird.

## Installation

### Using Composer

The recommended way to install the extension is by using [Composer][1]. In your Composer based TYPO3 project
root, just do `composer require mariomatzulla/cal`.

### As extension from TYPO3 Extension Repository (TER)

Download and install the extension with the extension manager module.

## Minimal setup

1) Add the site set "Calendar Base (cal)" (and optionally "Calendar Base (cal): default styles") to your site
   configuration. Sites without site sets can include the static TypoScript templates instead.
2) Create calendar and event records in a folder.
3) Create the content element "Calendar Plugin" on a page, select the allowed views and the calendars to show.
4) Run "Analyze Database Structure" and the upgrade wizards of the extension after an update.

### Upgrade from cal 1.x (TYPO3 <= 11)

cal 4 uses new table names (`tx_cal_domain_model_*`), `sys_category` instead of `tx_cal_category`, a content
element instead of the plugin "list_type" and replaces EXT:caldav. After the TYPO3 update:

1) Uninstall EXT:caldav, install cal 4.
2) "Analyze Database Structure": create the new tables and fields. Do **not** remove the old `tx_cal_*` tables and
   fields yet (if they were already renamed to `zzz_deleted_*`, the wizards read them from there).
3) Run the upgrade wizards "Calendar Base (cal) 1.x => 4.0" (Upgrade module or `vendor/bin/typo3 upgrade:run`):
   - migrate categories to sys_category
   - migrate plugins, permissions and TypoScript templates
   - migrate calendars, events, locations and organizers (keeps the uids, rebuilds the recurrence index). The files
     of "ICS file" calendars are copied from `uploads/tx_cal/ics/` into the folder `cal/ics/` of the default storage,
     the events of external and ICS file calendars are imported again.
   - replace the scheduler tasks
4) Generate the ICS data of the events in the backend module "Cal ICS Generator", update the reference index.
5) Check the result, then remove the old tables and fields with "Analyze Database Structure".

Own TypoScript below `plugin.tx_cal_controller` keeps working but is deprecated, move it to `plugin.tx_cal.settings`.
Templates of cal 1.x (marker based) cannot be migrated, cal 4 uses Fluid templates.

### Speaking URLs

Import the route enhancer of the extension in the site configuration (`config/sites/<site>/config.yaml`):

```yaml
imports:
  - { resource: 'EXT:cal/Configuration/Routes/Cal.yaml' }
  # optional, "/ics/<event>.ics" (only if the site has no own PageTypeSuffix decorator)
  - { resource: 'EXT:cal/Configuration/Routes/CalPageType.yaml' }
```

URLs: `/2026`, `/2026/11`, `/2026/week/47`, `/2026/11/21`, `/event/2026-11-21/<event>`, `/location/<location>`,
`/organizer/<organizer>` (German sites: `woche`, `termin`, `ort`, `veranstalter`). Events, locations and organizers
need the field "URL segment", run the upgrade wizard "create the URL segments" for existing records. Details and an
English-only configuration: `Documentation/Advanced/SpeakingUrls`.

### CalDAV

The CalDAV endpoint is `<site base>/caldav`. Frontend users authenticate with their TYPO3 username and password
and see the calendars assigned to them.

### Settings

All TypoScript settings live below `plugin.tx_cal.settings` (constants and site settings use the same path).
Settings directly below `plugin.tx_cal_controller` are still read but deprecated.

## Development

```bash
composer install
composer cs:check   # TYPO3 coding guidelines (php-cs-fixer)
composer stan       # PHPStan, configuration in phpstan.neon.dist
```

## Administration corner

### Versions and support

| Cal | TYPO3      | PHP       | Support/Development            |
|-----|------------|-----------|--------------------------------|
| 4.x | 14.x       | 8.2 - 8.5 | Bugfixes, Updates, Development |
| 3.x | 9.5 - 13.x | 7.2       | never released                 |
| 2.x | 8.7 - 9.5  | 7.0 - 7.2 | deprecated                     |
| 1.x | <= 8.7     | 5.5 - 5.6 | deprecated                     |

The last state for older TYPO3 versions is tagged as `v1.12.0`.

### Release Management

Cal uses **semantic versioning** which basically means for you, that
- **bugfix updates** (e.g. 1.0.0 => 1.0.1) just include small bugfixes or security relevant stuff without breaking changes.
- **minor updates** (e.g. 1.0.0 => 1.1.0) include new features and smaller tasks without breaking changes.
- **major updates** (e.g. 1.0.0 => 2.0.0) contain breaking changes which can be refactorings, features or bugfixes.

### Contribution

**Pull requests** are welcome in general! Nevertheless please don't forget to add an issue and connect it to your
pull request. This is very helpful to understand what kind of issue the **PR** is going to solve.

- Bugfixes: Please describe what kind of bug your fix solves and how to reproduce the issue. Bugfixes can only be
  accepted if the issue can be reproduced.
- Features: Not every feature is relevant for the bulk of cal users. Please discuss a new feature in an issue first.

[1]: https://getcomposer.org/
