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
        ]);
        $server->setBaseUri($baseUri);

        $server->addPlugin(new Auth\Plugin($authBackend));
        // ACL plugin: makes sure a user can only access his own calendar home
        $server->addPlugin(new DAVACL\Plugin());
        $server->addPlugin(new CalDAV\Plugin());
        if (Environment::getContext()->isDevelopment()) {
            // HTML browser for debugging, never expose it in production
            $server->addPlugin(new BrowserPlugin());
        }

        // SabreDAV writes directly to the output buffer, capture it
        ob_start();
        $server->start();
        $content = (string)ob_get_clean();

        $sabreResponse = $server->httpResponse;
        $response = new Response();
        $response->getBody()->write($content);
        foreach ($sabreResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, (string)$value);
            }
        }

        return $response->withStatus($sabreResponse->getStatus());
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
