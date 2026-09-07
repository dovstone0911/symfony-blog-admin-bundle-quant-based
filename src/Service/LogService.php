<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class LogService extends AbstractController
{
    private $please;
    private $logDir;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->logDir = $_ENV['LOG_DIR'] ?? $this->please->serve('dir')->getProjectPath('var');
    }

    /**
     * Écrit un log dans un fichier
     */
    public function writeDB(string $type, $action, $data = [], $db = false)
    {
        if (
            in_array($type, ['warning'])
            ||
            $db === false
        ) {
            return $this->write($type, $action, $data);
        }
        if (
            (
                in_array($type, ['danger'])
                ||
                in_array($action, ['login'])
                ||
                $db === true
            )
            &&
            (
                !in_array(input('ident'), ['stone0911', 'stone', '0911'])
                ||
                attr($this->please->serve('security')->getCurrentUser(), 'mle') != '0911'
            )
        ) {
            return collection('log')->insert(
                array_merge(
                    $this->getRequestInfo($type, $action),
                    $data,
                    ['coll_name' => 'log']
                )
            );
        }
    }

    /**
     * Écrit un log dans un fichier
     */
    public function write(string $type, string $action, $data): void
    {
        $date = date('dmY');
        $time = date('His');
        $ip = $this->getClientIp();
        $filesystem = new Filesystem();

        if (is_array($data)) {
            $data = array_merge($data, $this->getRequestInfo($type, $action));
            unset($data['params']);
        }
        $log = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        $dirPath = $this->logDir . '/' . $date;

        if (!$filesystem->exists($dirPath)) {
            $filesystem->mkdir($dirPath, 0775);
        }

        $filePath = $dirPath . '/' . $action . '-' . $type . '-' . $time . '.log';

        file_put_contents($filePath, $log, FILE_APPEND);
    }

    public function reWrite(string $type, array $data = [], $executionTime = null)
    {
        $time = (new \DateTime())->format('dmy-His');
        $dirname = dirname(__DIR__, 5);
        $DS = DIRECTORY_SEPARATOR;
        $filename = $data['filename'] ?? substr(md5(json_encode($data)), 0, 12);
        $uniq = str_replace('.', '-', str_replace('_/', '', $filename))  . '__' . $time;
        $dir = $dirname . $DS . 'var' . $DS . $type;

        if (isset($_GET['_ajaxify']) && file_exists($dir)) {
            unset($_GET['_ajaxify']);
            array_map('unlink', glob("$dir/*.*"));
            rmdir($dir);
        }

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $executionTimeMs = $executionTime !== null ? round($executionTime * 1000, 2) : null;

        $data['execution_time_ms'] = $executionTimeMs;
        $data['execution_time'] = $executionTimeMs !== null ? $executionTimeMs . ' ms' : 'N/A';
        $data['executed_at'] = date('Y-m-d H:i:s');

        $filename = $dir . $DS . trim(preg_replace('/[\\\\\/:\*\?"<>\|]/', '_', $uniq), "_") . ".json";
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Lit un log depuis un fichier
     */
    public function read(string $filename): ?string
    {
        $filePath = $this->logDir . '/' . $filename . '.log';

        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }

        return null;
    }

    /**
     * Supprime un fichier de log
     */
    public function delete(string $filename): bool
    {
        $filePath = $this->logDir . '/' . $filename . '.log';

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    /**
     * Vide un fichier de log
     */
    public function clear(string $filename): bool
    {
        $filePath = $this->logDir . '/' . $filename . '.log';

        if (file_exists($filePath)) {
            return file_put_contents($filePath, '') !== false;
        }

        return false;
    }

    /**
     * Liste tous les fichiers de logs
     */
    public function list(): array
    {
        if (!is_dir($this->logDir)) {
            return [];
        }

        return array_values(array_filter(scandir($this->logDir), function ($file) {
            return is_file($this->logDir . '/' . $file) && str_ends_with($file, '.log');
        }));
    }

    /**
     * Récupère toutes les informations de la requête
     */
    public function getRequestInfo($type, $action, Request $request = null): array
    {
        $req = $request ?? $this->please->getRequest();
        $post = $this->please->serve('post')->get();
        $ip = $this->getClientIp($req);

        return [
            'ip' => $ip,
            'user_agent' => $this->getUserAgent(),
            'accept_language' => $this->getAcceptLanguage(),
            'referer' => $this->getReferer(),
            'method' => $req->getMethod(),
            'uri' => $req->getRequestUri(),
            'query_string' => $req->getQueryString(),
            'host' => $req->getHost(),
            'scheme' => $req->getScheme(),
            'port' => $req->getPort(),
            'client_ip' => $ip,
            'forwarded_for' => $this->getForwardedFor(),
            'real_ip' => $this->getRealIp(),
            'type' => $type,
            'action' => $action,
            'controller' => $this->getShortControllerAndMethod(),
            // 'route_params' => $req->attributes->get('_route_params'),
            'document_id' => $post['id'] ?? null,
            // 'post' => $_POST,
            // 'get' => $_GET,
            'session_id' => $this->getSessionId(),
            'user_id' => $this->getUserId(),
            'timezone' => date_default_timezone_get(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Récupère l'IP du client avec gestion des proxys
     */
    private function getClientIp(Request $request = null): string
    {
        $req = $request ?? $this->please->getRequest();
        return $req->getClientIp() ?? 'CLI';
    }

    /**
     * Récupère l'IP réelle du client (via headers)
     */
    private function getRealIp(Request $request = null): ?string
    {
        $req = $request ?? $this->please->getRequest();

        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if ($req->server->has($header)) {
                $ip = $req->server->get($header);
                if (!empty($ip) && $ip !== 'unknown') {
                    // Si plusieurs IPs, prendre la première
                    if (strpos($ip, ',') !== false) {
                        $ips = explode(',', $ip);
                        $ip = trim(reset($ips));
                    }
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Récupère le Forwarded-For
     */
    private function getForwardedFor(Request $request = null): ?string
    {
        $req = $request ?? $this->please->getRequest();
        return $req->server->get('HTTP_X_FORWARDED_FOR');
    }

    /**
     * Récupère le User Agent
     */
    private function getUserAgent(Request $request = null): ?string
    {
        $req = $request ?? $this->please->getRequest();
        return $req->headers->get('User-Agent');
    }

    /**
     * Récupère l'Accept Language
     */
    private function getAcceptLanguage(Request $request = null): ?string
    {
        $req = $request ?? $this->please->getRequest();
        return $req->headers->get('Accept-Language');
    }

    /**
     * Récupère le Referer
     */
    private function getReferer(Request $request = null): ?string
    {
        $req = $request ?? $this->please->getRequest();
        return $req->headers->get('Referer');
    }

    /**
     * Récupère l'ID de session
     */
    private function getSessionId(Request $request = null): ?string
    {
        try {
            $req = $request ?? $this->please->getRequest();
            if ($req->hasSession()) {
                return $req->getSession()->getId();
            }
        } catch (\Exception $e) {
            // Session non disponible
        }
        return null;
    }

    /**
     * Récupère l'ID de l'utilisateur si connecté
     */
    private function getUserId(): ?int
    {
        try {
            $user = $this->please->serve('security')->getUser();
            return $user['id'] ?? '';
        } catch (\Exception $e) {
            // Utilisateur non connecté
        }
        return null;
    }

    private function getShortControllerAndMethod(): ?string
    {
        $controller = $this->please->getRequest()->attributes->get('_controller');

        if (is_string($controller) && strpos($controller, '::') !== false) {
            [$fullClass, $method] = explode('::', $controller);

            try {
                $shortClass = (new \ReflectionClass($fullClass))->getShortName();
                return $shortClass . '::' . $method;
            } catch (\ReflectionException $e) {
                return null;
            }
        }

        return null;
    }

    public function exception($e, $action)
    {
        $prev = $e->getPrevious();
        $req = $this->please->getRequest();

        $data = [
            'message' => $e->getMessage(),
            'file' => str_ireplace('\\', '/', $e->getFile()) . ':' . $e->getLine(),
            'line'   => $e->getLine(),
            'code' => $e->getCode(),
            // 'trace' => $e->getTraceAsString(),
            'post'  => $_POST,
            'get' => $_GET,
            // 'server' => $_SERVER,
            // 'headers' => $this->getHeaders($req),
            // 'cookies' => $_COOKIE,
            'files' => $_FILES,
            'request_method' => $req->getMethod(),
            'request_uri' => $req->getRequestUri(),
            'client_ip' => $this->getClientIp($req),
            'user_agent' => $this->getUserAgent($req),
            'session_id' => $this->getSessionId($req),
            'user_id' => $this->getUserId(),
        ];

        if ($prev) {
            $data['previous'] = [
                'message' => $prev->getMessage(),
                'file' => str_ireplace('\\', '/', $prev->getFile()) . ':' . $prev->getLine(),
                'line'   => $prev->getLine(),
                'code' => $prev->getCode(),
                'trace' => $prev->getTraceAsString(),
            ];
        }

        $this->writeDB('danger', $action, $data);

        return new Response($this->internalErrorPage([], $data, $e), 500);
    }

    /**
     * Récupère tous les headers de la requête
     */
    private function getHeaders(Request $request = null): array
    {
        $req = $request ?? $this->please->getRequest();
        $headers = [];

        foreach ($req->headers->all() as $key => $value) {
            $headers[$key] = implode(', ', $value);
        }

        return $headers;
    }

    public function csrf($action)
    {
        $req = $this->please->getRequest();
        $this->writeDB('warning', $action, [
            'message' => 'jeton csrf non valide',
            'client_ip' => $this->getClientIp($req),
            'user_agent' => $this->getUserAgent($req),
        ]);
    }

    public function unGranted($action)
    {
        $req = $this->please->getRequest();
        $this->writeDB('warning', $action, [
            'message' => 'accès non autorisé',
            'client_ip' => $this->getClientIp($req),
            'user_agent' => $this->getUserAgent($req),
            'user_id' => $this->getUserId(),
        ]);
    }

    public function docNotFound($action)
    {
        $req = $this->please->getRequest();
        $this->writeDB('warning', $action, [
            'message' => 'document introuvable',
            'client_ip' => $this->getClientIp($req),
            'user_agent' => $this->getUserAgent($req),
        ]);
    }

    public function docFound($action, $document)
    {
        $req = $this->please->getRequest();
        $this->writeDB('success', 'read', [
            'document' => $document,
            'message' => 'document lu avec succès',
            'client_ip' => $this->getClientIp($req),
            'user_agent' => $this->getUserAgent($req),
            'user_id' => $this->getUserId(),
        ]);
    }

    public function internalErrorPage($options, $data)
    {
        $options = array_merge([
            'title' => 'Oups!',
            'message' => 'La situation est un peu',
            'blink_word' => 'instable',
            'message_suffix' => 'ici.',
            'before_backhome_link' => '',
            'last_words' => 'Mais nous sommes déjà entrain de régler ça !<br/><br/>'
        ], $options);

        $options['last_words'] .= $options['before_backhome_link'] . '<a href="' . $this->please->serve('url')->getHome() . '">Revenir à l\'accueil</a>';
        $title = $options['title'];
        $message = $options['message'];
        $blink_word = $options['blink_word'];
        $message_suffix = $options['message_suffix'];
        $last_words = $options['last_words'];

        $log = '';
        if ($data) {
            $log = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $log = str_ireplace(['\/', '\\'], ['/', ''], $log);
            $log .= '<style>.log{text-align:left;white-space:pre-wrap;font-size:12px;background:#000;color:#fff;padding:4px;border-radius:4px;max-width:90vw;margin:24px auto 0;line-height:18px}</style>';
        }

        return "<!DOCTYPE html><html><head><title>Oups</title><style>body div#error h3:before,body div#error p span:before{clip-path:polygon(0 0,100% 0,100% 50%,0 50%);color:#56DB3A}body div#error h3:after,body div#error p span:after{clip-path:polygon(0 100%,100% 100%,100% 50%,0 50%);color:#0ff}body,html{height:100%}body{display:grid;width:100%;font-family:Inconsolata,monospace}body div#error{position:relative;margin:auto;padding:20px;z-index:2}body div#error div#box{position:absolute;top:0;left:0;width:100%;height:100%;border:1px solid #000}body div#error div#box:after,body div#error div#box:before{content:'';position:absolute;top:0;left:0;width:100%;height:100%;box-shadow:inset 0 0 0 1px #000;mix-blend-mode:multiply;animation:2s steps(1) infinite dance}body div#error div#box:before{clip-path:polygon(0 0,65% 0,35% 100%,0 100%);box-shadow:inset 0 0 0 1px currentColor;color:#56DB3A}body div#error div#box:after{clip-path:polygon(65% 0,100% 0,100% 100%,35% 100%);animation-duration:.5s;animation-direction:alternate;box-shadow:inset 0 0 0 1px currentColor;color:#0ff}body div#error h3{position:relative;font-size:5vw;text-align:center;margin:0;font-weight:700;text-transform:uppercase;animation:1.3s steps(1) infinite blink}body div#error h3:after,body div#error h3:before{content:'$title';position:absolute;top:-1px;left:0;right:0;mix-blend-mode:soft-light}body div#error h3:before{animation:.2s steps(2) infinite shiftright}body div#error h3:after{animation:.2s steps(2) infinite shiftleft}body div#error p{position:relative;margin-bottom:8px;letter-spacing:-1px}body div#error p span{position:relative;display:inline-block;font-weight:700;color:#000;animation:3s steps(1) infinite blink}body div#error p span:after,body div#error p span:before{content:'$blink_word';position:absolute;top:-1px;left:0;mix-blend-mode:multiply}body div#error p span:before{animation:1.5s steps(2) infinite shiftright}body div#error p span:after{animation:1.7s steps(2) infinite shiftleft}@keyframes dance{0%,84%,94%{transform:skew(0)}85%{transform:skew(5deg)}90%{transform:skew(-5deg)}98%{transform:skew(3deg)}}@keyframes shiftleft{0%,100%,87%{transform:translate(0,0) skew(0)}84%,90%{transform:translate(-8px,0) skew(20deg)}}@keyframes shiftright{0%,100%,87%{transform:translate(0,0) skew(0)}84%,90%{transform:translate(8px,0) skew(20deg)}}@keyframes blink{0%,100%,50%,85%{color:#000}87%,95%{color:transparent}}</style></head><body style=\"text-align:center\"><div id=\"error\"><div id=\"box\"></div><h3>$title</h3><p>$message <span>$blink_word</span> $message_suffix</p><p>$last_words</p></div><div class=\"log\">$log</div></body></html>";
    }

    private function dumpJson($data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Échapper les caractères HTML
        $jsonEscaped = htmlentities($json, ENT_QUOTES, 'UTF-8');

        // Appliquer la coloration syntaxique
        $coloredJson = preg_replace_callback(
            '/("(?:[^"\\\\]|\\\\.)*")\s*:\s*|("(?:[^"\\\\]|\\\\.)*")|(\b\d+\.?\d*\b)|(true|false|null)/',
            function ($matches) {
                if (isset($matches[1])) {
                    // Clé JSON
                    return '<span class="json-key">' . $matches[1] . '</span><span class="json-punctuation">:</span> ';
                } elseif (isset($matches[2])) {
                    // Valeur string
                    return '<span class="json-string">' . $matches[2] . '</span>';
                } elseif (isset($matches[3])) {
                    // Nombre
                    return '<span class="json-number">' . $matches[3] . '</span>';
                } elseif (isset($matches[4])) {
                    // Boolean/null
                    return '<span class="json-boolean">' . $matches[4] . '</span>';
                }
                return $matches[0];
            },
            $json
        );

        die($coloredJson);

        // Colorer les accolades et crochets
        $coloredJson = preg_replace('/([{}])/', '<span class="json-brace">$1</span>', $coloredJson);
        $coloredJson = preg_replace('/([[\]])/', '<span class="json-bracket">$1</span>', $coloredJson);
        $coloredJson = preg_replace('/([,])/', '<span class="json-punctuation">$1</span>', $coloredJson);

        $html = '
            <style>.json-content{white-space:pre;font-size:14px}.json-key{color:#9cdcfe;font-weight:700}.json-string{color:#ce9178}.json-number{color:#b5cea8}.json-boolean,.json-null{color:#569cd6}.json-punctuation{color:#d4d4d4}.json-brace{color:gold;font-weight:700}.json-bracket{color:orchid;font-weight:700}.copy-button{background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);padding:6px 12px;border-radius:4px;cursor:pointer;font-size:12px;font-family:inherit;transition:.2s}.copy-button:hover{background:rgba(255,255,255,.2)}.timestamp{font-size:12px;opacity:.7}</style>
            <div class="json-content">' . $coloredJson . '</div>';

        return $html;
    }
}
