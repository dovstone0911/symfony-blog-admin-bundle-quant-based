<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Controller;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use ScssPhp\ScssPhp\Compiler;

#[Route('_admin/settings/')]
class BundleSettingsController extends AbstractController
{
    public $please;
    public $crud;
    public $res;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->please->serve('execute_before')->executeBefore();
        $this->crud = $this->please->serve('crud');
        $this->res = $this->please->serve('response');
    }

    #[Route('reset-visits-counter', name: '_resetVisitsCounters')]
    public function _resetVisitsCounters()
    {
        return $this->crud->read([
            'collection' => 'bloggies',
            'isGranted' => ['byUserRoles' => ['admin']],
            'finder' => function () {
                return true;
            },
            'onFound' => function () {
                unlink($this->please->serve('dir')->dirPath('public/counter.txt'));
                return $this->res->jsonResponse([
                    'success' => true,
                    'msg' => 'Données mises à jour.'
                ]);
            }
        ]);
    }

    #[Route('reset-cache', name: '_resetCache')]
    public function _resetCache()
    {
        return $this->crud->read([
            'collection' => 'bloggies',
            'isGranted' => ['byUserRoles' => ['admin']],
            'finder' => function () {
                return true;
            },
            'onFound' => function () {
                $dir = $this->please->serve('dir');
                $dir->delTree($dir->dirPath('var/cache'));
                return $this->res->jsonResponse([
                    'success' => true,
                    'msg' => 'Données mises à jour.'
                ]);
            }
        ]);
    }

    #[Route('ttl', name: '_setTTL')]
    public function _setTTL()
    {
        $this->crud->delete([
            'collection' => 'bloggies',
            'isGranted' => ['byUserRoles' => ['admin']],
            'finder' => function ($collection) {
                return $collection->findBy(['type' => 'setting--ttl']);
            }
        ]);

        return $this->crud->basicCreate([
            'collection' => 'bloggies',
            'isGranted' => ['byUserRoles' => ['admin']],
            'sanitizer' => function () {
                return ['type' => 'setting--ttl'];
            },
            'onSuccess' => function () {
                return $this->res->jsonResponse([
                    'success' => true,
                    'msg' => 'Données mises à jour.'
                ]);
            }
        ]);
    }
}
