<?php

class RedirectService
{
    private RouteMap|RouteMapStub $routeMap;

    public function __construct()
    {
        /** @var RouteMap $routeMap */
        $routeMap = registry()->get('route_map');
        $this->routeMap = $routeMap;
    }

    /**
     * Get all redirects from RouteMap
     * @return array
     */
    public function getAllRedirects(): array
    {
        return $this->routeMap->getAllRedirects();
    }

    /**
     * Get all active routes for stats
     * @return array
     */
    public function getAllRoutes(): array
    {
        return $this->routeMap->getAllRoutes();
    }

    /**
     * Add a new redirect
     * @param string $oldUrl
     * @param string $newUrl
     * @param int $code
     * @return bool
     */
    public function addRedirect(string $oldUrl, string $newUrl, int $code = 301): bool
    {
        $oldUrl = trim($oldUrl);
        $newUrl = trim($newUrl);
        
        if (empty($oldUrl) || empty($newUrl)) {
            return false;
        }

        return $this->routeMap->addRedirect($oldUrl, $newUrl, $code);
    }

    /**
     * Remove an existing redirect
     * @param string $oldUrl
     * @return void
     */
    public function removeRedirect(string $oldUrl): void
    {
        $this->routeMap->removeRedirect($oldUrl);
    }
}
