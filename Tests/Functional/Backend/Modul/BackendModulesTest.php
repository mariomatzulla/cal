<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Backend\Modul;

/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use MarioMatzulla\Cal\Backend\Modul\IcsGeneratorController;
use MarioMatzulla\Cal\Backend\Modul\IndexController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(IndexController::class)]
#[CoversClass(IcsGeneratorController::class)]
final class BackendModulesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'cal' => [
                'hideLocationTextfield' => '0',
                'hideOrganizerTextfield' => '0',
                'useLocationStructure' => 'tx_cal_domain_model_location',
                'useOrganizerStructure' => 'tx_cal_domain_model_organizer',
                'showTimes' => '1',
                'useTeaser' => '0',
                'hideDeviationRecords' => '1',
                'recurrenceStart' => 'cal:yearstart',
                'recurrenceEnd' => '+3 years',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Hooks/Fixtures/DataHandler.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    private function route(string $module, string $action): Route
    {
        $router = $this->get(Router::class);
        foreach ($router->getRoutes() as $identifier => $route) {
            if (str_starts_with((string)$identifier, $module) && ($route->getOptions()['action'] ?? '') === $action) {
                // the backend route (getRoutes() returns the Symfony routes)
                return $router->match($route->getPath());
            }
        }
        self::fail('No route for ' . $module . ' / ' . $action);
    }

    private function dispatch(string $module, string $action, array $arguments = []): ResponseInterface
    {
        $route = $this->route($module, $action);
        $request = (new ServerRequest('https://example.org/typo3' . $route->getPath(), 'GET'))
            ->withQueryParams($arguments)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('module', $this->get(ModuleProvider::class)->getModule($module))
            ->withAttribute('route', $route);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $this->get(Bootstrap::class)->handleBackendRequest($request);
    }

    private function indexCount(): int
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_index')
            ->count('uid', 'tx_cal_domain_model_index', []);
    }

    #[Test]
    public function indexerModule(): void
    {
        $response = $this->dispatch('cal_indexer_module', 'index');
        self::assertSame(200, $response->getStatusCode());

        // a recurring event on a page which does not exist (anymore)
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_cal_domain_model_event')
            ->update('tx_cal_domain_model_event', ['freq' => 'week', 'until' => 1796256000], ['uid' => 8]);
        $response = $this->dispatch('cal_indexer_module', 'generate');
        self::assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        self::assertStringContainsString('Calendar records', $body);

        $response = $this->dispatch('cal_indexer_module', 'result', ['pageIds' => ['5', 'x'], 'starttime' => '20260101', 'endtime' => '+3 years']);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Calendar records', (string)$response->getBody());
        self::assertGreaterThan(0, $this->indexCount());
    }

    #[Test]
    public function indexerNeedsPagesAndValidDates(): void
    {
        foreach ([
            ['pageIds' => [], 'starttime' => '20260101', 'endtime' => '20261231'],
            ['pageIds' => ['5'], 'starttime' => '', 'endtime' => '20261231'],
            ['pageIds' => ['5'], 'starttime' => 'no date', 'endtime' => '20261231'],
        ] as $arguments) {
            $response = $this->dispatch('cal_indexer_module', 'result', $arguments);
            self::assertSame(303, $response->getStatusCode());
            self::assertStringContainsString('generate', $response->getHeaderLine('Location'));
        }
        self::assertSame(0, $this->indexCount());
    }

    #[Test]
    public function icsGeneratorModule(): void
    {
        self::assertSame(200, $this->dispatch('cal_ics_generator_module', 'index')->getStatusCode());

        $response = $this->dispatch('cal_ics_generator_module', 'generate');
        self::assertSame(200, $response->getStatusCode());
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_cal_domain_model_event');
        $caldavData = (string)$queryBuilder->select('caldav_data')->from('tx_cal_domain_model_event')
            ->where($queryBuilder->expr()->eq('uid', 1))->executeQuery()->fetchOne();
        self::assertStringContainsString('BEGIN:VCALENDAR', $caldavData);

        self::assertSame(200, $this->dispatch('cal_ics_generator_module', 'check')->getStatusCode());
    }
}
