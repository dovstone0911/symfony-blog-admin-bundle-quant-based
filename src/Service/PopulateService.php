<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use App\Repository\UserRepository;
use App\Repository\BloggyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class PopulateService extends AbstractController
{
    public \Dovstone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService $please;
    public \Dovstone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\CacheService $cache;
    public \Dovstone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\CRUDService $crud;
    public \App\Service\PopCollectionService $popColl;
    public \App\Repository\BloggyRepository $bRepo;
    public \App\Repository\UserRepository $uRepo;
    public array $_with;

    public function __construct(PleaseService $please, BloggyRepository $bRepo, UserRepository $uRepo)
    {
        $this->please = $please;
        $this->bRepo = $bRepo;
        $this->uRepo = $uRepo;
        $this->crud = $this->please->serve('crud');
        $this->cache = $this->please->serve('cache');
        $this->popColl = $this->please->serve('pop_collection');
    }

    /**
     * Called by \App\Twig\TwigExtension
     */
    public function with(array|null $items = [], array $params = [])
    {
        if (empty($items)) {
            return [];
        }

        // Détecter si c'est une structure paginée
        $isPaginated = isset($items['items']) && is_array($items['items']);

        // Extraire les items à traiter
        $data = $isPaginated ? $items['items'] : $items;

        // Gérer le cas d'un seul item
        $isSingleItem = !isset($data[0]) && !empty($data);
        $itemsToProcess = $isSingleItem ? [$data] : $data;

        // Traiter chaque item
        $processedItems = [];
        foreach ($itemsToProcess as $item) {
            $processedItems[] = array_merge($item, $this->popColl->with($item, $params, $this));
        }

        // Restaurer la structure
        if ($isSingleItem) {
            $processedItems = $processedItems[0] ?? [];
        }

        if ($isPaginated) {
            $items['items'] = $processedItems;
            return $items;
        }

        return $processedItems;
    }

    /**
     * Called by \App\Service\PopCollectionService
     */
    public function init(callable $then, array $item = [], array|null $params = []): array
    {
        if (!$item || empty($item['id']) || empty($item['coll_name'])) {
            return $then($item, $params, $this);
        }

        $with = $params['with'] ?? $params;
        $this->_with = $with;

        // $item = $this->cache->do(function () use ($then, $with, $item, $params) {
        // $item = Quant($item['coll_name'])->find($item['id']);
        $data_bank = $then($item, $params, $this);
        $coll_name = attr($item, 'coll_name');
        $roles = attr($item, 'roles', []);

        if (is_array($with)) {
            foreach ($with as $key) {
                $key = trim($key);
                if (array_key_exists($key, $data_bank)) {
                    $item[$key] = $data_bank[$key]();
                }
            }
        }
        if ($coll_name) {
            foreach ($data_bank as $k => $v) {
                $with = array_map('trim', explode(',', $k));
                if (in_array($coll_name, $with)) {
                    $v = $v() ?? [];
                    foreach ($v as $prop => $callback) {
                        $item[$prop] = is_callable($callback) ? $callback() : $callback;
                    }
                }
            }
        } else {
            $roles = array_merge($roles, ['users', 'user']);
            foreach ($roles as $role) {
                foreach ($data_bank as $k => $v) {
                    $with = array_map('trim', explode(',', $k));
                    if (in_array($role, $with)) {
                        $v = $v() ?? [];
                        foreach ($v as $prop => $callback) {
                            $item[$prop] = is_callable($callback) ? $callback() : $callback;
                        }
                    }
                }
            }
        }
        return $item;
        // }, [$item, quant_last_updated_at([$item['coll_name'] . '_id'], $item['id'])], "1 second");

        return $item;
    }

    /**
     * Called by \App\Service\popColl
     */
    public function load($key)
    {
        return in_array($key, $this->_with) || empty($this->_with);
    }

    public function injectFile($item, $prop = 'image_id')
    {
        return $this->please->serve('watch_upload')->bind($item, $prop);
    }

    public function findFileByID($id)
    {
        return collection('file')->find($id);
    }

    public function meta($item)
    {
        return [
            'meta' => attr($item, 'meta')
        ];
    }
}
