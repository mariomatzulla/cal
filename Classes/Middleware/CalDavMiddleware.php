<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Middleware;

use MarioMatzulla\Cal\Service\Auth\TYPO3AuthBackend;
use MarioMatzulla\Cal\Service\Auth\TYPO3PrincipalBackend;
use MarioMatzulla\Cal\Service\CalDavBackend;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sabre\CalDAV;
use Sabre\DAV\Auth;
use Sabre\DAV\Browser\Plugin as BrowserPlugin;
use Sabre\DAV\Server;
use Sabre\DAVACL;
use Sabre\HTTP\Request as SabreRequest;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

/**
 * Serves the CalDAV endpoint "<site base>/caldav" via SabreDAV and redirects
 * "<site base>/.well-known/caldav" to it (service discovery, RFC 6764).
 */
final class CalDavMiddleware implements MiddlewareInterface
{
    private const ENDPOINT = '/caldav';
    private const WELL_KNOWN = '/.well-known/caldav';

    public function __construct(
        private readonly TYPO3AuthBackend $authBackend,
        private readonly TYPO3PrincipalBackend $principalBackend,
        private readonly CalDavBackend $calendarBackend,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sitePath = $this->getSitePath($request);
        $baseUri = $sitePath . self::ENDPOINT;
        $path = $request->getUri()->getPath();

        // Clients that only know the domain ask "/.well-known/caldav" first
        if (rtrim($path, '/') === $sitePath . self::WELL_KNOWN) {
            return new RedirectResponse($baseUri . '/', 301);
        }

        // Only handle "<base>/caldav" and "<base>/caldav/...", not e.g. "/caldav-help"
        if ($path !== $baseUri && !str_starts_with($path, $baseUri . '/')) {
            return $handler->handle($request);
        }

        $authBackend = $this->authBackend;
        $authBackend->setRealm('TYPO3 CalDAV Server');
        $principalBackend = $this->principalBackend;
        $calendarBackend = $this->calendarBackend;

        $server = new Server([
            new CalDAV\Principal\Collection($principalBackend),
            new CalDAV\CalendarRoot($principalBackend, $calendarBackend),
        ], new BufferedSapi());
        $server->httpRequest = $this->createSabreRequest($request);
        $server->setBaseUri($baseUri);

        $server->addPlugin(new Auth\Plugin($authBackend));
        // ACL plugin: makes sure a user can only access his own calendar home
        $server->addPlugin(new DAVACL\Plugin());
        $server->addPlugin(new CalDAV\Plugin());
        if (Environment::getContext()->isDevelopment()) {
            // HTML browser for debugging, never expose it in production
            $server->addPlugin(new BrowserPlugin());
        }

        $server->start();

        $sabreResponse = $server->httpResponse;
        $response = new Response();
        $response->getBody()->write($sabreResponse->getBodyAsString());
        foreach ($sabreResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, (string)$value);
            }
        }

        return $response->withStatus($sabreResponse->getStatus());
    }

    /**
     * The PSR-7 request as request of SabreDAV. TYPO3 takes the headers from the HTTP_* server variables
     * only, the credentials can be elsewhere (like Sabre\HTTP\Sapi handles it):
     * - REDIRECT_HTTP_AUTHORIZATION: Apache with PHP as CGI / FPM after the rewrite to index.php
     * - PHP_AUTH_USER / PHP_AUTH_PW: Apache with mod_php
     */
    private function createSabreRequest(ServerRequestInterface $request): SabreRequest
    {
        $uri = $request->getUri();
        $headers = $request->getHeaders();
        $serverParams = $request->getServerParams();
        if (!$request->hasHeader('Authorization')) {
            if ((string)($serverParams['REDIRECT_HTTP_AUTHORIZATION'] ?? '') !== '') {
                $headers['Authorization'] = [(string)$serverParams['REDIRECT_HTTP_AUTHORIZATION']];
            } elseif (isset($serverParams['PHP_AUTH_USER'])) {
                $headers['Authorization'] = ['Basic ' . base64_encode($serverParams['PHP_AUTH_USER'] . ':' . ($serverParams['PHP_AUTH_PW'] ?? ''))];
            }
        }
        $sabreRequest = new SabreRequest(
            $request->getMethod(),
            $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : ''),
            $headers,
            (string)$request->getBody(),
        );
        $sabreRequest->setAbsoluteUrl((string)$uri);
        $sabreRequest->setHTTPVersion($request->getProtocolVersion());
        return $sabreRequest;
    }

    /**
     * Path of the site base without trailing slash, e.g. "" or "/club"
     */
    private function getSitePath(ServerRequestInterface $request): string
    {
        $site = $request->getAttribute('site');
        if ($site instanceof SiteInterface) {
            return rtrim($site->getBase()->getPath(), '/');
        }
        return '';
    }
}
