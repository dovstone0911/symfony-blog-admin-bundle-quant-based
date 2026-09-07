<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class ResponseService extends AbstractController
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function getRedirectUrl()
    {
        $r = $this->redirectTypes();
        return $r->r1 ?? $r->r2 ?? $r->r3 ?? $r->r4 ?? $this->please->serve('url')->getUrl();
    }

    public function jsonResponse($response, $status = 200)
    {
        // Gestion des redirections
        $r = $this->redirectTypes();
        $r = $r->r1 || $r->r2 || $r->r3 || $r->r4 || $r->r5 || $r->r6
            ? [
                $r->r1 || $r->r2 || $r->r3
                    ? 'redirectDeep'
                    : 'redirect' => $r->r1 ?? $r->r2 ?? $r->r3 ?? $r->r4 ?? $r->r5 ?? $r->r6
            ]
            : [];

        // Gestion du reload
        $rel = $this->reloadTypes();
        $rel = $rel->rel1 || $rel->rel2 ? ['reload' => $rel->rel1 ?? $rel->rel2] : [];

        // Fusionner les réponses
        $data = array_merge($response, $r, $rel);

        // Supprimer "redirect" ou "redirectDeep" si "reload" est présent
        if (isset($data['reload'])) {
            unset($data['redirect'], $data['redirectDeep']);
        }

        if (input('_swal') == "false") {
            unset($data['swal']);
        }

        // si $data['redirect'] ou $data['redirectDeep'] est en base_64, décoder
        // $data = $this->please->serve('mix')->autoDecodeBase64Array($data);

        // remplacer les valeur de $data['reload'], $data['redirect'] et $data['redirectDeep']
        $mix = $this->please->serve('mix');
        foreach (['reload', 'redirect', 'redirectDeep'] as $key) {
            $data[$key] = $mix->decodeIfBase64Encoded($data[$key] ?? null);
        }

        if (is_null($data['reload'])) {
            unset($data['reload']);
        }
        if (is_null($data['redirectDeep'])) {
            unset($data['redirectDeep']);
        }

        $_after_insert = input('_after_insert');

        switch ($_after_insert) {
            case 'edit-page':
                if (!empty($data['document'])) {
                    $data['redirect'] = $this->please->_generateUrl('readOrUpdateOrDeleteBack', [
                        'coll_name' => $data['document']['coll_name'] ?? null,
                        'id' => $data['document']['id'] ?? null,
                        'action' => 'update'
                    ]);
                }
                break;
            case 'same-page':
                $data['redirect'] = $this->please->getReferer();
                break;
            case 'new-insert':
                if (!empty($data['document'])) {
                    $data['redirect'] = $this->please->_generateUrl('createOrListBack', [
                        'coll_name' => $data['document']['coll_name'] ?? null,
                    ]);
                }
                break;
            case 'duplicata':
                if (!empty($data['document'])) {
                    $new_doc = collection($data['document']['coll_name'])->insert(
                        array_merge($data['document'], [
                            'title' => $data['document']['title'] . ' Copie'
                        ])
                    );
                    $data['redirect'] = $this->please->_generateUrl('createOrUpdateOrDelete', [
                        'coll_name' => $new_doc['coll_name'],
                        'id' => $new_doc['id'],
                        'action' => 'update'
                    ]);
                }
                break;

            default:
                # code...
                break;
        }

        return new JsonResponse($data, $status);
    }

    public function handleResponse($view, $responseFormat = true)
    {
        $tplServ = $this->please->serve('template');
        $view = $tplServ->sanitizeView($view);

        /**/
        $post = $this->please->serve('post')->get();
        // $this->please->serve('post')->set($post);
        /* 040326 - dont remember why i did this 🤔 so i commented */

        $parsedView = $tplServ->getParsedView($view);

        if ($this->please->isXHR() && input('_ajaxify')) {
            $response = $responseFormat ? new JsonResponse($parsedView) : $parsedView;
            $response->headers->set('Symfony-Debug-Toolbar-Replace', 1);
            return (object) [
                'response' => $response,
                'parsedView' => $parsedView
            ];
        }

        return (object) [
            'response' => $responseFormat ? new Response($view) : $view,
            'parsedView' => $parsedView
        ];
    }

    public function renderTpl($viewPath, $viewParameters = [], $ttl = "10 minutes")
    {
        $responseData = $this->handleResponse(
            $this->renderView("$viewPath.html.twig", $viewParameters)
        );
        if ($ttl && is_string($ttl)) {
            $this->please->serve('cache')->pushNavigationData($responseData, $ttl);
        }
        return $responseData->response;
    }

    private function redirectTypes()
    {
        $redirDeep = input('_redirect_deep');
        $redir = input('_redirect');

        return (object) [
            'r1' => $redirDeep ?: null,
            'r2' => input('_redirect_deep') ?: null,
            'r3' => $this->please->getRefererParam('_redirect_deep') ?: null,

            'r4' => $redir ?: null,
            'r5' => $redir ?: null,
            'r6' => $this->please->getRefererParam('_redirect') ?: null,
        ];
    }

    private function reloadTypes()
    {
        return (object) [
            'rel1' => input('_reload'),
            'rel2' => input('_reload'),
        ];
    }
}
