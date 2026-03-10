<?php
declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Provides HTMX-aware redirects.
 * For HTMX requests, uses HX-Redirect so HTMX performs client-side navigation.
 * For standard requests, uses Location with 303 See Other.
 */
trait RedirectTrait
{
    private function redirect(Response $response, Request $request, string $url): Response
    {
        if ($request->getHeaderLine('HX-Request') === 'true') {
            return $response
                ->withHeader('HX-Redirect', $url)
                ->withStatus(200);
        }

        return $response
            ->withHeader('Location', $url)
            ->withStatus(303);
    }
}
