<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PostService extends AbstractController
{
    public $please;
    public $bRepo;
    public $bCollection;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function set($info)
    {
        $title = $info['title'] ?? '';
        $info = array_merge([
            'title' => 'Mon Titre',
            'full_title' => ($info['full_title'] ?? $title) . ' • ' . $this->please->serve('env')->getAppEnv('APP_NAME'),
            'description' => $info['description'] ?? $title,
            'href' => $this->please->serve('url')->getCurrentUrl(),
            'layout' =>  "",
        ], $info);

        $this->please->setGlobal(['post' => $info]);

        return $info;
    }

    public function get(): array
    {
        return $this->please->getGlobal('post') ?? [];
    }

    public function getTitle()
    {
        $post = $this->get();
        return (
            $post['second_title'] ?? $post['title'] ?? ''
        ) . ' • ' . $this->please->serve('env')->getAppEnv('APP_NAME');
    }

    public function getProp($post, $prop, $limit = -1, $offset = null)
    {
        $created_at = attr($post, 'created_at');
        $date = is_string($created_at) ? (new \DateTime($created_at))->format('Y-m-d H:i:s') : $created_at;
        $coll_name = $post['coll_name'];

        switch ($prop) {
            case 'prev':
                return $coll_name ? collection($coll_name)
                    ->where('published', '=', 'on')
                    ->where('created_at', '<', $date)
                    ->orderBy('created_at', 'DESC')
                    ->first() : null;

            case 'next':
                return $coll_name ? collection($coll_name)
                    ->where('published', '=', 'on')
                    ->where('created_at', '>', $date)
                    ->orderBy('created_at', 'ASC')
                    ->first() : null;

            case 'related':
                if (isset($post['parent_id']) || isset($post['parent']['id'])) {
                    return $coll_name ? collection($coll_name)
                        ->where('parent_id', '=', $post['parent_id'] ?? $post['parent']['id'])
                        ->where('id', '!=', $post['id'])
                        ->limit($limit)
                        ->fetch() : null;
                }
                return [];

            case 'children':
                return $coll_name ? collection($coll_name)
                    ->where('parent_id', '=', attr($post, 'id'))
                    ->where('published', '=', 'on')
                    ->where('coll_name', '!=', 'acf')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->offset($offset)
                    ->fetch() : null;

            case 'childrenInMenu':
                return $coll_name ? collection($coll_name)
                    ->where('parent_id', '=', attr($post, 'id'))
                    ->where('published', '=', 'on')
                    ->where('coll_name', '!=', 'acf')
                    ->whereIn('in_menu', ['on', 'yes'])
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->offset($offset)
                    ->fetch() : null;

            case 'pages':
                return $coll_name ? collection($coll_name)
                    ->where('parent_id', '=', attr($post, 'id'))
                    ->where('published', '=', 'on')
                    ->where('coll_name', '!=', 'acf')
                    ->where('link_type', '=', 'page')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->offset($offset)
                    ->fetch() : null;

            case 'articles':
                return $coll_name ? collection($coll_name)
                    ->where('parent_id', '=', attr($post, 'id'))
                    ->where('published', '=', 'on')
                    ->where('coll_name', '!=', 'acf')
                    ->where('link_type', '=', 'article')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->offset($offset)
                    ->fetch() : null;

            default:
                return null;
        }
    }

    public function isActive($post): string
    {
        $urlServ = $this->please->serve('url');
        $href = is_string($post) ? $post : $urlServ->getHref($post);
        return $href == trim($urlServ->getCurrentUrlQueryLess(), '/');
    }

    public function routeActiveClass($routeName, string $activeClass = 'active'): string
    {
        $currRoute = $this->please->getRequest()->get('_route');
        //$currRouteParams = $this->please->getRequest()->get('_route_params');
        if (is_string($routeName)) {
            return $currRoute == $routeName ? $activeClass : '';
        } elseif (is_array($routeName) && in_array($currRoute, $routeName)) {
            return $activeClass;
        }
        return '';
    }

    public function push(array $data = [])
    {
        $post = $this->get();
        $newData = array_merge($post, $data);
        return $this->set($newData);
    }

    public function merge(array ...$data)
    {
        $post = $this->get();

        $allData = array_merge([$post], $data);
        $newData = array_merge(...$allData);

        return $this->set($newData);
    }

    public function void()
    {
        $this->please->serve('post')->set([]);
    }
}
