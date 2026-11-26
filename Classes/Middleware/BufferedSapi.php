<?php

declare(strict_types=1);

namespace MarioMatzulla\Cal\Middleware;

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
use Sabre\HTTP\Request;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi;

/**
 * SabreDAV reads the request from the PHP globals and sends the response directly. In TYPO3 the PSR-7
 * request of the middleware is used (CalDavMiddleware sets it) and the response is returned as PSR-7
 * response, so this SAPI neither reads nor sends anything.
 *
 * @internal
 */
final class BufferedSapi extends Sapi
{
    public static function getRequest(): Request
    {
        return new Request('GET', '/');
    }

    public static function sendResponse(ResponseInterface $response) {}
}
