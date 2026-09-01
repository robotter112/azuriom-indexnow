<?php

namespace Azuriom\Plugin\Seo\Middleware;

use Azuriom\Plugin\Seo\CanonicalUrl;
use Azuriom\Plugin\Seo\Controllers\SitemapController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds <link rel="canonical"> to HTML pages that do not have one.
 *
 * Azuriom's core layout has no @stack('meta'), so a plugin cannot push into the
 * head of an arbitrary theme. Rewriting the finished response is the only way
 * that works regardless of the theme - which is why this is deliberately narrow:
 * it only touches successful HTML responses to GET requests that actually have a
 * </head> and no canonical yet, and it leaves the body untouched otherwise.
 */
class AddCanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! SitemapController::canonicalEnabled() || ! $this->shouldRewrite($request, $response)) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || ! str_contains($html, '</head>')) {
            return $response;
        }

        // Never overwrite a canonical the theme or another plugin already set.
        if (preg_match('/<link[^>]+rel=(["\'])canonical\1/i', $html)) {
            return $response;
        }

        $url = CanonicalUrl::build($request->fullUrl(), SitemapController::canonicalKeptParameters());
        $tag = '<link rel="canonical" href="'.e($url).'">';

        // Only the first </head>, so a literal one inside page content cannot
        // move the tag into the body.
        $position = strpos($html, '</head>');

        $response->setContent(substr($html, 0, $position).$tag.substr($html, $position));

        return $response;
    }

    protected function shouldRewrite(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        // Streamed and binary responses have no content to work with.
        if (! method_exists($response, 'getContent')) {
            return false;
        }

        $type = $response->headers->get('Content-Type', '');

        return str_contains($type, 'text/html');
    }
}
