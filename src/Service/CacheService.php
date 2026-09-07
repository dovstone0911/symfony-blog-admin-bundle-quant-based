<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Twig\Markup;
use App\Command\WarmupCacheCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class CacheService extends AbstractController
{
    private $please;
    protected $warmupCacheCmd;

    public function __construct(
        PleaseService $please,
        WarmupCacheCommand $warmupCacheCmd
    ) {
        $this->please = $please;
        $this->warmupCacheCmd = $warmupCacheCmd;
    }

    public function do(callable $onSuccess, $cacheKey, $ttl = "1 month")
    {
        $requestUri = $this->please->getRequestUri();
        $requestData = $this->please->getRequestStackRequest()->all();
        $cacheKey = json_encode($cacheKey) . $requestUri . '-' . json_encode($requestData);
        $cacheKey = strtr($cacheKey, ['&_ajaxify=true' => '', '?_ajaxify=true' => '']);
        $cacheKey = md5($cacheKey);

        $memoryCache = $this->getMemoryCache();
        if ($memoryCache) {
            return $this->doMemoryCache($onSuccess, $cacheKey, $ttl, $memoryCache);
        }

        return $this->doFileCache($onSuccess, $cacheKey, $ttl);
    }

    public function session(callable $onSuccess, $cacheKey, $ttl = "1 day")
    {
        $timeServ = $this->please->serve('time');
        $session = $this->please->getContainer()->get('session');
        $ttlSeconds = $timeServ->humanTime($ttl);

        $requestUri = $this->please->getRequestUri();
        $requestData = $this->please->getRequestStackRequest()->all();
        $ck = json_encode($cacheKey) . $requestUri . '-' . json_encode($requestData);
        $ck = strtr($ck, ['&_ajaxify=true' => '', '?_ajaxify=true' => '']);
        $ck = md5($ck);

        // Structure de stockage en session
        $sessionCacheKey = "cache_$ck";
        $sessionTimestampKey = "cache_{$ck}_timestamp";

        // Vérifier si le cache existe et n'est pas expiré en session
        if ($session->has($sessionCacheKey) && $session->has($sessionTimestampKey)) {
            $cacheTimestamp = $session->get($sessionTimestampKey);
            $expiry = $cacheTimestamp + $ttlSeconds;

            if (time() < $expiry) {
                // Cache valide, on retourne les données
                return $session->get($sessionCacheKey);
            } else {
                // Cache expiré, on supprime de la session
                $session->remove($sessionCacheKey);
                $session->remove($sessionTimestampKey);
            }
        }

        // Cache inexistant ou expiré, on génère les données
        $data = $onSuccess();

        // Stocker en session avec timestamp
        $session->set($sessionCacheKey, $data);
        $session->set($sessionTimestampKey, time());

        return $data;
    }

    public function simpleSmartCache(
        callable $callback,
        $cacheKey = null,
        $cacheRoute = null,
        $forceFileCache = false,
        $ttl = "1 minute",
        $ttlStale = "10 minutes"
    ) {
        if ($this->please->serve('env')->getAppEnv('DO_CACHE') == 'false') {
            return $callback();
        }

        $timeServ = $this->please->serve('time');
        $cacheKey = $this->getCacheRequestKey($cacheKey);

        $ttl = $timeServ->humanTime($ttl);
        $ttlStale = $timeServ->humanTime($ttlStale);

        if ($forceFileCache === false && $memoryCache = $this->getMemoryCache()) {
            return $this->simpleSmartCacheMemory($memoryCache, $callback, $cacheKey, $cacheRoute, $ttl, $ttlStale);
        }

        return $this->simpleSmartCacheFile($callback, $cacheKey, $cacheRoute, $ttl, $ttlStale);
    }

    public function clearAllCaches()
    {
        $this->cleanupExpiredFiles();
        $data = [
            'redis' => $this->clearRedisCache(),
            'memcached' => $this->clearMemcachedCache(),
            'file' => $this->clearFileCache()
        ];

        return $data;
    }

    public function clearRedisCache()
    {
        try {
            $memoryCache = $this->getMemoryCache();

            if ($memoryCache instanceof RedisCacheAdapter) {
                $redis = $memoryCache->getRedisClient();
                $result = $redis->flushDb();

                return [
                    'success' => $result,
                    'message' => $result ? 'Redis cache cleared successfully' : 'Failed to clear Redis cache'
                ];
            }

            return [
                'success' => false,
                'message' => 'Redis not available or not configured'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Redis clearance error: ' . $e->getMessage()
            ];
        }
    }

    public function clearMemcachedCache()
    {
        try {
            $memoryCache = $this->getMemoryCache();

            if ($memoryCache instanceof MemcachedCacheAdapter) {
                $memcached = $memoryCache->getMemcachedClient();
                $result = $memcached->flush();

                return [
                    'success' => $result,
                    'message' => $result ? 'Memcached cache cleared successfully' : 'Failed to clear Memcached cache'
                ];
            }

            return [
                'success' => false,
                'message' => 'Memcached not available or not configured'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Memcached clearance error: ' . $e->getMessage()
            ];
        }
    }

    public function clearFileCache()
    {
        try {
            $cacheDir = $this->getCacheDir();
            $deletedFiles = 0;
            $dirFound = false;

            if (is_dir($cacheDir)) {
                $dirFound = true;
                $files = glob($cacheDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && unlink($file)) {
                        $deletedFiles++;
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'File cache cleared successfully',
                'files_deleted' => $deletedFiles,
                'dirFound' => $dirFound
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'File cache clearance error: ' . $e->getMessage()
            ];
        }
    }

    public function getNavigationData()
    {
        try {
            if ($this->please->serve('env')->getAppEnv('DO_CACHE') != "false") {
                $urlService = $this->please->serve('url');
                $currUrl = trim($urlService->getCurrentUrl(), '/');
                $url = md5($currUrl);

                // Chemin du répertoire de cache
                $cacheDir = $this->please->serve('dir')->getProjectPath('public/assets/navigation');
                $jsonFile = $cacheDir . DIRECTORY_SEPARATOR . $url . '.json';
                $data = json_decode(file_get_contents($jsonFile), true);
                $parsedView = $this->please->serve('template')->getParsedView($data['body'], 'main');

                return [
                    "hasMain" => true,
                    "showMain" => new Markup($parsedView['body'], 'UTF-8'),
                    "data" => $data,
                ];
            } else {
                return ["hasMain" => false];
            }
        } catch (\Exception $e) {
            return ["hasMain" => false];
        }
    }

    public function getCacheStats()
    {
        return [
            'redis' => $this->getRedisStats(),
            'memcached' => $this->getMemcachedStats(),
            'file' => $this->getFileCacheStats()
        ];
    }

    public function deleteUrlCache($url, ?callable $onDeleted = null)
    {
        $md5Url = md5($this->please->serve('url')->getUrl($url));
        $cacheFile = $this->please->serve('dir')->getProjectPath('public/assets/navigation') . DIRECTORY_SEPARATOR . $md5Url . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        return $onDeleted($url);
    }

    public function setUrlCache(string $url): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 60, // 60 secondes max par URL
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        return $response !== false;
    }

    public function resetUrlCache(string $url): Response
    {
        $start_time = microtime(true);

        $this->deleteUrlCache($url, function ($url) {
            return $this->setUrlCache($url);
        });

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        return new Response("Cache for '$url' was reset successfully. Execution time: {$execution_time} seconds");
    }

    public function exec($type): Response
    {
        $start_time = microtime(true);

        switch ($type) {
            case 'cache':
                $urls = $this->warmupCacheCmd->getPreparedUrls();
                $warmup = $this->please->serve('warmup');
                foreach ($urls as $url) {
                    $warmup->requestUrl($url);
                }
                $message = '✅ Cache warmed up <strong style="color:green">successfully</strong>!';
                break;
            case 'assets':
                $assetsCount = count($this->please->serve('hook')->hookBaseAssets() ?? []);
                $dirServ = $this->please->serve('dir');
                $tplServ = $this->please->serve('template');
                $tplServ->beginHTML([], false);
                $tplServ->endHTML(false, false);
                $varDirPath = $this->please->serve('dir')->dirPath('var');
                if (is_dir($varDirPath)) {
                    $newPath = dirname($varDirPath) . '/var--' . uniqid();
                    rename($varDirPath, $newPath);
                }
                foreach (glob($dirServ->dirPath('var--*'), GLOB_ONLYDIR) as $dossier) {
                    $dirServ->delTree($dossier);
                }
                $message = '✅ <strong style="color:green">' . $assetsCount . ' assets</strong> built <strong style="color:green">successfully</strong>!';
                break;
            case 'clear':
                $details = $this->clearAllCaches();
                $message = '✅ Caches cleared <strong style="color:green">successfully</strong> Details: ' . json_encode($details);
                break;
            case 'renamedirs':
                $dirs = ['var', 'public/assets/css-js'];
                foreach ($dirs as $dirname) {
                    $dir = $this->please->serve('dir')->dirPath($dirname);
                    if (is_dir($dir)) {
                        $newPath = $this->please->serve('dir')->dirPath("$dirname--" . (new \DateTime())->format('dmY-His'));
                        rename($dir, $newPath);
                    }
                }
                $message = '✅ <strong>' . implode(' - ', $dirs) . '</strong> directories <strong style="color:green">renamed successfully</strong>';
                break;
            default:
                $message = '❌ <strong style="color:red">Invalid</strong> build <strong style="color:red">type</strong> specified.';
                break;
        }

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        return new Response("$message Execution time: {$execution_time} seconds");
    }

    public function cronUrl(array $urls, int $delaySecondes = 15): void
    {
        if (empty($urls)) {
            return;
        }

        // Créer un script PHP temporaire
        $phpScript = '<?php' . PHP_EOL;
        $phpScript .= sprintf('sleep(%d);' . PHP_EOL, $delaySecondes);

        foreach ($urls as $url) {
            $safeUrl = addslashes($url);
            $phpScript .= sprintf(
                '@file_get_contents("%s");' . PHP_EOL,
                $safeUrl
            );
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'delay_');
        file_put_contents($tmpFile, $phpScript);

        // Exécuter en arrière-plan
        $cmd = sprintf(
            'php %s > /dev/null 2>&1 &',
            escapeshellarg($tmpFile)
        );

        pclose(popen($cmd, 'r'));
    }

    public function getCacheKey(mixed $data, string $ttl = "1 day", string $maxJitter = "0 second")
    {
        $ttlSeconds = $this->please->serve('time')->getTTL($ttl, $maxJitter);

        $now = new \DateTime();
        $currentTime = $now->getTimestamp();
        $timeSlot = floor($currentTime / $ttlSeconds);

        $dataWithTime = [
            'data' => $data,
            'timeSlot' => $timeSlot,
            'ttl' => $ttl,
            'maxJitter' => $maxJitter
        ];

        $cacheKey = md5(json_encode($dataWithTime, JSON_INVALID_UTF8_IGNORE));
        $this->please->setStorage([['last-cache-key', $cacheKey]], 'last-cache-key');
        return $cacheKey;
    }

    public function getCacheKey__(mixed $data, string $ttl = "1 day", string $maxJitter = "0 second")
    {
        $cacheKey = md5(json_encode($data, JSON_INVALID_UTF8_IGNORE));
        $this->please->setStorage([['last-cache-key', $cacheKey]], 'last-cache-key');
        return $cacheKey;
    }

    public function getCached($path)
    {
        $dirServ = $this->please->serve('dir');
        $file = $dirServ->dirPath("theme/$path.html.twig");
        $key = $this->please->serve('string')->getSlug($path);

        if (file_exists($file)) {
            $this->please->setStorage([
                [
                    $key,
                    function () use ($path) {
                        return $this->renderView(str_ireplace('theme', '', $path) . '.html.twig');
                    }
                ]
            ]);
            return new Markup($this->please->getStorage($key), 'UTF-8');
        }
        return null;
    }

    public function getCacheRequestKey($cacheKey = null)
    {
        $flash = $this->please->serve('flash');
        $k = (json_encode($cacheKey) ?? '') . ($this->please->getRequestUri() . '-' . json_encode($this->please->getRequestStackRequest()->all()));
        $k = str_ireplace('&_ajaxify=true', '', $k);
        $k = str_ireplace('?_ajaxify=true', '', $k);

        $login = $flash->getFlash('login');
        $logout = $flash->getFlash('logout');
        $user_id = attr($this->please->serve('security')->getCurrentUser(), 'id');
        $lastest_modif_date = $this->please->serve('time')->getLatestModificationDate();

        $final_cache_key = md5($k . $login . $logout . $user_id . $lastest_modif_date);

        return $final_cache_key;
    }

    public function pushNavigationData(object $responseData, $ttl = "10 minutes"): void
    {
        if ($this->please->serve('env')->getAppEnv('DO_CACHE') != "false") {

            $urlService = $this->please->serve('url');
            $stringService = $this->please->serve('string');
            $url = $urlService->getCurrentUrl();
            $url = trim($url, '/');
            $currUrl = str_replace('&_ajaxify=true', '', $url);
            $url = md5($currUrl);
            $userId = md5($this->please->serve('security')->getUserId() ?? "undefined");

            // Chemin du répertoire de cache
            $cacheDir = $this->please->serve('dir')->getProjectPath('public/assets/navigation');
            $DS = DIRECTORY_SEPARATOR;

            // Créer le répertoire s'il n'existe pas
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }

            // Calculer la date d'expiration
            $expiresAt = date('c', strtotime("+{$ttl}"));
            $body = $responseData->parsedView['body'];

            // HEADER
            $filename = $cacheDir . $DS . $userId . '-' . $url;
            $headerNode = $stringService->extractNode($body, '#header');
            $data = json_encode([
                'expires_at' => $expiresAt,
                'ttl' => $ttl,
                'url' => $currUrl,
                'body' => $headerNode
            ], JSON_PRETTY_PRINT);
            file_put_contents($filename . '.json', $data);

            // MAIN
            $filename = $cacheDir . $DS . $url;
            $bodyNode = $stringService->extractNode($body, '.app-page');
            $data = json_encode([
                'title' => strip_tags($responseData->parsedView['title']),
                'expires_at' => $expiresAt,
                'ttl' => $ttl,
                'url' => $currUrl,
                'body' => $bodyNode
            ], JSON_PRETTY_PRINT);
            file_put_contents($filename . '.json', $data);
        }
    }

    public function cleanupExpiredFiles($batchSize = 50, $maxExecutionTime = 5)
    {
        $startTime = time();
        $cacheDir = $this->please->serve('dir')->getProjectPath('public/assets/navigation');

        if (!is_dir($cacheDir)) {
            return 0;
        }

        $deletedCount = 0;
        $filesPaths = [];
        $processed = 0;

        $iterator = new \DirectoryIterator($cacheDir);

        foreach ($iterator as $file) {
            // Vérifier les limites
            if (time() - $startTime >= $maxExecutionTime) {
                break;
            }
            if ($processed >= $batchSize) {
                break;
            }

            if (!$file->isFile() || $file->getExtension() !== 'json') {
                continue;
            }

            $filePath = $file->getPathname();
            $processed++;

            try {
                if (!file_exists($filePath)) {
                    continue;
                }

                $content = json_decode(file_get_contents($filePath), true);
                $url = $content['url'] ?? 'unknown';

                if ($content && isset($content['expires_at'])) {
                    $expiration = new \DateTime($content['expires_at']);
                    $now = new \DateTime();

                    if ($now > $expiration) {
                        unlink($filePath);
                        $deletedCount++;
                        $filesPaths[] = ['message' => 'Fichier expiré', 'url' => $url, 'time' => date('c')];
                        continue;
                    }
                }

                // Fallback pour fichiers sans métadonnées (24h)
                $fileTime = filemtime($filePath);
                if (time() - $fileTime > 24 * 60 * 60) {
                    unlink($filePath);
                    $deletedCount++;
                    $filesPaths[] = ['message' => 'Vieux fichier sans métadonnées', 'url' => $url, 'time' => date('c')];
                }
            } catch (\Exception $e) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                    $deletedCount++;
                    $filesPaths[] = ['message' => 'Fichier illisible', 'url' => $url ?? 'unknown', 'time' => date('c')];
                }
            }
        }

        return $deletedCount;
    }

    public function getCurrentUpdatedAt(array|string|null $item): ?string
    {
        if (empty($item) || !is_array($item) || empty($item['id'])) return null;

        $collection = $item['coll_name'] ?? null;
        if (empty($collection)) return null;

        $collection = preg_replace('/[^a-zA-Z0-9_]/', '_', $collection);
        $sql = "SELECT MAX(updated_at) FROM $collection WHERE `uid` = ?";

        $result = RawQuery($sql, [$item['id']], [uniqid()]);
        return ($result[0]['MAX(updated_at)'] ?? null) . $item['id'];
    }

    private function simpleSmartCacheMemory($cache, callable $callback, $cacheKey, $cacheRoute, $ttl, $ttlStale)
    {
        $cacheKeyFresh = "fresh:$cacheKey";
        $cacheKeyStale = "stale:$cacheKey";
        $lockKey = "lock:$cacheKey";

        $freshData = $cache->get($cacheKeyFresh);
        if ($freshData !== false) {
            return unserialize($freshData);
        }

        $staleData = $cache->get($cacheKeyStale);
        if ($staleData !== false) {
            if ($cacheRoute && !$cache->get($lockKey)) {
                $cacheUrl = $this->_generateUrl($cacheRoute[0], $cacheRoute[1] ?? []);
                $this->please->serve('warmup')->rebuildCacheAsync($cacheUrl);
            }

            return unserialize($staleData);
        }

        $lockAcquired = false;

        try {
            $lockAcquired = $cache->add($lockKey, '1', 30);

            if ($lockAcquired) {
                $data = $callback();

                $cache->set($cacheKeyFresh, serialize($data), $ttl);
                $cache->set($cacheKeyStale, serialize($data), $ttlStale);
                return $data;
            } else {
                usleep(100000);
                $retryCount = 0;

                while ($retryCount < 10 && !$lockAcquired) {
                    $staleData = $cache->get($cacheKeyStale);
                    if ($staleData !== false) {
                        return unserialize($staleData);
                    }

                    usleep(50000);
                    $retryCount++;
                    $lockAcquired = $cache->add($lockKey, '1', 30);
                }

                if ($lockAcquired) {
                    $data = $callback();
                    $cache->set($cacheKeyFresh, serialize($data), $ttl);
                    $cache->set($cacheKeyStale, serialize($data), $ttlStale);
                    return $data;
                } else {
                    return $callback();
                }
            }
        } finally {
            if ($lockAcquired) {
                $cache->delete($lockKey);
            }
        }
    }

    private function simpleSmartCacheFile(callable $callback, $cacheKey, $cacheRoute, $ttl, $ttlStale)
    {
        $cacheDir = $this->getCacheDir();
        $cacheFile = "$cacheDir/$cacheKey.cache";
        $staleFile = "$cacheDir/$cacheKey.stale";
        $lockFile = "$cacheDir/$cacheKey.lock";

        $now = time();

        if (file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            if (($now - $cacheTime) < $ttl) {
                return unserialize(file_get_contents($cacheFile));
            }
        }

        if (file_exists($staleFile)) {
            $staleTime = filemtime($staleFile);
            if (($now - $staleTime) < $ttlStale) {
                if ($cacheRoute && !file_exists($lockFile)) {
                    $cacheUrl = $this->_generateUrl($cacheRoute[0], $cacheRoute[1] ?? []);
                    $this->please->serve('warmup')->rebuildCacheAsync($cacheUrl);
                }

                return unserialize(file_get_contents($staleFile));
            }
        }

        $lockAcquired = false;
        $lockHandle = null;

        try {
            $lockHandle = fopen($lockFile, 'w+');
            if ($lockHandle && flock($lockHandle, LOCK_EX | LOCK_NB)) {
                $lockAcquired = true;

                $data = $callback();

                $tempCacheFile = $cacheFile . '.tmp.' . uniqid();
                $tempStaleFile = $staleFile . '.tmp.' . uniqid();

                if (
                    file_put_contents($tempCacheFile, serialize($data)) &&
                    file_put_contents($tempStaleFile, serialize($data))
                ) {
                    rename($tempCacheFile, $cacheFile);
                    rename($tempStaleFile, $staleFile);
                }

                if (file_exists($tempCacheFile)) {
                    unlink($tempCacheFile);
                }
                if (file_exists($tempStaleFile)) {
                    unlink($tempStaleFile);
                }
            } else {
                if (file_exists($staleFile)) {
                    return unserialize(file_get_contents($staleFile));
                }

                if ($lockHandle) {
                    flock($lockHandle, LOCK_EX);
                    $lockAcquired = true;

                    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
                        return unserialize(file_get_contents($cacheFile));
                    }

                    $data = $callback();
                    file_put_contents($cacheFile, serialize($data));
                    file_put_contents($staleFile, serialize($data));
                }
            }

            return $data;
        } finally {
            if ($lockHandle) {
                if ($lockAcquired) {
                    flock($lockHandle, LOCK_UN);
                }
                fclose($lockHandle);

                if (file_exists($lockFile)) {
                    unlink($lockFile);
                }
            }
        }
    }

    private function doMemoryCache(callable $onSuccess, $cacheKey, $ttl = "1 day", $cache)
    {
        $data = $cache->get($cacheKey);
        if ($data !== false) {
            return unserialize($data);
        }

        $data = $onSuccess($cacheKey);
        $ttl = $this->please->serve('time')->humanTime($ttl);
        $cache->set($cacheKey, serialize($data), $ttl);
        return $data;
    }

    private function doFileCache(callable $onSuccess, $cacheKey, $ttl = "1 day")
    {
        $timeServ = $this->please->serve('time');
        $cacheDir = $this->getCacheDir();
        $cachefile = "$cacheDir/$cacheKey.cache";
        $ttlSeconds = $timeServ->humanTime($ttl);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Vérifier si le cache existe et n'est pas expiré
        if (file_exists($cachefile)) {
            $filemtime = filemtime($cachefile);
            $expiry = $filemtime + $ttlSeconds;

            if (time() < $expiry) {
                // Cache valide, on retourne les données
                return unserialize(file_get_contents($cachefile));
            } else {
                // Cache expiré, on supprime le fichier
                unlink($cachefile);
            }
        }

        // Cache inexistant ou expiré, on génère les données
        $data = $onSuccess($cacheKey);
        file_put_contents($cachefile, serialize($data));

        return $data;
    }

    private function getMemoryCache()
    {
        static $cacheClient = null;

        if ($cacheClient === null) {
            $cacheClient = false;

            if (class_exists('Redis') && $this->please->serve('env')->getAppEnv('REDIS_HOST')) {
                try {
                    $redis = new \Redis();
                    $host = $this->please->serve('env')->getAppEnv('REDIS_HOST', '127.0.0.1');
                    $port = $this->please->serve('env')->getAppEnv('REDIS_PORT', 6379);
                    $password = $this->please->serve('env')->getAppEnv('REDIS_PASSWORD');
                    $timeout = $this->please->serve('env')->getAppEnv('REDIS_TIMEOUT', 2.0);

                    if ($redis->connect($host, $port, $timeout)) {
                        if ($password && !$redis->auth($password)) {
                            $redis->close();
                        } else {
                            $prefix = $this->please->serve('env')->getAppEnv('REDIS_PREFIX', 'app_');
                            $redis->setOption(\Redis::OPT_PREFIX, $prefix);
                            $cacheClient = new RedisCacheAdapter($redis);
                        }
                    }
                } catch (\Exception $e) {
                    // error_log("Redis connection failed: " . $e->getMessage());
                }
            }

            if (!$cacheClient && class_exists('Memcached') && $this->please->serve('env')->getAppEnv('MEMCACHED_HOST')) {
                try {
                    $memcached = new \Memcached();
                    $memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 1000);
                    $memcached->setOption(\Memcached::OPT_RETRY_TIMEOUT, 1);

                    $memcached->addServer(
                        $this->please->serve('env')->getAppEnv('MEMCACHED_HOST', '127.0.0.1'),
                        $this->please->serve('env')->getAppEnv('MEMCACHED_PORT', 11211)
                    );

                    $memcached->get('test_connection');
                    if (
                        $memcached->getResultCode() !== \Memcached::RES_SUCCESS &&
                        $memcached->getResultCode() !== \Memcached::RES_NOTFOUND
                    ) {
                        // Connection failed
                    } else {
                        $cacheClient = new MemcachedCacheAdapter($memcached);
                    }
                } catch (\Exception $e) {
                    // error_log("Memcached connection failed: " . $e->getMessage());
                }
            }
        }

        return $cacheClient ?: null;
    }

    private function _generateUrl($route, $parameters = [])
    {
        return $this->please->serve('router')->generate($route, $parameters);
    }

    private function getCacheDir()
    {
        $dirPath = $this->please->serve('dir')->dirPath('var/cache/swagg');
        return $dirPath;
    }

    private function getRedisStats()
    {
        try {
            $memoryCache = $this->getMemoryCache();

            if ($memoryCache instanceof RedisCacheAdapter) {
                $redis = $memoryCache->getRedisClient();
                $info = $redis->info();
                $dbSize = $redis->dbSize();

                return [
                    'available' => true,
                    'keys_count' => $dbSize,
                    'memory_usage' => $info['used_memory'] ?? 'N/A',
                    'uptime' => $info['uptime_in_seconds'] ?? 'N/A'
                ];
            }

            return ['available' => false];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    private function getMemcachedStats()
    {
        try {
            $memoryCache = $this->getMemoryCache();

            if ($memoryCache instanceof MemcachedCacheAdapter) {
                $memcached = $memoryCache->getMemcachedClient();
                $stats = $memcached->getStats();

                return [
                    'available' => !empty($stats),
                    'servers' => $stats
                ];
            }

            return ['available' => false];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    private function getFileCacheStats()
    {
        try {
            $cacheDir = $this->getCacheDir();
            $fileCount = 0;
            $totalSize = 0;

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $fileCount++;
                        $totalSize += filesize($file);
                    }
                }
            }

            return [
                'available' => true,
                'files_count' => $fileCount,
                'total_size' => $totalSize,
                'total_size_human' => $this->formatBytes($totalSize)
            ];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

interface CacheAdapterInterface
{
    public function get($key);
    public function set($key, $value, $ttl = 0);
    public function add($key, $value, $ttl = 0);
    public function delete($key);
}

class RedisCacheAdapter implements CacheAdapterInterface
{
    private $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function get($key)
    {
        return $this->redis->get($key);
    }

    public function set($key, $value, $ttl = 0)
    {
        return $ttl ? $this->redis->setex($key, $ttl, $value) : $this->redis->set($key, $value);
    }

    public function add($key, $value, $ttl = 0)
    {
        if ($this->redis->setnx($key, $value)) {
            if ($ttl) {
                $this->redis->expire($key, $ttl);
            }
            return true;
        }
        return false;
    }

    public function delete($key)
    {
        return $this->redis->del($key) > 0;
    }

    public function getRedisClient()
    {
        return $this->redis;
    }
}

class MemcachedCacheAdapter implements CacheAdapterInterface
{
    private $memcached;

    public function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    public function get($key)
    {
        $result = $this->memcached->get($key);
        return $this->memcached->getResultCode() === \Memcached::RES_SUCCESS ? $result : false;
    }

    public function set($key, $value, $ttl = 0)
    {
        return $this->memcached->set($key, $value, $ttl);
    }

    public function add($key, $value, $ttl = 0)
    {
        return $this->memcached->add($key, $value, $ttl);
    }

    public function delete($key)
    {
        return $this->memcached->delete($key);
    }

    public function getMemcachedClient()
    {
        return $this->memcached;
    }
}
