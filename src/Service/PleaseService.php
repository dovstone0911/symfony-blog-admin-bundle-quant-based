<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Repository\BundleBloggyRepository;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Repository\BundleUserRepository;
use Dovstone\MoSQL\MoSQL;
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
    //
    private $appUiD;
    protected $pdo;

    public function __construct(ContainerInterface $container)
    {
        $this->prevContainer = $container;
        $this->appUiD = sha1($_SERVER['APP_NAME']);
    }

    public function getContainer()
    {
        return $this->prevContainer;
    }

    public function getService($serviceName)
    {
        return $this->prevContainer->get("{$serviceName}");
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

    public function serve($serviceName)
    {
        return $this->prevContainer->get("{$serviceName}");
    }

    public function setGlobal($bigData)
    {
        if ($bigData) {
            foreach ($bigData as $globalName => $data) {
                if (is_callable($data)) {
                    $data = $data();
                }
                $this->getService('session')->set('__global__' . $this->appUiD . '__' . $globalName, serialize($data));
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
        if (is_array($globalName)) {
            foreach ($globalName as $gbName) {
                $this->getService('session')->set('__global__' . $this->appUiD . '__' . $gbName, null);
            }
        } else {
            $this->getService('session')->set('__global__' . $this->appUiD . '__' . $globalName, null);
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

                    //creating file if not exists yet
                    if (!$fs->exists($file)) {
                        $fs->appendToFile($file, '');
                    }

                    //only put content if file is empty
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
                $file = $fileName . 'File';
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
        $merged = [];
        $data = json_decode(json_encode($data), true);
        $mixServ = $this->serve('mix');
        foreach ($data as $d) {
            $merged = $mixServ->arrayMergeRecursiveEx($merged, $d);
        }
        return $merged;
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

        return $config;
    }

    public function getAttr($data, $fieldsPath, $onNull = null, $isEmptiale = true)
    {
        $attrFetchedVal = null;
        $fieldsPath = explode('.', $fieldsPath);
        $size = sizeof($fieldsPath);
        for ($i = 0; $i < $size; $i++) {
            $val = $this->_getAttrLoop($data, $fieldsPath, $i, $onNull);
            if ($isEmptiale == false && (is_null($val) || empty($val))) {
                return $onNull;
            }
            return $val;
        }
        return null;
    }

    public function getCurrentUser()
    {
        return $this->serve('security')->getCurrentUser();
    }

    public function getRequestStack()
    {
        return $this->getService('request_stack');
    }

    public function getRequest()
    {
        return $this->getService('request_stack')->getCurrentRequest();
    }

    public function getRequestStackQuery()
    {
        return $this->getRequestStack()->getCurrentRequest()->query;
    }

    public function getInput(string $key, $default = null)
    {
        $request = $this->getRequest();

        // Récupère tout le JSON body
        $jsonData = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonData = [];
        }

        // Fusionne GET, POST et JSON body
        $data = array_merge($request->query->all(), $request->request->all(), $jsonData);

        if ($key === null) {
            return $data; // retourne tout
        }

        return $data[$key] ?? $default;
    }

    public function hasInput(string $key)
    {
        return $this->getInput($key) !== null;
    }

    public function getRequestStackRequest()
    {
        return $this->getRequestStack()->getCurrentRequest()->request;
    }

    public function getRequestUri()
    {
        return $this->getRequestStack()->getCurrentRequest()->getRequestUri();
    }

    public function getParam($var)
    {
        return $this->getRequestStackQuery()->get($var) ?? $this->getRequestStackRequest()->get($var);
    }

    public function isXHR()
    {
        $xhr = $this->getInput('xhr', true);
        return $this->getRequest()->isXmlHttpRequest() && $xhr;
    }

    public function isNotXHR()
    {
        return !$this->isXHR();
    }

    public function isBot()
    {
        if (preg_match('/bot|crawl|curl|dataprovider|search|get|spider|find|java|majesticsEO|google|yahoo|teoma|contaxe|yandex|libwww-perl|facebookexternalhit/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        }
        return false;
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
        parse_str(parse_url($this->getReferer(), PHP_URL_QUERY), $queries);
        return attr($queries, $name, $onNull);
    }

    public function _redirect($href = '/')
    {
        return $this->redirect($this->serve('url')->getUrl($href));
    }

    public function onSuccessUrl()
    {
        return $this->getRequestStackRequest()->get('_redirect', $this->serve('url')->getUrl());
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
                $this->getRequestStackQuery()->all()
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
        $paginator = $this->getService('knp_paginator')->paginate(
            $items,
            (int) $this->getService('request_stack')->getCurrentRequest()->query->get('page', 1) ?: 1,
            $perPage
        );
        $paginator->offset = $this->getPaginationOffset($perPage, $pageQuery);
        return $paginator;
    }

    public function jasonPaginator($items = [], $perPage = 15)
    {
        // $query = $collection;
        // foreach ($finderCriteria[0] as $field => $value) {
        //     $query->where($field, '=', $value);
        // }
        // $total = $query->count();
        $total = count($items);
        $perPage = (int) $perPage <= 0 ? 1 : (int) $perPage;
        $currPage = (int) $this->getService('request_stack')->getCurrentRequest()->query->get('page', 1) ?: 1;
        $urlPattern = '?page=(:num)';
        return new Paginator($total, $perPage, $currPage, $urlPattern);
    }

    public function mimicKnpPaginator(int $total, int $perPage = 15)
    {
        return $this->convertToKnpPaginatorBundle(array_fill(0, $total, 'stOne'), $perPage);
    }

    public function selectPaginator(int $total, int $perPage = 15)
    {
        $currentPage = (int) $this->getService('request_stack')->getCurrentRequest()->query->get('page', 1) ?: 1;
        return $this->serve('pagination')->selectPaginator($total, $currentPage, $perPage);
    }

    public function getPaginationOffset(int $limit, string $pageQuery = 'page')
    {
        $page = $_GET[$pageQuery] ?? 1;
        $page = $page <= 0 ? 1 : $page;
        $offset = $page * $limit - $limit;
        return $offset;
    }

    public function fetchEager(?array $data = [], array $orderBy = ['created_at' => 'desc'])
    {
        return $this->getRepo('bloggy')->fetchEager($data, $orderBy);
    }

    public function sendEmail(array $params)
    {
        $m = $this->serve('env')->getAppEnv('MAILER');
        preg_match('/(.+)(\|)(.+)(\|)(.+)/', $m, $MAILER);

        if (count($MAILER) !== 6) {
            return $params['onError']("Error parsing MAILER provided in .env");
        }

        $username = $MAILER[1];
        $password = $MAILER[3];

        $smtp = explode('::', $MAILER[5]);
        $SMTPSecure = $smtp[0];
        $Host = $smtp[1];
        $Port = $smtp[2];

        $params = array_merge([
            'IsSMTP' => true, // false means IsMail
            'SMTPAuth' => true,
            'SMTPSecure' => $SMTPSecure,
            'Host' => $Host,
            'Port' => $Port, //465
            'isHTML' => true,
            //
            'username' => $username,
            'password' => $password,
            //
            'subject' => 'Wonderful Subject',
            'from' => ['john@doe.com' => 'John Doe'],
            'to' => ['receiver@domain.org', 'other@domain.org' => 'A name'],

            'body' => function ($mail, $mediaService, $urlService, $params) {},
            'onSuccess' => function ($params) {},
            'onError' => function ($e) {},
        ], $params);

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer();

        $mail->SMTPDebug = false;

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        if ($params['IsSMTP']) {
            $mail->IsSMTP(); // telling the class to use SMTP
        }
        $mail->SMTPAuth = $params['SMTPAuth']; // enable SMTP authentication
        $mail->SMTPSecure = $params['SMTPSecure']; // sets the prefix to the servier
        $mail->Host = $params['Host']; // sets the SMTP server
        $mail->Port = $params['Port']; // set the SMTP port
        $mail->isHTML($params['isHTML']);
        //
        $mail->Username = $params['username']; // SMTP account username
        $mail->Password = $params['password']; // SMTP account password

        foreach ($params['to'] as $k => $v) {
            $k === 0 ? $mail->AddAddress($v) : $mail->AddAddress($k, $v);
        }

        //foreach ($params['from'] as $k => $v) { $k === 0  ? $mail->SetFrom($v) : $mail->SetFrom($k, $v); }
        foreach ($params['from'] as $k => $v) {
            $k === 0 ? $mail->SetFrom($username) : $mail->SetFrom($username, $this->serve('env')->getAppEnv('APP_NAME'));
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

        // lets check ttl
        if ($renderCache) {
            if (file_exists($cachedFile = $this->serve('dir')->dirPath("var/cache/swagg/$key.txt"))) {
                $ttl = is_bool($renderCache) ? "1 hour" : $renderCache;
                $ctime = filemtime($cachedFile);
                $expiryTime = date("Y-m-d H:i:s", strtotime("+$ttl", $ctime));
                $now = date("Y-m-d H:i:s");
                if ($now > $expiryTime) {
                    unlink($cachedFile);
                }
            }
        }

        if (!$renderCache) {
            return $callback();
        }

        if ($view = $this->getStorage($key)) {
            if ($this->isXHR()) {
                if (method_exists($view, 'getContent')) {
                    return new JsonResponse(json_decode($view->getContent()));
                }
            }
            return $view;
        }
        $view = $callback();
        $this->setStorage([[$key, $view]]);
        //
        return $view;
    }

    public function getVoidData()
    {
        return [
            'page' => 1,
            'limit' => 1,
            'count' => 0,
            'getData' => [],
            'items' => [],
            'ids' => [],
        ];
    }

    public function getData($ids, $coll_name, $is_rand, $params)
    {
        if ($params['ids_only']) {
            return $ids;
        }

        // 🔥 Initialisation des critères
        $criteria = [];
        if (!empty($ids)) {
            $criteria = ['uid' => ['IN', $ids]];
        }

        $limit = 15;

        // 🔥 Fusion des paramètres
        $params = array_merge([
            'coll_name' => $coll_name,
            'limit' => $params['limit'] ?? $limit,
            'criteria' => $criteria,
            'with' => $params['with'] ?? [],
            'order_by' => $is_rand ? ['RAND()'] : ($params['order_by'] ?? ['created_at' => 'DESC']),
            'select' => $params['select'] ?? ['*'],
            'offset' => $params['offset'] ?? 0,
            'options' => $params['options'] ?? [],
        ], $params);

        $order_by = $params['order_by'];
        $coll_name = $params['coll_name'];
        $limit = $params['limit'];
        $criteria = $params['criteria'];
        $select = $params['select'];
        $offset = $params['offset'];
        $with = $params['with'];

        $data = $this->serve('cache')->do(function () use (
            $order_by,
            $coll_name,
            $limit,
            $criteria,
            $select,
            $offset
        ) {
            $page = input('page', 1);

            // 🔥 Normalisation du nom de la collection
            $coll_name = in_array($coll_name, ['user', 'users', 'admin']) ? 'users' : $coll_name;

            if (empty($criteria)) {
                $criteria = [];
            }

            // 🔥 Compte total avec Quant
            $pagination_count = quant($coll_name)->countBy($criteria);

            // 🔥 Construction de la requête avec Quant
            $query = quant($coll_name);

            // Select
            if (is_array($select) && !in_array('*', $select)) {
                $query->select($select);
            }

            // 🔥 Appliquer les critères (where)
            if (!empty($criteria)) {
                $query->where($criteria);
            }

            // 🔥 Appliquer le tri
            if (!empty($order_by)) {
                if (is_array($order_by)) {
                    foreach ($order_by as $field => $direction) {
                        if (strtoupper($field) === 'RAND()' || $field === 'rand()') {
                            $query->inRandomOrder();
                        } else {
                            $query->orderBy($field, $direction);
                        }
                    }
                } else {
                    $query->orderBy($order_by);
                }
            }

            // 🔥 Limit et Offset
            if ($limit !== null && $limit > 0) {
                $query->limit($limit);
            }
            if ($offset > 0) {
                $query->offset($offset);
            }

            // 🔥 Récupérer le SQL généré (debug)
            $getData = $query->getData();

            // 🔥 Exécuter la requête
            $items = $limit == 1 ? $query->first() : $query->fetch();

            // 🔥 Pagination
            $limit = ($limit == -1 ? ($pagination_count == 0 ? 1 : $pagination_count) : $limit) ?? 15;
            $knp = $this->mimicKnpPaginator($pagination_count, $limit);

            // 🔥 Récupérer les IDs
            $total_ids = quant($coll_name)->findIDs($criteria);
            $ids_data = quant($coll_name)->findIDs($criteria, 'id', $limit);
            $ids = $limit == 1 && isset($ids_data['id']) ? [$ids_data['id']] : $ids_data;

            return [
                'page' => (int) $page,
                'limit' => (int) $limit,
                'count' => (int) (empty($items) ? 0 : $pagination_count),
                'pagination' => [
                    'knp' => $knp,
                    'select' => $this->selectPaginator($pagination_count, $limit)
                ],
                'getData' => $getData,
                'items' => $items,
                'ids' => empty($items) ? [] : $ids,
                'ids_bag' => $total_ids
            ];
        }, [$params, $this->serve('cache')->getCurrentUpdatedAt($coll_name), uniqid()]);

        $items = $data['items'];

        // 🔥 Appliquer les relations (with)
        $data['items'] = !empty($params['with']) ? $this->serve('populate')->with($items, $params) : $items;

        return $data;
    }

    public function incrementHits($uid)
    {
        $uid = $uid['id'] ?? $uid;
        file_get_contents($this->serve('url')->getUrl("_admin/hits/$uid/set"));
        return null;
    }

    public function getRemoteLessCompilation($less, $path, $key)
    {
        // Compiler via l'API
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

        // Tri sécurisé
        usort($criteria, function ($a, $b) {
            $aKey = isset($a[0]) && is_string($a[0]) ? $a[0] : '';
            $bKey = isset($b[0]) && is_string($b[0]) ? $b[0] : '';

            if ($aKey === 'id') {
                return -1;
            }
            if ($bKey === 'id') {
                return 1;
            }

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

        // Extrait le titre dans $titles
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

    protected function _getAttrLoop($obj, $attrs, $i, $onNull)
    {
        $size = sizeof($attrs);
        $attrToFetch = $attrs[$i];
        if (isset($obj[$attrToFetch])) {
            $val = $obj[$attrToFetch];
            if ($i == $size - 1) {
                return $val;
            }
            return $this->_getAttrLoop($val, $attrs, ++$i, $onNull);
        }
        return $onNull ?? null;
    }
}
