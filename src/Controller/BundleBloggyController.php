<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Controller;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use ScssPhp\ScssPhp\Compiler;

#[Route('_admin/bloggy/')]
class BundleBloggyController extends AbstractController
{
    public $perPage = 15;
    public $please;
    public $adminRoles;
    public $secur;
    public $common;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->please->serve('execute_before')->executeBefore();
        $this->secur = $this->please->serve('security');
        $this->adminRoles = ['admin', 'editor', 'moderator'];
    }

    #[Route('token', name: '_getToken')]
    public function _getToken()
    {
        $secu = $this->please->serve('security');
        return new JsonResponse([
            '_token' => $secu->getCurrentUser() ? $secu->getCsrfToken() : false
        ]);
    }

    #[Route('{type}/list', name: '_listBloggy')]
    public function _listBloggy($type)
    {
        return $this->please->serve('crud')->readList([
            'collection' => 'bloggies',
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'finderCriteria' => function ($collection) use ($type) {

                $common = $this->getCommon($type);
                $orderBy = explode('@', attr($common, 'acfChildren.readListOrderBy', 'created_at@desc'));

                if ($this->secur->userCan('edit', null, $strictRole = true)) {
                    if ($this->secur->userCanHandleAcf($type)) {
                        $criteria = [
                            ['type', '=', $type]
                        ];
                    } else {
                        $criteria = [
                            ['type', '=', $type],
                            ['user', '=', $this->please->getCurrentUser()['id']],
                        ];
                    }
                } else {
                    $criteria = [
                        ['type', '=', $type]
                    ];
                }

                $q = $this->please->getInput('q');

                if ($q) {
                    $words = explode(' ', $q);
                    $c = [];
                    foreach ($words as $i => $word) {
                        if (!empty($word)) {
                            $c[] = ['title', 'like', $word];
                            $c[] = 'or';
                            $c[] = ['id', 'like', $word];
                            if ($i < count($words) - 1) {
                                //$c[] = 'OR';
                            }
                        }
                    }
                    $criteria = array_merge($criteria, ['and' => $c]);
                }

                $col = trim($orderBy[0]);
                if ($col == 'rank') {
                    $col = 'cast(rank as int)';
                }
                return [
                    $criteria,
                    [$col => trim($orderBy[1])]
                ];
            },
            'perPage' => $this->perPage,
            'view' => function ($bloggies, $knpPaginator, $totalCount) use ($type) {

                $bloggies = $this->please->getRepo('bloggy')->fetchEager($bloggies);

                $url = $this->generateUrl('_createBloggy', ['type' => $type]);

                $common = $this->getCommon($type);

                $data = [
                    'is' => 'list',
                    'bloggies' => $bloggies,
                    'knpPaginator' => $knpPaginator,
                    'formAction' => $url,
                    'btnAdd' => $url,
                    'title' => !$common->isAcfChild
                        ? [
                            'page' => "Pages ($totalCount)",
                            'menu' => "Menus de navigation ($totalCount)",
                            'acf' => "Champs personnalisés ($totalCount)",
                            'role' => "Rôles ($totalCount)",
                            'log' => "Log ($totalCount)",
                        ][$type]
                        : attr($common->acfChildren, 'title') . " (" . $totalCount . ")",
                    'acfChildren' => $common->acfChildren,
                    'totalCount' => $totalCount
                ];

                return $this->please->serve('template')->getTpl($common->isAcfChild ? 'acf-children' : $type, compact('data'));
            }
        ]);
    }

    #[Route('{type}/create', name: '_createBloggy')]
    public function _createBloggy($type)
    {
        return $this->please->serve('crud')->create([
            'collection' => 'bloggies',
            'isGranted' => [
                'byUserRoles' => ['admin', 'editor'],
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'isValid' => true,
            'sanitizer' => function ($posted) use ($type) {
                return $this->please->mergeData($posted, [
                    'type' => $type
                ]);
            },
            'formView' => function () use ($type) {

                $common = $this->getCommon($type);
                $acfChildren = $common->acfChildren;
                //$fieldsList = $acfChildren['fieldsList'] ?? null;

                $data = [
                    'is' => 'create',
                    'formAction' => $this->generateUrl('_createBloggy', ['type' => $type]),
                    'layouts' => $this->please->serve('dir')->asOptions('theme/layouts'),
                    'cards' => $this->please->serve('dir')->asOptions('theme/cards'),
                    'pages' => $this->please->serve('dir')->buildTree($common->allPages, 'option'),
                    'pagesAsCheckboxes' => $this->please->serve('dir')->buildTree($common->allPages, 'checkbox'),
                    'navStructures' => $this->please->serve('dir')->asOptions('theme/navs'),
                    'title' => !$common->isAcfChild
                        ? [
                            'page' => "Créer une nouvelle page",
                            'menu' => "Créer un nouveau menu",
                            'acf' => "Créer des Champs Personnalisés",
                            'role' => "Ajouter un rôle",
                        ][$type]

                        : 'Créer ACF: ' . attr($common->acfChildren, 'title'),
                    'acfChildren' => $acfChildren
                ];

                return $this->please->serve('template')->getTpl($common->isAcfChild ? 'acf-children' : $type, compact('data'));
            },
            'onSuccess' => function ($bloggy) use ($type) {

                return new JsonResponse([
                    'gToast' => 'Données ajoutées.',
                    'redirect' => $this->generateUrl('_updateBloggy', ['type' => $type, 'id' => attr($bloggy, 'id')]),
                    'reloadAside' => ($type == 'acf')
                ]);
            }
        ]);
    }

    #[Route('{type}/{id}/update', name: '_updateBloggy')]
    public function _updateBloggy($type, $id)
    {
        $url = $this->generateUrl('_updateBloggy', ['type' => $type, 'id' => $id]);

        return $this->please->serve('crud')->update([
            'collection' => 'bloggies',
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'isValid' => true,
            'finder' => function ($collection) use ($type, $id) {
                $this->common = $this->getCommon($type);
                return $collection->findOneBy([
                    'id' => $id,
                    'type' => $type
                ]);
            },
            // 'sanitizer' => function($posted, $bloggy){

            //     // both checkboxes and radios need to be handled because when unchecked, they're not posted
            //     return [
            //         'allow_comments' => attr($posted, 'allow_comments') ?? ( $this->common->isAcf ? 'on' : 'off'),
            //         'published' => attr($posted, 'published') ?? ( $this->common->isAcf ? 'on' : 'off'),
            //         'in_menu' => attr($posted, 'in_menu') ?? ( $this->common->isAcf ? 'on' : 'off')
            //     ];
            // },
            'formView' => function ($bloggy) use ($type, $url, $id) {


                $data = [
                    'bloggy' => $bloggy,
                    'is' => 'update',
                    'formAction' => $url,
                    'layouts' => $this->please->serve('dir')->asOptions('theme/layouts'),
                    'cards' => $this->please->serve('dir')->asOptions('theme/cards'),
                    'pages' => $this->please->serve('dir')->buildTree($this->common->allPages, 'option'),
                    'pagesAsCheckboxes' => $this->please->serve('dir')->buildTree($this->common->allPages, 'checkbox'),
                    'navStructures' => $this->please->serve('dir')->asOptions('theme/navs'),
                    'title' => 'Modifier: ' . attr($bloggy, 'title'),
                    'acfChildren' => $this->common->acfChildren
                ];

                return $this->please->serve('template')->getTpl($this->common->isAcfChild ? 'acf-children' : $type, compact('data'));
            },
            'onSuccess' => function ($bloggy) use ($type, $url) {
                return new JsonResponse([
                    'gToast' => 'Données mises à jour.',
                    'redirect' => $url,
                    'reloadAside' => ($type == 'acf')
                ]);
            }
        ]);
    }

    #[Route('{id}/basic-update', name: '_basicUpdateBloggy')]
    public function _basicUpdateBloggy($id)
    {
        return $this->please->serve('crud')->basicUpdate([
            'collection' => 'bloggies',
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'isValid' => true,
            'finder' => function ($collection) use ($id) {
                return $collection->find($id);
            },
            // 'sanitizer' => function ($posted, $found) {
            //     return [
            //         'allow_comments' => attr($found, 'allow_comments', 'off'),
            //         'published' => attr($found, 'published', 'off'),
            //         'in_menu' => attr($found, 'in_menu', 'off')
            //     ];
            // },
            'onSuccess' => function ($bloggy) {

                // lets update sections
                $sections = $this->please->getInput('sections');

                if ($sections) {
                    $sections = json_decode($sections);
                    $dirServ = $this->please->serve('dir');

                    // lets delete "var/cache/prod" dir
                    $dirServ->delTree($dirServ->dirPath('var/cache/prod'));

                    foreach ($sections as $section) {
                        $file = $dirServ->getThemeDirAbsDirPath('sections') . '/' . $section->name . '.html.twig';
                        if (file_exists($file)) {
                            file_put_contents($file, $section->content);
                        }
                    }

                    // lets empty sections if html is empty
                    if (attr($bloggy, 'html') == '') {
                        $this->please->serve('crud')->basicUpdate([
                            'collection' => 'bloggies',
                            'finder' => function () use ($bloggy) {
                                return $bloggy;
                            },
                            'sanitizer' => function () {
                                return ['sections' => []];
                            }
                        ]);
                    }
                }

                return new JsonResponse([
                    'gToast' => 'Données mises à jour.',
                    'reload' => true,
                    'reloadAside' => (attr($bloggy, 'type') == 'acf')
                ]);
            }
        ]);
    }

    #[Route('{id}/delete', name: '_deleteBloggy')]
    public function _deleteBloggy($id)
    {
        return $this->please->serve('crud')->delete([
            'collection' => 'bloggies',
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'finder' => function ($collection) use ($id) {
                return $collection->find($id);
            },
            'onSuccess' => function ($bloggy) {
                return new JsonResponse([
                    'gToast' => 'Données définitivement supprimées.',
                    'reload' => true,
                    'reloadAside' => (attr($bloggy, 'type') == 'acf')
                ]);
            }
        ]);
    }

    private function getCommon($type)
    {
        return (object) [
            'allPages' => collection('*')
                ->where('published', '=', 'on')
                ->where('in_menu', '=', 'on')
                ->andWhere(function ($q) {
                    $q->where('type', '!=', 'acf')
                        ->where('link_type', '=', 'page')
                        ->orWhere('type', '=', 'page');
                })
                ->orderBy('created_at', 'DESC')
                ->limit(9999)
                ->fetch(),

            'isAcfChild' => !in_array($type, ['page', 'menu', 'acf', 'role', 'log']),

            'isAcf' => $type == 'acf',

            'acfChildren' => collection('acf')->findOneBy([
                'name' => $type
            ])
        ];
    }
}
