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
