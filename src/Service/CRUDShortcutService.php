<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class CRUDShortcutService extends AbstractController
{
    private \DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService $please;
    public \DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\CRUDService $crud;
    private \DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\ResponseService $res;
    public \DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\LogService $log;
    public array $errors = [];
    public $defaults;
    private array $seo = [];

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->crud = $please->serve('crud');
        $this->res = $please->serve('response');
        $this->log = $please->serve('log');
    }

    public function createOrList($params, $common = [])
    {
        $this->please->serve('post')->void();

        $oParams = $params;
        $stack_req = $this->please->getRequestStackRequest();
        $req = $this->please->getRequest();
        $attr = $req->attributes;
        $coll_name = $this->_getCollectionName($attr->get('coll_name'));

        $action = $attr->get('action');
        $is_create = $attr->get('action') == 'create' || $req->isMethod('POST');

        $meth = $is_create ? 'create' : 'list';
        $params = isset($params[$meth]) && is_callable($params[$meth]) ? $params[$meth]($coll_name, collection($coll_name)) : [];

        $this->seo = [
            'coll_name' => $coll_name,
            'action' => $action,
        ];

        if (is_callable($defaults = $this->defaults)) {
            try {
                $params = array_merge($params, $defaults($coll_name, $action, null));
            } catch (\Exception $e) {
                // Quelque chose s'est mal passé depuis un controller
                $this->log->exception($e, 'createOrList');
            }
        }

        $params = array_merge([
            'dd' => $params['dd'] ?? null,
            'isGranted' => null,
            'validator' => [],
            'reservedProps' => [],
            'watchUploadKeys' => [],
            'docSEO' => [],
            'finderCriteria' => function ($coll_name, $is_users_collection) {
                return null;
            },
            'preData' => [],
            'limit' => $req->query->get('limit') ?? 30,
            'columns' => null,
            'swalMessages' => function ($coll_name) {
                return [
                    'inbox' => "Merci de nous avoir contacté !",
                    'newsletter' => "Vous recevrez désormais toutes nos meilleures offres.",
                ][$coll_name] ?? null;
            },
            'formRoute' => 'createOrList',
            'redirectRoute' => 'readOrUpdateOrDelete',
            'sanitizer' => function ($posted) {
                return [];
            },
            'beforeSend' => null,
            'view' => null,
            'formView' => null,
            'onSuccess' => function ($document, $coll_name) {},
            'onTouched' => function ($document, $posted, $coll_name) {},
        ], $params);

        $bRepo = $params['repos'][0] ?? null;
        $uRepo = $params['repos'][1] ?? null;

        $validator = $params['validator'];
        $reservedProps = $params['reservedProps'];
        $watchUploadKeys = $params['watchUploadKeys'];

        $docSEO = $params['docSEO'];
        $finderCriteria = $params['finderCriteria'];
        $preData = $params['preData'];
        $limit = $params['limit'];
        $view = $params['view'];
        $beforeSend = $params['beforeSend'];
        $formView = $params['formView'];
        $columns = $params['columns'];
        $sanitizer = $params['sanitizer'];
        $onSuccess = $params['onSuccess'];
        $onTouched = $params['onTouched'];
        $formRoute = $params['formRoute'];
        $redirectRoute = $params['redirectRoute'];
        $swalMessages = $params['swalMessages'];
        $dd = $params['dd'];

        $isGranted = $params['isGranted'] ?? $common['isGranted'] ?? [];
        $layoutsDir = $params['layoutsDir'] ?? $common['layoutsDir'] ?? null;
        if ($is_create) {

            return $this->crud->create([
                'dd' => $dd,
                'collection' => $coll_name,
                'isGranted' => $isGranted,
                'validator' => $validator,
                'onInvalid' => function ($errors) {
                    return $this->res->jsonResponse(['errors' => $errors ?? $this->errors], 422);
                },
                'beforeSend' => $beforeSend,
                'formView' => function () use ($layoutsDir, $formRoute, $docSEO, $coll_name, $action, $preData, $formView) {
                    if (!$docSEO) {
                        return $this->_redir();
                    }
                    $preData = is_array($preData) ? $preData : [];
                    $data = array_merge([
                        'title' => $docSEO['title'],
                        'title_raw' => $docSEO['title'],
                        'description' => $docSEO['description'] ?? $docSEO['title'],
                        'action' => 'create',
                        'coll_name' => $coll_name,
                        'form_route' => $this->generateUrl($formRoute, ['coll_name' => $coll_name/*, 'action' => $action*/])
                    ], $docSEO ?? [], $preData);

                    //$post_service = $this->please->serve('post');
                    //$curr_post = $post_service->getPost();
                    //$this->please->serve('post')->merge(array_merge($curr_post, $data));
                    $this->please->serve('post')->merge($data);

                    if (is_callable($formView)) {
                        $data = $formView($data, $coll_name) ?? $data;
                        $this->please->serve('post')->merge($data);
                    }

                    $view = $docSEO['tpl'] ?? $data['tpl'] ?? $coll_name;
                    $final_view = strpos($view, '/') !== false ? $view : "$layoutsDir/layouts/$view";
                    return $this->res->renderTpl($final_view);
                },
                'reservedProps' => $reservedProps,
                'sanitizeRequest' => false,
                'sanitizer' => function ($posted) use ($coll_name, $sanitizer) {
                    $data = in_array($coll_name, ['user', 'users', 'admin']) ? [
                        'password' => sha1('0000'),
                        'lastname' => $posted['lastname'] ?? $posted['username'] ?? '',
                        'firstname' => $posted['firstname'] ?? $posted['username'] ?? '',
                        'enabled' => 'on',
                    ] : ['slug' => $coll_name];
                    return array_merge($data, $sanitizer($posted));
                },
                'onBadRequest' => function () {
                    return $this->res->jsonResponse([
                        'swal' => [
                            'icon' => 'error',
                            'title' => 'Impossible de traiter votre requête.'
                        ]
                    ], 400);
                },
                'onSuccess' => function ($docs) use ($stack_req, $watchUploadKeys, $redirectRoute, $reservedProps, $coll_name, $action, $swalMessages, $onSuccess, $onTouched) {
                    if (!isset($docs[0])) {
                        $docs = [$docs];
                    }
                    $all = $this->please->getRequest()->request->all();
                    foreach ($docs as $doc) {
                        $doc['coll_name'] = $coll_name;
                        $onSuccess($doc, $all);
                        $onTouched($doc, $all, $coll_name);
                        $redir = $this->_redirOnSuccess(
                            $redirectRoute,
                            $coll_name,
                            $doc['id'],
                            $this->_redirect($stack_req, $redirectRoute, $doc, $action)
                        );
                    }
                    return $this->_watchUpload(
                        $doc,
                        $watchUploadKeys,
                        $reservedProps,
                        [],
                        function ($response) use ($redir, $doc, $coll_name, $swalMessages, $action, $all) {
                            if ($response instanceof JsonResponse) {
                                $resContent = json_decode($response->getContent(), true);
                                if (isset($resContent['swal']['icon']) && $resContent['swal']['icon'] == 'error') {
                                    return $response;
                                }
                            }
                            $this->_onSuccessHook($coll_name, $doc, [$doc['id']], $action, $all);
                            return $this->res->jsonResponse([
                                'redirect' => $redir,
                                'document' => $doc,
                                'swal' => [
                                    'icon' => 'success',
                                    'title' => $swalMessages($coll_name, $doc) ?? 'Données enregistrées'
                                ]
                            ]);
                        }
                    );
                }
            ]);
        }

        $realCollName = isset($params['collName']) && is_callable($params['collName']) ? $params['collName']($coll_name) : $coll_name;

        return $this->crud->readList([
            'dd' => $oParams['dd'] ?? null,
            'collection' => $realCollName,
            'isGranted' => $isGranted,
            'finderCriteria' => function () use ($finderCriteria, $coll_name) {
                $cri = $finderCriteria($coll_name, $coll_name == 'users');
                return [
                    array_merge(attr($cri, '0', [])),
                    attr($cri, '1', ['created_at' => 'desc'])
                ];
            },
            'limit' => $limit,
            'view' => function ($items, $pagination, $count) use ($layoutsDir, $docSEO, $uRepo, $bRepo, $limit, $columns, $realCollName, $coll_name, $view) {
                if (!$docSEO) {
                    return $this->_redir();
                }

                $prop_columns = $columns[$realCollName] ?? null;
                $repo = in_array($realCollName, ['user', 'users', 'admin']) ? $uRepo : $bRepo;

                $data = array_merge([
                    'action' => 'list',
                    'coll_name' => $realCollName,
                    'items' => !empty($prop_columns) ? $repo->pop($items, $prop_columns) : $items,
                    'count' => $count,
                    'limit' => $limit,
                    'pagination' => $pagination
                ], $docSEO ?? []);

                $data['title'] = $data['title'] . (isset($data['hide_total']) ? '' : " (" . ($data['total'] ?? $count) . ")");
                $data['description'] = $data['description'] ?? $data['title'];

                $this->please->serve('post')->merge($data);

                if (is_callable($view)) {
                    $data = $view($data) ?? $data;
                    $this->please->serve('post')->merge($data);
                }

                $view = $docSEO['tpl'] ?? $data['tpl'] ?? $coll_name;
                $final_view = strpos($view, '/') !== false ? $view : "$layoutsDir/layouts/$view";
                return $this->res->renderTpl($final_view);
            }
        ]);
    }

    public function readOrUpdateOrDelete($params)
    {
        $this->please->serve('post')->void();

        $oParams = $params;
        $stack_req = $this->please->getRequestStackRequest();
        $req = $this->please->getRequest();
        $attr = $req->attributes;

        $coll_name = $this->_getCollectionName($attr->get('coll_name'));
        $id = $attr->get('id');
        $_method = $req->get('_method') ?? $req->getMethod();
        $action = $attr->get('action') ?? $_method;

        if ($_method == 'DELETE' || $action == 'delete') {
            $meth = 'delete';
            $req->setMethod('DELETE');
        } else {
            $meth = 'readOrUpdate';
        }

        $params = array_merge([
            'dd' => $oParams['dd'] ?? null,
            'isGranted' => null,
            'validator' => [],
            'reservedProps' => [],
            'watchUploadKeys' => [],
            'docSEO' => [],
            'preData' => function ($doc = null) {},
            'columns' => null,
            'swalMessages' => function ($coll_name) {
                return [
                    'inbox' => "Merci de nous avoir contacté !",
                    'newsletter' => "Vous recevrez désormais toutes nos meilleures offres.",
                ][$coll_name] ?? null;
            },
            'formRoute' => 'readOrUpdateOrDelete',
            'redirectRoute' => 'readOrUpdateOrDelete',
            'sanitizer' => function ($posted, $document = null) {},
            'beforeSend' => null,
            'onSuccess' => function ($document, $posted) {},
            'onTouched' => function ($document, $posted, $coll_name) {},
        ], isset($params[$meth]) && is_callable($params[$meth]) ? $params[$meth]($coll_name, $id) : []);

        $this->seo = [
            'coll_name' => $coll_name,
            'action' => $action,
        ];

        if (is_callable($defaults = $this->defaults)) {
            try {
                // $params = array_merge($params, $defaults($coll_name, $meth == 'readOrUpdate' ? $action : 'delete', $id, $meth == 'readOrUpdate'));
                $params = array_merge($params, $defaults($coll_name, $action, $id));
            } catch (\Exception $e) {
                // Quelque chose s'est mal passé depuis un controller
                $this->log->exception($e, 'readOrUpdateOrDelete');
            }
        }

        $isGranted = $params['isGranted'];
        $validator = $params['validator'];
        $reservedProps = $params['reservedProps'];
        $watchUploadKeys = $params['watchUploadKeys'];

        $docSEO = $params['docSEO'];
        $preData = $params['preData'];
        $columns = $params['columns'];
        $sanitizer = $params['sanitizer'];
        $beforeSend = $params['beforeSend'];
        $formView = $params['formView'] ?? null;
        $onSuccess = $params['onSuccess'];
        $onTouched = $params['onTouched'];
        $formRoute = $params['formRoute'];
        $redirectRoute = $params['redirectRoute'];
        $swalMessages = $params['swalMessages'];
        $dd = $params['dd'];

        $layoutsDir = $params['layoutsDir'] ?? null;
        $bRepo = $params['repos'][0] ?? null;
        $uRepo = $params['repos'][1] ?? null;

        $this->seo = [
            'coll_name' => $coll_name,
            'action' => $action,
        ];

        switch (strtoupper($req->getMethod())) {
            /* private cases (POST || PUT || PATCH) */
            case 'POST':
            case 'PUT':
            case 'PATCH':
                $this->errors = [];
                return $this->crud->update([
                    'dd' => $dd,
                    'collection' => $coll_name,
                    'isGranted' => $isGranted,
                    'validator' => $meth == 'delete' ? [] : $validator,
                    'finder' => function () use ($coll_name, $id) {
                        // lets check if this is in fact ACF
                        // $is_acf = collection('acf')->countBy([['coll_name', '=', 'acf'], ['name', '=', $coll_name]]);
                        // $collection = $is_acf > 0 ? collection('acf') : collection($coll_name);
                        return collection($coll_name)->find($id);
                    },
                    'onNotFound' => function () {
                        return $this->_onNotFound();
                    },
                    'onInvalid' => function ($errors) {
                        return $this->res->jsonResponse(['errors' => $errors ?? $this->errors], 422);
                    },
                    'beforeSend' => $beforeSend,
                    'formView' => function ($doc) use ($coll_name, $action, $formRoute, $docSEO, $preData, $uRepo, $columns, $bRepo, $formView) {
                        if (!$doc && !$docSEO) {
                            return $this->_redir();
                        }
                        $prop_columns = $columns[$coll_name] ?? null;
                        $t = $doc['title'] ?? $doc['username'];
                        $preData = is_array($preData) ? $preData : [];
                        $repo = in_array($coll_name, ['user', 'users', 'admin']) ? $uRepo : $bRepo;
                        $doc['parent'] = collection('page')->find($doc['parent_id'] ?? 0) ?? null;
                        $doc = $this->please->pop($doc, ['image']);
                        $data = array_merge([
                            'title' => in_array($coll_name, ['user', 'users', 'admin']) ? "Modifier les infos de \"$t\"" : "Modifier \"$t\"",
                            'action' => 'update',
                            'coll_name' => $coll_name,
                            'form_route' => $this->generateUrl($formRoute, ['coll_name' => $coll_name, 'id' => $doc['id'], 'action' => $action]),
                            'document' => !empty($prop_columns) ? $repo->pop($doc, $prop_columns) : $doc
                        ], $docSEO ?? [], $preData);

                        $this->please->serve('post')->merge($data);

                        if (is_callable($formView)) {
                            $data = $formView($data, $coll_name) ?? $data;
                            $this->please->serve('post')->merge($data);
                        }

                        $view = $docSEO['tpl'] ?? $data['tpl'] ?? $coll_name;

                        return $this->res->renderTpl($view);
                    },
                    'reservedProps' => $reservedProps,
                    'sanitizeRequest' => false,
                    'sanitizer' => function ($posted, $document) use ($sanitizer) {
                        return $sanitizer($posted, $document);
                    },
                    'onSuccess' => function ($docs) use ($stack_req, $coll_name, $action, $swalMessages, $redirectRoute, $watchUploadKeys, $reservedProps, $onSuccess, $onTouched) {
                        $all = $this->please->getRequest()->request->all();
                        if (!isset($docs[0])) {
                            $docs = [$docs];
                        }
                        foreach ($docs as $doc) {
                            $doc['coll_name'] = $coll_name;
                            $onSuccess($doc, $all);
                            $onTouched($doc, $all, $coll_name);
                            $redir = $this->_redirOnSuccess(
                                $redirectRoute,
                                $coll_name,
                                $doc['id'],
                                $this->_redirect($stack_req, $redirectRoute, $doc, $action)
                            );
                        }
                        return $this->_watchUpload(
                            $doc,
                            $watchUploadKeys,
                            $reservedProps,
                            [],
                            function ($response) use ($redir, $doc, $coll_name, $swalMessages, $action, $all) {
                                if ($response instanceof JsonResponse) {
                                    $resContent = json_decode($response->getContent(), true);
                                    if (isset($resContent['swal']['icon']) && $resContent['swal']['icon'] == 'error') {
                                        return $response;
                                    }
                                }
                                $this->_onSuccessHook($coll_name, $doc, [$doc['id']], $action, $all);
                                return $this->res->jsonResponse([
                                    'redirect' => $redir,
                                    'document' => $doc,
                                    'swal' => [
                                        'icon' => 'success',
                                        'title' => $swalMessages($coll_name, $doc) ?? 'Données enregistrées'
                                    ]
                                ]);
                            }
                        );
                    }
                ]);
                /* public cases ( DELETE || READ ) */
            case 'DELETE':
                return $this->crud->delete([
                    'dd' => $dd,
                    'collection' => $coll_name,
                    'ifNotXHR' => function () {
                        return $this->redirect($this->please->getReferer());
                    },
                    'isGranted' => $isGranted,
                    'finder' => function () use ($coll_name, $id) {
                        // lets check if this is in fact ACF
                        // $is_acf = collection('acf')->countBy([['coll_name', '=', 'acf'], ['name', '=', $coll_name]]);
                        // $collection = $is_acf > 0 ? collection('acf') : collection($coll_name);
                        return collection($coll_name)->find($id);
                    },
                    'onNotFound' => function () {
                        return $this->_onNotFound();
                    },
                    'onSuccess' => function ($docs) use ($stack_req, $coll_name, $action, $onSuccess, $onTouched, $redirectRoute) {
                        if (!isset($docs[0])) {
                            $docs = [$docs];
                        }
                        $all = $this->please->getRequest()->request->all();
                        foreach ($docs as $doc) {
                            $doc['coll_name'] = $coll_name;
                            $onSuccess($doc, $all);
                            $onTouched($doc, $all, $coll_name);
                            $redir = $this->_redirOnSuccess(
                                $redirectRoute,
                                $coll_name,
                                $doc['id'] ?? null,
                                $this->_redirect($stack_req, $redirectRoute, $doc, $action)
                            );
                            $this->_onSuccessHook($coll_name, $doc, [$doc['id']], $action, $all);
                        }
                        return $this->res->jsonResponse([
                            'redirect' => $redir,
                            'swal' => [
                                'icon' => 'success',
                                'title' => 'Données supprimées'
                            ]
                        ]);
                    }
                ]);
            default:
                return $this->crud->read([
                    'dd' => $dd,
                    'collection' => $coll_name,
                    'isGranted' => $isGranted,
                    'finder' => function () use ($coll_name, $id) {
                        // lets check if this is in fact ACF
                        // $is_acf = collection('acf')->countBy([['coll_name', '=', 'acf'], ['name', '=', $coll_name]]);
                        // $collection = $is_acf > 0 ? collection('acf') : collection($coll_name);
                        return collection($coll_name)->find($id);
                    },
                    'onNotFound' => function () {
                        return $this->_onNotFound();
                    },
                    'onFound' => function ($doc) use ($docSEO, $formRoute, $layoutsDir, $coll_name, $action, $preData, $uRepo, $bRepo, $columns, $formView) {

                        if (!$doc && !$docSEO) {
                            return $this->_redir();
                        }

                        $t = $doc['title'] ?? $doc['username'];
                        $title = ($action != 'update') ? $t : (in_array($coll_name, ['user', 'users', 'admin']) ? "Modifier les infos de \"$t\"" : "Modifier \"$t\"");
                        $prop_columns = $columns[$coll_name] ?? null;
                        $repo = in_array($coll_name, ['user', 'users', 'admin']) ? $uRepo : $bRepo;
                        $preData = is_array($preData) ? $preData : [];
                        $doc['parent'] = collection('page')->find($doc['parent_id'] ?? 0) ?? null;
                        $doc = $this->please->pop($doc, ['image']);
                        $data = array_merge([
                            'title' => $title,
                            'title_raw' => $t,
                            'description' => $docSEO['description'] ?? $title,
                            'action' => $action,
                            'coll_name' => $coll_name,
                            'form_route' => $this->generateUrl($formRoute, ['coll_name' => $coll_name, 'id' => $doc['id'], 'action' => $action]),
                            'document' => !empty($prop_columns) ? $repo->pop($doc, $prop_columns) : $doc
                        ], $docSEO ?? [], $preData);

                        //$post_service = $this->please->serve('post');
                        //$curr_post = $post_service->getPost();
                        //$this->please->serve('post')->merge(array_merge($curr_post, $data));
                        $this->please->serve('post')->merge($data);

                        if (is_callable($formView)) {
                            $data = $formView($data, $coll_name) ?? $data;
                            $this->please->serve('post')->merge($data);
                        }

                        $view = $docSEO['tpl'] ?? $data['tpl'] ?? $coll_name;
                        $final_view = strpos($view, '/') !== false ? $view : "$layoutsDir/layouts/$view";
                        return $this->res->renderTpl($final_view);
                    }
                ]);
        }
    }

    public function validate($rules)
    {
        if (is_callable($rules)) {
            return $rules();
        }

        $this->errors = [];
        $validators = [];

        foreach ($rules as $field => $ruleString) {
            $rulesArray = [];
            if (is_callable($ruleString)) {
                $validators[$field] = $ruleString;
            } else {
                foreach (explode('|', $ruleString) as $rule) {
                    if (strpos($rule, ':') !== false) {
                        [$key, $value] = explode(':', $rule, 2);
                    } else {
                        $key = $rule;
                        $value = 'true';
                    }
                    $rulesArray[$key] = $value;
                }

                $errorKey = $rulesArray['err_field'] ?? $field;

                $validators[$errorKey] = function ($posted, $doc) use ($field, $rulesArray) {
                    if ($doc) {
                        $posted = array_merge($doc, $posted);
                    }
                    $val = attr($posted, $field);
                    $requiredMsg = $rulesArray['alert'] ?? 'Champs obligatoire';
                    $requiredMsg_anyway = $rulesArray['alert'] ?? null;

                    if (($rulesArray['required'] ?? 'false') === 'true' && !$val && $val !== 0) {
                        return $this->errors[$field] = $requiredMsg;
                    }
                    if (!empty($rulesArray['type'])) {
                        $ruleType = $rulesArray['type'];
                        switch ($ruleType) {
                            case 'string':
                                if (!is_string($val)) {
                                    return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être une chaîne de caractères";
                                }
                                break;
                            case 'int':
                                if (!is_int($val)) {
                                    return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être un entier";
                                }
                                break;
                            case 'numeric':
                                if (!is_numeric($val)) {
                                    return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être un numérique";
                                }
                                break;
                            case 'email':
                                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                                    return $this->errors[$field] = $requiredMsg_anyway ?? "Adresse email invalide";
                                }
                                break;
                            case 'date':
                            case 'time':
                                $lbl = $ruleType == 'date' ? 'date' : 'heure';
                                $date = strtotime($val);
                                if (!$date) {
                                    return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être une $lbl valide";
                                }

                                // Fonction pour extraire le nom du champ et le libellé
                                $parseRule = function ($rule) {
                                    if (strpos($rule, '(') !== false) {
                                        preg_match('/^([^(]+)\(([^)]+)\)$/', $rule, $matches);
                                        return [
                                            'field' => trim($matches[1]),
                                            'label' => $matches[2] ?? trim($matches[1])
                                        ];
                                    }
                                    return [
                                        'field' => $rule,
                                        'label' => $rule
                                    ];
                                };

                                // Pour la règle 'after'
                                if (isset($rulesArray['after'])) {
                                    $parsed = $parseRule($rulesArray['after']);
                                    $compareField = $parsed['field'];
                                    $compareLabel = $parsed['label'];
                                    $compareValue = attr($posted, $compareField);

                                    if ($ruleType == 'date' && $compareValue) {
                                        $compareTimestamp = strtotime($compareValue);
                                        if ($date <= $compareTimestamp) {
                                            return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être après $compareLabel";
                                        }
                                    } elseif ($ruleType == 'time' && $compareValue) {
                                        $dateField = str_replace('time', 'date', $field);
                                        $relatedDate = attr($posted, $dateField);
                                        $compareDate = attr($posted, str_replace('time', 'date', $compareField));

                                        if ($relatedDate && $compareDate) {
                                            $thisDateTime = strtotime($relatedDate . ' ' . $val);
                                            $compareDateTime = strtotime($compareDate . ' ' . $compareValue);

                                            if ($thisDateTime <= $compareDateTime) {
                                                return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être après $compareLabel";
                                            }
                                        }
                                    }
                                }

                                // Pour la règle 'after_or_equal'
                                if (isset($rulesArray['after_or_equal'])) {
                                    $parsed = $parseRule($rulesArray['after_or_equal']);
                                    $compareField = $parsed['field'];
                                    $compareLabel = $parsed['label'];
                                    $compareTo = attr($posted, $compareField);

                                    if ($ruleType == 'date' && $compareTo) {
                                        $compareTimestamp = strtotime($compareTo);
                                        if ($date < $compareTimestamp) {
                                            return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être postérieure ou égale à $compareLabel";
                                        }
                                    } elseif ($ruleType == 'time' && $compareTo) {
                                        $dateField = str_replace('time', 'date', $field);
                                        $relatedDate = attr($posted, $dateField);
                                        $compareDate = attr($posted, str_replace('time', 'date', $compareField));

                                        if ($relatedDate && $compareDate) {
                                            $thisDateTime = strtotime($relatedDate . ' ' . $val);
                                            $compareDateTime = strtotime($compareDate . ' ' . $compareTo);

                                            if ($thisDateTime < $compareDateTime) {
                                                return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être postérieure ou égale à $compareLabel";
                                            }
                                        }
                                    }
                                }

                                // Pour la règle 'before'
                                if (isset($rulesArray['before'])) {
                                    $parsed = $parseRule($rulesArray['before']);
                                    $compareField = $parsed['field'];
                                    $compareLabel = $parsed['label'];
                                    $compareValue = attr($posted, $compareField);

                                    if ($ruleType == 'date' && $compareValue) {
                                        $compareTimestamp = strtotime($compareValue);
                                        if ($date >= $compareTimestamp) {
                                            return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être avant $compareLabel";
                                        }
                                    } elseif ($ruleType == 'time' && $compareValue) {
                                        $dateField = str_replace('time', 'date', $field);
                                        $relatedDate = attr($posted, $dateField);
                                        $compareDate = attr($posted, str_replace('time', 'date', $compareField));

                                        if ($relatedDate && $compareDate) {
                                            $thisDateTime = strtotime($relatedDate . ' ' . $val);
                                            $compareDateTime = strtotime($compareDate . ' ' . $compareValue);

                                            if ($thisDateTime >= $compareDateTime) {
                                                return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être avant $compareLabel";
                                            }
                                        }
                                    }
                                }

                                // Pour la règle 'before_or_equal'
                                if (isset($rulesArray['before_or_equal'])) {
                                    $parsed = $parseRule($rulesArray['before_or_equal']);
                                    $compareField = $parsed['field'];
                                    $compareLabel = $parsed['label'];
                                    $compareTo = attr($posted, $compareField);

                                    if ($ruleType == 'date' && $compareTo) {
                                        $compareTimestamp = strtotime($compareTo);
                                        if ($date > $compareTimestamp) {
                                            return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être antérieure ou égale à $compareLabel";
                                        }
                                    } elseif ($ruleType == 'time' && $compareTo) {
                                        $dateField = str_replace('time', 'date', $field);
                                        $relatedDate = attr($posted, $dateField);
                                        $compareDate = attr($posted, str_replace('time', 'date', $compareField));

                                        if ($relatedDate && $compareDate) {
                                            $thisDateTime = strtotime($relatedDate . ' ' . $val);
                                            $compareDateTime = strtotime($compareDate . ' ' . $compareTo);

                                            if ($thisDateTime > $compareDateTime) {
                                                return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être antérieure ou égale à $compareLabel";
                                            }
                                        }
                                    }
                                }
                                break;
                        }
                    }
                    if (isset($rulesArray['min']) && (!is_numeric($val) || (is_numeric($val) && $val < (int)$rulesArray['min']))) {
                        return $this->errors[$field] = $requiredMsg_anyway ?? "Ne doit pas être inférieure à {$rulesArray['min']}";
                    }
                    if (isset($rulesArray['max']) && (!is_numeric($val) || (is_numeric($val) && $val > (int)$rulesArray['max']))) {
                        return $this->errors[$field] = $requiredMsg_anyway ?? "Ne doit pas être supérieure à {$rulesArray['max']}";
                    }
                    if (isset($rulesArray['minchar']) && strlen((string)$val) < $rulesArray['minchar']) {
                        return $this->errors[$field] = $requiredMsg_anyway ?? "Doit contenir au moins {$rulesArray['minchar']} caractères";
                    }
                    if (isset($rulesArray['maxchar']) && strlen((string)$val) > $rulesArray['maxchar']) {
                        return $this->errors[$field] = $requiredMsg_anyway ?? "Ne doit pas dépasser {$rulesArray['maxchar']} caractères";
                    }
                    if (isset($rulesArray['length']) && strlen((string)$val) != (int)$rulesArray['length']) {
                        return $this->errors[$field] = $requiredMsg_anyway ?? "Doit contenir exactement {$rulesArray['length']} chiffres";
                    }
                    if (isset($rulesArray['diff']) && $val == $rulesArray['diff']) {
                        return $this->errors[$field] = $requiredMsg ?? "Donnée invalide";
                    }
                    if (isset($rulesArray['equal']) && $val != $rulesArray['equal']) {
                        return $this->errors[$field] = $requiredMsg ?? "Donnée invalide";
                    }
                    if (isset($rulesArray['upper']) && (!is_numeric($val) || (is_numeric($val) && $val <= $rulesArray['upper']))) {
                        return $this->errors[$field] = $requiredMsg_anyway ?? "Doit être supérieur à {$rulesArray['upper']}";
                    }
                    if (isset($rulesArray['lower']) && $val >= $rulesArray['lower']) {
                        return $this->errors[$field] = $requiredMsg ?? "Donnée invalide";
                    }
                    if (!empty($rulesArray['in'])) {
                        $options = explode(',', $rulesArray['in']);
                        if (sizeof($options) > 1) {
                            if (!in_array($val, $options)) {
                                return $this->errors[$field] = $requiredMsg_anyway ?? "Valeurs possibles : " . implode(', ', $options);
                            }
                        } else {
                            $options = explode('::', $rulesArray['in']);
                            $table = $options[0] ?? null;
                            $ignoreId = $options[1] ?? null;
                            if (isset($options[1])) {
                                if (!collection($options[0])->findOneBy([$options[1] => $val])) {
                                    return $this->errors[$field] = $requiredMsg ?? "Donnée invalide";
                                }
                            } else {
                                if (!collection($rulesArray['in'])->find($val)) {
                                    return $this->errors[$field] = $requiredMsg ?? "Donnée invalide";
                                }
                            }
                        }
                    }
                    if (!empty($rulesArray['unique_in'])) {
                        $options = explode(',', $rulesArray['unique_in']);
                        $table = $options[0];
                        $ignoreId = $options[1] ?? null;

                        if (!$ignoreId && collection($table)->findOneBy([$field => $val])) {
                            return $this->errors[$field] = "Cette valeur existe déjà";
                        }
                    }
                };
            }
        }

        return $validators;
    }

    public function preData(string $coll_name)
    {
        $req = $this->please->getRequest();
        $id = $req->attributes->get('id');

        $dir = $this->please->serve('dir');

        $common = [
            'all_pages' => $this->please->fetchEager(
                collection('page')
                    ->where('published', '=', 'on')
                    ->where('in_menu', '=', 'on')
                    ->when($id, function ($q) use ($id) {
                        $q->where('id', '!=', $id);
                    })
                    ->orderBy('created_at', 'DESC')
                    ->fetchAll()
            ),
        ];

        $r = [
            'meta_data' => array_merge([
                'layouts' => $dir->asOptions('theme/layouts'),
                'custom_layouts' => $dir->asOptions('theme/_back/layouts/custom'),
                'cards' => $dir->asOptions('theme/cards'),
                'navStructures' => $dir->asOptions('theme/navs'),
                'pages' => $dir->buildTree($common['all_pages'], 'option'),
                'pagesAsCheckboxes' => $dir->buildTree($common['all_pages'], 'checkbox'),
                'acf' => (function () use ($coll_name) {
                    $id = $this->please->getRequest()->get('_route_params')['id'] ?? null;
                    return collection('acf')->findOneBy(['name' => $coll_name])
                        ?: collection($coll_name)->find($id);
                })()
            ], $common)
        ];

        if (!in_array($coll_name, ['page', 'menu', 'users', 'file', 'inbox', 'acf'])) {
            $tpl = attr($r, 'meta_data.acf.back_layout', 'acf-child');
            $r = array_merge($r, ['tpl' => $tpl]);
        }

        return $r;
    }

    public function seo(string $title, ?array $add = null, bool $hideTotal = false): ?array
    {
        $coll_name = $this->seo['coll_name'];
        $action = $this->seo['action'];

        $config = [
            'list' => ['title' => $title, 'hide_total' => $hideTotal]
        ];
        $addLink = $add[1] ?? $this->generateUrl('createOrListBack', ['coll_name' => $coll_name, 'action' => 'create']);

        if (isset($add[0])) {
            $config['list']['add'] = [$add[0], $addLink];
            $config['create'] = ['title' => "Ajouter $title", "tpl" => $coll_name];
            $config['update'] = ['title' => "Modifier $title", "tpl" => $coll_name];
        }

        return $config[$action] ?? null;
    }

    private function _redir()
    {
        return $this->please->redirectToHome();
    }

    private function _redirOnSuccess($redirectRoute, $coll_name, $doc_id, $default)
    {
        $_coll_name = $this->please->getInput('_coll_name');
        $after_insert = $this->please->getInput('_after_insert');
        switch ($after_insert) {
            case 'new-insert':
                $url = preg_replace('/(.*)([a-zA-z0-9]{8})/m', "$1", $this->please->getReferer());
                $redir = $url . (strpos($url, "create") == false ? "create" : "");
                break;
            case 'same-insert':
                $redir = $this->generateUrl($redirectRoute, ['coll_name' => $coll_name, 'id' => $doc_id]);
                break;
            default:
                $redir = $default;
                break;
        }
        if ($_coll_name) {
            $redir = str_ireplace("/$coll_name/", "/$_coll_name/", $redir);
        }
        return $redir;
    }

    private function _watchUpload(
        $document,
        $watchUploadKeys,
        $reservedProps,
        $textOverlay = [],
        callable $callback
    ) {
        if ($watchUploadKeys) {
            $request = $this->please->getRequest();

            // Créer un tableau combiné pour 'all'
            // $allData = array_merge(
            //     $request->all(),
            //     [
            //         'document' => $document,
            //         'reservedProps' => $reservedProps,
            //         'textOverlay' => $textOverlay
            //     ]
            // );

            $user_id = $this->please->serve('security')->getCurrentUser()['id'] ?? null;
            $request->request->add([
                'document' => $document,
                'user_id' => $user_id,
                'reservedProps' => $reservedProps,
                'textOverlay' => $textOverlay
            ]);

            $result = $this->please->serve('watch_upload')->watch($request);
            return $callback($result);
        }
        return $callback([]);
    }

    private function _onSuccessHook(string $coll_name, $document, array $doc_ids, string $action, array $posted = [])
    {
        if ((new Filesystem())->exists($this->please->serve('dir')->getProjectDir() . '/src/Hook/OnSuccessHook.php')) {
            if (method_exists(\App\Hook\OnSuccessHook::class, 'onSuccessHook') && $this->please->prevContainer->has('on_success_hook')) {
                $r = $this->please->prevContainer->get('on_success_hook')->onSuccessHook($coll_name, $document, $doc_ids, $action, $posted);
            }
        }
    }

    private function _forwardToAssetsCDN($document, $watchUploadKeys, $reservedProps)
    {
        try {
            $req = $this->please->getRequestStackRequest();

            $postData = [];

            // Ajout de document
            $postData['document'] = json_encode($document);

            // Ajout des arrays - ATTENTION: sérialiser les arrays complexes
            $postData['keys'] = json_encode($watchUploadKeys);
            $postData['_televerse_keys'] = json_encode($req->get('_televerse_keys'));
            $postData['reservedProps'] = json_encode($reservedProps);
            $postData['folder_id'] = json_encode($req->get('folder_id'));

            // Récupérer les fichiers depuis Symfony Request
            $files = $this->please->getRequest()->files->all();
            $hasFiles = !empty($files);

            $ch = curl_init();
            $CDN_ASSETS = $this->please->serve('env')->getAppEnv('CDN_ASSETS');

            curl_setopt($ch, CURLOPT_URL, $CDN_ASSETS);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            if ($hasFiles) {
                // Ajout des fichiers avec CURLFile
                foreach ($files as $key => $file) {
                    if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $file->isValid()) {
                        $postData[$key] = new \CURLFile(
                            $file->getPathname(),
                            $file->getMimeType(),
                            $file->getClientOriginalName()
                        );
                    }
                }

                // Avec fichiers: passer le tableau directement (multipart/form-data automatique)
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            } else {
                // Sans fichiers: encoder en query string
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            return $response;
        } catch (\Exception $e) {
            $this->log->exception($e, '_forwardToAssetsCDN');
        }
    }

    private function _getCollectionName($coll_name)
    {
        return in_array($coll_name, ['user', 'users', 'admin']) ? 'users' : $coll_name;
    }

    private function _redirect($stack_req, $redirectRoute, $doc, $action)
    {
        $req = $this->please->getRequest();
        $attr = $req->attributes;

        $next_action = $this->please->getInput('_next_action');
        $next = $stack_req->get('_redirect');
        $next = in_array($next, ['false', false]) ? false : $next;
        $doc_id = $doc['id'] ?? $attr->get('id');

        return $stack_req->get('_redirect') ?? (
            $action == 'update'
            ? $this->please->serve('url')->getCurrentUrl()
            : $this->generateUrl(
                $redirectRoute,
                array_merge(
                    ['coll_name' => $doc['coll_name'] ?? 'user', 'id' => $doc_id],
                    $next_action ? ['action' => $next_action] : []
                )
            )
        );
    }

    private function _onNotFound()
    {
        if ($this->please->isXHR()) {
            return $this->please->serve('response')->jsonResponse([
                'reload' => true,
                'swal' => [
                    'type' => 'error',
                    'title' => 'Ressource introuvable'
                ]
            ]);
        }
        return $this->redirect($this->please->serve('url')->getHome());
    }
}
