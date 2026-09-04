<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Controller;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;

#[Route('/')]
class WebsiteController extends AbstractController
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->please->serve('execute_before')->executeBefore();
    }

    #[Route('', name: '_getHomePage')]
    public function _getHomePage()
    {
        $this->please->serve('run_hook')->runHook();
        //
        $page = $this->please->getRepo('bloggy')->fetchEager(
            collection('page')
                ->whereIn('slug', ['home', '/', 'accueil'])
                ->where('published', '=', 'on')
                ->findOne()
        );
        if ($page) {
            return $this->render200($page);
        }
        return $this->render404();
    }

    #[Route('{slug}', requirements: ['slug' => '[a-z-/0-9]+'], name: '_getPage')]
    public function _getPage($slug)
    {
        // $slug_ = $slug;
        //
        $slug = explode('/', trim($slug, '/'));
        $slug = end($slug);

        $pages = $this->please->getRepo('bloggy')->fetchEager(
            collection('page')
                ->where(function ($q) use ($slug) {
                    $q->where('slug', '=', $slug)
                        ->orWhere('custom_href', '=', $slug);
                })
                ->where('published', '=', 'on')
                ->limit(9999)
                ->fetchAll()
        );

        if ($pages) {
            return $this->browsePostsFound($pages, $slug);
        }
        return $this->render404();
    }

    #[Route(
        '{parent_slug}/{slug}-{id}.html',
        requirements: ['parent_slug' => '[a-z-/0-9]+', 'slug' => '[a-z-/0-9]+', 'id' => '[0-9]+'],
        name: '_getArticleWithParent'
    )]
    public function _getArticleWithParent($parent_slug, $slug, $id)
    {
        $slug = explode('/', $slug);
        $slug = end($slug);

        $posts = $this->please->getRepo('bloggy')->fetchEager(
            collection($coll_name)
                ->where('id', '=', $id)
                ->where('slug', '=', $slug)
                ->where('published', '=', 'on')
                ->limit(9999)
                ->fetchAll()
        );

        if (!$posts) {
            return $this->render404();
        }

        return $this->browsePostsFound($posts, $parent_slug . '/' . $slug);
    }

    #[Route(
        '{slug}-{id}.html',
        requirements: ['slug' => '[a-z-/0-9]+', 'id' => '[0-9]+'],
        name: '_getArticleWithOutParent'
    )]
    public function _getArticleWithOutParent($slug, $id)
    {
        $posts = $this->please->getRepo('bloggy')->fetchEager(
            collection($coll_name)
                ->where('id', '=', $id)
                ->where('slug', '=', $slug)
                ->where('published', '=', 'on')
                ->limit(9999)
                ->fetchAll()
        );

        if (!$posts) {
            return $this->render404();
        }

        return $this->browsePostsFound($posts, $slug);
    }

    private function browsePostsFound($posts, $slug)
    {
        $urlServ = $this->please->serve('url');
        $navServ = $this->please->serve('nav');
        $tplServ = $this->please->serve('template');

        foreach ($posts as $post) {

            $post['href'] = $urlServ->getHref($post);

            $href = $tplServ->sanitizeViewLinks($post['href']);

            if ($href == $urlServ->getCurrentUrlQueryLess()) {

                // $relatives = $navServ->getPrevNext($post);
                // $post['prev'] = $relatives->prev;
                // $post['next'] = $relatives->next;

                $post['user'] = collection('users')->find($post['user_id'] ?? null);

                $this->please->serve('hit')->setVal($post, 'views');
                return $this->render200($post);
            }
        }
        return $this->render404();
    }

    private function render200($post)
    {
        $envServ = $this->please->serve('env');

        $post = $this->please->serve('template')->hookPost($post);

        $post['full_title'] = ($post['second_title'] ?? $post['title'] ?? '') . ' • ' . $envServ->getAppEnv('APP_NAME');

        $this->please->setGlobal(["post" => $post]);

        $layout = attr($post, 'front_layout', attr($post, 'layout', 'single'));
        $link_type = attr($post, 'link_type');
        $layout_single = attr($post, 'parent.layout_single', 'single');

        if (!in_array($layout_single, ['single', 'default'])) {
            $layout = $layout_single;
        } else if (($layout == 'none' || $layout == 'default') && $link_type == 'article') {
            $layout = 'single';
        }

        $viewPath = $this->findLayout($this->please->serve('dir')->dirPath('theme'), "$layout.html.twig");

        return $this->please->serve('response')->renderTpl($viewPath);
        // return $this->please->serve('response')->handleResponse($view);
    }

    private function render404()
    {
        $fileSystem = new Filesystem();

        $_404path = $this->please->serve('dir')->getThemeDirAbsDirPath('layouts') . "/404.html.twig";

        return new Response($this->please->serve('template')->sanitizeView(
            $this->renderView($fileSystem->exists($_404path) ? "layouts/404.html.twig" : "@DovStoneSymfonyBlogAdminBundleMoSQLBased/partials/website-404.html.twig")
        ), 404);
    }

    private function findLayout($baseDir, $layout)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir)
        );

        foreach ($iterator as $file) {

            $path = $file->getPathname();
            $path = str_replace('\\', '/', $path);
            preg_match('/theme\/(.+)$/', $path, $matches);
            $clean_path = $matches[1] ?? $path;

            if ($clean_path === $layout) {
                $pathname = substr($clean_path, 0, strrpos($clean_path, '.html.twig'));
                return $pathname;
            }
        }

        return "@DovStoneSymfonyBlogAdminBundleMoSQLBased/partials/website-default";
    }
}
