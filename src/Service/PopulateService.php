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
    public $bRepo;
    public $uRepo;
    public $crud;
    public $sql;
    public $pop_columns;
    public $with;

    public function __construct(PleaseService $please, BloggyRepository $bRepo, UserRepository $uRepo)
    {
        $this->please = $please;
        $this->bRepo = $bRepo;
        $this->uRepo = $uRepo;
        $this->crud = $this->please->serve('crud');
        $this->cache = $this->please->serve('cache');
    }

    public function props(callable $then, array $item = [], array|null $params = []): array
    {
        if (!$item) {
            return $then($this, $item, $params);
        }

        $with = $params['with'] ?? [];
        $coll_name = $item['coll_name'] ?? 'users';
        // $cacheKey = $params['cacheKey'] ?? [];

        // $last_updated_at = $this->please->serve('cache')->getLastUpdatedAt($params);
        // return $this->cache->do(function () use ($then, $item, $with, $params) {

        $data_bank = $then($this, $item, $with, $params);
        $coll_name = attr($item, 'coll_name');

        $roles = attr($item, 'roles', []);
        $this->with = $with;

        if ($with && empty($roles)) {
            foreach ($with as $key) {
                $key = trim($key);
                if (array_key_exists($key, $data_bank)) {
                    $item[$key] = $data_bank[$key]();
                }
            }
        }

        if (!empty($roles) && $coll_name == 'users') {
            foreach ($data_bank as $k => $v) {
                $k = trim($k);
                if (in_array($k, ['users', 'user'])) {
                    $v = $v() ?? [];
                    $item = array_merge($item, $v);
                }
            }
        } elseif ($coll_name) {
            foreach ($data_bank as $k => $v) {
                $with = array_map('trim', explode(',', $k));
                if (in_array($coll_name, $with)) {
                    $v = $v() ?? [];
                    foreach ($v as $prop => $callback) {
                        $item[$prop] = is_callable($callback) ? $callback() : $callback;
                    }
                }
            }
        }
        return $item ?? [];

        // }, [$last_updated_at, $item, $with, $params, $cacheKey], "1 hour");
    }

    public function popKey($key)
    {
        return in_array($key, $this->with) || empty($this->with);
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
