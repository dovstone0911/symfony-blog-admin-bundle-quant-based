<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Repository\BundleBloggyRepository;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Repository\BundleUserRepository;
use Dovstone\Quant\Quant;
use JasonGrimes\Paginator;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Markup;

class PleaseService extends AbstractController
{
    public $prevContainer;
    public $moSQLConfig;

    private $appUiD;
    private $pdo;
    private $services = [];

    // ⚡ Caches pour optimisations (uniquement les services, pas les données)
    private $serviceCache = [];
    private $mailerConfig = null;
    private $currentUserCache = null;
    private $requestCache = null;
    private $requestStackCache = null;
    private $moSQLConfigCache = null;
    private $popService = null;

    public function __construct(ContainerInterface $container)
    {
        $this->prevContainer = $container;
        $this->appUiD = sha1($_SERVER['APP_NAME']);
    }

    /**
     * ⚡ Optimisé avec cache via serve('cache')->do()
     */
    public function setMoSQLConfig()
    {
        if ($this->moSQLConfigCache !== null) {
            return $this->moSQLConfigCache;
        }

        return $this->serve('cache')->do(function () {
            $configPath = $this->serve('dir')->dirPath('var/cache/db-config.json');
            if (file_exists($configPath)) {
                $this->moSQLConfigCache = json_decode(file_get_contents($configPath), true);
                return $this->moSQLConfigCache;
            }

            $dsn = $this->serve('env')->getAppEnv('DATABASE');

            preg_match('/^([a-z]+):host=([^;:]+)(?::(\d+))?;dbname=([^@]+)@([^:]+):?([^;]*)/', $dsn, $matches);

            $config = [
                'driver' => 'pdo_' . ($matches[1] ?? 'mysql'),
                'host' => $matches[2] ?? 'localhost',
                'port' => $matches[3] ?? '3306',
                'dbname' => $matches[4] ?? '',
                'user' => $matches[5] ?? '',
                'password' => $matches[6] ?? ''
            ];

            file_put_contents($configPath, json_encode($config));
            $this->moSQLConfigCache = $config;
            return $config;
        }, ['setMoSQLConfig'], '1 year');
    }

    public function getContainer()
    {
        return $this->prevContainer;
    }

    /**
     * ⚡ Optimisé avec cache service (pas de cache de données)
     */
    public function getService($serviceName)
    {
        if (isset($this->serviceCache[$serviceName])) {
            return $this->serviceCache[$serviceName];
        }

        $service = $this->prevContainer->get($serviceName);
        $this->serviceCache[$serviceName] = $service;
        return $service;
    }

    public function getRepo($repositoryName)
    {
        if (in_array($repositoryName, ['BloggyRepository', 'BloggyRepo', 'Bloggy', 'bloggy', 'B', 'b'])) {
            return new BundleBloggyRepository($this);
        }
        if (in_array($repositoryName, ['UserRepository', 'UserRepo', 'User', 'user', 'U', 'u'])) {
            return new BundleUserRepository($this);
        }
        return null;
    }

    public function getExecTime($context)
    {
        if (!isset($context->startTime)) {
            return 'Provide a $startTime';
        }
        $delay_sec = microtime(true) - $context->startTime;
        $delay_ms = $delay_sec * 1000;

        return sprintf(
            "Execution time: %.4f seconds (%.2f ms)",
            $delay_sec,
            $delay_ms
        );
        // Exemple: "Execution time: 0.1234 seconds (123.45 ms)"
    }

    /**
     * ⚡ Optimisé avec cache service (pas de cache de données)
     */
    public function serve($serviceName)
    {
        if (isset($this->serviceCache[$serviceName])) {
            return $this->serviceCache[$serviceName];
        }

        // Éviter les boucles de dépendances
        if (in_array($serviceName, ['pop', 'please', 'PleaseService'])) {
            $this->serviceCache[$serviceName] = $this;
            return $this;
        }

        $service = $this->prevContainer->get($serviceName);
        $this->serviceCache[$serviceName] = $service;
        return $service;
    }

    public function setGlobal($bigData)
    {
        if ($bigData) {
            $session = $this->getService('session');
            foreach ($bigData as $globalName => $data) {
                if (is_callable($data)) {
                    $data = $data();
                }
                $session->set('__global__' . $this->appUiD . '__' . $globalName, serialize($data));
            }
        }
    }

    public function setFlash(array $bigData, $message = null)
    {
        if (is_string($bigData)) {
            $this->setGlobal([$bigData => $message]);
        } else {
            $this->setGlobal($bigData);
        }
    }

    public function getGlobal($globalName = null, $default = null)
    {
        if (is_null($globalName)) {
            return $this->getService('session');
        }
        $globalValue = $this->getService('session')->get('__global__' . $this->appUiD . '__' . $globalName);
        if (!is_null($globalValue)) {
            return unserialize($globalValue);
        }
        return $default;
    }

    public function unsetGlobal($globalName): void
    {
        $session = $this->getService('session');
        if (is_array($globalName)) {
            foreach ($globalName as $gbName) {
                $session->set('__global__' . $this->appUiD . '__' . $gbName, null);
            }
        } else {
            $session->set('__global__' . $this->appUiD . '__' . $globalName, null);
        }
    }

    public function setStorage(array $bigData, $filename = null)
    {
        $stringServ = $this->serve('string');
        $dirServ = $this->serve('dir');
        $fs = new Filesystem();

        if ($bigData) {
            foreach ($bigData as $data) {
                if (count($data) === 2) {
                    $filename = $stringServ->getSlug($data[0]);
                    $content = $data[1];
                    $file = $dirServ->dirPath("var/cache/swagg/$filename.txt");

                    if (!$fs->exists($file)) {
                        $fs->appendToFile($file, '');
                    }

                    if (empty(file_get_contents($file))) {
                        $content = is_callable($content) ? $content() : $content;
                        $fs->appendToFile($file, serialize($content));
                    }
                }
            }
        }

        return $this->getStorage($filename);
    }

    public function getStorage($filename)
    {
        $dirServ = $this->serve('dir');
        $file = $dirServ->dirPath("var/cache/swagg/$filename.txt");
        if (file_exists($file)) {
            return unserialize(file_get_contents($file));
        }
        return null;
    }

    public function unsetStorage($filesNames)
    {
        $dirServ = $this->serve('dir');

        if (is_string($filesNames)) {
            $filesNames = [$filesNames];
        }
        if ($filesNames) {
            foreach ($filesNames as $fileName) {
                $file = $dirServ->dirPath("var/cache/swagg/$fileName.txt");
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        return null;
    }

    public function mergeData(...$data)
    {
        return $this->serve('cache')->do(function () use ($data) {
            $merged = [];
            $data = json_decode(json_encode($data), true);
            $mixServ = $this->serve('mix');
            foreach ($data as $d) {
                $merged = $mixServ->arrayMergeRecursiveEx($merged, $d);
            }
            return $merged;
        }, ['mergeData', $data], '1 hour');
    }

    public function setBackOfficeNav($data)
    {
        $nav = '';
        $colors = ['#a03c3c', '#009688', '#3F51B5', '#000000', '#F44336', '#607D8B', '#CDDC39', '#FF5722', '#2196F3', '#795548', '#767948', '#b4bf0c'];
        if ($data) {
            foreach ($data as $i => $d) {
                if (sizeof($d) === 3) {
                    $data[$i]['id'] = substr(md5($d[0]), 0, 8);
                    $data[$i]['title'] = $d[0];

                    $nav .= '<a href="' . $d[2] . '" class="text-ellipsis" title="' . $d[0] . '">
                            <div class="icon" style="background-color:' . $colors[rand(0, 11)] . '"><span class="iconify" data-icon="' . $d[1] . '" data-inline="false"></span></div>
                            <span>' . $d[0] . '</span>
                        </a>';
                }
            }
        }
        $this->setGlobal([
            'backOfficeNav' => new Markup($nav, 'UTF-8'),
            'backOfficeNavData' => $data
        ]);
    }

    public function setMyNoSQL()
    {
        return $this->setMoSQLConfig();
    }

    /**
     * ⚡ Optimisé - version itérative au lieu de récursive
     */
    public function getAttr($data, $fieldsPath, $onNull = null, $isEmptiale = true)
    {
        $fields = explode('.', $fieldsPath);
        $current = $data;

        foreach ($fields as $field) {
            if (!isset($current[$field])) {
                return $onNull;
            }
            $current = $current[$field];
        }

        if (!$isEmptiale && (is_null($current) || empty($current))) {
            return $onNull;
        }

        return $current;
    }

    /**
     * ⚡ Optimisé avec cache service (pas de cache de données)
     */
    public function getCurrentUser()
    {
        if ($this->currentUserCache !== null) {
            return $this->currentUserCache;
        }
        $this->currentUserCache = $this->serve('security')->getCurrentUser();
        return $this->currentUserCache;
    }

    /**
     * ⚡ Optimisé avec cache service (pas de cache de données)
     */
    public function getRequestStack()
    {
        if ($this->requestStackCache !== null) {
            return $this->requestStackCache;
        }
        $this->requestStackCache = $this->getService('request_stack');
        return $this->requestStackCache;
    }

    /**
     * ⚡ Optimisé avec cache service (pas de cache de données)
     */
    public function getRequest()
    {
        if ($this->requestCache !== null) {
            return $this->requestCache;
        }
        $this->requestCache = $this->getRequestStack()->getCurrentRequest();
        return $this->requestCache;
    }

    public function getRequestStackQuery()
    {
        return $this->getRequest()->query;
    }

    /**
     * ⚡ Optimisé avec cache via serve('cache')->do()
     */
    public function getInput(string $key, $default = null)
    {
        return $this->serve('cache')->do(function () use ($key, $default) {
            $request = $this->getRequest();
            $jsonData = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonData = [];
            }
            $allData = array_merge(
                $request->query->all(),
                $request->request->all(),
                $jsonData
            );

            if ($key === null) {
                return $allData;
            }

            return $allData[$key] ?? $default;
        }, ['getInput', $key, $default], '1 minute');
    }

    public function hasInput(string $key)
    {
        return $this->getInput($key) !== null;
    }

    public function getRequestStackRequest()
    {
        return $this->getRequest()->request;
    }

    public function getRequestUri()
    {
        return $this->getRequest()->getRequestUri();
    }

    public function getParam($var)
    {
        $request = $this->getRequest();
        return $request->query->get($var) ?? $request->request->get($var);
    }

    public function isXHR()
    {
        return $this->serve('cache')->do(function () {
            $xhr = $this->getInput('xhr', true);
            return $this->getRequest()->isXmlHttpRequest() && $xhr;
        }, ['isXHR'], '1 minute');
    }

    public function isNotXHR()
    {
        return !$this->isXHR();
    }

    public function isBot()
    {
        return $this->serve('cache')->do(function () {
            return (bool) preg_match('/bot|crawl|curl|dataprovider|search|get|spider|find|java|majesticsEO|google|yahoo|teoma|contaxe|yandex|libwww-perl|facebookexternalhit/i', $_SERVER['HTTP_USER_AGENT']);
        }, ['isBot'], '1 day');
    }

    public function compress($content)
    {
        return new Markup(
            mb_convert_encoding(
                (new __PhpHtmlCssJsMinifierService())->getMinifiedHtml($content),
                'UTF-8',
                'UTF-8'
            ),
            'UTF-8'
        );
    }

    public function XHRreturn($params)
    {
        $xhr_continue = $this->getRequest()->getRequestUri();
        $xhr_continue = strpos($xhr_continue, '/logout') !== false ? '' : '?xhr_continue=' . $xhr_continue;

        if (isset($params['ifNotXHR'])) {
            return $this->redirect($this->generateUrl($params['ifNotXHR'], $params['params'] ?? []) . $xhr_continue);
        }
    }

    public function redirectToHome()
    {
        return $this->redirect($this->serve('url')->getUrl());
    }

    public function redirectToReferer()
    {
        $referer = $this->getRequest()->headers->get('referer');
        return $this->redirect(is_null($referer) ? $this->serve('url')->getUrl('/') : $referer);
    }

    public function getReferer()
    {
        return $this->getRequest()->headers->get('referer');
    }

    public function getRefererParam($name = '', $onNull = '')
    {
        return $this->serve('cache')->do(function () use ($name, $onNull) {
            $referer = $this->getReferer();
            parse_str(parse_url($referer, PHP_URL_QUERY), $queries);
            return $queries[$name] ?? $onNull;
        }, ['getRefererParam', $name, $onNull], '1 minute');
    }

    public function _redirect($href = '/')
    {
        return $this->redirect($this->serve('url')->getUrl($href));
    }

    public function onSuccessUrl()
    {
        return $this->getRequest()->request->get('_redirect', $this->serve('url')->getUrl());
    }

    public function smartRedirect($route_name, $route_params = [])
    {
        if ($this->isXHR()) {
            return $this->serve('response')->jsonResponse([
                'redirect' => $this->generateUrl($route_name, $route_params),
            ]);
        }
        return $this->redirectToRoute($route_name, $route_params);
    }

    public function curl($routeParameters): void
    {
        if (is_string($routeParameters)) {
            $url = $routeParameters;
        } else {
            $key = array_key_first($routeParameters);
            $routeParameters[$key] = array_merge(
                $routeParameters[$key] ?? [],
                $this->getRequest()->query->all()
            );
            $url = $this->_generateUrl($key, $routeParameters[$key] ?: []);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]);
        curl_exec($ch);
    }

    public function _generatePath(string $route, array $parameters = []): string
    {
        return $this->generateUrl($route, $parameters);
    }

    public function _generateUrl(string $route, array $parameters = []): string
    {
        return $this->generateUrl($route, $parameters, \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function _renderView(string $view, array $parameters = []): string
    {
        return $this->renderView($view . '.html.twig', $parameters);
    }

    public function convertToKnpPaginatorBundle(array $items = [], int $perPage, string $pageQuery = 'page')
    {
        return $this->serve('cache')->do(function () use ($items, $perPage, $pageQuery) {
            $paginator = $this->getService('knp_paginator')->paginate(
                $items,
                (int) $this->getRequest()->query->get('page', 1) ?: 1,
                $perPage
            );
            $paginator->offset = $this->getPaginationOffset($perPage, $pageQuery);
            return $paginator;
        }, ['convertToKnpPaginatorBundle', md5(json_encode($items)), $perPage, $pageQuery], '1 hour');
    }

    public function jasonPaginator($items = [], $perPage = 15)
    {
        return $this->serve('cache')->do(function () use ($items, $perPage) {
            $total = count($items);
            $perPage = (int) $perPage <= 0 ? 1 : (int) $perPage;
            $currPage = (int) $this->getRequest()->query->get('page', 1) ?: 1;
            $urlPattern = '?page=(:num)';
            return new Paginator($total, $perPage, $currPage, $urlPattern);
        }, ['jasonPaginator', md5(json_encode($items)), $perPage], '1 hour');
    }

    public function mimicKnpPaginator(int $total, int $perPage = 15)
    {
        return $this->convertToKnpPaginatorBundle(array_fill(0, $total, 'stOne'), $perPage);
    }

    public function selectPaginator(int $total, int $perPage = 15)
    {
        return $this->serve('cache')->do(function () use ($total, $perPage) {
            $currentPage = (int) $this->getRequest()->query->get('page', 1) ?: 1;
            return $this->serve('pagination')->selectPaginator($total, $currentPage, $perPage);
        }, ['selectPaginator', $total, $perPage], '1 hour');
    }

    public function getPaginationOffset(int $limit, string $pageQuery = 'page')
    {
        $page = $_GET[$pageQuery] ?? 1;
        $page = $page <= 0 ? 1 : $page;
        return $page * $limit - $limit;
    }

    public function fetchEager(?array $data = [], array $orderBy = ['created_at' => 'desc'])
    {
        return $this->getRepo('bloggy')->fetchEager($data, $orderBy);
    }

    /**
     * ⚡ Optimisé avec cache du parsing MAILER
     */
    public function sendEmail(array $params)
    {
        if ($this->mailerConfig === null) {
            $m = $this->serve('env')->getAppEnv('MAILER');
            preg_match('/(.+)(\|)(.+)(\|)(.+)/', $m, $MAILER);

            if (count($MAILER) !== 6) {
                return $params['onError']("Error parsing MAILER provided in .env");
            }

            $smtp = explode('::', $MAILER[5]);
            $this->mailerConfig = [
                'username' => $MAILER[1],
                'password' => $MAILER[3],
                'SMTPSecure' => $smtp[0],
                'Host' => $smtp[1],
                'Port' => $smtp[2]
            ];
        }

        $params = array_merge([
            'IsSMTP' => true,
            'SMTPAuth' => true,
            'SMTPSecure' => $this->mailerConfig['SMTPSecure'],
            'Host' => $this->mailerConfig['Host'],
            'Port' => $this->mailerConfig['Port'],
            'isHTML' => true,
            'username' => $this->mailerConfig['username'],
            'password' => $this->mailerConfig['password'],
            'subject' => 'Wonderful Subject',
            'from' => ['john@doe.com' => 'John Doe'],
            'to' => ['receiver@domain.org', 'other@domain.org' => 'A name'],
            'body' => function ($mail, $mediaService, $urlService, $params) {},
            'onSuccess' => function ($params) {},
            'onError' => function ($e) {},
        ], $params);

        $mail = new PHPMailer();
        $mail->SMTPDebug = false;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        if ($params['IsSMTP']) {
            $mail->IsSMTP();
        }
        $mail->SMTPAuth = $params['SMTPAuth'];
        $mail->SMTPSecure = $params['SMTPSecure'];
        $mail->Host = $params['Host'];
        $mail->Port = $params['Port'];
        $mail->isHTML($params['isHTML']);
        $mail->Username = $params['username'];
        $mail->Password = $params['password'];

        foreach ($params['to'] as $k => $v) {
            $k === 0 ? $mail->AddAddress($v) : $mail->AddAddress($k, $v);
        }

        foreach ($params['from'] as $k => $v) {
            $k === 0 ? $mail->SetFrom($params['username']) : $mail->SetFrom($params['username'], $this->serve('env')->getAppEnv('APP_NAME'));
        }

        $mail->Subject = $params['subject'];
        $mail->Body = $params['body']($mail, $this->serve('asset'), $this->serve('url'), (object) $params);

        if ($mail->Send() && $mail->ErrorInfo == "") {
            return $params['onSuccess']($params, $mail);
        } else {
            return $params['onError']($mail->ErrorInfo);
        }
    }

    public function cacheRendered(callable $callback, $renderCache = true, $cacheKey = null)
    {
        $key = $this->serve('string')->getSlug($cacheKey ?? $this->getRequestUri());

        if ($renderCache) {
            $cachedFile = $this->serve('dir')->dirPath("var/cache/swagg/$key.txt");
            if (file_exists($cachedFile)) {
                $ttl = is_bool($renderCache) ? "1 hour" : $renderCache;
                $ctime = filemtime($cachedFile);
                $expiryTime = date("Y-m-d H:i:s", strtotime("+$ttl", $ctime));
                $now = date("Y-m-d H:i:s");
                if ($now > $expiryTime) {
                    unlink($cachedFile);
                } else {
                    $view = $this->getStorage($key);
                    if ($view) {
                        if ($this->isXHR() && method_exists($view, 'getContent')) {
                            return new JsonResponse(json_decode($view->getContent()));
                        }
                        return $view;
                    }
                }
            }
        }

        if (!$renderCache) {
            return $callback();
        }

        $view = $callback();
        $this->setStorage([[$key, $view]]);
        return $view;
    }

    /**
     * ⚡ Optimisé avec cache via serve('cache')->do()
     */
    public function getData(array $params = [])
    {
        $params = array_merge([
            'coll_name' => null,
            'limit' => 15,
            'criteria' => [],
            'random' => false,
            'order_by' => ['created_at' => 'desc'],
            'select' => [],
            'offset' => 0,
            'pop' => true,
            'watch_collections' => [],
            'c' => []
        ], $params);

        $watch_collections = array_merge($params['watch_collections'] ?? [], [$params['coll_name']]);
        $params['watch_collections'] = $watch_collections;
        $last_updated_at = $this->serve('cache')->getLastUpdatedAt($params);

        return $this->serve('cache')->do(function () use ($params, $watch_collections) {
            $order_by = $params['order_by'];
            $coll_name = $params['coll_name'];
            $limit = $params['limit'];
            $criteria = $params['criteria'];
            $select = $params['select'];
            $offset = $params['offset'];
            $keys = $params['keys'];

            $page = $this->getRequest()->request->get('page', $this->getRequest()->query->get('page', 1));
            $coll_name = in_array($coll_name, ['user', 'users', 'admin']) ? 'users' : $coll_name;

            if (empty($criteria)) {
                $criteria = [];
            }

            $pagination_count = collection($coll_name)->countBy($criteria);
            $query = collection($coll_name)->select(is_array($select) ? $select : []);
            $query = $query->applyCriteria($criteria);

            if ($order_by) {
                $query->orderBy($order_by);
            }

            if ($limit !== null && $limit > 0) {
                $query->limit($limit);
            }

            $query->offset($offset);
            $sqlData = $query->getData();
            $items = $query->fetchAll();

            $knp = $this->mimicKnpPaginator(
                $pagination_count,
                $limit == -1 ? ($pagination_count == 0 ? 1 : $pagination_count) : $limit
            );

            $total_ids = collection($coll_name)->findIDs($criteria);
            $ids_data = collection($coll_name)->findIDs($criteria, [], $limit);
            $ids = $limit == 1 && isset($ids_data['uid']) ? [$ids_data['uid']] : $ids_data;

            return [
                'page' => (int) $page,
                'limit' => (int) $limit,
                'count' => (int) (empty($items) ? 0 : $pagination_count),
                'pagination' => [
                    'knp' => $knp,
                    'select' => $this->selectPaginator($pagination_count, $limit)
                ],
                'sqlData' => $sqlData,
                'items' => $items,
                'ids' => empty($items) ? [] : $ids,
                'idsBag' => $total_ids
            ];
        }, ['getData', $params, $last_updated_at], '1 hour');
    }

    public function getVoidData()
    {
        return [
            'page' => 1,
            'limit' => 1,
            'count' => 0,
            'sqlData' => [],
            'items' => [],
            'ids' => [],
        ];
    }

    public function pop(array|null $items = [], array $params = [])
    {
        if (empty($items)) {
            return [];
        }

        $serveCache = $this->serve('cache');
        $cacheKey = $params['cacheKey'] ?? [];

        $single_item = !isset($items[0]);
        if ($single_item) {
            $items = [$items];
        }

        if ($this->popService === null) {
            $this->popService = $this->getService('pop');
        }

        $popped_items = [];
        $last_updated_at = $serveCache->getLastUpdatedAt($items[0]);
        foreach ($items as $item) {
            $popped_items[] = $serveCache->do(function () use ($popped_items, $item, $params) {
                return array_merge($item, $this->popService->props($item, $params));
            }, ['pop', $last_updated_at, $item], '1 hour');
        }

        return $single_item ? $popped_items[0] : $popped_items;
    }

    public function getFinalData($ids, $coll_name, $is_rand, $params)
    {
        if ($params['ids_only']) {
            return $ids;
        }

        if (!empty($ids)) {
            $criteria = ['id' => ['in', $ids]];
        }

        $limit = 15;

        $data = $this->getData([
            'coll_name' => $coll_name,
            'limit' => $params['limit'] ?? $limit,
            'criteria' => $criteria ?? [],
            'keys' => $params['keys'],
            'order_by' => $is_rand ? ['' => 'rand()'] : $params['order_by'],
            'select' => $params['select'] ?? '*',
            'offset' => $params['offset'] == 0 ? 0 : $this->getPaginationOffset($params['limit'] ?? $limit),
            'options' => $params['options'],
            'watch_collections' => $params['watch_collections']
        ]);

        $items = $data['items'];
        $data['items'] = ($params['pop'] || $params['keys']) ? $this->pop($items, $params) : $items;
        return $data;
    }

    public function getCollection($collectionName)
    {
        return new Quant($GLOBALS['_ENV']['DATABASE_PREFIX'] . $collectionName, $GLOBALS['MoSQLConfig']);
    }

    public function incrementHits($uid)
    {
        $uid = $uid['id'] ?? $uid;
        file_get_contents($this->serve('url')->getUrl("_admin/hits/$uid/set"));
        return null;
    }

    public function getRemoteLessCompilation($less, $path, $key)
    {
        return $this->serve('cache')->do(function () use ($less, $path, $key) {
            $origin = rtrim($this->serve('env')->getAppEnv('LESS_COMPILER_ORIGIN'), '/') . '/';
            $css = file_get_contents($origin, false, stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => http_build_query(['less' => $less]),
                ]
            ]));

            $id = md5("$path-$key") . '_' . (new \DateTime())->format("dmY_His");

            return ['less' => $less, 'id' => $id, 'css' => $css];
        }, ['getRemoteLessCompilation', md5($less), $path, $key], '1 month');
    }

    public function normalizeCriteria(array $criteria, array $ids): array
    {
        if (isset($criteria[0][2])) {
            if (is_array($criteria[0][2])) {
                $cri_original = $criteria[0][2];
                $criteria[0][2] = array_intersect($cri_original, $ids);
                if (empty($criteria[0][2])) {
                    $criteria[0][2] = $cri_original;
                }
            }
        } else {
            if (!empty($ids)) {
                $criteria[] = ['id' => ['in', $ids]];
            }
        }

        usort($criteria, function ($a, $b) {
            $aKey = isset($a[0]) && is_string($a[0]) ? $a[0] : '';
            $bKey = isset($b[0]) && is_string($b[0]) ? $b[0] : '';

            if ($aKey === 'id') return -1;
            if ($bKey === 'id') return 1;

            return strcmp($aKey, $bKey);
        });

        return $criteria;
    }

    public function checkHook($hookName, ?callable $then)
    {
        $str = $this->serve('string');
        $camel_to_snake = $str->camelToSnake($hookName);
        $className = ucfirst($hookName);

        if ((new Filesystem())->exists($this->serve('dir')->getProjectDir() . "/src/Hook/$className.php")) {
            $serviceClass = 'App\\Hook\\' . $className;
            if (method_exists($serviceClass, $hookName) && $this->prevContainer->has($camel_to_snake)) {
                return $then($this->getService($camel_to_snake));
            }
        }
        dd("please->checkHook($hookName) not found");
        return $then();
    }

    public function fbDialogAttr(array $titles, array $params)
    {
        if (!empty($params['item']) && !empty($params['item']['id'])) {
            $document = $params['item'];
            $id = $document['id'];
            $coll_name = $document['coll_name'] ?? 'users';
        } else {
            $id = null;
            $coll_name = $params['coll_name'] ?? 'users';
            $document = null;
        }

        $title = $titles[$coll_name] ?? 'un élément';
        $title = ($id ? 'Modifier ' : 'Ajouter ') . ($document ? $document['title'] : $title);

        $data = [
            "title" => $title,
            "tpl" => $params['tpl'] ?? "_back/tpl/$coll_name",
            "endpoint" => $this->generateUrl('getTpl'),
            "footer" => true,
            "data" => $id ? ['id' => $id] : []
        ];

        return new Markup(
            'data-backstatable="toggleFBDialogAjax" data-fbdialog-ajax="' . htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8') . '"',
            'UTF-8'
        );
    }

    /**
     * @deprecated Utiliser getAttr() à la place
     */
    protected function _getAttrLoop($obj, $attrs, $i, $onNull)
    {
        return $this->getAttr($obj, implode('.', array_slice($attrs, $i)), $onNull);
    }

    /**
     * ⚡ Méthode utilitaire pour vider les caches
     */
    public function clearCache()
    {
        $this->serviceCache = [];
        $this->mailerConfig = null;
        $this->currentUserCache = null;
        $this->requestCache = null;
        $this->requestStackCache = null;
        $this->moSQLConfigCache = null;
        $this->popService = null;
    }
}
