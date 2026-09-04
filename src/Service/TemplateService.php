<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Markup;

class TemplateService extends AbstractController
{
    public $please;
    public $resetAssets;
    private $backEndHTMLParams = [];
    private $paramsCacheAssets = true;
    private \DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\LogService $log;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function sanitizeViewLinks($view)
    {
        $envServ = $this->please->serve('env');

        // Vérification rapide pour éviter le traitement inutile
        if (empty($view)) {
            return $view;
        }

        $oldHosts = $replacements = [];
        $old_hosts = $envServ->getAppEnv('APP_OLD_ORIGINS');

        if (!empty($old_hosts)) {
            $urlService = $this->please->serve('url');
            $baseUrl = rtrim($urlService->getBaseUrl(), '/') . '/';

            // Exploser et nettoyer les hôtes
            $oldHostsList = array_filter(array_map('trim', explode('|', $old_hosts)));

            foreach ($oldHostsList as $oldHost) {
                // Normaliser l'ancien hôte
                $oldHost = rtrim($oldHost, '/') . '/';

                // Version normale
                $oldHosts[] = $oldHost;
                $replacements[] = $baseUrl;

                // Version JSON (échappée)
                $oldHostJson = trim(json_encode($oldHost), '"');
                if ($oldHostJson !== $oldHost) { // Éviter les doublons si identique
                    $oldHosts[] = $oldHostJson;
                    $replacements[] = trim(json_encode($baseUrl), '"');
                }

                // Ajouter aussi la version sans slash final pour plus de robustesse
                $oldHostNoSlash = rtrim($oldHost, '/');
                if ($oldHostNoSlash . '/' !== $oldHost) {
                    $oldHosts[] = $oldHostNoSlash;
                    $replacements[] = rtrim($baseUrl, '/');

                    $oldHostNoSlashJson = trim(json_encode($oldHostNoSlash), '"');
                    if ($oldHostNoSlashJson !== $oldHostNoSlash) {
                        $oldHosts[] = $oldHostNoSlashJson;
                        $replacements[] = trim(json_encode(rtrim($baseUrl, '/')), '"');
                    }
                }
            }

            // Utiliser str_replace() au lieu de str_ireplace() pour plus de précision
            // str_ireplace() est case-insensitive, ce qui peut causer des remplacements non désirés
            if (!empty($oldHosts)) {
                $view = str_replace($oldHosts, $replacements, $view);
            }
        }

        return $view;
    }

    public function sanitizeView($view)
    {
        $envServ = $this->please->serve('env');
        $view = $this->sanitizeViewLinks($view);

        if (($LAZY_LOAD_IMAGE_PLACEHOLDER = $envServ->getAppEnv('LAZY_LOAD_IMAGE_PLACEHOLDER')) != '') {

            $placeholder = $this->please->serve('url')->getUrl($LAZY_LOAD_IMAGE_PLACEHOLDER);

            preg_match_all('/(<main)([\s\S]+)(<\/main>)/U', $view, $matches);

            if ($matches) {

                /*$view = preg_replace_callback('/(<main)([\s\S]+)(<\/main>)/U', function($m) use ($placeholder) {
                    //return str_ireplace('<img src', '<img src="'.$placeholder.'" data-lazy-src', $m[0]);
                    $re = '/(<img)(.+)(src)(.+)(>)/m';
                    $str = $m[0];
                    $subst = '$1$2src="'.$placeholder.'" data-lazy-$3$4$5';
                }, $view);*/

                $view = preg_replace_callback('/(data-lazy-style=\"background-image:)(.+)(\")/U', function ($m) use ($placeholder) {
                    //$src = preg_replace_callback('/(url\()([\s\S]+)(\))/U', function($m){
                    //  return $m[2];
                    //}, trim($m[2]));
                    $src = trim($m[2]);
                    // if( strpos($src, ':no-lazy') !== false ){
                    // die($src);
                    //     $src = preg_replace('/(url\()(.+)(\))/m', '$1\'$2\')', str_replace(':no-lazy', '', $src));
                    //     $src = str_ireplace("&quot;", "'", $src);
                    //     $src = str_ireplace("''", "'", $src);
                    //     return 'style="background-image:'.$src.'"';
                    // }
                    $src = preg_replace('/(url\()(.+)(\))/m', '$1\'$2\')', $src);
                    // $src = str_ireplace("&quot;", "'", $src);
                    // $src = str_ireplace("''", "'", $src);
                    return 'style="background:url(' . $placeholder . ') center center / contain no-repeat!important" data-lazy-style="background-image:' . $src . '"';
                }, $view);
            }
        }

        if ((new Filesystem())->exists($this->please->serve('dir')->getProjectDir() . '/src/Service/HookService.php')) {
            if (method_exists(\App\Service\HookService::class, 'viewHook') && $this->please->prevContainer->has('view_hook')) {
                $viewHook = $this->please->prevContainer->get('view_hook')->viewHook($view);
                if (!empty($viewHook)) {
                    $view = $viewHook;
                }
            }
        }

        if (
            !$this->please->serve('url')->isBackoffice()
            &&
            ($envServ->getAppEnv('MINIFY_OUTPUT') == "true" || $this->please->isXHR())
        ) {
            preg_match_all('/(<style type=\")(text\/less)(\">)([\s\S]+)(<\/style>)/U', $view, $matches);
            if ($matches) {
                $view = preg_replace_callback('/(<style type=\")(text\/less)(\">)([\s\S]+)(<\/style>)/U', function ($m) {
                    $body = (new __PhpHtmlCssJsMinifierService())->getMinifiedCss($m[4]);
                    $final = "<style type=\"text/less\">$body</style>";
                    $final = preg_replace('~>\s+<~', '><', $final); // removing whitespaces
                    return $final;
                }, $view);
            }
            $view = preg_replace('~>\s+<~', '><', $view); // removing whitespaces
            $view = preg_replace('/( )(\/\/)( )/m', ';', $view);
            $view = preg_replace('/(\'use strict\')/m', "'use strict';", $view);
            $view = str_replace("'use strict';;", "'use strict';", $view);
        }

        $view = $view ?: "<h1 style='background-color:red;text-align:center;color:#fff;padding:15px;border-radius:8px'>La vue doit \"sortir\" au moins un caractère.</h1>";

        $view = $this->_sanitize($view);
        $view = $this->_fixCDNFileOrgin($view);
        $view = $this->_fixUrls($view);

        return new Markup($view, 'UTF-8');
    }

    public function getBundleEmptyListView($message = 'Le dossier est vide.', $icon = 'ban')
    {
        return $this->renderView('@DovStoneSymfonyBlogAdminBundleMoSQLBased/partials/empty-list.html.twig', compact('message', 'icon'));
    }

    public function getTpl($tpl, array $parameters = [])
    {
        $view = $this->sanitizeView($this->renderView($tpl . '.html.twig', $parameters));
        $tplServ = $this->please->serve('template');
        $_ajaxify = input('_ajaxify') || input('_ajaxify');
        //$view = $this->please->serve('url')->isBackoffice() ? $view : $tplServ->sanitizeView($view);
        //
        if (
            ($this->please->isXHR() && $_ajaxify != false)
            ||
            ($_ajaxify != false)
        ) {
            $tplServ = $this->please->serve('template');
            $response = new JsonResponse(
                $tplServ->getParsedView($view)
            );
        } else {
            $response = new Response($view);
        }
        //
        $response->headers->set('Symfony-Debug-Toolbar-Replace', 1);
        //
        return $response;
    }

    public function getParsedView($view, $tag = 'body')
    {
        preg_match('/(<' . $tag . ')([\s\S]+)(<\/' . $tag . '>)/U', $view, $body);
        if ($body) {
            $body = $body[0];
        }
        $data = [
            'title' => $this->please->serve('post')->getTitle(),
            'body' => $body ?: $view,
            'jsonifiedView' => true,
            'post' => $this->please->serve('post')->get()
        ];
        return $data;
    }

    public function beginHTML($params = [], $uniqueKey = null)
    {
        $enableCache = $params['enableCache'] ?? true;
        $params['resetAssets'] = $params['resetAssets'] ?? false;
        $isBack = isset($params['back']);

        $beginHTML = $this->please->checkHook('baseAssetsHook', function ($hook) use ($params, $enableCache, $uniqueKey, $isBack) {

            $hookAssets = $isBack ? $this->backAssetsHook($enableCache) : $hook->baseAssetsHook();
            $this->paramsCacheAssets = $hookAssets['enableCache'] ?? true;

            if ($isBack) {
                $this->backEndHTMLParams = $params;
            }
            $params = array_merge($hookAssets, $params);

            $resetAssets = input('resetAssets') ?? $params['resetAssets'] ?? false;
            $def = [
                'headAssets',
                'cssCDN',
                'cssCDNraw',
                'jsCDN',
                'themeCssAssets',
                'themeCssAssetsRaw',
                'themeJsAssets',
                'less'
            ];
            if ($resetAssets === false) {
                $resetAssets = [];
            } else {
                $resetAssets = is_array($resetAssets) ? (empty($resetAssets) ? $def : $resetAssets) : $def;
            }

            $this->please->setGlobal(['resetAssets' => $resetAssets]);
            $assetServ = $this->please->serve('asset');
            $dirServ = $this->please->serve('dir');
            $resetAssets = array_values(array_diff($resetAssets, ['less'])); // let remove this so we could reset only it while styling
            $reset = is_array($resetAssets) && !empty($resetAssets);
            $this->resetAssets = $resetAssets;

            $content = $this->please->serve('cache')->do(function () use (
                $params,
                $resetAssets,
                $assetServ,
                $dirServ
            ) {
                $links = (function () use ($params) {
                    $linksCss = $params['links']['css'] ?? [];
                    $final = '';
                    foreach ($linksCss as $link) {
                        if ($link && is_string($link)) {
                            $final .= '<link href="' . $link . '" rel="stylesheet" media="print" onload="this.media=\'all\'">';
                        }
                    }
                    return $final;
                })();

                // preloader
                $preloader = (function () use ($params) {
                    $preloader = $params['preloader'] ?? [1, 'loading.svg', 'auto'];
                    return $this->please->serve('mix')->preloader(
                        $preloader[0] ?? 1,
                        $preloader[1] ?? 'loading.svg',
                        $preloader[2] ?? 'auto'
                    );
                })();

                // less
                $less = (function () use ($params) {
                    $less = $params['less'] ?? [];
                    return $this->please->serve('asset')->getLess($less[0] ?? $less ?? '', $less[1] ?? false, $less[2] ?? false);
                })();

                $APP_NAME = $this->please->serve('env')->getAppEnv('APP_NAME');
                $APP_AUTHOR = $this->please->serve('env')->getAppEnv('APP_AUTHOR', "Silvère Dovoui • +255 0 757 337 871 • www.silveredovoui.com");
                $post = $this->please->getGlobal('post');
                $title = ($post['second_title'] ?? $post['title'] ?? '') . ' • ' . $APP_NAME;
                if (file_exists($dirServ->dirPath('public/assets/img/og-image.png'))) {
                    $ogimage = $this->please->serve('url')->getUrl('assets/img/og-image.png');
                }
                $image = $post['image'] ?? $ogimage ?? $this->please->serve('asset')->getLogoSrc();
                $keywords = $post['keywords'] ?? $this->please->serve('string')->getTag($APP_NAME);
                $description = strip_tags(($post['description'] ?? ''));
                $currentUrl = $this->please->serve('url')->getCurrentUrl();
                $favicon = $this->please->serve('url')->getUrl('favicon.png?v=' . rand(0, 999));

                $final_head_assets = $this->buildAssets($params, $resetAssets, $assetServ);

                $xhr = input('xhr', true);

                $html = '<!DOCTYPE html>
                <html lang="fr" data-app-config=\'' . $assetServ->getAppConfig() . '\' class="theme-' . ($params['theme'] ?? 'light') . '">
                <head>
                    ' . (attr($params, 'public') === false ? '' : '<meta name="robots" content="noindex">') . '
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
                    <title>' . $title . '</title>
                    <meta name="description" content="' . $description . '">
                    <meta name="keywords" content="' . $keywords . ', ' . $APP_NAME . '">
                    <meta name="author" content="' . $APP_AUTHOR . '">
                    <meta name="url" content="' . $currentUrl . '">
                    <meta name="theme-color" content="' . ($params['themeColor'] ?? '#fff') . '" />
                    <link rel="canonical" href="' . $currentUrl . '" />
                    <link rel="shortcut icon" href="' . $favicon . '" type="image/x-icon">
                    <link rel="icon" href="' . $favicon . '" type="image/x-icon">
                    <meta property="og:url" content="' . $currentUrl . '" />
                    <meta property="og:type" content="website" />
                    <meta property="og:title" content="' . $title . '" />
                    <meta property="og:description" content="' . $description . '" />
                    <meta property="og:image" content="' . $image . '" />
                    ' . $final_head_assets . '
                    ' . $links . '
                    ' . ($xhr ? $preloader : '') . '
                    ' . $less . '
                    
                </head>';
                file_put_contents('head-assets' . (isset($params['back']) ? '-back' : '') . '.txt', $final_head_assets);
                return $html;
            }, [
                $params,
                md5(json_encode($uniqueKey)),
                $reset === true ? uniqid() : null,
                $enableCache ?: uniqid(),
                $this->paramsCacheAssets ?: uniqid()
            ], "1 month");

            return new Markup($content, 'UTF-8');
        });

        return $beginHTML;
    }

    public function endHTML($params = [], $uniqueKey = null)
    {
        $enableCache = $params['enableCache'] ?? true;
        $isBack = isset($params['back']);

        $endHTML = $this->please->checkHook('BaseAssetsHook', function ($hook) use ($params, $enableCache, $uniqueKey, $isBack) {

            return $this->please->serve('cache')->do(
                function () use ($params, $isBack, $enableCache, $hook) {

                    $hookAssets = $isBack ? $this->backAssetsHook($enableCache) : $hook->baseAssetsHook();

                    // $params = $params === false ? $this->backEndHTMLParams : (
                    //     $isBack
                    //     ? $params
                    //     : array_merge(
                    //         $hookAssets,
                    //         $params
                    //     )
                    // );
                    $params = array_merge($hookAssets, $params);

                    $assetServ = $this->please->serve('asset');

                    // jsCDN
                    $jsCDN = isset($params['jsCDN']) ? $assetServ->getCDN(
                        $assetServ->versionize($params['jsCDN']),
                        'js',
                        in_array('jsCDN', $this->resetAssets),
                        'js-cdn'
                    ) : '';

                    // themeJsAssets
                    $themeJsAssets = isset($params['themeJsAssets']) ? $assetServ->getAssets(
                        $assetServ->versionize($params['themeJsAssets']),
                        'js',
                        null,
                        in_array('themeJsAssets', $this->resetAssets)
                    ) : '';

                    if (isset($params['preloaderColor']) && is_string($params['preloaderColor'])) {
                        $preloader = '<input type="hidden" name="_onfly_token" value="' . $this->please->serve('security')->getCsrfToken() . '" /><div class="app-ajax-loader"><img src="' . $assetServ->getCDN('swagg/assets/img/loading-spinner.gif') . '" alt="Chargement en cours..."></div>';
                    }

                    $links = (function () {
                        $linksJs = $params['links']['js'] ?? [];
                        $filename = 'assets/css-js/bundlejs.min.js';
                        $bundleFile = $this->please->serve('dir')->dirPath("public/$filename");
                        $content = '';
                        foreach ($linksJs as $link) {
                            if ($link && is_string($link)) {
                                $content .= file_get_contents($link) . ";\n";
                                $this->log->reWrite('assets', ['link' => $filename, 'content' => $content]);
                            }
                        }
                        if (!empty($content)) {
                            $this->log->reWrite('assets', ['filename' => $filename, 'content' => $content]);
                            return '<script defer src="' . $this->please->serve('url')->getUrl($filename) . '?v=' . rand(0, 999) . '"></script>';
                        }
                        return '';
                    })();

                    if ($this->please->serve('env')->getAppEnv('CDN_ORIGIN')) {
                        //$userData = "<script>window.User={};function f(){window.__navigation?window.__navigation__navigation.push({onSuccess:{templateService:()=>fetch('" . $this->please->_generateUrl("userData") . "').then(r=>r.json()).then(u=>window.User=u)}}):setTimeout(f,50)}f();</script>";
                        $userData = '<script type="text/javascript">window.User={}</script>';
                    }

                    $html = $jsCDN . $themeJsAssets . $links . ($preloader ?? '') . ($userData ?? '') . '</body></html>';
                    return new Markup($html, 'UTF-8');
                },
                [
                    $params,
                    md5(json_encode($uniqueKey)),
                    is_array($this->resetAssets) && !empty($this->resetAssets) ? uniqid() : null,
                    $enableCache ?: uniqid(),
                    $this->paramsCacheAssets ?: uniqid()
                ],
                "1 month"
            );
        });

        return $endHTML;
    }

    public function gTag($id)
    {
        return new Markup("<script>window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '$id');</script>", 'UTF-8');
    }

    /**
     * call within WebsiteController::render200($post)
     */
    public function postHook($post)
    {
        if ((new Filesystem())->exists($this->please->serve('dir')->getProjectDir() . '/src/Hook/PostHook.php')) {
            if (method_exists(\App\Hook\PostHook::class, 'postHook') && $this->please->prevContainer->has('post_hook')) {
                $hooked = $this->please->prevContainer->get('post_hook')->postHook($post);
                if ($hooked) {
                    return $hooked;
                }
            }
        }
        return $post;
    }

    public function gfInput($label, $name, $value = '', array|string $attr = '', $icon = null, $em = ''): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $string_serv = $this->please->serve('string');
        $full_cls = $value == '' ? '' : ' gf-full';

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $icon = $icon ? $string_serv->i($icon) : '';

        $psw_toggler = '';
        if (is_string($attr) && strpos($attr, 'type="password"') !== false) {
            $psw_toggler = '<button onclick="var $t=$(this);$t.hide().siblings(\'button\').show().end().parents(\'.gf-wrapper\').find(\'input\').attr(\'type\',\'text\');" type="button">' . $string_serv->i('clarity:eye-line') . '</button><button onclick="var $t=$(this);$t.hide().siblings(\'button\').show().end().parents(\'.gf-wrapper\').find(\'input\').attr(\'type\',\'password\');" type="button" style="display:none">' . $string_serv->i('clarity:eye-hide-line') . '</button>';
        }

        $em = $em ? '<em class="prop fs--6">' . $em . '</em>' : '';

        return new Markup('
        <div class="gf-wrapper gf-wrapper-input ' . ($icon ? 'gf-wrapper-icon' : '') . '">
            <input name="' . $name . '" class="gf-control form-control' . $full_cls . $cls . '" ' . $attr . ' value="' . htmlspecialchars($value) . '">
            <div class="gf-label">' . $label . '</div>
            <div class="gf-icon">' . $icon . '</div>
            <div class="gf-psw-toggler">' . $psw_toggler . '</div>
        </div>' . $em, 'UTF-8');
    }

    public function gfTextarea($label, $name, $value = '', array|string $attr = '', $icon = null, $em = ''): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $string_serv = $this->please->serve('string');
        $gfull_cls = $value == '' ? '' : ' gf-full';

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $icon = $icon ? $string_serv->i($icon) : '';
        $is_content_editable = is_string($attr) && strpos($attr, 'contenteditable') !== false;
        $tag = $is_content_editable ? 'div' : 'textarea';
        $em = $em ? '<em class="prop fs--6">' . $em . '</em>' : '';

        return new Markup('
        <div class="gf-wrapper gf-wrapper-textarea ' . ($icon ? 'gf-wrapper-icon' : '') . '">
            <' . $tag . ' name="' . $name . '" class="gf-control form-control' . $gfull_cls . $cls . '" ' . $attr . '>' . $value . '</' . $tag . '>
            <div class="gf-label">' . $label . '</div>
            <div class="gf-icon">' . $icon . '</div>
        </div>' . $em, 'UTF-8');
    }

    public function gfSelect($label, $name, $selected = '', $options = [], array|string $attr = '', $icon = null): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $selected_htmlentities = [];
        $selected = is_numeric($selected) || is_string($selected) ? [$selected] : $selected;
        if (is_array($selected)) {
            foreach ($selected as $val) {
                $selected_htmlentities[] = htmlentities($val);
            }
        }

        $string_serv = $this->please->serve('string');
        $gfull_cls = '';

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $options_html = '';
        $icon = $icon ? $string_serv->i($icon) : '';
        $user = $this->please->serve('user');

        if (is_array($options)) {
            foreach ($options as $v) {
                $id = $v['id'] ?? null;
                $title = isset($v['roles']) ? ('(' . $v['mle'] . ') ' . $user->getUserFullName($v)) : ($v['title'] ?? null);
                if ($id && $title) {
                    $options_html .= '<option ' . ($v['attr'] ?? '') . ' ' . (in_array($id, $selected_htmlentities) ? 'selected' : '') . ' value="' . $id . '">' . $title . '</option>';
                }
            }
        } else {
            $options_html = $options;
            $selected = $selected[0] ?? '';
            $selected_escaped = preg_quote($selected, '/');
            $re = "/(.*)(value=\"$selected_escaped\"|value='$selected_escaped')(.*)/m";
            $options_html = preg_replace($re, "$1$2 selected$3", $options_html);
        }

        return new Markup('
        <div class="gf-wrapper gf-wrapper-select ' . ($icon ? 'gf-wrapper-icon' : '') . '">
            <select name="' . $name . '" class="gf-control form-control' . $gfull_cls . $cls . '" ' . $attr . '>' . $options_html . '</select>
            <div class="gf-label">' . $label . '</div>
            <div class="gf-icon">' . $icon . '</div>
        </div>', 'UTF-8');
    }

    public function bfInput($label, $name, $value = '', array|string $attr = '', $icon = null): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $string_serv = $this->please->serve('string');

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $icon = $icon ? $string_serv->i($icon) : '';
        $id = 'bf_control_' . $name;

        $psw_toggler = '';
        if (is_string($attr) && strpos($attr, 'type="password"') !== false) {
            $psw_toggler = '<button onclick="var $t=$(this);$t.hide().siblings(\'button\').show().end().parents(\'.bf-wrapper\').find(\'input\').attr(\'type\',\'text\');" type="button">' . $string_serv->i('clarity:eye-line') . '</button><button onclick="var $t=$(this);$t.hide().siblings(\'button\').show().end().parents(\'.bf-wrapper\').find(\'input\').attr(\'type\',\'password\');" type="button" style="display:none">' . $string_serv->i('clarity:eye-hide-line') . '</button>';
        }

        return new Markup('
        <div class="bf-wrapper bf-wrapper-input form-floating' . ($icon ? ' bf-wrapper-icon' : '') . '">
            <input name="' . $name . '" id="' . $id . '" class="form-control bf-control ' . $cls . '" ' . $attr . ' value="' . htmlspecialchars($value) . '" placeholder="' . $label . '">
            <label class="bf-label" for="' . $id . '">' . $label . '</label>
            <div class="bf-icon">' . $icon . '</div>
            <div class="bf-psw-toggler">' . $psw_toggler . '</div>
        </div>', 'UTF-8');
    }

    public function bfTextarea($label, $name, $value = '', array|string $attr = '', $icon = null): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $string_serv = $this->please->serve('string');

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $icon = $icon ? $string_serv->i($icon) : '';
        $is_content_editable = is_string($attr) && strpos($attr, 'contenteditable') !== false;
        $tag = $is_content_editable ? 'div' : 'textarea';

        return new Markup('
        <div class="bf-wrapper bf-wrapper-textarea form-floating' . ($icon ? ' bf-wrapper-icon' : '') . '">
            <' . $tag . ' name="' . $name . '" onfocus="$(this).parents(\'.bf-wrapper\').addClass(\'bf-focus\')" onblur="$(this).parents(\'.bf-wrapper\').removeClass(\'bf-focus\')" class="bf-control form-control ' . $cls . '" ' . $attr . ' placeholder="' . $label . '">' . $value . '</' . $tag . '>
            <label class="bf-label">' . $label . '</label>
            <div class="bf-icon">' . $icon . '</div>
        </div>', 'UTF-8');
    }

    public function bfSelect($label, $name, $selected = '', $options = [], array|string $attr = '', $icon = null): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $selected_htmlentities = [];
        $selected = is_numeric($selected) || is_string($selected) ? [$selected] : $selected;
        if (is_array($selected)) {
            foreach ($selected as $val) {
                $selected_htmlentities[] = htmlentities($val);
            }
        }

        $string_serv = $this->please->serve('string');

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $options_html = '';
        $icon = $icon ? $string_serv->i($icon) : '';
        $user = $this->please->serve('user');

        if (is_array($options)) {
            foreach ($options as $v) {
                $id = $v['id'] ?? null;
                $title = isset($v['roles']) ? ('(' . $v['mle'] . ') ' . $user->getUserFullName($v)) : ($v['title'] ?? null);
                if ($id && $title) {
                    $options_html .= '<option ' . ($v['attr'] ?? '') . ' ' . (in_array($id, $selected_htmlentities) ? 'selected' : '') . ' value="' . $id . '">' . $title . '</option>';
                }
            }
        } else {
            $options_html = $options;
            $selected = $selected[0] ?? '';
            $selected_escaped = preg_quote($selected, '/');
            $re = "/(.*)(value=\"$selected_escaped\"|value='$selected_escaped')(.*)/m";
            $options_html = preg_replace($re, "$1$2 selected$3", $options_html);
        }

        return new Markup('
        <div class="bf-wrapper bf-wrapper-select form-floating' . ($icon ? ' bf-wrapper-icon' : '') . '">
            <select name="' . $name . '" class="bf-control form-control form-select ' . $cls . '" ' . $attr . '>' . $options_html . '</select>
            <label class="bf-label">' . $label . '</label>
            <div class="bf-icon">' . $icon . '</div>
        </div>', 'UTF-8');
    }

    public function fcInput($label, $name, $value = '', array|string $attr = '', $icon = null): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $string_serv = $this->please->serve('string');

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $icon = $icon ? $string_serv->i($icon) : '';
        $id = 'fc_control_' . $name;

        $psw_toggler = '';
        if (is_string($attr) && strpos($attr, 'type="password"') !== false) {
            $psw_toggler = '<button onclick="var $t=$(this);$t.hide().siblings(\'button\').show().end().parents(\'.fc-wrapper\').find(\'input\').attr(\'type\',\'text\');" type="button">' . $string_serv->i('clarity:eye-line') . '</button><button onclick="var $t=$(this);$t.hide().siblings(\'button\').show().end().parents(\'.fc-wrapper\').find(\'input\').attr(\'type\',\'password\');" type="button" style="display:none">' . $string_serv->i('clarity:eye-hide-line') . '</button>';
        }

        return new Markup('
        <div class="fc-wrapper fc-wrapper-input' . ($icon ? ' fc-wrapper-icon' : '') . '">
            <label class="fc-label">
                <div>' . $label . '</div>
                <input name="' . $name . '" id="' . $id . '" class="form-control fc-control ' . $cls . '" ' . $attr . ' value="' . htmlspecialchars($value) . '">
            </label>
            <div class="fc-icon">' . $icon . '</div>
            <div class="fc-psw-toggler">' . $psw_toggler . '</div>
        </div>', 'UTF-8');
    }

    public function fcTextarea($label, $name, $value = '', array|string $attr = '', $icon = null): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $string_serv = $this->please->serve('string');

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $icon = $icon ? $string_serv->i($icon) : '';
        $is_content_editable = is_string($attr) && strpos($attr, 'contenteditable') !== false;
        $tag = $is_content_editable ? 'div' : 'textarea';

        return new Markup('
        <div class="fc-wrapper fc-wrapper-textarea ' . ($icon ? ' fc-wrapper-icon' : '') . '">
            <label class="fc-label">
                <div>' . $label . '</div>
                <' . $tag . ' name="' . $name . '" class="fc-control form-control ' . $cls . '" ' . $attr . '>' . $value . '</' . $tag . '>
            </label>
            <div class="fc-icon">' . $icon . '</div>
        </div>', 'UTF-8');
    }

    public function fcSelect($label, $name, $selected = '', $options = [], array|string $attr = '', $icon = null): string
    {
        // Conversion tableau → chaîne si nécessaire
        if (is_array($attr)) {
            $attrString = '';
            foreach ($attr as $key => $val) {
                if ($val === true) {
                    $attrString .= ' ' . $key;
                } elseif ($val !== false && $val !== null) {
                    $attrString .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
                }
            }
            $attr = $attrString;
        }

        $selected_htmlentities = [];
        $selected = is_numeric($selected) || is_string($selected) ? [$selected] : $selected;
        if (is_array($selected)) {
            foreach ($selected as $val) {
                $selected_htmlentities[] = htmlentities($val);
            }
        }

        $string_serv = $this->please->serve('string');

        $cls = '';
        if (is_string($attr) && strpos($attr, 'class') !== false) {
            preg_match('/class="([^"]*)"/', $attr, $matches);
            $cls = isset($matches[1]) ? ' ' . $matches[1] : '';
        }

        $options_html = '';
        $icon = $icon ? $string_serv->i($icon) : '';
        $user = $this->please->serve('user');

        if (is_array($options)) {
            foreach ($options as $v) {
                $id = $v['id'] ?? null;
                $title = isset($v['roles']) ? ('(' . $v['mle'] . ') ' . $user->getUserFullName($v)) : ($v['title'] ?? null);
                if ($id && $title) {
                    $options_html .= '<option ' . ($v['attr'] ?? '') . ' ' . (in_array($id, $selected_htmlentities) ? 'selected' : '') . ' value="' . $id . '">' . $title . '</option>';
                }
            }
        } else {
            $options_html = $options;
            $selected = $selected[0] ?? '';
            $selected_escaped = preg_quote($selected, '/');
            $re = "/(.*)(value=\"$selected_escaped\"|value='$selected_escaped')(.*)/m";
            $options_html = preg_replace($re, "$1$2 selected$3", $options_html);
        }

        return new Markup('
        <div class="fc-wrapper fc-wrapper-select' . ($icon ? ' fc-wrapper-icon' : '') . '">
            <label class="fc-label">
                <div>' . $label . '</div>
                <select name="' . $name . '" class="fc-control form-control form-select ' . $cls . '" ' . $attr . '>' . $options_html . '</select>
            </label>
            <div class="fc-icon">' . $icon . '</div>
        </div>', 'UTF-8');
    }

    /*private function convertAnyScssToCss($view)
    {
        return $view;
        preg_match_all('/(<style type=\")(text\/scss|scss)(\">)([\s\S]+)(<\/style>)/U', $view, $matches);
        if ($matches) {
            $view = preg_replace_callback('/(<style type=\")(text\/scss|scss)(\">)([\s\S]+)(<\/style>)/U', function ($m) {
                return '<style>' . (new Compiler())->compile($m[4]) . '</style>' . "\n";
            }, $view);
        }
        return $view;
    }
    */

    private function _sanitize($view)
    {
        // Parcourir tous les scripts et les envelopper avec le code fourni
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $view, $matches);
        foreach ($matches[1] as $content) {
            $wrappedScript = '<script defer>(function(){function runWhenJQueryIsReady(){if(window.jQuery){' . $content . ';console.log("loaded!");}else{setTimeout(runWhenJQueryIsReady,500);console.log("reload");}}runWhenJQueryIsReady();})();</script>';
            // Remplacement dans le contenu de $view
            $view = str_replace('<script>' . $content . '</script>', $wrappedScript, $view);
        }

        // Fonction pour supprimer les doublons de balises <style> avec type="text/less"
        $foundIds = [];
        $view = preg_replace_callback(
            '/<style[^>]*id="([^"]*)"[^>]*type="text\/less"[^>]*>(.*?)<\/style>/s',
            function ($match) use (&$foundIds) {
                $styleId = $match[1];

                if (in_array($styleId, $foundIds)) {
                    return '';
                }

                $foundIds[] = $styleId;
                return $match[0];
            },
            $view
        );

        $view = preg_replace('/data-type="text\/less"/i', 'type="text/less"', $view);

        return $view;
    }

    private function _fixCDNFileOrgin($view)
    {
        $cdn_file_origin = $this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN');

        if (empty($cdn_file_origin)) {
            return $view;
        }

        // Normaliser l'URL CDN
        $cdn_file_origin = rtrim($cdn_file_origin, '/') . '/';

        // Pattern unique pour capturer toutes les URLs contenant "uploads"
        $resultat = preg_replace_callback(
            '/((?:src|data-lazy-src|data-src|href|poster)=["\']|url\()([^"\')]+)(["\']|\))/i',
            function ($matches) use ($cdn_file_origin) {
                $prefix = $matches[1]; // src=" ou url(
                $url = trim(html_entity_decode($matches[2]), " '\"");
                $suffix = $matches[3]; // " ou )

                // Vérifier si c'est une URL contenant "uploads"
                if (strpos($url, 'uploads') !== false) {
                    // Chercher le début du chemin uploads
                    $pattern = '/(uploads(?:-[a-z]+)?\/)/i';
                    if (preg_match($pattern, $url, $uploadMatch, PREG_OFFSET_CAPTURE)) {
                        $pos = $uploadMatch[0][1];
                        $uploadPath = substr($url, $pos);

                        // Construire la nouvelle URL
                        $nouvelleUrl = $cdn_file_origin . $uploadPath;
                        $nouvelleUrl = htmlspecialchars($nouvelleUrl, ENT_QUOTES);

                        return $prefix . $nouvelleUrl . $suffix;
                    }
                }

                return $matches[0];
            },
            $view
        );

        return $resultat;
    }

    private function _fixUrls($view)
    {
        // Pattern pour trouver les URLs avec des ? incorrects
        return preg_replace_callback(
            '/(href|src|action|url)=["\']([^"\']+\?[^"\']+)["\']/i',
            function ($matches) {
                $url = $matches[2];
                $firstQm = strpos($url, '?');

                if ($firstQm === false || substr_count($url, '?') <= 1) {
                    return $matches[0]; // Pas besoin de correction
                }

                $fixed = substr($url, 0, $firstQm + 1) .
                    str_replace('?', '&', substr($url, $firstQm + 1));

                return $matches[1] . '="' . $fixed . '"';
            },
            $view
        );
    }

    private function buildAssets($params, $resetAssets, $assetServ)
    {
        $gTag = isset($params['gTag']) ? '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $params['gTag'] . '"></script>' : '';

        // headAssets
        $headAssets = isset($params['headAssets']) ? $assetServ->getHeadAssets(
            $params['headAssets'][0] ?? [],
            $params['headAssets'][1] ?? [],
            in_array('headAssets', $resetAssets)
        ) : '';

        // themeCssAssets
        $themeCssAssets = isset($params['themeCssAssets']) ? $assetServ->getAssets(
            $params['themeCssAssets'],
            'css',
            null,
            in_array('themeCssAssets', $resetAssets)
        ) : '';

        // themeCssAssetsRaw
        $themeCssAssetsRaw = isset($params['themeCssAssetsRaw']) ? $assetServ->getAssetsRaw(
            $params['themeCssAssetsRaw'],
            'css',
            null,
            in_array('themeCssAssetsRaw', $resetAssets)
        ) : '';

        // cssCDN
        $cssCDN = isset($params['cssCDN']) ? $assetServ->getCDN(
            $params['cssCDN'],
            'css',
            in_array('cssCDN', $resetAssets),
            'css-cdn'
        ) : '';

        // cssCDNraw
        $cssCDNraw = isset($params['cssCDNraw']) ? $assetServ->getCDNraw(
            $params['cssCDNraw'],
            'css',
            null,
            in_array('cssCDNraw', $resetAssets),
            'css-cdn-raw'
        ) : '';

        if (isset($params['scrollBarSelectors'])) {
            $color = $params['themeColor'] ?? 'var(--p)';
            //$customSelectors = array_merge(['body'], $params['scrollBarSelectors'] ?? []);
            $customSelectors = $params['scrollBarSelectors'] ?? [];
            $scrollBars = '';

            foreach ($customSelectors as $mediaQuery => $selector) {
                $scrollBar = '';

                $bar = $selector . '::-webkit-scrollbar';
                $scrollBar .= "$bar{width:10px}";

                $hbar = $selector . '::-webkit-scrollbar:horizontal';
                $scrollBar .= "$hbar{height:10px}";

                $track = $selector . '::-webkit-scrollbar-track';
                $scrollBar .= "$track{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);background:#f0f0f0;border-radius:0}";

                $thumb = $selector . '::-webkit-scrollbar-thumb';
                $scrollBar .= "$thumb{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);background:$color;border-radius:0}";

                $scrollBar .= "$selector*{scrollbar-width:thin;scrollbar-color:$color #f0f0f0}";

                //
                $open_mq = is_numeric($mediaQuery) ? '@media screen and (' . $mediaQuery . '){' : '';
                $close_mq = is_numeric($mediaQuery) ? '}' : '';
                $scrollBars .= '<style>' . $open_mq . $scrollBar . $close_mq . '</style>';
            }

            /*$scrollBar = str_replace(',{', '{', '<style>@media screen and (min-width:992px){html::-webkit-scrollbar' . ($bar ? (',' . $bar) : '') . '{width:10px;height:12px;background-color:' . $color . '}html::-webkit-scrollbar-track' . ($track ? (',' . $track) : '') . '{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);border-radius:0;background-color:#f5f5f5}html::-webkit-scrollbar-thumb' . ($thumb ? (',' . $thumb) : '') . '{border-radius:0;-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);background-color:' . $color . '}}</style>');*/
        }

        if (isset($params['googleFonts']) && $params['googleFonts'] != false) {
            $googleFonts = '<link rel="preconnect" href="https://fonts.gstatic.com"><link href="https://fonts.googleapis.com/css2?family=' . ($params['googleFonts']) . '&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'" fetchpriority="low">';
        }

        if (isset($params['googleSiteVerifKey']) && $params['googleSiteVerifKey'] != false) {
            $googleSiteVerifMeta = '<meta name="google-site-verification" content="' . $params['googleSiteVerifKey'] . '" />';
        }

        if (isset($params['meta']) && $params['meta'] != '') {
            $meta = $params['meta'];
        }

        $final_head_assets =
            ($googleFonts ?? '')
            . ($googleSiteVerifMeta ?? '')
            . ($meta ?? '')
            . $headAssets
            . $cssCDN
            . $cssCDNraw
            . $themeCssAssets
            . $themeCssAssetsRaw
            . $gTag
            //. $jsCDN
            //. $themeJsAssets
            . ($scrollBars ?? '')
            . ('<style id="navigation__lockscroll">html,body,.app-page{overflow:hidden!important}</style><style>.app-page{display:none}.app-preloader-wrapper{position:fixed;top:0;left:0;width:100%;height:100%;text-align:center;background:var(--white);z-index:99999999}.app-preloader-wrapper>div{top:50%;position:absolute;transform:translateY(-50%);left:0;right:0;margin:auto}html body::before,html body::after{display:none}.hide-app-preloader-wrapper .app-preloader-wrapper{display:none}</style>' . (isset($params['preloaderColor'])
                ? ('<style>body:not(.pending) .app-ajax-loader{transform:scale(0)}body.pending .app-ajax-loader{transform:scale(1)}.app-ajax-loader{transition:all .3s ease;position:fixed;left:0;top:0;height:100%;width:100%;z-index:99999999}.app-ajax-loader img{background:var(--white);border-radius:100%;position:absolute;top:50%;left:50%;padding:10px;transform:translate(-50%,-50%);box-shadow: 0 0 30px 15px ' . $params['preloaderColor']) . '</style>'
                : ''));

        return $final_head_assets;
    }

    private function backAssetsHook($enableCache = true)
    {
        return [
            'cache' => $this->please->serve('env')->isProduction() ? true : $enableCache,
            'back' => true,
            'public' => false,
            'themeColor' => 'var(--p)',
            'theme' => 'light',
            'scrollBarSelectors' => [],
            'preloaderColor' => 'var(--p)',
            'googleFonts' => 'Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Exo+2:wght@100;200;300;400;500;600&family=Lato:wght@400;700',
            'headAssets' => [['bs-5']],
            'cssCDN' => [
                'swagg/plugins/bottom-sheet/bottom-sheet',
                'swagg/plugins/fbdialog/fbdialog',
                'swagg/plugins/files-explorer/files-explorer'
            ],
            'cssCDNraw' => [
                'tabler/css/tabler-io--tabler.min',
                'tabler-io--tabler-vendors.min'
            ],
            'jsCDN' => [
                'swagg/plugins/bottom-sheet/bottom-sheet-v1',
                'swagg/plugins/fbdialog/fbdialog-v1',
                'swagg/services/claude-toast',
                'swagg/plugins/files-explorer/files-explorer',
                'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min',
            ],
            'themeCssAssets' => [],
            'themeJsAssets' => [
                'app',
                '_back/back',
                '_back/filter'
            ],
            //
            'links' => [
                'css' => [],
            ],
            'less' => [
                [
                    '_/theme-app-v1',
                    '_/app',
                    '_/fix',
                    '_/gf-wrapper',
                    '_/ellipsis',
                    '_/owl-cute',
                    '_/select2',
                    '_/my-select2',
                    '_/separators',
                    '_/form-switch',
                    '_/swal',
                    '_/scrolling',
                    '_/custom-scrollbar',
                    '_back/basic',
                    '_back/fix',
                    '_/tiny-frame',
                    '_/bottomsheet',
                    '_/btn',
                    '_/single-filter-container',
                    '_/text-align',
                    '_/animation',
                    '_/account',
                    '_/socials',
                    '_/stars-review',
                    '_/in-out',
                    '_/stars',
                    '_/flex-scroll'
                ],
                true, // raw
                true // back_office
            ]
        ];
    }
}
