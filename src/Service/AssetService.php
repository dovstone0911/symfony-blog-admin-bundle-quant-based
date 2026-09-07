<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\__PhpHtmlCssJsMinifierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Markup;

class AssetService extends AbstractController
{
    protected $previousContainer;

    private $filesystem;
    private $minifier;
    private $fileCdnOrigin;

    public $please;
    public $uploadsDir;
    public $themeDir;
    public $reset;
    public \DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\CacheService $cache;
    public \DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\LogService $log;

    // Cache des configurations CSS/JS pour éviter les recalculs
    private static $cssBag = [
        'bs-3' => 'bootstrap-3/css/bootstrap.min|v',
        'bs-4' => 'bootstrap-4/css/bootstrap.min|v',
        'bs-5' => 'bootstrap-5/css/bootstrap.min|v',
        'fa-4' => 'font-awesome-4/css/cdn-origin.font-awesome.min|v',
        'fa-5' => 'font-awesome-5/css/cdn-origin.all|v',
        'imports' => 'swagg/assets/css/imports|v',
        'animate' => 'animate/css/animate.min|v',
        'basic' => 'swagg/assets/css/basic|v',
        'tabs' => 'swagg/assets/css/tabs|v',
        'tiles' => 'swagg/assets/css/tiles|v'
    ];

    // Cache des assets par défaut pour éviter la redéfinition
    private static $defaultCssAssets = [
        'owlcarousel/css/owl.carousel.2.3.4.min',
        'owlcarousel/css/owl.theme.default.2.3.4.min',
        'trumbowyg/css/trumbowyg.min',
        'sweetalert2/10/sweetalert2.min',
        'jBox/jBox.all.min',
        'swagg/navigation/toast.min',
        'swagg/navigation/xhr-progress',
        'swagg/plugins/drawer/css/drawer',
        'swagg/assets/css/widget-file',
        'pretty-checkbox/css/pretty-checkbox.min',
    ];

    private static $defaultJsAssets = [
        'jquery/jquery-1.11.3.min',
        //'swagg/services/window|v',
        'swagg/helpers-v2|v',
        'less/less@3.13',
        'trumbowyg/js/trumbowyg.min',
        'trumbowyg/js/fr.min',
        'underscore/js/underscore',
        'iconify/js/iconify.min',
        'owlcarousel/js/owl.carousel.2.3.4.min',
        'sticky/jquery.sticky',
        'sweetalert2/10/sweetalert2.min',
        'jBox/jBox.all.min',
        'hoangnd25/cacheJS/cacheJS.min|v',
        'swagg/sfm/builder-v2|v',
        'swagg/services/files-gallery|v',
        'swagg/services/gcontrol|v',
        'swagg/services/bindonce|v',
        'swagg/services/please|v',
        'swagg/services/ajaxify-v3|v',
        'swagg/services/lazyloading|v',
        'swagg/services/attr|v',
        'swagg/services/less-to-textless|v',
        'swagg/services/ping|v',
        'swagg/services/cute-modal|v',
        'swagg/services/blur-effect|v',
        'swagg/services/iconifier|v',
        'swagg/services/replacenode-v2|v',
        'swagg/services/sticky|v',
        'swagg/services/nav-active|v',
        'swagg/services/files-gallery|v',
        'swagg/services/loadmore|v',
        'swagg/services/generic-app-init|v',
        'swagg/services/swagg-lite-editor|v',
        'swagg/services/yt-duration-display|v',
        'swagg/services/youtube-progress|v',
        'swagg/services/debug-events|v',
        'swagg/services/keyboard-navigation',
        //'swagg/services/event-cleaner|v',
        'swagg/navigation/bootstrap-v4|v',
        'swagg/navigation/debounce|v',
        'swagg/navigation/toast.min|v',
        'swagg/plugins/drawer/js/drawer-v2|v',
        'tabler/js/tabler-io--tabler.min',
        'tabler/js/tabler-io--demo.min',
        'tabler/js/apexcharts.min',
        'tabler/js/chart',
        'tabler/js/app|v',
        'select2/js/select2.full.min',
        'select2/i18n/fr',
        'cryptojs/4.1.1/crypto-js.min',
    ];

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->filesystem = new Filesystem();
        $this->uploadsDir = $this->please->prevContainer->get('kernel')->getProjectDir() . "/public/uploads";
        $this->themeDir = $this->please->prevContainer->get('kernel')->getProjectDir() . "/theme";
        $this->fileCdnOrigin = $this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN', $this->please->serve('url')->getUrl());
        $this->cache = $this->please->serve('cache');
        $this->log = $this->please->serve('log');
    }

    /**
     * Initialise le minificateur de façon lazy
     */
    public function getMinifier(): __PhpHtmlCssJsMinifierService
    {
        if (!isset($this->minifier)) {
            $this->minifier = new __PhpHtmlCssJsMinifierService();
        }
        return $this->minifier;
    }

    /**
     * Prépare le répertoire CSS/JS de façon optimisée
     */
    public function ensureCssJsDirectory(): void
    {
        //if (!$this->cssJsRemoved) {
        $dirServ = $this->please->serve('dir');
        $dir = $dirServ->dirPath('public/assets/css-js');
        $this->filesystem->remove($dir);
        $this->filesystem->mkdir($dir);
        //$this->cssJsRemoved = true;
        //}
    }

    /**
     * Génère un ID unique pour les assets
     */
    private function generateAssetId(string $path): string
    {
        return str_ireplace(['.css', '.js', '.', '/', '-'], ['_', '_', '_', '_', '_'], $path);
    }

    /**
     * Ajoute la version aux assets si nécessaire
     */
    private function addVersionToAsset(string $path): array
    {
        if (strpos($path, '|v') !== false) {
            $cleanPath = str_replace('|v', '', $path);
            return [
                'path' => $cleanPath,
                'url' => $cleanPath . '?v=' . rand(0, 999),
                'data_url' => $cleanPath
            ];
        }
        return [
            'path' => $path,
            'url' => $path,
            'data_url' => $path
        ];
    }

    public function getHeadAssets($arr = ['bs-3', 'fa-5', 'animate', 'basic'], $prevent = [], $enableCache = true): string
    {
        $cacheKey = [$arr, $enableCache ?: uniqid(), $prevent];

        $content = $this->cache->do(function () use ($arr, $prevent) {
            // Header optimisé avec moins de concaténations
            $output = sprintf(
                '<style>html .pending::before{background:rgba(255, 255, 255, 0.32) url("%s") center center no-repeat}</style>%s<script defer src="%s"></script>%s',
                $this->getCDN('swagg/assets/img/loading-spinner.gif'),
                "\n",
                $this->getCDN("swagg/services/addscript.min.js?v=" . rand(0, 999)),
                "\n"
            );

            // CSS Assets optimisés
            $output .= $this->getCDN(
                $this->cleanHeadAssets($prevent, self::$defaultCssAssets),
                'css',
                true,
                'default-css-assets'
            );

            // JS Assets optimisés
            $output .= $this->getCDN(
                $this->cleanHeadAssets($prevent, self::$defaultJsAssets),
                'js',
                true,
                'default-js-assets'
            );

            $output .= $this->getAsset('_back/back.js', '', true);

            // Traitement optimisé du CSS bag
            $finalCssBag = array_intersect_key(self::$cssBag, array_flip($arr));
            if (!empty($finalCssBag)) {
                $output .= $this->getCDN(
                    array_values($finalCssBag),
                    'css',
                    true,
                    'headassets-css'
                );
            }

            return $output;
        }, $cacheKey);

        return new Markup($content, 'UTF-8');
    }

    public function getAssets(array $paths = [], $extension = 'css', $isCDN = null, $enableCache = true)
    {
        return $this->getMixedAssets($paths, $extension, $isCDN, $enableCache);
    }

    public function getLogoSrc($v = 1, $ext = 'png', $path = null)
    {
        return $this->getAsset($path ?? "logo.$ext") . "?v=$v";
    }

    public function getAsset($asset, $attr = '', $enableCache = true)
    {
        $cacheKey = [$asset, $attr, $enableCache ?: uniqid()];

        $content = $this->cache->do(function () use ($asset, $attr) {
            $urlServ = $this->please->serve('url');

            // Détermination optimisée du type de fichier
            $extension = null;
            if (strpos($asset, '.css') !== false) {
                $suffix = "css/$asset";
                $extension = 'css';
            } elseif (strpos($asset, '.js') !== false) {
                $suffix = "js/$asset";
                $extension = 'js';
            } else {
                $suffix = "img/$asset";
            }

            // Traitement optimisé des versions
            $assetInfo = $this->addVersionToAsset($suffix);
            $url = $urlServ->getUrl("assets/" . $assetInfo['url']);
            $data_url = $urlServ->getUrl("assets/" . $assetInfo['data_url']);
            $id = $this->generateAssetId($assetInfo['path']);

            // Génération optimisée des balises HTML
            switch ($extension) {
                case 'css':
                    return sprintf(
                        '<link data-ref="%s" id="link_%s" %s rel="stylesheet" href="%s" media="print" onload="this.media=\'all\'">',
                        $data_url,
                        $id,
                        $attr,
                        $url
                    );
                case 'js':
                    return sprintf(
                        '<script defer="true" data-ref="%s" id="script_%s" %s src="%s"></script>',
                        $data_url,
                        $id,
                        $attr,
                        $url
                    );
                default:
                    return $url;
            }
        }, $cacheKey);

        return new Markup($content, 'UTF-8');
    }

    public function getCDN($path, string $extension = 'css', $enableCache = true, $filename = null)
    {
        $cacheKey = [$path, $extension, $enableCache ?: uniqid()];

        $content = $this->cache->do(function () use ($path, $filename, $extension, $enableCache) {
            //$this->ensureCssJsDirectory();

            if (is_string($path)) {
                return trim($this->please->serve('env')->getAppEnv('CDN_ORIGIN'), '/') . '/' .
                    trim(preg_replace('~/+~', '/', $path), '/');
            }

            if (is_array($path)) {
                return $this->processAssetArray($path, $extension, $filename, $enableCache);
            }

            return '';
        }, $cacheKey);

        return new Markup($content, 'UTF-8');
    }

    /**
     * Traite un tableau d'assets de façon optimisée
     */
    private function processAssetArray(array $paths, string $extension, $reset = false): string
    {
        $envServ = $this->please->serve('env');
        $dirServ = $this->please->serve('dir');

        //$minifier = $this->getMinifier();
        $mixedKey = substr(md5(json_encode($paths)), 0, 8);
        $css_js_folder = $dirServ->dirPath("public/assets/css-js");
        if (!is_dir($css_js_folder)) {
            mkdir($css_js_folder);
        }
        $mixedFilePath = $dirServ->dirPath("public/assets/css-js/$mixedKey.min.$extension");


        // Si le fichier existe déjà, retourner directement le tag
        if (file_exists($mixedFilePath) && $reset === false) {
            return $this->getAssetFullContentTag($mixedKey, $extension, $paths);
        }

        $contents = '';
        $pathCount = count($paths);
        $startTime = microtime(true);

        foreach ($paths as $i => $path) {
            $v = '?v=' . rand(0, 999);
            $cleanPath = str_replace('|v', '', $path);
            $fullPath = stripos($cleanPath, 'http') === false ?
                $this->getCDN($cleanPath . '.' . $extension) :
                $cleanPath . '.' . $extension;

            $contents .= @file_get_contents($fullPath . $v) . PHP_EOL;

            // Traitement final uniquement à la dernière itération
            if ($i === $pathCount - 1) {
                $processedContent = $this->processAssetContent($contents, $extension, $envServ);
                file_put_contents($mixedFilePath, $processedContent, LOCK_EX);
                $executionTime = microtime(true) - $startTime;
                $this->log->reWrite('assets', ['filename' => $path, 'mixedFilePath' => $mixedFilePath, 'content' => $processedContent], $executionTime);
                return $this->getAssetFullContentTag($mixedKey, $extension, $paths);
            }
        }

        return '';
    }

    public function getCDNraw($path, $extension = 'css', $theme = null, $enableCache = true)
    {
        $cacheKey = [$path, $extension, $enableCache ?: uniqid(), $theme];

        $content = $this->cache->do(function () use ($path, $extension, $theme) {
            //$this->ensureCssJsDirectory();

            if (is_string($path)) {
                return trim($this->please->serve('env')->getAppEnv('CDN_ORIGIN'), '/') . '/' .
                    trim(preg_replace('~/+~', '/', $path), '/');
            }

            if (is_array($path)) {
                return $this->processRawAssetArray($path, $extension, $theme);
            }

            return '';
        }, $cacheKey);

        return new Markup($content, 'UTF-8');
    }

    /**
     * Traite un tableau d'assets raw de façon optimisée
     */
    private function processRawAssetArray(array $paths, string $extension, $theme = null): string
    {
        $envServ = $this->please->serve('env');
        $dirServ = $this->please->serve('dir');
        $tplServ = $this->please->serve('template');
        $startTime = microtime(true);

        foreach ($paths as $path) {
            $key = substr(md5(json_encode($path)), 0, 8);
            $filepath = $dirServ->dirPath("public/assets/css-js/$key.min.css");

            if (file_exists($filepath)) {
                return $this->getAssetFullContentTag($key, 'css');
            }

            $v = '?v=' . rand(0, 999);
            $cleanPath = str_replace('|v', '', $path);
            $isHTTP = stripos($cleanPath, 'http') !== false;

            $fullPath = !is_null($theme) ?
                $dirServ->dirPath("public/assets/css/$cleanPath.$extension") : ($isHTTP ? $cleanPath . '.' . $extension : $this->getCDN($cleanPath . '.' . $extension));

            $content = file_get_contents($fullPath . ($isHTTP ? $v : '')) . PHP_EOL;
            $minifiedCss = $this->getMinifier()->getMinifiedCss($content);
            $executionTime = microtime(true) - $startTime;
            $this->log->reWrite('assets', ['filename' => $path, 'minifiedCss' => $minifiedCss, 'content' => $minifiedCss], $executionTime);

            $content = str_ireplace([
                'CDN_ORIGIN',
                'APP_ORIGIN'
            ], [
                $envServ->getAppEnv('CDN_ORIGIN'),
                $envServ->getAppEnv('APP_ORIGIN') . '/' . $envServ->getAppEnv('APP_PREFIX')
            ], $minifiedCss);

            $content = $tplServ->sanitizeViewLinks($content);
            file_put_contents($filepath, $content);

            return $this->getAssetFullContentTag($key, 'css');
        }

        return '';
    }

    public function getAssetsRaw($path, $extension = 'css', $reset = false)
    {
        return $this->getCDNraw($path, $extension, null, $reset);
    }

    public function getAppCss($reset = false)
    {
        // Code commenté conservé tel quel pour compatibilité future
        // $dirServ = $this->please->serve('dir');
        // $fs = new FileSystem();
        // $scssContents = file_get_contents( $dirServ->dirPath('public/assets/scss/app.scss') );
        // $key = md5('app');
        // $mixedCssFilepath = $dirServ->dirPath("public/assets/css-js/$key.min.css");
        // if( $reset === true ){
        //     $fs->remove($mixedCssFilepath);
        // }
        // $scssContentsMd5 = $this->please->getGlobal('scssContentsMd5');
        // if( $scssContentsMd5 && $scssContentsMd5 != md5($scssContents) ){
        //     $fs->remove($mixedCssFilepath);
        // }
        // //
        // if (!$fs->exists($mixedCssFilepath)) {
        //     //$fs->appendToFile( $mixedCssFilepath, (new __PhpHtmlCssJsMinifierService())->getMinifiedCss((new Compiler())->compile($scssContents)) );
        //     $this->please->setGlobal(['scssContentsMd5' => md5($scssContents) ]);
        // }
        // //
        // //$cssOutput = "<style>".file_get_contents($this->please->serve("url")->getUrl("assets/css-js/$key.min.css"))."</style>"."\n";
        // $cssOutput = "<style>".file_get_contents($mixedCssFilepath)."</style>"."\n";
        // return $cssOutput;
        // return new Markup($cssOutput, 'UTF-8');
    }

    public function getLess(array $files = [], bool $raw = true, bool $backOffice = false)
    {
        $resetAssets = $this->please->getGlobal('resetAssets');
        $cacheKey = [$files, $raw, $backOffice, in_array('less', $resetAssets) ? uniqid() : null];

        $style = $this->cache->do(function () use ($files, $raw, $backOffice) {
            $envServ = $this->please->serve('env');
            $dirServ = $this->please->serve('dir');
            $tplServ = $this->please->serve('template');
            $minifier = $this->getMinifier();

            $content = '';
            $files = array_merge($files, $raw === false ? ['app'] : []);
            $startTime = microtime(true);

            // Traitement optimisé des fichiers LESS
            foreach ($files as $filename) {
                $file = $dirServ->dirPath("public/assets/less/$filename.less");
                if (file_exists($file)) {
                    $fileContent = file_get_contents($file);
                    $cssContent = $minifier->getMinifiedCss($fileContent);
                    $executionTime = microtime(true) - $startTime;
                    $this->log->reWrite('assets', ['filename' => "$filename.less", 'content' => $cssContent], $executionTime);

                    // Optimisation des remplacements avec un seul passage
                    $cssContent = str_ireplace([
                        'CDN_ORIGIN',
                        'APP_ORIGIN'
                    ], [
                        $envServ->getAppEnv('CDN_ORIGIN'),
                        $envServ->getAppEnv('APP_ORIGIN') . '/' . $envServ->getAppEnv('APP_PREFIX')
                    ], $cssContent);

                    $cssContent = $tplServ->sanitizeViewLinks($cssContent);
                    $content .= $cssContent;
                }
            }

            $sha1 = sha1($content);
            $cacheDir = $dirServ->dirPath("var/cache/less");
            $lessname = $backOffice ? 'getless-backoffice' : 'getless';
            $cacheFile = "$cacheDir/$lessname-$sha1.cache";

            // Création optimisée du répertoire cache
            if (!is_dir($cacheDir)) {
                $this->filesystem->mkdir($cacheDir, 0777);
            }

            $compilation = null;
            if (file_exists($cacheFile)) {
                $compilation = json_decode(file_get_contents($cacheFile), true);
                $executionTime = microtime(true) - $startTime;
                $this->log->reWrite('assets', ['filename' => "$lessname-$sha1.cache", 'cacheFile' => $cacheFile, 'content' => $compilation], $executionTime);
            }

            if (!($compilation['css'] ?? null)) {
                $compilation = $this->please->getRemoteLessCompilation($content, $lessname, 0);

                // ✅ Met en cache UNIQUEMENT si le CSS n'est PAS vide
                if (!empty($compilation['css']) && trim($compilation['css']) !== '') {
                    file_put_contents($cacheFile, json_encode($compilation));
                }
            }

            $css = $compilation['css'];

            // Vérifie si le CSS est vide
            if (empty($css) || trim($css) === '') {
                // Retourne une chaîne vide ou un fallback
                return '';
            }

            $id = substr($compilation['id'], 0, 10);
            $filename = "$id.min.css";
            $dirPath = $dirServ->dirPath("public/assets/css-js");
            if (!is_dir($dirPath)) {
                $this->filesystem->mkdir($dirPath, 0777);
            }
            $filepath = $dirPath . '/' . $filename;

            file_put_contents($filepath, $css);

            return sprintf(
                '<link class="app-main-style" rel="stylesheet" href="%s" data-sha1="%s" media="print" onload="this.media=\'all\'">',
                $this->please->serve('url')->getUrl("assets/css-js/$filename?v=1" . rand(0, 999)),
                $sha1
            );
        }, $cacheKey);

        return new Markup($style, 'UTF-8');
    }

    public function getAppConfig($jsonify = true)
    {
        // Cache de la configuration pour éviter les recalculs
        static $cachedConfig = null;

        if ($cachedConfig === null) {
            $post = $this->please->getGlobal('post');
            $envServ = $this->please->serve('env');
            $urlServ = $this->please->serve('url');
            $mobile_detect = $this->please->serve('mobile_detect');

            // Recherche optimisée du répertoire SFM
            $sfmDirname = $this->findSfmDirectory();

            $cachedConfig = [
                'postId' => attr($post, 'id'),
                'collName' => attr($post, 'coll_name'),
                'name' => $envServ->getAppEnv('APP_NAME'),
                'env' => $envServ->getAppEnv(),
                'dir' => $envServ->getAppDir(),
                'cdnHost' => $envServ->getAppEnv('CDN_ORIGIN'),
                'lessCompilerOrigin' => rtrim($envServ->getAppEnv('LESS_COMPILER_ORIGIN'), '/') . '/',
                'enableCache' => $envServ->getAppEnv('DO_CACHE'),
                'sfmDirName' => $sfmDirname,
                'isBo' => $urlServ->isBackoffice(),
                'isDesktop' => $mobile_detect->isDesktop(),
                'isNotDesktop' => $mobile_detect->isNotDesktop(),
                'isNotDesktop' => $mobile_detect->isNotDesktop(),
                'cdnFileOrigin' => $envServ->getAppEnv('CDN_FILE_ORIGIN'),
            ];
        }

        return $jsonify ? new Markup(json_encode($cachedConfig), 'UTF-8') : $cachedConfig;
    }

    /**
     * Recherche optimisée du répertoire SFM
     */
    private function findSfmDirectory(): string
    {
        $dirServ = $this->please->serve('dir');

        // Recherche directe du répertoire 'fg' en premier (plus probable)
        if (file_exists($dirServ->dirPath('fg'))) {
            return 'fg';
        }

        // Recherche des autres versions si nécessaire
        for ($v = 1; $v <= 5; $v++) {
            $name = 'sfm-v' . $v;
            if (file_exists($dirServ->dirPath($name))) {
                return $name;
            }
        }

        return '';
    }

    public function getImage(
        string $alt = '',
        $src = null,
        $width = null,
        $height = null,
        $mode = 'r',
        $classNames = '',
        $lazy = true,
        $attr = null,
        $extension = 'jpeg',
        $backgroundColor = [255, 255, 255]
    ) {
        $resizedSrc = $this->resizeImage(
            $src,
            $width,
            $height,
            $mode,
            $extension,
            $backgroundColor
        );

        if ($lazy) {
            $lazySrc = $this->resizeImage(
                $this->please->serve('env')->getAppEnv('LAZY_LOAD_IMAGE_PLACEHOLDER'),
                $width,
                $height,
                $mode,
                $extension,
                $extension
            );
        } else {
            $lazySrc = $resizedSrc;
        }

        return sprintf(
            '<img alt="%s" width="%s" height="%s" src="%s" data-lazy-src="%s" loading="lazy" class="%s" %s>',
            htmlspecialchars($alt),
            $width,
            $height,
            $lazySrc,
            $resizedSrc,
            $classNames,
            $attr
        );
        return new Markup($content, 'UTF-8');
    }

    public function resizeImage(
        string $src,
        $width = null,
        $height = null,
        $mode = 'r',
        $extension  = "jpeg",
        $backgroundColor = [255, 255, 255]
    ) {
        return $this->please->serve('image')->resize($src, $width, $height, $mode, $extension, $backgroundColor);
    }

    public function getPicture(
        string $alt = '',
        string $src,
        array $mediumSizes,
        array $querySizes = [],
        $mode = 'r',
        $classNames = '',
        $lazy = true,
        $extension = 'jpeg',
        $backgroundColor = [255, 255, 255]
    ) {
        $pic = '<picture>';

        // Traitement optimisé des tailles de requête
        foreach ($querySizes as $maxW => $imgSizes) {
            [$w, $h] = $imgSizes;
            $resizedSrc = $this->resizeImage($src, $w, $h, $mode, $extension, $backgroundColor);
            $style = $lazy ? "style=\"width:{$w}px;height:{$h}px\"" : '';

            $pic .= sprintf(
                '<source %s width="%s" height="%s" class="%s" srcset="%s" media="(max-width:%spx)">',
                $style,
                $w,
                $h,
                $classNames,
                $resizedSrc,
                $maxW
            );
        }

        // Image principale
        [$w, $h] = $mediumSizes;
        $resizedSrc = $this->resizeImage($src, $w, $h, $mode, $extension, $backgroundColor);

        $pic .= sprintf(
            '<img alt="%s" width="%s" height="%s" srcset="%s" class="%s" loading="lazy">',
            htmlspecialchars($alt),
            $w,
            $h,
            $resizedSrc,
            $classNames
        );

        $pic .= '</picture>';
        return $pic;

        return new Markup($content, 'UTF-8');
    }

    public function smartImage(
        $item = null,
        $filename = 'uploads/placeholder.png',
        $width = null,
        $height = null,
        $mode = 'r',
        $backgroundColor = [255, 255, 255]
    ): string {
        $url_service = $this->please->serve('url');

        // Recherche optimisée de l'image avec fallbacks
        $imageSources = [
            attr($item, 'image.meta.relative_url'),
            attr($item, 'meta.relative_url'),
            acf($item, 'props.image'),
            acf($item, 'props.photo'),
            attr($item, 'images.0'),
            attr($item, 'image'),
            $url_service->getUrl($filename),
            $this->please->serve('env')->getAppEnv('LAZY_LOAD_IMAGE_PLACEHOLDER')
        ];

        foreach ($imageSources as $source) {
            if ($source && !is_array($source)) {
                $src = $this->fileCdnOrigin ? trim($this->fileCdnOrigin, "/")  . "/$source" : $url_service->getUrl($source);
                if ($width && $height) {
                    $src = $this->resizeImage($src, $width, $height, $mode, "jpeg", $backgroundColor);
                }
                return $src;
            }
        }

        // Fallback par défaut
        return $url_service->getUrl($filename);
    }

    public function versionize($assets)
    {
        $assets = array_map(fn($a) => $a . '|v', $assets);
        return $assets;
    }

    public function resetAssets()
    {
        $resetAssets = $this->please->getGlobal('resetAssets');
        $docReset = !empty($resetAssets) ? uniqid() : false;
        return $docReset;
    }

    /**
     * Version optimisée de getMixedAssets avec traitement par lots
     */
    private function getMixedAssets($paths, $extension, $isCDN = null, $enableCache = true)
    {
        $cache_key = [$paths, $extension, $isCDN, $enableCache ?: uniqid()];

        return $this->cache->do(function () use ($paths, $extension, $isCDN) {
            if (empty($paths)) {
                return null;
            }

            $envServ = $this->please->serve("env");
            $dirServ = $this->please->serve("dir");
            $urlServ = $this->please->serve("url");
            $tplServ = $this->please->serve("template");

            $minifier = $this->getMinifier();
            $mixedKey = substr(md5(json_encode($paths)), 0, 8);
            $mixedFilePath = $dirServ->dirPath("public/assets/css-js/$mixedKey.min.$extension");

            // Si le fichier existe déjà, retourner le tag directement
            // if (file_exists($mixedFilePath)) {
            //     return $this->getAssetFullContentTag($mixedKey, $extension, $paths);
            // }

            $contents = '';
            $pathCount = count($paths);
            $startTime = microtime(true);

            foreach ($paths as $i => $path) {
                $assetInfo = $this->addVersionToAsset($path);

                $fullPath = stripos($assetInfo['path'], 'http') === false ?
                    ($isCDN ? $this->getCDN($assetInfo['path'] . '.' . $extension) :
                        $dirServ->dirPath("public/assets/$extension/" . $assetInfo['path'] . '.' . $extension)) :
                    $assetInfo['path'];

                if (file_exists($fullPath) || stripos($fullPath, 'http') !== false) {
                    $contents .= file_get_contents($fullPath) . PHP_EOL;
                    $executionTime = microtime(true) - $startTime;
                    $this->log->reWrite('assets', ['filename' => $path, 'fullPath' => $fullPath, 'content' => $contents], $executionTime);
                }

                // Traitement final uniquement à la dernière itération
                if ($i === $pathCount - 1) {
                    $processedContent = $this->processAssetContent($contents, $extension, $envServ, $tplServ);
                    file_put_contents($mixedFilePath, $processedContent, LOCK_EX);
                    $executionTime = microtime(true) - $startTime;
                    $this->log->reWrite('assets', ['filename' => $path, 'mixedFilePath' => $mixedFilePath, 'content' => $processedContent], $executionTime);
                    return $this->getAssetFullContentTag($mixedKey, $extension, $paths);
                }
            }

            return null;
        }, $cache_key);
    }

    /**
     * Version optimisée de processAssetContent avec gestion du template service
     */
    private function processAssetContent(string $content, string $extension, $envServ, $tplServ = null): string
    {
        if ($extension === 'css') {
            $minifier = $this->getMinifier();
            $content = $minifier->getMinifiedCss($content);

            // Optimisation des remplacements multiples en un seul passage
            $replacements = [
                'CDN_ORIGIN' => $envServ->getAppEnv('CDN_ORIGIN'),
                'APP_ORIGIN' => $envServ->getAppEnv('APP_ORIGIN') . '/' . $envServ->getAppEnv('APP_PREFIX')
            ];

            $content = str_ireplace(array_keys($replacements), array_values($replacements), $content);

            if ($tplServ) {
                $content = $tplServ->sanitizeViewLinks($content);
            } else {
                $content = $this->please->serve('template')->sanitizeViewLinks($content);
            }
        }

        return $content;
    }

    /**
     * Version optimisée de cleanHeadAssets avec gestion des paramètres optionnels
     */
    private function cleanHeadAssets($prevent, $oldCollection, $resetToken = null)
    {
        $cacheKey = [$prevent, $oldCollection, $resetToken];

        return $this->cache->do(function () use ($prevent, $oldCollection) {
            if (empty($prevent)) {
                return $oldCollection;
            }

            $newCollection = $oldCollection;
            foreach ($prevent as $needle) {
                $input = preg_quote($needle, '~');
                $filtered = preg_grep('~' . $input . '~', $newCollection);
                if ($filtered) {
                    $newCollection = array_diff($newCollection, $filtered);
                }
            }

            return array_values($newCollection);
        }, $cacheKey);
    }

    /**
     * Version optimisée de getAssetFullContentTag avec moins de cache imbriqué
     */
    private function getAssetFullContentTag($mixedKey, $type, $paths = [])
    {
        $urlServ = $this->please->serve("url");
        $url = $urlServ->getUrl("assets/css-js/{$mixedKey}.min.$type?v=" . rand(0, 999));
        $pathsJson = !empty($paths) ? json_encode($paths) : '[]';

        if ($type === 'css') {
            return sprintf(
                '<link rel="stylesheet" href="%s" data-links=\'%s\' media="print" onload="this.media=\'all\'">',
                $url,
                $pathsJson
            );
        } else {
            return sprintf(
                '<script defer src="%s" data-links=\'%s\'></script>',
                $url,
                $pathsJson
            );
        }

        return new Markup($content, 'UTF-8');
    }
}
