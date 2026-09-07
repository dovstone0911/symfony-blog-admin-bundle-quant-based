<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class WarmupService extends AbstractController
{
    private $please;
    private $log;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->log = $please->serve('log');
    }

    public function requestUrl(string $url): string
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

        return $response !== false ? "✅ Cache warmed: $url" : "❌ Timeout: $url";
    }

    public function url___(string $url): void
    {
        try {
            file_get_contents($url);
        } catch (\Exception $e) {
            file_put_contents('var/' . date('dmY') . 'warmupUrl-' . date('His') . '.log', json_encode([
                'message' => "failed to warmup cache",
                'url'     => $url,
                'error'   => $e->getMessage()
            ]));
        }
    }

    public function _url(string $route, array $params = [], bool $customUrl = false): void
    {
        $url = !$customUrl ? $this->please->_generateUrl($route, $params) : $route;

        $env = $this->please->serve('env')->getAppEnv('APP_ENV');
        $isLocal = in_array($env, ['dev', 'local', 'test']);

        $parts = parse_url($url);
        if (!$parts || !isset($parts['host'])) {
            if ($isLocal) {
                $url = 'http://127.0.0.1:8000' . $url;
            } else {
                return;
            }
        }

        // Commande cURL en background
        $cmd = "curl -s --max-time 5 --retry 0 '" . escapeshellarg($url) . "' > /dev/null 2>&1 &";

        // Exécuter en arrière-plan
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        $this->log->writeDB('success', 'warmupCache', [
            'message' => "background cache warmup initiated",
            'url'     => $url,
            'env'     => $env,
            'command' => $cmd,
            'return_code' => $returnCode
        ], true);
    }
}
