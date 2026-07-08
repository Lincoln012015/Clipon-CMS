<?php
/**
 * Class RouteMap
 *
 * Singleton for managing URL routing, slugs, and redirects in the CMS.
 * Handles loading, saving, and rebuilding the route map, as well as sitemap generation.
 * Supports multilingual slugs and automatic redirects.
 */
class RouteMapStub {
    /** @var RouteMap|null Singleton instance */
    private static $instance = null;
    /** @var array Main route map: url => route data */
    private $map = [];
    /** @var array Slug index: lang => slug => url */
    private $slugMap = [];
    /** @var array Redirects: url => [target, code, ...] */
    private $redirects = [];
    /** @var string Path to the route map file */
    private $mapFile;
    /** @var bool Whether the map is loaded */
    private $loaded = false;
    /** @var bool Whether sitemap needs to be regenerated */
    private $needsSitemapUpdate = false;
    /** @var string|null Last rebuild or registration error */
    private $lastError = null;
    /** @var int Safety bound for redirect chain traversal */
    private const MAX_REDIRECT_CHAIN_LENGTH = 100;

    /** @var array|null Cached enabled language codes and primary language */
    private static $languageMeta = null;

    /**
     * Builds a type-scoped slug index key to avoid false collisions between page/blog.
     * @param string $slug
     * @param string $type
     * @return string
     */
    private function slugIndexKey($slug, $type = 'page') {
        $normalizedType = ($type === 'blog') ? 'blog' : 'page';
        return $normalizedType . ':' . (string)$slug;
    }

    /**
     * Private constructor for singleton pattern.
     * Registers shutdown function to generate sitemap if needed.
     */
    public function __construct() {
        $this->mapFile = C_CONFIG_PATH . '/route_map.php';
        // Ensures sitemap is generated once per request if there were changes
        register_shutdown_function([$this, 'generateSitemapIfRequired']);
    }

    /**
     * Prevent cloning of singleton.
     */
    private function __clone() {}
    /**
     * Prevent unserialization of singleton.
     * @throws \Exception
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }
    /**
     * Ensures JsonStorage.php is loaded only once per request.
     */
    private static function ensureJsonStorage() {
        static $loaded = false;
        if ($loaded) return;
        $jsonStorage = __DIR__ . '/JsonStorage.php';
        if (file_exists($jsonStorage)) {
            require_once $jsonStorage;
        }
        $loaded = true;
    }
    /**
     * Logs route map errors when logger is available.
     * @param string $message
     */
    private function logError($message) {
        if (class_exists('Log')) {
            Log::error($message);
        }
    }
    /**
     * Returns the singleton instance.
     * @return RouteMap
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Loads the route map and redirects from storage if not already loaded.
     * Populates slug index for fast lookup.
     */
    private function load() {
        if ($this->loaded) {
            return;
        }

        if (file_exists($this->mapFile)) {
            self::ensureJsonStorage();
            try {
                $data = read_json_file($this->mapFile, true);
                if (!is_array($data)) {
                    throw new \Exception("Invalid route map data format");
                }

                $this->map = $data['routes'] ?? [];
                $this->redirects = $data['redirects'] ?? [];
                if (!is_array($this->map)) {
                    throw new \Exception("Invalid routes format in route map");
                }
                if (!is_array($this->redirects)) {
                    throw new \Exception("Invalid redirects format in route map");
                }

                $this->slugMap = [];
                // Build slug index for all languages
                foreach ($this->map as $url => $routeData) {
                    if (isset($routeData['slug'])) {
                        $lang = $routeData['lang'] ?? $this->getPrimaryLanguageCode();
                        $slug = (string)$routeData['slug'];
                        $type = (string)($routeData['type'] ?? 'page');
                        $slugKey = $this->slugIndexKey($slug, $type);
                        if (!isset($this->slugMap[$lang][$slugKey])) {
                            $this->slugMap[$lang][$slugKey] = $url;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logError("Failed to load RouteMap: " . $e->getMessage());
                // Prevent fatal error if file is corrupt
                $this->map = [];
                $this->redirects = [];
                $this->slugMap = [];
            }
        }

        $this->loaded = true;
    }
    /**
     * Returns the current route map (url => route data).
     * @return array
     */
    public function getMap() {
        $this->load();
        return $this->map;
    }
    /**
     * Saves the current route map and redirects to storage.
     * Marks sitemap for regeneration.
     */
    private function save() {
        $data = [
            'routes' => $this->map,
            'redirects' => $this->redirects,
            'updated' => date('Y-m-d H:i:s')
        ];
        self::ensureJsonStorage();
        if (!write_json_file($this->mapFile, $data)) {
            $this->logError("Failed to save RouteMap to {$this->mapFile}");
            return false;
        }
        $this->needsSitemapUpdate = true;
        return true;
    }
    /**
     * Generates sitemap if there were changes during the request.
     */
    public function generateSitemapIfRequired() {
        if ($this->needsSitemapUpdate) {
            $this->generateSitemap();
            $this->needsSitemapUpdate = false;
        }
    }
    /**
     * Generates the sitemap.xml file based on the current route map.
     * Handles alternate language versions and lastmod dates.
     */
    private function generateSitemap() {
        global $request;
        $settings = Settings::load();
        $baseUrl = c_site_url();
        if (empty($baseUrl) && null !== $request->server('HTTP_HOST')) {
            $protocol = (!empty($request->server('HTTPS')) && $request->server('HTTPS') !== 'off') ? "https://" : "http://";
            $host = $request->server('HTTP_HOST');
            
            $basePath = defined('CMS_BASE_PATH') ? CMS_BASE_PATH : '';
            $baseUrl = rtrim($protocol . $host . $basePath, '/');
        }
        if (empty($baseUrl)) {
            return;
        }

        $xml = $this->buildSitemapXml($baseUrl, $settings);
        if ($xml === '') {
            return;
        }

        $root = dirname(__DIR__, 2);
        $sitemapFile = $root . '/sitemap.xml';
        $this->writeSitemapFile($root, $sitemapFile, $xml);
    }

    /**
     * Builds sitemap XML for current route map.
     * @param string $baseUrl
     * @param array $settings
     * @return string
     */
    private function buildSitemapXml($baseUrl, $settings) {

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . PHP_EOL;
        $mainLang = $this->getPrimaryLanguageCode();
        // Group routes by content id for alternate language links
        $groups = [];
        foreach ($this->map as $url => $data) {
            if (isset($data['active']) && $data['active'] === false) continue;
            $contentId = ($data['type'] ?? 'page') . ':' . ($data['slug'] ?? 'index');
            $groups[$contentId][$data['lang'] ?? $mainLang] = $url;
        }

        foreach ($this->map as $url => $data) {
            if (isset($data['active']) && $data['active'] === false) continue;
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($baseUrl . $url) . '</loc>' . PHP_EOL;
            $contentId = ($data['type'] ?? 'page') . ':' . ($data['slug'] ?? 'index');
            // Add alternate language links if available
            if (isset($groups[$contentId]) && count($groups[$contentId]) > 1) {
                $primaryAltUrl = $groups[$contentId][$mainLang] ?? null;

                foreach ($groups[$contentId] as $lang => $altUrl) {
                    if (!Settings::isValidLanguageCode((string)$lang)) {
                        continue;
                    }
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $lang . '" href="' . htmlspecialchars($baseUrl . $altUrl) . '" />' . PHP_EOL;
                }

                if ($primaryAltUrl !== null) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($baseUrl . $primaryAltUrl) . '" />' . PHP_EOL;
                }
            }
            // Set lastmod date based on content file
            $lastMod = date('Y-m-d');
            $slug = $data['slug'] ?? trim($url, '/');
            if ($slug === '' || $slug === '/') $slug = 'index'; // Fallback
            $dir = (($data['type'] ?? 'page') === 'blog') ? '/blog/' : '/pages/';
            $contentFile = C_CONTENT_PATH . $dir . $slug . '.php';
            if (file_exists($contentFile)) {
                $lastMod = date('Y-m-d', filemtime($contentFile));
            }
            $xml .= '    <lastmod>' . $lastMod . '</lastmod>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Writes sitemap XML atomically when destination is writable.
     * @param string $root
     * @param string $sitemapFile
     * @param string $xml
     */
    private function writeSitemapFile($root, $sitemapFile, $xml) {
        if (is_writable($root) || (file_exists($sitemapFile) && is_writable($sitemapFile))) {
            $tmpSitemap = $sitemapFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (file_put_contents($tmpSitemap, $xml, LOCK_EX) !== false) {
                if (!rename($tmpSitemap, $sitemapFile)) {
                    if (file_exists($tmpSitemap)) unlink($tmpSitemap);
                }
            }
        }
    }
    /**
     * Finds route data by URL.
     * @param string $url
     * @return array|null
     */
    public function findRoute($url) {
        $this->load();
        $url = $this->normalizeUrl($url);
        return $this->map[$url] ?? null;
    }
    /**
     * Finds redirect data by URL.
     * @param string $url
     * @return array|null
     */
    public function findRedirect($url) {
        $this->load();
        $url = $this->normalizeUrl($url);
        return $this->redirects[$url] ?? null;
    }

    /**
     * Returns a localized URL for resource slug and type.
     * @param string $slug
     * @param string|null $lang
     * @param string $type
     * @return string|null
     */
    public function getUrl($slug, $lang = null, $type = 'page') {
        $this->load();
        $lang = $lang ?? $this->getPrimaryLanguageCode();
        $primary = $this->getPrimaryLanguageCode();
        $type = ($type === 'blog') ? 'blog' : 'page';
        $slugKey = $this->slugIndexKey((string)$slug, $type);

        if (isset($this->slugMap[$lang][$slugKey])) {
            return $this->slugMap[$lang][$slugKey];
        }

        if (isset($this->slugMap[$primary][$slugKey])) {
            return $this->slugMap[$primary][$slugKey];
        }

        $primaryMatch = null;

        foreach ($this->map as $url => $route) {
            if (($route['slug'] ?? '') !== $slug) {
                continue;
            }
            if (($route['type'] ?? 'page') !== $type) {
                continue;
            }

            $routeLang = $route['lang'] ?? $primary;
            if ($routeLang === $lang) {
                return $url;
            }

            if ($routeLang === $primary && $primaryMatch === null) {
                $primaryMatch = $url;
            }
        }

        return $primaryMatch;
    }

    /**
     * Returns all language alternates for resource slug and type.
     * @param string $slug
     * @param string $type
     * @return array<string,string>
     */
    public function getAlternates($slug, $type = 'page') {
        $this->load();
        $type = ($type === 'blog') ? 'blog' : 'page';
        $primary = $this->getPrimaryLanguageCode();
        $alternates = [];

        foreach ($this->map as $url => $route) {
            if (($route['slug'] ?? '') !== $slug) {
                continue;
            }
            if (($route['type'] ?? 'page') !== $type) {
                continue;
            }

            $routeLang = (string)($route['lang'] ?? $primary);
            if (!Settings::isValidLanguageCode($routeLang)) {
                continue;
            }
            $alternates[$routeLang] = $url;
        }

        return $alternates;
    }

    /**
     * Returns only active language alternates for resource slug and type.
     * @param string $slug
     * @param string $type
     * @return array<string,string>
     */
    public function getActiveAlternates($slug, $type = 'page') {
        $this->load();
        $type = ($type === 'blog') ? 'blog' : 'page';
        $primary = $this->getPrimaryLanguageCode();
        $alternates = [];

        foreach ($this->map as $url => $route) {
            if (($route['slug'] ?? '') !== $slug) {
                continue;
            }
            if (($route['type'] ?? 'page') !== $type) {
                continue;
            }
            if (isset($route['active']) && $route['active'] === false) {
                continue;
            }

            $routeLang = (string)($route['lang'] ?? $primary);
            if (!Settings::isValidLanguageCode($routeLang)) {
                continue;
            }
            $alternates[$routeLang] = $url;
        }

        return $alternates;
    }
    /**
     * Sets or updates a route for a given URL.
     * Updates slug index and removes conflicting redirects.
     * @param string $url
     * @param array $data
     */
    public function setRoute($url, $data) {
        $this->load();
        
        $url = $this->normalizeUrl($url);
        if ($url !== '/' && empty($data['slug'])) {
            $data['slug'] = trim($url, '/');
        }
        
        $prevData = $this->map[$url] ?? [];
        $nextData = array_merge([
            'type' => 'page',
            'lang' => $this->getPrimaryLanguageCode(),
            'slug' => '',
            'title' => '',
            'template' => null,
            'directory_id' => null,
            'seo' => [],
            'updated' => date('Y-m-d H:i:s')
        ], $prevData, $data);

        $newSlug = $nextData['slug'] ?? '';
        if ($newSlug !== '') {
            $newLang = $nextData['lang'] ?? $this->getPrimaryLanguageCode();
            $newType = (string)($nextData['type'] ?? 'page');
            $newSlugKey = $this->slugIndexKey((string)$newSlug, $newType);
            $conflictUrl = $this->slugMap[$newLang][$newSlugKey] ?? null;
            if ($conflictUrl !== null && $conflictUrl !== $url) {
                $this->logError("Route slug collision for lang '{$newLang}' and slug '{$newSlug}' between '{$conflictUrl}' and '{$url}'");
                return false;
            }
        }

        if (isset($this->redirects[$url])) {
            unset($this->redirects[$url]);
        }

        if (!empty($prevData['slug'])) {
            $prevLang = $prevData['lang'] ?? $this->getPrimaryLanguageCode();
            $prevType = (string)($prevData['type'] ?? 'page');
            $prevSlugKey = $this->slugIndexKey((string)$prevData['slug'], $prevType);
            if (isset($this->slugMap[$prevLang][$prevSlugKey]) && $this->slugMap[$prevLang][$prevSlugKey] === $url) {
                unset($this->slugMap[$prevLang][$prevSlugKey]);
            }
        }

        $this->map[$url] = $nextData;
        if ($newSlug !== '') {
            $newLang = $this->map[$url]['lang'] ?? $this->getPrimaryLanguageCode();
            $newType = (string)($this->map[$url]['type'] ?? 'page');
            $newSlugKey = $this->slugIndexKey((string)$newSlug, $newType);
            $this->slugMap[$newLang][$newSlugKey] = $url;
        }

        return $this->save();
    }
    /**
     * Removes a route by URL and updates slug index.
     * @param string $url
     */
    public function removeRoute($url) {
        $this->load();
        
        $url = $this->normalizeUrl($url);
        
        if (isset($this->map[$url])) {
            $slug = $this->map[$url]['slug'] ?? '';
            $lang = $this->map[$url]['lang'] ?? $this->getPrimaryLanguageCode();
            $type = (string)($this->map[$url]['type'] ?? 'page');
            $slugKey = $this->slugIndexKey((string)$slug, $type);
            if ($slug !== '' && isset($this->slugMap[$lang][$slugKey]) && $this->slugMap[$lang][$slugKey] === $url) {
                unset($this->slugMap[$lang][$slugKey]);
            }

            unset($this->map[$url]);
            $this->save();
        }
    }
    /**
     * Adds a redirect from oldUrl to newUrl, flattening redirect chains.
     * @param string $oldUrl
     * @param string $newUrl
     * @param int $code
     * @return bool True if redirect added, false if not needed
     */
    public function addRedirect($oldUrl, $newUrl, $code = 301) {
        $this->load();
        
        $oldUrl = $this->normalizeUrl($oldUrl);
        $newUrl = $this->normalizeUrl($newUrl);
        if ($oldUrl === $newUrl) {
            return false;
        }  

        $newUrl = $this->resolveRedirectTarget($newUrl, [$oldUrl]);
        if ($oldUrl === $newUrl || $this->wouldCreateRedirectCycle($oldUrl, $newUrl)) {
            $this->logError("Refusing to create redirect cycle: {$oldUrl} -> {$newUrl}");
            return false;
        }

        foreach ($this->redirects as $source => $data) {
            if ($data['target'] === $oldUrl) {
                if ($source === $newUrl) {
                    unset($this->redirects[$source]);
                }
            }
        }
        
        
        $this->redirects[$oldUrl] = [
            'target' => $newUrl,
            'code' => $code,
            'created' => date('Y-m-d H:i:s')
        ];
        
        return $this->save();
    }
    /**
     * Resolves final redirect target by flattening existing chains.
     * @param string $url
     * @param array $seedVisited
     * @return string
     */
    private function resolveRedirectTarget($url, $seedVisited = []) {
        $visited = [];
        foreach ($seedVisited as $seed) {
            $visited[$seed] = true;
        }

        $current = $url;
        $hops = 0;
        while (isset($this->redirects[$current]) && $hops < self::MAX_REDIRECT_CHAIN_LENGTH) {
            if (isset($visited[$current])) {
                break;
            }
            $visited[$current] = true;

            $nextTarget = $this->normalizeUrl((string)($this->redirects[$current]['target'] ?? '/'));
            if ($nextTarget === $current) {
                break;
            }

            $current = $nextTarget;
            $hops++;
        }

        return $current;
    }
    /**
     * Checks whether a redirect source -> target introduces a cycle.
     * @param string $sourceUrl
     * @param string $targetUrl
     * @return bool
     */
    private function wouldCreateRedirectCycle($sourceUrl, $targetUrl) {
        $visited = [];
        $current = $targetUrl;
        $hops = 0;

        while (isset($this->redirects[$current]) && $hops < self::MAX_REDIRECT_CHAIN_LENGTH) {
            if ($current === $sourceUrl) {
                return true;
            }
            if (isset($visited[$current])) {
                return false;
            }
            $visited[$current] = true;

            $nextTarget = $this->normalizeUrl((string)($this->redirects[$current]['target'] ?? '/'));
            if ($nextTarget === $sourceUrl) {
                return true;
            }
            if ($nextTarget === $current) {
                return false;
            }

            $current = $nextTarget;
            $hops++;
        }

        return $hops >= self::MAX_REDIRECT_CHAIN_LENGTH;
    }
    /**
     * Removes a redirect by URL.
     * @param string $url
     */
    public function removeRedirect($url) {
        $this->load();
        
        $url = $this->normalizeUrl($url);
        
        if (isset($this->redirects[$url])) {
            unset($this->redirects[$url]);
            $this->save();
        }
    }
    /**
     * Returns all routes.
     * @return array
     */
    public function getAllRoutes() {
        $this->load();
        return $this->map;
    }
    /**
     * Returns all redirects.
     * @return array
     */
    public function getAllRedirects() {
        $this->load();
        return $this->redirects;
    }

    /**
     * Returns last route-map error string (if any).
     * @return string|null
     */
    public function getLastError() {
        return $this->lastError;
    }
    /**
     * Rebuilds the route map from content files (pages and blog posts).
     * @param string|null $pagesDir
     */
    public function rebuild($pagesDir = null) {
        $lockFile = C_CONFIG_PATH . '/route_map.lock';
        $fp = @fopen($lockFile, 'c+');
        if ($fp && !flock($fp, LOCK_EX)) {
            fclose($fp);
            $fp = false;
        }

        // Ensure language meta is recalculated for this rebuild invocation.
        // Static cached language meta could be stale if settings changed earlier
        // in the same process; clear it so registerLocalizedRoutes uses up-to-date languages.
        self::$languageMeta = null;

        $this->load();
        $this->lastError = null;
        
        $pagesDir = $pagesDir ? rtrim((string)$pagesDir, '/\\') . '/' : C_CONTENT_PATH . '/pages/';
        $blogDir = C_CONTENT_PATH . '/blog/';
        
        $this->map = [];
        $this->slugMap = []; 
        $this->clearAutoHomeRedirects();
        
        self::ensureJsonStorage();

        try {
            $this->processPages($pagesDir);
            $this->processBlogPosts($blogDir);
        } catch (\Throwable $e) {
            if ($fp) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            $this->lastError = $e->getMessage();
            $this->logError('RouteMap rebuild failed: ' . $this->lastError);
            return false;
        }
        
        if (!$this->save()) {
            if ($fp) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            $this->lastError = 'Failed to save route map';
            return false;
        }

        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return true;
    }
    /**
     * Removes all automatically generated home redirects.
     */
    private function clearAutoHomeRedirects() {
        foreach ($this->redirects as $url => $r) {
            if (isset($r['auto']) && $r['auto'] === 'home') {
                unset($this->redirects[$url]);
            }
        }
    }
    /**
     * Processes all page files in the given directory and registers their routes.
     * @param string $pagesDir
     */
    private function processPages($pagesDir) {
        $files = glob($pagesDir . '*.php') ?: [];
        foreach ($files as $file) {
            $data = read_json_file($file);
            if (!$data) continue;
            
            $slug = basename($file, '.php');
            $this->registerPageRoute($slug, $data);
        }
    }
    /**
     * Registers a single page route and its locales.
     * @param string $slug
     * @param array $data
     */
    private function registerPageRoute($slug, $data) {
        $url = $this->normalizeUrl($data['url'] ?? '/' . $slug);
        $primaryLang = $this->getPrimaryLanguageCode();
        $primaryLocale = (isset($data['locales'][$primaryLang]) && is_array($data['locales'][$primaryLang]))
            ? $data['locales'][$primaryLang]
            : [];
        $primarySeo = $primaryLocale['seo'] ?? ($data['seo'] ?? []);
        if (!is_array($primarySeo)) {
            $primarySeo = [];
        }
        
        $routeData = [
            'type' => 'page',
            'lang' => $primaryLang,
            'slug' => $slug,
            'title' => (string)($primaryLocale['title'] ?? ($data['title'] ?? '')),
            'template' => $data['template'] ?? ($slug . '.php'),
            'directory_id' => $data['directory_id'] ?? null,
            'seo' => $primarySeo,
            'active' => $data['active'] ?? true,
            'updated' => date('Y-m-d H:i:s')
        ];

        if (!empty($data['is_home'])) {
            $routeData['is_home'] = true; // explicitly preserve is_home
            $this->map['/'] = $routeData;
            $slugKey = $this->slugIndexKey((string)$slug, 'page');
            $this->slugMap[$primaryLang][$slugKey] = '/'; 
            $this->addAutoHomeRedirect($slug);
        } else {
            $this->map[$url] = $routeData;
            $slugKey = $this->slugIndexKey((string)$slug, 'page');
            if (!isset($this->slugMap[$primaryLang][$slugKey])) {
                $this->slugMap[$primaryLang][$slugKey] = $url;
            }
        }

        $this->registerLocalizedRoutes($routeData, $data['locales'] ?? [], '/', $data);
    }
    /**
     * Adds an automatic redirect from /slug to home if needed.
     * @param string $slug
     */
    private function addAutoHomeRedirect($slug) {
        $slugUrl = $this->normalizeUrl('/' . $slug);
        if ($slugUrl !== '/') {
            $this->redirects[$slugUrl] = [
                'target' => (defined('CMS_BASE_PATH') ? CMS_BASE_PATH : '') . '/',
                'code' => 301,
                'auto' => 'home',
                'created' => date('Y-m-d H:i:s')
            ];
        }
    }
    /**
     * Processes all blog post files and registers their routes.
     * @param string $blogDir
     */
    private function processBlogPosts($blogDir) {
        if (!is_dir($blogDir)) return;
        
        $files = glob($blogDir . '*.php') ?: [];
        foreach ($files as $file) {
            $data = read_json_file($file);
            if (!$data) continue;
            
            $slug = basename($file, '.php');
            $this->registerBlogPostRoute($slug, $data);
        }
    }
    /**
     * Registers a single blog post route and its locales.
     * @param string $slug
     * @param array $data
     */
    private function registerBlogPostRoute($slug, $data) {
        $url = $this->normalizeUrl($data['url'] ?? '/blog/' . $slug);
        $primaryLang = $this->getPrimaryLanguageCode();
        $primaryLocale = (isset($data['locales'][$primaryLang]) && is_array($data['locales'][$primaryLang]))
            ? $data['locales'][$primaryLang]
            : [];
        $primarySeo = $primaryLocale['seo'] ?? ($data['seo'] ?? []);
        if (!is_array($primarySeo)) {
            $primarySeo = [];
        }
        
        $routeData = [
            'type' => 'blog',
            'lang' => $primaryLang,
            'slug' => $slug,
            'title' => (string)($primaryLocale['title'] ?? ($data['title'] ?? '')),
            'template' => $data['template'] ?? 'blog_post.php',
            'directory_id' => $data['directory_id'] ?? null,
            'seo' => $primarySeo,
            'active' => $data['active'] ?? true,
            'updated' => date('Y-m-d H:i:s')
        ];
        
        $this->map[$url] = $routeData;
        $slugKey = $this->slugIndexKey((string)$slug, 'blog');
        if (!isset($this->slugMap[$primaryLang][$slugKey])) {
            $this->slugMap[$primaryLang][$slugKey] = $url;
        }

        $this->registerLocalizedRoutes($routeData, $data['locales'] ?? [], '/blog/');
    }
    /**
     * Registers localized routes for a base route.
     * @param array $baseRoute
     * @param array $locales
     * @param string $prefix
     */
    private function registerLocalizedRoutes($baseRoute, $locales, $prefix = '/', $contentData = null) {
        if (empty($locales) || !is_array($locales)) return;

        $languageMeta = $this->getLanguageMeta();
        $primaryLang = $languageMeta['primary'];
        $enabledLangs = array_flip($languageMeta['enabled']);
        $normalizedPrefix = trim((string)$prefix, '/');

        foreach ($locales as $langCode => $trans) {
            if (!isset($enabledLangs[$langCode]) || $langCode === $primaryLang) {
                continue;
            }

            $translatedSlug = trim((string)($trans['slug'] ?? ''));
            if ($translatedSlug === '') {
                continue;
            }

            $routePath = '/' . $langCode;
            
            // If it's a homepage, use /en/ instead of /en/home
            $isHome = ($prefix === '/' && !empty($baseRoute['is_home']));

            if ($prefix === '/' && !$isHome && is_array($contentData)) {
                $routePath = $this->buildLocalizedPageRoutePath($translatedSlug, $contentData, $langCode, $primaryLang);
                if ($routePath === '') {
                    continue;
                }
            } elseif (!$isHome) {
                if ($normalizedPrefix !== '') {
                    $routePath .= '/' . $normalizedPrefix;
                }
                $routePath .= '/' . $translatedSlug;
            }

            $transUrl = $this->normalizeUrl($routePath);
            $existingRoute = $this->map[$transUrl] ?? null;
            if ($existingRoute !== null) {
                $existingSlug = $existingRoute['slug'] ?? '';
                $existingLang = $existingRoute['lang'] ?? $primaryLang;
                $currentSlug = $baseRoute['slug'] ?? '';
                if ($existingSlug !== $currentSlug || $existingLang !== $langCode) {
                    throw new \RuntimeException("Localized route collision for URL '{$transUrl}' (lang='{$langCode}', slug='{$translatedSlug}')");
                }
            }

            $baseType = (string)($baseRoute['type'] ?? 'page');
            $translatedSlugKey = $this->slugIndexKey((string)$translatedSlug, $baseType);
            if (isset($this->slugMap[$langCode][$translatedSlugKey]) && $this->slugMap[$langCode][$translatedSlugKey] !== $transUrl) {
                throw new \RuntimeException("Localized slug collision for lang '{$langCode}' and slug '{$translatedSlug}'");
            }

            $transRoute = $baseRoute;
            $transRoute['lang'] = $langCode;
            if (isset($trans['title']) && is_string($trans['title']) && trim($trans['title']) !== '') {
                $transRoute['title'] = $trans['title'];
            }
            if (isset($trans['seo']) && is_array($trans['seo'])) {
                $transRoute['seo'] = $trans['seo'];
            }
            $this->map[$transUrl] = $transRoute;
            $this->slugMap[$langCode][$translatedSlugKey] = $transUrl;
        }
    }

    /**
     * Builds localized path for nested pages using localized parent slugs when available.
     * @param string $translatedSlug
     * @param array $contentData
     * @param string $langCode
     * @param string $primaryLang
     * @return string
     */
    private function buildLocalizedPageRoutePath($translatedSlug, array $contentData, $langCode, $primaryLang) {
        $segments = [$translatedSlug];
        $parentId = trim((string)($contentData['parent_id'] ?? ''));
        $visited = [];

        while ($parentId !== '' && !in_array($parentId, $visited, true)) {
            $visited[] = $parentId;
            $parentFile = C_CONTENT_PATH . '/pages/' . $parentId . '.php';
            if (!file_exists($parentFile)) {
                break;
            }

            $parentData = read_json_file($parentFile);
            $parentSlug = $parentId;
            if ($langCode !== $primaryLang) {
                $localizedParentSlug = trim((string)($parentData['locales'][$langCode]['slug'] ?? ''));
                if ($localizedParentSlug !== '') {
                    $parentSlug = $localizedParentSlug;
                }
            }

            if ($parentSlug !== 'index' && $parentSlug !== 'notindex') {
                array_unshift($segments, $parentSlug);
            }

            $parentId = trim((string)($parentData['parent_id'] ?? ''));
        }

        return '/' . $langCode . '/' . implode('/', $segments);
    }

    /**
     * Returns language configuration metadata.
     * @return array{enabled: array<int,string>, primary: string}
     */
    private function getLanguageMeta() {
        if (self::$languageMeta !== null) {
            return self::$languageMeta;
        }

        $enabled = [];
        foreach (Settings::getLanguages() as $lang) {
            $code = Settings::normalizeLanguageCode((string)($lang['code'] ?? ''));
            if (!empty($lang['enabled']) && $code !== '' && Settings::isValidLanguageCode($code)) {
                $enabled[] = $code;
            }
        }

        if (empty($enabled)) {
            $enabled = [Settings::normalizeLanguageCode((string)(Settings::load()['language'] ?? 'en')) ?: 'en'];
        }

        self::$languageMeta = [
            'enabled' => $enabled,
            'primary' => $enabled[0],
        ];

        return self::$languageMeta;
    }

    /**
     * Returns primary language code from settings.
     * @return string
     */
    private function getPrimaryLanguageCode() {
        $meta = $this->getLanguageMeta();
        return $meta['primary'] ?? (Settings::load()['language'] ?? 'en');
    }
    /**
     * Normalizes a URL for consistent storage and lookup.
     * Removes .php, trims slashes, collapses multiple slashes, etc.
     * @param string $url
     * @return string
     */
    private function normalizeUrl($url) {
        if (empty($url) || $url === '/') {
            return '/';
        }
        
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        
        if (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }
        $path = preg_replace('#/+#', '/', trim($path, " \t\n\r\0\x0B/"));
        if ($path === '' || $path === '/') {
            return '/';
        }
        
        return '/' . $path;
    }
    /**
     * Checks if a route exists for the given URL.
     * @param string $url
     * @return bool
     */
    public function exists($url) {
        $this->load();
        $url = $this->normalizeUrl($url);
        return isset($this->map[$url]);
    }
    /**
     * Returns the URL for a given slug and language.
     * @param string $slug
     * @param string|null $lang
     * @return string|null
     */
    public function getUrlBySlug($slug, $lang = null, $type = 'page') {
        $this->load();
        $slugKey = $this->slugIndexKey((string)$slug, (string)$type);
        if ($lang && isset($this->slugMap[$lang][$slugKey])) {
            return $this->slugMap[$lang][$slugKey];
        }
        $primary = $this->getPrimaryLanguageCode();
        if (isset($this->slugMap[$primary][$slugKey])) {
            return $this->slugMap[$primary][$slugKey];
        }
        return null;
    }
    /**
     * Clears all loaded data and forces reload from storage on next access.
     */
    public function clearCache() {
        $this->loaded = false;
        $this->map = [];
        $this->slugMap = [];
        $this->redirects = [];
        // Скидаємо статичний кеш мета-інформації мов, щоб зміни налаштувань мови
        // відобразилися негайно без перезавантаження процесу.
        self::$languageMeta = null;
    }
}
