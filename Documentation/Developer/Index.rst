..  include:: /Includes.rst.txt

..  _developer:

==============
For developers
==============

..  _developer-events:

PSR-14 events
=============

The controller gets its data through PSR-14 events. cal ships listeners for
its own records (`EventService`, `LocationService`, `OrganizerService`,
`CategoryService`). Own listeners can add data from other sources or change the
result.

`MarioMatzulla\Cal\Events\SearchEventsEvent`
    Events of a time range. Getters: `getStart()`, `getEnd()`,
    `getCalendarIds()`, `getExcludedCalendarIds()`. Result: `getEvents()`,
    `setEvents()`.

`MarioMatzulla\Cal\Events\GetEventEvent`
    A single event (event view, iCalendar download). Getters: `getType()`,
    `getUid()`, `getDate()`, `getCalendarIds()`, `getExcludedCalendarIds()`.
    Result: `getEvent()`, `setEvent()`.

`MarioMatzulla\Cal\Events\FilterEventsEvent`
    Filters the found events, e.g. by the categories of the plugin. Getters:
    `getEvents()`, `getSettings()`. Result: `setEvents()`.

`MarioMatzulla\Cal\Events\GetLocationEvent`, `MarioMatzulla\Cal\Events\GetOrganizerEvent`
    A location or organizer of the type `getType()` (default
    `tx_cal_location`, `tx_cal_organizer`) and the uid `getUid()`. Result:
    `setLocation()` / `setOrganizer()`. These events are stoppable: the first
    listener that finds the record stops the propagation.

`SearchEventsEvent`, `GetEventEvent`, `GetLocationEvent` and `GetOrganizerEvent`
also have `getStoragePageIds()`: the page ids of the storage
folders (setting `pidList` with `recursive`). Listeners should only return
records of these pages, an empty array means no limitation.

Example: a listener for locations of another table.

..  code-block:: php
    :caption: EXT:site_package/Classes/EventListener/VenueLocationListener.php

    <?php

    declare(strict_types=1);

    namespace Vendor\SitePackage\EventListener;

    use MarioMatzulla\Cal\Events\GetLocationEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use Vendor\SitePackage\Domain\Repository\VenueRepository;

    #[AsEventListener(identifier: 'site-package/cal-venue', before: 'cal/location-service')]
    final readonly class VenueLocationListener
    {
        public function __construct(private VenueRepository $venueRepository) {}

        public function __invoke(GetLocationEvent $event): void
        {
            if ($event->getType() !== 'tx_sitepackage_venue') {
                return;
            }
            $venue = $this->venueRepository->findByUid($event->getUid());
            if ($venue !== null) {
                // The object must implement MarioMatzulla\Cal\Model\LocationInterface
                $event->setLocation($venue);
                $event->stopPropagation();
            }
        }
    }

..  _developer-routes:

Routing
=======

The route enhancer is in :file:`EXT:cal/Configuration/Routes/Cal.yaml`, see
:ref:`speaking-urls`. Records written directly to the database get their URL
segment from `MarioMatzulla\Cal\Service\SlugService::fillMissing()`.

..  _developer-quality:

Code quality
============

..  code-block:: bash

    composer install
    composer cs:check   # TYPO3 coding guidelines (php-cs-fixer)
    composer cs:fix
    composer stan       # PHPStan, configuration in phpstan.neon.dist

..  _developer-tests:

Tests
=====

Unit tests and functional tests (SQLite) with PHPUnit and the TYPO3 testing
framework:

..  code-block:: bash

    composer test:unit
    composer test:functional
    composer test            # both
    composer test:coverage   # both with code coverage, needs PCOV or Xdebug

The coverage report is written to :file:`.Build/coverage/html`. The
configurations are in :file:`Build/phpunit/`.

..  _developer-release:

Release
=======

:file:`Build/Scripts/release.sh` creates the extension package (zip) of a git
revision. Files marked with `export-ignore` in :file:`.gitattributes` are not
part of it. The versions in :file:`composer.json` (`extra.typo3/cms.version`)
and :file:`ext_emconf.php` must be equal.

..  code-block:: bash

    # cal_<version>.zip for the TER and Composer installations
    Build/Scripts/release.sh

    # cal_<version>-bundled.zip for installations without Composer
    Build/Scripts/release.sh --bundle

    # another git revision or target directory (default: HEAD, .Build/release)
    Build/Scripts/release.sh --bundle --ref v4.0.0 --output ~/Desktop

With `--bundle` the script installs the Composer packages of `require` that are
no TYPO3 packages (`sabre/dav`, `sabre/vobject` and their dependencies) into
:file:`Resources/Private/Php/ComposerVendor` and declares them in
`extra.typo3/cms.Package.providesPackages` of the packaged
:file:`composer.json`. TYPO3 loads their :file:`autoload.php` in classic mode.
`psr/log` is shipped by TYPO3 and not bundled. The packages are resolved for the
lowest supported PHP version (8.2). The git repository itself contains no
bundled libraries, an update of them is a new release with `--bundle`.

..  _developer-docs:

Documentation
=============

Render this documentation locally with Docker:

..  code-block:: bash

    docker run --rm --pull always -v $(pwd):/project -it \
        ghcr.io/typo3-documentation/render-guides:latest --config=Documentation
