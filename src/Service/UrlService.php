<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UrlService extends AbstractController
{
    private $please;
    private $fileCdnOrigin;
    private $documents;
    private $bRepo;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->fileCdnOrigin = $this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN');
        $this->bRepo = $this->please->getRepo('bloggy');
    }

    public function getHome()
    {
        return $this->getUrl();
    }

    public function getRouterContext()
    {
        return $this->please->prevContainer->get('router')->getContext();
    }

    public function getRequestStack()
    {
        return $this->please->prevContainer->get('request_stack');
    }

    public function getQueryString(): string
    {
        return $this->getRouterContext()->getQueryString();
    }

    public function getUrl($href = '/'): string
    {
        // Ensuring we can return a proper absolute url weither the given href
        // for exemple even from C:\Apps\Web\sf4\public\uploads\2018\03
        $href = str_ireplace($this->please->prevContainer->get('kernel')->getProjectDir() . '/public', '', $href);

        // Replacing trailing backslash(es) to slash
        $href = preg_replace('~\\\+~', '/', $href);

        // Replacing slash(es) to slash
        //$href = $this->getAppBaseUrl() . '/' . trim(preg_replace('~/+~', '/', $href), '/');
        $href = $this->getBaseUrl() . '/' . trim(preg_replace('~/+~', '/', $href), '/');

        // Returning the absolute url
        return $href;
    }

    public function cdnFileUrl($href = '/'): string
    {
        // Ensuring we can return a proper absolute url weither the given href
        // for exemple even from C:\Apps\Web\sf4\public\uploads\2018\03
        $href = str_ireplace($this->please->prevContainer->get('kernel')->getProjectDir() . '/public', '', $href);

        // Replacing trailing backslash(es) to slash
        $href = preg_replace('~\\\+~', '/', $href);

        // Replacing slash(es) to slash
        //$href = $this->getAppBaseUrl() . '/' . trim(preg_replace('~/+~', '/', $href), '/');
        $href = trim($this->fileCdnOrigin, '/') . '/' . trim(preg_replace('~/+~', '/', $href), '/');

        // Returning the absolute url
        return $href;
    }

    public function getRelativeUrl($href = '/', $replace = ''): string
    {
        //replacing the host and app dev dir
        $href = str_ireplace($this->getBaseUrl() . $replace, '', $href);
        return $href;
    }

    public function getPath($path, $params = [])
    {
        $symfPath = $this->please->prevContainer->get('router')->generate($path, $params);
        return $this->please->serve('env')->getAppEnv('APP_ORIGIN') . $symfPath;
    }

    public function getUrlsManagedView(string $view)
    {
        return $view;
    }

    public function getCurrentUrl(string $suffix = ''): string
    {
        $getQueryString = $this->getRouterContext()->getQueryString();
        $qString = (!empty($getQueryString) ? '?' . $getQueryString : '');
        $url = $this->getHandledUrl() . $qString;
        $url = str_ireplace('&_ajaxify=true', '', $url);
        $url = str_ireplace('?_ajaxify=true', '', $url);
        return $url . ($suffix ? "$suffix" : "");
    }

    public function getRawUrl()
    {
        $request = $this->please->getRequest();

        $scheme = $request->server->get('REQUEST_SCHEME', 'http');
        $host = $request->server->get('HTTP_HOST', 'localhost');
        $requestUri = $request->server->get('REQUEST_URI', '/');

        $rawUrl = $scheme . '://' . $host . $requestUri;

        return $rawUrl;
    }

    public function removeDomain(string $url): string
    {
        // Supprimer tout ce qui précède le premier slash (domaines, sous-domaines, protocoles)
        return preg_replace('#^([a-z]+://)?([a-z0-9.-]+\.)?[a-z0-9.-]+#', '', $url) ?: '/';
    }

    public function getQueryRedir(): string
    {
        $url = '&_redirect=' . $this->please->getInput('_redirect', $this->getCurrentUrl());
        return $url;
    }

    public function getHref($post): string
    {
        if (is_numeric($post)) {
            $post = collection('*')->find($post);
        }

        $custom_href = attr($post, 'custom_href');
        if (!empty($custom_href)) {
            return strpos($custom_href, 'http') !== false ? $custom_href : $this->please->serve('url')->getUrl($custom_href);
        }

        $post_id = attr($post, 'id');

        $cachePostHref = collection('href')->findOneBy(['post_id' => $post_id]);
        if ($cachePostHref && isset($cachePostHref['href'])) {
            return $cachePostHref['href'];
        }

        $href = '';

        $customed_slug = attr($post, 'customed_slug');
        $coll_name = attr($post, 'coll_name');
        $slug = attr($post, 'slug');
        $acfLinkType = attr(collection('acf')->findOneBy(['name' => $coll_name]), 'link_type');
        $link_type = attr($post, 'link_type');
        $finalLinkType = $acfLinkType ?? $link_type;

        if (!isset($this->documents)) {
            $this->documents = $this->please->getRepo('bloggy')->fetchEager(
                collection('page')
                    ->whereIn('in_menu', ['on', 'yes'])
                    ->fetchAll()
            );
        }

        if ($this->documents) {
            if (!isset($post['parent']) && is_array($post)) {
                $post = $this->please->getRepo('bloggy')->fetchEager($post);
            }
            $href = $this->_getRecursivePostHref($post, $this->documents);
        }

        $href = $href . (!empty($customed_slug) ? $customed_slug : '/' . $slug);
        $href = ($href === '/home' || $href === '/accueil' || $href === '/' || $href === '') ? '/' : $href;
        $href .= ($finalLinkType == 'article') ? '-' . attr($post, 'id') . '.html' : '';

        $url = $this->please->serve('url')->getUrl($href);

        $this->please->serve('crud')->basicCreate([
            'collection' => 'href',
            'sanitizeRequest' => false,
            'sanitizer' => function () use ($post_id, $url) {
                return [
                    'coll_name' => 'href',
                    'post_id' => $post_id,
                    'href' => $url
                ];
            }
        ]);
        return $url;
    }

    public function href($post): string
    {
        return $this->getHref($post);
    }

    public function redirectToHome()
    {
        return $this->redirect(
            $this->getHome()
        );
    }

    public function getCurrentUrlQueryLess(string $suffix = ''): string
    {
        return $this->getHandledUrl() . ($suffix ? "$suffix" : "");
    }

    public function getBaseUrl()
    {
        return $this->please->serve('env')->getAppBaseUrl();
    }

    private function getHandledUrl()
    {
        //$this->getRouterContext()->getPathInfo()

        $app_base_url = $this->getBaseUrl();

        //localhost
        //so lets remove app_dir
        if (false !== strpos($app_base_url, 'http://localhost')) {
            $exploded = explode('/', $app_base_url);
            $app_dir = end($exploded);
            $app_base_url = str_ireplace($app_dir, '', $app_base_url);
            //$app_base_url = preg_replace('~//+~', '/', $app_base_url);
        }
        return trim($app_base_url, '/') . '/' . trim($this->getRouterContext()->getPathInfo(), '/');
    }

    public function isDev()
    {
        return $this->getEnv() == 'dev';
    }

    public function getEnv($var = 'APP_ENV')
    {
        return $this->please->serve('env')->getAppEnv($var);
    }

    public function isLocalHost()
    {
        return $this->please->serve('env')->isLocalHost();
    }

    public function isBackoffice()
    {
        $currentUrl = $this->getCurrentUrlQueryLess();
        return preg_match('/(_back|_admin|_files)/', $currentUrl) === 1;
    }

    public function isPageBuilderMode()
    {
        return strpos($this->getQueryString(), 'swagg') !== false;
    }

    protected function _getRecursivePostHref($post, $documents)
    {
        //if( !isset($this->bloggyStore) ){ $this->bloggyStore = b(); }
        //if( !isset($this->bloggyStore) ){ $this->bloggyStore = $this->please->getMyNoSQLCollection('bloggies'); }
        if (isset($post[0])) {
            $post = $post[0];
        }

        $parent = attr($post, 'parent');

        if (is_string($parent)) {
            //$parent = $this->bloggyStore->find($parent);
        }

        if ($parent) {
            foreach ($documents as $document) {
                if (attr($document, 'id') == $parent) {
                    $parent = $document;
                }
            }
        }

        $href = '';

        foreach ($documents as $document) {

            $document_id = attr($document, 'id');
            $document_slug = attr($document, 'slug');

            if ($parent) {

                $parent_id = attr($parent, 'id');
                $parent_slug = attr($parent, 'slug');

                if ($document_id == $parent_id) {

                    $href .= "$document_slug/";

                    return $this->_getRecursivePostHref(
                        $this->bRepo->fetchEager(
                            collection('*')->find($parent_id)
                        ),
                        $documents

                    ) . '/' . $parent_slug;
                }
            }
        }

        return $href;
    }

    public function redirectUrl()
    {
        $queryString = explode('_redirect=', $this->please->getRequest()->getQueryString())[1] ?? null;
        if (!$queryString) {
            return $this->getUrl();
        }
        $redirectUrl = urldecode($queryString);
        return preg_replace('/&/', '?', $redirectUrl, 1);
    }
}
