<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Tests\Functional\Middleware;

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
use MarioMatzulla\Cal\Middleware\BufferedSapi;
use MarioMatzulla\Cal\Middleware\CalDavMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(CalDavMiddleware::class)]
#[CoversClass(BufferedSapi::class)]
final class CalDavMiddlewareTest extends FunctionalTestCase
{
    private const PROPFIND = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>';

    protected array $testExtensionsToLoad = ['mariomatzulla/cal'];

    protected function setUp(): void
    {
        parent::setUp();
        $hash = $this->get(PasswordHashFactory::class)->getDefaultHashInstance('FE')->getHashedPassword('secret');
        $this->get(ConnectionPool::class)->getConnectionForTable('fe_users')
            ->insert('fe_users', ['uid' => 1, 'pid' => 7, 'username' => 'anna', 'password' => $hash, 'name' => 'Anna Muster']);
    }

    private function handler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://temp', 204);
            }
        };
    }

    private function request(string $url, string $method = 'GET', array $headers = [], string $body = '', array $serverParams = []): ServerRequest
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();
        return new ServerRequest($url, $method, $stream, $headers, $serverParams);
    }

    private function process(ServerRequestInterface $request): ResponseInterface
    {
        return $this->get(CalDavMiddleware::class)->process($request, $this->handler());
    }

    #[Test]
    public function otherRequestsAreNotHandled(): void
    {
        self::assertSame(204, $this->process($this->request('https://example.org/'))->getStatusCode());
        self::assertSame(204, $this->process($this->request('https://example.org/caldav-help'))->getStatusCode());
    }

    #[Test]
    public function wellKnownUrlRedirectsToTheEndpoint(): void
    {
        $response = $this->process($this->request('https://example.org/.well-known/caldav'));
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/caldav/', $response->getHeaderLine('Location'));

        $site = new Site('main', 1, ['base' => 'https://example.org/club/']);
        $response = $this->process($this->request('https://example.org/club/.well-known/caldav/')->withAttribute('site', $site));
        self::assertSame('/club/caldav/', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function authenticationIsRequired(): void
    {
        $response = $this->process($this->request('https://example.org/caldav/principals/anna/', 'PROPFIND', ['Depth' => '0'], self::PROPFIND));
        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('TYPO3 CalDAV Server', $response->getHeaderLine('WWW-Authenticate'));
        self::assertStringContainsString('NotAuthenticated', (string)$response->getBody());
    }

    #[Test]
    public function principalsAreServed(): void
    {
        $response = $this->process($this->request(
            'https://example.org/caldav/principals/anna/',
            'PROPFIND',
            ['Depth' => '0', 'Authorization' => 'Basic ' . base64_encode('anna:secret'), 'Content-Type' => 'application/xml'],
            self::PROPFIND,
        ));
        self::assertSame(207, $response->getStatusCode());
        $body = (string)$response->getBody();
        self::assertStringContainsString('/caldav/principals/anna/', $body);
        self::assertStringContainsString('Anna Muster', $body);
        self::assertStringContainsString('application/xml', $response->getHeaderLine('Content-Type'));

        // basic authentication of the web server (PHP_AUTH_USER) and a site with path
        $site = new Site('main', 1, ['base' => 'https://example.org/club/']);
        $response = $this->process($this->request(
            'https://example.org/club/caldav/principals/anna/?x=1',
            'PROPFIND',
            ['Depth' => '0'],
            self::PROPFIND,
            ['PHP_AUTH_USER' => 'anna', 'PHP_AUTH_PW' => 'secret'],
        )->withAttribute('site', $site));
        self::assertSame(207, $response->getStatusCode());
        self::assertStringContainsString('/club/caldav/principals/anna/', (string)$response->getBody());
    }

    #[Test]
    public function htmlBrowserInDevelopmentContext(): void
    {
        $context = Environment::getContext();
        $initialize = static fn(ApplicationContext $context) => Environment::initialize(
            $context,
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
        $initialize(new ApplicationContext('Development'));
        try {
            $response = $this->process($this->request('https://example.org/caldav/', 'GET', ['Authorization' => 'Basic ' . base64_encode('anna:secret')]));
        } finally {
            $initialize($context);
        }
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<html', (string)$response->getBody());
        self::assertSame((string)$context, (string)Environment::getContext());
    }

    #[Test]
    public function bufferedSapiDoesNotUseTheGlobals(): void
    {
        self::assertSame('/', BufferedSapi::getRequest()->getUrl());
        BufferedSapi::sendResponse(new \Sabre\HTTP\Response(200, [], 'body'));
        $this->expectOutputString('');
    }
}
