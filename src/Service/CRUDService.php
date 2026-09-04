<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class CRUDService extends AbstractController
{
    private $please;
    public $log;
    public $is;
    public $isBadRequest;
    public $hookData;
    public const ANY = '__ANY__';
    public const NONE = '__NONE__';

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->log = $please->serve('log');
    }

    public function create($params)
    {
        $params = array_merge([
            'dd' => null,
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'isValid' => false,
            'csrfVerif' => true,
            'validator' => [],
            'onInvalid' => function ($errors) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse(['errors' => $errors]);
                }
                // $this->please->setFlash(['errors' => $errors]);
                return $this->redirect($this->please->getReferer());
            },
            'sanitizeRequest' => true,
            'sanitizeData' => true,
            'reservedProps' => [],
            'callHook' => true,
            'sanitizer' => function () {},
            'isBadRequest' => function () {
                return false;
            },
            'onBadRequest' => function () {},
            'beforeSend' => null,
            'formView' => function () {},
            'onSuccess' => function ($document) {},
            'onError' => function ($message) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'reload' => true,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($this->please->serve('url')->getHome());
            }
        ], $params);

        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('create', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            $this->is = 'C';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('create', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            if (
                $this->please->getRequest()->isMethod('POST')
                ||
                $this->please->getRequest()->isMethod('PUT')
                ||
                $this->please->getRequest()->isMethod('PATCH')
            ) {
                return $this->checkDataValidity($params, function ($params) {
                    $coll_name = $this->_getCollectionName($params);
                    $collection = collection($coll_name);

                    if ($params['sanitizeRequest'] === true) {
                        $this->_sanitizeRequest($coll_name);
                    }

                    //$all = $_POST; //$all = $this->_getRequestAll();
                    $all = $this->_getRequestAll();

                    //$sanitized = $params['sanitizer']($all, $collection, $this) ?? $all;
                    $sanitized = $params['sanitizeData'] === false
                        ? ($params['sanitizer']($all, $collection, $this) ?? [])
                        : array_merge($all, $params['sanitizer']($all, $collection, $this) ?? $all); // <<- 30.07.22

                    if ($params['isBadRequest']($all, $collection, $this) === true) {
                        return $params['onBadRequest']();
                    } // <<- 21.04.22

                    if ($params['sanitizeData'] === true) {
                        $data = $this->_sanitizeData($coll_name, $sanitized, $params);
                    }
                    $data = $this->_sanitizeDataAnyway($coll_name, $data ?? [], $params); // <<- 10.08.22
                    $data = array_merge($data ?? [], $sanitized ?? []); // <<- 22.07.21
                    $data = $this->_sanitizeDataAnyway($coll_name, $data, $params); // <<- 12.08.22

                    if ($params['sanitizeData'] === true && $params['callHook'] === true) { // <<- 30.07.22
                        $this->_setHookSanitizer(); // <<- 08.02.22
                        $hookData = $this->_getHookSanitizer($data['coll_name'] ?? $data['roles'] ?? null, $data); // <<- 22.07.21
                        if ($hookData instanceof Response) {
                            return $hookData;
                        }
                        $data = array_merge($data, $hookData ?? []); // <<- 22.07.21
                    }

                    $data = $this->_sanitizeProps($data); // <<- 16.12.23
                    $data = $this->dismissReservedProps($data, $params);

                    if (isset($params['dd'])) { // <<- 05.07.23
                        dd('dump and die :: create', $data);
                    }

                    if (is_callable($beforeSend = $params['beforeSend'])) {
                        $beforeSend($all, $data, $coll_name);
                    }

                    $data = collection($coll_name)->insert($data);
                    //
                    $this->_deleteCache($data);
                    //
                    $this->log->writeDB('success', 'create', [
                        'params' => $params,
                        'data' => $data,
                        'message' => 'document crée avec succès'
                    ], true);
                    return $params['onSuccess']($data, $all, $params);
                }, null, 'create');
            }
            return $params['formView']($params);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'create');
        }
    }

    public function basicCreate($params)
    {
        $params = array_merge([
            'dd' => null,
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'isValid' => false,
            'csrfVerif' => true,
            'validator' => [],
            'onInvalid' => function ($errors) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse(['errors' => $errors]);
                }
                // $this->please->setFlash(['errors' => $errors]);
                return $this->redirect($this->please->getReferer());
            },
            'sanitizeRequest' => true,
            'sanitizeData' => true,
            'reservedProps' => [],
            'callHook' => true,
            'sanitizer' => function () {},
            'isBadRequest' => function () {
                return false;
            },
            'onBadRequest' => function () {},
            'beforeSend' => null,
            'onSuccess' => function ($bloggy) {},
            'onError' => function ($message) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'reload' => true,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($this->please->serve('url')->getHome());
            }
        ], $params);

        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('basicCreate', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            $this->is = 'C';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('basicCreate', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            return $this->checkDataValidity($params, function ($params) {

                $coll_name = $this->_getCollectionName($params);
                $collection = collection($coll_name);

                if ($params['sanitizeRequest'] === true) {
                    $this->_sanitizeRequest($coll_name);
                }

                //$all = $_POST; //$all = $this->_getRequestAll();
                $all = $this->_getRequestAll();

                //$sanitized = $params['sanitizer']($all, $collection, $this) ?? $all;
                $sanitized = $params['sanitizeData'] === false
                    ? ($params['sanitizer']($all, $collection, $this) ?? [])
                    : array_merge($all, $params['sanitizer']($all, $collection, $this) ?? $all); // <<- 30.07.22

                if ($params['isBadRequest']($all, $collection, $this) === true) {
                    return $params['onBadRequest']();
                } // <<- 21.04.22

                if ($params['sanitizeData'] === true) {
                    $data = $this->_sanitizeData($coll_name, $sanitized, $params);
                }
                $data = $this->_sanitizeDataAnyway($coll_name, $data ?? [], $params); // <<- 10.08.22
                $data = array_merge($data ?? [], $sanitized ?? []); // <<- 22.07.21
                $data = $this->_sanitizeDataAnyway($coll_name, $data, $params); // <<- 12.08.22

                if ($params['sanitizeData'] === true && $params['callHook'] === true) { // <<- 30.07.22
                    $this->_setHookSanitizer(); // <<- 08.02.22
                    $hookData = $this->_getHookSanitizer($data['coll_name'] ?? $data['roles'] ?? null, $data); // <<- 22.07.21
                    if ($hookData instanceof Response) {
                        return $hookData;
                    }
                    $data = array_merge($data, $hookData ?? []); // <<- 22.07.21
                }

                $data = $this->_sanitizeProps($data); // <<- 16.12.23
                $data = $this->dismissReservedProps($data, $params);

                if (isset($params['dd'])) { // <<- 05.07.23
                    dd('dump and die :: basicCreate', $data);
                }

                if (is_callable($beforeSend = $params['beforeSend'])) {
                    $beforeSend($all, $data, $coll_name);
                }

                $data = collection($coll_name)->insert($data);
                //
                $this->_deleteCache($data);
                //
                $this->log->writeDB('success', 'basicCreate', [
                    'params' => $params,
                    'data' => $data,
                    'message' => 'document crée avec succès'
                ], true);
                //
                return $params['onSuccess']($data, $all, $params);
            }, null, 'basicCreate');
        } catch (\Exception $e) {
            $params['onError']($e);
            return $this->log->exception($e, 'basicCreate');
        }
    }

    public function read($params)
    {
        $params = array_merge([
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'csrfVerif' => false,
            'finder' => function ($collection) {},
            'onNotFound' => function () {
                return $this->please->redirectToHome();
            },
            'onFound' => function ($document) {}
        ], $params);

        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('read', $params);
                return $params['onNotFound']("Non-valid CSRF token");
            }

            $this->is = 'R';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }
            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('read', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            $coll_name = $this->_getCollectionName($params);
            $collection = collection($coll_name);

            $document = is_callable($params['finder']) ? $params['finder']($collection) : $params['finder'];

            if (!$document) {
                $this->log->docNotFound('read', $params);
                return $params['onNotFound']();
            }
            $this->log->docFound('read', $params, $document);
            return $params['onFound']($document);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'read');
        }
    }

    public function readList($params)
    {
        $params = array_merge([
            'dd' => null,
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'csrfVerif' => false,
            'finder' => function ($collection) {},
            'finderCriteria' => function ($collection) {},
            'limit' => $this->please->getInput('limit') ?? 30,
            'view' => function ($documents, $paginator, $total, $params) {}
        ], $params);

        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('readList', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            $this->is = 'R';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('readList', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            $coll_name = $this->_getCollectionName($params);

            // lets check if this is in fact ACF
            /*$is_acf = collection('acf')->countBy([
                ['coll_name', '=', 'acf'], 
                ['name', '=', $coll_name], 
                ['fields_list', 'exists', true]
            ]);
            dd($is_acf);
            $coll_name = $is_acf ? 'acf' : $coll_name;*/

            //$documents = is_callable($params['finder']) ? $params['finder']($collection) : $params['finder'];

            $finderCriteria = is_callable($params['finderCriteria']) ? $params['finderCriteria'](collection($coll_name)) : $params['finderCriteria'];
            $finalCriteria = $finderCriteria[0][0] ?? $finderCriteria[0] ?? [];

            if (isset($finalCriteria[0]) && is_string($finalCriteria[0])) {
                $finalCriteria = [$finalCriteria];
            }

            $query = collection($coll_name)->whereBy($finalCriteria)->orderBy($finderCriteria[1] ?? []);

            if (isset($params['dd'])) {
                dd('dump and die :: readList', $query->getData());
            }

            $total = $query->count();
            $documents = $query->limit($params['limit'])->fetchAll();

            $knpPaginator = $this->please->mimicKnpPaginator($total, $params['limit']);
            $paginatorFormControl = $this->please->selectPaginator($total, $params['limit']);

            $pagination = [
                'knp' => $knpPaginator,
                'form_control' => $paginatorFormControl
            ];

            $this->log->docFound('readList', $params, $documents);
            return $params['view']($documents, $pagination, $total, $params);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'readList');
        }
    }

    public function update($params)
    {
        $params = array_merge([
            'dd' => null,
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'isValid' => false,
            'csrfVerif' => true,
            'validator' => [],
            'onInvalid' => function ($errors) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse(['errors' => $errors]);
                }
                // $this->please->setFlash(['errors' => $errors]);
                return $this->redirect($this->please->getReferer());
            },
            'finder' => function ($collection) {},
            'onNotFound' => function () {
                return $this->please->serve('response')->jsonResponse([
                    'reload' => false,
                    'swal' => [
                        'icon' => 'error',
                        'title' => 'Donnée introuvable',
                    ]
                ], 404);
            },
            'sanitizeRequest' => true,
            'sanitizeData' => true,
            'reservedProps' => [],
            'callHook' => true,
            'sanitizer' => function () {},
            'isBadRequest' => function () {
                return false;
            },
            'onBadRequest' => function () {},
            'beforeSend' => null,
            'formView' => function ($document) {},
            'onSuccess' => function ($document) {},
            'onError' => function ($message) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'reload' => true,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($this->please->serve('url')->getHome());
            }
        ], $params);

        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('update', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            $this->is = 'U';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('update', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            $coll_name = $this->_getCollectionName($params);
            $collection = collection($coll_name);

            $document = $params['finder']($collection);

            if (!$document) {
                $this->log->docNotFound('update', $params);
                return $params['onNotFound']();
            }

            if (
                $this->please->getRequest()->isMethod('POST')
                ||
                $this->please->getRequest()->isMethod('PUT')
                ||
                $this->please->getRequest()->isMethod('PATCH')
            ) {
                return $this->checkDataValidity($params, function ($params) use ($document) {

                    $coll_name = $document['coll_name'] ?? $this->_getCollectionName($params);
                    $collection = collection($coll_name);

                    if ($params['sanitizeRequest'] === true) {
                        $this->_sanitizeRequest($coll_name, $document);
                    }

                    //parse_str(file_get_contents("php://input"), $PUT_OR_PATCH) ?? []; // <<- 14.12.23
                    //$all = array_merge($_POST, $PUT_OR_PATCH); //$all = $this->_getRequestAll();
                    $all = $this->_getRequestAll();

                    //$sanitized = array_merge($all, $params['sanitizer']($all, $document, $collection, $this) ?? []);
                    $sanitized = $params['sanitizeData'] === false
                        ? ($params['sanitizer']($all, $document, $collection, $this) ?? [])
                        : array_merge($all, $params['sanitizer']($all, $document, $collection, $this) ?? $all); // <<- 30.07.22

                    if ($params['isBadRequest']($all, $collection, $this) === true) {
                        return $params['onBadRequest']();
                    } // <<- 21.04.22

                    //$sanitized = array_merge($document, $sanitized);

                    if ($params['sanitizeData'] === true) {
                        $data = $this->_sanitizeData($coll_name, $sanitized, $params);
                    }
                    $data = $this->_sanitizeDataAnyway($coll_name, $document, $params); // <<- 10.08.22
                    $data = array_merge($data ?? [], $sanitized ?? []); // <<- 22.07.21
                    $data = $this->_sanitizeDataAnyway($coll_name, $data, $params); // <<- 12.08.22

                    if ($params['sanitizeData'] === true && $params['callHook'] === true) { // <<- 30.07.22
                        $this->_setHookSanitizer(); // <<- 08.02.22
                        $hookData = $this->_getHookSanitizer($data['coll_name'] ?? $data['roles'] ?? null, $data); // <<- 22.07.21
                        if ($hookData instanceof Response) {
                            return $hookData;
                        }
                        $data = array_merge($data, $hookData ?? []); // <<- 22.07.21
                    }

                    $data = $this->_sanitizeProps($data); // <<- 16.12.23
                    $data = $this->dismissReservedProps($data, $params);

                    if (isset($params['dd'])) { // <<- 05.07.23
                        dd('dump and die :: update', $data);
                    }

                    if (is_callable($beforeSend = $params['beforeSend'])) {
                        $beforeSend($all, $data, $coll_name);
                    }

                    $data = collection($coll_name)->updateById(attr($document, 'id'), $data);
                    //n
                    $this->_deleteCache($data);
                    //
                    $this->log->writeDB('success', 'update', [
                        'params' => $params,
                        'data' => $data,
                        'message' => 'document mis à jour'
                    ], true);
                    //
                    return $params['onSuccess']($data, $all);
                }, $document, 'update');
            }
            $this->log->docFound('update', $params, $document);
            return $params['formView']($document);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'update');
        }
    }

    public function basicUpdate($params)
    {
        $params = array_merge([
            'dd' => null,
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'isValid' => false,
            'csrfVerif' => true,
            'validator' => [],
            'onInvalid' => function ($errors) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse(['errors' => $errors]);
                }
                // $this->please->setFlash(['errors' => $errors]);
                return $this->redirect($this->please->getReferer());
            },
            'log' => true,
            'finder' => function ($collection) {},
            'onNotFound' => function () {
                return $this->please->serve('response')->jsonResponse([
                    'reload' => false,
                    'swal' => [
                        'icon' => 'error',
                        'title' => 'Donnée introuvable',
                    ]
                ], 404);
            },
            'sanitizeRequest' => true,
            'sanitizeData' => true,
            'reservedProps' => [],
            'callHook' => true,
            'sanitizer' => function ($posted, $document, $collection, $params) {},
            'isBadRequest' => function () {
                return false;
            },
            'onBadRequest' => function ($posted, $document, $collection, $params) {},
            'beforeSend' => null,
            'onSuccess' => function ($document) {},
            'onError' => function ($message) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'reload' => true,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($this->please->serve('url')->getHome());
            }
        ], $params);

        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('basicUpdate', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            $this->is = 'U';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('basicUpdate', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            $coll_name = $this->_getCollectionName($params);
            $collection = collection($coll_name);

            $document = $params['finder']($collection);

            if (!$document) {
                $this->log->docNotFound('update', $params);
                return $params['onNotFound']();
            }
            return $this->checkDataValidity($params, function ($params) use ($document, $collection) {

                $coll_name = $document['coll_name'] ?? $this->_getCollectionName($params);

                if ($params['sanitizeRequest'] === true) {
                    $this->_sanitizeRequest($coll_name, $document);
                }

                //parse_str(file_get_contents("php://input"), $PUT_OR_PATCH) ?? []; // <<- 14.12.23
                //$all = array_merge($_POST, $PUT_OR_PATCH); //$all = $this->_getRequestAll();
                $all = $this->_getRequestAll();

                //$sanitized = array_merge($all, $params['sanitizer']($all, $document, $collection, $this) ?? $all);
                $sanitized = $params['sanitizeData'] === false
                    ? ($params['sanitizer']($all, $document, $collection, $this) ?? [])
                    : array_merge($all, $params['sanitizer']($all, $document, $collection, $this) ?? $all); // <<- 30.07.22

                if ($params['isBadRequest']($all, $collection, $this) === true) {
                    return $params['onBadRequest']();
                } // <<- 21.04.22

                //$sanitized = array_merge($document, $sanitized);

                if ($params['sanitizeData'] === true) {
                    $data = $this->_sanitizeData($coll_name, $sanitized, $params);
                }
                $data = $this->_sanitizeDataAnyway($coll_name, $document, $params); // <<- 10.08.22
                $data = array_merge($data ?? [], $sanitized ?? []); // <<- 22.07.21
                $data = $this->_sanitizeDataAnyway($coll_name, $data, $params); // <<- 12.08.22

                if ($params['sanitizeData'] === true && $params['callHook'] === true) { // <<- 30.07.22
                    $this->_setHookSanitizer(); // <<- 08.02.22
                    $hookData = $this->_getHookSanitizer($data['coll_name'] ?? $data['roles'] ?? null, $data); // <<- 22.07.21
                    if ($hookData instanceof Response) {
                        return $hookData;
                    }
                    $data = array_merge($data, $hookData ?? []); // <<- 22.07.21
                }

                $data = $this->_sanitizeProps($data); // <<- 16.12.23
                $data = $this->dismissReservedProps($data, $params);

                if (isset($params['dd'])) { // <<- 05.07.23
                    dd('dump and die :: basicUpdate', $data);
                }

                if (is_callable($beforeSend = $params['beforeSend'])) {
                    $beforeSend($all, $data, $coll_name);
                }

                $data = collection($coll_name)->updateById(attr($document, 'id'), $data);
                //
                $this->_deleteCache($data);
                //
                $this->log->writeDB('success', 'create', [
                    'params' => $params,
                    'data' => $data,
                    'message' => 'document mis à jour avec succès'
                ], true);
                //
                return $params['onSuccess']($data, $all);
            }, $document, 'basicUpdate');
        } catch (\Exception $e) {
            return $this->log->exception($e, 'basicUpdate');
        }
    }

    public function delete($params)
    {
        $params = array_merge([
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'csrfVerif' => true,
            'finder' => function ($collection) {},
            'onNotFound' => function () {
                return $this->please->serve('response')->jsonResponse([
                    'reload' => false,
                    'swal' => [
                        'icon' => 'error',
                        'title' => 'Donnée introuvable',
                    ]
                ], 404);
            },
            'onSuccess' => function ($document) {},
            'onError' => function ($message) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'reload' => true,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($this->please->serve('url')->getHome());
            },
            'onComplete' => null  // <<- 23.11.23
        ], $params);

        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid(true)) {
                $this->log->csrf('delete', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            $this->is = 'D';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('delete', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            $coll_name = $this->_getCollectionName($params);

            $document = $params['finder'](collection($coll_name));

            if (!$document) {
                $this->log->docNotFound('delete', $params);
                if (is_callable($params['onComplete'])) {  // <<- 23.11.23
                    return $params['onComplete']();
                }
                return $params['onNotFound']();
            }
            //
            if ((isset($document['type']) && $document['type'] !== 'log') || isset($document['roles'])) {
                ////// stand by //////$this->log->->put('danger', 'Suppression', $document);
            }
            //
            if (!isset($document[0])) {
                $document = [$document];
            }
            foreach ($document as $doc) {
                collection($coll_name)->delete(attr($doc, 'id'));
                if ($coll_name == 'file') {
                    $this->please->serve('file')->deleteFromFolder($doc['meta']);
                }
            }
            //
            $this->_deleteCache($document);
            //
            $this->log->writeDB('success', 'delete', [
                'params' => $params,
                'document' => $document,
                'message' => 'document supprimé avec succès'
            ], true);
            //
            if (is_callable($params['onComplete'])) {  // <<- 23.11.23
                return $params['onComplete']();
            }
            return $params['onSuccess']($document, $_POST);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'delete');
        }
    }

    public function upsert($params)
    {
        $params = array_merge([
            'dd' => null,
            'collection' => null,
            'ifNotXHR' => null,
            'isGranted' => null,
            'isValid' => false,
            'csrfVerif' => true,
            'refKeys' => [],
            'sanitizeRequest' => false,
            'sanitizeData' => false,
            'reservedProps' => [],
            'sanitizer' => function ($posted, $document, $collection, $params) {},
            'isBadRequest' => function () {
                return false;
            },
            'onBadRequest' => function ($posted, $document, $collection, $params) {},
            'sanitizer' => function () {},
            'onSuccess' => function ($document) {},
            'onError' => function ($message) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'reload' => true,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($this->please->serve('url')->getHome());
            }
        ], $params);


        try {

            if ($params['csrfVerif'] === true && $this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('upsert', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            $this->is = 'UorI';

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('upsert', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('hook')->hookFallbacks('otherwise')($this->please->serve('crud')->_getRequestAll());
            }

            $coll_name = $this->_getCollectionName($params);
            $collection = collection($coll_name);

            $keysReferences = $params['refKeys'];

            //$all = $_POST; //$all = $this->_getRequestAll();
            $all = $this->_getRequestAll();

            //$sanitized = $params['sanitizer']($all, $collection, $this) ?? $all;
            $sanitized = $params['sanitizeData'] === false
                ? ($params['sanitizer']($all, $collection, $this) ?? [])
                : array_merge($all, $params['sanitizer']($all, $collection, $this) ?? $all); // <<- 30.07.22

            if ($params['isBadRequest']($all, $collection, $this) === true) {
                return $params['onBadRequest']();
            } // <<- 21.04.22

            if ($params['sanitizeData'] === true) {
                $data = $this->_sanitizeData($coll_name, $sanitized, $params);
            }
            $data = $this->_sanitizeDataAnyway($coll_name, $data ?? [], $params); // <<- 10.08.22
            $data = array_merge($data ?? [], $sanitized ?? []); // <<- 22.07.21
            $data = $this->_sanitizeDataAnyway($coll_name, $data, $params); // <<- 12.08.22

            $data = $this->_sanitizeProps($data); // <<- 16.12.23
            $data = $this->dismissReservedProps($data, $params);

            if (isset($params['dd'])) { // <<- 05.07.23
                dd('dump and die :: upsert', $data);
            }

            $document = collection($coll_name)->upsert(
                $keysReferences,
                array_merge($data, ['coll_name' => $coll_name])
            );

            return $params['onSuccess']($document);
        } catch (\Exception $e) {
            $this->log->exception($e, 'upsert');
            return $params['onError']($e->getMessage());
        }
    }

    public function checkDataValidity($params, callable $onValid, $document = null, $action)
    {
        if (!$this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN')) {
            return $onValid($params); // always valid if this is API
        }
        if ($params['isValid'] === true) {
            return $onValid($params); // inutile d'aller plus loin
        }

        $valid_props_serv = $this->please->serve('valid_props');
        $validationFailed = false;
        $errors = [];
        $validProps = [];
        $excludedProps = $valid_props_serv->getExcludedColumns();
        if (method_exists(\App\Hook\PropsRegisterHook::class, 'propsRegisterHook') && $this->please->prevContainer->has('props_register_hook')) {
            $coll_name = $this->please->serve('string')->getSlug($params['collection'] ?? 'user', '_');
            $validProps = $this->please->prevContainer->get('props_register_hook')->propsRegisterHook()[$coll_name] ?? [];
        }
        $posted = $this->_getRequestAll();

        // 1. Vérifier TOUS les champs postés avant même de vérifier les validateurs
        foreach ($posted as $fieldName => $value) {
            // Ignorer les champs spéciaux (commençant par _)
            if ($fieldName[0] === '_') {
                continue;
            }

            // Ignorer les champs de soumission de formulaire
            if (in_array($fieldName, ['submit', 'btn_submit', 'action'])) {
                continue;
            }

            // Vérifier si le champ n'est ni valide ni exclu
            if (!in_array($fieldName, $validProps) && !in_array($fieldName, $excludedProps)) {
                $errors[$fieldName] = "Le champ '$fieldName' n'est pas autorisé.";
                $validationFailed = true;
            }
        }

        // 2. Ensuite vérifier les validateurs personnalisés
        if (isset($params['validator']) && is_array($params['validator'])) {
            foreach ($params['validator'] as $fieldName => $fieldCallable) {
                // Sauter si le champ a déjà une erreur de non-autorisation
                if (isset($errors[$fieldName])) {
                    continue;
                }

                $result = $fieldCallable($posted, $document, $params, $this);
                if (is_string($result)) {
                    $errors[$fieldName] = $result;
                    $validationFailed = true;
                }
            }
        }

        if ($validationFailed === true) {
            $this->log->writeDB('warning', $action, [
                'params' => $params,
                'message' => 'validation de données échouée',
                'invalid_fields' => array_keys($errors)
            ]);

            if (isset($params['onInvalid']) && is_callable($params['onInvalid'])) {
                return $params['onInvalid']($errors);
            } elseif (isset($params['formView'])) {
                return $params['formView']($errors);
            }
        }

        return $onValid($params);
    }

    private function _deleteCache($data): void
    {
        $req = $this->please->getRequestStackRequest();

        $id = $data['id'] ?? null;
        $coll_name = $data['coll_name'] ?? null;

        // lets delete cached href
        if (in_array($coll_name, ['page', 'menu'])) {
            qB()->rawQuery('TRUNCATE TABLE href;', [], [$coll_name, 'href']);
        }

        // menu cache
        if (in_array($coll_name, ['menu', 'page']) || (isset($data['in_menu']) && in_array($data['in_menu'], ['on', 'yes']))) {
            $dirServ = $this->please->serve('dir');
            $files = $dirServ->listFiles('var/cache/swagg');
            if ($files) {
                foreach ($files as $filename) {
                    if (strpos($filename, 'sys-nav') !== false) {
                        unlink($dirServ->dirPath("var/cache/swagg/$filename"));
                    }
                }
            }
        }

        $this->please->unsetStorage($coll_name);

        // lets update user for cache purpose
        if ($user_id = attr($this->please->serve('security')->getCurrentUser(), 'id')) {
            collection('users')->updateById(
                $user_id,
                ['updated_at' => (new \DateTime)->format('Y-m-d H:i:s')]
            );
        }

        // lets basic update _touch_ids
        $touch_ids = $this->please->getInput('_touch_ids', []);
        foreach ($touch_ids as $coll_name => $id) {
            collection($coll_name)->updateById(
                $id,
                ['updated_at' => (new \DateTime)->format('Y-m-d H:i:s')]
            );
        }
    }

    private function _otherwise($params)
    {
        if (is_array($params['isGranted']) && isset($params['isGranted']['otherwise']) && is_callable($params['isGranted']['otherwise'])) {
            return $params['isGranted']['otherwise'];
        }
        return false;
    }

    private function _getRequestAll()
    {
        $all = $this->please->getRequest()->request->all();
        return $all;
        dd($all);
        try {
            // Lorsque les données ont été posté à l'API
            $request = new Request([], $_POST, [], [], [], [], null);
            $all = $request->request->all()['all'] ?: $all;
        } catch (\Exception $e) {
            //
        }
        return is_string($all) ? json_decode($all, true) : $all;
        // return $_POST;
    }

    private function _setHookSanitizer()
    {
        $fileSystem = new Filesystem();
        //if ($fileSystem->exists($this->please->serve('dir')->getProjectDir() . '/src/Service/HookService.php')) {
        if (method_exists(\App\Hook\SanitizerHook::class, 'sanitizerHook') && $this->please->prevContainer->has('sanitizer_hook')) {
            $this->hookData = $this->please->prevContainer->get('sanitizer_hook')->sanitizerHook($this->please);
        }
        //}
    }

    private function _getHookSanitizer($typeOrRole, $data)
    {
        if (isset($this->hookData)) {
            $bArr = ['B', 'b', 'Bloggy', 'bloggy', 'Bloggies', 'bloggies'];
            $uArr = ['U', 'u', 'User', 'user'];

            foreach ($this->hookData as $collectionKey => $hookData) {
                if (in_array($collectionKey, $bArr)) {
                    if (is_string($typeOrRole)) {
                        $finalResult = [];

                        // 1. Cherche d'abord le callback spécifique (exact ou avec virgules)
                        $specificCallback = null;

                        // Correspondance exacte
                        $specificCallback = $hookData[$typeOrRole] ?? null;

                        // Si pas trouvé, recherche dans les clés avec virgules
                        if (!$specificCallback) {
                            foreach ($hookData as $key => $callback) {
                                if (strpos($key, ',') !== false) {
                                    $types = array_map('trim', explode(',', $key));
                                    if (in_array($typeOrRole, $types)) {
                                        $specificCallback = $callback;
                                        break;
                                    }
                                }
                            }
                        }

                        // 2. Récupère le callback __ANY__ s'il existe
                        $anyCallback = $hookData['__ANY__'] ?? null;

                        // 3. Exécute le callback spécifique s'il existe
                        if ($specificCallback) {
                            $result = $specificCallback($data, $_POST) ?? [];
                            if ($result instanceof Response) {
                                return $result;
                            }
                            $finalResult = array_merge($finalResult, $result);
                        }

                        // 4. Exécute __ANY__ s'il existe (TOUJOURS en complément)
                        if ($anyCallback) {
                            $result = $anyCallback($data, $_POST) ?? [];
                            if ($result instanceof Response) {
                                return $result;
                            }
                            $finalResult = array_merge($finalResult, $result);
                        }

                        // 5. Fusionne avec les données originales
                        if (!empty($finalResult)) {
                            return array_merge($data, $finalResult);
                        }
                    }
                } elseif (in_array($collectionKey, $uArr)) {
                    foreach (attr($data, 'roles', []) as $role) {
                        $finalResult = [];

                        // Même logique pour les rôles utilisateur
                        $specificCallback = $hookData[$role] ?? null;

                        if (!$specificCallback) {
                            foreach ($hookData as $key => $callback) {
                                if (strpos($key, ',') !== false) {
                                    $types = array_map('trim', explode(',', $key));
                                    if (in_array($role, $types)) {
                                        $specificCallback = $callback;
                                        break;
                                    }
                                }
                            }
                        }

                        $anyCallback = $hookData['__ANY__'] ?? null;

                        if ($specificCallback) {
                            $result = $specificCallback($data, $_POST) ?? [];
                            if ($result instanceof Response) {
                                return $result;
                            }
                            $finalResult = array_merge($finalResult, $result);
                        }

                        if ($anyCallback) {
                            $result = $anyCallback($data, $_POST) ?? [];
                            if ($result instanceof Response) {
                                return $result;
                            }
                            $finalResult = array_merge($finalResult, $result);
                        }

                        if (!empty($finalResult)) {
                            return array_merge($data, $finalResult);
                        }
                    }
                }
            }
        }
        return [];
    }

    private function _getHookSanitizer__old($typeOrRole, $data)
    {
        if (isset($this->hookData)) {
            $bArr = ['B', 'b', 'Bloggy', 'bloggy', 'Bloggies', 'bloggies'];
            $uArr = ['U', 'u', 'User', 'user'];

            foreach ($this->hookData as $collectionKey => $hookData) {
                if (in_array($collectionKey, $bArr)) {
                    if (is_string($typeOrRole)) {
                        $hookCallback = $hookData[$typeOrRole] ?? $hookData['__ANY__'] ?? null;
                        if ($hookCallback) {
                            $result = $hookCallback($data, $_POST) ?? [];
                            if ($result instanceof Response) {
                                return $result;
                            }
                            return array_merge($data, $result);
                        }
                    }
                } elseif (in_array($collectionKey, $uArr)) {
                    foreach (attr($data, 'roles', []) as $role) {
                        $hookCallback = $hookData[$role] ?? $hookData['__ANY__'] ?? null;
                        if ($hookCallback) {
                            $result = $hookCallback($data, $_POST) ?? [];
                            if ($result instanceof Response) {
                                return $result;
                            }
                            return array_merge($data, $result);
                        }
                    }
                }
            }
        }
        return [];
    }

    private function _sanitizeRequest($collectionName, $document = null)
    {
        //$dbPrefix = $this->_getDBPrefix();
        $req = $this->please->getRequest()->request;

        foreach (
            in_array($collectionName, ['users', 'admin']) ? [
                'roles',
                'password',
                'old_password',
                'username',
                'username_slugged',
                'forgot_token',
                'mle',
                'lastname',
                'firstname',
                'telephone',
                'email',
                'activated',
                'user_id',
                'created_at',
                'updated_at'
            ] : [
                'title',
                'parent_id',
                'second_title',
                'description',
                'custom_href',
                'slug',
                'keywords',
                'name',
                'link_type',
                'acf_type',
                'layout',
                'layout_single',
                'in_menu',
                'published',
                'allow_comments',
                'extra_data',
                'user_id',
                'created_at',
                'updated_at'
            ] as $key
        ) {
            if (!isset($req->all()[$key])) {
                $req->set($key, $document[$key] ?? '');
                //$_POST[$key] = $document[$key] ?? '';
            }
        }
    }

    private function _sanitizeProps($data)
    {
        $allowed_props = array_filter(array_keys($data), function ($key) { // <<- 16.12.23
            return strpos($key, '_') !== 0 && !in_array($key, ['id', 'token', 'ident', 'psw', 'confirm_psw', 'new_psw', 'confirm_new_psw', 'undefined']);
        });
        return array_intersect_key($data, array_flip($allowed_props)); // <<- 16.12.23
    }

    private function _sanitizeData($collectionName, $s, $params)
    {
        return $s;
    }

    private function _sanitizeDataAnyway($collectionName, $s, $params)
    {
        $strServ = $this->please->serve('string');
        $dbPrefix = $this->_getDBPrefix();
        $p = $_POST;

        if ($params['sanitizeRequest'] === false) {
            $p = $s;
        }
        if (in_array($collectionName, ['users', 'admin', "$dbPrefix{users}", "$dbPrefix{admin}"])) {

            $username = $s['username'] ?? $p['username'] ?? 'utilisateur-' . rand(0, 9999);

            //$p['role'] = strtolower($s['role'] ?? 'guest' );
            //$p['roles'] = $s['roles'] ?? ['title' => 'Invité', 'slug' => 'guest', 'description' => "Aucun action sur le Back-office"];
            $p['roles'] = $s['roles'] ?? $p['roles'] ?? ['guest'];

            $p['password'] = $s['password'] ?? $p['password'] ?? sha1('000000');
            $s['password'] = $p['password'] ?: sha1('000000');

            //$p['old_password'] = $s['password'];
            $p['username'] = $s['username'] ?? $username;
            $p['username_slugged'] = $this->please->serve('string')->getSlug($p['username']);
            $p['forgot_token'] = $s['forgot_token'] ?? $p['forgot_token'] ?? null;
            // $p['mle'] = $s['mle'] ?? $p['mle'] ?? ('MAT' . substr(md5(substr(uniqid(''), 0, 20)), 0, 5));
            $p['mle'] = (function ($val) {
                return ($val !== null && $val !== '' && !in_array($val, ['INF', 'Infinity', 'NAN', 'NaN']) && !(is_float($val) && (is_infinite($val) || is_nan($val))))
                    ? $val
                    : 'MAT' . substr(md5(substr(uniqid(''), 0, 20)), 0, 5);
            })(($s['mle'] ?? $p['mle'] ?? null));
            $s['mle'] = $p['mle'] ?: substr(md5(substr(uniqid(''), 0, 20)), 0, 5);
            //$p['lastname'] = $s['lastname'] ?? $p['lastname'] ?? $username;
            //$p['firstname'] = $s['firstname'] ?? $p['firstname'] ?? $username;
            $p['telephone'] = $s['telephone'] ?? $p['telephone'] ?? '';
            $p['email'] = $s['email'] ?? $p['email'] ?? '';
            $p['activated'] = $s['activated'] ?? $p['activated'] ?? 'on';
            $s['activated'] = $p['activated'] ?: 'on';

            $p['created_at'] = $s['created_at'] ?? $p['created_at'] ?? (new \DateTime())->format("Y-m-d H:i:s");
            $s['created_at'] = $p['created_at'] ?: (new \DateTime())->format("Y-m-d H:i:s");

            $p['updated_at'] = $s['updated_at'] = (new \DateTime())->format("Y-m-d H:i:s");
            $s['updated_at'] = $p['updated_at'] ?: (new \DateTime())->format("Y-m-d H:i:s");

            $p['user_id'] = $s['user_id'] ?? $p['user_id'] ?? $this->please->getCurrentUser()['id'] ?? null;
        } else {

            $p['coll_name'] = $s['coll_name'] ?? $p['coll_name'] ?? $this->please->getRequest()->attributes->get('coll_name') ?? 'page';

            $title = $s['title'] ?? $p['title'] ?? ('titre-' . rand(0, 9999));

            $p['title'] = $title;

            // mendatory but visually optionnal
            $secT = $s['second_title'] ?? $p['second_title'] ?? $title;
            $p['second_title'] = $secT ?: $title;
            $s['second_title'] = $p['second_title'] ?: $title;

            // mendatory but visually optionnal
            $desc = $s['description'] ?? $p['description'] ?? $title;
            $p['description'] = $desc ?: $title;
            $s['description'] = $p['description'] ?: $title;

            // mendatory but visually optionnal
            $slug = $s['slug'] ?? $p['slug'] ?? $title;
            $p['slug'] = $slug ?: $title;
            $s['slug'] = $strServ->getSlug($p['slug'] ?: $title);
            if ($s['slug'] == $p['coll_name']) {
                $s['slug'] = $strServ->getSlug($title);
            } // <<- 15.02.24

            // mendatory but visually optionnal
            $name = $s['name'] ?? $p['name'] ?? $title;
            $p['name'] = $name ?: $title;
            $s['name'] = $p['name'] ?: $title;

            // mendatory but visually optionnal
            $keywords = $s['keywords'] ?? $p['keywords'] ?? $title;
            $p['keywords'] = $keywords ?: $title;
            $s['keywords'] = $strServ->getTag($p['keywords'] ?: $title);

            $p['published'] = $s['published'] = ($_POST['published'] ?? $s['published'] ?? $p['published'] ?? 'on'); // checkbox

            $p['created_at'] = $s['created_at'] ?? $p['created_at'] ?? (new \DateTime())->format("Y-m-d H:i:s");
            $s['created_at'] = $p['created_at'] ?: (new \DateTime())->format("Y-m-d H:i:s");

            $p['updated_at'] = $s['updated_at'] = (new \DateTime())->format("Y-m-d H:i:s");
            $s['updated_at'] = $p['updated_at'] ?: (new \DateTime())->format("Y-m-d H:i:s");

            $p['user_id'] = $s['user_id'] ?? $p['user_id'] ?? $this->please->getCurrentUser()['id'] ?? null;
        }
        return array_merge($p, $s);
    }

    private function _getDBPrefix()
    {
        return $this->please->serve('env')->getAppEnv('DATABASE_PREFIX');
    }

    private function _getCollectionName($params)
    {
        $collection = $params['collection'];
        return in_array($collection, ['user', 'users', 'admin']) ? 'users' : $collection;
        // return $this->_getDBPrefix() . $params['collection'];
    }

    private function dismissReservedProps($data, $params)
    {
        $props = $params['reservedProps'] ?? [];
        $reservedProps = array_merge(
            is_string($props) // Lorsque les données ont été posté à l'API
                ? [] : $props,
            ['criteria', 'props', 'stay_connected']
        );

        foreach ($data as $v) {
            foreach ($reservedProps as $word) {
                if (isset($data[$word])) {
                    unset($data[$word]);
                }
            }
        }
        return $data;
    }
}
