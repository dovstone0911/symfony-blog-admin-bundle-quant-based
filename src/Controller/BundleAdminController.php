<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

#[Route('_admin/')]
class BundleAdminController extends AbstractController
{
    public $perPage = 15;
    public $please;
    public $adminRoles;
    public $posted;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->please->serve('execute_before')->executeBefore();
        $this->adminRoles = ['admin', 'editor', 'moderator'];
    }

    #[Route('!dc', name: '_deleteCache', priority: 1)]
    public function _deleteTree()
    {
        $dir = $this->please->serve('dir');

        $trash = $dir->dirPath('_trash');
        $mynosql_cache = $dir->dirPath('vendor/dovstone/quant/cache');
        $symf_cache = $dir->dirPath('vendor/dovstone/symfony-blog-admin-bundle-quant-based/cache');
        $folders = [$trash, $mynosql_cache, $symf_cache];
        foreach ($folders as $path) {
            if (file_exists($path) && strpos('dev', $path) == false) {
                $dir->delTree($path);
            }
        }
        $content = '<pre><p>Folders deleted successfuly</p><p>' . date("d/m/Y H:i:s") . '</p>' . (function () use ($folders) {
            $v = '';
            foreach ($folders as $f) {
                $v .= $f . "<br/>";
            }
            return $v;
        })();
        return new Response($content, 200);
    }

    ################## B A S E ##################
    #[Route('', name: '_base')]
    public function _base()
    {
        return $this->redirectToRoute('_dashboard');
    }

    #[Route('dashboard', name: '_dashboard')]
    public function _dashboard()
    {
        return $this->please->serve('crud')->read([
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'finder' => function () {
                return true;
            },
            'onFound' => function () {
                return $this->please->serve('template')->getTpl('dashboard');
            }
        ]);
    }

    #[Route('settings', name: '_settings')]
    public function _settings()
    {
        return $this->please->serve('crud')->read([
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'finder' => function () {
                return true;
            },
            'onFound' => function () {
                return $this->please->serve('template')->getTpl('settings');
            }
        ]);
    }

    ################## IN - OUT ##################

    #[Route('auth', name: '_authAdmin')]
    public function _authAdmin()
    {
        return $this->please->serve('user')->login([
            'onAlreadyLoggedIn' => function () {
                /*$roles = attr($this->please->getCurrentUser(), '_roles', []);
                foreach ($roles as $role) {
                    if( !in_array($role, $this->adminRoles) ){
                        return $this->redirect( $this->please->serve('url')->getUrl() );
                    }
                }*/
                return $this->redirectToRoute('_dashboard');
            },
            'finder' => function ($posted, $collection) {

                $this->posted = $posted;

                //$r = $this->please->getInput('_role');
                $ident = attr($posted, 'ident');
                $psw = sha1(attr($posted, 'password'));

                return $collection
                    ->whereJsonContains('roles', $this->adminRoles)
                    ->where('password', '=', $psw)
                    ->andWhere(function ($q) use ($ident) {
                        $q->where('username', '=', $ident)
                            ->orWhere('email', '=', $ident);
                    })
                    ->first();
            },
            'formView' => function ($userWasNotFound) {
                $posted = $this->posted ?? null;
                return $this->please->serve('template')->getTpl('auth', compact('userWasNotFound', 'posted'));
            },
            'onSuccess' => function ($user) {
                $welcomeMessage = 'Heureux de vous revoir <br><b class="text-uppercase" style="font-weight:bold">' . attr($user, 'username') . '</b>';
                return $this->please->serve('template')->getTpl('auth', compact('welcomeMessage'));
            },
        ]);
    }

    #[Route('logout', name: '_logoutAdmin')]
    public function _logoutAdmin()
    {
        return $this->please->serve('user')->logout([
            'onAlreadyLoggedOut' => function () {
                return $this->redirectToRoute('_authAdmin');
            },
            'onSuccess' => function ($user) {
                return new JsonResponse([
                    'reloadTo' => '_admin/auth'
                ]);
            },
        ]);
    }

    ################## C R U D ##################
    #[Route('users/list', name: '_listUsers')]
    public function _listUsers()
    {
        return $this->please->serve('crud')->readList([
            'isGranted' => [
                'byUserRoles' => ['admin'],
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'collection' => 'users',
            'finderCriteria' => function ($collection) {
                $criteria = [];

                $q = $this->please->getInput('q');

                if ($q) {
                    $words = explode(' ', $q);
                    $c = [];
                    foreach ($words as $i => $word) {
                        if (!empty($word)) {
                            $c[] = ['id', 'like', $word];
                            $c[] = 'or';
                            $c[] = ['username', 'like', $word];
                            $c[] = 'or';
                            $c[] = ['firstname', 'like', $word];
                            $c[] = 'or';
                            $c[] = ['lastname', 'like', $word];
                            if ($i < count($words) - 1) {
                                //$c[] = 'OR';
                            }
                        }
                    }
                    $criteria = array_merge($criteria, ['and' => $c]);
                }

                return [
                    $criteria,
                    ['created_at' => 'desc']
                ];
            },
            'perPage' => $this->perPage,
            'view' => function ($users, $knpPaginator, $totalCount) {

                /*$users = $this->please->convertToKnpPaginatorBundle(
                    $this->please->getRepo('user')->fetchEager($users),
                    $this->perPage
                );*/

                $data = [
                    'is' => 'list',
                    'users' => $users,
                    'knpPaginator' => $knpPaginator,
                    'btnAdd' => $this->generateUrl('_createUser'),
                    'title' => "Utilisateurs ($totalCount)"
                ];

                return $this->please->serve('template')->getTpl('user', compact('data'));
            }
        ]);
    }

    #[Route('users/create', name: '_createUser')]
    public function _createUser()
    {
        return $this->please->serve('crud')->create([
            'isGranted' => [
                'byUserRoles' => ['admin'],
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'isValid' => true,
            'collection' => 'users',
            'sanitizer' => function ($posted) {
                return $this->please->mergeData($posted, [
                    'password' => sha1(attr($posted, 'password', '000000')),
                    'validated' => 'on',
                    'enabled' => 'on'
                ]);
            },
            'formView' => function () {

                $data = [
                    'is' => 'create',
                    'formAction' => $this->generateUrl('_createUser'),
                    'roles' => $this->getRoles(),
                    'title' => "Ajouter un nouveau compte utilisateur"
                ];

                return $this->please->serve('template')->getTpl('user', compact('data'));
            },
            'onSuccess' => function ($user) {
                $redirect = $this->generateUrl('_updateUser', ['id' => attr($user, 'id')]);
                return new JsonResponse([
                    'gToast' => 'Données ajoutées.',
                    'redirect' =>  $this->please->getInput('_redirect', $redirect)
                ]);
            }
        ]);
    }

    #[Route('users/{id}/update', name: '_updateUser')]
    public function _updateUser($id)
    {
        $url = $this->generateUrl('_updateUser', ['id' => $id]);

        return $this->please->serve('crud')->update([
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'isValid' => true,
            'collection' => 'users',
            'finder' => function ($collection) use ($id) {
                return $collection->find($id);
            },
            'sanitizer' => function ($posted, $user) {

                if (isset($posted['password'])) {
                    $password = sha1(attr($posted, 'password'));
                    return [
                        'password' => $password,
                        'old_password' => $password
                    ];
                }
            },
            'formView' => function ($user) use ($url, $id) {

                $data = [
                    'user' => $user,
                    'is' => 'update',
                    'formAction' => $url,
                    'roles' => $this->getRoles(),
                    'title' => 'Infos: ' . attr($user, 'username'),
                ];

                return $this->please->serve('template')->getTpl('user', compact('data'));
            },
            'onSuccess' => function ($user) use ($url) {
                return new JsonResponse([
                    'gToast' => 'Données mises à jour.',
                    'redirect' =>  $this->please->getInput('_redirect', $url)
                ]);
            }
        ]);
    }

    #[Route('users/{id}/basic-update', name: '_basicUpdateUser')]
    public function _basicUpdateUser($id)
    {
        return $this->please->serve('crud')->basicUpdate([
            'isGranted' => [
                'byUserRoles' => $this->adminRoles,
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'isValid' => true,
            'collection' => 'users',
            'finder' => function ($collection) use ($id) {
                return $collection->find($id);
            },
            'sanitizer' => function ($posted) {
                return $posted;
            },
            'onSuccess' => function ($user) {
                return new JsonResponse([
                    'gToast' => 'Données mises à jour.',
                    'reload' => true
                ]);
            }
        ]);
    }

    #[Route('users/{id}/delete', name: '_deleteUser')]
    public function _deleteUser($id)
    {
        return $this->please->serve('crud')->delete([
            'isGranted' => [
                'byUserRoles' => ['admin'],
                'otherwise' => function () {
                    return $this->redirectToRoute('_authAdmin');
                }
            ],
            'collection' => 'users',
            'finder' => function ($collection) use ($id) {
                return $collection->find($id);
            },
            'onSuccess' => function () {
                return new JsonResponse([
                    'gToast' => 'Données définitivement supprimées.',
                    'reload' => true
                ]);
            }
        ]);
    }

    private function getRoles()
    {
        return array_merge(
            collection('role')->fetch() ?: [],
            [
                ['title' => 'Administrateur', 'slug' => 'admin', 'description' => "Ajoute, Modifie, Supprime, Gère la visibilité d'un enregistrement"],
                ['title' => 'Rédacteur', 'slug' => 'editor', 'description' => "Ajoute, Modifie, Supprime ses enregistrements"],
                ['title' => 'Modérateur', 'slug' => 'moderator', 'description' => "Gère la visibilité d'un enregistrement"],
                ['title' => 'Invité', 'slug' => 'guest', 'description' => "Ajoute, Modifie, Supprime ses enregistrements"]
            ]
        );
    }
}
