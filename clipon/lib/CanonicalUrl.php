<?php

/**
 * Builds the canonical no-trailing-slash location for a public request.
 *
 * The caller is responsible for verifying that the path resolves to an active
 * public route. Only safe, idempotent requests are redirected.
 */
function clipon_trailing_slash_redirect_target(
    string $requestPath,
    string $basePath = '',
    string $queryString = '',
    string $requestMethod = 'GET'
): ?string {
    $method = strtoupper($requestMethod);
    if ($method !== 'GET' && $method !== 'HEAD') {
        return null;
    }

    if ($requestPath === '/' || !str_ends_with($requestPath, '/')) {
        return null;
    }

    $canonicalPath = rtrim($requestPath, '/');
    if ($canonicalPath === '') {
        return null;
    }

    $normalizedBasePath = rtrim($basePath, '/');
    if ($normalizedBasePath === '/') {
        $normalizedBasePath = '';
    }

    $target = $normalizedBasePath . '/' . ltrim($canonicalPath, '/');
    if ($queryString !== '') {
        $target .= '?' . $queryString;
    }

    return $target;
}
